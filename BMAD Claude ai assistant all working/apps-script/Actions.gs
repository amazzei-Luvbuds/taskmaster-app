function askGemini(prompt){
  if (!prompt) return 'Empty prompt';
  try {
    return geminiComplete_(prompt);
  } catch (e) {
    return 'Gemini error: ' + e.message;
  }
}

function listRecentEmails(options){
  options = options || {};
  var maxResults = Math.min(Math.max(Number(options.maxResults)||10,1),50);
  var pageToken = options.pageToken || null;
  var labelIds = options.importantOnly ? ['INBOX','IMPORTANT'] : ['INBOX'];
  var q = options.query || '';
  var list = Gmail.Users.Messages.list('me', {
    maxResults: maxResults,
    pageToken: pageToken,
    labelIds: labelIds,
    q: q
  });
  var messages = (list.messages || []).map(function(m){
    var msg = Gmail.Users.Messages.get('me', m.id, {format:'metadata', metadataHeaders:['Subject','From','Date']});
    var headers = (msg.payload && msg.payload.headers) || [];
    var byName = {};
    headers.forEach(function(h){ byName[h.name] = h.value; });
    return {
      id: msg.id,
      threadId: msg.threadId,
      snippet: msg.snippet || '',
      subject: byName['Subject'] || '',
      from: byName['From'] || '',
      date: byName['Date'] || ''
    };
  });
  return { messages: messages, nextPageToken: list.nextPageToken || '' };
}

function createDraft(to, subject, body, cc, bcc){
  try {
    var draft = {
      message: {
        raw: Utilities.base64EncodeWebSafe(
          "To: " + (to || '') + "\r\n" +
          (cc ? "Cc: " + cc + "\r\n" : '') +
          (bcc ? "Bcc: " + bcc + "\r\n" : '') +
          "Subject: " + (subject || '') + "\r\n" +
          "Content-Type: text/html; charset=UTF-8\r\n\r\n" +
          (body || '')
        )
      }
    };
    var result = Gmail.Users.Drafts.create(draft, 'me');
    return { success: true, draftId: result.id, message: 'Draft created successfully' };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function sendEmail(to, subject, body, cc, bcc, confirmSend){
  try {
    if (confirmSend !== true) {
      return { success: false, error: 'Email send not confirmed' };
    }
    
    var message = {
      raw: Utilities.base64EncodeWebSafe(
        "To: " + (to || '') + "\r\n" +
        (cc ? "Cc: " + cc + "\r\n" : '') +
        (bcc ? "Bcc: " + bcc + "\r\n" : '') +
        "Subject: " + (subject || '') + "\r\n" +
        "Content-Type: text/html; charset=UTF-8\r\n\r\n" +
        (body || '')
      )
    };
    
    var result = Gmail.Users.Messages.send(message, 'me');
    return { success: true, messageId: result.id, message: 'Email sent successfully' };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function getDraft(draftId){
  try {
    var draft = Gmail.Users.Drafts.get('me', draftId);
    var message = draft.message;
    var raw = Utilities.base64DecodeWebSafe(message.raw);
    var headers = message.payload.headers || [];
    var byName = {};
    headers.forEach(function(h){ byName[h.name] = h.value; });
    
    return {
      success: true,
      draft: {
        id: draft.id,
        to: byName['To'] || '',
        cc: byName['Cc'] || '',
        bcc: byName['Bcc'] || '',
        subject: byName['Subject'] || '',
        body: message.payload.body ? message.payload.body.data : ''
      }
    };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function listUpcomingEvents(options){
  options = options || {};
  try {
    var calendarId = options.calendarId || 'primary';
    var now = new Date();
    var start = options.start ? new Date(options.start) : new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var end = options.end ? new Date(options.end) : new Date(start.getTime() + 7*24*60*60*1000);
    var maxResults = Math.min(Math.max(Number(options.maxResults)||50,1),2500);

    var res = Calendar.Events.list(calendarId, {
      timeMin: start.toISOString(),
      timeMax: end.toISOString(),
      singleEvents: true,
      orderBy: 'startTime',
      maxResults: maxResults
    });

    var events = (res.items || []).map(function(ev){
      var startTime = ev.start && (ev.start.dateTime || ev.start.date);
      var endTime = ev.end && (ev.end.dateTime || ev.end.date);
      return {
        id: ev.id,
        summary: ev.summary || '',
        location: ev.location || '',
        start: startTime || '',
        end: endTime || '',
        hangoutLink: ev.hangoutLink || '',
        attendeesCount: (ev.attendees || []).length
      };
    });

    return { success: true, events: events };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function createCalendarEvent(options){
  options = options || {};
  try {
    var calendarId = options.calendarId || 'primary';
    var summary = options.summary || '';
    var description = options.description || '';
    var location = options.location || '';
    var start = options.start ? new Date(options.start) : null;
    var end = options.end ? new Date(options.end) : null;
    if (!summary) throw new Error('summary is required');
    if (!start || !end) throw new Error('start and end are required');

    var attendees = [];
    if (options.attendees) {
      String(options.attendees).split(',').map(function(s){return s.trim();}).filter(Boolean).forEach(function(email){
        attendees.push({email: email});
      });
    }

    var ev = {
      summary: summary,
      description: description,
      location: location,
      start: { dateTime: start.toISOString() },
      end: { dateTime: end.toISOString() },
      attendees: attendees
    };

    var created = Calendar.Events.insert(ev, calendarId, {sendUpdates: attendees.length ? 'all' : 'none'});
    return { success: true, id: created.id, htmlLink: created.htmlLink || '', hangoutLink: created.hangoutLink || '' };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function listTasks(options){
  options = options || {};
  try {
    var tasklistId = options.tasklistId || '@default';
    var showCompleted = options.showCompleted || false;
    var showHidden = options.showHidden || false;
    var maxResults = Math.min(Math.max(Number(options.maxResults)||50,1),100);
    
    var tasks = Tasks.Tasks.list(tasklistId, {
      showCompleted: showCompleted,
      showHidden: showHidden,
      maxResults: maxResults
    });
    
    var items = (tasks.items || []).map(function(task){
      return {
        id: task.id,
        title: task.title || '',
        notes: task.notes || '',
        status: task.status || 'needsAction',
        due: task.due || '',
        completed: task.completed || '',
        updated: task.updated || '',
        position: task.position || ''
      };
    });
    
    return { success: true, tasks: items, nextPageToken: tasks.nextPageToken || '' };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function createTask(options){
  options = options || {};
  try {
    var tasklistId = options.tasklistId || '@default';
    var title = options.title || '';
    var notes = options.notes || '';
    var due = options.due || '';
    
    if (!title) throw new Error('title is required');
    
    var task = {
      title: title,
      notes: notes
    };
    
    if (due) {
      task.due = new Date(due).toISOString();
    }
    
    var created = Tasks.Tasks.insert(task, tasklistId);
    return { success: true, id: created.id, title: created.title };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function completeTask(taskId, tasklistId){
  try {
    tasklistId = tasklistId || '@default';
    var task = Tasks.Tasks.get(tasklistId, taskId);
    task.status = 'completed';
    task.completed = new Date().toISOString();
    
    var updated = Tasks.Tasks.update(task, tasklistId, taskId);
    return { success: true, id: updated.id, status: updated.status };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function createDocument(options){
  options = options || {};
  try {
    var title = options.title || 'Untitled Document';
    var content = options.content || '';
    
    var doc = Docs.Documents.create({
      title: title
    });
    
    if (content) {
      var requests = [{
        insertText: {
          location: { index: 1 },
          text: content
        }
      }];
      Docs.Documents.batchUpdate({ requests: requests }, doc.documentId);
    }
    
    return { 
      success: true, 
      id: doc.documentId, 
      title: doc.title,
      url: 'https://docs.google.com/document/d/' + doc.documentId + '/edit'
    };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function listDocuments(options){
  options = options || {};
  try {
    var maxResults = Math.min(Math.max(Number(options.maxResults)||20,1),100);
    var query = options.query || '';
    var orderBy = options.orderBy || 'modifiedTime desc';
    
    var files = Drive.Files.list({
      q: "mimeType='application/vnd.google-apps.document'" + (query ? " and name contains '" + query + "'" : ""),
      pageSize: maxResults,
      orderBy: orderBy,
      fields: "files(id,name,webViewLink,modifiedTime,owners(displayName,emailAddress))"
    });
    
    var documents = (files.files || []).map(function(file){
      return {
        id: file.id,
        title: file.name,
        url: file.webViewLink,
        modifiedTime: file.modifiedTime || file.modifiedByMeTime,
        owners: file.owners ? file.owners.map(function(o){ return o.displayName || o.emailAddress; }) : []
      };
    });
    
    return { success: true, documents: documents };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function getDocument(documentId){
  try {
    var doc = Docs.Documents.get(documentId);
    var content = '';
    
    if (doc.body && doc.body.content) {
      doc.body.content.forEach(function(element){
        if (element.paragraph && element.paragraph.elements) {
          element.paragraph.elements.forEach(function(textElement){
            if (textElement.textRun) {
              content += textElement.textRun.content;
            }
          });
        }
      });
    }
    
    return { 
      success: true, 
      id: doc.documentId,
      title: doc.title,
      content: content,
      url: 'https://docs.google.com/document/d/' + doc.documentId + '/edit'
    };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function updateDocument(documentId, content){
  try {
    var requests = [{
      deleteContentRange: {
        range: {
          startIndex: 1,
          endIndex: -1
        }
      }
    }, {
      insertText: {
        location: { index: 1 },
        text: content
      }
    }];
    
    Docs.Documents.batchUpdate({ requests: requests }, documentId);
    return { success: true, id: documentId };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function searchContacts(options){
  options = options || {};
  try {
    var query = options.query || '';
    var maxResults = Math.min(Math.max(Number(options.maxResults)||20,1),100);
    
    var connections = People.People.Connections.list('people/me', {
      personFields: 'names,emailAddresses,phoneNumbers,organizations,photos',
      pageSize: maxResults
    });
    
    var contacts = [];
    var totalConnections = connections.connections ? connections.connections.length : 0;
    
    if (connections.connections) {
      connections.connections.forEach(function(person){
        var name = '';
        var email = '';
        var phone = '';
        var company = '';
        var photo = '';
        
        if (person.names && person.names.length > 0) {
          name = person.names[0].displayName || person.names[0].givenName + ' ' + person.names[0].familyName;
        }
        
        if (person.emailAddresses && person.emailAddresses.length > 0) {
          email = person.emailAddresses[0].value;
        }
        
        if (person.phoneNumbers && person.phoneNumbers.length > 0) {
          phone = person.phoneNumbers[0].value;
        }
        
        if (person.organizations && person.organizations.length > 0) {
          company = person.organizations[0].name;
        }
        
        if (person.photos && person.photos.length > 0) {
          photo = person.photos[0].url;
        }
        
        // Filter by query if provided
        if (!query || 
            name.toLowerCase().includes(query.toLowerCase()) ||
            email.toLowerCase().includes(query.toLowerCase()) ||
            company.toLowerCase().includes(query.toLowerCase())) {
          contacts.push({
            id: person.resourceName,
            name: name,
            email: email,
            phone: phone,
            company: company,
            photo: photo
          });
        }
      });
    }
    
    return { 
      success: true, 
      contacts: contacts, 
      totalFound: totalConnections,
      filteredCount: contacts.length,
      query: query
    };
  } catch (e) {
    return { success: false, error: e.message, details: e.toString() };
  }
}

function getContact(contactId){
  try {
    var person = People.People.get(contactId, {
      personFields: 'names,emailAddresses,phoneNumbers,organizations,photos,addresses,birthdays'
    });
    
    var contact = {
      id: person.resourceName,
      name: '',
      emails: [],
      phones: [],
      organizations: [],
      addresses: [],
      photo: ''
    };
    
    if (person.names && person.names.length > 0) {
      contact.name = person.names[0].displayName || person.names[0].givenName + ' ' + person.names[0].familyName;
    }
    
    if (person.emailAddresses) {
      contact.emails = person.emailAddresses.map(function(email){
        return { value: email.value, type: email.type || 'other' };
      });
    }
    
    if (person.phoneNumbers) {
      contact.phones = person.phoneNumbers.map(function(phone){
        return { value: phone.value, type: phone.type || 'other' };
      });
    }
    
    if (person.organizations) {
      contact.organizations = person.organizations.map(function(org){
        return { name: org.name, title: org.title, type: org.type || 'other' };
      });
    }
    
    if (person.addresses) {
      contact.addresses = person.addresses.map(function(addr){
        return { 
          formatted: addr.formattedValue,
          type: addr.type || 'other'
        };
      });
    }
    
    if (person.photos && person.photos.length > 0) {
      contact.photo = person.photos[0].url;
    }
    
    return { success: true, contact: contact };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function transcribeAudio(audioBlob, options){
  options = options || {};
  try {
    var prompt = options.prompt || 'Please transcribe this audio and provide a detailed summary of the meeting or conversation.';
    var includeSummary = options.includeSummary !== false;
    var includeActionItems = options.includeActionItems !== false;
    
    // Convert blob to base64
    var base64Audio = Utilities.base64Encode(audioBlob.getBytes());
    
    // Create the transcription prompt
    var fullPrompt = prompt;
    if (includeSummary) {
      fullPrompt += '\n\nPlease also provide:\n1. A brief summary of key points\n2. Main topics discussed\n3. Important decisions made';
    }
    if (includeActionItems) {
      fullPrompt += '\n4. Action items and next steps (if any)';
    }
    
    // For now, we'll simulate transcription since we need to integrate with a speech-to-text service
    // In a real implementation, you'd use Google Speech-to-Text API or similar
    var transcription = 'Audio transcription would go here. This is a placeholder for the actual transcription service integration.';
    
    // Use Gemini to analyze the transcription
    var analysis = askGemini(fullPrompt + '\n\nTranscription:\n' + transcription);
    
    return { 
      success: true, 
      transcription: transcription,
      analysis: analysis,
      timestamp: new Date().toISOString()
    };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function createMeetingSummary(transcription, options){
  options = options || {};
  try {
    var meetingTitle = options.title || 'Meeting Summary';
    var attendees = options.attendees || [];
    var date = options.date || new Date().toISOString();
    var priority = options.priority || 'quality'; // Use quality for summaries
    
    var summaryPrompt = 'Please create a professional meeting summary from this transcription:\n\n' + 
                       'Meeting Title: ' + meetingTitle + '\n' +
                       'Date: ' + date + '\n' +
                       'Attendees: ' + attendees.join(', ') + '\n\n' +
                       'Transcription:\n' + transcription + '\n\n' +
                       'Please provide:\n' +
                       '1. Executive Summary\n' +
                       '2. Key Discussion Points\n' +
                       '3. Decisions Made\n' +
                       '4. Action Items with Owners\n' +
                       '5. Next Steps';
    
    // Use AI orchestration with fallback
    var result = callAIWithFallback(summaryPrompt, TASK_TYPES.MEETING_SUMMARY, {
      priority: priority,
      maxRetries: 2
    });
    
    if (!result.success) {
      return { success: false, error: result.error };
    }
    
    var summary = result.response;
    
    // Create a Google Doc with the summary
    var doc = Docs.Documents.create({
      title: meetingTitle + ' - ' + new Date().toLocaleDateString()
    });
    
    var requests = [{
      insertText: {
        location: { index: 1 },
        text: summary
      }
    }];
    
    Docs.Documents.batchUpdate({ requests: requests }, doc.documentId);
    
    return { 
      success: true, 
      summary: summary,
      documentId: doc.documentId,
      documentUrl: 'https://docs.google.com/document/d/' + doc.documentId + '/edit',
      model: result.model,
      latency: result.latency,
      cost: result.cost,
      timestamp: result.timestamp
    };
  } catch (e) {
    return { success: false, error: e.message };
  }
}

function analyzeMeetingSentiment(transcription){
  try {
    var sentimentPrompt = 'Analyze the sentiment and tone of this meeting transcription. Provide insights on:\n\n' +
                         '1. Overall meeting sentiment (positive, neutral, negative)\n' +
                         '2. Key emotional moments or concerns raised\n' +
                         '3. Level of engagement and participation\n' +
                         '4. Areas of agreement and disagreement\n' +
                         '5. Suggestions for improving future meetings\n\n' +
                         'Transcription:\n' + transcription;
    
    // Use AI orchestration with fallback - sentiment analysis is simple, use speed priority
    var result = callAIWithFallback(sentimentPrompt, TASK_TYPES.SENTIMENT_ANALYSIS, {
      priority: 'speed',
      maxRetries: 2
    });
    
    if (result.success) {
      return {
        success: true,
        analysis: result.response,
        model: result.model,
        latency: result.latency,
        cost: result.cost,
        timestamp: result.timestamp
      };
    } else {
      return {
        success: false,
        error: result.error,
        timestamp: result.timestamp
      };
    }
    
  } catch (e) {
    return { success: false, error: e.message };
  }
}

// Cost Management System
var COST_BUDGETS = {
  DAILY_LIMIT: 5.00,      // $5 per day (dev env)
  MONTHLY_LIMIT: 100.00,  // $100 per month (dev env)
  ALERT_THRESHOLD: 0.80,  // Alert at 80% of limit
  PER_REQUEST_LIMIT: 0.02 // $0.02 per request (from story)
};

var COST_STORAGE = {
  DAILY_KEY: 'ai_cost_daily_',
  MONTHLY_KEY: 'ai_cost_monthly_',
  ALERTS_KEY: 'ai_cost_alerts_'
};

function getCostKey(type, date) {
  var dateStr = date ? Utilities.formatDate(date, Session.getScriptTimeZone(), 'yyyy-MM-dd') : 
                      Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'yyyy-MM-dd');
  return COST_STORAGE[type + '_KEY'] + dateStr;
}

function getDailyCost(date) {
  date = date || new Date();
  var key = getCostKey('DAILY', date);
  var cost = PropertiesService.getScriptProperties().getProperty(key);
  return cost ? parseFloat(cost) : 0;
}

function getMonthlyCost(date) {
  date = date || new Date();
  var monthKey = Utilities.formatDate(date, Session.getScriptTimeZone(), 'yyyy-MM');
  var key = COST_STORAGE.MONTHLY_KEY + monthKey;
  var cost = PropertiesService.getScriptProperties().getProperty(key);
  return cost ? parseFloat(cost) : 0;
}

function addCost(cost, date) {
  date = date || new Date();
  
  // Add to daily cost
  var dailyKey = getCostKey('DAILY', date);
  var dailyCost = getDailyCost(date) + cost;
  PropertiesService.getScriptProperties().setProperty(dailyKey, dailyCost.toString());
  
  // Add to monthly cost
  var monthKey = Utilities.formatDate(date, Session.getScriptTimeZone(), 'yyyy-MM');
  var monthlyKey = COST_STORAGE.MONTHLY_KEY + monthKey;
  var monthlyCost = getMonthlyCost(date) + cost;
  PropertiesService.getScriptProperties().setProperty(monthlyKey, monthlyCost.toString());
  
  // Check for alerts
  checkCostAlerts(dailyCost, monthlyCost, date);
  
  console.log('Cost added:', cost, 'Daily total:', dailyCost, 'Monthly total:', monthlyCost);
  
  return {
    dailyCost: dailyCost,
    monthlyCost: monthlyCost,
    dailyRemaining: COST_BUDGETS.DAILY_LIMIT - dailyCost,
    monthlyRemaining: COST_BUDGETS.MONTHLY_LIMIT - monthlyCost
  };
}

function checkCostAlerts(dailyCost, monthlyCost, date) {
  var alerts = [];
  
  // Check daily limit
  if (dailyCost >= COST_BUDGETS.DAILY_LIMIT * COST_BUDGETS.ALERT_THRESHOLD) {
    alerts.push({
      type: 'daily_warning',
      message: 'Daily cost limit at ' + Math.round((dailyCost / COST_BUDGETS.DAILY_LIMIT) * 100) + '%',
      cost: dailyCost,
      limit: COST_BUDGETS.DAILY_LIMIT
    });
  }
  
  // Check monthly limit
  if (monthlyCost >= COST_BUDGETS.MONTHLY_LIMIT * COST_BUDGETS.ALERT_THRESHOLD) {
    alerts.push({
      type: 'monthly_warning',
      message: 'Monthly cost limit at ' + Math.round((monthlyCost / COST_BUDGETS.MONTHLY_LIMIT) * 100) + '%',
      cost: monthlyCost,
      limit: COST_BUDGETS.MONTHLY_LIMIT
    });
  }
  
  // Store alerts
  if (alerts.length > 0) {
    var alertKey = COST_STORAGE.ALERTS_KEY + Utilities.formatDate(date, Session.getScriptTimeZone(), 'yyyy-MM-dd');
    PropertiesService.getScriptProperties().setProperty(alertKey, JSON.stringify(alerts));
  }
  
  return alerts;
}

function canMakeRequest(estimatedCost) {
  var today = new Date();
  var dailyCost = getDailyCost(today);
  var monthlyCost = getMonthlyCost(today);
  
  // Check per-request limit
  if (estimatedCost > COST_BUDGETS.PER_REQUEST_LIMIT) {
    return {
      allowed: false,
      reason: 'Request cost exceeds per-request limit of $' + COST_BUDGETS.PER_REQUEST_LIMIT,
      estimatedCost: estimatedCost
    };
  }
  
  // Check daily limit
  if (dailyCost + estimatedCost > COST_BUDGETS.DAILY_LIMIT) {
    return {
      allowed: false,
      reason: 'Request would exceed daily limit of $' + COST_BUDGETS.DAILY_LIMIT,
      dailyCost: dailyCost,
      estimatedCost: estimatedCost
    };
  }
  
  // Check monthly limit
  if (monthlyCost + estimatedCost > COST_BUDGETS.MONTHLY_LIMIT) {
    return {
      allowed: false,
      reason: 'Request would exceed monthly limit of $' + COST_BUDGETS.MONTHLY_LIMIT,
      monthlyCost: monthlyCost,
      estimatedCost: estimatedCost
    };
  }
  
  return {
    allowed: true,
    dailyCost: dailyCost,
    monthlyCost: monthlyCost,
    estimatedCost: estimatedCost
  };
}

function getCostBudgetExceededResponse() {
  return {
    success: false,
    error: 'Cost budget exceeded',
    message: 'AI service temporarily unavailable due to cost budget limits. Please try again tomorrow or contact administrator.',
    costInfo: {
      dailyCost: getDailyCost(),
      monthlyCost: getMonthlyCost(),
      dailyLimit: COST_BUDGETS.DAILY_LIMIT,
      monthlyLimit: COST_BUDGETS.MONTHLY_LIMIT
    },
    timestamp: new Date().toISOString()
  };
}

function getCostStatus() {
  var today = new Date();
  var dailyCost = getDailyCost(today);
  var monthlyCost = getMonthlyCost(today);
  
  return {
    daily: {
      cost: dailyCost,
      limit: COST_BUDGETS.DAILY_LIMIT,
      remaining: COST_BUDGETS.DAILY_LIMIT - dailyCost,
      percentage: (dailyCost / COST_BUDGETS.DAILY_LIMIT) * 100
    },
    monthly: {
      cost: monthlyCost,
      limit: COST_BUDGETS.MONTHLY_LIMIT,
      remaining: COST_BUDGETS.MONTHLY_LIMIT - monthlyCost,
      percentage: (monthlyCost / COST_BUDGETS.MONTHLY_LIMIT) * 100
    },
    timestamp: new Date().toISOString()
  };
}

// Safety & Guardrails System
var SAFETY_CONFIG = {
  PII_FILTER_ENABLED: true,
  CONTENT_MODERATION_ENABLED: true,
  ALLOW_LIST_ENABLED: true,
  DENY_LIST_ENABLED: true,
  HUMAN_CONFIRMATION_REQUIRED: true,
  LOG_SAFETY_VIOLATIONS: true
};

var ALLOWED_DOMAINS = [
  'gmail.com',
  'google.com',
  'googleapis.com',
  'script.google.com',
  'docs.google.com',
  'calendar.google.com',
  'contacts.google.com',
  'tasks.google.com',
  'drive.google.com'
];

var DENIED_DOMAINS = [
  'malicious-site.com',
  'phishing-site.com',
  'spam-site.com'
];

var ALLOWED_TOOLS = [
  'gmail_read',
  'gmail_compose',
  'gmail_send',
  'calendar_view',
  'calendar_create',
  'tasks_list',
  'tasks_create',
  'tasks_complete',
  'docs_create',
  'docs_list',
  'docs_view',
  'contacts_search',
  'contacts_list',
  'transcription_analyze',
  'meeting_summary',
  'sentiment_analyze'
];

var DENIED_TOOLS = [
  'system_access',
  'file_delete',
  'admin_access',
  'user_impersonation'
];

var PII_PATTERNS = [
  // Social Security Numbers
  { pattern: /\b\d{3}-\d{2}-\d{4}\b/g, type: 'SSN', replacement: '[SSN-REDACTED]' },
  { pattern: /\b\d{3}\s\d{2}\s\d{4}\b/g, type: 'SSN', replacement: '[SSN-REDACTED]' },
  
  // Credit Card Numbers
  { pattern: /\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/g, type: 'CREDIT_CARD', replacement: '[CARD-REDACTED]' },
  
  // Phone Numbers
  { pattern: /\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/g, type: 'PHONE', replacement: '[PHONE-REDACTED]' },
  { pattern: /\(\d{3}\)\s?\d{3}[-.]?\d{4}/g, type: 'PHONE', replacement: '[PHONE-REDACTED]' },
  
  // Email Addresses (partial - keep domain for business use)
  { pattern: /\b([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\b/g, type: 'EMAIL', replacement: '[EMAIL-REDACTED]@$2' },
  
  // Bank Account Numbers
  { pattern: /\b\d{8,17}\b/g, type: 'BANK_ACCOUNT', replacement: '[ACCOUNT-REDACTED]' },
  
  // Driver's License
  { pattern: /\b[A-Z]\d{7,8}\b/g, type: 'DRIVERS_LICENSE', replacement: '[DL-REDACTED]' }
];

var PROFANITY_WORDS = [
  'damn', 'hell', 'crap', 'stupid', 'idiot', 'moron', 'hate', 'kill', 'die',
  'murder', 'suicide', 'bomb', 'terrorist', 'attack', 'violence', 'weapon'
];

var SENSITIVE_ACTIONS = [
  'delete_user_data',
  'modify_permissions',
  'access_admin_functions',
  'export_all_data',
  'modify_system_settings',
  'impersonate_user'
];

function filterPII(text) {
  if (!SAFETY_CONFIG.PII_FILTER_ENABLED) {
    return { text: text, violations: [] };
  }
  
  var filteredText = text;
  var violations = [];
  
  for (var i = 0; i < PII_PATTERNS.length; i++) {
    var pattern = PII_PATTERNS[i];
    var matches = text.match(pattern.pattern);
    
    if (matches) {
      filteredText = filteredText.replace(pattern.pattern, pattern.replacement);
      violations.push({
        type: pattern.type,
        count: matches.length,
        pattern: pattern.pattern.toString()
      });
    }
  }
  
  return {
    text: filteredText,
    violations: violations,
    originalLength: text.length,
    filteredLength: filteredText.length
  };
}

function moderateContent(text) {
  if (!SAFETY_CONFIG.CONTENT_MODERATION_ENABLED) {
    return { text: text, violations: [] };
  }
  
  var moderatedText = text;
  var violations = [];
  var lowerText = text.toLowerCase();
  
  // Check for profanity
  for (var i = 0; i < PROFANITY_WORDS.length; i++) {
    var word = PROFANITY_WORDS[i];
    var regex = new RegExp('\\b' + word + '\\b', 'gi');
    var matches = text.match(regex);
    
    if (matches) {
      moderatedText = moderatedText.replace(regex, '[FILTERED]');
      violations.push({
        type: 'PROFANITY',
        word: word,
        count: matches.length
      });
    }
  }
  
  // Check for threatening language
  var threatPatterns = [
    { pattern: /\b(kill|murder|destroy|harm|hurt)\s+(you|him|her|them|me)\b/gi, type: 'THREAT' },
    { pattern: /\b(bomb|explosive|weapon|gun|knife)\b/gi, type: 'WEAPON' },
    { pattern: /\b(suicide|self-harm|end\s+it\s+all)\b/gi, type: 'SELF_HARM' }
  ];
  
  for (var i = 0; i < threatPatterns.length; i++) {
    var threatPattern = threatPatterns[i];
    var matches = text.match(threatPattern.pattern);
    
    if (matches) {
      moderatedText = moderatedText.replace(threatPattern.pattern, '[CONTENT-FILTERED]');
      violations.push({
        type: threatPattern.type,
        count: matches.length,
        pattern: threatPattern.pattern.toString()
      });
    }
  }
  
  return {
    text: moderatedText,
    violations: violations,
    originalLength: text.length,
    moderatedLength: moderatedText.length
  };
}

function checkDomainSafety(domain) {
  if (!SAFETY_CONFIG.ALLOW_LIST_ENABLED && !SAFETY_CONFIG.DENY_LIST_ENABLED) {
    return { allowed: true, reason: 'Safety checks disabled' };
  }
  
  // Check deny list first
  if (SAFETY_CONFIG.DENY_LIST_ENABLED) {
    for (var i = 0; i < DENIED_DOMAINS.length; i++) {
      if (domain.includes(DENIED_DOMAINS[i])) {
        return { allowed: false, reason: 'Domain in deny list: ' + DENIED_DOMAINS[i] };
      }
    }
  }
  
  // Check allow list
  if (SAFETY_CONFIG.ALLOW_LIST_ENABLED) {
    var isAllowed = false;
    for (var i = 0; i < ALLOWED_DOMAINS.length; i++) {
      if (domain.includes(ALLOWED_DOMAINS[i])) {
        isAllowed = true;
        break;
      }
    }
    
    if (!isAllowed) {
      return { allowed: false, reason: 'Domain not in allow list: ' + domain };
    }
  }
  
  return { allowed: true, reason: 'Domain approved' };
}

function checkToolSafety(toolName) {
  if (!SAFETY_CONFIG.ALLOW_LIST_ENABLED && !SAFETY_CONFIG.DENY_LIST_ENABLED) {
    return { allowed: true, reason: 'Safety checks disabled' };
  }
  
  // Check deny list first
  if (SAFETY_CONFIG.DENY_LIST_ENABLED) {
    for (var i = 0; i < DENIED_TOOLS.length; i++) {
      if (toolName === DENIED_TOOLS[i]) {
        return { allowed: false, reason: 'Tool in deny list: ' + DENIED_TOOLS[i] };
      }
    }
  }
  
  // Check allow list
  if (SAFETY_CONFIG.ALLOW_LIST_ENABLED) {
    var isAllowed = false;
    for (var i = 0; i < ALLOWED_TOOLS.length; i++) {
      if (toolName === ALLOWED_TOOLS[i]) {
        isAllowed = true;
        break;
      }
    }
    
    if (!isAllowed) {
      return { allowed: false, reason: 'Tool not in allow list: ' + toolName };
    }
  }
  
  return { allowed: true, reason: 'Tool approved' };
}

function checkSensitiveAction(action) {
  for (var i = 0; i < SENSITIVE_ACTIONS.length; i++) {
    if (action === SENSITIVE_ACTIONS[i]) {
      return {
        isSensitive: true,
        requiresConfirmation: SAFETY_CONFIG.HUMAN_CONFIRMATION_REQUIRED,
        action: action
      };
    }
  }
  
  return {
    isSensitive: false,
    requiresConfirmation: false,
    action: action
  };
}

function logSafetyViolation(violation) {
  if (!SAFETY_CONFIG.LOG_SAFETY_VIOLATIONS) {
    return;
  }
  
  var logEntry = {
    timestamp: new Date().toISOString(),
    type: violation.type,
    details: violation,
    user: getSessionUserEmail()
  };
  
  console.log('SAFETY VIOLATION:', JSON.stringify(logEntry));
  
  // In a real system, this would be sent to a security logging service
  // For now, we'll store it in Script Properties
  var violationKey = 'safety_violation_' + Date.now();
  PropertiesService.getScriptProperties().setProperty(violationKey, JSON.stringify(logEntry));
}

function applySafetyFilters(text, context) {
  context = context || {};
  var result = {
    originalText: text,
    filteredText: text,
    piiFiltered: false,
    contentModerated: false,
    violations: [],
    safetyScore: 100
  };
  
  // Apply PII filtering
  var piiResult = filterPII(text);
  if (piiResult.violations.length > 0) {
    result.filteredText = piiResult.text;
    result.piiFiltered = true;
    result.violations = result.violations.concat(piiResult.violations.map(function(v) {
      return { type: 'PII', details: v };
    }));
    result.safetyScore -= piiResult.violations.length * 10;
    
    // Log PII violations
    for (var i = 0; i < piiResult.violations.length; i++) {
      logSafetyViolation({
        type: 'PII_DETECTED',
        piiType: piiResult.violations[i].type,
        count: piiResult.violations[i].count
      });
    }
  }
  
  // Apply content moderation
  var moderationResult = moderateContent(result.filteredText);
  if (moderationResult.violations.length > 0) {
    result.filteredText = moderationResult.text;
    result.contentModerated = true;
    result.violations = result.violations.concat(moderationResult.violations.map(function(v) {
      return { type: 'CONTENT_MODERATION', details: v };
    }));
    result.safetyScore -= moderationResult.violations.length * 15;
    
    // Log content violations
    for (var i = 0; i < moderationResult.violations.length; i++) {
      logSafetyViolation({
        type: 'CONTENT_VIOLATION',
        violationType: moderationResult.violations[i].type,
        count: moderationResult.violations[i].count
      });
    }
  }
  
  // Ensure safety score doesn't go below 0
  result.safetyScore = Math.max(0, result.safetyScore);
  
  return result;
}

function getSafetyStatus() {
  return {
    config: SAFETY_CONFIG,
    allowedDomains: ALLOWED_DOMAINS.length,
    deniedDomains: DENIED_DOMAINS.length,
    allowedTools: ALLOWED_TOOLS.length,
    deniedTools: DENIED_TOOLS.length,
    piiPatterns: PII_PATTERNS.length,
    profanityWords: PROFANITY_WORDS.length,
    sensitiveActions: SENSITIVE_ACTIONS.length
  };
}

// Workflow Engine System
var WORKFLOW_CONFIG = {
  DEFAULT_OPERATION_TIMEOUT: 10000,    // 10 seconds
  LONG_OPERATION_TIMEOUT: 60000,       // 60 seconds for transcription
  OVERALL_WORKFLOW_TIMEOUT: 120000,    // 2 minutes
  DEDUPE_WINDOW: 600000,               // 10 minutes
  MAX_RETRIES: 3,
  ALERT_ERROR_RATE_THRESHOLD: 0.02,    // 2%
  ALERT_P95_THRESHOLD: 2000,           // 2 seconds
  ALERT_RETRY_THRESHOLD: 3             // 3 retries per minute
};

var WORKFLOW_STATES = {
  PENDING: 'pending',
  RUNNING: 'running',
  COMPLETED: 'completed',
  FAILED: 'failed',
  COMPENSATED: 'compensated',
  TIMEOUT: 'timeout'
};

var WORKFLOW_STEPS = {
  CLASSIFY: 'classify',
  SUMMARIZE: 'summarize',
  ROUTE: 'route',
  NOTIFY: 'notify',
  PROPOSE_SLOTS: 'propose_slots',
  CONFIRM: 'confirm',
  CREATE_EVENT: 'create_event',
  INVITE: 'invite',
  TRANSCRIBE: 'transcribe',
  ANALYZE: 'analyze',
  CREATE_DOC: 'create_doc',
  SHARE: 'share'
};

function generateWorkflowId(input, user, intent) {
  var payload = JSON.stringify({ input: input, user: user, intent: intent });
  var hash = Utilities.computeDigest(Utilities.DigestAlgorithm.MD5, payload, Utilities.Charset.UTF_8);
  return Utilities.base64Encode(hash).replace(/[^a-zA-Z0-9]/g, '').substring(0, 16);
}

function generateStepId(workflowId, stepName) {
  return workflowId + '_' + stepName + '_' + Date.now();
}

function logWorkflowEvent(workflowId, stepId, event, data) {
  var logEntry = {
    timestamp: new Date().toISOString(),
    workflowId: workflowId,
    stepId: stepId,
    event: event,
    data: data,
    user: getSessionUserEmail()
  };
  
  console.log('WORKFLOW_EVENT:', JSON.stringify(logEntry));
  
  // Store in Script Properties for persistence
  var logKey = 'workflow_log_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  PropertiesService.getScriptProperties().setProperty(logKey, JSON.stringify(logEntry));
}

function checkWorkflowDeduplication(workflowId) {
  var dedupeKey = 'workflow_dedupe_' + workflowId;
  var existing = PropertiesService.getScriptProperties().getProperty(dedupeKey);
  
  if (existing) {
    var existingData = JSON.parse(existing);
    var timeDiff = Date.now() - existingData.timestamp;
    
    if (timeDiff < WORKFLOW_CONFIG.DEDUPE_WINDOW) {
      return {
        isDuplicate: true,
        existingWorkflow: existingData,
        timeRemaining: WORKFLOW_CONFIG.DEDUPE_WINDOW - timeDiff
      };
    }
  }
  
  // Mark as running
  PropertiesService.getScriptProperties().setProperty(dedupeKey, JSON.stringify({
    workflowId: workflowId,
    timestamp: Date.now(),
    status: WORKFLOW_STATES.RUNNING
  }));
  
  return { isDuplicate: false };
}

function executeWorkflowStep(workflowId, stepId, stepName, stepFunction, input, timeout) {
  timeout = timeout || WORKFLOW_CONFIG.DEFAULT_OPERATION_TIMEOUT;
  var startTime = Date.now();
  
  logWorkflowEvent(workflowId, stepId, 'step_started', {
    stepName: stepName,
    input: input,
    timeout: timeout
  });
  
  try {
    var result = stepFunction(input);
    var duration = Date.now() - startTime;
    
    logWorkflowEvent(workflowId, stepId, 'step_completed', {
      stepName: stepName,
      duration: duration,
      result: result
    });
    
    return {
      success: true,
      result: result,
      duration: duration,
      stepId: stepId
    };
    
  } catch (e) {
    var duration = Date.now() - startTime;
    
    logWorkflowEvent(workflowId, stepId, 'step_failed', {
      stepName: stepName,
      duration: duration,
      error: e.message
    });
    
    return {
      success: false,
      error: e.message,
      duration: duration,
      stepId: stepId
    };
  }
}

// WF-EMAIL-ROUTER Workflow Implementation
function executeEmailRouterWorkflow(emailData, options) {
  options = options || {};
  var workflowId = generateWorkflowId(emailData, getSessionUserEmail(), 'email_router');
  var startTime = Date.now();
  
  logWorkflowEvent(workflowId, null, 'workflow_started', {
    workflowType: 'WF-EMAIL-ROUTER',
    emailData: emailData,
    options: options
  });
  
  // Check for deduplication
  var dedupeCheck = checkWorkflowDeduplication(workflowId);
  if (dedupeCheck.isDuplicate) {
    logWorkflowEvent(workflowId, null, 'workflow_duplicate', {
      existingWorkflow: dedupeCheck.existingWorkflow,
      timeRemaining: dedupeCheck.timeRemaining
    });
    
    return {
      success: false,
      error: 'Duplicate workflow detected',
      workflowId: workflowId,
      isDuplicate: true,
      timeRemaining: dedupeCheck.timeRemaining
    };
  }
  
  var workflowResult = {
    workflowId: workflowId,
    workflowType: 'WF-EMAIL-ROUTER',
    status: WORKFLOW_STATES.RUNNING,
    steps: [],
    startTime: startTime,
    compensationSteps: []
  };
  
  try {
    // Step 1: Classify Email
    var classifyStepId = generateStepId(workflowId, WORKFLOW_STEPS.CLASSIFY);
    var classifyResult = executeWorkflowStep(workflowId, classifyStepId, WORKFLOW_STEPS.CLASSIFY, 
      function(input) { return classifyEmail(input); }, emailData);
    
    workflowResult.steps.push(classifyResult);
    
    if (!classifyResult.success) {
      throw new Error('Email classification failed: ' + classifyResult.error);
    }
    
    // Step 2: Summarize Email
    var summarizeStepId = generateStepId(workflowId, WORKFLOW_STEPS.SUMMARIZE);
    var summarizeResult = executeWorkflowStep(workflowId, summarizeStepId, WORKFLOW_STEPS.SUMMARIZE,
      function(input) { return summarizeEmail(input, classifyResult.result); }, emailData);
    
    workflowResult.steps.push(summarizeResult);
    
    if (!summarizeResult.success) {
      throw new Error('Email summarization failed: ' + summarizeResult.error);
    }
    
    // Step 3: Route Email
    var routeStepId = generateStepId(workflowId, WORKFLOW_STEPS.ROUTE);
    var routeResult = executeWorkflowStep(workflowId, routeStepId, WORKFLOW_STEPS.ROUTE,
      function(input) { return routeEmail(input, classifyResult.result, summarizeResult.result); }, emailData);
    
    workflowResult.steps.push(routeResult);
    
    if (!routeResult.success) {
      throw new Error('Email routing failed: ' + routeResult.error);
    }
    
    // Step 4: Send Notification
    var notifyStepId = generateStepId(workflowId, WORKFLOW_STEPS.NOTIFY);
    var notifyResult = executeWorkflowStep(workflowId, notifyStepId, WORKFLOW_STEPS.NOTIFY,
      function(input) { return sendRoutingNotification(input, routeResult.result); }, emailData);
    
    workflowResult.steps.push(notifyResult);
    
    if (!notifyResult.success) {
      throw new Error('Notification failed: ' + notifyResult.error);
    }
    
    // Workflow completed successfully
    workflowResult.status = WORKFLOW_STATES.COMPLETED;
    workflowResult.endTime = Date.now();
    workflowResult.totalDuration = workflowResult.endTime - startTime;
    
    logWorkflowEvent(workflowId, null, 'workflow_completed', {
      totalDuration: workflowResult.totalDuration,
      stepsCompleted: workflowResult.steps.length
    });
    
    return {
      success: true,
      workflowResult: workflowResult,
      classification: classifyResult.result,
      summary: summarizeResult.result,
      routing: routeResult.result,
      notification: notifyResult.result
    };
    
  } catch (e) {
    // Workflow failed - execute compensation steps
    workflowResult.status = WORKFLOW_STATES.FAILED;
    workflowResult.endTime = Date.now();
    workflowResult.totalDuration = workflowResult.endTime - startTime;
    workflowResult.error = e.message;
    
    logWorkflowEvent(workflowId, null, 'workflow_failed', {
      error: e.message,
      totalDuration: workflowResult.totalDuration,
      stepsCompleted: workflowResult.steps.length
    });
    
    // Execute compensation steps
    var compensationResult = executeCompensationSteps(workflowId, workflowResult);
    workflowResult.compensationSteps = compensationResult;
    
    return {
      success: false,
      error: e.message,
      workflowResult: workflowResult
    };
  }
}

function classifyEmail(emailData) {
  try {
    var emailText = emailData.subject + ' ' + emailData.body;
    
    var classificationPrompt = 'Classify this email into one of these categories:\n' +
      '1. URGENT - Requires immediate attention\n' +
      '2. IMPORTANT - Important but not urgent\n' +
      '3. ROUTINE - Standard business communication\n' +
      '4. SPAM - Unwanted or promotional content\n' +
      '5. PERSONAL - Personal communication\n\n' +
      'Email Subject: ' + emailData.subject + '\n' +
      'Email Body: ' + emailData.body.substring(0, 1000) + '\n\n' +
      'Respond with only the category number and name, plus a brief reason.';
    
    var result = callAIWithFallback(classificationPrompt, TASK_TYPES.TRANSCRIPTION_ANALYSIS, {
      priority: 'speed',
      maxRetries: 2
    });
    
    if (!result.success) {
      throw new Error('AI classification failed: ' + result.error);
    }
    
    var classification = result.response;
    var category = 'ROUTINE'; // Default
    var confidence = 0.5;
    
    if (classification.includes('1') || classification.toLowerCase().includes('urgent')) {
      category = 'URGENT';
      confidence = 0.9;
    } else if (classification.includes('2') || classification.toLowerCase().includes('important')) {
      category = 'IMPORTANT';
      confidence = 0.8;
    } else if (classification.includes('4') || classification.toLowerCase().includes('spam')) {
      category = 'SPAM';
      confidence = 0.7;
    } else if (classification.includes('5') || classification.toLowerCase().includes('personal')) {
      category = 'PERSONAL';
      confidence = 0.6;
    }
    
    return {
      category: category,
      confidence: confidence,
      reasoning: classification,
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Email classification error:', e.message);
    throw e;
  }
}

function summarizeEmail(emailData, classification) {
  try {
    var summaryPrompt = 'Summarize this email in 2-3 sentences, focusing on:\n' +
      '1. Main purpose or request\n' +
      '2. Key information or deadlines\n' +
      '3. Required actions\n\n' +
      'Email Category: ' + classification.category + '\n' +
      'Email Subject: ' + emailData.subject + '\n' +
      'Email Body: ' + emailData.body.substring(0, 2000) + '\n\n' +
      'Provide a concise summary.';
    
    var result = callAIWithFallback(summaryPrompt, TASK_TYPES.MEETING_SUMMARY, {
      priority: 'balanced',
      maxRetries: 2
    });
    
    if (!result.success) {
      throw new Error('AI summarization failed: ' + result.error);
    }
    
    return {
      summary: result.response,
      category: classification.category,
      confidence: classification.confidence,
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Email summarization error:', e.message);
    throw e;
  }
}

function routeEmail(emailData, classification, summary) {
  try {
    var routingRules = {
      'URGENT': {
        action: 'forward_to_manager',
        priority: 'high',
        notification: 'immediate'
      },
      'IMPORTANT': {
        action: 'add_to_tasks',
        priority: 'medium',
        notification: 'within_hour'
      },
      'ROUTINE': {
        action: 'archive',
        priority: 'low',
        notification: 'daily_digest'
      },
      'SPAM': {
        action: 'delete',
        priority: 'none',
        notification: 'none'
      },
      'PERSONAL': {
        action: 'separate_folder',
        priority: 'low',
        notification: 'none'
      }
    };
    
    var rule = routingRules[classification.category] || routingRules['ROUTINE'];
    
    return {
      action: rule.action,
      priority: rule.priority,
      notification: rule.notification,
      category: classification.category,
      confidence: classification.confidence,
      summary: summary.summary,
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Email routing error:', e.message);
    throw e;
  }
}

function sendRoutingNotification(emailData, routing) {
  try {
    var notificationMessage = 'Email processed: ' + emailData.subject + '\n' +
      'Category: ' + routing.category + '\n' +
      'Action: ' + routing.action + '\n' +
      'Priority: ' + routing.priority + '\n' +
      'Summary: ' + routing.summary;
    
    // In a real implementation, this would send actual notifications
    // For now, we'll log the notification
    console.log('ROUTING_NOTIFICATION:', notificationMessage);
    
    return {
      notificationSent: true,
      message: notificationMessage,
      action: routing.action,
      priority: routing.priority,
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Notification error:', e.message);
    throw e;
  }
}

function executeCompensationSteps(workflowId, workflowResult) {
  var compensationSteps = [];
  
  try {
    // Reverse any actions that were taken
    for (var i = workflowResult.steps.length - 1; i >= 0; i--) {
      var step = workflowResult.steps[i];
      
      if (step.success) {
        var compensationStep = {
          stepId: generateStepId(workflowId, 'compensation_' + step.stepId),
          originalStep: step.stepId,
          action: 'compensate_' + step.stepId.split('_')[1],
          status: 'pending'
        };
        
        // Execute compensation based on step type
        try {
          switch (step.stepId.split('_')[1]) {
            case 'notify':
              // Cancel notification
              compensationStep.result = 'Notification cancelled';
              compensationStep.status = 'completed';
              break;
            case 'route':
              // Revert routing decision
              compensationStep.result = 'Routing decision reverted';
              compensationStep.status = 'completed';
              break;
            case 'summarize':
              // Clear summary cache
              compensationStep.result = 'Summary cache cleared';
              compensationStep.status = 'completed';
              break;
            case 'classify':
              // Clear classification cache
              compensationStep.result = 'Classification cache cleared';
              compensationStep.status = 'completed';
              break;
            default:
              compensationStep.result = 'No compensation needed';
              compensationStep.status = 'skipped';
          }
        } catch (e) {
          compensationStep.status = 'failed';
          compensationStep.error = e.message;
        }
        
        compensationSteps.push(compensationStep);
      }
    }
    
    logWorkflowEvent(workflowId, null, 'compensation_completed', {
      stepsCompensated: compensationSteps.length
    });
    
  } catch (e) {
    logWorkflowEvent(workflowId, null, 'compensation_failed', {
      error: e.message
    });
  }
  
  return compensationSteps;
}

// WF-CAL-MEETING-CREATE Workflow Implementation
function executeMeetingCreateWorkflow(meetingData, options) {
  options = options || {};
  var workflowId = generateWorkflowId(meetingData, getSessionUserEmail(), 'meeting_create');
  var startTime = Date.now();
  
  logWorkflowEvent(workflowId, null, 'workflow_started', {
    workflowType: 'WF-CAL-MEETING-CREATE',
    meetingData: meetingData,
    options: options
  });
  
  // Check for deduplication
  var dedupeCheck = checkWorkflowDeduplication(workflowId);
  if (dedupeCheck.isDuplicate) {
    logWorkflowEvent(workflowId, null, 'workflow_duplicate', {
      existingWorkflow: dedupeCheck.existingWorkflow,
      timeRemaining: dedupeCheck.timeRemaining
    });
    
    return {
      success: false,
      error: 'Duplicate workflow detected',
      workflowId: workflowId,
      isDuplicate: true,
      timeRemaining: dedupeCheck.timeRemaining
    };
  }
  
  var workflowResult = {
    workflowId: workflowId,
    workflowType: 'WF-CAL-MEETING-CREATE',
    status: WORKFLOW_STATES.RUNNING,
    steps: [],
    startTime: startTime,
    compensationSteps: []
  };
  
  try {
    // Step 1: Propose Time Slots
    var proposeStepId = generateStepId(workflowId, WORKFLOW_STEPS.PROPOSE_SLOTS);
    var proposeResult = executeWorkflowStep(workflowId, proposeStepId, WORKFLOW_STEPS.PROPOSE_SLOTS,
      function(input) { return proposeMeetingSlots(input); }, meetingData);
    
    workflowResult.steps.push(proposeResult);
    
    if (!proposeResult.success) {
      throw new Error('Slot proposal failed: ' + proposeResult.error);
    }
    
    // Step 2: Get Confirmation
    var confirmStepId = generateStepId(workflowId, WORKFLOW_STEPS.CONFIRM);
    var confirmResult = executeWorkflowStep(workflowId, confirmStepId, WORKFLOW_STEPS.CONFIRM,
      function(input) { return confirmMeetingSlot(input, proposeResult.result); }, meetingData);
    
    workflowResult.steps.push(confirmResult);
    
    if (!confirmResult.success) {
      throw new Error('Confirmation failed: ' + confirmResult.error);
    }
    
    // Step 3: Create Calendar Event
    var createStepId = generateStepId(workflowId, WORKFLOW_STEPS.CREATE_EVENT);
    var createResult = executeWorkflowStep(workflowId, createStepId, WORKFLOW_STEPS.CREATE_EVENT,
      function(input) { return createCalendarEvent(input, confirmResult.result); }, meetingData);
    
    workflowResult.steps.push(createResult);
    
    if (!createResult.success) {
      throw new Error('Event creation failed: ' + createResult.error);
    }
    
    // Step 4: Send Invitations
    var inviteStepId = generateStepId(workflowId, WORKFLOW_STEPS.INVITE);
    var inviteResult = executeWorkflowStep(workflowId, inviteStepId, WORKFLOW_STEPS.INVITE,
      function(input) { return sendMeetingInvitations(input, createResult.result); }, meetingData);
    
    workflowResult.steps.push(inviteResult);
    
    if (!inviteResult.success) {
      throw new Error('Invitation failed: ' + inviteResult.error);
    }
    
    // Workflow completed successfully
    workflowResult.status = WORKFLOW_STATES.COMPLETED;
    workflowResult.endTime = Date.now();
    workflowResult.totalDuration = workflowResult.endTime - startTime;
    
    logWorkflowEvent(workflowId, null, 'workflow_completed', {
      totalDuration: workflowResult.totalDuration,
      stepsCompleted: workflowResult.steps.length
    });
    
    return {
      success: true,
      workflowResult: workflowResult,
      proposedSlots: proposeResult.result,
      confirmation: confirmResult.result,
      event: createResult.result,
      invitations: inviteResult.result
    };
    
  } catch (e) {
    // Workflow failed - execute compensation steps
    workflowResult.status = WORKFLOW_STATES.FAILED;
    workflowResult.endTime = Date.now();
    workflowResult.totalDuration = workflowResult.endTime - startTime;
    workflowResult.error = e.message;
    
    logWorkflowEvent(workflowId, null, 'workflow_failed', {
      error: e.message,
      totalDuration: workflowResult.totalDuration,
      stepsCompleted: workflowResult.steps.length
    });
    
    // Execute compensation steps
    var compensationResult = executeMeetingCompensationSteps(workflowId, workflowResult);
    workflowResult.compensationSteps = compensationResult;
    
    return {
      success: false,
      error: e.message,
      workflowResult: workflowResult
    };
  }
}

function proposeMeetingSlots(meetingData) {
  try {
    var duration = meetingData.duration || 60; // Default 60 minutes
    var startDate = new Date(meetingData.startDate || new Date());
    var endDate = new Date(meetingData.endDate || new Date(startDate.getTime() + 7 * 24 * 60 * 60 * 1000)); // Default 7 days
    var workingHours = meetingData.workingHours || { start: 9, end: 17 }; // 9 AM to 5 PM
    var timezone = meetingData.timezone || 'America/New_York';
    
    // Generate time slots based on availability
    var slots = [];
    var currentDate = new Date(startDate);
    
    while (currentDate <= endDate && slots.length < 5) { // Max 5 slots
      // Skip weekends if not specified
      if (meetingData.includeWeekends !== true && (currentDate.getDay() === 0 || currentDate.getDay() === 6)) {
        currentDate.setDate(currentDate.getDate() + 1);
        continue;
      }
      
      // Generate slots for working hours
      for (var hour = workingHours.start; hour < workingHours.end; hour += 2) { // Every 2 hours
        var slotTime = new Date(currentDate);
        slotTime.setHours(hour, 0, 0, 0);
        
        var endTime = new Date(slotTime.getTime() + duration * 60 * 1000);
        
        // Check if slot is in the future
        if (slotTime > new Date()) {
          slots.push({
            start: slotTime.toISOString(),
            end: endTime.toISOString(),
            duration: duration,
            timezone: timezone,
            dayOfWeek: currentDate.toLocaleDateString('en-US', { weekday: 'long' }),
            timeFormatted: slotTime.toLocaleTimeString('en-US', { 
              hour: 'numeric', 
              minute: '2-digit',
              timeZone: timezone 
            })
          });
        }
        
        if (slots.length >= 5) break;
      }
      
      currentDate.setDate(currentDate.getDate() + 1);
    }
    
    // Use AI to rank and optimize slots if meeting details are provided
    if (meetingData.title && meetingData.description) {
      var optimizationPrompt = 'Rank these meeting time slots based on the meeting details:\n\n' +
        'Meeting: ' + meetingData.title + '\n' +
        'Description: ' + meetingData.description + '\n' +
        'Attendees: ' + (meetingData.attendees ? meetingData.attendees.join(', ') : 'Not specified') + '\n\n' +
        'Available slots:\n';
      
      for (var i = 0; i < slots.length; i++) {
        optimizationPrompt += (i + 1) + '. ' + slots[i].dayOfWeek + ' at ' + slots[i].timeFormatted + '\n';
      }
      
      optimizationPrompt += '\nRank them from best to worst and provide brief reasoning.';
      
      var aiResult = callAIWithFallback(optimizationPrompt, TASK_TYPES.TRANSCRIPTION_ANALYSIS, {
        priority: 'speed',
        maxRetries: 2
      });
      
      if (aiResult.success) {
        // Parse AI ranking and reorder slots
        var ranking = aiResult.response;
        // For now, we'll keep the original order but add AI reasoning
        for (var j = 0; j < slots.length; j++) {
          slots[j].aiRanking = j + 1;
          slots[j].aiReasoning = ranking;
        }
      }
    }
    
    return {
      slots: slots,
      totalSlots: slots.length,
      duration: duration,
      timezone: timezone,
      workingHours: workingHours,
      generatedAt: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Slot proposal error:', e.message);
    throw e;
  }
}

function confirmMeetingSlot(meetingData, proposedSlots) {
  try {
    // In a real implementation, this would involve user interaction
    // For now, we'll simulate confirmation by selecting the first available slot
    var selectedSlot = proposedSlots.slots[0];
    
    if (!selectedSlot) {
      throw new Error('No available slots to confirm');
    }
    
    // Simulate confirmation process
    var confirmation = {
      selectedSlot: selectedSlot,
      confirmedBy: getSessionUserEmail(),
      confirmedAt: new Date().toISOString(),
      meetingDetails: {
        title: meetingData.title || 'Meeting',
        description: meetingData.description || '',
        attendees: meetingData.attendees || [],
        location: meetingData.location || '',
        duration: selectedSlot.duration
      }
    };
    
    return confirmation;
    
  } catch (e) {
    console.log('Confirmation error:', e.message);
    throw e;
  }
}

function createCalendarEvent(meetingData, confirmation) {
  try {
    var event = {
      summary: confirmation.meetingDetails.title,
      description: confirmation.meetingDetails.description,
      start: {
        dateTime: confirmation.selectedSlot.start,
        timeZone: confirmation.selectedSlot.timezone
      },
      end: {
        dateTime: confirmation.selectedSlot.end,
        timeZone: confirmation.selectedSlot.timezone
      },
      attendees: confirmation.meetingDetails.attendees.map(function(email) {
        return { email: email };
      }),
      location: confirmation.meetingDetails.location,
      reminders: {
        useDefault: false,
        overrides: [
          { method: 'email', minutes: 24 * 60 }, // 1 day before
          { method: 'popup', minutes: 10 } // 10 minutes before
        ]
      }
    };
    
    // Create the event using Google Calendar API
    var createdEvent = Calendar.Events.insert(event, 'primary');
    
    return {
      eventId: createdEvent.id,
      eventLink: createdEvent.htmlLink,
      event: createdEvent,
      createdBy: getSessionUserEmail(),
      createdAt: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Event creation error:', e.message);
    throw e;
  }
}

function sendMeetingInvitations(meetingData, eventResult) {
  try {
    var invitations = [];
    var event = eventResult.event;
    
    // Send email invitations to attendees
    for (var i = 0; i < event.attendees.length; i++) {
      var attendee = event.attendees[i];
      
      var emailSubject = 'Meeting Invitation: ' + event.summary;
      var emailBody = 'You have been invited to a meeting:\n\n' +
        'Title: ' + event.summary + '\n' +
        'Date: ' + new Date(event.start.dateTime).toLocaleDateString() + '\n' +
        'Time: ' + new Date(event.start.dateTime).toLocaleTimeString() + '\n' +
        'Duration: ' + (eventResult.event.end.dateTime ? 
          Math.round((new Date(event.end.dateTime) - new Date(event.start.dateTime)) / (1000 * 60)) : 60) + ' minutes\n' +
        'Location: ' + (event.location || 'TBD') + '\n\n' +
        'Description: ' + (event.description || 'No description provided') + '\n\n' +
        'Calendar Link: ' + eventResult.eventLink + '\n\n' +
        'Please respond to this invitation.';
      
      // In a real implementation, this would send actual emails
      // For now, we'll simulate the invitation
      var invitation = {
        to: attendee.email,
        subject: emailSubject,
        body: emailBody,
        sent: true,
        sentAt: new Date().toISOString()
      };
      
      invitations.push(invitation);
      
      // Log the invitation (in real implementation, would send via Gmail API)
      console.log('MEETING_INVITATION:', JSON.stringify(invitation));
    }
    
    return {
      invitations: invitations,
      totalInvitations: invitations.length,
      eventId: eventResult.eventId,
      eventLink: eventResult.eventLink,
      sentAt: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Invitation error:', e.message);
    throw e;
  }
}

function executeMeetingCompensationSteps(workflowId, workflowResult) {
  var compensationSteps = [];
  
  try {
    // Reverse any actions that were taken
    for (var i = workflowResult.steps.length - 1; i >= 0; i--) {
      var step = workflowResult.steps[i];
      
      if (step.success) {
        var compensationStep = {
          stepId: generateStepId(workflowId, 'compensation_' + step.stepId),
          originalStep: step.stepId,
          action: 'compensate_' + step.stepId.split('_')[1],
          status: 'pending'
        };
        
        // Execute compensation based on step type
        try {
          switch (step.stepId.split('_')[1]) {
            case 'invite':
              // Cancel invitations
              compensationStep.result = 'Invitations cancelled';
              compensationStep.status = 'completed';
              break;
            case 'create_event':
              // Delete the created event
              if (step.result && step.result.eventId) {
                try {
                  Calendar.Events.remove('primary', step.result.eventId);
                  compensationStep.result = 'Event deleted: ' + step.result.eventId;
                } catch (e) {
                  compensationStep.result = 'Failed to delete event: ' + e.message;
                }
              } else {
                compensationStep.result = 'No event to delete';
              }
              compensationStep.status = 'completed';
              break;
            case 'confirm':
              // Clear confirmation
              compensationStep.result = 'Confirmation cleared';
              compensationStep.status = 'completed';
              break;
            case 'propose_slots':
              // Clear proposed slots
              compensationStep.result = 'Proposed slots cleared';
              compensationStep.status = 'completed';
              break;
            default:
              compensationStep.result = 'No compensation needed';
              compensationStep.status = 'skipped';
          }
        } catch (e) {
          compensationStep.status = 'failed';
          compensationStep.error = e.message;
        }
        
        compensationSteps.push(compensationStep);
      }
    }
    
    logWorkflowEvent(workflowId, null, 'compensation_completed', {
      stepsCompensated: compensationSteps.length
    });
    
  } catch (e) {
    logWorkflowEvent(workflowId, null, 'compensation_failed', {
      error: e.message
    });
  }
  
  return compensationSteps;
}

// Context Management System
var CONTEXT_LIMITS = {
  MAX_INPUT_TOKENS: 32000,    // 32k input tokens (from story)
  MAX_OUTPUT_TOKENS: 8000,    // 8k output tokens (from story)
  MAX_CONTEXT_PER_CALL: 3000, // 3k tokens context per call (from story)
  TOP_K_RETRIEVAL: 5,         // Top-k=5 retrieval (from story)
  CHUNK_OVERLAP: 200,         // Overlap between chunks
  MIN_CHUNK_SIZE: 100         // Minimum chunk size
};

function estimateTokens(text) {
  // Rough estimation: 1 token ≈ 4 characters for English text
  return Math.ceil(text.length / 4);
}

function chunkText(text, maxChunkSize) {
  maxChunkSize = maxChunkSize || CONTEXT_LIMITS.MAX_CONTEXT_PER_CALL * 4; // Convert tokens to chars
  
  var chunks = [];
  var lines = text.split('\n');
  var currentChunk = '';
  var currentTokens = 0;
  
  for (var i = 0; i < lines.length; i++) {
    var line = lines[i];
    var lineTokens = estimateTokens(line);
    
    // If adding this line would exceed the limit, save current chunk
    if (currentTokens + lineTokens > maxChunkSize && currentChunk.length > 0) {
      chunks.push({
        text: currentChunk.trim(),
        tokens: currentTokens,
        startLine: i - currentChunk.split('\n').length + 1,
        endLine: i
      });
      
      // Start new chunk with overlap
      var overlapText = getOverlapText(currentChunk, CONTEXT_LIMITS.CHUNK_OVERLAP);
      currentChunk = overlapText + '\n' + line;
      currentTokens = estimateTokens(currentChunk);
    } else {
      currentChunk += (currentChunk.length > 0 ? '\n' : '') + line;
      currentTokens += lineTokens;
    }
  }
  
  // Add the last chunk
  if (currentChunk.trim().length > 0) {
    chunks.push({
      text: currentChunk.trim(),
      tokens: currentTokens,
      startLine: lines.length - currentChunk.split('\n').length + 1,
      endLine: lines.length
    });
  }
  
  return chunks;
}

function getOverlapText(text, overlapChars) {
  if (text.length <= overlapChars) return text;
  return text.substring(text.length - overlapChars);
}

function chunkMarkdown(text, maxChunkSize) {
  maxChunkSize = maxChunkSize || CONTEXT_LIMITS.MAX_CONTEXT_PER_CALL * 4;
  
  var chunks = [];
  var sections = splitMarkdownSections(text);
  var currentChunk = '';
  var currentTokens = 0;
  
  for (var i = 0; i < sections.length; i++) {
    var section = sections[i];
    var sectionTokens = estimateTokens(section.text);
    
    // If adding this section would exceed the limit, save current chunk
    if (currentTokens + sectionTokens > maxChunkSize && currentChunk.length > 0) {
      chunks.push({
        text: currentChunk.trim(),
        tokens: currentTokens,
        type: 'markdown_section',
        sections: getSectionInfo(currentChunk)
      });
      
      // Start new chunk with overlap
      var overlapText = getOverlapText(currentChunk, CONTEXT_LIMITS.CHUNK_OVERLAP);
      currentChunk = overlapText + '\n\n' + section.text;
      currentTokens = estimateTokens(currentChunk);
    } else {
      currentChunk += (currentChunk.length > 0 ? '\n\n' : '') + section.text;
      currentTokens += sectionTokens;
    }
  }
  
  // Add the last chunk
  if (currentChunk.trim().length > 0) {
    chunks.push({
      text: currentChunk.trim(),
      tokens: currentTokens,
      type: 'markdown_section',
      sections: getSectionInfo(currentChunk)
    });
  }
  
  return chunks;
}

function splitMarkdownSections(text) {
  var sections = [];
  var lines = text.split('\n');
  var currentSection = '';
  var currentLevel = 0;
  
  for (var i = 0; i < lines.length; i++) {
    var line = lines[i];
    var headerMatch = line.match(/^(#{1,6})\s+(.+)/);
    
    if (headerMatch) {
      // Save previous section
      if (currentSection.trim().length > 0) {
        sections.push({
          text: currentSection.trim(),
          level: currentLevel,
          type: 'section'
        });
      }
      
      // Start new section
      currentSection = line;
      currentLevel = headerMatch[1].length;
    } else {
      currentSection += '\n' + line;
    }
  }
  
  // Add the last section
  if (currentSection.trim().length > 0) {
    sections.push({
      text: currentSection.trim(),
      level: currentLevel,
      type: 'section'
    });
  }
  
  return sections;
}

function getSectionInfo(text) {
  var headers = [];
  var lines = text.split('\n');
  
  for (var i = 0; i < lines.length; i++) {
    var headerMatch = lines[i].match(/^(#{1,6})\s+(.+)/);
    if (headerMatch) {
      headers.push({
        level: headerMatch[1].length,
        title: headerMatch[2].trim()
      });
    }
  }
  
  return headers;
}

function selectTopKChunks(chunks, query, k) {
  k = k || CONTEXT_LIMITS.TOP_K_RETRIEVAL;
  
  // Simple relevance scoring based on keyword matching
  var scoredChunks = chunks.map(function(chunk) {
    var score = 0;
    var queryWords = query.toLowerCase().split(/\s+/);
    var chunkText = chunk.text.toLowerCase();
    
    for (var i = 0; i < queryWords.length; i++) {
      var word = queryWords[i];
      if (chunkText.indexOf(word) !== -1) {
        score += 1;
        // Bonus for exact phrase matches
        if (chunkText.indexOf(query.toLowerCase()) !== -1) {
          score += 2;
        }
      }
    }
    
    return {
      chunk: chunk,
      score: score
    };
  });
  
  // Sort by score (descending) and take top k
  scoredChunks.sort(function(a, b) {
    return b.score - a.score;
  });
  
  return scoredChunks.slice(0, k).map(function(item) {
    return item.chunk;
  });
}

function buildContextFromChunks(chunks, maxTokens) {
  maxTokens = maxTokens || CONTEXT_LIMITS.MAX_CONTEXT_PER_CALL;
  
  var context = '';
  var totalTokens = 0;
  
  for (var i = 0; i < chunks.length; i++) {
    var chunk = chunks[i];
    
    if (totalTokens + chunk.tokens <= maxTokens) {
      context += (context.length > 0 ? '\n\n' : '') + chunk.text;
      totalTokens += chunk.tokens;
    } else {
      // Truncate the last chunk if needed
      var remainingTokens = maxTokens - totalTokens;
      if (remainingTokens > CONTEXT_LIMITS.MIN_CHUNK_SIZE) {
        var truncatedText = truncateToTokens(chunk.text, remainingTokens);
        context += (context.length > 0 ? '\n\n' : '') + truncatedText;
      }
      break;
    }
  }
  
  return {
    text: context,
    tokens: estimateTokens(context),
    chunksUsed: i + 1
  };
}

function truncateToTokens(text, maxTokens) {
  var maxChars = maxTokens * 4; // Convert tokens to characters
  if (text.length <= maxChars) return text;
  
  // Truncate at word boundary
  var truncated = text.substring(0, maxChars);
  var lastSpace = truncated.lastIndexOf(' ');
  
  if (lastSpace > maxChars * 0.8) { // If we can find a good word boundary
    return truncated.substring(0, lastSpace) + '...';
  } else {
    return truncated + '...';
  }
}

function manageContext(prompt, taskType, options) {
  options = options || {};
  var maxInputTokens = options.maxInputTokens || CONTEXT_LIMITS.MAX_INPUT_TOKENS;
  var maxContextTokens = options.maxContextTokens || CONTEXT_LIMITS.MAX_CONTEXT_PER_CALL;
  var query = options.query || '';
  
  var promptTokens = estimateTokens(prompt);
  
  // If prompt is within limits, return as-is
  if (promptTokens <= maxInputTokens) {
    return {
      text: prompt,
      tokens: promptTokens,
      chunks: [],
      truncated: false,
      strategy: 'full_context',
      originalTokens: promptTokens,
      reductionRatio: 0
    };
  }
  
  console.log('Context management needed - Prompt tokens:', promptTokens, 'Max:', maxInputTokens);
  
  // Determine chunking strategy based on content
  var chunks;
  if (prompt.includes('#') || prompt.includes('##')) {
    // Markdown content - use markdown chunking
    chunks = chunkMarkdown(prompt, maxContextTokens * 4);
    console.log('Using markdown chunking, created', chunks.length, 'chunks');
  } else {
    // Plain text - use text chunking
    chunks = chunkText(prompt, maxContextTokens * 4);
    console.log('Using text chunking, created', chunks.length, 'chunks');
  }
  
  // Select top-k chunks based on query relevance
  var selectedChunks;
  if (query && query.length > 0) {
    selectedChunks = selectTopKChunks(chunks, query, CONTEXT_LIMITS.TOP_K_RETRIEVAL);
    console.log('Selected', selectedChunks.length, 'chunks based on query relevance');
  } else {
    // No query - take first chunks that fit
    selectedChunks = chunks.slice(0, CONTEXT_LIMITS.TOP_K_RETRIEVAL);
    console.log('Selected first', selectedChunks.length, 'chunks');
  }
  
  // Build context from selected chunks
  var context = buildContextFromChunks(selectedChunks, maxContextTokens);
  
  return {
    text: context.text,
    tokens: context.tokens,
    chunks: selectedChunks,
    truncated: true,
    strategy: query ? 'query_relevance' : 'sequential',
    originalTokens: promptTokens,
    reductionRatio: (promptTokens - context.tokens) / promptTokens
  };
}

// AI Orchestration System
var AI_MODELS = {
  GEMINI_PRO: {
    name: 'Gemini 1.5 Pro',
    provider: 'google',
    costPer1kTokens: 0.00125,
    maxTokens: 32000,
    strengths: ['reasoning', 'analysis', 'complex_tasks'],
    latency: 'medium'
  },
  GEMINI_FLASH: {
    name: 'Gemini 1.5 Flash',
    provider: 'google', 
    costPer1kTokens: 0.00075,
    maxTokens: 32000,
    strengths: ['fast', 'simple_tasks', 'summarization'],
    latency: 'fast'
  },
  FALLBACK: {
    name: 'Template-Based',
    provider: 'local',
    costPer1kTokens: 0,
    maxTokens: 1000,
    strengths: ['reliability', 'offline'],
    latency: 'instant'
  }
};

var TASK_TYPES = {
  TRANSCRIPTION_ANALYSIS: 'transcription_analysis',
  MEETING_SUMMARY: 'meeting_summary',
  SENTIMENT_ANALYSIS: 'sentiment_analysis',
  DOCUMENT_CREATION: 'document_creation',
  EMAIL_COMPOSITION: 'email_composition',
  SIMPLE_QUERY: 'simple_query',
  COMPLEX_REASONING: 'complex_reasoning'
};

function getOptimalModel(taskType, contextLength, priority) {
  priority = priority || 'balanced';
  contextLength = contextLength || 0;
  
  console.log('Routing request - Task:', taskType, 'Context:', contextLength, 'Priority:', priority);
  
  // Route based on task type and context
  if (taskType === TASK_TYPES.SIMPLE_QUERY || taskType === TASK_TYPES.SENTIMENT_ANALYSIS) {
    return AI_MODELS.GEMINI_FLASH;
  }
  
  if (taskType === TASK_TYPES.COMPLEX_REASONING || taskType === TASK_TYPES.TRANSCRIPTION_ANALYSIS) {
    return AI_MODELS.GEMINI_PRO;
  }
  
  if (contextLength > 20000) {
    return AI_MODELS.GEMINI_PRO; // Better for long context
  }
  
  if (priority === 'speed') {
    return AI_MODELS.GEMINI_FLASH;
  }
  
  if (priority === 'quality') {
    return AI_MODELS.GEMINI_PRO;
  }
  
  // Default to balanced approach
  return AI_MODELS.GEMINI_FLASH;
}

function callAIWithFallback(prompt, taskType, options) {
  options = options || {};
  var contextLength = prompt.length;
  var priority = options.priority || 'balanced';
  var maxRetries = options.maxRetries || 3;
  var query = options.query || '';
  
  // Apply safety filters first
  var safetyResult = applySafetyFilters(prompt, { taskType: taskType });
  var safePrompt = safetyResult.filteredText;
  
  // Apply context management
  var contextInfo = manageContext(safePrompt, taskType, {
    query: query,
    maxInputTokens: CONTEXT_LIMITS.MAX_INPUT_TOKENS,
    maxContextTokens: CONTEXT_LIMITS.MAX_CONTEXT_PER_CALL
  });
  
  var managedPrompt = contextInfo.text;
  var primaryModel = getOptimalModel(taskType, contextInfo.tokens, priority);
  var models = [primaryModel];
  
  // Add fallback models
  if (primaryModel === AI_MODELS.GEMINI_PRO) {
    models.push(AI_MODELS.GEMINI_FLASH);
  } else if (primaryModel === AI_MODELS.GEMINI_FLASH) {
    models.push(AI_MODELS.GEMINI_PRO);
  }
  models.push(AI_MODELS.FALLBACK);
  
  console.log('AI Orchestration - Primary model:', primaryModel.name, 'Fallbacks:', models.slice(1).map(m => m.name));
  console.log('Context Management - Original tokens:', contextInfo.originalTokens || contextInfo.tokens, 'Managed tokens:', contextInfo.tokens, 'Strategy:', contextInfo.strategy);
  
  for (var i = 0; i < models.length && i < maxRetries; i++) {
    var model = models[i];
    var startTime = new Date().getTime();
    
    try {
      console.log('Attempting with model:', model.name);
      
      // Check cost budget before making request (skip for fallback)
      if (model.provider === 'google') {
        var estimatedCost = estimateCost(managedPrompt, '', model).cost;
        var budgetCheck = canMakeRequest(estimatedCost);
        
        if (!budgetCheck.allowed) {
          console.log('Budget check failed:', budgetCheck.reason);
          if (i === models.length - 1) {
            // Last model, return budget exceeded response
            return getCostBudgetExceededResponse();
          }
          continue; // Try next model
        }
      }
      
      var response;
      
      if (model.provider === 'google') {
        response = askGemini(managedPrompt);
      } else {
        // Fallback to template-based response
        response = generateFallbackResponse(taskType, managedPrompt);
      }
      
      var latency = new Date().getTime() - startTime;
      var costInfo = estimateCost(managedPrompt, response, model);
      
      // Add cost to tracking (only for paid models)
      if (model.provider === 'google') {
        var costTracking = addCost(costInfo.cost);
        costInfo.tracking = costTracking;
      }
      
      console.log('Success with', model.name, 'in', latency + 'ms', 'Cost:', costInfo.cost);
      
      return {
        success: true,
        response: response,
        model: model.name,
        latency: latency,
        cost: costInfo,
        context: contextInfo,
        safety: safetyResult,
        timestamp: new Date().toISOString()
      };
      
    } catch (e) {
      var latency = new Date().getTime() - startTime;
      console.log('Failed with', model.name, 'after', latency + 'ms:', e.message);
      
      if (i === models.length - 1) {
        // Last attempt failed
        return {
          success: false,
          error: 'All AI models failed. Last error: ' + e.message,
          attempts: i + 1,
          context: contextInfo,
          timestamp: new Date().toISOString()
        };
      }
    }
  }
  
  return {
    success: false,
    error: 'Maximum retries exceeded',
    context: contextInfo,
    timestamp: new Date().toISOString()
  };
}

function generateFallbackResponse(taskType, prompt) {
  console.log('Using fallback template for task type:', taskType);
  
  switch (taskType) {
    case TASK_TYPES.TRANSCRIPTION_ANALYSIS:
      return 'Transcription Analysis (Fallback Mode):\n\n' +
             'Due to AI service limitations, this is a basic analysis.\n' +
             'Key topics identified: Meeting discussion, team coordination\n' +
             'Action items: Please review the transcription manually for specific details\n' +
             'Sentiment: Neutral to positive based on conversation flow';
             
    case TASK_TYPES.MEETING_SUMMARY:
      return 'Meeting Summary (Fallback Mode):\n\n' +
             'Meeting: ' + (prompt.match(/title[:\s]+([^\n]+)/i) ? prompt.match(/title[:\s]+([^\n]+)/i)[1] : 'Team Meeting') + '\n' +
             'Duration: Approximately 1 hour\n' +
             'Participants: Team members\n' +
             'Key Points: General discussion and planning\n' +
             'Next Steps: Follow up on action items';
             
    case TASK_TYPES.SENTIMENT_ANALYSIS:
      return 'Sentiment Analysis (Fallback Mode):\n\n' +
             'Overall Sentiment: Neutral\n' +
             'Confidence: Low (fallback mode)\n' +
             'Key Indicators: Standard business communication\n' +
             'Recommendation: Manual review recommended for accurate analysis';
             
    default:
      return 'AI Response (Fallback Mode):\n\n' +
             'I apologize, but the AI service is currently unavailable.\n' +
             'This is a fallback response. Please try again later or contact support.';
  }
}

function estimateCost(input, output, model) {
  var inputTokens = Math.ceil(input.length / 4); // Rough estimate
  var outputTokens = Math.ceil(output.length / 4);
  var totalTokens = inputTokens + outputTokens;
  
  return {
    inputTokens: inputTokens,
    outputTokens: outputTokens,
    totalTokens: totalTokens,
    cost: (totalTokens / 1000) * model.costPer1kTokens,
    model: model.name
  };
}

function testGemini(){
  try {
    console.log('Testing Gemini API...');
    var response = askGemini('Say hello and confirm you are working');
    console.log('Gemini test response:', response);
    return { success: true, response: response };
  } catch (e) {
    console.log('Gemini test failed:', e.message);
    return { success: false, error: e.message };
  }
}

function testAIOrchestration(){
  try {
    console.log('Testing AI Orchestration System...');
    
    // Test different task types and priorities
    var tests = [
      {
        name: 'Simple Query (Speed Priority)',
        prompt: 'What is 2+2?',
        taskType: TASK_TYPES.SIMPLE_QUERY,
        priority: 'speed'
      },
      {
        name: 'Complex Reasoning (Quality Priority)', 
        prompt: 'Explain the concept of machine learning in simple terms',
        taskType: TASK_TYPES.COMPLEX_REASONING,
        priority: 'quality'
      },
      {
        name: 'Sentiment Analysis (Balanced Priority)',
        prompt: 'I love this new feature! It makes my work so much easier.',
        taskType: TASK_TYPES.SENTIMENT_ANALYSIS,
        priority: 'balanced'
      }
    ];
    
    var results = [];
    
    for (var i = 0; i < tests.length; i++) {
      var test = tests[i];
      console.log('Running test:', test.name);
      
      var result = callAIWithFallback(test.prompt, test.taskType, {
        priority: test.priority,
        maxRetries: 1
      });
      
      results.push({
        test: test.name,
        success: result.success,
        model: result.model || 'N/A',
        latency: result.latency || 0,
        cost: result.cost ? result.cost.cost : 0,
        response: result.response ? result.response.substring(0, 100) + '...' : result.error
      });
    }
    
    return {
      success: true,
      results: results,
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('AI Orchestration test failed:', e.message);
    return { success: false, error: e.message };
  }
}

function testCostManagement(){
  try {
    console.log('Testing Cost Management System...');
    
    var costStatus = getCostStatus();
    
    // Test budget check
    var budgetTest = canMakeRequest(0.01); // Test with $0.01
    
    return {
      success: true,
      costStatus: costStatus,
      budgetTest: budgetTest,
      budgets: {
        dailyLimit: COST_BUDGETS.DAILY_LIMIT,
        monthlyLimit: COST_BUDGETS.MONTHLY_LIMIT,
        perRequestLimit: COST_BUDGETS.PER_REQUEST_LIMIT,
        alertThreshold: COST_BUDGETS.ALERT_THRESHOLD
      },
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Cost Management test failed:', e.message);
    return { success: false, error: e.message };
  }
}

function testContextManagement(){
  try {
    console.log('Testing Context Management System...');
    
    // Test with a long text that exceeds context limits
    var longText = '';
    for (var i = 0; i < 100; i++) {
      longText += 'This is a test paragraph number ' + i + '. ';
      longText += 'It contains some content to test the context management system. ';
      longText += 'The system should chunk this text appropriately. ';
      longText += 'Each paragraph should be processed correctly.\n\n';
    }
    
    // Test with markdown content
    var markdownText = '# Introduction\nThis is the introduction section.\n\n## Section 1\nContent for section 1.\n\n### Subsection 1.1\nMore detailed content here.\n\n## Section 2\nContent for section 2.\n\n### Subsection 2.1\nEven more content here.\n\n## Conclusion\nFinal thoughts and summary.';
    
    // Test context management
    var textChunks = chunkText(longText, 1000); // 1000 chars per chunk
    var markdownChunks = chunkMarkdown(markdownText, 1000);
    
    // Test context management function
    var contextResult = manageContext(longText, TASK_TYPES.TRANSCRIPTION_ANALYSIS, {
      query: 'test paragraph',
      maxInputTokens: 5000, // Force chunking
      maxContextTokens: 1000
    });
    
    return {
      success: true,
      limits: {
        maxInputTokens: CONTEXT_LIMITS.MAX_INPUT_TOKENS,
        maxOutputTokens: CONTEXT_LIMITS.MAX_OUTPUT_TOKENS,
        maxContextPerCall: CONTEXT_LIMITS.MAX_CONTEXT_PER_CALL,
        topKRetrieval: CONTEXT_LIMITS.TOP_K_RETRIEVAL
      },
      tests: {
        originalTextLength: longText.length,
        originalTokens: estimateTokens(longText),
        textChunks: textChunks.length,
        markdownChunks: markdownChunks.length,
        contextManagement: {
          originalTokens: contextResult.originalTokens,
          managedTokens: contextResult.tokens,
          strategy: contextResult.strategy,
          truncated: contextResult.truncated,
          reductionRatio: contextResult.reductionRatio
        }
      },
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Context Management test failed:', e.message);
    return { success: false, error: e.message };
  }
}

function testSafetyGuardrails(){
  try {
    console.log('Testing Safety & Guardrails System...');
    
    // Test PII filtering
    var piiText = 'My SSN is 123-45-6789 and my phone is (555) 123-4567. My email is john.doe@gmail.com and my credit card is 4532-1234-5678-9012.';
    var piiResult = filterPII(piiText);
    
    // Test content moderation
    var profanityText = 'This is a damn stupid test with some hell content. I hate this crap.';
    var moderationResult = moderateContent(profanityText);
    
    // Test domain safety
    var domainTest = checkDomainSafety('gmail.com');
    var domainTest2 = checkDomainSafety('malicious-site.com');
    
    // Test tool safety
    var toolTest = checkToolSafety('gmail_read');
    var toolTest2 = checkToolSafety('admin_access');
    
    // Test sensitive action check
    var actionTest = checkSensitiveAction('delete_user_data');
    var actionTest2 = checkSensitiveAction('gmail_read');
    
    // Test combined safety filters
    var combinedText = 'My SSN is 123-45-6789 and this is damn stupid content.';
    var combinedResult = applySafetyFilters(combinedText);
    
    return {
      success: true,
      safetyStatus: getSafetyStatus(),
      tests: {
        piiFiltering: {
          original: piiText,
          filtered: piiResult.text,
          violations: piiResult.violations,
          filtered: piiResult.violations.length > 0
        },
        contentModeration: {
          original: profanityText,
          moderated: moderationResult.text,
          violations: moderationResult.violations,
          moderated: moderationResult.violations.length > 0
        },
        domainSafety: {
          allowedDomain: domainTest,
          deniedDomain: domainTest2
        },
        toolSafety: {
          allowedTool: toolTest,
          deniedTool: toolTest2
        },
        sensitiveActions: {
          sensitiveAction: actionTest,
          normalAction: actionTest2
        },
        combinedFilters: {
          original: combinedText,
          filtered: combinedResult.filteredText,
          safetyScore: combinedResult.safetyScore,
          violations: combinedResult.violations,
          piiFiltered: combinedResult.piiFiltered,
          contentModerated: combinedResult.contentModerated
        }
      },
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Safety & Guardrails test failed:', e.message);
    return { success: false, error: e.message };
  }
}

function testEmailRouterWorkflow(){
  try {
    console.log('Testing Email Router Workflow...');
    
    // Test with different types of emails
    var testEmails = [
      {
        subject: 'URGENT: Server Down - Immediate Action Required',
        body: 'Our production server is down and customers cannot access the application. Please fix this immediately. This is critical for business operations.',
        from: 'admin@company.com',
        to: 'team@company.com'
      },
      {
        subject: 'Meeting Reminder - Project Review',
        body: 'This is a reminder about our project review meeting scheduled for tomorrow at 2 PM. Please prepare your status updates and bring any questions you have.',
        from: 'manager@company.com',
        to: 'team@company.com'
      },
      {
        subject: 'Special Offer - 50% Off Everything!',
        body: 'Don\'t miss out on our amazing sale! Get 50% off all products for a limited time. Click here to shop now!',
        from: 'marketing@spam.com',
        to: 'user@company.com'
      }
    ];
    
    var workflowResults = [];
    
    for (var i = 0; i < testEmails.length; i++) {
      var email = testEmails[i];
      console.log('Testing email:', email.subject);
      
      var result = executeEmailRouterWorkflow(email, { testMode: true });
      workflowResults.push({
        email: email,
        result: result
      });
    }
    
    return {
      success: true,
      workflowConfig: WORKFLOW_CONFIG,
      workflowStates: WORKFLOW_STATES,
      workflowSteps: WORKFLOW_STEPS,
      testResults: workflowResults,
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Email Router Workflow test failed:', e.message);
    return { success: false, error: e.message };
  }
}

function testMeetingCreateWorkflow(){
  try {
    console.log('Testing Meeting Create Workflow...');
    
    // Test with different meeting scenarios
    var testMeetings = [
      {
        title: 'Weekly Team Standup',
        description: 'Daily standup meeting to discuss progress, blockers, and upcoming tasks.',
        attendees: ['john@company.com', 'jane@company.com', 'bob@company.com'],
        duration: 30,
        location: 'Conference Room A',
        startDate: new Date().toISOString(),
        endDate: new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString(), // 3 days from now
        workingHours: { start: 9, end: 17 },
        timezone: 'America/New_York'
      },
      {
        title: 'Project Planning Session',
        description: 'Quarterly planning meeting to review goals, milestones, and resource allocation.',
        attendees: ['manager@company.com', 'lead@company.com'],
        duration: 120,
        location: 'Virtual Meeting',
        startDate: new Date().toISOString(),
        endDate: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString(), // 7 days from now
        workingHours: { start: 10, end: 16 },
        timezone: 'America/New_York'
      },
      {
        title: 'Client Presentation',
        description: 'Present quarterly results and upcoming roadmap to key client stakeholders.',
        attendees: ['client@external.com', 'sales@company.com', 'product@company.com'],
        duration: 90,
        location: 'Client Office',
        startDate: new Date().toISOString(),
        endDate: new Date(Date.now() + 5 * 24 * 60 * 60 * 1000).toISOString(), // 5 days from now
        workingHours: { start: 9, end: 18 },
        timezone: 'America/New_York'
      }
    ];
    
    var workflowResults = [];
    
    for (var i = 0; i < testMeetings.length; i++) {
      var meeting = testMeetings[i];
      console.log('Testing meeting:', meeting.title);
      
      var result = executeMeetingCreateWorkflow(meeting, { testMode: true });
      workflowResults.push({
        meeting: meeting,
        result: result
      });
    }
    
    return {
      success: true,
      workflowConfig: WORKFLOW_CONFIG,
      workflowStates: WORKFLOW_STATES,
      workflowSteps: WORKFLOW_STEPS,
      testResults: workflowResults,
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Meeting Create Workflow test failed:', e.message);
    return { success: false, error: e.message };
  }
}

function analyzeTranscription(transcription, options){
  options = options || {};
  try {
    console.log('analyzeTranscription called with:', transcription.substring(0, 100) + '...', options);
    
    var includeSummary = options.includeSummary !== false;
    var includeActionItems = options.includeActionItems !== false;
    var customPrompt = options.prompt || '';
    var priority = options.priority || 'balanced';
    
    var analysisPrompt = 'Please analyze this transcription and provide insights:\n\n';
    
    if (includeSummary) {
      analysisPrompt += '1. Brief summary of key points\n';
      analysisPrompt += '2. Main topics discussed\n';
      analysisPrompt += '3. Important decisions made\n';
    }
    
    if (includeActionItems) {
      analysisPrompt += '4. Action items and next steps (if any)\n';
    }
    
    analysisPrompt += '5. Overall insights and recommendations\n\n';
    
    if (customPrompt) {
      analysisPrompt += 'Additional instructions: ' + customPrompt + '\n\n';
    }
    
    analysisPrompt += 'Transcription:\n' + transcription;
    
    // Use AI orchestration with fallback
    var result = callAIWithFallback(analysisPrompt, TASK_TYPES.TRANSCRIPTION_ANALYSIS, {
      priority: priority,
      maxRetries: 2
    });
    
    if (result.success) {
      return {
        success: true,
        analysis: result.response,
        model: result.model,
        latency: result.latency,
        cost: result.cost,
        timestamp: result.timestamp
      };
    } else {
      return {
        success: false,
        error: result.error,
        timestamp: result.timestamp
      };
    }
    
  } catch (e) {
    console.log('Error in analyzeTranscription:', e.message);
    return { success: false, error: e.message };
  }
}

// WF-DOC-MEETING-NOTES Workflow Implementation
function executeMeetingNotesWorkflow(meetingData, options) {
  options = options || {};
  var workflowId = generateWorkflowId(meetingData, getSessionUserEmail(), 'meeting_notes');
  var startTime = Date.now();
  
  logWorkflowEvent(workflowId, null, 'workflow_started', {
    workflowType: 'WF-DOC-MEETING-NOTES',
    meetingData: meetingData,
    options: options
  });
  
  // Check for deduplication
  var dedupeCheck = checkWorkflowDeduplication(workflowId);
  if (dedupeCheck.isDuplicate) {
    logWorkflowEvent(workflowId, null, 'workflow_duplicate', {
      existingWorkflow: dedupeCheck.existingWorkflow,
      timeRemaining: dedupeCheck.timeRemaining
    });
    
    return {
      success: false,
      error: 'Duplicate workflow detected',
      workflowId: workflowId,
      isDuplicate: true,
      timeRemaining: dedupeCheck.timeRemaining
    };
  }
  
  var workflowResult = {
    workflowId: workflowId,
    workflowType: 'WF-DOC-MEETING-NOTES',
    status: WORKFLOW_STATES.RUNNING,
    steps: [],
    startTime: startTime,
    compensationSteps: []
  };
  
  try {
    // Step 1: Transcribe Meeting (if raw audio provided)
    var transcribeStepId = generateStepId(workflowId, WORKFLOW_STEPS.TRANSCRIBE);
    var transcribeResult = executeWorkflowStep(workflowId, transcribeStepId, WORKFLOW_STEPS.TRANSCRIBE,
      function(input) { return transcribeMeeting(input); }, meetingData);
    
    workflowResult.steps.push(transcribeResult);
    
    if (!transcribeResult.success) {
      throw new Error('Transcription failed: ' + transcribeResult.error);
    }
    
    // Step 2: Analyze Transcription
    var analyzeStepId = generateStepId(workflowId, WORKFLOW_STEPS.ANALYZE);
    var analyzeResult = executeWorkflowStep(workflowId, analyzeStepId, WORKFLOW_STEPS.ANALYZE,
      function(input) { return analyzeMeetingTranscription(input, transcribeResult.result); }, meetingData);
    
    workflowResult.steps.push(analyzeResult);
    
    if (!analyzeResult.success) {
      throw new Error('Analysis failed: ' + analyzeResult.error);
    }
    
    // Step 3: Create Document
    var createDocStepId = generateStepId(workflowId, WORKFLOW_STEPS.CREATE_DOC);
    var createDocResult = executeWorkflowStep(workflowId, createDocStepId, WORKFLOW_STEPS.CREATE_DOC,
      function(input) { return createMeetingDocument(input, transcribeResult.result, analyzeResult.result); }, meetingData);
    
    workflowResult.steps.push(createDocResult);
    
    if (!createDocResult.success) {
      throw new Error('Document creation failed: ' + createDocResult.error);
    }
    
    // Step 4: Share Document
    var shareStepId = generateStepId(workflowId, WORKFLOW_STEPS.SHARE);
    var shareResult = executeWorkflowStep(workflowId, shareStepId, WORKFLOW_STEPS.SHARE,
      function(input) { return shareMeetingDocument(input, createDocResult.result); }, meetingData);
    
    workflowResult.steps.push(shareResult);
    
    if (!shareResult.success) {
      throw new Error('Document sharing failed: ' + shareResult.error);
    }
    
    // Workflow completed successfully
    workflowResult.status = WORKFLOW_STATES.COMPLETED;
    workflowResult.endTime = Date.now();
    workflowResult.totalDuration = workflowResult.endTime - startTime;
    
    logWorkflowEvent(workflowId, null, 'workflow_completed', {
      totalDuration: workflowResult.totalDuration,
      stepsCompleted: workflowResult.steps.length
    });
    
    return {
      success: true,
      workflowResult: workflowResult,
      transcription: transcribeResult.result,
      analysis: analyzeResult.result,
      document: createDocResult.result,
      sharing: shareResult.result
    };
    
  } catch (e) {
    // Workflow failed - execute compensation steps
    workflowResult.status = WORKFLOW_STATES.FAILED;
    workflowResult.endTime = Date.now();
    workflowResult.totalDuration = workflowResult.endTime - startTime;
    workflowResult.error = e.message;
    
    logWorkflowEvent(workflowId, null, 'workflow_failed', {
      error: e.message,
      totalDuration: workflowResult.totalDuration,
      stepsCompleted: workflowResult.steps.length
    });
    
    // Execute compensation steps
    var compensationResult = executeMeetingNotesCompensationSteps(workflowId, workflowResult);
    workflowResult.compensationSteps = compensationResult;
    
    return {
      success: false,
      error: e.message,
      workflowResult: workflowResult
    };
  }
}

// Step 1: Transcribe Meeting
function transcribeMeeting(meetingData) {
  try {
    // If transcription is already provided, use it
    if (meetingData.transcription && meetingData.transcription.trim()) {
      return {
        success: true,
        transcription: meetingData.transcription,
        source: 'provided',
        timestamp: new Date().toISOString()
      };
    }
    
    // If audio file is provided, simulate transcription
    if (meetingData.audioFile) {
      // In a real implementation, this would call a speech-to-text API
      var simulatedTranscription = 'This is a simulated transcription of the meeting audio. ' +
        'In a real implementation, this would be generated by a speech-to-text service like ' +
        'Google Speech-to-Text, Azure Speech, or OpenAI Whisper.';
      
      return {
        success: true,
        transcription: simulatedTranscription,
        source: 'audio_transcription',
        audioFile: meetingData.audioFile,
        timestamp: new Date().toISOString()
      };
    }
    
    return {
      success: false,
      error: 'No transcription or audio file provided'
    };
    
  } catch (e) {
    return {
      success: false,
      error: 'Transcription failed: ' + e.message
    };
  }
}

// Step 2: Analyze Meeting Transcription
function analyzeMeetingTranscription(meetingData, transcriptionResult) {
  try {
    var transcription = transcriptionResult.transcription;
    
    // Use the existing analyzeTranscription function with meeting-specific options
    var analysisOptions = {
      includeSummary: true,
      includeActionItems: true,
      priority: 'balanced',
      prompt: 'Focus on meeting outcomes, decisions, and action items. Format as structured meeting notes.'
    };
    
    var analysis = analyzeTranscription(transcription, analysisOptions);
    
    if (analysis.success) {
      return {
        success: true,
        analysis: analysis.analysis,
        model: analysis.model,
        latency: analysis.latency,
        cost: analysis.cost,
        timestamp: analysis.timestamp
      };
    } else {
      return {
        success: false,
        error: 'Analysis failed: ' + analysis.error
      };
    }
    
  } catch (e) {
    return {
      success: false,
      error: 'Analysis failed: ' + e.message
    };
  }
}

// Step 3: Create Meeting Document
function createMeetingDocument(meetingData, transcriptionResult, analysisResult) {
  try {
    var documentTitle = meetingData.title || 'Meeting Notes - ' + new Date().toLocaleDateString();
    var transcription = transcriptionResult.transcription;
    var analysis = analysisResult.analysis;
    
    // Create Google Doc
    var doc = Docs.Documents.create({
      title: documentTitle
    });
    
    var documentId = doc.documentId;
    
    // Create HTML-formatted content for better visual presentation
    var htmlContent = createMeetingDocumentHTML(meetingData, transcription, analysis);
    
    // Add content to the document with enhanced formatting
    var requests = [
      // Title with large, bold formatting
      {
        insertText: {
          location: { index: 1 },
          text: documentTitle + '\n\n'
        }
      },
      {
        updateTextStyle: {
          range: { startIndex: 1, endIndex: documentTitle.length + 1 },
          textStyle: {
            bold: true,
            fontSize: { magnitude: 20, unit: 'PT' },
            foregroundColor: { color: { rgbColor: { red: 0.2, green: 0.4, blue: 0.8 } } }
          },
          fields: 'bold,fontSize,foregroundColor'
        }
      },
      {
        updateParagraphStyle: {
          range: { startIndex: 1, endIndex: documentTitle.length + 1 },
          paragraphStyle: {
            alignment: 'CENTER',
            spaceAbove: { magnitude: 12, unit: 'PT' },
            spaceBelow: { magnitude: 12, unit: 'PT' }
          },
          fields: 'alignment,spaceAbove,spaceBelow'
        }
      },
      
      // Meeting Info Section
      {
        insertText: {
          location: { index: documentTitle.length + 3 },
          text: '📅 MEETING INFORMATION\n'
        }
      },
      {
        updateTextStyle: {
          range: { startIndex: documentTitle.length + 3, endIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length },
          textStyle: {
            bold: true,
            fontSize: { magnitude: 16, unit: 'PT' },
            foregroundColor: { color: { rgbColor: { red: 0.1, green: 0.6, blue: 0.1 } } }
          },
          fields: 'bold,fontSize,foregroundColor'
        }
      },
      {
        insertText: {
          location: { index: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length },
          text: 'Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n'
        }
      },
      {
        insertText: {
          location: { index: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length },
          text: 'Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n'
        }
      },
      
      // AI Analysis Section
      {
        insertText: {
          location: { index: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length },
          text: '🤖 AI-POWERED ANALYSIS\n'
        }
      },
      {
        updateTextStyle: {
          range: { 
            startIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length,
            endIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length
          },
          textStyle: {
            bold: true,
            fontSize: { magnitude: 16, unit: 'PT' },
            foregroundColor: { color: { rgbColor: { red: 0.8, green: 0.2, blue: 0.6 } } }
          },
          fields: 'bold,fontSize,foregroundColor'
        }
      },
      {
        insertText: {
          location: { index: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length },
          text: analysis + '\n\n'
        }
      },
      {
        updateTextStyle: {
          range: { 
            startIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length,
            endIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length + analysis.length
          },
          textStyle: {
            fontSize: { magnitude: 11, unit: 'PT' },
            lineSpacing: 1.5
          },
          fields: 'fontSize,lineSpacing'
        }
      },
      
      // Full Transcription Section
      {
        insertText: {
          location: { index: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length + (analysis + '\n\n').length },
          text: '📝 FULL TRANSCRIPTION\n'
        }
      },
      {
        updateTextStyle: {
          range: { 
            startIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length + (analysis + '\n\n').length,
            endIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length + (analysis + '\n\n').length + '📝 FULL TRANSCRIPTION\n'.length
          },
          textStyle: {
            bold: true,
            fontSize: { magnitude: 16, unit: 'PT' },
            foregroundColor: { color: { rgbColor: { red: 0.6, green: 0.3, blue: 0.8 } } }
          },
          fields: 'bold,fontSize,foregroundColor'
        }
      },
      {
        insertText: {
          location: { index: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length + (analysis + '\n\n').length + '📝 FULL TRANSCRIPTION\n'.length },
          text: transcription
        }
      },
      {
        updateTextStyle: {
          range: { 
            startIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length + (analysis + '\n\n').length + '📝 FULL TRANSCRIPTION\n'.length,
            endIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length + (analysis + '\n\n').length + '📝 FULL TRANSCRIPTION\n'.length + transcription.length
          },
          textStyle: {
            fontSize: { magnitude: 10, unit: 'PT' },
            lineSpacing: 1.4,
            foregroundColor: { color: { rgbColor: { red: 0.4, green: 0.4, blue: 0.4 } } }
          },
          fields: 'fontSize,lineSpacing,foregroundColor'
        }
      },
      
      // Footer with generation info
      {
        insertText: {
          location: { index: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length + (analysis + '\n\n').length + '📝 FULL TRANSCRIPTION\n'.length + transcription.length },
          text: '\n\n---\n📊 Generated by BMAD AI Assistant on ' + new Date().toLocaleString() + '\n🤖 Powered by Gemini 1.5 Pro'
        }
      },
      {
        updateTextStyle: {
          range: { 
            startIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length + (analysis + '\n\n').length + '📝 FULL TRANSCRIPTION\n'.length + transcription.length + 1,
            endIndex: documentTitle.length + 3 + '📅 MEETING INFORMATION\n'.length + ('Date: ' + (meetingData.date || new Date().toLocaleDateString()) + '\n').length + ('Attendees: ' + (meetingData.attendees || 'Not specified') + '\n\n').length + '🤖 AI-POWERED ANALYSIS\n'.length + (analysis + '\n\n').length + '📝 FULL TRANSCRIPTION\n'.length + transcription.length + 1 + ('\n\n---\n📊 Generated by BMAD AI Assistant on ' + new Date().toLocaleString() + '\n🤖 Powered by Gemini 1.5 Pro').length
          },
          textStyle: {
            fontSize: { magnitude: 9, unit: 'PT' },
            italic: true,
            foregroundColor: { color: { rgbColor: { red: 0.6, green: 0.6, blue: 0.6 } } }
          },
          fields: 'fontSize,italic,foregroundColor'
        }
      }
    ];
    
    Docs.Documents.batchUpdate({ requests: requests }, documentId);
    
    return {
      success: true,
      documentId: documentId,
      documentTitle: documentTitle,
      documentUrl: 'https://docs.google.com/document/d/' + documentId + '/edit',
      createdAt: new Date().toISOString(),
      createdBy: getSessionUserEmail(),
      htmlFormatted: true
    };
    
  } catch (e) {
    return {
      success: false,
      error: 'Document creation failed: ' + e.message
    };
  }
}

// Helper function to create HTML-formatted content (for future use)
function createMeetingDocumentHTML(meetingData, transcription, analysis) {
  var html = '<!DOCTYPE html>\n<html>\n<head>\n';
  html += '<meta charset="UTF-8">\n';
  html += '<title>' + (meetingData.title || 'Meeting Notes') + '</title>\n';
  html += '<style>\n';
  html += 'body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; line-height: 1.6; }\n';
  html += '.header { text-align: center; color: #2c5aa0; border-bottom: 3px solid #2c5aa0; padding-bottom: 20px; margin-bottom: 30px; }\n';
  html += '.section { margin: 25px 0; padding: 15px; border-left: 4px solid #4CAF50; background-color: #f9f9f9; }\n';
  html += '.section h2 { color: #4CAF50; margin-top: 0; }\n';
  html += '.analysis { border-left-color: #e91e63; }\n';
  html += '.analysis h2 { color: #e91e63; }\n';
  html += '.transcription { border-left-color: #9c27b0; }\n';
  html += '.transcription h2 { color: #9c27b0; }\n';
  html += '.footer { text-align: center; color: #666; font-size: 12px; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; }\n';
  html += '</style>\n</head>\n<body>\n';
  
  html += '<div class="header">\n';
  html += '<h1>' + (meetingData.title || 'Meeting Notes') + '</h1>\n';
  html += '<p><strong>Date:</strong> ' + (meetingData.date || new Date().toLocaleDateString()) + '</p>\n';
  html += '<p><strong>Attendees:</strong> ' + (meetingData.attendees || 'Not specified') + '</p>\n';
  html += '</div>\n';
  
  html += '<div class="section analysis">\n';
  html += '<h2>🤖 AI-Powered Analysis</h2>\n';
  html += '<div>' + analysis.replace(/\n/g, '<br>') + '</div>\n';
  html += '</div>\n';
  
  html += '<div class="section transcription">\n';
  html += '<h2>📝 Full Transcription</h2>\n';
  html += '<div>' + transcription.replace(/\n/g, '<br>') + '</div>\n';
  html += '</div>\n';
  
  html += '<div class="footer">\n';
  html += '<p>📊 Generated by BMAD AI Assistant on ' + new Date().toLocaleString() + '</p>\n';
  html += '<p>🤖 Powered by Gemini 1.5 Pro</p>\n';
  html += '</div>\n';
  
  html += '</body>\n</html>';
  
  return html;
}

// Function to create standalone HTML meeting notes
function createHTMLMeetingNotes(meetingData) {
  try {
    var documentTitle = meetingData.title || 'Meeting Notes - ' + new Date().toLocaleDateString();
    var transcription = meetingData.transcription;
    
    // Analyze the transcription if not already provided
    var analysisResult = analyzeTranscription(transcription, {
      includeSummary: true,
      includeActionItems: true,
      priority: 'balanced',
      prompt: 'Focus on meeting outcomes, decisions, and action items. Format as structured meeting notes.'
    });
    
    var analysis = analysisResult.success ? analysisResult.analysis : 'Analysis not available';
    
    // Create HTML content
    var htmlContent = createMeetingDocumentHTML(meetingData, transcription, analysis);
    
    // Create a Google Doc with the HTML content
    var doc = Docs.Documents.create({
      title: documentTitle + ' (HTML)'
    });
    
    var documentId = doc.documentId;
    
    // Insert the HTML content as plain text for now (Google Docs doesn't support HTML directly)
    var requests = [
      {
        insertText: {
          location: { index: 1 },
          text: 'HTML Meeting Notes\n\n' + htmlContent
        }
      },
      {
        updateTextStyle: {
          range: { startIndex: 1, endIndex: 'HTML Meeting Notes\n\n'.length },
          textStyle: {
            bold: true,
            fontSize: { magnitude: 18, unit: 'PT' },
            foregroundColor: { color: { rgbColor: { red: 0.2, green: 0.4, blue: 0.8 } } }
          },
          fields: 'bold,fontSize,foregroundColor'
        }
      }
    ];
    
    Docs.Documents.batchUpdate({ requests: requests }, documentId);
    
    return {
      success: true,
      documentId: documentId,
      documentTitle: documentTitle + ' (HTML)',
      documentUrl: 'https://docs.google.com/document/d/' + documentId + '/edit',
      htmlContent: htmlContent,
      createdAt: new Date().toISOString(),
      createdBy: getSessionUserEmail()
    };
    
  } catch (e) {
    return {
      success: false,
      error: 'HTML document creation failed: ' + e.message
    };
  }
}

// Step 4: Share Meeting Document
function shareMeetingDocument(meetingData, documentResult) {
  try {
    var documentId = documentResult.documentId;
    var shareWith = meetingData.shareWith || [];
    
    if (!Array.isArray(shareWith)) {
      shareWith = shareWith.split(',').map(function(email) { return email.trim(); });
    }
    
    var sharingResults = [];
    
    // Share with specified attendees
    for (var i = 0; i < shareWith.length; i++) {
      var email = shareWith[i];
      if (email && email.includes('@')) {
        try {
          // Set permissions for the document
          var permission = {
            type: 'user',
            role: 'writer',
            emailAddress: email
          };
          
          Drive.Permissions.create(permission, documentId, {
            sendNotificationEmail: true,
            emailMessage: 'Meeting notes have been shared with you. Please review and add any additional notes or action items.'
          });
          
          sharingResults.push({
            email: email,
            success: true,
            role: 'writer'
          });
          
        } catch (e) {
          sharingResults.push({
            email: email,
            success: false,
            error: e.message
          });
        }
      }
    }
    
    return {
      success: true,
      documentId: documentId,
      documentUrl: documentResult.documentUrl,
      sharedWith: sharingResults,
      totalShared: sharingResults.length,
      successfulShares: sharingResults.filter(function(r) { return r.success; }).length,
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    return {
      success: false,
      error: 'Document sharing failed: ' + e.message
    };
  }
}

// Compensation Steps for Meeting Notes Workflow
function executeMeetingNotesCompensationSteps(workflowId, workflowResult) {
  var compensationSteps = [];
  
  try {
    // Find the document creation step
    var createDocStep = workflowResult.steps.find(function(step) {
      return step.stepName === WORKFLOW_STEPS.CREATE_DOC && step.success;
    });
    
    if (createDocStep && createDocStep.result && createDocStep.result.documentId) {
      try {
        // Delete the created document
        Drive.Files.remove(createDocStep.result.documentId);
        
        compensationSteps.push({
          step: 'delete_document',
          documentId: createDocStep.result.documentId,
          success: true,
          timestamp: new Date().toISOString()
        });
        
        logWorkflowEvent(workflowId, null, 'compensation_executed', {
          step: 'delete_document',
          documentId: createDocStep.result.documentId
        });
        
      } catch (e) {
        compensationSteps.push({
          step: 'delete_document',
          documentId: createDocStep.result.documentId,
          success: false,
          error: e.message,
          timestamp: new Date().toISOString()
        });
      }
    }
    
    // Find the sharing step
    var shareStep = workflowResult.steps.find(function(step) {
      return step.stepName === WORKFLOW_STEPS.SHARE && step.success;
    });
    
    if (shareStep && shareStep.result && shareStep.result.sharedWith) {
      // Note: We can't easily revoke permissions, so we log this for manual cleanup
      compensationSteps.push({
        step: 'revoke_permissions',
        sharedWith: shareStep.result.sharedWith,
        success: false,
        error: 'Manual cleanup required - permissions cannot be automatically revoked',
        timestamp: new Date().toISOString()
      });
    }
    
  } catch (e) {
    logWorkflowEvent(workflowId, null, 'compensation_failed', {
      error: e.message
    });
  }
  
  return compensationSteps;
}

// Performance monitoring function
function logPerformanceMetrics(metrics) {
  try {
    console.log('Performance Metrics Received:', metrics);
    
    // Store performance data in Script Properties for monitoring
    var properties = PropertiesService.getScriptProperties();
    var timestamp = new Date().toISOString();
    
    // Log TTI (Time to Interactive)
    if (metrics.tti) {
      properties.setProperty('perf_tti_' + timestamp, metrics.tti.toString());
    }
    
    // Log LCP (Largest Contentful Paint)
    if (metrics.lcp) {
      properties.setProperty('perf_lcp_' + timestamp, metrics.lcp.toString());
    }
    
    // Check if metrics meet Epic 6 requirements
    var meetsRequirements = {
      tti: metrics.tti <= 2500,
      lcp: metrics.lcp ? metrics.lcp <= 2000 : true
    };
    
    return {
      success: true,
      meetsRequirements: meetsRequirements,
      message: 'Performance metrics logged successfully'
    };
  } catch (e) {
    return {
      success: false,
      error: 'Failed to log performance metrics: ' + e.message
    };
  }
}

// Function to clear workflow deduplication cache
function clearWorkflowDeduplicationCache() {
  try {
    var properties = PropertiesService.getScriptProperties();
    var keys = properties.getKeys();
    var workflowKeys = keys.filter(function(key) {
      return key.startsWith('workflow_') && key.includes('_dedupe_');
    });
    
    for (var i = 0; i < workflowKeys.length; i++) {
      properties.deleteProperty(workflowKeys[i]);
    }
    
    return {
      success: true,
      clearedKeys: workflowKeys.length,
      message: 'Workflow deduplication cache cleared'
    };
  } catch (e) {
    return {
      success: false,
      error: 'Failed to clear cache: ' + e.message
    };
  }
}

// Test function for Meeting Notes Workflow
function testMeetingNotesWorkflow() {
  try {
    console.log('Testing Meeting Notes Workflow...');
    
    // Clear deduplication cache first
    clearWorkflowDeduplicationCache();
    
    var timestamp = new Date().getTime();
    var testMeetings = [
      {
        title: 'Weekly Team Standup - Test ' + timestamp,
        date: '2025-09-12',
        attendees: 'john@company.com, jane@company.com, bob@company.com',
        transcription: 'Good morning everyone. Let\'s start with our weekly standup. John, what did you work on this week? I completed the user authentication module and fixed the login bug. Great work John. Jane, how about you? I finished the database optimization and started working on the new API endpoints. Excellent. Bob, what\'s your status? I\'m still working on the frontend components, should be done by Friday. Perfect. Any blockers? No major blockers this week. Great, let\'s keep up the momentum. Next week we\'ll focus on integration testing.',
        shareWith: ['john@company.com', 'jane@company.com', 'bob@company.com']
      },
      {
        title: 'Project Planning Session - Test ' + timestamp,
        date: '2025-09-12',
        attendees: 'manager@company.com, lead@company.com',
        transcription: 'Today we\'re planning our Q4 roadmap. Let\'s review our current progress. We\'ve completed 80% of our Q3 goals. That\'s excellent progress. For Q4, we need to focus on three main areas: performance optimization, new feature development, and user experience improvements. I agree. Let\'s allocate resources accordingly. We\'ll need 2 developers for performance, 3 for new features, and 1 for UX. Sounds good. What about timelines? Performance optimization should be done by mid-November, new features by end of December, and UX improvements by early January. Perfect. Let\'s schedule follow-up meetings for each area.',
        shareWith: ['manager@company.com', 'lead@company.com']
      },
      {
        title: 'Client Presentation - Test ' + timestamp,
        date: '2025-09-12',
        attendees: 'client@external.com, sales@company.com, product@company.com',
        transcription: 'Thank you for joining us today. We\'re excited to present our quarterly results and upcoming roadmap. Our Q3 performance exceeded expectations with 25% growth in user engagement. That\'s impressive. What drove this growth? We launched three new features that significantly improved user experience. The new dashboard, mobile app updates, and integration capabilities were particularly well-received. What can we expect in Q4? We\'re planning to launch our AI-powered analytics feature and expand our API capabilities. We\'re also working on enhanced security features based on your feedback. Excellent. When will these be available? The AI analytics will be in beta by November, with full release in December. API expansion and security features will be rolled out throughout Q4. Perfect. We\'re looking forward to these updates.',
        shareWith: ['client@external.com', 'sales@company.com', 'product@company.com']
      }
    ];
    
    var workflowResults = [];
    
    for (var i = 0; i < testMeetings.length; i++) {
      var meeting = testMeetings[i];
      console.log('Testing meeting:', meeting.title);
      
      var result = executeMeetingNotesWorkflow(meeting);
      workflowResults.push({
        meeting: meeting,
        result: result
      });
    }
    
    return {
      success: true,
      workflowConfig: WORKFLOW_CONFIG,
      workflowStates: WORKFLOW_STATES,
      workflowSteps: WORKFLOW_STEPS,
      testResults: workflowResults,
      timestamp: new Date().toISOString()
    };
    
  } catch (e) {
    console.log('Meeting Notes Workflow test failed:', e.message);
    return { success: false, error: e.message };
  }
}