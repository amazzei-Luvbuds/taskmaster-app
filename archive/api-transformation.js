// ===================================================================================
// |   TASKMASTER REST API TRANSFORMATION - ADDITION TO EXISTING CODE.JS           |
// |   - Adds REST endpoints while maintaining HTML serving for gradual migration   |
// |   - Provides JSON responses with proper CORS headers                           |
// |   - Maintains backward compatibility during transition                         |
// ===================================================================================

// --- API ROUTING ENHANCEMENT ---
/**
 * Enhanced doGet function - adds REST API support while maintaining HTML serving
 * This is the complete replacement for the existing doGet function
 */
function doGet(e) {
  // Check if this is an API request
  if (e.parameter.api === 'v1') {
    return handleApiRequest(e);
  }

  // EXISTING HTML ROUTING LOGIC CONTINUES HERE
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

  // Continue with existing HTML routing...
  if (page === 'task' && e.parameter.id) {
    const taskId = e.parameter.id;
    Logger.log(`Task detail request for ID: "${taskId}"`);

    const taskData = getTaskById(taskId);
    if (!taskData) {
      Logger.log(`Task not found for ID: "${taskId}"`);
      const debugResult = debugSpecificTaskId(taskId);
      Logger.log(`Debug result for "${taskId}": ${JSON.stringify(debugResult)}`);
      return HtmlService.createHtmlOutput('<h1>Task not found</h1><p>Task ID: ' + taskId + ' could not be found.</p><p>Debug info: ' + JSON.stringify(debugResult) + '</p>');
    }

    Logger.log(`Task found: "${taskData.actionItem}"`);

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

    let template = HtmlService.createTemplateFromFile('task_detail');
    template.task = taskData;
    template.plan = projectPlan || { projectGoal: '', expectedBenefits: [], milestonePlan: { title: 'Key Milestones', description: '' }, dataIntegrityPlan: [], implementationPlan: [] };
    template.teamDirectory = getTeamData();

    return template.evaluate().setTitle(taskData.actionItem || 'Task Details');
  }

  // ALL OTHER EXISTING ROUTES CONTINUE AS BEFORE...
  if (page === 'kanban') {
    return HtmlService.createTemplateFromFile('kanban').evaluate().setTitle('Kanban Board');
  }

  if (page === 'uploader') {
    return HtmlService.createTemplateFromFile('uploader').evaluate().setTitle('Upload Transcript');
  }

  if (page === 'leadership') {
    return HtmlService.createTemplateFromFile('leadership').evaluate().setTitle('Leadership Portal');
  }

  if (page === 'leadership_kanban') {
    return HtmlService.createTemplateFromFile('leadership_kanban').evaluate().setTitle('Leadership Kanban');
  }

  if (page === 'leadership_dashboard') {
    return HtmlService.createTemplateFromFile('leadership_dashboard').evaluate().setTitle('Leadership Dashboard');
  }

  if (page === 'leadership_reports') {
    return HtmlService.createTemplateFromFile('leadership_reports').evaluate().setTitle('Leadership Reports');
  }

  if (page === 'leadership_admin') {
    return HtmlService.createTemplateFromFile('leadership_admin').evaluate().setTitle('Leadership Admin');
  }

  if (page === 'swag_supply') {
    return HtmlService.createTemplateFromFile('swag_supply').evaluate().setTitle('Swag Supply');
  }

  if (page === 'diagnostics') {
    const key = e.parameter.key || '';
    if (key !== getAdminKey_()) {
      return HtmlService.createHtmlOutput('<h1>Forbidden</h1><p>Invalid or missing admin key.</p>');
    }
    // Handle diagnostics...
  }

  if (page === 'admin') {
    const key = e.parameter.key || '';
    if (!assertAdminKey_(key)) {
      return HtmlService.createHtmlOutput('<h1>Forbidden</h1><p>Invalid or missing admin key.</p>');
    }
    const t = HtmlService.createTemplateFromFile('admin');
    t.adminKey = key;
    return t.evaluate().setTitle('Admin Dashboard');
  }

  // Default route → render main departments page
  try {
    let template = HtmlService.createTemplateFromFile('tasks_departments');
    return template.evaluate().setTitle('Department Tasks');
  } catch (err) {
    Logger.log(`Default route failed: ${err.stack}`);
    return HtmlService.createHtmlOutput('<h1>Error</h1><p>Unable to load the application. Please try again later.</p>');
  }
}

/**
 * NEW: REST API request handler
 */
function handleApiRequest(e) {
  try {
    // Set CORS headers for all API responses
    const response = createApiResponse();

    const endpoint = e.parameter.endpoint || '';
    const method = e.parameter.method || 'GET';
    const department = e.parameter.department || '';

    Logger.log(`API Request: ${method} /${endpoint} ${department ? `(dept: ${department})` : ''}`);

    // Route API requests
    switch (endpoint) {
      case 'tasks':
        return handleTasksEndpoint(e, response);
      case 'task':
        return handleTaskEndpoint(e, response);
      case 'departments':
        return handleDepartmentsEndpoint(e, response);
      case 'team':
        return handleTeamEndpoint(e, response);
      case 'avatars':
        return handleAvatarsEndpoint(e, response);
      case 'kanban':
        return handleKanbanEndpoint(e, response);
      case 'health':
        return handleHealthEndpoint(e, response);
      default:
        return createErrorResponse('Endpoint not found', 404);
    }
  } catch (error) {
    Logger.log(`API Error: ${error.stack}`);
    return createErrorResponse('Internal server error', 500);
  }
}

/**
 * NEW: doPost function for handling POST/PUT/DELETE operations
 */
function doPost(e) {
  try {
    // Check if this is an API request
    if (e.parameter.api !== 'v1') {
      return createErrorResponse('API version required', 400);
    }

    const response = createApiResponse();
    const endpoint = e.parameter.endpoint || '';
    const method = e.parameter.method || 'POST';

    // Parse JSON body
    let requestBody = {};
    try {
      if (e.postData && e.postData.contents) {
        requestBody = JSON.parse(e.postData.contents);
      }
    } catch (parseError) {
      return createErrorResponse('Invalid JSON body', 400);
    }

    Logger.log(`API POST Request: ${method} /${endpoint}`);
    Logger.log(`Request body: ${JSON.stringify(requestBody)}`);

    // Route POST requests
    switch (endpoint) {
      case 'tasks':
        if (method === 'POST') {
          return handleCreateTask(requestBody, response);
        } else if (method === 'PUT') {
          return handleUpdateTask(requestBody, response);
        } else if (method === 'DELETE') {
          return handleDeleteTask(requestBody, response);
        }
        break;
      case 'task':
        if (method === 'PUT') {
          return handleUpdateTaskStatus(requestBody, response);
        }
        break;
      default:
        return createErrorResponse('Endpoint not found', 404);
    }

    return createErrorResponse('Method not allowed', 405);
  } catch (error) {
    Logger.log(`API POST Error: ${error.stack}`);
    return createErrorResponse('Internal server error', 500);
  }
}

// --- API ENDPOINT HANDLERS ---

/**
 * Handle /api/v1/tasks endpoint
 */
function handleTasksEndpoint(e, response) {
  const department = e.parameter.department;
  const status = e.parameter.status;
  const assignee = e.parameter.assignee;
  const limit = parseInt(e.parameter.limit) || 0;

  let tasks = getTasks();
  if (tasks.error) {
    return createErrorResponse('Failed to fetch tasks', 500);
  }

  // Apply filters
  if (department) {
    tasks = tasks.filter(task =>
      task.department && task.department.toLowerCase() === department.toLowerCase()
    );
  }

  if (status) {
    tasks = tasks.filter(task =>
      task.status && task.status.toLowerCase() === status.toLowerCase()
    );
  }

  if (assignee) {
    tasks = tasks.filter(task =>
      task.owners && task.owners.toLowerCase().includes(assignee.toLowerCase())
    );
  }

  if (limit > 0) {
    tasks = tasks.slice(0, limit);
  }

  return createSuccessResponse({
    tasks: tasks,
    total: tasks.length,
    filters: { department, status, assignee, limit }
  });
}

/**
 * Handle /api/v1/task endpoint (single task)
 */
function handleTaskEndpoint(e, response) {
  const taskId = e.parameter.id;
  if (!taskId) {
    return createErrorResponse('Task ID required', 400);
  }

  const task = getTaskById(taskId);
  if (!task) {
    return createErrorResponse('Task not found', 404);
  }

  return createSuccessResponse({ task });
}

/**
 * Handle /api/v1/departments endpoint
 */
function handleDepartmentsEndpoint(e, response) {
  const departments = getDepartments();
  return createSuccessResponse({ departments });
}

/**
 * Handle /api/v1/team endpoint
 */
function handleTeamEndpoint(e, response) {
  const team = getTeamData();
  return createSuccessResponse({ team });
}

/**
 * Handle /api/v1/avatars endpoint
 */
function handleAvatarsEndpoint(e, response) {
  const avatars = getAvatarMap();
  return createSuccessResponse({ avatars });
}

/**
 * Handle /api/v1/kanban endpoint
 */
function handleKanbanEndpoint(e, response) {
  const department = e.parameter.department;
  const kanbanData = getKanbanData(department);
  return createSuccessResponse(kanbanData);
}

/**
 * Handle /api/v1/health endpoint
 */
function handleHealthEndpoint(e, response) {
  const health = {
    status: 'healthy',
    timestamp: new Date().toISOString(),
    version: '1.0.0',
    endpoints: [
      'GET /api/v1/tasks',
      'GET /api/v1/task?id=<taskId>',
      'GET /api/v1/departments',
      'GET /api/v1/team',
      'GET /api/v1/avatars',
      'GET /api/v1/kanban',
      'POST /api/v1/tasks',
      'PUT /api/v1/tasks',
      'DELETE /api/v1/tasks'
    ]
  };
  return createSuccessResponse(health);
}

// --- POST ENDPOINT HANDLERS ---

/**
 * Handle POST /api/v1/tasks (create task)
 */
function handleCreateTask(requestBody, response) {
  try {
    const requiredFields = ['actionItem', 'department'];
    for (const field of requiredFields) {
      if (!requestBody[field]) {
        return createErrorResponse(`Missing required field: ${field}`, 400);
      }
    }

    // Create task object with defaults
    const task = {
      actionItem: requestBody.actionItem,
      department: requestBody.department,
      owners: requestBody.owners || 'Unassigned',
      status: requestBody.status || 'Not Started',
      priorityScore: requestBody.priorityScore || 5,
      progressPercentage: requestBody.progressPercentage || 0,
      problemDescription: requestBody.problemDescription || '',
      proposedSolution: requestBody.proposedSolution || '',
      dueDate: requestBody.dueDate || '',
      notes: requestBody.notes || ''
    };

    const result = createSimpleTask(task);
    if (result.error) {
      return createErrorResponse(result.error, 500);
    }

    const newTask = getTaskById(result.taskID);
    return createSuccessResponse({
      task: newTask,
      message: 'Task created successfully'
    }, 201);
  } catch (error) {
    return createErrorResponse(`Failed to create task: ${error.message}`, 500);
  }
}

/**
 * Handle PUT /api/v1/tasks (update task)
 */
function handleUpdateTask(requestBody, response) {
  try {
    const taskId = requestBody.taskID || requestBody.id;
    if (!taskId) {
      return createErrorResponse('Task ID required', 400);
    }

    const existingTask = getTaskById(taskId);
    if (!existingTask) {
      return createErrorResponse('Task not found', 404);
    }

    const result = updateTaskDetails(taskId, requestBody);
    if (result.error) {
      return createErrorResponse(result.error, 500);
    }

    const updatedTask = getTaskById(taskId);
    return createSuccessResponse({
      task: updatedTask,
      message: 'Task updated successfully'
    });
  } catch (error) {
    return createErrorResponse(`Failed to update task: ${error.message}`, 500);
  }
}

/**
 * Handle DELETE /api/v1/tasks (delete task)
 */
function handleDeleteTask(requestBody, response) {
  try {
    const taskId = requestBody.taskID || requestBody.id;
    if (!taskId) {
      return createErrorResponse('Task ID required', 400);
    }

    const existingTask = getTaskById(taskId);
    if (!existingTask) {
      return createErrorResponse('Task not found', 404);
    }

    // Update status to 'Deleted' instead of physical deletion
    const result = updateTaskStatus(taskId, 'Deleted');
    if (result.error) {
      return createErrorResponse(result.error, 500);
    }

    return createSuccessResponse({
      message: 'Task deleted successfully',
      taskId: taskId
    });
  } catch (error) {
    return createErrorResponse(`Failed to delete task: ${error.message}`, 500);
  }
}

/**
 * Handle PUT /api/v1/task (update task status)
 */
function handleUpdateTaskStatus(requestBody, response) {
  try {
    const taskId = requestBody.taskID || requestBody.id;
    const newStatus = requestBody.status;

    if (!taskId || !newStatus) {
      return createErrorResponse('Task ID and status required', 400);
    }

    const result = updateTaskStatus(taskId, newStatus);
    if (result.error) {
      return createErrorResponse(result.error, 500);
    }

    const updatedTask = getTaskById(taskId);
    return createSuccessResponse({
      task: updatedTask,
      message: 'Task status updated successfully'
    });
  } catch (error) {
    return createErrorResponse(`Failed to update task status: ${error.message}`, 500);
  }
}

// --- API RESPONSE HELPERS ---

/**
 * Create API response with CORS headers
 */
function createApiResponse() {
  return {
    headers: {
      'Content-Type': 'application/json',
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Requested-With',
      'Access-Control-Max-Age': '86400'
    }
  };
}

/**
 * Create success response
 */
function createSuccessResponse(data, statusCode = 200) {
  const response = {
    success: true,
    status: statusCode,
    data: data,
    timestamp: new Date().toISOString()
  };

  return ContentService
    .createTextOutput(JSON.stringify(response))
    .setMimeType(ContentService.MimeType.JSON)
    .setHeaders(createApiResponse().headers);
}

/**
 * Create error response
 */
function createErrorResponse(message, statusCode = 400) {
  const response = {
    success: false,
    status: statusCode,
    error: {
      message: message,
      code: statusCode
    },
    timestamp: new Date().toISOString()
  };

  return ContentService
    .createTextOutput(JSON.stringify(response))
    .setMimeType(ContentService.MimeType.JSON)
    .setHeaders(createApiResponse().headers);
}

// --- UTILITY FUNCTIONS ---

/**
 * Handle OPTIONS requests for CORS preflight
 */
function doOptions() {
  return ContentService
    .createTextOutput('')
    .setHeaders(createApiResponse().headers);
}

/**
 * Get all departments from tasks
 */
function getDepartments() {
  try {
    const tasks = getTasks();
    if (tasks.error) return [];

    const departmentSet = new Set(
      tasks.map(task => task.department)
        .filter(dept => dept && dept.trim() !== '')
    );

    return Array.from(departmentSet).sort();
  } catch (error) {
    Logger.log(`Error getting departments: ${error.message}`);
    return [];
  }
}

/**
 * Enhanced error logging for API calls
 */
function logApiCall(endpoint, method, params, result, error = null) {
  const logData = {
    endpoint,
    method,
    params,
    success: !error,
    error: error ? error.message : null,
    timestamp: new Date().toISOString()
  };

  if (error) {
    log_('error', 'API', `${method} /${endpoint} failed`, logData);
  } else {
    log_('info', 'API', `${method} /${endpoint} success`, logData);
  }
}

/**
 * Validate API request parameters
 */
function validateApiRequest(requiredParams, providedParams) {
  const missing = requiredParams.filter(param => !providedParams[param]);
  if (missing.length > 0) {
    throw new Error(`Missing required parameters: ${missing.join(', ')}`);
  }
  return true;
}

// --- API DOCUMENTATION ---
/**
 * API Endpoints Documentation:
 *
 * GET  /api/v1/health              - Health check endpoint
 * GET  /api/v1/tasks               - Get all tasks (with optional filters)
 *      ?department=<dept>          - Filter by department
 *      ?status=<status>            - Filter by status
 *      ?assignee=<name>            - Filter by assignee
 *      ?limit=<number>             - Limit results
 * GET  /api/v1/task?id=<taskId>    - Get specific task
 * GET  /api/v1/departments         - Get all departments
 * GET  /api/v1/team                - Get team members
 * GET  /api/v1/avatars             - Get avatar mappings
 * GET  /api/v1/kanban              - Get kanban data
 * POST /api/v1/tasks               - Create new task
 * PUT  /api/v1/tasks               - Update existing task
 * PUT  /api/v1/task                - Update task status
 * DELETE /api/v1/tasks             - Delete task (set status to 'Deleted')
 *
 * All API calls require the parameter: api=v1
 * All responses include CORS headers for frontend access
 * Error responses follow standard HTTP status codes
 */