// ===================================================================================
// |   TASKMASTER API VERSION - MODERN FRONTEND INTEGRATION                        |
// |   - Pure API endpoints for React/Vue frontend                                 |
// |   - CORS enabled for cross-origin requests                                    |
// |   - Maintains all existing functionality as API endpoints                     |
// ===================================================================================

// --- CONFIGURATION (Keep existing) ---
const MASTER_SHEET_ID = "161omaX8sXMPPvL9iqHi5c6EUUbn5ywQRhOPrIZ_-MVc";
const AVATAR_SHEET_ID = "1iPTZ72wbx-CYu2tTcKe0QQe7HGbX_wUb9nfUFY2xp00";
const MISTRAL_API_KEY = PropertiesService.getScriptProperties().getProperty('MISTRAL_API_KEY'); 
const MISTRAL_API_ENDPOINT = 'https://api.mistral.ai/v1/chat/completions';

// --- API ROUTING (New) ---
function doGet(e) {
  return handleApiRequest(e);
}

function doPost(e) {
  return handleApiRequest(e);
}

function handleApiRequest(e) {
  // CORS headers for frontend integration
  const corsHeaders = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With',
    'Access-Control-Max-Age': '86400'
  };

  // Handle preflight requests
  if (e.parameter.method === 'OPTIONS') {
    return createJsonResponse({message: 'OK'}, corsHeaders);
  }

  try {
    const action = e.parameter.action;
    const method = e.parameter.method || 'GET';
    
    log_('info', 'API', `${method} ${action}`, e.parameter);

    // Route to appropriate handler
    switch (action) {
      // Task Management
      case 'getTasks':
        return createJsonResponse(apiGetTasks(e.parameter), corsHeaders);
      case 'getTask':
        return createJsonResponse(apiGetTask(e.parameter), corsHeaders);
      case 'createTask':
        return createJsonResponse(apiCreateTask(e.parameter), corsHeaders);
      case 'updateTask':
        return createJsonResponse(apiUpdateTask(e.parameter), corsHeaders);
      case 'deleteTask':
        return createJsonResponse(apiDeleteTask(e.parameter), corsHeaders);
      
      // Department Management
      case 'getDepartmentStats':
        return createJsonResponse(apiGetDepartmentStats(e.parameter), corsHeaders);
      case 'getDepartments':
        return createJsonResponse(apiGetDepartments(), corsHeaders);
      
      // Kanban Views
      case 'getKanbanData':
        return createJsonResponse(apiGetKanbanData(e.parameter), corsHeaders);
      
      // Leadership
      case 'verifyLeadership':
        return createJsonResponse(apiVerifyLeadership(e.parameter), corsHeaders);
      case 'getLeadershipTasks':
        return createJsonResponse(apiGetLeadershipTasks(e.parameter), corsHeaders);
      
      // CSV Import
      case 'importCsvData':
        return createJsonResponse(apiImportCsvData(e.parameter), corsHeaders);
      
      // Google Integration
      case 'createGoogleTask':
        return createJsonResponse(apiCreateGoogleTask(e.parameter), corsHeaders);
      case 'createCalendarEvent':
        return createJsonResponse(apiCreateCalendarEvent(e.parameter), corsHeaders);
      
      default:
        return createJsonResponse({
          success: false,
          error: 'Unknown action',
          availableActions: [
            'getTasks', 'getTask', 'createTask', 'updateTask', 'deleteTask',
            'getDepartmentStats', 'getDepartments', 'getKanbanData',
            'verifyLeadership', 'getLeadershipTasks', 'importCsvData',
            'createGoogleTask', 'createCalendarEvent'
          ]
        }, corsHeaders);
    }
  } catch (error) {
    log_('error', 'API', 'Request failed', {error: error.toString()});
    return createJsonResponse({
      success: false,
      error: error.toString()
    }, corsHeaders);
  }
}

function createJsonResponse(data, headers = {}) {
  const response = ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
  
  // Set headers
  Object.keys(headers).forEach(key => {
    response.setHeader(key, headers[key]);
  });
  
  return response;
}

// --- API ENDPOINT IMPLEMENTATIONS ---

function apiGetTasks(params) {
  try {
    const department = params.department;
    const status = params.status;
    const assignee = params.assignee;
    const limit = parseInt(params.limit) || 100;
    const offset = parseInt(params.offset) || 0;

    // Use existing getTasks function but filter results
    const allTasks = getTasks();
    let filteredTasks = allTasks;

    // Apply filters
    if (department && department !== 'all') {
      filteredTasks = filteredTasks.filter(task => 
        task.department && task.department.toLowerCase() === department.toLowerCase()
      );
    }

    if (status && status !== 'all') {
      filteredTasks = filteredTasks.filter(task => 
        task.status && task.status.toLowerCase() === status.toLowerCase()
      );
    }

    if (assignee && assignee !== 'all') {
      filteredTasks = filteredTasks.filter(task => 
        task.assignee && task.assignee.toLowerCase().includes(assignee.toLowerCase())
      );
    }

    // Apply pagination
    const total = filteredTasks.length;
    const paginatedTasks = filteredTasks.slice(offset, offset + limit);

    return {
      success: true,
      data: {
        tasks: paginatedTasks,
        pagination: {
          total: total,
          limit: limit,
          offset: offset,
          hasMore: offset + limit < total
        }
      }
    };
  } catch (error) {
    log_('error', 'apiGetTasks', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiGetTask(params) {
  try {
    const taskId = params.id;
    if (!taskId) {
      return { success: false, error: 'Task ID is required' };
    }

    const task = getTaskById(taskId);
    if (!task) {
      return { success: false, error: 'Task not found' };
    }

    return {
      success: true,
      data: task
    };
  } catch (error) {
    log_('error', 'apiGetTask', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiCreateTask(params) {
  try {
    // Extract task data from parameters
    const taskData = {
      actionItem: params.actionItem || '',
      department: params.department || '',
      assignee: params.assignee || '',
      priority: params.priority || 'Medium',
      status: params.status || 'Not Started',
      dueDate: params.dueDate || '',
      description: params.description || '',
      tags: params.tags || '',
      estimatedHours: params.estimatedHours || '',
      actualHours: params.actualHours || '',
      completionPercentage: params.completionPercentage || 0
    };

    // Validate required fields
    if (!taskData.actionItem) {
      return { success: false, error: 'Action item is required' };
    }
    if (!taskData.department) {
      return { success: false, error: 'Department is required' };
    }

    // Use existing createSimpleTask function
    const result = createSimpleTask(taskData);
    
    if (result && result.id) {
      return {
        success: true,
        data: {
          id: result.id,
          message: 'Task created successfully'
        }
      };
    } else {
      return { success: false, error: 'Failed to create task' };
    }
  } catch (error) {
    log_('error', 'apiCreateTask', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiUpdateTask(params) {
  try {
    const taskId = params.id;
    if (!taskId) {
      return { success: false, error: 'Task ID is required' };
    }

    // Prepare updates object
    const updates = {};
    const allowedFields = [
      'actionItem', 'department', 'assignee', 'priority', 'status',
      'dueDate', 'description', 'tags', 'estimatedHours', 'actualHours',
      'completionPercentage'
    ];

    allowedFields.forEach(field => {
      if (params[field] !== undefined) {
        updates[field] = params[field];
      }
    });

    if (Object.keys(updates).length === 0) {
      return { success: false, error: 'No valid fields to update' };
    }

    // Use existing updateTaskDetails function
    const result = updateTaskDetails(taskId, updates);
    
    return {
      success: true,
      data: {
        message: 'Task updated successfully',
        updatedFields: Object.keys(updates)
      }
    };
  } catch (error) {
    log_('error', 'apiUpdateTask', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiDeleteTask(params) {
  try {
    const taskId = params.id;
    if (!taskId) {
      return { success: false, error: 'Task ID is required' };
    }

    // Check if task exists
    const task = getTaskById(taskId);
    if (!task) {
      return { success: false, error: 'Task not found' };
    }

    // Mark task as deleted (soft delete)
    const result = updateTaskDetails(taskId, { status: 'Deleted' });
    
    return {
      success: true,
      data: {
        message: 'Task deleted successfully'
      }
    };
  } catch (error) {
    log_('error', 'apiDeleteTask', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiGetDepartmentStats(params) {
  try {
    const department = params.department;
    if (!department) {
      return { success: false, error: 'Department is required' };
    }

    const allTasks = getTasks();
    const departmentTasks = allTasks.filter(task => 
      task.department && task.department.toLowerCase() === department.toLowerCase()
    );

    // Calculate statistics
    const stats = {
      total: departmentTasks.length,
      byStatus: {},
      byPriority: {},
      byAssignee: {},
      completionRate: 0,
      averageHours: 0
    };

    let completedTasks = 0;
    let totalHours = 0;
    let hoursCount = 0;

    departmentTasks.forEach(task => {
      // Status breakdown
      const status = task.status || 'Unknown';
      stats.byStatus[status] = (stats.byStatus[status] || 0) + 1;

      // Priority breakdown
      const priority = task.priority || 'Unknown';
      stats.byPriority[priority] = (stats.byPriority[priority] || 0) + 1;

      // Assignee breakdown
      const assignee = task.assignee || 'Unassigned';
      stats.byAssignee[assignee] = (stats.byAssignee[assignee] || 0) + 1;

      // Completion tracking
      if (status.toLowerCase() === 'completed' || status.toLowerCase() === 'done') {
        completedTasks++;
      }

      // Hours tracking
      if (task.actualHours && !isNaN(parseFloat(task.actualHours))) {
        totalHours += parseFloat(task.actualHours);
        hoursCount++;
      }
    });

    stats.completionRate = departmentTasks.length > 0 ? 
      Math.round((completedTasks / departmentTasks.length) * 100) : 0;
    stats.averageHours = hoursCount > 0 ? 
      Math.round((totalHours / hoursCount) * 10) / 10 : 0;

    return {
      success: true,
      data: {
        department: department,
        stats: stats
      }
    };
  } catch (error) {
    log_('error', 'apiGetDepartmentStats', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiGetDepartments() {
  try {
    const departments = [
      { id: 'accounting', name: 'Accounting', icon: '💰', color: '#10b981' },
      { id: 'sales', name: 'Sales', icon: '📞', color: '#3b82f6' },
      { id: 'tech', name: 'Tech', icon: '💻', color: '#8b5cf6' },
      { id: 'marketing', name: 'Marketing', icon: '📈', color: '#f59e0b' },
      { id: 'hr', name: 'HR', icon: '👥', color: '#ef4444' },
      { id: 'customer-retention', name: 'Customer Retention', icon: '🎧', color: '#06b6d4' },
      { id: 'swag', name: 'Swag', icon: '🎁', color: '#84cc16' },
      { id: 'ideas', name: 'Ideas', icon: '💡', color: '#eab308' },
      { id: 'trade-shows', name: 'Trade Shows', icon: '🎪', color: '#f97316' },
      { id: 'purchasing', name: 'Purchasing', icon: '📦', color: '#6366f1' }
    ];

    return {
      success: true,
      data: departments
    };
  } catch (error) {
    log_('error', 'apiGetDepartments', error.toString());
    return { success: false, error: error.toString() };
  }
}

function apiGetKanbanData(params) {
  try {
    const department = params.department;
    const isLeadership = params.isLeadership === 'true';
    const userEmail = params.userEmail;

    let tasks;
    if (isLeadership && userEmail) {
      // Use existing leadership function
      tasks = getLeadershipKanbanData(userEmail);
    } else {
      // Get regular tasks
      tasks = getTasks();
      if (department && department !== 'all') {
        tasks = tasks.filter(task => 
          task.department && task.department.toLowerCase() === department.toLowerCase()
        );
      }
    }

    // Group tasks by status for kanban board
    const kanbanData = {
      'Not Started': [],
      'In Progress': [],
      'Completed': [],
      'On Hold': []
    };

    tasks.forEach(task => {
      const status = task.status || 'Not Started';
      if (kanbanData[status]) {
        kanbanData[status].push(task);
      } else {
        // Handle custom statuses
        if (!kanbanData['Other']) kanbanData['Other'] = [];
        kanbanData['Other'].push(task);
      }
    });

    return {
      success: true,
      data: {
        kanbanData: kanbanData,
        totalTasks: tasks.length
      }
    };
  } catch (error) {
    log_('error', 'apiGetKanbanData', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiVerifyLeadership(params) {
  try {
    const email = params.email;
    if (!email) {
      return { success: false, error: 'Email is required' };
    }

    // Use existing leadership verification
    const isLeadership = verifyLeadershipAccess(email);
    
    return {
      success: true,
      data: {
        isLeadership: isLeadership,
        email: email
      }
    };
  } catch (error) {
    log_('error', 'apiVerifyLeadership', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiGetLeadershipTasks(params) {
  try {
    const userEmail = params.userEmail;
    if (!userEmail) {
      return { success: false, error: 'User email is required' };
    }

    // Verify leadership access
    if (!verifyLeadershipAccess(userEmail)) {
      return { success: false, error: 'Unauthorized access' };
    }

    // Use existing leadership function
    const tasks = getLeadershipKanbanData(userEmail);
    
    return {
      success: true,
      data: {
        tasks: tasks,
        totalTasks: tasks.length
      }
    };
  } catch (error) {
    log_('error', 'apiGetLeadershipTasks', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiCreateGoogleTask(params) {
  try {
    const taskId = params.taskId;
    if (!taskId) {
      return { success: false, error: 'Task ID is required' };
    }

    const taskData = getTaskById(taskId);
    if (!taskData) {
      return { success: false, error: 'Task not found' };
    }

    // Use existing Google Task creation
    const result = createGoogleTask(taskData);
    
    return {
      success: result.success || false,
      data: {
        message: result.message || 'Google task creation attempted'
      }
    };
  } catch (error) {
    log_('error', 'apiCreateGoogleTask', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiCreateCalendarEvent(params) {
  try {
    const taskId = params.taskId;
    if (!taskId) {
      return { success: false, error: 'Task ID is required' };
    }

    const taskData = getTaskById(taskId);
    if (!taskData) {
      return { success: false, error: 'Task not found' };
    }

    // Use existing calendar event creation
    const result = createTaskCalendarEvent(taskData);
    
    return {
      success: result.success || false,
      data: {
        message: result.message || result.error || 'Calendar event creation attempted'
      }
    };
  } catch (error) {
    log_('error', 'apiCreateCalendarEvent', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

function apiImportCsvData(params) {
  try {
    const csvData = params.csvData;
    const department = params.department;
    
    if (!csvData || !department) {
      return { success: false, error: 'CSV data and department are required' };
    }

    // This would integrate with your existing CSV import logic
    // For now, return a placeholder response
    return {
      success: true,
      data: {
        message: 'CSV import functionality to be implemented',
        department: department,
        rowsProcessed: 0
      }
    };
  } catch (error) {
    log_('error', 'apiImportCsvData', error.toString(), params);
    return { success: false, error: error.toString() };
  }
}

// ===================================================================================
// |   EXISTING FUNCTIONS - COPY FROM YOUR ORIGINAL CODE.JS                        |
// |   All functions below should be copied from your current working code         |
// ===================================================================================

// --- HELPER FUNCTIONS (Keep as-is) ---
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

function getLinkSigningSecret() {
  const props = PropertiesService.getScriptProperties();
  let secret = props.getProperty('LINK_SIGNING_SECRET');
  if (!secret) {
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
    const shouldPersist = level === 'error' || level === 'warn' || getEnv_() === 'dev';
    if (shouldPersist) {
      const sheet = ensureLogsSheet_();
      sheet.appendRow([ts, level, context, taskId || '', message || '', meta ? JSON.stringify(meta) : '', requestId]);
    }
  } catch (e) {
    Logger.log('log_ failed: ' + e);
  }
}

// ===================================================================================
// |   IMPORTANT: COPY ALL YOUR EXISTING FUNCTIONS FROM Code.js HERE               |
// |   Including: getTasks(), createSimpleTask(), getTaskById(), updateTaskDetails() |
// |   verifyLeadershipAccess(), getLeadershipKanbanData(), createGoogleTask(), etc. |
// ===================================================================================

// Placeholder functions - REPLACE WITH YOUR ACTUAL FUNCTIONS
function getTasks() {
  // TODO: Copy your actual getTasks() function here
  try {
    const ss = SpreadsheetApp.openById(MASTER_SHEET_ID);
    const sheet = ss.getActiveSheet();
    const data = sheet.getDataRange().getValues();
    
    // Basic implementation - replace with your actual logic
    const tasks = [];
    for (let i = 1; i < data.length; i++) {
      tasks.push({
        id: data[i][0] || i.toString(),
        actionItem: data[i][1] || '',
        department: data[i][2] || '',
        assignee: data[i][3] || '',
        status: data[i][4] || 'Not Started',
        priority: data[i][5] || 'Medium',
        dueDate: data[i][6] || '',
        description: data[i][7] || ''
      });
    }
    return tasks;
  } catch (error) {
    log_('error', 'getTasks', error.toString());
    return [];
  }
}

function getTaskById(id) {
  // TODO: Copy your actual getTaskById() function here
  const tasks = getTasks();
  return tasks.find(task => task.id === id);
}

function createSimpleTask(taskData) {
  // TODO: Copy your actual createSimpleTask() function here
  try {
    const ss = SpreadsheetApp.openById(MASTER_SHEET_ID);
    const sheet = ss.getActiveSheet();
    const id = Utilities.getUuid().slice(0, 8);
    
    sheet.appendRow([
      id,
      taskData.actionItem,
      taskData.department,
      taskData.assignee,
      taskData.status,
      taskData.priority,
      taskData.dueDate,
      taskData.description
    ]);
    
    return { id: id };
  } catch (error) {
    log_('error', 'createSimpleTask', error.toString(), taskData);
    return null;
  }
}

function updateTaskDetails(taskId, updates) {
  // TODO: Copy your actual updateTaskDetails() function here
  log_('info', 'updateTaskDetails', `Updating task ${taskId}`, updates);
  return true;
}

function verifyLeadershipAccess(email) {
  // TODO: Copy your actual verifyLeadershipAccess() function here
  const leadershipEmails = [
    'founder@luvbuds.com',
    // Add your actual leadership emails here
  ];
  return leadershipEmails.includes(email.toLowerCase());
}

function getLeadershipKanbanData(userEmail) {
  // TODO: Copy your actual getLeadershipKanbanData() function here
  if (!verifyLeadershipAccess(userEmail)) {
    return [];
  }
  return getTasks(); // Return all tasks for leadership
}

function createGoogleTask(taskData) {
  // TODO: Copy your actual createGoogleTask() function here
  return { success: true, message: 'Google task creation not implemented yet' };
}

function createTaskCalendarEvent(taskData) {
  // TODO: Copy your actual createTaskCalendarEvent() function here
  return { success: true, message: 'Calendar event creation not implemented yet' };
}