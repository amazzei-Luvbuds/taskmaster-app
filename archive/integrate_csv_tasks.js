// CSV Task Integration Script
// This script helps integrate your existing CSV KPI data into the task manager

class CSVTaskIntegrator {
    constructor() {
        this.departments = {
            'accounting': {
                name: 'Accounting',
                color: '#10b981',
                kpis: ['TOTAL REVENUE YTD', 'TOTAL REVENUE MTD', 'TOTAL REVENUE QTD', '% Change YTD', '% Change MTD'],
                taskTypes: ['Revenue Tracking', 'Financial Analysis', 'Budget Review']
            },
            'sales': {
                name: 'Sales',
                color: '#3b82f6',
                kpis: ['Calls Made', 'Emails Made', 'New Accounts Opened', 'Order Count', 'Largest Orders'],
                taskTypes: ['Sales Performance', 'Lead Generation', 'Account Management']
            },
            'tech': {
                name: 'Tech',
                color: '#8b5cf6',
                kpis: ['New Tickets Opened', 'Total Tickets Closed', 'Project Status'],
                taskTypes: ['System Maintenance', 'Project Development', 'Bug Fixes']
            },
            'marketing': {
                name: 'Marketing',
                color: '#f59e0b',
                kpis: ['TOTAL BUSINESS WON', 'GROWTH OF ORGANIC TRAFFIC', 'TOTAL TRUE TRAFFIC'],
                taskTypes: ['Campaign Management', 'SEO Optimization', 'Content Creation']
            },
            'hr': {
                name: 'HR',
                color: '#ef4444',
                kpis: ['Late Days %', 'Missing Days %', 'Write ups', 'TOT # OF EMPLOYEES'],
                taskTypes: ['Employee Management', 'Performance Review', 'Policy Updates']
            },
            'customer-retention': {
                name: 'Customer Retention',
                color: '#06b6d4',
                kpis: ['Total Calls', 'Total Chats', 'Total Emails', 'Replacement Orders'],
                taskTypes: ['Customer Support', 'RMA Processing', 'Quality Assurance']
            },
            'swag': {
                name: 'Swag',
                color: '#84cc16',
                kpis: ['Product Mapping', 'Process O2C', 'Inventory Levels'],
                taskTypes: ['Product Management', 'Inventory Control', 'Process Optimization']
            },
            'ideas': {
                name: 'Ideas',
                color: '#f97316',
                kpis: ['AVG MONTHLY REV', 'AVG MONTHLY GM', 'AVG MONTHLY COGS'],
                taskTypes: ['Strategic Planning', 'Growth Analysis', 'Business Development']
            },
            'trade-shows': {
                name: 'Trade Shows & Merchandising',
                color: '#ec4899',
                kpis: ['Event Attendance', 'Lead Generation', 'ROI'],
                taskTypes: ['Event Planning', 'Lead Management', 'ROI Analysis']
            },
            'purchasing': {
                name: 'Purchasing',
                color: '#6366f1',
                kpis: ['Purchase Orders', 'Vendor Performance', 'Cost Savings'],
                taskTypes: ['Vendor Management', 'Cost Optimization', 'Procurement']
            }
        };
        
        this.taskCounter = 0;
    }

    // Convert CSV data to tasks based on department
    convertCSVToTasks(csvData, department) {
        const tasks = [];
        const config = this.departments[department];
        
        if (!config) {
            throw new Error(`Unknown department: ${department}`);
        }

        csvData.forEach((row, index) => {
            // Skip empty rows
            if (!row || Object.values(row).every(val => !val || val.toString().trim() === '')) {
                return;
            }

            // Handle different CSV structures based on department
            if (department === 'tech' && this.isProjectData(row)) {
                // Tech department has project-based data
                tasks.push(this.createProjectTask(row, department, index));
            } else if (department === 'sales' && this.isSalesRepData(row)) {
                // Sales department has rep-based data
                tasks.push(...this.createSalesRepTasks(row, department, index));
            } else {
                // Other departments have KPI-based data
                tasks.push(...this.createKPITasks(row, department, index));
            }
        });

        return tasks;
    }

    // Check if row contains project data (Tech department)
    isProjectData(row) {
        return row['Project'] || row['Task'] || row['Description'] || row['Status'];
    }

    // Check if row contains sales rep data (Sales department)
    isSalesRepData(row) {
        return row['SALE REP'] || row['Calls Made'] || row['Emails Made'];
    }

    // Create a project task (for Tech department)
    createProjectTask(row, department, index) {
        this.taskCounter++;
        return {
            id: `csv-${department}-${this.taskCounter}`,
            title: row['Project'] || row['Task'] || `Tech Task ${index + 1}`,
            description: this.createProjectDescription(row),
            department: department,
            status: this.mapStatus(row['Status']),
            priority: this.determinePriority(row),
            assignee: row['Assignee'] || 'Tech Team',
            dueDate: this.parseDate(row['Due Date']),
            createdDate: new Date().toISOString(),
            type: 'Project',
            source: 'CSV Import',
            tags: ['tech', 'project', 'migrated'],
            metadata: {
                originalRow: row,
                importDate: new Date().toISOString()
            }
        };
    }

    // Create sales rep tasks (for Sales department)
    createSalesRepTasks(row, department, index) {
        const tasks = [];
        const repName = row['SALE REP'] || 'Unknown Rep';
        
        // Create tasks for each KPI metric
        const metrics = ['Calls Made', 'Emails Made', 'New Accounts Opened', 'Order Count'];
        
        metrics.forEach(metric => {
            if (row[metric] && row[metric].toString().trim() !== '') {
                this.taskCounter++;
                tasks.push({
                    id: `csv-${department}-${this.taskCounter}`,
                    title: `${repName} - ${metric}: ${row[metric]}`,
                    description: `Sales performance tracking for ${repName}`,
                    department: department,
                    status: 'In Progress',
                    priority: 'Medium',
                    assignee: repName,
                    dueDate: new Date().toISOString().split('T')[0],
                    createdDate: new Date().toISOString(),
                    type: 'KPI',
                    kpiValue: row[metric],
                    kpiMetric: metric,
                    source: 'CSV Import',
                    tags: ['sales', 'performance', 'migrated'],
                    metadata: {
                        repName: repName,
                        originalRow: row,
                        importDate: new Date().toISOString()
                    }
                });
            }
        });

        return tasks;
    }

    // Create KPI tasks (for most departments)
    createKPITasks(row, department, index) {
        const tasks = [];
        const config = this.departments[department];
        
        // Create tasks for each KPI metric
        Object.entries(row).forEach(([key, value]) => {
            if (value && value.toString().trim() !== '' && key !== 'WEEK OF' && key !== 'SALE REP') {
                this.taskCounter++;
                tasks.push({
                    id: `csv-${department}-${this.taskCounter}`,
                    title: `${key}: ${value}`,
                    description: this.createKPIDescription(key, value, config.name),
                    department: department,
                    status: this.determineKPIStatus(value, key),
                    priority: this.determineKPIPriority(key, value),
                    assignee: this.getDefaultAssignee(department),
                    dueDate: this.parseDate(row['WEEK OF']) || new Date().toISOString().split('T')[0],
                    createdDate: new Date().toISOString(),
                    type: 'KPI',
                    kpiValue: value,
                    kpiMetric: key,
                    source: 'CSV Import',
                    tags: [department, 'kpi', 'migrated'],
                    metadata: {
                        originalRow: row,
                        importDate: new Date().toISOString()
                    }
                });
            }
        });

        return tasks;
    }

    // Helper methods
    createProjectDescription(row) {
        let description = 'Migrated from CSV data:\n\n';
        Object.entries(row).forEach(([key, value]) => {
            if (value && value.toString().trim() !== '') {
                description += `${key}: ${value}\n`;
            }
        });
        return description;
    }

    createKPIDescription(metric, value, departmentName) {
        return `KPI tracking for ${departmentName}\n\nMetric: ${metric}\nValue: ${value}\n\nMigrated from CSV data`;
    }

    mapStatus(status) {
        const statusMap = {
            'In Progress': 'In Progress',
            'Completed': 'Completed',
            'On Hold': 'Blocked',
            'Testing': 'In Progress',
            'Not Started': 'Not Started',
            'Blocked': 'Blocked'
        };
        return statusMap[status] || 'Not Started';
    }

    determinePriority(row) {
        // Determine priority based on project data
        if (row['Priority']) {
            return row['Priority'];
        }
        
        // Default priority logic
        if (row['Status'] === 'Completed') return 'Low';
        if (row['Status'] === 'In Progress') return 'High';
        if (row['Status'] === 'On Hold') return 'Medium';
        return 'Medium';
    }

    determineKPIStatus(value, metric) {
        // Determine status based on KPI value
        if (typeof value === 'string' && value.includes('%')) {
            const numValue = parseFloat(value.replace('%', ''));
            if (numValue >= 90) return 'Completed';
            if (numValue >= 70) return 'In Progress';
            return 'Not Started';
        }
        
        return 'In Progress';
    }

    determineKPIPriority(metric, value) {
        // Determine priority based on metric type
        const highPriorityMetrics = ['TOTAL REVENUE', 'PROFIT', 'GROWTH', 'PERFORMANCE'];
        const lowPriorityMetrics = ['%', 'RATIO', 'SCORE'];
        
        if (highPriorityMetrics.some(term => metric.toUpperCase().includes(term))) {
            return 'High';
        }
        
        if (lowPriorityMetrics.some(term => metric.toUpperCase().includes(term))) {
            return 'Low';
        }
        
        return 'Medium';
    }

    getDefaultAssignee(department) {
        const assignees = {
            'accounting': 'Accounting Team',
            'sales': 'Sales Manager',
            'tech': 'Tech Team',
            'marketing': 'Marketing Manager',
            'hr': 'HR Manager',
            'customer-retention': 'Customer Service Manager',
            'swag': 'Product Manager',
            'ideas': 'Strategy Team',
            'trade-shows': 'Events Manager',
            'purchasing': 'Procurement Manager'
        };
        return assignees[department] || 'Department Lead';
    }

    parseDate(dateString) {
        if (!dateString) return null;
        
        try {
            // Handle various date formats from your CSV files
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return null;
            return date.toISOString().split('T')[0];
        } catch (error) {
            return null;
        }
    }

    // Integration with existing task manager
    integrateWithTaskManager(tasks) {
        // This method would integrate with your existing task manager
        // You'll need to adapt this based on your current system
        
        console.log(`Integrating ${tasks.length} tasks into task manager...`);
        
        // Example integration (adapt to your system):
        tasks.forEach(task => {
            // Add to your existing task data structure
            if (typeof window !== 'undefined' && window.fullData) {
                window.fullData.tasks.push(task);
            }
            
            // Or save to your backend
            this.saveTaskToBackend(task);
        });
        
        return tasks;
    }

    saveTaskToBackend(task) {
        // Implement your backend save logic here
        // This could be Google Apps Script, a REST API, or local storage
        console.log('Saving task:', task);
    }

    // Batch processing for all departments
    async processAllDepartments(csvDataByDepartment) {
        const allTasks = [];
        const results = {};
        
        for (const [department, csvData] of Object.entries(csvDataByDepartment)) {
            try {
                console.log(`Processing ${department} department...`);
                const tasks = this.convertCSVToTasks(csvData, department);
                allTasks.push(...tasks);
                results[department] = {
                    success: true,
                    taskCount: tasks.length,
                    tasks: tasks
                };
                console.log(`Created ${tasks.length} tasks for ${department}`);
            } catch (error) {
                results[department] = {
                    success: false,
                    error: error.message,
                    taskCount: 0
                };
                console.error(`Error processing ${department}:`, error);
            }
        }
        
        return {
            totalTasks: allTasks.length,
            results: results,
            allTasks: allTasks
        };
    }

    // Generate summary report
    generateSummaryReport(processingResults) {
        const report = {
            summary: {
                totalTasks: processingResults.totalTasks,
                departmentsProcessed: Object.keys(processingResults.results).length,
                successfulDepartments: Object.values(processingResults.results).filter(r => r.success).length,
                failedDepartments: Object.values(processingResults.results).filter(r => !r.success).length
            },
            departmentBreakdown: {},
            recommendations: []
        };
        
        // Department breakdown
        Object.entries(processingResults.results).forEach(([dept, result]) => {
            report.departmentBreakdown[dept] = {
                taskCount: result.taskCount,
                success: result.success,
                error: result.error || null
            };
        });
        
        // Generate recommendations
        if (report.summary.failedDepartments > 0) {
            report.recommendations.push('Review failed department imports and fix data format issues');
        }
        
        if (report.summary.totalTasks > 1000) {
            report.recommendations.push('Consider implementing task archiving for large datasets');
        }
        
        report.recommendations.push('Set up regular CSV import schedules for each department');
        report.recommendations.push('Train department leads on proper CSV formatting');
        
        return report;
    }
}

// Usage example:
/*
const integrator = new CSVTaskIntegrator();

// Process individual department
const accountingTasks = integrator.convertCSVToTasks(accountingCSVData, 'accounting');
integrator.integrateWithTaskManager(accountingTasks);

// Process all departments
const allCSVData = {
    'accounting': accountingCSVData,
    'sales': salesCSVData,
    'tech': techCSVData,
    // ... other departments
};

const results = await integrator.processAllDepartments(allCSVData);
const report = integrator.generateSummaryReport(results);

console.log('Integration complete:', report);
*/

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CSVTaskIntegrator;
}
