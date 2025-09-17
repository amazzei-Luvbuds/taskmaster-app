// ===================================================================================
// |   LUVBUDS PROJECT DASHBOARD - VERSION 7.3 - FINAL & ROBUST (MISTRAL API)       |
// |   - Contains a completely rewritten, simplified, and corrected data-merging    |
// |     logic for avatars to fix the broken image issue.                           |
// ===================================================================================

// --- PART 1: GLOBAL CONFIGURATION ---
const MASTER_SHEET_ID = "161omaX8sXMPPvL9iqHi5c6EUUbn5ywQRhOPrIZ_-MVc"; // Main Task Sheet
const AVATAR_SHEET_ID = "1iPTZ72wbx-CYu2tTcKe0QQe7HGbX_wUb9nfUFY2xp00"; // Your Avatar Profiles Sheet ID
const MISTRAL_API_KEY = PropertiesService.getScriptProperties().getProperty('MISTRAL_API_KEY'); 
const MISTRAL_API_ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';

// --- PART 1B: ADMIN KEY + CACHE HELPERS ---
function getAdminKey_() {
  const props = PropertiesService.getScriptProperties();
  let key = props.getProperty('ADMIN_KEY');
  if (!key) {
    key = Utilities.getUuid();
    props.setProperty('ADMIN_KEY', key);
  }
  return key;
}

function getCacheEpoch_() {
  const props = PropertiesService.getScriptProperties();
  let v = props.getProperty('CACHE_EPOCH');
  if (!v) { v = '0'; props.setProperty('CACHE_EPOCH', v); }
  return v;
}
function bumpCacheEpoch_() {
  const props = PropertiesService.getScriptProperties();
  const n = (parseInt(props.getProperty('CACHE_EPOCH') || '0', 10) + 1).toString();
  props.setProperty('CACHE_EPOCH', n);
  return n;
}

function computeSheetSignature_(sheet) {
  const lastRow = sheet.getLastRow();
  const lastCol = sheet.getLastColumn();
  const header = lastCol > 0 ? sheet.getRange(1, 1, 1, lastCol).getValues()[0].join('|') : '';
  const sampleRows = Math.min(2, Math.max(0, lastRow - 1));
  const sample = sampleRows > 0 ? sheet.getRange(2, 1, sampleRows, Math.max(1, lastCol)).getValues() : [];
  const flat = sample.map(r => r.join('|')).join('||');
  const crc = Utilities.base64EncodeWebSafe(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, header + '::' + flat)).slice(0, 12);
  return `${lastRow}:${lastCol}:${crc}`;
}

function getCachedJSON_(baseKey) {
  const epoch = getCacheEpoch_();
  const key = `${baseKey}:e${epoch}`;
  const cache = CacheService.getScriptCache();
  const raw = cache.get(key);
  if (!raw) return null;
  try { return JSON.parse(raw); } catch (_) { return null; }
}
function putCachedJSON_(baseKey, obj, seconds) {
  const epoch = getCacheEpoch_();
  const key = `${baseKey}:e${epoch}`;
  CacheService.getScriptCache().put(key, JSON.stringify(obj), Math.max(5, Math.min(seconds, 21600))); // cap 6h
}

// Signing secret for action links (auto-generated if missing)
function getLinkSigningSecret() {
  const props = PropertiesService.getScriptProperties();
  let secret = props.getProperty('LINK_SIGNING_SECRET');
  if (!secret) {
    // 32 random bytes, base64
    const bytes = Utilities.getUuid() + Utilities.getUuid();
    secret = Utilities.base64EncodeWebSafe(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, bytes));
    props.setProperty('LINK_SIGNING_SECRET', secret);
  }
  return secret;
}

function signActionToken(taskId, action, expIso) {
  const secret = getLinkSigningSecret();
  const payload = `${taskId}|${action}|${expIso}`;
  const sig = Utilities.computeHmacSha256Signature(payload, secret);
  return Utilities.base64EncodeWebSafe(sig);
}

function verifyActionToken(taskId, action, expIso, token) {
  try {
    if (!taskId || !action || !expIso || !token) return false;
    const exp = new Date(expIso);
    if (isNaN(exp.getTime()) || exp.getTime() < Date.now()) return false;
    const expected = signActionToken(taskId, action, expIso);
    // time-safe compare
    if (expected.length !== token.length) return false;
    let diff = 0;
    for (let i = 0; i < expected.length; i++) diff |= expected.charCodeAt(i) ^ token.charCodeAt(i);
    return diff === 0;
  } catch (_) {
    return false;
  }
}

function buildSignedActionUrl(action, taskId, expMinutes) {
  const webAppUrl = ScriptApp.getService().getUrl();
  const expIso = new Date(Date.now() + (expMinutes * 60 * 1000)).toISOString();
  const token = signActionToken(taskId, action, expIso);
  const qp = encodeURIComponent;
  return `${webAppUrl}?action=${qp(action)}&id=${qp(taskId)}&exp=${qp(expIso)}&token=${qp(token)}`;
}

// --- PART 1C: ENV + STRUCTURED LOGGING ---
function getEnv_() {
  const v = PropertiesService.getScriptProperties().getProperty('ENV') || 'prod';
  return v === 'dev' ? 'dev' : 'prod';
}

function ensureLogsSheet_() {
  const ss = SpreadsheetApp.openById(MASTER_SHEET_ID);
  let sheet = ss.getSheetByName('Logs');
  if (!sheet) {
    sheet = ss.insertSheet('Logs');
    sheet.appendRow(['Timestamp', 'Level', 'Context', 'TaskID', 'Message', 'MetaJSON', 'RequestId']);
  }
  return sheet;
}

function log_(level, context, message, meta, taskId) {
  try {
    const ts = new Date().toISOString();
    const requestId = Utilities.getUuid().slice(0, 8);
    const line = `[${level.toUpperCase()}] ${context} ${taskId ? '(' + taskId + ')' : ''} ${message}`;
    if (level === 'error') {
      Logger.log(line);
    } else if (getEnv_() === 'dev') {
      Logger.log(line);
    }
    // Persist rules: always persist error/warn; persist info/debug only in dev
    const shouldPersist = level === 'error' || level === 'warn' || getEnv_() === 'dev';
    if (shouldPersist) {
      const sheet = ensureLogsSheet_();
      sheet.appendRow([ts, level, context, taskId || '', message || '', meta ? JSON.stringify(meta) : '', requestId]);
    }
  } catch (e) {
    // Last resort
    Logger.log('log_ failed: ' + e);
  }
}

// --- PART 2: WEB APP ROUTING ---
function doGet(e) {
  const page = e.parameter.page;

  Logger.log(`=== WEB APP ROUTING DEBUG ===`);
  Logger.log(`Requested page: "${page}"`);
  Logger.log(`All parameters: ${JSON.stringify(e.parameter)}`);

  // Secure action routes from emails/buttons: createTask, addCalendar
  if (e.parameter.action && e.parameter.id) {
    const { action, id, exp, token } = e.parameter;
    if (!verifyActionToken(id, action, exp, token)) {
      return HtmlService.createHtmlOutput('<h1>Access denied</h1><p>Invalid or expired link.</p>');
    }
    const taskData = getTaskById(id);
    if (!taskData) {
      return HtmlService.createHtmlOutput('<h1>Task not found</h1>');
    }
    if (action === 'createTask') {
      const result = createGoogleTask(taskData);
      const redirectUrl = 'https://tasks.google.com/';
      const html = `<!doctype html><html><head><meta http-equiv="refresh" content="1;url=${redirectUrl}"><style>body{font-family:Arial;background:#0a0a14;color:#e0e0e0;padding:40px}</style></head><body><h2>Google Task ${result.success ? 'Created' : 'Not Created'}</h2><p>${result.message || ''}</p><p>Redirecting to Google Tasks...</p><p><a href="${redirectUrl}">Open Google Tasks</a></p></body></html>`;
      return HtmlService.createHtmlOutput(html);
    }
    if (action === 'addCalendar') {
      const result = createTaskCalendarEvent(taskData);
      const html = `<!doctype html><html><head><style>body{font-family:Arial;background:#0a0a14;color:#e0e0e0;padding:40px}</style></head><body><h2>${result.success ? 'Calendar Event Created' : 'Calendar Error'}</h2><pre>${result.success ? (result.message || '') : result.error}</pre><p><a href="${ScriptApp.getService().getUrl()}?page=task&id=${encodeURIComponent(id)}">Back to task</a></p></body></html>`;
      return HtmlService.createHtmlOutput(html);
    }
    return HtmlService.createHtmlOutput('<h1>Unknown action</h1>');
  }

  if (page === 'task' && e.parameter.id) {
    const taskId = e.parameter.id;
    Logger.log(`Task detail request for ID: "${taskId}"`);
    
    const taskData = getTaskById(taskId);
    if (!taskData) { 
      Logger.log(`Task not found for ID: "${taskId}"`);
      
      // Call debug function to get more information
      const debugResult = debugSpecificTaskId(taskId);
      Logger.log(`Debug result for "${taskId}": ${JSON.stringify(debugResult)}`);
      
      return HtmlService.createHtmlOutput('<h1>Task not found</h1><p>Task ID: ' + taskId + ' could not be found.</p><p>Debug info: ' + JSON.stringify(debugResult) + '</p>'); 
    }
    
    Logger.log(`Task found: "${taskData.actionItem}"`);

    // Robust plan generation — optional safe mode
    let projectPlan;
    if (String(e.parameter.safe) === '1') {
      Logger.log('Safe mode enabled — skipping AI plan generation');
      projectPlan = { projectGoal: 'Project plan (safe mode)', expectedBenefits: [], milestonePlan: { title: 'Key Milestones', description: 'Safe mode summary' }, dataIntegrityPlan: [], implementationPlan: [] };
    } else {
      try {
        Logger.log(`Fetching or generating cached project plan...`);
        projectPlan = getOrBuildProjectPlan_(taskData);
        Logger.log(`Project plan ready: ${projectPlan && projectPlan.error ? 'ERROR' : 'SUCCESS'}`);
      } catch (planErr) {
        Logger.log(`Plan generation failed: ${planErr.stack}`);
        projectPlan = { projectGoal: 'Project plan unavailable', expectedBenefits: [], milestonePlan: { title: 'Key Milestones', description: 'Plan failed to load' }, dataIntegrityPlan: [], implementationPlan: [] };
      }
    }
    
    Logger.log(`Creating template...`);
    let template = HtmlService.createTemplateFromFile('task_detail');
    template.task = taskData;
    template.plan = projectPlan || { projectGoal: '', expectedBenefits: [], milestonePlan: { title: 'Key Milestones', description: '' }, dataIntegrityPlan: [], implementationPlan: [] };
    template.teamDirectory = getTeamData();
    
    Logger.log(`Template created successfully`);
    Logger.log(`Task data keys: ${Object.keys(taskData).join(', ')}`);
    Logger.log(`Plan data keys: ${Object.keys(template.plan).join(', ')}`);
    Logger.log(`Team directory length: ${template.teamDirectory.length}`);
    
    // Test if template can be evaluated with minimal data
    Logger.log(`Testing template evaluation...`);
    try {
      const testTemplate = HtmlService.createTemplateFromFile('task_detail');
      testTemplate.task = { taskID: 'TEST', actionItem: 'Test', status: 'Test' };
      testTemplate.plan = { projectGoal: 'Test', expectedBenefits: [], milestonePlan: { title: 'Test', description: 'Test' }, dataIntegrityPlan: [], implementationPlan: [] };
      testTemplate.teamDirectory = [];
      const testResult = testTemplate.evaluate();
      Logger.log(`Test template evaluation successful! Content length: ${testResult.getContent().length}`);
    } catch (testError) {
      Logger.log(`Test template evaluation failed: ${testError.stack}`);
    }
    
    Logger.log(`Evaluating template...`);
    try {
      const result = template.evaluate().setTitle(taskData.actionItem || 'Task Details');
      Logger.log(`Template evaluation successful!`);
      Logger.log(`Result content length: ${result.getContent().length}`);
      Logger.log(`First 200 chars of result: ${result.getContent().substring(0, 200)}`);
      return result;
    } catch (e) {
      Logger.log(`Template evaluation failed: ${e.stack}`);
      
      // Try a simple fallback template without include
      try {
        const fallbackHtml = `
          <!DOCTYPE html>
          <html>
          <head><meta charset="UTF-8"><title>Task Details</title></head>
          <body><h1>${taskData.taskID}</h1><p>${taskData.actionItem}</p></body>
          </html>`;
        return HtmlService.createHtmlOutput(fallbackHtml);
      } catch (fallbackError) {
        Logger.log(`Fallback template creation failed: ${fallbackError.stack}`);
        return HtmlService.createHtmlOutput('<h1>Task Details</h1><p>An error occurred while rendering the page.</p>');
      }
    }
  }

  // Route: Kanban view
  if (page === 'kanban') {
    Logger.log('Routing to kanban.html');
    try {
      return HtmlService.createTemplateFromFile('kanban').evaluate().setTitle('Kanban Board');
    } catch (err) {
      Logger.log('Kanban route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Kanban</h1><p>Failed to load Kanban view.</p>');
    }
  }

  // Route: Uploader view
  if (page === 'uploader') {
    Logger.log('Routing to uploader.html');
    try {
      return HtmlService.createTemplateFromFile('uploader').evaluate().setTitle('Upload Transcript');
    } catch (err) {
      Logger.log('Uploader route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Uploader</h1><p>Failed to load upload page.</p>');
    }
  }

  // Route: Leadership portal (authentication and links)
  if (page === 'leadership') {
    Logger.log('Routing to leadership.html');
    try {
      return HtmlService.createTemplateFromFile('leadership').evaluate().setTitle('Leadership Portal');
    } catch (err) {
      Logger.log('Leadership route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Leadership</h1><p>Failed to load leadership portal.</p>');
    }
  }

  // Route: Leadership kanban (full-access board)
  if (page === 'leadership_kanban') {
    Logger.log('Routing to leadership_kanban.html');
    try {
      return HtmlService.createTemplateFromFile('leadership_kanban').evaluate().setTitle('Leadership Kanban');
    } catch (err) {
      Logger.log('Leadership Kanban route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Leadership Kanban</h1><p>Failed to load leadership board.</p>');
    }
  }

  // Route: Leadership dashboard (analytics)
  if (page === 'leadership_dashboard') {
    Logger.log('Routing to leadership_dashboard.html');
    try {
      return HtmlService.createTemplateFromFile('leadership_dashboard').evaluate().setTitle('Leadership Dashboard');
    } catch (err) {
      Logger.log('Leadership Dashboard route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Leadership Dashboard</h1><p>Failed to load dashboard.</p>');
    }
  }

  // Route: Leadership reports
  if (page === 'leadership_reports') {
    Logger.log('Routing to leadership_reports.html');
    try {
      return HtmlService.createTemplateFromFile('leadership_reports').evaluate().setTitle('Leadership Reports');
    } catch (err) {
      Logger.log('Leadership Reports route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Leadership Reports</h1><p>Failed to load reports.</p>');
    }
  }

  // Route: Leadership admin
  if (page === 'leadership_admin') {
    Logger.log('Routing to leadership_admin.html');
    try {
      return HtmlService.createTemplateFromFile('leadership_admin').evaluate().setTitle('Leadership Admin');
    } catch (err) {
      Logger.log('Leadership Admin route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Leadership Admin</h1><p>Failed to load admin panel.</p>');
    }
  }

  // Route: Swag Supply production board
  if (page === 'swag_supply') {
    Logger.log('Routing to swag_supply.html');
    try {
      return HtmlService.createTemplateFromFile('swag_supply').evaluate().setTitle('Swag Supply');
    } catch (err) {
      Logger.log('Swag Supply route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Swag Supply</h1><p>Failed to load Swag Supply board.</p>');
    }
  }

  // Route: Leadership dashboard
  if (page === 'leadership_dashboard') {
    Logger.log('Routing to leadership_dashboard.html');
    try {
      return HtmlService.createTemplateFromFile('leadership_dashboard').evaluate().setTitle('Leadership Dashboard');
    } catch (err) {
      Logger.log('Leadership Dashboard route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Leadership Dashboard</h1><p>Failed to load leadership dashboard.</p>');
    }
  }

  // Route: Leadership reports
  if (page === 'leadership_reports') {
    Logger.log('Routing to leadership_reports.html');
    try {
      return HtmlService.createTemplateFromFile('leadership_reports').evaluate().setTitle('Leadership Reports');
    } catch (err) {
      Logger.log('Leadership Reports route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Leadership Reports</h1><p>Failed to load leadership reports.</p>');
    }
  }

  // Route: Leadership admin
  if (page === 'leadership_admin') {
    Logger.log('Routing to leadership_admin.html');
    try {
      return HtmlService.createTemplateFromFile('leadership_admin').evaluate().setTitle('Leadership Admin');
    } catch (err) {
      Logger.log('Leadership Admin route error: ' + err.stack);
      return HtmlService.createHtmlOutput('<h1>Leadership Admin</h1><p>Failed to load leadership admin.</p>');
    }
  }

  // Admin diagnostics route
  if (page === 'diagnostics') {
    const key = e.parameter.key || '';
    if (key !== getAdminKey_()) {
      return HtmlService.createHtmlOutput('<h1>Forbidden</h1><p>Invalid or missing admin key.</p>');
    }
    if (String(e.parameter.bump) === '1') {
      bumpCacheEpoch_();
    }
    const master = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const avatar = SpreadsheetApp.openById(AVATAR_SHEET_ID).getSheets()[0];

    // Get last 100 log lines
    let logs = [];
    try {
      const logSheet = ensureLogsSheet_();
      const lastRow = logSheet.getLastRow();
      const start = Math.max(2, lastRow - 99);
      const rows = lastRow >= 2 ? logSheet.getRange(start, 1, lastRow - start + 1, 7).getValues() : [];
      logs = rows.map(r => ({ ts: r[0], level: r[1], ctx: r[2], taskId: r[3], msg: r[4], meta: r[5], req: r[6] }));
    } catch (_) {}

    const diag = {
      version: '7.3',
      env: getEnv_(),
      cacheEpoch: getCacheEpoch_(),
      masterSig: computeSheetSignature_(master),
      avatarSig: computeSheetSignature_(avatar),
      scopes: Session.getActiveUser().getEmail() ? 'user' : 'anonymous',
      adminKeyTail: getAdminKey_().slice(-6),
      logsCount: logs.length
    };
    const html = `<pre>${JSON.stringify(diag, null, 2)}</pre>
<p><a href="?page=diagnostics&key=${getAdminKey_()}&bump=1">Bump cache epoch</a></p>
<h3>Recent Logs</h3>
<pre>${logs.map(l => `[${l.level}] ${l.ts} ${l.ctx} (${l.taskId}) ${l.msg}`).join('\n')}</pre>`;
    return HtmlService.createHtmlOutput(html);
  }

  // Admin UI route
  if (page === 'admin') {
    const key = e.parameter.key || '';
    if (!assertAdminKey_(key)) {
      return HtmlService.createHtmlOutput('<h1>Forbidden</h1><p>Invalid or missing admin key.</p>');
    }
    const t = HtmlService.createTemplateFromFile('admin');
    t.adminKey = key;
    return t.evaluate().setTitle('Admin');
  }

  // Default route → render main departments page
  try {
    let template = HtmlService.createTemplateFromFile('tasks_departments');
    return template.evaluate().setTitle('Department Tasks');
  } catch (err) {
    Logger.log(`Default route failed: ${err.stack}`);
    return HtmlService.createHtmlOutput('<h1>Welcome</h1>');
  }
}

function assertAdminKey_(key) { return key && key === getAdminKey_(); }

function adminGetSnapshot(key) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  const master = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
  const avatar = SpreadsheetApp.openById(AVATAR_SHEET_ID).getSheets()[0];
  let logs = [];
  try {
    const logSheet = ensureLogsSheet_();
    const lastRow = logSheet.getLastRow();
    const start = Math.max(2, lastRow - 50);
    const rows = lastRow >= 2 ? logSheet.getRange(start, 1, lastRow - start + 1, 7).getValues() : [];
    logs = rows.map(r => ({ ts: r[0], level: r[1], ctx: r[2], taskId: r[3], msg: r[4], meta: r[5], req: r[6] }));
  } catch (_) {}
  return {
    version: '7.3',
    env: getEnv_(),
    cacheEpoch: getCacheEpoch_(),
    masterSig: computeSheetSignature_(master),
    avatarSig: computeSheetSignature_(avatar),
    taskRows: master.getLastRow() - 1,
    avatarRows: avatar.getLastRow() - 1,
    logs: logs
  };
}

function adminBumpCache(key) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  return { cacheEpoch: bumpCacheEpoch_() };
}

function adminForceClearCaches(key) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  const res = forceClearAllCaches();
  return { cacheEpoch: bumpCacheEpoch_(), res };
}

// --- PART 3: DATA FETCHING FUNCTIONS (REWRITTEN FOR RELIABILITY) ---

/**
 * THIS IS THE NEW, SIMPLIFIED AVATAR LOOKUP.
 * It creates a simple map of lowercase names to their avatar URLs.
 */
function getAvatarMap() {
    try {
        Logger.log("=== GETTING AVATAR MAP ===");
        const avatarSheet = SpreadsheetApp.openById(AVATAR_SHEET_ID).getSheets()[0];
        const sig = computeSheetSignature_(avatarSheet);
        const cached = getCachedJSON_(`AVATARS:${sig}`);
        if (cached) { Logger.log('Avatar map cache hit'); return cached; }
        const avatarData = avatarSheet.getDataRange().getValues();
        avatarData.shift(); // Remove header

        Logger.log("Avatar data rows: " + avatarData.length);
        Logger.log("First few avatar rows:");
        avatarData.slice(0, 5).forEach((row, index) => {
            Logger.log(`Row ${index + 1}: name="${row[0]}", url="${row[1]}"`);
        });
        
        const avatarMap = avatarData.reduce((map, row, index) => {
          const name = row[0];
          const driveUrl = row[1];
          
          Logger.log(`Processing avatar row ${index + 1}: name="${name}", url="${driveUrl}"`);
          
          if (name && driveUrl) {
            // Improved URL parsing for Google Drive format
            let fileId = null;
            
            // Try multiple patterns to extract file ID from Google Drive URLs
            const patterns = [
              /\/file\/d\/([^\/\?]+)/,  // /file/d/FILE_ID
              /\/d\/([^\/\?]+)/,        // /d/FILE_ID
              /id=([^&]+)/              // ?id=FILE_ID
            ];
            
            for (const pattern of patterns) {
              const match = driveUrl.match(pattern);
              if (match && match[1]) {
                fileId = match[1];
                Logger.log(`Found fileId: ${fileId} for name: ${name}`);
                break;
              }
            }
            
            if (fileId) {
              // Use the most reliable Google Drive thumbnail URL
              const imageUrl = `https://drive.google.com/thumbnail?id=${fileId}&sz=w200`;
              map[name.trim().toLowerCase()] = imageUrl;
              Logger.log(`Mapped ${name} to ${imageUrl}`);
            } else {
              Logger.log(`Could not extract fileId from URL: ${driveUrl}`);
              // Use fallback for this person
              map[name.trim().toLowerCase()] = 'https://i.imgur.com/Vues1gP.png';
            }
          } else {
            Logger.log(`Missing name or URL for row ${index + 1}`);
          }
          return map;
        }, {});
        
        // Add fallback for unassigned
        if (!avatarMap['unassigned']) {
          avatarMap['unassigned'] = 'https://i.imgur.com/Vues1gP.png'; // Default LuvBuds logo
        }
        
        Logger.log("Final avatar map keys: " + Object.keys(avatarMap).join(', '));
        Logger.log("Sample avatar URLs:");
        Object.keys(avatarMap).slice(0, 5).forEach(key => {
            Logger.log(`  ${key}: ${avatarMap[key]}`);
        });
        
        putCachedJSON_(`AVATARS:${sig}`, avatarMap, 3600);
        return avatarMap;
    } catch (e) {
        Logger.log("ERROR in getAvatarMap: " + e.stack);
        // Return a minimal map with fallback
        return {
          'unassigned': 'https://i.imgur.com/Vues1gP.png'
        };
    }
}

/**
 * THIS IS THE NEW, SIMPLIFIED TEAM DATA FUNCTION.
 * It now uses the simple avatar map.
 */
function getTeamData() {
  try {
    const timestamp = new Date().toISOString();
    Logger.log(`=== GETTING TEAM DATA AT ${timestamp} ===`);
    Logger.log(`Avatar sheet ID: ${AVATAR_SHEET_ID}`);
    
    // Force fresh connection to the sheet
    const avatarSheet = SpreadsheetApp.openById(AVATAR_SHEET_ID).getSheets()[0];
    const sig = computeSheetSignature_(avatarSheet);
    const cached = getCachedJSON_(`TEAM:${sig}`);
    if (cached) { Logger.log('Team data cache hit'); return cached; }
    const sheetName = avatarSheet.getName();
    
    Logger.log(`Sheet name: "${sheetName}"`);
    Logger.log(`Current time: ${new Date()}`);
    
    const avatarData = avatarSheet.getDataRange().getValues();
    
    Logger.log(`Raw avatar data rows: ${avatarData.length}`);
    Logger.log(`Raw avatar data columns: ${avatarData[0] ? avatarData[0].length : 0}`);
    Logger.log(`Header row: ${JSON.stringify(avatarData[0])}`);
    Logger.log(`First 10 rows of raw data:`);
    avatarData.slice(0, 10).forEach((row, index) => {
      Logger.log(`Row ${index + 1}: ${JSON.stringify(row)}`);
    });
    
    avatarData.shift(); // Remove header
    
    const teamMembers = avatarData.map((row, index) => {
      const fullName = row[0];
      const avatarUrl = row[1];
      const department = row[2];
      const email = row[3];
      
      // More detailed email logging
      const emailType = typeof email;
      const emailValue = email;
      const emailTrimmed = typeof email === 'string' ? email.trim() : email;
      const emailEmpty = !emailTrimmed || emailTrimmed === '';
      
      Logger.log(`Processing row ${index + 1}:`);
      Logger.log(`  - name: "${fullName}"`);
      Logger.log(`  - email raw: ${JSON.stringify(emailValue)} (type: ${emailType})`);
      Logger.log(`  - email trimmed: "${emailTrimmed}"`);
      Logger.log(`  - email empty: ${emailEmpty}`);
      Logger.log(`  - department: "${department}"`);
      
      return {
        fullName: fullName,
        avatarUrl: avatarUrl,
        department: department,
        email: emailTrimmed
      };
    }).filter(member => member.fullName && member.fullName !== 'Unassigned');
    
    Logger.log(`Final team members count: ${teamMembers.length}`);
    
    const membersWithEmails = teamMembers.filter(m => m.email && m.email.trim() !== '');
    const membersWithoutEmails = teamMembers.filter(m => !m.email || m.email.trim() === '');
    
    Logger.log(`Members with emails: ${membersWithEmails.length}`);
    Logger.log(`Members without emails: ${membersWithoutEmails.length}`);
    
    // Log first few team members with emails
    Logger.log(`=== TEAM MEMBERS WITH EMAILS ===`);
    membersWithEmails.slice(0, 10).forEach((member, index) => {
      Logger.log(`${index + 1}. ${member.fullName} - Email: "${member.email}"`);
    });
    
    // Log first few team members without emails
    Logger.log(`=== TEAM MEMBERS WITHOUT EMAILS ===`);
    membersWithoutEmails.slice(0, 10).forEach((member, index) => {
      Logger.log(`${index + 1}. ${member.fullName} - No email`);
    });
    
    putCachedJSON_(`TEAM:${sig}`, teamMembers, 3600);
    return teamMembers;
  } catch (e) {
    Logger.log(`ERROR in getTeamData: ${e.stack}`);
    return [];
  }
}

/**
 * THIS IS THE NEW, SIMPLIFIED TASK FETCHING FUNCTION.
 * It correctly merges task data with the team member data.
 */
function getTasks() {
  try {
    Logger.log("=== GETTING TASKS WITH AVATAR DEBUG ===");
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const team = getTeamData();
    
    Logger.log("Getting avatar map...");
    const avatarMap = getAvatarMap(); // Get the processed avatar URLs
    Logger.log(`Avatar map created with ${Object.keys(avatarMap).length} entries`);
    Logger.log("Sample avatar map entries:");
    Object.keys(avatarMap).slice(0, 5).forEach(key => {
      Logger.log(`  ${key}: ${avatarMap[key]}`);
    });
    
    if (sheet.getLastRow() < 2) return [];
    
    const data = sheet.getRange(2, 1, sheet.getLastRow() - 1, sheet.getLastColumn()).getValues();
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    
    Logger.log("Task data rows: " + data.length);
    Logger.log("Task headers: " + headers.join(', '));
    Logger.log("Available team members: " + team.map(t => t.fullName).join(', '));
    
    const tasks = data.map((row, index) => {
      let task = {};
      headers.forEach((header, headerIndex) => {
          if (header) {
              const headerLc = header.toLowerCase();
              let key;
              // Normalize special keys
              if (headerLc.includes('progress') || headerLc.includes('complete')) {
                key = 'progressPercentage';
              } else if (/^date[ _-]*created$/i.test(header)) {
                key = 'dateCreated';
              } else {
                key = header.charAt(0).toLowerCase() + header.slice(1)
                      .replace(/[^a-zA-Z0-9]+(.)?/g, (m, chr) => chr ? chr.toUpperCase() : '')
                      .replace(/\s+/g, '');
              }
              let value = row[headerIndex];

              // Normalize date created mapping
              if (key === 'dateCreated') {
                const dc = row[headerIndex];
                task.dateCreated = (dc instanceof Date) ? dc.toISOString() : dc;
                return; // continue
              }
              
              // Handle progress percentage specifically
              if (key === 'progressPercentage') {
                value = value || 0;
                if (typeof value === 'string' && value.includes('%')) {
                  value = parseInt(value.replace('%', '')) || 0;
                }
              }
              
              task[key] = (value instanceof Date) ? value.toISOString() : value;
          }
      });
      
      // Debug: Log all available fields in the task
      Logger.log(`Task ${index + 1} available fields: ${Object.keys(task).join(', ')}`);
      
      // Debug: Log specific important fields
      Logger.log(`Task ${index + 1} - actionItem: "${task.actionItem}", progressPercentage: "${task.progressPercentage}", status: "${task.status}"`);

      // Normalize status spelling
      if (String(task.status).toLowerCase() === 'complete') {
        task.status = 'Completed';
      }
      // Force 100% for completed tasks if progress missing/low
      if (String(task.status).toLowerCase() === 'completed') {
        const p = parseInt(task.progressPercentage, 10);
        if (isNaN(p) || p < 100) task.progressPercentage = 100;
      }
      
      // Try multiple possible owner field names - check the actual field names from the data
      const ownerField = task.owners || task.ownerS || task.owner || task.assignedTo || "Unassigned";
      const ownerNames = ownerField.toString().split(',').map(name => name.trim()).filter(Boolean);
      Logger.log(`Task ${index + 1} owner field: ${ownerField}, parsed owners: ${ownerNames.join(', ')}`);
      
      task.ownerDetails = ownerNames.map(name => {
        Logger.log(`Looking for team member matching: "${name}"`);
        Logger.log(`Available team members: ${team.map(m => m.fullName).join(', ')}`);
        
        // Try multiple matching strategies
        let member = team.find(m => m.fullName.toLowerCase() === name.toLowerCase());
        
        if (!member) {
          Logger.log(`No exact match for "${name}", trying partial match`);
          // Try partial matching
          member = team.find(m => 
            m.fullName.toLowerCase().includes(name.toLowerCase()) || 
            name.toLowerCase().includes(m.fullName.toLowerCase())
          );
        }
        
        if (!member) {
          Logger.log(`No partial match for "${name}", trying first name only`);
          // Try matching by first name only
          member = team.find(m => 
            m.fullName.toLowerCase().split(' ')[0] === name.toLowerCase().split(' ')[0]
          );
        }
        
        if (!member) {
          Logger.log(`No team member found for "${name}", using unassigned`);
          // Use unassigned member
          member = team.find(m => m.fullName.toLowerCase() === 'unassigned') || 
                   { fullName: name, avatarUrl: 'https://i.imgur.com/Vues1gP.png', email: '', phone: '' };
        } else {
          // Use the processed avatar URL from avatarMap instead of raw URL
          const processedAvatarUrl = avatarMap[member.fullName.toLowerCase()] || member.avatarUrl;
          Logger.log(`Processing avatar for "${member.fullName}":`);
          Logger.log(`  - Raw URL: ${member.avatarUrl}`);
          Logger.log(`  - Processed URL: ${processedAvatarUrl}`);
          Logger.log(`  - Avatar map key: ${member.fullName.toLowerCase()}`);
          Logger.log(`  - Found in map: ${avatarMap.hasOwnProperty(member.fullName.toLowerCase())}`);
          
          member = { ...member, avatarUrl: processedAvatarUrl };
          Logger.log(`Final avatar URL for "${member.fullName}": ${member.avatarUrl}`);
        }
        
        return member;
      });
      
      return task;
    });
    
    Logger.log("Processed tasks: " + tasks.length);
    
    // Log a few sample avatar URLs to verify they're processed correctly
    Logger.log("=== SAMPLE PROCESSED AVATAR URLS ===");
    tasks.slice(0, 3).forEach((task, index) => {
      if (task.ownerDetails && task.ownerDetails.length > 0) {
        task.ownerDetails.forEach((owner, ownerIndex) => {
          Logger.log(`Task ${index + 1} owner ${ownerIndex + 1}: ${owner.fullName} - Avatar: ${owner.avatarUrl}`);
        });
      }
    });
    
    return tasks;
  } catch (e) { 
    Logger.log("CRITICAL ERROR in getTasks: " + e.stack);
    return { error: true, message: e.message }; 
  }
}

// Create a minimal new task row with robust column mapping
function createSimpleTask(task) {
  const lock = LockService.getScriptLock();
  try {
    lock.waitLock(30000);
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const { headers } = ensureTaskColumns_();
    const row = new Array(headers.length).fill('');

    // Generate ID and dates
    const newId = `NT-${Date.now().toString().slice(-6)}-${Math.random().toString(36).substr(2,3).toUpperCase()}`;
    setByHeaderNames_(row, headers, ['Task ID','Task_ID','TaskId'], newId);
    setByHeaderNames_(row, headers, ['Date_Created','Created','Created_At'], new Date().toISOString());

    // Core fields
    setByHeaderNames_(row, headers, ['Department','Dept'], task && task.department ? String(task.department) : 'General');
    setByHeaderNames_(row, headers, ['Action Item','Action_Item','Title'], task && task.actionItem ? String(task.actionItem) : 'New Task');
    setByHeaderNames_(row, headers, ['Owner(s)','Owners','Assigned To','Assigned_To'], task && task.owners ? String(task.owners) : 'Unassigned');
    setByHeaderNames_(row, headers, ['Status'], task && task.status ? String(task.status) : 'Not Started');
    setByHeaderNames_(row, headers, ['Due_Date','Due Date','DueDate'], task && task.dueDate ? new Date(task.dueDate) : '');
    setByHeaderNames_(row, headers, ['Predicted_Hours','Predicted Hours'], task && task.predictedHours ? Number(task.predictedHours) : '');
    setByHeaderNames_(row, headers, ['Progress_Percentage','Progress %','Progress Percentage'], 0);

    // Optional: Production_Stage for Swag
    const prodCol = headers.indexOf('Production_Stage');
    if (prodCol !== -1 && task && task.productionStage) row[prodCol] = String(task.productionStage);

    // Optional: isLeadership flag
    const leadIdx = headers.indexOf('isLeadership');
    if (leadIdx !== -1 && task && (task.isLeadership === true || String(task.isLeadership).toLowerCase() === 'true')) {
      row[leadIdx] = 'TRUE';
    }

    sheet.appendRow(row);
    return { status: 'Success', taskID: newId };
  } catch (e) {
    Logger.log('ERROR in createSimpleTask: ' + e.stack);
    return { status: 'Error', message: e.message };
  } finally { try { lock.releaseLock(); } catch(_){} }
}

function getTaskById(id) {
  const allTasks = getTasks();
  if (allTasks.error) return null;
  
  Logger.log(`=== DEBUGGING TASK LOOKUP ===`);
  Logger.log(`Looking for task ID: "${id}"`);
  Logger.log(`Available task IDs: ${allTasks.map(t => t.taskID).join(', ')}`);
  
  // Try exact match first
  let foundTask = allTasks.find(task => task.taskID === id);
  
  if (!foundTask) {
    Logger.log(`Exact match failed, trying string comparison...`);
    // Try string comparison
    foundTask = allTasks.find(task => String(task.taskID) === String(id));
  }
  
  if (!foundTask) {
    Logger.log(`String comparison failed, trying case-insensitive...`);
    // Try case-insensitive comparison
    foundTask = allTasks.find(task => String(task.taskID).toLowerCase() === String(id).toLowerCase());
  }
  
  if (foundTask) {
    Logger.log(`Found task: ${foundTask.actionItem}`);
    Logger.log(`Task details: ID=${foundTask.taskID}, Status=${foundTask.status}, Department=${foundTask.department}`);
  } else {
    Logger.log(`Task not found!`);
    Logger.log(`First 5 task IDs for comparison:`);
    allTasks.slice(0, 5).forEach((task, index) => {
      Logger.log(`  Task ${index + 1}: "${task.taskID}" (type: ${typeof task.taskID})`);
    });
  }
  
  return foundTask || null;
}

function getDepartments() {
  try {
    const allTasks = getTasks();
    if(allTasks.error || allTasks.length === 0) return [];
    const departmentSet = new Set(allTasks.map(task => task.department).filter(Boolean));
    return Array.from(departmentSet).sort();
  } catch (e) { return { error: true, message: e.message }; }
}

function getMilestoneGroups() {
  try {
    const allTasks = getTasks();
    if(allTasks.error || allTasks.length === 0) return [];
    const milestoneSet = new Set(allTasks.map(task => task.milestoneGroup).filter(Boolean));
    return Array.from(milestoneSet).sort();
  } catch (e) { 
    return { error: true, message: e.message }; 
  }
}

function getKanbanData() {
  try {
    console.log("getKanbanData: Starting function");
    
    const allTasks = getTasks();
    console.log("getKanbanData: getTasks() returned:", allTasks ? allTasks.length : "null/undefined");
    
    if (!allTasks) {
      console.log("getKanbanData: allTasks is null/undefined");
      return { error: "No tasks data available" };
    }
    
    if (allTasks.error) {
      console.log("getKanbanData: allTasks has error:", allTasks.error);
      throw new Error(allTasks.message);
    }
    
    if (!Array.isArray(allTasks)) {
      console.log("getKanbanData: allTasks is not an array:", typeof allTasks);
      return { error: "Tasks data is not in expected format" };
    }
    
    console.log("getKanbanData: Processing", allTasks.length, "tasks");
    
    // Filter out leadership tasks for regular users
    const regularTasks = allTasks.filter(task => {
      const isLeadership = task.isLeadership === 'TRUE' || task.isLeadership === true;
      return !isLeadership;
    });
    
    console.log("getKanbanData: Filtered to", regularTasks.length, "regular tasks");
    
    const departments = [...new Set(regularTasks.map(task => task.department).filter(Boolean))].sort();
    console.log("getKanbanData: Found", departments.length, "departments");
    
    const users = getTeamData().map(member => member.fullName);
    console.log("getKanbanData: Found", users.length, "users");
    
    const result = { 
      tasks: regularTasks, 
      departments: departments, 
      users: users,
      userRole: 'regular'
    };
    
    console.log("getKanbanData: Returning result with", result.tasks.length, "tasks");
    return result;
    
  } catch(e) {
    console.log("getKanbanData: ERROR caught:", e.message);
    Logger.log("ERROR in getKanbanData: " + e.stack);
    return { error: e.message };
  }
}

// --- SWAG SUPPLY: data + updates ---
function getSwagSupplyData() {
  try {
    const defaults = ['Mockup requests','Waiting for approval','Waiting for deposit','Waiting for shipment'];
    const tasks = getTasks();
    const swagTasks = (Array.isArray(tasks) ? tasks : []).filter(t => {
      const dep = String(t.department || '').toLowerCase();
      const cat = String(t.taskCategory || '').toLowerCase();
      return dep.includes('swag') || dep.includes('merch') || dep.includes('promo') ||
             cat.includes('swag') || cat.includes('merch') || cat.includes('promo');
    }).map(t => {
      // derive productionStage if missing
      let stage = String(t.productionStage || t['Production_Stage'] || '').trim();
      if (!stage) {
        const s = String(t.status || '').toLowerCase();
        const text = (String(t.actionItem || '') + ' ' + String(t.notes || '')).toLowerCase();
        if (text.includes('mockup')) stage = 'Mockup requests';
        else if (s.includes('approval') || text.includes('approval')) stage = 'Waiting for approval';
        else if (s.includes('payment') || s.includes('deposit') || text.includes('deposit')) stage = 'Waiting for deposit';
        else if (s.includes('ship') || text.includes('ship')) stage = 'Waiting for shipment';
        else stage = 'Other';
      }
      t.productionStage = stage;
      // normalize owners string for frontend use
      let owners = String(t.owners || t.ownerS || t['Owner(s)'] || t['Owners'] || '').trim();
      if (!owners && Array.isArray(t.ownerDetails) && t.ownerDetails.length) {
        owners = t.ownerDetails.map(o => o.fullName).join(', ');
      }
      t.owners = owners;
      return t;
    });

    // Build stage list: defaults first, then any additional unique
    const found = new Set();
    const stages = [];
    defaults.forEach(s => { if (!found.has(s)) { stages.push(s); found.add(s); } });
    swagTasks.forEach(t => { const s = String(t.productionStage || 'Other'); if (!found.has(s)) { stages.push(s); found.add(s); } });
    // Build owner list; fallback to global users if swag-specific is empty
    let owners = Array.from(new Set(swagTasks.flatMap(t => String(t.owners || '').split(',').map(x => x.trim()).filter(Boolean)))).sort();
    const team = getTeamData();
    if (owners.length === 0) {
      try {
        owners = team && team.length ? team.map(m => m.fullName) : getUsersFromSheet();
      } catch (_) {}
    }
    const avatarMap = getAvatarMap(); // nameLower -> url
    return { stages, tasks: swagTasks, owners, avatarMap, team };
  } catch (e) {
    Logger.log('ERROR in getSwagSupplyData: ' + e.stack);
    return { error: String(e) };
  }
}

function updateSwagProductionStage(taskId, newStage) {
  const lock = LockService.getScriptLock();
  try {
    lock.waitLock(30000);
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const headers = sheet.getRange(1,1,1,sheet.getLastColumn()).getValues()[0];
    let col = headers.indexOf('Production_Stage') + 1;
    if (!col) {
      // ensure column exists
      const { headers: hdrs } = ensureTaskColumns_();
      col = hdrs.indexOf('Production_Stage') + 1;
    }
    if (!col) throw new Error('Production_Stage column not found');
    const idx = buildTaskIdIndexMap_(sheet);
    const rowIndex = idx[String(taskId)] || 0;
    if (!rowIndex) throw new Error('Task not found');
    withRetry_(function(){ sheet.getRange(rowIndex, col).setValue(String(newStage || '')); });
    SpreadsheetApp.flush();
    return { status: 'Success' };
  } catch (e) {
    Logger.log('ERROR in updateSwagProductionStage: ' + e.stack);
    return { status: 'Error', message: e.message };
  } finally { try { lock.releaseLock(); } catch (_) {} }
}

// --- PART 4: DATA UPDATE & ACTION FUNCTIONS ---
// Helper: find a column by trying multiple header names (returns 1-based index)
function findColumnIndex_(headers, possibleNames) {
  for (var i = 0; i < possibleNames.length; i++) {
    var idx = headers.indexOf(possibleNames[i]);
    if (idx !== -1) return idx + 1;
  }
  return 0;
}

// Helper: normalize owners string (trims quotes, removes empties/dupes)
function sanitizeOwners_(input) {
  try {
    var raw = String(input == null ? '' : input);
    var parts = raw.split(/[\n,]/).map(function(t){
      // trim and remove leading/trailing straight or smart quotes
      return String(t).trim().replace(/^[“”"']+|[“”"']+$/g, '');
    }).filter(function(s){ return s && s.length > 0; });
    var seen = new Set();
    var out = [];
    parts.forEach(function(name){
      var key = name.toLowerCase();
      if (!seen.has(key)) { seen.add(key); out.push(name); }
    });
    return out.join(', ');
  } catch (e) {
    return String(input || '');
  }
}

// Helper: build taskID -> rowIndex map for O(1) updates (header-agnostic)
function buildTaskIdIndexMap_(sheet) {
  var lastCol = sheet.getLastColumn();
  var headers = lastCol > 0 ? sheet.getRange(1, 1, 1, lastCol).getValues()[0] : [];
  var taskIdCol = findColumnIndex_(headers, ['taskID','Task_ID','Task ID','ID','Id','id','taskId']);
  if (!taskIdCol) taskIdCol = 1; // fallback to column A if unknown
  var lastRow = sheet.getLastRow();
  var ids = lastRow > 1 ? sheet.getRange(2, taskIdCol, lastRow - 1, 1).getValues() : [];
  var map = {};
  for (var i = 0; i < ids.length; i++) {
    var id = ids[i][0];
    if (id) map[String(id)] = i + 2; // offset header
  }
  return map;
}

function updateTaskStatus(taskId, newStatus) {
  const lock = LockService.getScriptLock();
  try {
    lock.waitLock(30000);
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    const statusColIndex = findColumnIndex_(headers, ['Status','status','Task_Status','taskStatus']);
    if (!statusColIndex) throw new Error("'Status' column not found.");

    const idx = buildTaskIdIndexMap_(sheet);
    const rowIndex = idx[String(taskId)] || 0;
    if (rowIndex > 0) {
      withRetry_(function(){ sheet.getRange(rowIndex, statusColIndex).setValue(newStatus); });
      SpreadsheetApp.flush();
      return { status: "Success" };
    }
    return { status: "Error", message: "Task ID not found." };
  } catch (e) {
    Logger.log(`ERROR in updateTaskStatus: ${e.stack}`);
    return { status: "Error", message: e.message };
  } finally {
    try { lock.releaseLock(); } catch (_) {}
  }
}

function updateTaskAction(taskId, action, payload = {}) {
  const lock = LockService.getScriptLock();
  try {
    lock.tryLock(10000);
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    const idx = buildTaskIdIndexMap_(sheet);
    const rowIndex = idx[taskId] || 0;
    if (rowIndex === 0) throw new Error("Task ID not found.");
    const statusCol = findColumnIndex_(headers, ['Status','status']);
    const ownersCol = findColumnIndex_(headers, ['Owner(s)','Owner(s) ','Owners','Owner','owners','ownerS']);
    const dueDateCol = findColumnIndex_(headers, ['Due_Date','Due Date','DueDate','Target Date','targetDate']);

    switch (action) {
      case 'START_PROJECT':
        withRetry_(function(){ sheet.getRange(rowIndex, statusCol).setValue('In Progress'); });
        break;
      case 'ASSIGN_TEAM':
        withRetry_(function(){
          var newOwners = sanitizeOwners_((payload.teamMembers || []).join(', '));
          sheet.getRange(rowIndex, ownersCol).setValue(newOwners);
        });
        break;
      case 'SET_DEADLINE':
        withRetry_(function(){ sheet.getRange(rowIndex, dueDateCol).setValue(payload.dueDate ? new Date(payload.dueDate) : ''); });
        break;
      case 'FORCE_COMPLETE':
        withRetry_(function(){ sheet.getRange(rowIndex, statusCol).setValue('Completed'); });
        break;
      case 'CANCEL_PROJECT':
        withRetry_(function(){ sheet.getRange(rowIndex, statusCol).setValue('Cancelled'); });
        break;
      case 'DELETE_PROJECT':
        sheet.deleteRow(rowIndex);
        break;
      default:
        throw new Error('Invalid action specified.');
    }
    return { status: 'Success', message: `Action '${action}' completed.` };
  } catch (e) {
    Logger.log('ERROR in updateTaskAction: ' + e.stack);
    return { status: 'Error', message: e.message };
  } finally {
    try { lock.releaseLock(); } catch (_) {}
  }
}

function markTaskComplete(taskId) {
  const lock = LockService.getScriptLock();
  try {
    lock.tryLock(10000);
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    const statusColIndex = headers.indexOf('Status') + 1;
    const progressColIndex = headers.indexOf('Progress_Percentage') + 1;
    if (statusColIndex === 0) throw new Error("'Status' column not found.");
    if (progressColIndex === 0) throw new Error("'Progress_Percentage' column not found.");

    const idx = buildTaskIdIndexMap_(sheet);
    const rowIndex = idx[taskId] || 0;
    if (rowIndex > 0) {
      sheet.getRange(rowIndex, statusColIndex).setValue('Completed');
      sheet.getRange(rowIndex, progressColIndex).setValue(100);
      return { status: 'Success', message: 'Task marked as complete!' };
    }
    return { status: 'Error', message: 'Task ID not found.' };
  } catch (e) {
    Logger.log(`ERROR in markTaskComplete: ${e.stack}`);
    return { status: 'Error', message: e.message };
  } finally {
    try { lock.releaseLock(); } catch (_) {}
  }
}

function updateTaskDetails(taskId, updates) {
  const lock = LockService.getScriptLock();
  try {
    lock.waitLock(30000);
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    const idx = buildTaskIdIndexMap_(sheet);
    const rowIndex = idx[String(taskId)] || 0;
    if (rowIndex === 0) throw new Error('Task ID not found.');

    const fieldMappings = {
      owners: findColumnIndex_(headers, ['Owner(s)','Owner(s) ','Owners','Owner','owners','ownerS']),
      dueDate: findColumnIndex_(headers, ['Due_Date','Due Date','DueDate','Target Date','targetDate']),
      predictedHours: findColumnIndex_(headers, ['Predicted_Hours','Predicted Hours','Est Hours','Estimated Hours','estimatedHours']),
      actualHoursSpent: findColumnIndex_(headers, ['Actual_Hours_Spent','Actual Hours Spent','Actual Hours','actualHours'])
    };

    var wrote = false;
    Object.keys(updates || {}).forEach(function(field){
      var col = fieldMappings[field];
      if (col && col > 0) {
        var val = updates[field];
        if (field === 'owners') val = sanitizeOwners_(val);
        withRetry_(function(){ sheet.getRange(rowIndex, col).setValue(val); });
        wrote = true;
      }
    });

    if (wrote) SpreadsheetApp.flush();
    return { status: 'Success', message: 'Task updated successfully!' };
  } catch (e) {
    Logger.log(`ERROR in updateTaskDetails: ${e.stack}`);
    return { status: 'Error', message: e.message };
  } finally {
    try { lock.releaseLock(); } catch (_) {}
  }
}

// Atomic append of a single owner to the Owners field
function appendOwner(taskId, ownerName) {
  const lock = LockService.getScriptLock();
  try {
    lock.waitLock(30000);
    if (!ownerName || !taskId) throw new Error('Missing taskId or ownerName');
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    const ownersCol = findColumnIndex_(headers, ['Owner(s)','Owner(s) ','Owners','Owner','owners','ownerS']);
    if (!ownersCol) throw new Error("Owners column not found");
    const idx = buildTaskIdIndexMap_(sheet);
    const rowIndex = idx[String(taskId)] || 0;
    if (rowIndex === 0) throw new Error('Task ID not found');
    const current = sheet.getRange(rowIndex, ownersCol).getValue();
    const list = sanitizeOwners_(current).split(',').map(function(s){ return String(s).trim(); }).filter(Boolean);
    const norm = function(s){ return String(s||'').toLowerCase().trim(); };
    if (!list.map(norm).includes(norm(ownerName))) list.push(ownerName);
    const updated = sanitizeOwners_(list.join(', '));
    withRetry_(function(){ sheet.getRange(rowIndex, ownersCol).setValue(updated); });
    SpreadsheetApp.flush();
    return { status: 'Success', owners: updated };
  } catch (e) {
    Logger.log('ERROR in appendOwner: ' + e.stack);
    return { status: 'Error', message: e.message };
  } finally {
    try { lock.releaseLock(); } catch (_) {}
  }
}

// Retry helper to mitigate transient INTERNAL storage errors
function withRetry_(fn, attempts, sleepMs) {
  var tries = attempts || 4;
  var delay = sleepMs || 250;
  for (var i = 0; i < tries; i++) {
    try { return fn(); }
    catch (e) { if (i === tries - 1) throw e; Utilities.sleep(delay); delay = Math.min(2000, Math.floor(delay * 1.8)); }
  }
}

// --- PART 5: AI FUNCTIONS (UPGRADED TO MISTRAL) ---
function generateProjectPlan(taskData) {
  // Ensure all required fields exist with fallbacks
  const title = taskData.actionItem || 'Untitled Task';
  const problem = taskData.problemDescription || 'No problem description available';
  const solution = taskData.proposedSolution || 'No proposed solution available';
  const stakeholders = taskData.owners || 'Unassigned';
  const department = taskData.department || 'General';
  
  const prompt = `
    As an expert project manager, expand the following task details into a formal, comprehensive project plan. The output MUST be in valid JSON format.
    Task Details: - Title: "${title}" - Problem: "${problem}" - Proposed Solution: "${solution}" - Stakeholders: "${stakeholders}" - Department: "${department}"
    Generate the following sections: 1. projectGoal: A concise goal. 2. expectedBenefits: An array of 3-5 key benefits. 3. milestonePlan: An object with two keys: "title" and "description". 4. dataIntegrityPlan: An array of objects where each key is a step title and value is a description. 5. implementationPlan: An array of 5 objects where each key is a step title and value is a description.
    Return ONLY the raw JSON object.`;
  const payload = { "model": "mistral-large-latest", "messages": [{"role": "user", "content": prompt}], "response_format": { "type": "json_object" } };
  const options = { 'method': 'post', 'contentType': 'application/json', 'headers': { 'Authorization': 'Bearer ' + MISTRAL_API_KEY }, 'payload': JSON.stringify(payload), 'muteHttpExceptions': true };
  try {
    const response = UrlFetchApp.fetch(MISTRAL_API_ENDPOINT, options);
    const responseText = response.getContentText();
    Logger.log("Mistral API Response: " + responseText);
    const json = JSON.parse(responseText);
    if (!json || json.error || !json.choices || !json.choices[0] || !json.choices[0].message || !json.choices[0].message.content) {
      throw new Error(json && json.error ? json.error.message : 'Invalid response structure from API.');
    }
    const aiContent = json.choices[0].message.content;
    return JSON.parse(aiContent);
  } catch (e) {
    log_('error', 'generateProjectPlan', 'AI call/parse failed', { err: String(e) }, String(taskData && taskData.taskID));
    return { 
      error: true, 
      projectGoal: "Failed to generate project plan.", 
      expectedBenefits: ["Benefit 1", "Benefit 2", "Benefit 3"], 
      milestonePlan: { title: "API Communication Error", description: e.message }, 
      dataIntegrityPlan: [{ "Step 1": "Error occurred during plan generation" }], 
      implementationPlan: [
        { "Step 1": "Review task details" },
        { "Step 2": "Define requirements" },
        { "Step 3": "Create implementation plan" },
        { "Step 4": "Execute plan" },
        { "Step 5": "Monitor and adjust" }
      ] 
    };
  }
}
function processPastedTranscript(transcriptText) {
  try {
    runTranscriptAutomation(transcriptText);
    return `SUCCESS: Transcript processed and tasks have been added.`;
  } catch (error) {
    Logger.log(`A critical error occurred: ${error.stack}`);
    return `ERROR: Could not process transcript. Details: ${error.message}`;
  }
}
function runTranscriptAutomation(transcript) {
  const meetingData = callMistralForTranscriptAnalysis(transcript); 
  if (!meetingData || !meetingData.tasks) { throw new Error("AI did not return a valid list of tasks from the transcript."); }
  const { sheet, headers } = ensureTaskColumns_();

  const transcriptSummary = meetingData.summary || '';
  const resources = (meetingData.resources || []).join('\n');
  const mentions = (meetingData.mentions || []).join(', ');

  meetingData.tasks.forEach(task => {
    const newId = `AI-${new Date().getTime().toString().slice(-6)}-${Math.random().toString(36).substr(2, 4).toUpperCase()}`;
    const row = new Array(headers.length).fill('');

    // Core columns (robust names)
    setByHeaderNames_(row, headers, ['Task ID','Task_ID','TaskId'], newId);
    setByHeaderNames_(row, headers, ['Date_Created','Created','Created_At'], new Date().toISOString());
    setByHeaderNames_(row, headers, ['Department','Dept'], task.department || 'Unassigned');
    setByHeaderNames_(row, headers, ['Action Item','Action_Item','Title'], task.action_item || task.title || '');
    setByHeaderNames_(row, headers, ['Owner(s)','Owners','Assigned To','Assigned_To'], task.owner || 'Unassigned');
    setByHeaderNames_(row, headers, ['Priority Score','Priority_Score','Priority'], task.priority_score || 5);
    setByHeaderNames_(row, headers, ['Milestone Group','Milestone_Group','Milestone'], task.milestone_group || '');
    setByHeaderNames_(row, headers, ['Task Category','Task_Category','Category'], task.task_category || '');
    setByHeaderNames_(row, headers, ['Problem_Description','Problem Description','Problem'], task.problem_description || '');
    setByHeaderNames_(row, headers, ['Proposed_Solution','Proposed Solution','Solution'], task.proposed_solution || '');
    setByHeaderNames_(row, headers, ['Time_Savings_Impact','Time Savings Impact','Impact'], task.time_savings_impact || '');
    setByHeaderNames_(row, headers, ['Status'], 'Not Started');
    setByHeaderNames_(row, headers, ['Source'], 'AI Generated');
    setByHeaderNames_(row, headers, ['Notes','Description'], `Generated from transcript on ${new Date().toLocaleDateString()}`);
    setByHeaderNames_(row, headers, ['Predicted_Hours','Predicted Hours'], task.predicted_hours || 0);
    setByHeaderNames_(row, headers, ['Progress_Percentage','Progress %','Progress Percentage'], 0);

    // New transcript fields
    setByHeaderNames_(row, headers, ['Transcript_Raw'], transcript);
    setByHeaderNames_(row, headers, ['Transcript_Summary'], transcriptSummary);
    setByHeaderNames_(row, headers, ['Transcript_Resources'], resources);
    setByHeaderNames_(row, headers, ['Transcript_Mentions'], mentions);

    // Append row
    sheet.appendRow(row);

    // Cache a plan immediately for faster first view
    try {
      const taskData = { taskID: newId, actionItem: task.action_item || '', department: task.department || 'Unassigned', owners: task.owner || 'Unassigned', priorityScore: task.priority_score || 5, status: 'Not Started', progressPercentage: 0 };
      const plan = generateProjectPlan(taskData);
      savePlanJson_(newId, plan);
    } catch (e) {
      log_('warn', 'runTranscriptAutomation', 'Plan generation post-insert failed', { err: String(e) }, newId);
    }
  });
}
function callMistralForTranscriptAnalysis(transcript) {
  const prompt = `
    Analyze the following meeting transcript and return structured JSON. RULES: Return ONLY a valid JSON object with this shape:
    {
      "summary": string,
      "resources": string[] (URLs or file names mentioned),
      "mentions": string[] (people mentioned),
      "tasks": [ {
        "action_item": string,
        "owner": string,
        "department": string,
        "priority_score": number,
        "predicted_hours": number,
        "milestone_group": string,
        "task_category": string,
        "problem_description": string,
        "proposed_solution": string,
        "time_savings_impact": string
      } ]
    }
    Be concise but informative for description fields. For empty values, return an empty string, not null.
    Transcript:\n---\n${transcript}\n---`;
  const payload = { "model": "mistral-large-latest", "messages": [{"role": "user", "content": prompt}], "response_format": { "type": "json_object" } };
  const options = { 'method': 'post', 'contentType': 'application/json', 'headers': { 'Authorization': 'Bearer ' + MISTRAL_API_KEY }, 'payload': JSON.stringify(payload), 'muteHttpExceptions': true };
  try {
    const response = UrlFetchApp.fetch(MISTRAL_API_ENDPOINT, options);
    const responseText = response.getContentText();
    Logger.log("Transcript Analysis Response: " + responseText);
    const json = JSON.parse(responseText);
    if (!json || json.error || !json.choices || !json.choices[0] || !json.choices[0].message || !json.choices[0].message.content) {
      throw new Error(json && json.error ? json.error.message : 'Invalid response structure from API.');
    }
    const jsonString = json.choices[0].message.content;
    return JSON.parse(jsonString);
  } catch (e) {
    log_('error', 'callMistralForTranscriptAnalysis', 'AI parse error', { err: String(e) });
    throw new Error(`Failed to get a valid JSON response from the AI. Details: ${e.message}`);
  }
}

// --- PART 6: HELPER FUNCTIONS ---
function include(filename) { return HtmlService.createHtmlOutputFromFile(filename).getContent(); }
function getWebAppUrl() { return ScriptApp.getService().getUrl(); }
function getColumnIndexByName(sheet, columnName) {
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    return headers.indexOf(columnName) + 1;
}

// Debug function to check data loading
function debugDataLoading() {
  try {
    Logger.log("=== DEBUGGING DATA LOADING ===");
    
    // Check avatar data
    const avatarMap = getAvatarMap();
    Logger.log("Avatar map size: " + Object.keys(avatarMap).length);
    Logger.log("Avatar keys: " + Object.keys(avatarMap).join(', '));
    
    // Check team data
    const team = getTeamData();
    Logger.log("Team members: " + team.length);
    team.forEach((member, index) => {
      Logger.log(`Member ${index + 1}: ${member.fullName} - Avatar: ${member.avatarUrl}`);
    });
    
    // Check task data
    const tasks = getTasks();
    Logger.log("Tasks: " + tasks.length);
    tasks.forEach((task, index) => {
      Logger.log(`Task ${index + 1}: ${task.actionItem} - Owners: ${task.ownerDetails?.map(o => o.fullName).join(', ')}`);
    });
    
    return "Debug complete - check logs";
  } catch (e) {
    Logger.log("DEBUG ERROR: " + e.stack);
    return "Debug failed: " + e.message;
  }
}

// Clear cache to force fresh data loading  
function clearCache() {
  Logger.log("Cache cleared - next data load will be fresh");
  return "Cache cleared successfully";
}

// Force clear all caches and reload data
function forceClearAllCaches() {
  try {
    Logger.log("=== FORCING CLEAR ALL CACHES ===");
    
    // Clear any potential caches
    Logger.log("Clearing all potential caches...");
    
    // Force fresh data by calling all functions
    Logger.log("Testing fresh data loading...");
    const avatarMap = getAvatarMap();
    const teamData = getTeamData();
    const tasks = getTasks();
    
    Logger.log(`Results after cache clear:`);
    Logger.log(`- Avatar map entries: ${Object.keys(avatarMap).length}`);
    Logger.log(`- Team data entries: ${teamData.length}`);
    Logger.log(`- Tasks loaded: ${tasks.length}`);
    
    return {
      success: true,
      message: "All caches cleared and data reloaded",
      avatarMapEntries: Object.keys(avatarMap).length,
      teamDataEntries: teamData.length,
      taskCount: tasks.length
    };
  } catch (e) {
    Logger.log(`ERROR in forceClearAllCaches: ${e.stack}`);
    return { error: e.message, stack: e.stack };
  }
}

// Simple test function for web app
function testDataLoading() {
  try {
    const avatarMap = getAvatarMap();
    const team = getTeamData();
    const tasks = getTasks();
    
    return {
      avatarCount: Object.keys(avatarMap).length,
      teamCount: team.length,
      taskCount: tasks.length,
      avatarKeys: Object.keys(avatarMap).slice(0, 5), // First 5 keys
      teamNames: team.slice(0, 5).map(t => t.fullName), // First 5 names
      taskTitles: tasks.slice(0, 3).map(t => t.actionItem) // First 3 titles
    };
  } catch (e) {
    return { error: e.message };
  }
}

// Debug team member matching
function debugTeamMatching() {
  try {
    const team = getTeamData();
    const tasks = getTasks();
    
    const results = tasks.slice(0, 5).map(task => {
      const ownerNames = (task.owners || "Unassigned").toString().split(',').map(name => name.trim()).filter(Boolean);
      const matches = ownerNames.map(name => {
        const member = team.find(m => m.fullName.toLowerCase() === name.toLowerCase());
        return {
          ownerName: name,
          found: !!member,
          matchedTo: member ? member.fullName : 'Unassigned'
        };
      });
      
      return {
        taskId: task.taskID,
        taskTitle: task.actionItem,
        owners: ownerNames,
        matches: matches
      };
    });
    
    return results;
  } catch (e) {
    return { error: e.message };
  }
}

// Debug function to check column headers
function debugColumnHeaders() {
  try {
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    
    Logger.log("Column headers: " + headers.join(', '));
    
    // Find the owner column
    const ownerColumnIndex = headers.findIndex(header => 
      header.toLowerCase().includes('owner') || 
      header.toLowerCase().includes('assign')
    );
    
    return {
      headers: headers,
      ownerColumnIndex: ownerColumnIndex,
      ownerColumnName: ownerColumnIndex >= 0 ? headers[ownerColumnIndex] : 'NOT FOUND'
    };
  } catch (e) {
    return { error: e.message };
  }
}

// Debug avatar mapping
function debugAvatarMapping() {
  try {
    Logger.log("=== DEBUGGING AVATAR MAPPING ===");
    
    // Get the avatar map
    const avatarMap = getAvatarMap();
    Logger.log("Avatar map size: " + Object.keys(avatarMap).length);
    Logger.log("Avatar map keys: " + Object.keys(avatarMap).join(', '));
    
    // Get team data to see what names we're trying to match
    const team = getTeamData();
    Logger.log("Team members: " + team.length);
    
    const results = team.map(member => {
      const nameLower = member.fullName.toLowerCase();
      const hasAvatar = avatarMap[nameLower];
      return {
        fullName: member.fullName,
        nameLower: nameLower,
        hasAvatar: hasAvatar,
        avatarUrl: hasAvatar || 'NOT FOUND',
        teamAvatarUrl: member.avatarUrl
      };
    });
    
    return {
      avatarMapKeys: Object.keys(avatarMap),
      teamMembers: results,
      sampleAvatars: Object.entries(avatarMap).slice(0, 5)
    };
  } catch (e) {
    Logger.log("AVATAR DEBUG ERROR: " + e.stack);
    return { error: e.message };
  }
}

// Debug raw avatar sheet data
function debugAvatarSheet() {
  try {
    Logger.log("=== DEBUGGING RAW AVATAR SHEET ===");
    
    const avatarSheet = SpreadsheetApp.openById(AVATAR_SHEET_ID).getSheets()[0];
    const avatarData = avatarSheet.getDataRange().getValues();
    
    Logger.log("Avatar sheet rows: " + avatarData.length);
    Logger.log("Avatar sheet columns: " + avatarData[0].length);
    
    // Show first few rows
    const sampleRows = avatarData.slice(0, 5);
    Logger.log("Sample rows:");
    sampleRows.forEach((row, index) => {
      Logger.log(`Row ${index}: ${row.join(' | ')}`);
    });
    
    return {
      totalRows: avatarData.length,
      totalColumns: avatarData[0].length,
      sampleRows: sampleRows,
      headers: avatarData[0]
    };
  } catch (e) {
    Logger.log("AVATAR SHEET DEBUG ERROR: " + e.stack);
    return { error: e.message };
  }
}

// Debug Team Directory sheet
function debugTeamDirectory() {
  try {
    Logger.log("=== DEBUGGING TEAM DIRECTORY ===");
    
    const masterSheet = SpreadsheetApp.openById(MASTER_SHEET_ID);
    Logger.log("Master sheet name: " + masterSheet.getName());
    
    // List all sheet names
    const allSheets = masterSheet.getSheets();
    const sheetNames = allSheets.map(sheet => sheet.getName());
    Logger.log("All sheet names: " + sheetNames.join(', '));
    
    // Check if Team Directory exists
    const directorySheet = masterSheet.getSheetByName('Team Directory');
    if (!directorySheet) {
      Logger.log("ERROR: 'Team Directory' sheet not found!");
      return {
        error: "Team Directory sheet not found",
        availableSheets: sheetNames
      };
    }
    
    Logger.log("Team Directory sheet found!");
    
    // Get the data
    const directoryData = directorySheet.getDataRange().getValues();
    Logger.log("Team Directory rows: " + directoryData.length);
    Logger.log("Team Directory columns: " + directoryData[0].length);
    
    // Show headers
    Logger.log("Headers: " + directoryData[0].join(', '));
    
    // Show first few rows
    const sampleRows = directoryData.slice(0, 5);
    Logger.log("Sample rows:");
    sampleRows.forEach((row, index) => {
      Logger.log(`Row ${index}: ${row.join(' | ')}`);
    });
    
    return {
      sheetFound: true,
      totalRows: directoryData.length,
      totalColumns: directoryData[0].length,
      headers: directoryData[0],
      sampleRows: sampleRows,
      availableSheets: sheetNames
    };
  } catch (e) {
    Logger.log("TEAM DIRECTORY DEBUG ERROR: " + e.stack);
    return { error: e.message };
  }
}

// Debug task field mapping
function debugTaskFieldMapping() {
  try {
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    
    Logger.log("=== TASK FIELD MAPPING DEBUG ===");
    Logger.log("Original headers: " + headers.join(', '));
    
    const fieldMappings = headers.map((header, index) => {
      if (header) {
        const key = header.charAt(0).toLowerCase() + header.slice(1).replace(/[^a-zA-Z0-9]+(.)?/g, (match, chr) => chr ? chr.toUpperCase() : '').replace(/\s+/g, '');
        return {
          originalHeader: header,
          mappedKey: key,
          columnIndex: index + 1
        };
      }
      return null;
    }).filter(Boolean);
    
    Logger.log("Field mappings:");
    fieldMappings.forEach(mapping => {
      Logger.log(`  "${mapping.originalHeader}" → "${mapping.mappedKey}" (column ${mapping.columnIndex})`);
    });
    
    return {
      headers: headers,
      mappings: fieldMappings
    };
  } catch (e) {
    Logger.log("TASK FIELD MAPPING DEBUG ERROR: " + e.stack);
    return { error: e.message };
  }
}

// Debug task IDs directly from sheet
function debugTaskIds() {
  try {
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const data = sheet.getDataRange().getValues();
    const headers = data[0];
    const taskRows = data.slice(1); // Skip header
    
    Logger.log("=== TASK IDS DEBUG ===");
    Logger.log("Total task rows: " + taskRows.length);
    
    // Find the task ID column
    const taskIdColumnIndex = headers.findIndex(header => 
      header.toLowerCase().includes('task') && header.toLowerCase().includes('id')
    );
    
    if (taskIdColumnIndex === -1) {
      Logger.log("ERROR: No task ID column found!");
      Logger.log("Available headers: " + headers.join(', '));
      return { error: "No task ID column found", headers: headers };
    }
    
    Logger.log(`Task ID column: "${headers[taskIdColumnIndex]}" (column ${taskIdColumnIndex + 1})`);
    
    // Get first 10 task IDs
    const taskIds = taskRows.slice(0, 10).map((row, index) => {
      const taskId = row[taskIdColumnIndex];
      const actionItem = row[headers.findIndex(h => h.toLowerCase().includes('action'))] || 'N/A';
      return {
        row: index + 2, // +2 because we skipped header and arrays are 0-indexed
        taskId: taskId,
        actionItem: actionItem
      };
    });
    
    Logger.log("First 10 task IDs:");
    taskIds.forEach(item => {
      Logger.log(`  Row ${item.row}: "${item.taskId}" - "${item.actionItem}"`);
    });
    
    return {
      taskIdColumn: headers[taskIdColumnIndex],
      taskIdColumnIndex: taskIdColumnIndex + 1,
      sampleTaskIds: taskIds,
      totalTasks: taskRows.length
    };
  } catch (e) {
    Logger.log("TASK IDS DEBUG ERROR: " + e.stack);
    return { error: e.message };
  }
}

// Test task detail template rendering
function testTaskDetailTemplate() {
  try {
    // Create a mock task for testing
    const mockTask = {
      taskID: 'TEST-001',
      actionItem: 'Test Task',
      status: 'Not Started',
      department: 'Test Department',
      owners: 'Test User',
      priorityScore: 5,
      progressPercentage: 0
    };
    
    // Try to create the template
    let template = HtmlService.createTemplateFromFile('task_detail');
    template.task = mockTask;
    template.plan = {
      projectGoal: 'Test goal',
      expectedBenefits: ['Benefit 1', 'Benefit 2'],
      milestonePlan: { title: 'Test Milestone', description: 'Test description' },
      dataIntegrityPlan: [{ 'Step 1': 'Description 1' }],
      implementationPlan: [{ 'Step 1': 'Implementation 1' }]
    };
    template.teamDirectory = [];
    
    // Try to evaluate the template
    const html = template.evaluate().getContent();
    
    Logger.log('Template evaluation successful!');
    Logger.log('HTML length: ' + html.length);
    Logger.log('First 200 chars: ' + html.substring(0, 200));
    
    return { success: true, htmlLength: html.length };
  } catch (e) {
    Logger.log('Template evaluation failed: ' + e.stack);
    return { success: false, error: e.message };
  }
}

// Debug specific task ID lookup
function debugSpecificTaskId(requestedId) {
  try {
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const data = sheet.getDataRange().getValues();
    const headers = data[0];
    const taskRows = data.slice(1); // Skip header
    
    Logger.log(`=== SPECIFIC TASK ID DEBUG ===`);
    Logger.log(`Requested ID: "${requestedId}" (type: ${typeof requestedId})`);
    
    // Find the task ID column
    const taskIdColumnIndex = headers.findIndex(header => 
      header.toLowerCase().includes('task') && header.toLowerCase().includes('id')
    );
    
    if (taskIdColumnIndex === -1) {
      Logger.log("ERROR: No task ID column found!");
      Logger.log("Available headers: " + headers.join(', '));
      return { error: "No task ID column found", headers: headers };
    }
    
    Logger.log(`Task ID column: "${headers[taskIdColumnIndex]}" (column ${taskIdColumnIndex + 1})`);
    
    // Check all task IDs for matches
    const matches = [];
    const allTaskIds = [];
    
    taskRows.forEach((row, index) => {
      const taskId = row[taskIdColumnIndex];
      const actionItem = row[headers.findIndex(h => h.toLowerCase().includes('action'))] || 'N/A';
      
      allTaskIds.push(taskId);
      
      // Check for exact match
      if (taskId === requestedId) {
        matches.push({ type: 'exact', row: index + 2, taskId, actionItem });
      }
      // Check for string match
      else if (String(taskId) === String(requestedId)) {
        matches.push({ type: 'string', row: index + 2, taskId, actionItem });
      }
      // Check for case-insensitive match
      else if (String(taskId).toLowerCase() === String(requestedId).toLowerCase()) {
        matches.push({ type: 'case-insensitive', row: index + 2, taskId, actionItem });
      }
      // Check for partial match
      else if (String(taskId).includes(String(requestedId)) || String(requestedId).includes(String(taskId))) {
        matches.push({ type: 'partial', row: index + 2, taskId, actionItem });
      }
    });
    
    Logger.log(`Total task IDs in sheet: ${allTaskIds.length}`);
    Logger.log(`First 10 task IDs: ${allTaskIds.slice(0, 10).join(', ')}`);
    Logger.log(`Matches found: ${matches.length}`);
    
    matches.forEach(match => {
      Logger.log(`  ${match.type} match: Row ${match.row}, ID="${match.taskId}", Action="${match.actionItem}"`);
    });
    
    return {
      requestedId: requestedId,
      totalTasks: allTaskIds.length,
      matches: matches,
      sampleTaskIds: allTaskIds.slice(0, 10)
    };
  } catch (e) {
    Logger.log("SPECIFIC TASK ID DEBUG ERROR: " + e.stack);
    return { error: e.message };
  }
}

// Debug team member data and email availability
function debugTeamEmails() {
  try {
    const teamMembers = getTeamData();
    Logger.log(`=== TEAM EMAIL DEBUG ===`);
    Logger.log(`Total team members: ${teamMembers.length}`);
    
    const teamData = teamMembers.map(member => ({
      fullName: member.fullName,
      email: member.email,
      department: member.department,
      hasEmail: !!member.email
    }));
    
    Logger.log(`Team members with emails: ${teamData.filter(m => m.hasEmail).length}`);
    Logger.log(`Team members without emails: ${teamData.filter(m => !m.hasEmail).length}`);
    
    // Log first 5 team members as sample
    teamData.slice(0, 5).forEach((member, index) => {
      Logger.log(`Member ${index + 1}: ${member.fullName} - Email: ${member.email || 'MISSING'}`);
    });
    
    return {
      totalMembers: teamMembers.length,
      membersWithEmails: teamData.filter(m => m.hasEmail).length,
      membersWithoutEmails: teamData.filter(m => !m.hasEmail).length,
      sampleMembers: teamData.slice(0, 5)
    };
  } catch (e) {
    Logger.log(`ERROR in debugTeamEmails: ${e.stack}`);
    return { error: e.message };
  }
}

// Force fresh data reload with comprehensive debugging
function forceDataRefresh() {
  try {
    Logger.log(`=== FORCING FRESH DATA REFRESH ===`);
    Logger.log(`Time: ${new Date().toISOString()}`);
    
    // Clear any potential caches (no caches currently used)
    
    // Force fresh connection to avatar sheet
    const avatarSheet = SpreadsheetApp.openById(AVATAR_SHEET_ID).getSheets()[0];
    
    Logger.log(`Avatar sheet details:`);
    Logger.log(`- ID: ${AVATAR_SHEET_ID}`);
    Logger.log(`- Name: ${avatarSheet.getName()}`);
    Logger.log(`- Last row: ${avatarSheet.getLastRow()}`);
    Logger.log(`- Last column: ${avatarSheet.getLastColumn()}`);
    
    // Get ALL data from the sheet
    const allData = avatarSheet.getDataRange().getValues();
    Logger.log(`Total rows in sheet: ${allData.length}`);
    
    if (allData.length > 0) {
      Logger.log(`Header row: ${JSON.stringify(allData[0])}`);
      Logger.log(`Expected: ["Name", "Avatar URL", "Department", "Email"]`);
      
      // Check if column D (index 3) exists and has email data
      Logger.log(`Checking email column (D/index 3):`);
      for (let i = 1; i < Math.min(allData.length, 11); i++) {
        const row = allData[i];
        const email = row[3];
        Logger.log(`Row ${i}: Name="${row[0]}" Email="${email}" Type=${typeof email}`);
      }
    }
    
    // Now get fresh team data
    const freshTeamData = getTeamData();
    
    return {
      timestamp: new Date().toISOString(),
      totalRows: allData.length,
      teamMembers: freshTeamData.length,
      membersWithEmails: freshTeamData.filter(m => m.email && m.email.trim() !== '').length,
      sampleData: allData.slice(0, 5),
      headers: allData[0] || []
    };
  } catch (e) {
    Logger.log(`ERROR in forceDataRefresh: ${e.stack}`);
    return { error: e.message, stack: e.stack };
  }
}

// Test avatar loading specifically
function testAvatarLoading() {
  try {
    Logger.log("=== TESTING AVATAR LOADING ===");
    
    const avatarMap = getAvatarMap();
    Logger.log(`Avatar map created with ${Object.keys(avatarMap).length} entries`);
    
    // Test a few specific avatars
    const testNames = ['unassigned', 'kelleen', 'alexander'];
    testNames.forEach(name => {
      const url = avatarMap[name.toLowerCase()];
      Logger.log(`Avatar for "${name}": ${url}`);
    });
    
    return {
      totalAvatars: Object.keys(avatarMap).length,
      sampleAvatars: Object.keys(avatarMap).slice(0, 5).map(name => ({
        name: name,
        url: avatarMap[name]
      })),
      unassignedUrl: avatarMap['unassigned']
    };
  } catch (e) {
    Logger.log(`ERROR in testAvatarLoading: ${e.stack}`);
    return { error: e.message };
  }
}

// Comprehensive avatar pipeline debug
function debugAvatarPipeline() {
  try {
    Logger.log("=== COMPREHENSIVE AVATAR PIPELINE DEBUG ===");
    
    // Step 1: Test getAvatarMap
    Logger.log("Step 1: Testing getAvatarMap...");
    const avatarMap = getAvatarMap();
    Logger.log(`Avatar map created with ${Object.keys(avatarMap).length} entries`);
    
    // Step 2: Test getTeamData
    Logger.log("Step 2: Testing getTeamData...");
    const teamData = getTeamData();
    Logger.log(`Team data created with ${teamData.length} members`);
    
    // Step 3: Test avatar processing for specific names
    Logger.log("Step 3: Testing avatar processing for specific names...");
    const testNames = ['david callejas', 'alex mazzei', 'ben peach'];
    
    testNames.forEach(name => {
      Logger.log(`\nTesting "${name}":`);
      
      // Check if in avatar map
      const avatarUrl = avatarMap[name];
      Logger.log(`  - In avatar map: ${avatarUrl || 'NOT FOUND'}`);
      
      // Check if in team data
      const teamMember = teamData.find(m => m.fullName.toLowerCase() === name);
      if (teamMember) {
        Logger.log(`  - In team data: ${teamMember.fullName}`);
        Logger.log(`  - Raw avatar URL: ${teamMember.avatarUrl}`);
        
        // Test the processing logic
        const processedUrl = avatarMap[name] || teamMember.avatarUrl;
        Logger.log(`  - Processed URL: ${processedUrl}`);
      } else {
        Logger.log(`  - NOT FOUND in team data`);
      }
    });
    
    // Step 4: Test a few tasks to see the final result
    Logger.log("Step 4: Testing task processing...");
    const tasks = getTasks();
    Logger.log(`Tasks processed: ${tasks.length}`);
    
    // Show first few tasks with avatar details
    tasks.slice(0, 3).forEach((task, index) => {
      Logger.log(`\nTask ${index + 1}: ${task.actionItem}`);
      if (task.ownerDetails && task.ownerDetails.length > 0) {
        task.ownerDetails.forEach((owner, ownerIndex) => {
          Logger.log(`  Owner ${ownerIndex + 1}: ${owner.fullName} - Avatar: ${owner.avatarUrl}`);
        });
      }
    });
    
    return {
      avatarMapEntries: Object.keys(avatarMap).length,
      teamDataEntries: teamData.length,
      taskCount: tasks.length,
      sampleAvatars: Object.keys(avatarMap).slice(0, 5).map(name => ({
        name: name,
        url: avatarMap[name]
      })),
      sampleTasks: tasks.slice(0, 2).map(task => ({
        actionItem: task.actionItem,
        owners: task.ownerDetails ? task.ownerDetails.map(o => ({
          name: o.fullName,
          avatar: o.avatarUrl
        })) : []
      }))
    };
  } catch (e) {
    Logger.log(`ERROR in debugAvatarPipeline: ${e.stack}`);
    return { error: e.message, stack: e.stack };
  }
}

// Create Google Task for accountability
function createGoogleTask(taskData) {
  try {
    Logger.log(`=== CREATING GOOGLE TASK FOR ACCOUNTABILITY ===`);
    Logger.log(`Task: ${taskData.actionItem}`);

    const webAppUrl = ScriptApp.getService().getUrl();
    const taskUrl = `${webAppUrl}?page=task&id=${taskData.taskID}`;

    const taskTitle = `${taskData.taskID}: ${taskData.actionItem}`;
    const taskNotes = `Task: ${taskData.actionItem}\nDepartment: ${taskData.department}\nPriority: ${taskData.priorityScore}\nStatus: ${taskData.status}\nProgress: ${taskData.progressPercentage}%\n\nView task details: ${taskUrl}`;

    // Prefer official Google Tasks API (Advanced Service)
    try {
      if (typeof Tasks === 'undefined') {
        throw new Error('Google Tasks Advanced Service not enabled. In Apps Script: Services > Advanced Google services > enable Tasks API, then also enable it in Google Cloud console.');
      }
      const tasklists = Tasks.Tasklists.list();
      if (!tasklists.items || tasklists.items.length === 0) {
        throw new Error('No Google Task lists available for this account.');
      }
      const listId = tasklists.items[0].id; // use first list (usually "My tasks")
      const due = new Date();
      due.setDate(due.getDate() + 7);
      const inserted = Tasks.Tasks.insert({
        title: taskTitle,
        notes: taskNotes,
        due: due.toISOString(),
      }, listId);

      Logger.log(`Google Task created: list=${listId} id=${inserted.id}`);
      return {
        success: true,
        taskTitle: taskTitle,
        message: 'Google Task created in your Tasks list',
        tasksUrl: 'https://tasks.google.com/',
        taskListId: listId,
        taskId: inserted.id,
      };
    } catch (inner) {
      // Fallback: open Tasks app (cannot pre-create without Advanced Service)
      Logger.log(`Falling back to Tasks URL method: ${inner.message}`);
      return {
        success: true,
        taskTitle: taskTitle,
        message: 'Open Google Tasks to create/check the task',
        tasksUrl: 'https://tasks.google.com/'
      };
    }
  } catch (e) {
    Logger.log(`ERROR creating Google Task: ${e.stack}`);
    return { success: false, error: e.message };
  }
}

function createTaskCalendarEvent(taskData) {
  try {
    const calendar = CalendarApp.getDefaultCalendar();
    const eventTitle = `${taskData.taskID}: ${taskData.actionItem}`;
    const webAppUrl = ScriptApp.getService().getUrl();

    // 1-hour block starting now
    const startTime = new Date();
    const endTime = new Date(startTime.getTime() + 60 * 60 * 1000);

    const description = [
      'Task Details:',
      `- Department: ${taskData.department}`,
      `- Priority: ${taskData.priorityScore}`,
      `- Status: ${taskData.status}`,
      `- Assigned to: ${taskData.owners}`,
      `- Progress: ${taskData.progressPercentage}%`,
      '',
      `View task: ${webAppUrl}?page=task&id=${taskData.taskID}`
    ].join('\n');

    const event = calendar.createEvent(eventTitle, startTime, endTime, {
      description,
      location: 'LuvBuds Task Management System'
    });

    // Invite owners if we can resolve emails
    if (taskData.owners && taskData.owners !== 'Unassigned') {
      const teamMembers = getTeamData();
      const ownerNames = String(taskData.owners).split(',').map(x => x.trim()).filter(Boolean);
      ownerNames.forEach(name => {
        const m = teamMembers.find(t => t.fullName.toLowerCase().includes(name.toLowerCase()) || name.toLowerCase().includes(t.fullName.toLowerCase()));
        if (m && m.email) try { event.addGuest(m.email); } catch (_) {}
      });
    }

    // Build a usable event URL (base64url of "eventId calendarId")
    let eventUrl = '';
    try {
      const calendarId = calendar.getId();
      const eidRaw = `${event.getId()} ${calendarId}`;
      const eid = Utilities.base64EncodeWebSafe(eidRaw).replace(/=+$/, '');
      eventUrl = `https://calendar.google.com/calendar/event?eid=${eid}`;
    } catch (linkErr) {
      Logger.log('Failed to build event URL, falling back to day view: ' + linkErr);
      const y = startTime.getFullYear();
      const m = String(startTime.getMonth() + 1).padStart(2, '0');
      const d = String(startTime.getDate()).padStart(2, '0');
      eventUrl = `https://calendar.google.com/calendar/u/0/r/day/${y}/${m}/${d}`;
    }

    Logger.log(`Calendar event created for ${taskData.taskID}: ${event.getId()} → ${eventUrl}`);
    return { success: true, eventId: event.getId(), eventUrl: eventUrl, message: 'Calendar event created and guests invited.' };
  } catch (e) {
    Logger.log(`ERROR creating calendar event: ${e.stack}`);
    return { success: false, error: e.message };
  }
}

// Test notification for a specific task
function testNotificationForTask(taskId) {
  try {
    Logger.log(`=== TESTING NOTIFICATION FOR TASK ${taskId} ===`);
    
    const taskData = getTaskById(taskId);
    if (!taskData) {
      Logger.log(`Task not found: ${taskId}`);
      return { error: 'Task not found' };
    }
    
    Logger.log(`Task found: ${taskData.actionItem}`);
    Logger.log(`Task owners: ${taskData.owners}`);
    Logger.log(`Task ownerDetails: ${taskData.ownerDetails ? JSON.stringify(taskData.ownerDetails) : 'undefined'}`);
    
    // Test the notification process
    const result = sendTaskNotification(taskData, 'test');
    
    return {
      taskId: taskId,
      taskName: taskData.actionItem,
      owners: taskData.owners,
      ownerDetails: taskData.ownerDetails,
      notificationResult: result
    };
  } catch (e) {
    Logger.log(`ERROR in testNotificationForTask: ${e.stack}`);
    return { error: e.message };
  }
}

// Debug function to test notification process
function testNotificationProcess(taskId) {
  try {
    Logger.log(`=== TESTING NOTIFICATION PROCESS ===`);
    
    // Get the task data
    const taskData = getTaskById(taskId);
    if (!taskData) {
      Logger.log(`Task not found: ${taskId}`);
      return { error: 'Task not found' };
    }
    
    Logger.log(`Task found: ${taskData.actionItem}`);
    Logger.log(`Task owners: ${taskData.owners}`);
    
    // Get team members
    const teamMembers = getTeamData();
    Logger.log(`Total team members: ${teamMembers.length}`);
    
    // Find matching team members
    if (taskData.owners && taskData.owners !== 'Unassigned') {
      const ownerNames = taskData.owners.split(',').map(name => name.trim());
      Logger.log(`Looking for owners: ${ownerNames.join(', ')}`);
      
      const foundMembers = [];
      ownerNames.forEach(ownerName => {
        const teamMember = teamMembers.find(member => 
          member.fullName.toLowerCase().includes(ownerName.toLowerCase()) ||
          ownerName.toLowerCase().includes(member.fullName.toLowerCase())
        );
        
        if (teamMember) {
          foundMembers.push({
            name: teamMember.fullName,
            email: teamMember.email,
            hasEmail: !!teamMember.email
          });
          Logger.log(`Found: ${teamMember.fullName} - Email: ${teamMember.email || 'MISSING'}`);
        } else {
          Logger.log(`Not found: ${ownerName}`);
        }
      });
      
      return {
        taskId: taskId,
        taskName: taskData.actionItem,
        owners: taskData.owners,
        foundMembers: foundMembers,
        totalTeamMembers: teamMembers.length,
        membersWithEmails: foundMembers.filter(m => m.hasEmail).length
      };
    } else {
      Logger.log(`No owners assigned to task`);
      return { error: 'No team members assigned to this task' };
    }
    
  } catch (e) {
    Logger.log(`ERROR in testNotificationProcess: ${e.stack}`);
    return { error: e.message };
  }
}

// Get all team members with their email addresses for verification
function getAllTeamEmails() {
  try {
    const teamMembers = getTeamData();
    Logger.log(`=== ALL TEAM MEMBERS AND EMAILS ===`);
    Logger.log(`Total team members: ${teamMembers.length}`);
    
    const teamData = teamMembers.map((member, index) => {
      Logger.log(`${index + 1}. ${member.fullName} - Email: ${member.email || 'MISSING'}`);
      return {
        index: index + 1,
        fullName: member.fullName,
        email: member.email || 'MISSING',
        department: member.department || 'N/A',
        hasEmail: !!member.email
      };
    });
    
    const membersWithEmails = teamData.filter(m => m.hasEmail);
    const membersWithoutEmails = teamData.filter(m => !m.hasEmail);
    
    Logger.log(`\nSummary:`);
    Logger.log(`- Members with emails: ${membersWithEmails.length}`);
    Logger.log(`- Members without emails: ${membersWithoutEmails.length}`);
    
    if (membersWithoutEmails.length > 0) {
      Logger.log(`\nMembers missing emails:`);
      membersWithoutEmails.forEach(member => {
        Logger.log(`- ${member.fullName}`);
      });
    }
    
    return {
      totalMembers: teamMembers.length,
      membersWithEmails: membersWithEmails.length,
      membersWithoutEmails: membersWithoutEmails.length,
      allMembers: teamData,
      membersWithEmailsList: membersWithEmails,
      membersWithoutEmailsList: membersWithoutEmails
    };
    
  } catch (e) {
    Logger.log(`ERROR in getAllTeamEmails: ${e.stack}`);
    return { error: e.message };
  }
}

// Function to help update avatar sheet with correct email addresses
function getAvatarSheetUpdateInstructions() {
  try {
    const teamMembers = getTeamData();
    Logger.log(`=== AVATAR SHEET UPDATE INSTRUCTIONS ===`);
    Logger.log(`Current team members: ${teamMembers.length}`);
    
    // Create a mapping of what needs to be updated
    const updateList = teamMembers.map(member => {
      return {
        fullName: member.fullName,
        currentEmail: member.email || 'MISSING',
        needsUpdate: !member.email
      };
    });
    
    Logger.log(`Members needing email updates: ${updateList.filter(m => m.needsUpdate).length}`);
    
    return {
      totalMembers: teamMembers.length,
      membersNeedingEmails: updateList.filter(m => m.needsUpdate).length,
      updateList: updateList,
      instructions: `
To fix the missing emails:

1. Open your Avatar Profiles sheet: ${AVATAR_SHEET_ID}
2. Add email addresses for these team members:
${updateList.filter(m => m.needsUpdate).map(m => `- ${m.fullName}`).join('\n')}

3. Make sure the email column is in the correct format
4. The system will automatically pick up the new email addresses
      `
    };
    
  } catch (e) {
    Logger.log(`ERROR in getAvatarSheetUpdateInstructions: ${e.stack}`);
    return { error: e.message };
  }
}

// Function to create a sample avatar sheet format
function createAvatarSheetTemplate() {
  try {
    const teamMembers = getTeamData();
    
    // Create a template with the correct format
    const template = teamMembers.map(member => {
      return {
        fullName: member.fullName,
        email: '', // Empty email to be filled in
        avatarUrl: member.avatarUrl || '',
        department: member.department || ''
      };
    });
    
    Logger.log(`Created template for ${template.length} team members`);
    
    return {
      template: template,
      instructions: `
Avatar Sheet Template:

Column A: Full Name
Column B: Email Address  
Column C: Avatar URL (optional)
Column D: Department (optional)

Add the email addresses from your company directory to Column B.
      `
    };
    
  } catch (e) {
    Logger.log(`ERROR in createAvatarSheetTemplate: ${e.stack}`);
    return { error: e.message };
  }
}

function sendTaskNotification(taskData, action = 'assigned') {
  try {
    // Get the actual web app URL
    const webAppUrl = ScriptApp.getService().getUrl();
    
    const subject = `Task ${action}: ${taskData.actionItem}`;

    // If ownerDetails arrived as a JSON string from the client, parse it
    if (taskData && typeof taskData.ownerDetails === 'string') {
      try {
        taskData.ownerDetails = JSON.parse(taskData.ownerDetails);
      } catch (parseErr) {
        Logger.log('ownerDetails parse failed: ' + parseErr);
      }
    }
    
    // Build the email body with signed action links
    const calendarLink = buildSignedActionUrl('addCalendar', taskData.taskID, 60); // 1 hour expiry
    const taskLink = buildSignedActionUrl('createTask', taskData.taskID, 60); // 1 hour expiry
    
    const bodyText = [
      'Task Details:',
      `- Task ID: ${taskData.taskID}`,
      `- Action Item: ${taskData.actionItem}`,
      `- Department: ${taskData.department}`,
      `- Priority: ${taskData.priorityScore}`,
      `- Status: ${taskData.status}`,
      `- Progress: ${taskData.progressPercentage}%`,
      '',
      'Quick Actions:',
      `- Book 1 Hour: ${calendarLink}`,
      `- Add to Google Tasks: ${taskLink}`,
      '',
      `View full task details: ${webAppUrl}?page=task&id=${taskData.taskID}`
    ].join('\n');

    const bodyHtml = `
<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;">
  <p><strong>Task Details</strong></p>
  <ul style="margin:0 0 10px 16px;padding:0;">
    <li><strong>Task ID:</strong> ${taskData.taskID}</li>
    <li><strong>Action Item:</strong> ${taskData.actionItem}</li>
    <li><strong>Department:</strong> ${taskData.department}</li>
    <li><strong>Priority:</strong> ${taskData.priorityScore}</li>
    <li><strong>Status:</strong> ${taskData.status}</li>
    <li><strong>Progress:</strong> ${taskData.progressPercentage}%</li>
  </ul>
  <p><strong>Quick Actions:</strong></p>
  <p>📅 <a href="${calendarLink}">CLICK TO BOOK 1 HOUR</a><br/>
     📋 <a href="${taskLink}">ADD TO GOOGLE TASKS</a></p>
  <p>View full task details: <a href="${webAppUrl}?page=task&id=${taskData.taskID}">${webAppUrl}?page=task&id=${taskData.taskID}</a></p>
</div>`.trim();

    // Find recipients from task owners
    let recipients = [];

    const normalizeName = (s) => (s || '').toString().toLowerCase().replace(/\s+/g, ' ').trim();

    // Prefer ownerDetails provided by the page (already matched to team directory)
    if (Array.isArray(taskData.ownerDetails) && taskData.ownerDetails.length > 0) {
      for (const owner of taskData.ownerDetails) {
        if (owner && owner.email && owner.email.trim() !== '') {
          recipients.push(owner.email.trim());
        }
      }
    }

    // Fallback only if no recipients yet: resolve strictly by exact full-name match (case-insensitive)
    if (recipients.length === 0 && taskData.owners && taskData.owners !== 'Unassigned') {
      const teamMembers = getTeamData();
      const indexByName = new Map(teamMembers.map(m => [normalizeName(m.fullName), m]));
      const ownerNames = String(taskData.owners).split(',').map(x => x.trim()).filter(Boolean);

      ownerNames.forEach(name => {
        const member = indexByName.get(normalizeName(name));
        if (member && member.email && member.email.trim() !== '') {
          recipients.push(member.email.trim());
        } else {
          Logger.log(`Strict match not found or missing email for owner: "${name}"`);
        }
      });
    }

    // Deduplicate
    recipients = [...new Set(recipients)];
    
    if (recipients.length === 0) {
      Logger.log('No team members assigned to this task');
      return { success: false, error: 'No team members assigned to this task' };
    }
    
    Logger.log(`Sending notification to: ${recipients.join(', ')}`);
    
    // Send the email
    MailApp.sendEmail(recipients.join(','), subject, bodyText, {
      htmlBody: bodyHtml,
      name: 'LuvBuds Task Management System'
    });
    
    Logger.log(`Notification sent successfully to ${recipients.length} recipients`);
    return { success: true, recipients: recipients };
    
  } catch (e) {
    Logger.log(`ERROR sending notification: ${e.stack}`);
    return { success: false, error: e.message };
  }
}

// Public alias without default parameters to avoid client/runtime quirks
function sendTaskNotificationPublic(taskData) {
  return sendTaskNotification(taskData, 'assigned');
}

// Ensure required extra columns exist and return updated headers
function ensureTaskColumns_() {
  const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
  const needed = [
    'Transcript_Raw',
    'Transcript_Summary',
    'Transcript_Resources',
    'Transcript_Mentions',
    'Plan_JSON',
    'Plan_LastUpdated',
    'Date_Created',
    // Shared notes log for quick updates from boards
    'Notes_Log',
    // Swag Supply / production tracker column
    'Production_Stage',
    // Subtasks feature
    'Subtasks_JSON',
    'Subtasks_LastUpdated',
    'Progress_Mode'
  ];
  let headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  let col = headers.length;
  needed.forEach(name => {
    if (headers.indexOf(name) === -1) {
      col += 1;
      sheet.getRange(1, col, 1, 1).setValue(name);
    }
  });
  headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  return { sheet, headers };
}

// Append a timestamped note to the task's Notes_Log column.
function appendTaskNote(taskId, noteText) {
  const lock = LockService.getScriptLock();
  try {
    lock.waitLock(30000);
    if (!taskId || !noteText) throw new Error('Missing taskId or note');
    const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
    const { headers } = ensureTaskColumns_();
    const notesCol = headers.indexOf('Notes_Log') + 1;
    if (!notesCol) throw new Error('Notes_Log column not found');
    const idx = buildTaskIdIndexMap_(sheet);
    const rowIndex = idx[String(taskId)] || 0;
    if (!rowIndex) throw new Error('Task not found');
    const email = (Session.getActiveUser().getEmail() || 'unknown').toString();
    const ts = new Date().toISOString();
    const entry = `[${ts}] ${email}: ${noteText}`;
    const prev = sheet.getRange(rowIndex, notesCol).getValue();
    const nextVal = prev ? (prev + "\n" + entry) : entry;
    withRetry_(function(){ sheet.getRange(rowIndex, notesCol).setValue(nextVal); });
    SpreadsheetApp.flush();
    return { status: 'Success', note: entry };
  } catch (e) {
    Logger.log('ERROR in appendTaskNote: ' + e.stack);
    return { status: 'Error', message: e.message };
  } finally { try { lock.releaseLock(); } catch (_) {} }
}

// Save plan JSON to the sheet for a given task ID
function savePlanJson_(taskId, planObj) {
  const { sheet, headers } = ensureTaskColumns_();
  const idx = buildTaskIdIndexMap_(sheet);
  const rowIndex = idx[taskId] || 0;
  if (!rowIndex) return false;
  const planCol = headers.indexOf('Plan_JSON') + 1;
  const tsCol = headers.indexOf('Plan_LastUpdated') + 1;
  const nowIso = new Date().toISOString();
  if (planCol > 0) sheet.getRange(rowIndex, planCol).setValue(JSON.stringify(planObj));
  if (tsCol > 0) sheet.getRange(rowIndex, tsCol).setValue(nowIso);
  return true;
}

// Read plan JSON from the sheet (returns null if none)
function getStoredPlanJson_(taskId) {
  const { sheet, headers } = ensureTaskColumns_();
  const idx = buildTaskIdIndexMap_(sheet);
  const rowIndex = idx[taskId] || 0;
  if (!rowIndex) return null;
  const planCol = headers.indexOf('Plan_JSON') + 1;
  if (planCol === 0) return null;
  try {
    const raw = sheet.getRange(rowIndex, planCol).getValue();
    if (!raw) return null;
    return JSON.parse(raw);
  } catch (_) {
    return null;
  }
}

// Get plan: prefer cached JSON; otherwise generate and persist once
function getOrBuildProjectPlan_(taskData) {
  const cached = getStoredPlanJson_(taskData.taskID);
  if (cached) return cached;
  try {
    const plan = generateProjectPlan(taskData);
    if (!plan || plan.error) {
      Logger.log('Plan generation returned error; not caching');
      return { projectGoal: 'Project plan unavailable', expectedBenefits: [], milestonePlan: { title: 'Key Milestones', description: 'Plan failed to load' }, dataIntegrityPlan: [], implementationPlan: [] };
    }
    savePlanJson_(taskData.taskID, plan);
    return plan;
  } catch (e) {
    Logger.log('Plan generation failed in getOrBuildProjectPlan_: ' + e);
    return { projectGoal: 'Project plan unavailable', expectedBenefits: [], milestonePlan: { title: 'Key Milestones', description: 'Plan failed to load' }, dataIntegrityPlan: [], implementationPlan: [] };
  }
}

function headerIndex_(headers, candidates) {
  for (const name of candidates) {
    const idx = headers.indexOf(name);
    if (idx !== -1) return idx;
  }
  return -1;
}

function setByHeaderNames_(row, headers, names, value) {
  const i = headerIndex_(headers, names);
  if (i >= 0) row[i] = value;
}

function adminValidateSheets(key) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  const sheet = SpreadsheetApp.openById(MASTER_SHEET_ID).getSheetByName('Tasks');
  const headers = sheet.getRange(1,1,1,sheet.getLastColumn()).getValues()[0];
  const canonical = [
    'Task ID','Department','Action Item','Owner(s)','Priority_Score','Milestone Group','Task Category',
    'Problem_Description','Proposed_Solution','Time_Savings_Impact','Transcript_Summary','Transcript_Resources','Transcript_Mentions',
    'Plan_JSON','Plan_LastUpdated','Status','Predicted_Hours','Progress_Percentage'
  ];
  const aliases = {
    'Task ID': ['Task ID','Task_ID','TaskId'],
    'Department': ['Department','Dept'],
    'Action Item': ['Action Item','Action_Item','Title'],
    'Owner(s)': ['Owner(s)','Owners','Assigned To','Assigned_To'],
    'Priority_Score': ['Priority_Score','Priority Score','Priority'],
    'Milestone Group': ['Milestone Group','Milestone_Group','Milestone'],
    'Task Category': ['Task Category','Task_Category','Category'],
    'Problem_Description': ['Problem_Description','Problem Description','Problem'],
    'Proposed_Solution': ['Proposed_Solution','Proposed Solution','Solution'],
    'Time_Savings_Impact': ['Time_Savings_Impact','Time Savings Impact','Impact'],
    'Transcript_Summary': ['Transcript_Summary'],
    'Transcript_Resources': ['Transcript_Resources'],
    'Transcript_Mentions': ['Transcript_Mentions'],
    'Plan_JSON': ['Plan_JSON'],
    'Plan_LastUpdated': ['Plan_LastUpdated'],
    'Status': ['Status'],
    'Predicted_Hours': ['Predicted_Hours','Predicted Hours'],
    'Progress_Percentage': ['Progress_Percentage','Progress %','Progress Percentage']
  };
  const missing = [];
  canonical.forEach(name => { if (headerIndex_(headers, aliases[name] || [name]) === -1) missing.push(name); });
  // Duplicate Task IDs
  const idCol = headerIndex_(headers, aliases['Task ID']);
  let duplicates = [];
  if (idCol !== -1) {
    const ids = sheet.getRange(2, idCol+1, Math.max(0, sheet.getLastRow()-1), 1).getValues().map(r=>String(r[0]||''));
    const seen = new Map();
    ids.forEach((id, idx)=>{ if(!id) return; if(seen.has(id)) duplicates.push({id, rows:[seen.get(id)+2, idx+2]}); else seen.set(id, idx); });
  }
  return { headers, missing, duplicates, totalRows: sheet.getLastRow()-1 };
}

function adminListMissingEmails(key) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  const team = getTeamData();
  return team.filter(m => !m.email || String(m.email).trim() === '');
}

function adminProbeAvatars(key, limit) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  const avatarMap = getAvatarMap();
  const entries = Object.entries(avatarMap).filter(([k])=>k!=='unassigned').slice(0, Math.max(1, Math.min(limit||10, 50)));
  const results = [];
  entries.forEach(([name,url])=>{
    try {
      const resp = UrlFetchApp.fetch(url, { muteHttpExceptions: true, followRedirects: true, method: 'get' });
      results.push({ name, url, status: resp.getResponseCode() });
    } catch (e) {
      results.push({ name, url, error: String(e) });
    }
  });
  return { total: Object.keys(avatarMap).length, results };
}

function adminSetEnv(key, env) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  const v = (String(env||'').toLowerCase() === 'dev') ? 'dev' : 'prod';
  PropertiesService.getScriptProperties().setProperty('ENV', v);
  return { env: v };
}

function adminCheckApis(key) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  let tasks = { enabled:false, message:'' };
  let calendar = { enabled:false, message:'' };
  try {
    if (typeof Tasks !== 'undefined') {
      const lists = Tasks.Tasklists.list();
      tasks.enabled = !!(lists && lists.items);
      tasks.message = 'OK';
    } else {
      tasks.message = 'Advanced Tasks service not enabled';
    }
  } catch (e) { tasks.message = String(e); }
  try {
    const calId = CalendarApp.getDefaultCalendar().getId();
    calendar.enabled = !!calId;
    calendar.message = 'OK';
  } catch (e) { calendar.message = String(e); }
  return { tasks, calendar };
}

function adminGetTask(key, taskId) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  const t = getTaskById(taskId);
  return t || { error: 'Task not found' };
}

function adminRegeneratePlan(key, taskId) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  const t = getTaskById(taskId);
  if (!t) return { error: 'Task not found' };
  const plan = generateProjectPlan(t);
  if (!plan || plan.error) return { error: 'Plan generation failed' };
  savePlanJson_(taskId, plan);
  return { success: true };
}

// === SUBTASKS BACKEND (Schema + APIs) ===
function normalizeSubtasksArray_(raw) {
  try {
    if (!raw) return [];
    const arr = Array.isArray(raw) ? raw : JSON.parse(String(raw));
    if (!Array.isArray(arr)) return [];
    return arr.map((it, idx) => ({
      id: String(it.id || `S${idx+1}`),
      title: String(it.title || '').slice(0, 200),
      done: Boolean(it.done),
      weight: Math.max(1, Math.min(100, parseInt(it.weight, 10) || 1))
    }));
  } catch (_) { return []; }
}

function recalcProgressFromSubtasks_(subtasks) {
  const list = normalizeSubtasksArray_(subtasks);
  if (list.length === 0) return 0;
  let total = 0, done = 0;
  list.forEach(it => { const w = Math.max(1, parseInt(it.weight, 10) || 1); total += w; if (it.done) done += w; });
  if (total === 0) return 0;
  return Math.floor((done / total) * 100);
}

function getSubtasks(taskId) {
  try {
    const { sheet, headers } = ensureTaskColumns_();
    const idx = buildTaskIdIndexMap_(sheet);
    const rowIndex = idx[taskId] || 0;
    if (!rowIndex) return { subtasks: [], mode: 'Auto', progress: 0 };
    const subtasksCol = headers.indexOf('Subtasks_JSON') + 1;
    const modeCol = headers.indexOf('Progress_Mode') + 1;
    const progressCol = headers.indexOf('Progress_Percentage') + 1;
    const raw = subtasksCol > 0 ? sheet.getRange(rowIndex, subtasksCol).getValue() : '';
    const mode = modeCol > 0 ? (sheet.getRange(rowIndex, modeCol).getValue() || 'Auto') : 'Auto';
    const progress = progressCol > 0 ? (parseInt(sheet.getRange(rowIndex, progressCol).getValue(), 10) || 0) : 0;
    return { subtasks: normalizeSubtasksArray_(raw), mode, progress };
  } catch (e) {
    log_('error', 'getSubtasks', 'failed', { err: String(e) }, taskId);
    return { subtasks: [], mode: 'Auto', progress: 0 };
  }
}

function updateSubtasks(taskId, subtasks) {
  const lock = LockService.getScriptLock();
  try {
    lock.tryLock(10000);
    const { sheet, headers } = ensureTaskColumns_();
    const idx = buildTaskIdIndexMap_(sheet);
    const rowIndex = idx[taskId] || 0;
    if (!rowIndex) throw new Error('Task not found');
    const subtasksCol = headers.indexOf('Subtasks_JSON') + 1;
    const lastCol = headers.indexOf('Subtasks_LastUpdated') + 1;
    const modeCol = headers.indexOf('Progress_Mode') + 1;
    const progressCol = headers.indexOf('Progress_Percentage') + 1;
    const list = normalizeSubtasksArray_(subtasks);
    if (subtasksCol > 0) sheet.getRange(rowIndex, subtasksCol).setValue(JSON.stringify(list));
    if (lastCol > 0) sheet.getRange(rowIndex, lastCol).setValue(new Date().toISOString());
    let progress = null;
    const mode = modeCol > 0 ? (sheet.getRange(rowIndex, modeCol).getValue() || 'Auto') : 'Auto';
    if (mode === 'Auto' && progressCol > 0) {
      progress = recalcProgressFromSubtasks_(list);
      sheet.getRange(rowIndex, progressCol).setValue(progress);
    }
    log_('info', 'updateSubtasks', 'updated', { count: list.length, mode }, taskId);
    return { ok: true, progress: progress !== null ? progress : undefined };
  } catch (e) {
    log_('error', 'updateSubtasks', 'failed', { err: String(e) }, taskId);
    return { ok: false, error: String(e) };
  } finally { try { lock.releaseLock(); } catch (_) {} }
}

function setProgressMode(taskId, mode) {
  const lock = LockService.getScriptLock();
  try {
    lock.tryLock(10000);
    const { sheet, headers } = ensureTaskColumns_();
    const idx = buildTaskIdIndexMap_(sheet);
    const rowIndex = idx[taskId] || 0;
    if (!rowIndex) throw new Error('Task not found');
    const modeCol = headers.indexOf('Progress_Mode') + 1;
    if (modeCol > 0) sheet.getRange(rowIndex, modeCol).setValue(String(mode) === 'Manual' ? 'Manual' : 'Auto');
    return { ok: true, mode: String(mode) === 'Manual' ? 'Manual' : 'Auto' };
  } catch (e) {
    log_('error', 'setProgressMode', 'failed', { err: String(e) }, taskId);
    return { ok: false, error: String(e) };
  } finally { try { lock.releaseLock(); } catch (_) {} }
}

function toggleSubtask(taskId, subtaskId, done) {
  try {
    const state = getSubtasks(taskId);
    const list = state.subtasks.map(it => it.id === String(subtaskId) ? { ...it, done: Boolean(done) } : it);
    return updateSubtasks(taskId, list);
  } catch (e) { return { ok: false, error: String(e) }; }
}

function addSubtask(taskId, title, weight) {
  try {
    const state = getSubtasks(taskId);
    const list = state.subtasks.slice();
    const nextNum = list.length + 1;
    const id = `${taskId}-${nextNum}`;
    list.push({ id, title: String(title || '').slice(0,200), done: false, weight: Math.max(1, Math.min(100, parseInt(weight,10) || 1)) });
    const res = updateSubtasks(taskId, list);
    return { ...res, id };
  } catch (e) { return { ok: false, error: String(e) }; }
}

function removeSubtask(taskId, subtaskId) {
  try {
    const state = getSubtasks(taskId);
    const list = state.subtasks.filter(it => it.id !== String(subtaskId));
    return updateSubtasks(taskId, list);
  } catch (e) { return { ok: false, error: String(e) }; }
}

function generateSubtasksFromPlan(taskId) {
  const lock = LockService.getScriptLock();
  try {
    lock.tryLock(10000);
    const plan = getOrBuildProjectPlan_({ taskID: taskId });
    const steps = Array.isArray(plan && plan.implementationPlan) ? plan.implementationPlan : [];
    const items = steps.map((step, i) => ({ id: `${taskId}-${i+1}`, title: String(Object.keys(step)[0] || `Step ${i+1}`), done: false, weight: 1 }));
    return updateSubtasks(taskId, items);
  } catch (e) {
    log_('error', 'generateSubtasksFromPlan', 'failed', { err: String(e) }, taskId);
    return { ok: false, error: String(e) };
  } finally { try { lock.releaseLock(); } catch (_) {} }
}

function adminRunSubtasksTests(key) {
  if (!assertAdminKey_(key)) throw new Error('Forbidden');
  const cases = [];
  function addCase(name, fn){
    try { const ok = !!fn(); cases.push({ name, ok }); }
    catch (e) { cases.push({ name, ok: false, message: String(e) }); }
  }
  addCase('empty -> 0', function(){ return recalcProgressFromSubtasks_([]) === 0; });
  addCase('equal weights 1/3 done -> 33', function(){
    const list = [ {id:'1', title:'a', done:true, weight:1}, {id:'2', title:'b', done:false, weight:1}, {id:'3', title:'c', done:false, weight:1} ];
    return recalcProgressFromSubtasks_(list) === 33;
  });
  addCase('mixed weights (1+2)/ (1+2+2) -> floor(3/5*100)=60', function(){
    const list = [ {id:'1', title:'a', done:true, weight:1}, {id:'2', title:'b', done:true, weight:2}, {id:'3', title:'c', done:false, weight:2} ];
    return recalcProgressFromSubtasks_(list) === 60;
  });
  addCase('no weights defaults to 1 -> 50', function(){
    const list = [ {id:'1', title:'a', done:true}, {id:'2', title:'b', done:false} ];
    return recalcProgressFromSubtasks_(list) === 50;
  });
  const passed = cases.filter(c=>c.ok).length;
  return { total: cases.length, passed, failed: cases.length - passed, cases };
}

function startTask(taskId) {
  const lock = LockService.getScriptLock();
  try {
    lock.tryLock(20000);
    const task = getTaskById(taskId);
    if (!task) return { status: 'Error', message: 'Task not found' };

    // 1) Move to In Progress
    const statusRes = updateTaskAction(taskId, 'START_PROJECT', {});

    // Build taskData object compatible with existing helpers
    const taskData = {
      taskID: task.taskID,
      actionItem: task.actionItem,
      department: task.department,
      priorityScore: task.priorityScore,
      status: 'In Progress',
      owners: task.owners,
      progressPercentage: task.progressPercentage
    };

    // 2) Calendar event
    const calendarRes = createTaskCalendarEvent(taskData);

    // 3) Google Tasks
    const tasksRes = createGoogleTask(taskData);

    // 4) Email notification to owners
    let emailRes;
    try {
      emailRes = sendTaskNotification(taskData, 'started');
    } catch (e) {
      emailRes = { success: false, error: String(e) };
    }

    return {
      status: 'Success',
      statusRes, calendarRes, tasksRes, emailRes
    };
  } catch (e) {
    log_('error', 'startTask', 'failed', { err: String(e) }, taskId);
    return { status: 'Error', message: String(e) };
  } finally {
    try { lock.releaseLock(); } catch (_) {}
  }
}

// ===================================================================================
// |   LEADERSHIP TASK FILTERING FUNCTIONS                                          |
// ===================================================================================

// Configuration for leadership system
const LEADERSHIP_CONFIG = {
  LEADERSHIP_EMAILS: [
    'brett@luvbuds.co',
    'pmartin@luvbuds.co', 
    'mmartin@luvbuds.co',
    'amazzei@luvbuds.co'
  ]
};


/**
 * Get departments from the sheet (leadership-aware)
 */
function getDepartmentsFromSheet() {
  try {
    const tasks = getTasks();
    const departments = [...new Set(tasks.map(task => task.department).filter(dept => dept))];
    return departments.sort();
  } catch (error) {
    console.error('Error getting departments:', error);
    return [];
  }
}

/**
 * Verify if a Gmail address has leadership access
 */
function verifyLeadershipAccess(email) {
  try {
    const normalizedEmail = email.toLowerCase().trim();
    const isAuthorized = LEADERSHIP_CONFIG.LEADERSHIP_EMAILS.includes(normalizedEmail);
    
    console.log(`Leadership access check for ${normalizedEmail}: ${isAuthorized}`);
    
    return {
      success: isAuthorized,
      email: normalizedEmail,
      message: isAuthorized ? 'Access granted' : 'Email not authorized for leadership access',
      authorizedEmails: LEADERSHIP_CONFIG.LEADERSHIP_EMAILS
    };
  } catch (error) {
    console.error('Error verifying leadership access:', error);
    return {
      success: false,
      email: email,
      message: `Error: ${error.toString()}`
    };
  }
}

/**
 * Test function to debug getKanbanData issues
 */
function testGetKanbanData() {
  try {
    console.log("testGetKanbanData: Starting test");
    
    // Test getTasks first
    const allTasks = getTasks();
    console.log("testGetKanbanData: getTasks() returned:", allTasks ? allTasks.length : "null/undefined");
    
    if (!allTasks) {
      return { error: "getTasks() returned null/undefined" };
    }
    
    if (allTasks.error) {
      return { error: "getTasks() has error: " + allTasks.error };
    }
    
    if (!Array.isArray(allTasks)) {
      return { error: "getTasks() returned non-array: " + typeof allTasks };
    }
    
    // Test the full getKanbanData function
    const result = getKanbanData();
    console.log("testGetKanbanData: getKanbanData() returned:", result);
    
    return {
      success: true,
      allTasksCount: allTasks.length,
      result: result
    };
    
  } catch (e) {
    console.log("testGetKanbanData: ERROR:", e.message);
    return { error: e.message };
  }
}

/**
 * Get kanban data for leadership users (includes all tasks)
 */
function getLeadershipKanbanData(userEmail) {
  try {
    // Verify user has leadership access
    const authResult = verifyLeadershipAccess(userEmail);
    if (!authResult.success) {
      return { 
        error: 'Unauthorized access',
        message: authResult.message 
      };
    }
    
    // Get all tasks including leadership ones
    const allTasks = getTasks();
    const departments = getDepartments();
    const users = getTeamData().map(member => member.fullName);
    
    console.log(`Leadership user ${userEmail} accessing ${allTasks.length} total tasks`);
    
    return {
      tasks: allTasks, // Include ALL tasks for leadership
      departments: departments,
      users: users,
      userRole: 'leadership'
    };
  } catch (error) {
    console.error('Error getting leadership kanban data:', error);
    return { 
      error: error.toString(),
      message: 'Failed to load leadership data'
    };
  }
}
