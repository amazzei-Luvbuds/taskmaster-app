# CSV Integration Setup Guide

## Overview
This guide will help you integrate your existing CSV KPI data into your task manager system, allowing departments to move away from Excel and use a centralized task management system.

## Files Created

### 1. `csv_import_system.html`
- **Purpose**: Main interface for CSV import functionality
- **Features**:
  - Department-specific CSV upload
  - Data preview before import
  - Automatic task creation from CSV data
  - KPI dashboard with department filtering
  - Kanban board integration

### 2. `csv_templates.html`
- **Purpose**: Template generator for departments
- **Features**:
  - Department-specific CSV templates
  - Downloadable template files
  - Usage instructions
  - Data format examples

### 3. `migrate_csv_data.html`
- **Purpose**: One-time migration tool for existing data
- **Features**:
  - Batch migration of all departments
  - Progress tracking
  - Error handling and reporting
  - Results summary

### 4. `integrate_csv_tasks.js`
- **Purpose**: Core integration logic
- **Features**:
  - CSV data parsing and conversion
  - Task creation algorithms
  - Department-specific handling
  - Error handling and validation

## Implementation Steps

### Step 1: Set Up the Import System

1. **Add CSV Import to Your Main Task Manager**:
   ```html
   <!-- Add to your main task manager HTML -->
   <button onclick="window.open('csv_import_system.html', '_blank')">Import CSV Data</button>
   ```

2. **Include Required Libraries**:
   ```html
   <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
   ```

3. **Update Your Data Structure**:
   ```javascript
   // Add to your existing fullData structure
   fullData.departments = [
       'accounting', 'sales', 'tech', 'marketing', 'hr',
       'customer-retention', 'swag', 'ideas', 'trade-shows', 'purchasing'
   ];
   ```

### Step 2: Configure Department Settings

Update the department configurations in `csv_import_system.html`:

```javascript
const departmentConfigs = {
    'accounting': {
        name: 'Accounting',
        color: '#10b981',
        kpis: ['TOTAL REVENUE YTD', 'TOTAL REVENUE MTD', 'TOTAL REVENUE QTD'],
        taskTypes: ['Revenue Tracking', 'Financial Analysis', 'Budget Review']
    },
    // ... other departments
};
```

### Step 3: Migrate Existing Data

1. **Run the Migration Tool**:
   - Open `migrate_csv_data.html`
   - Click "Migrate" for each department
   - Review the results and fix any errors

2. **Verify Data Integrity**:
   - Check that all tasks were created correctly
   - Verify department assignments
   - Ensure KPI values are preserved

### Step 4: Train Department Users

1. **Provide Templates**:
   - Share `csv_templates.html` with department leads
   - Show them how to download their department's template
   - Explain the data format requirements

2. **Training Sessions**:
   - Demonstrate the CSV import process
   - Show how to preview data before importing
   - Explain the task creation process

### Step 5: Set Up Regular Workflows

1. **Weekly Data Updates**:
   - Departments export their weekly KPI data as CSV
   - Upload to the task manager using the import system
   - Review and approve the created tasks

2. **Monthly Reviews**:
   - Use the KPI dashboard to review performance
   - Identify trends and issues
   - Create action items based on the data

## Department-Specific Implementation

### Accounting Department
- **Data Focus**: Revenue tracking, financial metrics
- **Task Types**: Revenue analysis, budget reviews, financial reporting
- **Update Frequency**: Weekly
- **Key Metrics**: Total Revenue YTD/MTD/QTD, % Change metrics

### Sales Department
- **Data Focus**: Sales rep performance, lead generation
- **Task Types**: Performance tracking, account management, lead follow-up
- **Update Frequency**: Weekly
- **Key Metrics**: Calls made, emails sent, new accounts, order counts

### Tech Department
- **Data Focus**: IT tickets, project status, system maintenance
- **Task Types**: Bug fixes, feature development, system maintenance
- **Update Frequency**: Daily/Weekly
- **Key Metrics**: Ticket counts, project status, system health

### Marketing Department
- **Data Focus**: Campaign performance, traffic, engagement
- **Task Types**: Campaign management, SEO optimization, content creation
- **Update Frequency**: Weekly
- **Key Metrics**: Business won, traffic growth, organic performance

### HR Department
- **Data Focus**: Employee metrics, attendance, performance
- **Task Types**: Employee management, performance reviews, policy updates
- **Update Frequency**: Weekly
- **Key Metrics**: Attendance rates, write-ups, employee count

### Customer Retention
- **Data Focus**: Support tickets, RMA processing, customer satisfaction
- **Task Types**: Customer support, quality assurance, process improvement
- **Update Frequency**: Daily
- **Key Metrics**: Call/chat/email volumes, RMA counts, replacement orders

## Data Format Requirements

### Standard CSV Format
```csv
WEEK OF,9/20/2024,9/27/2024,10/4/2024
METRIC_NAME,VALUE1,VALUE2,VALUE3
ANOTHER_METRIC,VALUE1,VALUE2,VALUE3
```

### Project Data Format (Tech Department)
```csv
Project,Status,Assignee,Due Date,Description
Project Name,In Progress,Developer Name,2025-01-15,Project description
```

### Sales Rep Data Format
```csv
SALE REP,Calls Made,Emails Made,New Accounts,Order Count
Rep Name,50,100,5,25
```

## Troubleshooting

### Common Issues

1. **CSV Parsing Errors**:
   - Check for special characters in data
   - Ensure proper CSV formatting
   - Verify column headers match expected format

2. **Task Creation Failures**:
   - Review department configuration
   - Check data validation rules
   - Verify required fields are present

3. **Performance Issues**:
   - Limit CSV file size to reasonable amounts
   - Process large files in batches
   - Consider data archiving for old records

### Error Handling

The system includes comprehensive error handling:
- Invalid data format detection
- Missing required fields validation
- Department configuration validation
- Data type conversion errors

## Maintenance

### Regular Tasks

1. **Weekly**:
   - Review import logs for errors
   - Update department configurations as needed
   - Monitor system performance

2. **Monthly**:
   - Archive old CSV data
   - Review and update templates
   - Analyze usage patterns

3. **Quarterly**:
   - Review department configurations
   - Update KPI definitions
   - Optimize system performance

## Security Considerations

1. **Data Validation**:
   - All CSV data is validated before processing
   - Malicious content is filtered out
   - Data types are enforced

2. **Access Control**:
   - Department-specific access controls
   - Audit logging for all imports
   - Data backup and recovery procedures

## Support

For technical support or questions:
1. Check the troubleshooting section above
2. Review the error logs in the import system
3. Contact your system administrator
4. Refer to the department-specific documentation

## Future Enhancements

Planned improvements include:
- Automated CSV import scheduling
- Advanced data visualization
- Integration with external systems
- Mobile app support
- Advanced reporting and analytics
