/**
 * Leadership Task Management System
 * Google Apps Script for LuvBuds Task Management
 * 
 * This script handles:
 * - Leadership task authentication
 * - Automatic leadership column management
 * - Task filtering based on user permissions
 * - New task categorization
 */

// Configuration - UPDATE THESE VALUES
const CONFIG = {
  // Your Google Sheet ID (from the URL)
  SHEET_ID: '161omaX8sXMPPvL9iqHi5c6EUUbn5ywQRhOPrIZ_-MVc',
  // Google Identity Services OAuth 2.0 Client ID (Web application)
  // Create in Google Cloud Console and paste the value here
  GOOGLE_CLIENT_ID: PropertiesService.getScriptProperties().getProperty('GOOGLE_CLIENT_ID') || 'REPLACE_ME.apps.googleusercontent.com',
  
  // Authorized leadership Gmail addresses
  LEADERSHIP_EMAILS: [
    'brett@luvbuds.co',
    'pmartin@luvbuds.co', 
    'mmartin@luvbuds.co',
    'amazzei@luvbuds.co',
    // Add more leadership emails here
  ],
  
  // Leadership keywords that should automatically mark tasks as leadership
  LEADERSHIP_KEYWORDS: [
    'strategic',
    'confidential',
    'executive',
    'board',
    'leadership',
    'ceo',
    'cto',
    'vp',
    'founder',
    'budget',
    'financial',
    'acquisition',
    'merger',
    'partnership',
    'investor',
    'funding',
    'legal',
    'compliance',
    'hr confidential',
    'salary',
    'compensation'
  ],
  
  // Department names that should be leadership-only
  LEADERSHIP_DEPARTMENTS: [
    'Executive',
    'Leadership',
    'Board',
    'Strategic Planning',
    'Legal',
    'Finance',
    'HR Confidential'
  ]
};

// Internal helper: consistently open the Tasks sheet (not the active sheet)
function getTasksSheet_() {
  const ss = SpreadsheetApp.openById(CONFIG.SHEET_ID);
  // The primary task data lives on the 'Tasks' sheet
  const sheet = ss.getSheetByName('Tasks') || ss.getActiveSheet();
  return sheet;
}

// Internal helper: read a truthy leadership flag across common variants
function readLeadershipFlag_(obj) {
  const cand = obj?.isLeadership ?? obj?.['isLeadership'] ?? obj?.isleadership ?? obj?.['IsLeadership'] ?? obj?.['is Leadership'] ?? obj?.['Is Leadership'];
  if (cand === true) return true;
  if (cand === false || cand === null || cand === undefined) return false;
  const s = String(cand).trim();
  if (s === 'TRUE') return true;
  const sl = s.toLowerCase();
  return sl === 'true' || sl === 'yes' || sl === 'y' || sl === '1';
}

/**
 * Initialize the leadership system
 * Run this once to set up the leadership column
 */
function initializeLeadershipSystem() {
  try {
    const sheet = getTasksSheet_();
    const headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    
    // Check if leadership column already exists
    const leadershipColumnIndex = headers.indexOf('isLeadership');
    
    if (leadershipColumnIndex === -1) {
      // Add leadership column
      const newColumnIndex = sheet.getLastColumn() + 1;
      sheet.getRange(1, newColumnIndex).setValue('isLeadership');
      
      // Add header styling
      sheet.getRange(1, newColumnIndex).setBackground('#ffd700');
      sheet.getRange(1, newColumnIndex).setFontWeight('bold');
      
      console.log(`Leadership column added at column ${newColumnIndex}`);
      
      // Auto-categorize existing tasks
      autoCategorizeExistingTasks();
      
      return {
        success: true,
        message: `Leadership column added successfully at column ${newColumnIndex}`,
        columnIndex: newColumnIndex
      };
    } else {
      console.log(`Leadership column already exists at column ${leadershipColumnIndex + 1}`);
      return {
        success: true,
        message: `Leadership column already exists at column ${leadershipColumnIndex + 1}`,
        columnIndex: leadershipColumnIndex + 1
      };
    }
  } catch (error) {
    console.error('Error initializing leadership system:', error);
    return {
      success: false,
      message: `Error: ${error.toString()}`
    };
  }
}

/**
 * Helper function to find column index by trying multiple possible names
 */
function findColumnIndex(headers, possibleNames) {
  for (const name of possibleNames) {
    const index = headers.indexOf(name);
    if (index !== -1) {
      return index + 1; // Return 1-based index
    }
  }
  return 0; // Not found
}

/**
 * Auto-categorize existing tasks based on content
 */
function autoCategorizeExistingTasks() {
  try {
    const sheet = getTasksSheet_();
    const data = sheet.getDataRange().getValues();
    const headers = data[0];
    
    // Try different possible column names (updated for your actual sheet structure)
    const taskColumnIndex = findColumnIndex(headers, ['Action_Item', 'actionItem', 'Action Item', 'action_item', 'Task', 'task', 'Description', 'description']);
    const departmentColumnIndex = findColumnIndex(headers, ['Department', 'department', 'dept', 'Dept', 'Team', 'team']);
    const leadershipColumnIndex = headers.indexOf('isLeadership') + 1;
    
    console.log('Column indices found:');
    console.log('Task column:', taskColumnIndex, '(looking for action item)');
    console.log('Department column:', departmentColumnIndex, '(looking for department)');
    console.log('Leadership column:', leadershipColumnIndex, '(looking for isLeadership)');
    
    if (leadershipColumnIndex === 0) {
      throw new Error('Leadership column not found - run initializeLeadershipSystem() first');
    }
    
    if (taskColumnIndex === 0) {
      console.log('Warning: Task column not found, skipping auto-categorization');
      return {
        success: true,
        message: 'Leadership column exists but task column not found - manual categorization required',
        updatedCount: 0
      };
    }
    
    let updatedCount = 0;
    
    // Process each row (skip header)
    for (let i = 1; i < data.length; i++) {
      const taskText = (data[i][taskColumnIndex - 1] || '').toString().toLowerCase();
      const department = (data[i][departmentColumnIndex - 1] || '').toString();
      const currentLeadershipValue = data[i][leadershipColumnIndex - 1];
      
      // Skip if already categorized
      if (currentLeadershipValue === true || currentLeadershipValue === 'TRUE') {
        continue;
      }
      
      let isLeadership = false;
      
      // Check keywords in task text
      for (const keyword of CONFIG.LEADERSHIP_KEYWORDS) {
        if (taskText.includes(keyword.toLowerCase())) {
          isLeadership = true;
          break;
        }
      }
      
      // Check department
      if (!isLeadership) {
        for (const dept of CONFIG.LEADERSHIP_DEPARTMENTS) {
          if (department.toLowerCase().includes(dept.toLowerCase())) {
            isLeadership = true;
            break;
          }
        }
      }
      
      // Update the cell if it should be leadership
      if (isLeadership) {
        sheet.getRange(i + 1, leadershipColumnIndex).setValue('TRUE');
        sheet.getRange(i + 1, leadershipColumnIndex).setBackground('#fff3cd');
        updatedCount++;
      }
    }
    
    console.log(`Auto-categorized ${updatedCount} tasks as leadership`);
    return {
      success: true,
      message: `Auto-categorized ${updatedCount} tasks as leadership`,
      updatedCount: updatedCount
    };
  } catch (error) {
    console.error('Error auto-categorizing tasks:', error);
    return {
      success: false,
      message: `Error: ${error.toString()}`
    };
  }
}

/**
 * Verify if a Gmail address has leadership access
 */
function verifyLeadershipAccess(email) {
  try {
    const normalizedEmail = email.toLowerCase().trim();
    const isAuthorized = CONFIG.LEADERSHIP_EMAILS.includes(normalizedEmail);
    
    console.log(`Leadership access check for ${normalizedEmail}: ${isAuthorized}`);
    
    return {
      success: isAuthorized,
      email: normalizedEmail,
      message: isAuthorized ? 'Access granted' : 'Email not authorized for leadership access',
      authorizedEmails: CONFIG.LEADERSHIP_EMAILS
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
 * Domain-session based auth helpers (no GIS token required)
 */
function getSessionEmail() {
  try {
    const email = (Session.getActiveUser().getEmail() || '').trim().toLowerCase();
    return { success: !!email, email: email };
  } catch (e) {
    return { success: false, email: '', message: e.toString() };
  }
}

function getLeadershipSessionStatus() {
  try {
    const s = getSessionEmail();
    if (!s.success || !s.email) {
      return { success: false, message: 'No active Workspace session detected.' };
    }
    return verifyLeadershipAccess(s.email);
  } catch (e) {
    return { success: false, message: e.toString() };
  }
}

function getLeadershipKanbanDataForSession() {
  try {
    const s = getSessionEmail();
    if (!s.success || !s.email) {
      return { error: 'Unauthorized access', message: 'No active Workspace session' };
    }
    const access = verifyLeadershipAccess(s.email);
    if (!access.success) {
      return { error: 'Unauthorized access', message: access.message };
    }
    const allTasks = getAllTasksFromSheet();
    const departments = getDepartmentsFromSheet();
    const users = getUsersFromSheet();
    return { tasks: allTasks, departments: departments, users: users, userRole: 'leadership', email: s.email };
  } catch (e) {
    return { error: e.toString(), message: 'Failed to load leadership data' };
  }
}

/**
 * Verify a Google ID token from Google Identity Services, then check leadership access.
 * This allows secure sign-in without manual email entry.
 */
function verifyGoogleIdToken(idToken) {
  try {
    if (!idToken) {
      return { success: false, message: 'Missing ID token' };
    }
    const url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' + encodeURIComponent(idToken);
    const res = UrlFetchApp.fetch(url, { muteHttpExceptions: true, followRedirects: true });
    if (res.getResponseCode() !== 200) {
      return { success: false, message: 'Token verification failed', status: res.getResponseCode(), body: res.getContentText() };
    }
    const payload = JSON.parse(res.getContentText());
    const audOk = payload.aud === CONFIG.GOOGLE_CLIENT_ID;
    const email = (payload.email || '').toLowerCase().trim();
    const verified = String(payload.email_verified) === 'true' || payload.email_verified === true;
    if (!audOk) {
      return { success: false, message: 'Invalid audience', expected: CONFIG.GOOGLE_CLIENT_ID, actual: payload.aud };
    }
    if (!verified || !email) {
      return { success: false, message: 'Email not verified or missing' };
    }
    // Reuse existing access check
    const access = verifyLeadershipAccess(email);
    return access;
  } catch (e) {
    return { success: false, message: 'verifyGoogleIdToken error: ' + e.toString() };
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
    const allTasks = getAllTasksFromSheet();
    const departments = getDepartmentsFromSheet();
    const users = getUsersFromSheet();
    
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

/**
 * Token-verified version: leadership data only if Google ID token is valid and email is authorized.
 */
function getLeadershipKanbanDataByToken(idToken) {
  try {
    const verify = verifyGoogleIdToken(idToken);
    if (!verify || !verify.success) {
      return { error: 'Unauthorized access', message: verify && verify.message ? verify.message : 'Invalid token' };
    }
    const allTasks = getAllTasksFromSheet();
    const departments = getDepartmentsFromSheet();
    const users = getUsersFromSheet();
    return {
      tasks: allTasks,
      departments: departments,
      users: users,
      userRole: 'leadership',
      email: verify.email
    };
  } catch (e) {
    return { error: e.toString(), message: 'Failed to load leadership data' };
  }
}

/**
 * Get kanban data for regular users (filters out leadership tasks)
 */
function getKanbanData() {
  try {
    const allTasks = getAllTasksFromSheet();
    const departments = getDepartmentsFromSheet();
    const users = getUsersFromSheet();
    
    // Filter out leadership tasks for regular users
    const regularTasks = allTasks.filter(task => !readLeadershipFlag_(task));
    
    console.log(`Regular user accessing ${regularTasks.length} tasks (filtered from ${allTasks.length} total)`);
    
    return {
      tasks: regularTasks,
      departments: departments,
      users: users,
      userRole: 'regular'
    };
  } catch (error) {
    console.error('Error getting regular kanban data:', error);
    return { 
      error: error.toString(),
      message: 'Failed to load task data'
    };
  }
}

/**
 * Get all tasks from the Google Sheet
 */
function getAllTasksFromSheet() {
  try {
    const sheet = getTasksSheet_();
    const data = sheet.getDataRange().getValues();
    const headers = data[0];
    
    console.log('Getting tasks from sheet with', data.length, 'rows and', headers.length, 'columns');
    
    const tasks = [];
    
    // Process each row (skip header)
    for (let i = 1; i < data.length; i++) {
      const row = data[i];
      const task = {};
      
      // Map each column to task properties
      headers.forEach((header, index) => {
        if (header && row[index] !== undefined) {
          // Convert header to a normalized key (camelCase)
          const propertyName = header.charAt(0).toLowerCase() + header.slice(1)
            .replace(/[^a-zA-Z0-9]+(.)?/g, (m, chr) => chr ? chr.toUpperCase() : '')
            .replace(/\s+/g, '');
          task[propertyName] = row[index];
          
          // Keep original header name as well
          task[header] = row[index];
        }
      });

      // Normalize leadership flag to a single canonical boolean
      task.isLeadership = readLeadershipFlag_(task);

      // Normalize owners to a single canonical string field `owners`
      // Handle common variants like "Owner(s)", "Owners", "Owner", camelCase artifacts like ownerS
      const ownerCandidates = [
        task.owners,
        task.ownerS,
        task['Owner(s)'],
        task['Owners'],
        task['Owner'],
        task.owner,
        task['owner(s)'],
        task['Owner(s) ']
      ];
      for (var oc = 0; oc < ownerCandidates.length; oc++) {
        if (ownerCandidates[oc] !== undefined && ownerCandidates[oc] !== null && String(ownerCandidates[oc]).trim() !== '') {
          task.owners = ownerCandidates[oc];
          break;
        }
      }
      
      // Check if this row has a task ID (try different possible names)
      const hasTaskId = task.taskid || task['Task_ID'] || task['Task ID'] || task.taskid || task['taskID'] || task['ID'] || task.id;
      
      if (hasTaskId) {
        tasks.push(task);
      }
    }
    
    console.log('Found', tasks.length, 'tasks in sheet');
    return tasks;
  } catch (error) {
    console.error('Error getting tasks from sheet:', error);
    return [];
  }
}

/**
 * Get departments from the sheet
 */
function getDepartmentsFromSheet() {
  try {
    const tasks = getAllTasksFromSheet();
    const departments = [...new Set(tasks.map(task => task.department).filter(dept => dept))];
    return departments.sort();
  } catch (error) {
    console.error('Error getting departments:', error);
    return [];
  }
}

/**
 * Get users from the sheet
 */
function getUsersFromSheet() {
  try {
    const tasks = getAllTasksFromSheet();
    const users = new Set();
    
    tasks.forEach(task => {
      // Prefer the normalized owners field but fall back to common variants just in case
      const ownerStr = (task.owners || task.ownerS || task['Owner(s)'] || task['Owners'] || task['Owner'] || '').toString();
      if (ownerStr) {
        ownerStr.split(',').map(o => o.trim()).forEach(owner => { if (owner) users.add(owner); });
      }
    });
    
    return Array.from(users).sort();
  } catch (error) {
    console.error('Error getting users:', error);
    return [];
  }
}

/**
 * Auto-categorize a new task when it's created
 * This should be called whenever a new task is added
 */
function autoCategorizeNewTask(taskData) {
  try {
    const taskText = (taskData.actionItem || '').toString().toLowerCase();
    const department = (taskData.department || '').toString();
    
    let isLeadership = false;
    
    // Check keywords in task text
    for (const keyword of CONFIG.LEADERSHIP_KEYWORDS) {
      if (taskText.includes(keyword.toLowerCase())) {
        isLeadership = true;
        break;
      }
    }
    
    // Check department
    if (!isLeadership) {
      for (const dept of CONFIG.LEADERSHIP_DEPARTMENTS) {
        if (department.toLowerCase().includes(dept.toLowerCase())) {
          isLeadership = true;
          break;
        }
      }
    }
    
    return {
      isLeadership: isLeadership,
      reason: isLeadership ? 'Contains leadership keywords or department' : 'Regular task',
      keywords: CONFIG.LEADERSHIP_KEYWORDS,
      departments: CONFIG.LEADERSHIP_DEPARTMENTS
    };
  } catch (error) {
    console.error('Error auto-categorizing new task:', error);
    return {
      isLeadership: false,
      reason: 'Error in categorization',
      error: error.toString()
    };
  }
}

/**
 * Update task details (for both regular and leadership users)
 */
function updateTaskDetails(taskId, updates) {
  try {
    const lock = LockService.getScriptLock();
    try { lock.waitLock(30000); } catch (e) { /* continue best-effort */ }
    if (!taskId) throw new Error('Missing taskId');

    const sheet = getTasksSheet_();
    const lastCol = sheet.getLastColumn();
    const headers = lastCol > 0 ? sheet.getRange(1, 1, 1, lastCol).getValues()[0] : [];

    // Resolve key columns (case-insensitive + variants)
    const taskIdCol = findColumnIndex(headers, ['taskID','Task_ID','Task ID','ID','Id','id','taskId']);
    if (!taskIdCol) throw new Error('Task ID column not found');

    // Find row by reading only taskId column
    const lastRow = sheet.getLastRow();
    if (lastRow < 2) throw new Error('No task rows');
    const ids = sheet.getRange(2, taskIdCol, lastRow - 1, 1).getValues();
    let rowIndex = -1;
    for (let i = 0; i < ids.length; i++) {
      if (String(ids[i][0]).trim() === String(taskId).trim()) { rowIndex = i + 2; break; }
    }
    if (rowIndex === -1) throw new Error(`Task with ID ${taskId} not found`);

    // Map update fields to actual header columns
    const colFor = (names) => findColumnIndex(headers, names);
    const columns = {
      owners: colFor(['owners','Owners','Owner(s)','Owner','ownerS','owner','Owner(s) ']),
      dueDate: colFor(['dueDate','Due Date','due','DueDate','Target Date','targetDate']),
      predictedHours: colFor(['predictedHours','Predicted Hours','Est Hours','Estimated Hours','estimatedHours']),
      actualHoursSpent: colFor(['actualHoursSpent','Actual Hours Spent','Actual Hours','actualHours'])
    };

    // Apply updates only to columns that exist
    Object.keys(updates || {}).forEach(key => {
      const col = columns[key];
      if (col && col > 0) {
        let val = updates[key];
        if (key === 'owners') {
          // sanitize owners similar to main code
          try {
            const parts = String(val || '').split(/[\n,]/).map(s => String(s).trim().replace(/^[“”"']+|[“”"']+$/g, '')).filter(Boolean);
            const seen = new Set(); const out = [];
            parts.forEach(n => { const k = n.toLowerCase(); if (!seen.has(k)) { seen.add(k); out.push(n); } });
            val = out.join(', ');
          } catch (_) {}
        }
        withRetry_(function(){ sheet.getRange(rowIndex, col).setValue(val); });
      }
    });

    SpreadsheetApp.flush();
    return { status: 'Success', message: 'Task updated successfully', taskId: taskId, updates: updates };
  } catch (error) {
    console.error('Error updating task details:', error);
    return { status: 'Error', message: error && error.message ? error.message : String(error) };
  }
  finally {
    try { LockService.getScriptLock().releaseLock(); } catch (_) {}
  }
}

/**
 * Update task status (for drag and drop)
 */
function updateTaskStatus(taskId, newStatus) {
  try {
    const lock = LockService.getScriptLock();
    try { lock.waitLock(30000); } catch (e) {}
    const sheet = getTasksSheet_();
    const headers = sheet.getRange(1,1,1,sheet.getLastColumn()).getValues()[0];
    const statusCol = findColumnIndex(headers, ['status','Status','Task_Status','taskStatus']);
    if (!statusCol) return updateTaskDetails(taskId, { status: newStatus });

    const taskIdCol = findColumnIndex(headers, ['taskID','Task_ID','Task ID','ID','Id','id','taskId']);
    if (!taskIdCol) throw new Error('Task ID column not found');
    const lastRow = sheet.getLastRow(); if (lastRow < 2) throw new Error('No rows');
    const ids = sheet.getRange(2, taskIdCol, lastRow-1, 1).getValues();
    let rowIndex = -1; for (let i=0;i<ids.length;i++){ if(String(ids[i][0]).trim()===String(taskId).trim()){ rowIndex=i+2; break; } }
    if (rowIndex === -1) throw new Error(`Task with ID ${taskId} not found`);
    withRetry_(function(){ sheet.getRange(rowIndex, statusCol).setValue(newStatus); });
    SpreadsheetApp.flush();
    return { status: 'Success', message: 'Status updated', taskId: taskId, statusValue: newStatus };
  } catch (error) {
    console.error('Error updating task status:', error);
    return { status: 'Error', message: error && error.message ? error.message : String(error) };
  }
  finally {
    try { LockService.getScriptLock().releaseLock(); } catch (_) {}
  }
}

// Retry helper to mitigate transient INTERNAL storage errors
function withRetry_(fn, attempts, sleepMs) {
  var tries = attempts || 4;
  var delay = sleepMs || 250;
  for (var i = 0; i < tries; i++) {
    try {
      return fn();
    } catch (e) {
      if (i === tries - 1) throw e;
      Utilities.sleep(delay);
      delay = Math.min(2000, Math.floor(delay * 1.8));
    }
  }
}

/**
 * Test function to verify the system is working
 */
function testLeadershipSystem() {
  console.log('Testing Leadership System...');
  
  const initResult = initializeLeadershipSystem();
  console.log('Init result:', initResult);
  
  const authResult = verifyLeadershipAccess('ceo@luvbuds.com');
  console.log('Auth test result:', authResult);
  
  const regularData = getKanbanData();
  console.log('Regular data count:', regularData.tasks?.length || 0);
  
  const leadershipData = getLeadershipKanbanData('ceo@luvbuds.com');
  console.log('Leadership data count:', leadershipData.tasks?.length || 0);
  
  return {
    initialization: initResult,
    authentication: authResult,
    regularTasks: regularData.tasks?.length || 0,
    leadershipTasks: leadershipData.tasks?.length || 0,
    totalTasks: (regularData.tasks?.length || 0) + (leadershipData.tasks?.length || 0)
  };
}

/**
 * Diagnostic function to inspect the Google Sheet structure
 * Run this first to see what columns actually exist
 */
function inspectSheetStructure() {
  try {
    const sheet = SpreadsheetApp.openById(CONFIG.SHEET_ID).getActiveSheet();
    const data = sheet.getDataRange().getValues();
    const headers = data[0];
    
    console.log('=== SHEET STRUCTURE INSPECTION ===');
    console.log('Total columns:', headers.length);
    console.log('Total rows:', data.length);
    console.log('');
    console.log('Column headers:');
    
    headers.forEach((header, index) => {
      console.log(`Column ${index + 1}: "${header}"`);
    });
    
    console.log('');
    console.log('Looking for key columns:');
    console.log('Task ID column:', headers.indexOf('taskID') + 1, '(looking for "taskID")');
    console.log('Action Item column:', headers.indexOf('actionItem') + 1, '(looking for "actionItem")');
    console.log('Department column:', headers.indexOf('department') + 1, '(looking for "department")');
    console.log('Status column:', headers.indexOf('status') + 1, '(looking for "status")');
    console.log('Owners column:', headers.indexOf('owners') + 1, '(looking for "owners")');
    
    // Show first few rows of data
    console.log('');
    console.log('First 3 rows of data:');
    for (let i = 0; i < Math.min(3, data.length); i++) {
      console.log(`Row ${i + 1}:`, data[i].slice(0, 5)); // Show first 5 columns
    }
    
    return {
      success: true,
      totalColumns: headers.length,
      totalRows: data.length,
      headers: headers,
      keyColumns: {
        taskID: headers.indexOf('taskID') + 1,
        actionItem: headers.indexOf('actionItem') + 1,
        department: headers.indexOf('department') + 1,
        status: headers.indexOf('status') + 1,
        owners: headers.indexOf('owners') + 1
      }
    };
  } catch (error) {
    console.error('Error inspecting sheet structure:', error);
    return {
      success: false,
      error: error.toString()
    };
  }
}

/**
 * Setup function - run this once to initialize everything
 */
function setupLeadershipSystem() {
  console.log('Setting up Leadership System for LuvBuds...');
  
  // Step 1: Initialize the leadership column
  const initResult = initializeLeadershipSystem();
  console.log('Step 1 - Column setup:', initResult);
  
  // Step 2: Auto-categorize existing tasks
  const categorizeResult = autoCategorizeExistingTasks();
  console.log('Step 2 - Auto-categorization:', categorizeResult);
  
  // Step 3: Test the system
  const testResult = testLeadershipSystem();
  console.log('Step 3 - System test:', testResult);
  
  return {
    setup: 'Complete',
    initialization: initResult,
    categorization: categorizeResult,
    testing: testResult,
    nextSteps: [
      '1. Update CONFIG.LEADERSHIP_EMAILS with your actual leadership emails',
      '2. Deploy the web app with these new functions',
      '3. Test with leadership and regular users',
      '4. Monitor the system for proper task categorization'
    ]
  };
}
