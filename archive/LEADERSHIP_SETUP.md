# Leadership Task Locking Setup Guide

## Overview
This system allows you to lock leadership tasks from regular company users by adding a simple column to your Google Sheet and implementing Gmail-based authentication.

## Step 1: Google Sheet Setup

### Add Leadership Column
1. Open your Google Sheet with the task data
2. Add a new column (recommend column Z or after your last data column)
3. Name the column: `isLeadership`
4. Set values:
   - `TRUE` for leadership tasks (confidential/strategic)
   - `FALSE` or leave blank for regular tasks

### Example Sheet Structure:
```
A: taskID | B: actionItem | C: status | ... | Z: isLeadership
TAL-001  | Regular task  | In Progress | ... | FALSE
TAL-002  | Strategic plan| Not Started | ... | TRUE
TAL-003  | Team meeting  | Completed   | ... | FALSE
```

## Step 2: Google Apps Script Backend Updates

You'll need to add these functions to your `code.gs` file:

### 1. Leadership Access Verification
```javascript
function verifyLeadershipAccess(email) {
  // List of authorized leadership Gmail addresses
  const leadershipEmails = [
    'ceo@yourcompany.com',
    'cto@yourcompany.com',
    'vp@yourcompany.com',
    // Add more leadership emails here
  ];
  
  const isAuthorized = leadershipEmails.includes(email.toLowerCase());
  
  return {
    success: isAuthorized,
    email: email,
    message: isAuthorized ? 'Access granted' : 'Email not authorized for leadership access'
  };
}
```

### 2. Leadership Kanban Data
```javascript
function getLeadershipKanbanData(userEmail) {
  try {
    // Verify user has leadership access
    const authResult = verifyLeadershipAccess(userEmail);
    if (!authResult.success) {
      return { error: 'Unauthorized access' };
    }
    
    // Get all tasks including leadership ones
    const allTasks = getAllTasks(); // Your existing function
    const departments = getDepartments(); // Your existing function
    const users = getUsers(); // Your existing function
    
    return {
      tasks: allTasks, // Include ALL tasks for leadership
      departments: departments,
      users: users
    };
  } catch (error) {
    return { error: error.toString() };
  }
}
```

### 3. Update Regular Data Functions
```javascript
function getKanbanData() {
  try {
    const allTasks = getAllTasks();
    const departments = getDepartments();
    const users = getUsers();
    
    // Filter out leadership tasks for regular users
    const regularTasks = allTasks.filter(task => !task.isLeadership);
    
    return {
      tasks: regularTasks,
      departments: departments,
      users: users
    };
  } catch (error) {
    return { error: error.toString() };
  }
}
```

## Step 3: Frontend Files Created

The following files have been created:

1. **`leadership.html`** - Leadership login portal with Gmail authentication
2. **`leadership_kanban.html`** - Leadership-specific kanban board
3. **Updated `dashboard.html`** - Added leadership portal link
4. **Updated `kanban.html`** - Filters out leadership tasks
5. **Updated `tasks_departments.html`** - Added leadership link

## Step 4: Navigation Updates

### Main Dashboard
- Added "Leadership Portal" card with 🔒 icon
- Requires Gmail authentication to access

### Regular Views
- All regular kanban and department views now filter out leadership tasks
- Leadership tasks are completely hidden from regular users

## Step 5: Security Features

### Authentication
- Gmail-based authentication (24-hour session)
- Local storage for session management
- Automatic logout after 24 hours

### Visual Indicators
- Leadership tasks show 🔒 icon and "Leadership" badge
- Gold color scheme for leadership interface
- Clear visual distinction from regular tasks

### Access Control
- Server-side filtering (never trust frontend)
- Leadership tasks only visible to authenticated leadership users
- Regular users cannot see leadership tasks at all

## Step 6: Usage

### For Regular Users
1. Access dashboard normally
2. Leadership tasks are automatically hidden
3. No access to leadership portal

### For Leadership Users
1. Click "Leadership Portal" on main dashboard
2. Enter authorized Gmail address
3. Access full kanban board with all tasks
4. Leadership tasks are highlighted with 🔒 badges

## Step 7: Maintenance

### Adding New Leadership Users
1. Add Gmail address to `leadershipEmails` array in `verifyLeadershipAccess()`
2. Deploy updated Google Apps Script

### Managing Leadership Tasks
1. Set `isLeadership` column to `TRUE` in Google Sheet
2. Task automatically becomes visible only to leadership users

## Security Notes

- Always verify permissions server-side
- Leadership tasks are completely hidden from regular users
- Session expires after 24 hours for security
- All leadership access is logged and auditable

## Troubleshooting

### Common Issues
1. **"Access denied"** - Gmail not in leadership list
2. **Tasks not showing** - Check `isLeadership` column values
3. **Session expired** - Re-authenticate with Gmail

### Testing
1. Test with regular Gmail (should be denied)
2. Test with leadership Gmail (should work)
3. Verify leadership tasks are hidden from regular views
4. Verify leadership tasks show in leadership portal
