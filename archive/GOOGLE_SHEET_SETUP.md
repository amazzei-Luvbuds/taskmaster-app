# Google Sheet Leadership System Setup

## Your Google Sheet
**Sheet ID:** `161omaX8sXMPPvL9iqHi5c6EUUbn5ywQRhOPrIZ_-MVc`  
**URL:** [https://docs.google.com/spreadsheets/d/161omaX8sXMPPvL9iqHi5c6EUUbn5ywQRhOPrIZ_-MVc/edit](https://docs.google.com/spreadsheets/d/161omaX8sXMPPvL9iqHi5c6EUUbn5ywQRhOPrIZ_-MVc/edit)

## Step 1: Add Google Apps Script

### 1.1 Open Apps Script
1. Go to your Google Sheet
2. Click **Extensions** → **Apps Script**
3. Delete any existing code in `Code.gs`
4. Copy and paste the entire contents of `leadership_appscript.gs`

### 1.2 Update Configuration
In the Apps Script editor, find the `CONFIG` section and update:

```javascript
const CONFIG = {
  // Your Google Sheet ID (already set correctly)
  SHEET_ID: '161omaX8sXMPPvL9iqHi5c6EUUbn5ywQRhOPrIZ_-MVc',
  
  // UPDATE THESE WITH YOUR ACTUAL LEADERSHIP EMAILS
  LEADERSHIP_EMAILS: [
    'your.ceo@gmail.com',
    'your.cto@gmail.com', 
    'your.vp@gmail.com',
    'founder@luvbuds.com',
    // Add more leadership emails here
  ],
  
  // These keywords will automatically mark tasks as leadership
  LEADERSHIP_KEYWORDS: [
    'strategic', 'confidential', 'executive', 'board', 'leadership',
    'ceo', 'cto', 'vp', 'founder', 'budget', 'financial',
    'acquisition', 'merger', 'partnership', 'investor', 'funding',
    'legal', 'compliance', 'hr confidential', 'salary', 'compensation'
  ],
  
  // These departments will be leadership-only
  LEADERSHIP_DEPARTMENTS: [
    'Executive', 'Leadership', 'Board', 'Strategic Planning',
    'Legal', 'Finance', 'HR Confidential'
  ]
};
```

## Step 2: Initialize the System

### 2.1 Run Setup Function
1. In Apps Script, select the function `setupLeadershipSystem`
2. Click **Run** (▶️)
3. Grant permissions when prompted
4. Check the execution log for results

### 2.2 What This Does
- ✅ Adds `isLeadership` column to your sheet
- ✅ Auto-categorizes existing tasks based on keywords
- ✅ Tests the authentication system
- ✅ Verifies task filtering works

## Step 3: Deploy as Web App

### 3.1 Deploy the Script
1. In Apps Script, click **Deploy** → **New deployment**
2. Choose **Web app** as type
3. Set **Execute as:** "Me"
4. Set **Who has access:** "Anyone"
5. Click **Deploy**
6. Copy the web app URL

### 3.2 Update Your HTML Files
In your existing HTML files, make sure they're calling the correct functions:
- `getKanbanData()` - for regular users
- `getLeadershipKanbanData(userEmail)` - for leadership users
- `verifyLeadershipAccess(email)` - for authentication

## Step 4: Test the System

### 4.1 Test Regular Users
1. Open your main dashboard
2. Verify leadership tasks are hidden
3. Check that regular tasks show normally

### 4.2 Test Leadership Users
1. Go to the leadership portal
2. Enter an authorized Gmail address
3. Verify you can see ALL tasks (including leadership ones)
4. Check that leadership tasks have 🔒 badges

## Step 5: Automatic Task Categorization

### 5.1 How It Works
New tasks are automatically categorized as leadership if they contain:
- **Keywords:** strategic, confidential, executive, budget, etc.
- **Departments:** Executive, Leadership, Legal, Finance, etc.

### 5.2 Manual Override
You can manually set the `isLeadership` column to:
- `TRUE` - Makes task leadership-only
- `FALSE` or blank - Makes task visible to everyone

### 5.3 Integration with Task Creation
When you create new tasks (via AI uploader or manual entry), the system will:
1. Check the task text for leadership keywords
2. Check the department for leadership departments
3. Automatically set `isLeadership` column
4. Apply appropriate visibility rules

## Step 6: Monitoring and Maintenance

### 6.1 Check Task Categorization
Run this function periodically to see categorization results:
```javascript
function checkTaskCategorization() {
  const tasks = getAllTasksFromSheet();
  const leadershipTasks = tasks.filter(t => t.isLeadership === 'TRUE' || t.isLeadership === true);
  const regularTasks = tasks.filter(t => !t.isLeadership || t.isLeadership === 'FALSE');
  
  console.log(`Total tasks: ${tasks.length}`);
  console.log(`Leadership tasks: ${leadershipTasks.length}`);
  console.log(`Regular tasks: ${regularTasks.length}`);
  
  return {
    total: tasks.length,
    leadership: leadershipTasks.length,
    regular: regularTasks.length
  };
}
```

### 6.2 Update Leadership Emails
When you need to add/remove leadership users:
1. Update `CONFIG.LEADERSHIP_EMAILS` in Apps Script
2. Save and redeploy if needed

### 6.3 Adjust Keywords
If you need to change what makes a task "leadership":
1. Update `CONFIG.LEADERSHIP_KEYWORDS` in Apps Script
2. Run `autoCategorizeExistingTasks()` to recategorize existing tasks

## Troubleshooting

### Common Issues

**"Column not found" errors:**
- Run `initializeLeadershipSystem()` again
- Check that the `isLeadership` column was created

**"Unauthorized access" errors:**
- Verify the Gmail address is in `CONFIG.LEADERSHIP_EMAILS`
- Check for typos in email addresses

**Tasks not showing correctly:**
- Run `testLeadershipSystem()` to diagnose
- Check the execution log for errors

**New tasks not auto-categorizing:**
- Ensure your task creation process calls `autoCategorizeNewTask()`
- Check that the task text contains recognizable keywords

### Debug Functions

Run these functions in Apps Script to debug:

```javascript
// Test the entire system
testLeadershipSystem()

// Check current task categorization
checkTaskCategorization()

// Re-categorize all existing tasks
autoCategorizeExistingTasks()

// Test authentication
verifyLeadershipAccess('test@email.com')
```

## Security Notes

- ✅ Leadership tasks are completely hidden from regular users
- ✅ Authentication is server-side (secure)
- ✅ All filtering happens in Google Apps Script
- ✅ Regular users cannot access leadership functions
- ✅ Session expires after 24 hours

## Next Steps

1. **Update leadership emails** in the CONFIG section
2. **Run the setup function** to initialize everything
3. **Deploy as web app** and test with real users
4. **Monitor the system** for proper categorization
5. **Adjust keywords** as needed for your specific use case

The system is now ready to automatically handle leadership task locking for your LuvBuds task management system!
