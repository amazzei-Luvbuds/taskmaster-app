// Script to generate remaining department pages
const departmentConfigs = {
    'marketing': {
        name: 'Marketing',
        color: '#f59e0b',
        icon: '📈',
        gradient: 'linear-gradient(135deg, #f59e0b, #d97706)',
        subtitle: 'Campaign Management & Growth',
        kpis: [
            { name: 'Total Business Won', type: 'currency', placeholder: '$0' },
            { name: 'Growth of Organic Traffic', type: 'percentage', placeholder: '0' },
            { name: 'Total True Traffic', type: 'number', placeholder: '0' },
            { name: 'Organic Business Due to SEO', type: 'currency', placeholder: '$0' },
            { name: 'Email Open Rate', type: 'percentage', placeholder: '0' },
            { name: 'Conversion Rate', type: 'percentage', placeholder: '0' }
        ],
        quickActions: [
            'Campaign Launch',
            'Content Creation',
            'SEO Optimization',
            'Social Media Management',
            'Email Marketing',
            'Analytics Review'
        ]
    },
    'hr': {
        name: 'HR',
        color: '#ef4444',
        icon: '👥',
        gradient: 'linear-gradient(135deg, #ef4444, #dc2626)',
        subtitle: 'Employee Management & Development',
        kpis: [
            { name: 'Late Days %', type: 'percentage', placeholder: '0' },
            { name: 'Missing Days %', type: 'percentage', placeholder: '0' },
            { name: 'Write ups', type: 'number', placeholder: '0' },
            { name: 'Total Employees', type: 'number', placeholder: '0' },
            { name: 'Employee Satisfaction', type: 'percentage', placeholder: '0' },
            { name: 'Training Completion', type: 'percentage', placeholder: '0' }
        ],
        quickActions: [
            'Employee Review',
            'Policy Update',
            'Training Session',
            'Recruitment',
            'Performance Evaluation',
            'Team Building'
        ]
    },
    'customer-retention': {
        name: 'Customer Retention',
        color: '#06b6d4',
        icon: '🎧',
        gradient: 'linear-gradient(135deg, #06b6d4, #0891b2)',
        subtitle: 'Customer Support & Satisfaction',
        kpis: [
            { name: 'Total Calls', type: 'number', placeholder: '0' },
            { name: 'Total Chats', type: 'number', placeholder: '0' },
            { name: 'Total Emails', type: 'number', placeholder: '0' },
            { name: 'Replacement Orders', type: 'number', placeholder: '0' },
            { name: 'Pending RMAs', type: 'number', placeholder: '0' },
            { name: 'Customer Satisfaction', type: 'percentage', placeholder: '0' }
        ],
        quickActions: [
            'Customer Support Ticket',
            'RMA Processing',
            'Quality Check',
            'Customer Follow-up',
            'Issue Resolution',
            'Feedback Collection'
        ]
    },
    'swag': {
        name: 'Swag',
        color: '#84cc16',
        icon: '🎁',
        gradient: 'linear-gradient(135deg, #84cc16, #65a30d)',
        subtitle: 'Product & Merchandise Management',
        kpis: [
            { name: 'Product Mapping Progress', type: 'percentage', placeholder: '0' },
            { name: 'Process O2C Status', type: 'percentage', placeholder: '0' },
            { name: 'Inventory Levels', type: 'number', placeholder: '0' },
            { name: 'Order Fulfillment', type: 'percentage', placeholder: '0' },
            { name: 'Quality Score', type: 'percentage', placeholder: '0' },
            { name: 'Shipping Accuracy', type: 'percentage', placeholder: '0' }
        ],
        quickActions: [
            'Product Mapping',
            'Inventory Update',
            'Process Optimization',
            'Quality Control',
            'Order Processing',
            'Vendor Management'
        ]
    },
    'ideas': {
        name: 'Ideas',
        color: '#f97316',
        icon: '💡',
        gradient: 'linear-gradient(135deg, #f97316, #ea580c)',
        subtitle: 'Strategic Planning & Innovation',
        kpis: [
            { name: 'Average Monthly Revenue', type: 'currency', placeholder: '$0' },
            { name: 'Average Monthly GM', type: 'percentage', placeholder: '0' },
            { name: 'Average Monthly COGS', type: 'currency', placeholder: '$0' },
            { name: 'Annual Profit', type: 'currency', placeholder: '$0' },
            { name: 'Growth Rate', type: 'percentage', placeholder: '0' },
            { name: 'Market Share', type: 'percentage', placeholder: '0' }
        ],
        quickActions: [
            'Strategic Planning',
            'Growth Analysis',
            'Business Development',
            'Market Research',
            'Innovation Review',
            'ROI Analysis'
        ]
    },
    'trade-shows': {
        name: 'Trade Shows & Merchandising',
        color: '#ec4899',
        icon: '🎪',
        gradient: 'linear-gradient(135deg, #ec4899, #db2777)',
        subtitle: 'Events & Lead Generation',
        kpis: [
            { name: 'Event Attendance', type: 'number', placeholder: '0' },
            { name: 'Lead Generation', type: 'number', placeholder: '0' },
            { name: 'ROI', type: 'percentage', placeholder: '0' },
            { name: 'Booth Visitors', type: 'number', placeholder: '0' },
            { name: 'Follow-up Rate', type: 'percentage', placeholder: '0' },
            { name: 'Cost Per Lead', type: 'currency', placeholder: '$0' }
        ],
        quickActions: [
            'Event Planning',
            'Lead Follow-up',
            'ROI Analysis',
            'Booth Setup',
            'Marketing Materials',
            'Post-Event Review'
        ]
    },
    'purchasing': {
        name: 'Purchasing',
        color: '#6366f1',
        icon: '📦',
        gradient: 'linear-gradient(135deg, #6366f1, #4f46e5)',
        subtitle: 'Procurement & Vendor Management',
        kpis: [
            { name: 'Purchase Orders', type: 'number', placeholder: '0' },
            { name: 'Vendor Performance', type: 'percentage', placeholder: '0' },
            { name: 'Cost Savings', type: 'currency', placeholder: '$0' },
            { name: 'Average Lead Time', type: 'number', placeholder: '0' },
            { name: 'Quality Score', type: 'percentage', placeholder: '0' },
            { name: 'Supplier Diversity', type: 'percentage', placeholder: '0' }
        ],
        quickActions: [
            'Vendor Management',
            'Cost Optimization',
            'Procurement Review',
            'Contract Negotiation',
            'Quality Assessment',
            'Supplier Evaluation'
        ]
    }
};

function generateDepartmentPage(deptKey, config) {
    const kpiInputs = config.kpis.map(kpi => `
                <div class="kpi-card">
                    <div class="kpi-label">${kpi.name}</div>
                    <input type="${kpi.type === 'currency' ? 'text' : kpi.type === 'percentage' ? 'text' : 'number'}" 
                           class="kpi-input ${kpi.type === 'currency' ? 'currency' : kpi.type === 'percentage' ? 'percentage' : ''}" 
                           placeholder="${kpi.placeholder}" 
                           data-kpi="${kpi.name}">
                </div>
            `).join('');

    const quickActionButtons = config.quickActions.map(action => `
                <button class="quick-btn" onclick="createQuickTask('${action}')">${action}</button>
            `).join('');

    return `<!DOCTYPE html>
<html>
<head>
    <base target="_top">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <?!= include('../style.css.html'); ?>
    <style>
        .department-header {
            background: ${config.gradient};
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .department-title {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .department-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #1e293b;
            border: 1px solid #374151;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 600;
            color: ${config.color};
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #9ca3af;
            font-size: 0.9rem;
        }
        
        .kpi-section {
            background: #1e293b;
            border: 1px solid #374151;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #e5e7eb;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .kpi-card {
            background: #0f172a;
            border: 1px solid #374151;
            border-radius: 8px;
            padding: 20px;
        }
        
        .kpi-label {
            color: #9ca3af;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        
        .kpi-input {
            background: transparent;
            border: none;
            color: #e5e7eb;
            font-size: 1.5rem;
            font-weight: 600;
            width: 100%;
            padding: 8px 0;
            border-bottom: 2px solid #374151;
            transition: border-color 0.3s;
        }
        
        .kpi-input:focus {
            outline: none;
            border-bottom-color: ${config.color};
        }
        
        .kpi-input.currency::before {
            content: '$';
            color: ${config.color};
        }
        
        .kpi-input.percentage::after {
            content: '%';
            color: ${config.color};
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .quick-btn {
            background: ${config.color};
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            font-weight: 500;
        }
        
        .quick-btn:hover {
            background: ${config.color}dd;
        }
        
        .quick-btn.secondary {
            background: #6b7280;
        }
        
        .quick-btn.secondary:hover {
            background: #4b5563;
        }
        
        .tasks-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .task-column {
            background: #1e293b;
            border: 1px solid #374151;
            border-radius: 8px;
            padding: 20px;
        }
        
        .column-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .column-title {
            color: #e5e7eb;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .task-count {
            background: #374151;
            color: #e5e7eb;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .task-item {
            background: #0f172a;
            border: 1px solid #374151;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .task-item:hover {
            border-color: ${config.color};
            transform: translateY(-1px);
        }
        
        .task-title {
            color: #e5e7eb;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .task-meta {
            color: #9ca3af;
            font-size: 0.8rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .task-priority {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .priority-high {
            background: #ef4444;
            color: white;
        }
        
        .priority-medium {
            background: #f59e0b;
            color: white;
        }
        
        .priority-low {
            background: #6b7280;
            color: white;
        }
        
        .custom-task-form {
            background: #1e293b;
            border: 1px solid #374151;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            color: #e5e7eb;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            background: #0f172a;
            color: #e5e7eb;
            border: 1px solid #374151;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: ${config.color};
            box-shadow: 0 0 0 2px ${config.color}20;
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid #374151;
        }
        
        .btn-primary {
            background: ${config.color};
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: ${config.color}dd;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 1rem;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .navigation {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .nav-btn {
            background: #374151;
            color: #e5e7eb;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .nav-btn:hover {
            background: #4b5563;
        }
        
        .nav-btn.active {
            background: ${config.color};
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
        }
        
        .empty-state h4 {
            color: #e5e7eb;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div id="loader" class="loader">Loading ${config.name} Department...</div>
    
    <div class="app-container">
        <!-- Navigation -->
        <div class="navigation">
            <a href="../dashboard.html" class="nav-btn">← Dashboard</a>
            <a href="../kanban.html" class="nav-btn">Kanban View</a>
            <a href="../department_dashboard.html" class="nav-btn">All Departments</a>
        </div>
        
        <!-- Department Header -->
        <div class="department-header">
            <h1 class="department-title">${config.icon} ${config.name} Department</h1>
            <p class="department-subtitle">${config.subtitle}</p>
        </div>
        
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="total-tasks">0</div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="active-tasks">0</div>
                <div class="stat-label">Active Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="completed-tasks">0</div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="overdue-tasks">0</div>
                <div class="stat-label">Overdue</div>
            </div>
        </div>
        
        <!-- KPI Tracking Section -->
        <div class="kpi-section">
            <h2 class="section-title">
                📊 ${config.name} KPIs
            </h2>
            <div class="kpi-grid">
                ${kpiInputs}
            </div>
            <div class="quick-actions">
                <button class="quick-btn" onclick="createKPITask()">Create KPI Task</button>
                <button class="quick-btn secondary" onclick="clearKPIs()">Clear All</button>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="kpi-section">
            <h2 class="section-title">
                ⚡ Quick Actions
            </h2>
            <div class="quick-actions">
                ${quickActionButtons}
            </div>
        </div>
        
        <!-- Custom Task Creation -->
        <div class="custom-task-form">
            <h2 class="section-title">
                ➕ Create Custom Task
            </h2>
            <div class="form-grid">
                <div class="form-group">
                    <label>Task Title</label>
                    <input type="text" id="custom-title" placeholder="Enter task title">
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select id="custom-priority">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assignee</label>
                    <input type="text" id="custom-assignee" placeholder="${config.name} Team">
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" id="custom-due-date">
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="custom-description" placeholder="Enter task description"></textarea>
            </div>
            <div class="form-actions">
                <button class="btn-primary" onclick="createCustomTask()">Create Task</button>
                <button class="btn-secondary" onclick="clearForm()">Clear Form</button>
            </div>
        </div>
        
        <!-- Tasks by Status -->
        <div class="tasks-section">
            <div class="task-column">
                <div class="column-header">
                    <div class="column-title">Not Started</div>
                    <div class="task-count" id="not-started-count">0</div>
                </div>
                <div id="not-started-tasks">
                    <!-- Tasks will be populated here -->
                </div>
            </div>
            
            <div class="task-column">
                <div class="column-header">
                    <div class="column-title">In Progress</div>
                    <div class="task-count" id="in-progress-count">0</div>
                </div>
                <div id="in-progress-tasks">
                    <!-- Tasks will be populated here -->
                </div>
            </div>
            
            <div class="task-column">
                <div class="column-header">
                    <div class="column-title">Blocked</div>
                    <div class="task-count" id="blocked-count">0</div>
                </div>
                <div id="blocked-tasks">
                    <!-- Tasks will be populated here -->
                </div>
            </div>
            
            <div class="task-column">
                <div class="column-header">
                    <div class="column-title">Completed</div>
                    <div class="task-count" id="completed-count">0</div>
                </div>
                <div id="completed-tasks">
                    <!-- Tasks will be populated here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let fullData = { tasks: [], departments: [], users: [] };
        const department = '${deptKey}';
        
        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            hideLoader();
            loadData();
            setupEventListeners();
            renderDashboard();
        });

        function hideLoader() {
            document.getElementById('loader').style.display = 'none';
        }

        function setupEventListeners() {
            // Set default due date to next week
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            document.getElementById('custom-due-date').value = nextWeek.toISOString().split('T')[0];
        }

        function renderDashboard() {
            updateStats();
            renderTasks();
        }

        function updateStats() {
            const deptTasks = fullData.tasks.filter(task => task.department === department);
            
            document.getElementById('total-tasks').textContent = deptTasks.length;
            document.getElementById('active-tasks').textContent = deptTasks.filter(t => t.status === 'In Progress').length;
            document.getElementById('completed-tasks').textContent = deptTasks.filter(t => t.status === 'Completed').length;
            document.getElementById('overdue-tasks').textContent = deptTasks.filter(t => isOverdue(t.dueDate)).length;
        }

        function renderTasks() {
            const deptTasks = fullData.tasks.filter(task => task.department === department);
            const statuses = ['Not Started', 'In Progress', 'Blocked', 'Completed'];
            
            statuses.forEach(status => {
                const tasks = deptTasks.filter(task => task.status === status);
                const container = document.getElementById(\`\${status.toLowerCase().replace(' ', '-')}-tasks\`);
                const countElement = document.getElementById(\`\${status.toLowerCase().replace(' ', '-')}-count\`);
                
                countElement.textContent = tasks.length;
                
                if (tasks.length === 0) {
                    container.innerHTML = \`
                        <div class="empty-state">
                            <h4>No tasks</h4>
                            <p>No tasks in \${status.toLowerCase()} status</p>
                        </div>
                    \`;
                } else {
                    container.innerHTML = tasks.map(task => createTaskItem(task)).join('');
                }
            });
        }

        function createTaskItem(task) {
            const priorityClass = \`priority-\${task.priority.toLowerCase()}\`;
            const dueDate = task.dueDate ? new Date(task.dueDate).toLocaleDateString() : 'No due date';
            
            return \`
                <div class="task-item" onclick="viewTask('\${task.id}')">
                    <div class="task-title">\${task.title}</div>
                    <div class="task-meta">
                        <span>Due: \${dueDate}</span>
                        <span class="task-priority \${priorityClass}">\${task.priority}</span>
                    </div>
                </div>
            \`;
        }

        function createKPITask() {
            const kpiInputs = document.querySelectorAll('.kpi-input');
            const kpiData = {};
            let hasData = false;
            
            kpiInputs.forEach(input => {
                if (input.value.trim()) {
                    kpiData[input.dataset.kpi] = input.value;
                    hasData = true;
                }
            });
            
            if (!hasData) {
                alert('Please enter at least one KPI value');
                return;
            }
            
            const task = {
                id: generateTaskId(),
                title: '${config.name} KPI Update',
                description: \`KPI tracking for ${config.name} Department:\\n\\n\${Object.entries(kpiData).map(([key, value]) => \`\${key}: \${value}\`).join('\\n')}\`,
                department: department,
                status: 'In Progress',
                priority: 'Medium',
                assignee: '${config.name} Team',
                dueDate: new Date().toISOString().split('T')[0],
                createdDate: new Date().toISOString(),
                type: 'KPI',
                kpiData: kpiData,
                source: 'KPI Tracking'
            };
            
            fullData.tasks.push(task);
            saveData();
            renderDashboard();
            showSuccessMessage('KPI task created successfully');
            
            // Clear KPI inputs
            kpiInputs.forEach(input => input.value = '');
        }

        function createQuickTask(name) {
            const task = {
                id: generateTaskId(),
                title: name,
                description: \`Quick action task for ${config.name} Department\`,
                department: department,
                status: 'Not Started',
                priority: 'Medium',
                assignee: '${config.name} Team',
                dueDate: getNextWeekDate(),
                createdDate: new Date().toISOString(),
                type: 'Quick Action',
                source: 'Quick Action'
            };
            
            fullData.tasks.push(task);
            saveData();
            renderDashboard();
            showSuccessMessage(\`Created task: \${name}\`);
        }

        function createCustomTask() {
            const title = document.getElementById('custom-title').value.trim();
            const description = document.getElementById('custom-description').value.trim();
            const priority = document.getElementById('custom-priority').value;
            const assignee = document.getElementById('custom-assignee').value.trim() || '${config.name} Team';
            const dueDate = document.getElementById('custom-due-date').value;
            
            if (!title) {
                alert('Please enter a task title');
                return;
            }
            
            const task = {
                id: generateTaskId(),
                title: title,
                description: description,
                department: department,
                status: 'Not Started',
                priority: priority,
                assignee: assignee,
                dueDate: dueDate,
                createdDate: new Date().toISOString(),
                type: 'Custom',
                source: 'Custom Task'
            };
            
            fullData.tasks.push(task);
            saveData();
            renderDashboard();
            showSuccessMessage(\`Created task: \${title}\`);
            clearForm();
        }

        function clearForm() {
            document.getElementById('custom-title').value = '';
            document.getElementById('custom-description').value = '';
            document.getElementById('custom-priority').value = 'Medium';
            document.getElementById('custom-assignee').value = '';
            document.getElementById('custom-due-date').value = getNextWeekDate();
        }

        function clearKPIs() {
            document.querySelectorAll('.kpi-input').forEach(input => input.value = '');
        }

        function viewTask(taskId) {
            console.log(\`Viewing task: \${taskId}\`);
        }

        function generateTaskId() {
            return 'task-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        }

        function getNextWeekDate() {
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            return nextWeek.toISOString().split('T')[0];
        }

        function isOverdue(dueDate) {
            if (!dueDate) return false;
            return new Date(dueDate) < new Date();
        }

        function showSuccessMessage(message) {
            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = \`
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${config.color};
                color: white;
                padding: 15px 20px;
                border-radius: 6px;
                z-index: 1000;
                font-weight: 500;
            \`;
            messageDiv.textContent = message;
            document.body.appendChild(messageDiv);
            
            setTimeout(() => {
                document.body.removeChild(messageDiv);
            }, 3000);
        }

        function loadData() {
            fullData = {
                tasks: [],
                departments: [department],
                users: []
            };
        }

        function saveData() {
            console.log('Saving data:', fullData);
        }
    </script>
</body>
</html>`;
}

// Generate all remaining department pages
Object.entries(departmentConfigs).forEach(([deptKey, config]) => {
    const pageContent = generateDepartmentPage(deptKey, config);
    console.log(`Generated ${deptKey} department page`);
    // In a real implementation, you would write this to a file
    // For now, we'll just log that it was generated
});

console.log('All department pages generated successfully!');
