# TaskMaster Migration: Step-by-Step Implementation Guide
**Complete 7-Week Migration Plan with Daily Tasks**

---

# WEEK 1: Foundation & API Setup
*Goal: Transform Apps Script to API-first architecture*

## DAY 1: Environment Setup & Project Backup

### Morning (2-3 hours)
**1. Create Full Project Backup**
```bash
# Navigate to your project directory
cd "/Users/alexandermazzei2020/Documents/cursor projects/taskmasterdoneworking sept 16 backup"

# Create timestamped backup
cp -r . "../taskmaster-backup-$(date +%Y%m%d)"

# Verify backup
ls -la "../taskmaster-backup-$(date +%Y%m%d)"
```

**2. Install Development Tools**
```bash
# Check Node.js version (need 18+)
node --version

# If not installed or wrong version:
# Download from https://nodejs.org/ (LTS version)

# Verify npm
npm --version

# Install global tools
npm install -g @netlify/cli
```

**3. Apps Script Preparation**
```bash
# Install clasp for Apps Script deployment
npm install -g @google/clasp

# Login to Google (follow prompts)
clasp login

# Verify your current project
cat .clasp.json
```

### Afternoon (2-3 hours)
**4. Create New Frontend Project Structure**
```bash
# Create frontend directory
mkdir taskmaster-frontend
cd taskmaster-frontend

# Initialize React TypeScript project
npm create vite@latest . -- --template react-ts

# Install dependencies
npm install

# Install additional packages
npm install axios zustand react-router-dom
npm install -D tailwindcss postcss autoprefixer @types/node

# Initialize Tailwind
npx tailwindcss init -p
```

**5. Configure Tailwind CSS**
```typescript
// tailwind.config.js
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

```css
/* src/index.css */
@tailwind base;
@tailwind components;
@tailwind utilities;
```

**Testing Day 1:**
```bash
# Test frontend setup
npm run dev
# Should open localhost:5173 with Vite + React

# Test Apps Script access
clasp open
# Should open your Apps Script project
```

---

## DAY 2: Apps Script API Foundation

### Morning (3-4 hours)
**1. Add API Router to Code.js**

Add this to the TOP of your existing Code.js (after constants):

```javascript
// ===================================================================================
// |   API ROUTER - NEW SECTION - ADD TO TOP OF EXISTING CODE.JS                   |
// ===================================================================================

function doGet(e) {
  const action = e.parameter.action;

  // If no action specified, serve HTML (backward compatibility)
  if (!action) {
    return doGetOriginal(e);
  }

  // CORS headers for frontend requests
  const cors = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET,POST,OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type,Authorization'
  };

  // Handle preflight OPTIONS requests
  if (e.parameter.method === 'OPTIONS') {
    return jsonResponse({}, cors);
  }

  try {
    switch(action) {
      case 'getTasks':
        return jsonResponse(apiGetTasks(e.parameter), cors);
      case 'createTask':
        return jsonResponse(apiCreateTask(e.parameter), cors);
      case 'test':
        return jsonResponse({ message: 'API is working!', timestamp: new Date() }, cors);
      default:
        return jsonResponse({error: 'Unknown action: ' + action}, cors, 400);
    }
  } catch (error) {
    console.error('API Error:', error);
    return jsonResponse({
      error: 'Internal server error',
      message: error.message,
      action: action
    }, cors, 500);
  }
}

function doPost(e) {
  // Handle POST requests with JSON body
  let postData = {};
  try {
    postData = JSON.parse(e.postData.contents);
  } catch (error) {
    return jsonResponse({error: 'Invalid JSON in request body'}, {}, 400);
  }

  // Merge POST data with parameters and route to doGet
  const mockGet = {
    parameter: {
      ...e.parameter,
      ...postData,
      method: 'POST'
    }
  };
  return doGet(mockGet);
}

function jsonResponse(data, headers = {}, status = 200) {
  const response = {
    success: status < 400,
    data: data,
    status: status,
    timestamp: new Date().toISOString()
  };

  return ContentService
    .createTextOutput(JSON.stringify(response))
    .setMimeType(ContentService.MimeType.JSON)
    .setHeaders(headers);
}

// Rename existing doGet to maintain backward compatibility
function doGetOriginal(e) {
  // PASTE YOUR EXISTING doGet FUNCTION CONTENT HERE
  // (everything after "function doGet(e) {" and before the closing "}")

  // For now, return a simple HTML response
  return HtmlService.createHtmlOutput('<h1>Original HTML Interface Still Works</h1>');
}

// API Functions (implement these step by step)
function apiGetTasks(params) {
  const department = params.department;
  if (!department) {
    throw new Error('Department parameter required');
  }

  // For now, return dummy data
  return {
    department: department,
    tasks: [
      {
        id: '1',
        title: 'Sample Task',
        description: 'This is a test task from the API',
        assignee: 'Test User',
        priority: 'Medium',
        status: 'In Progress',
        department: department,
        createdDate: new Date().toISOString()
      }
    ],
    count: 1
  };
}

function apiCreateTask(params) {
  // For now, return success with dummy data
  return {
    id: 'new-' + Date.now(),
    title: params.title || 'New Task',
    message: 'Task created successfully (dummy response)'
  };
}
```

### Afternoon (2-3 hours)
**2. Deploy and Test API**

```bash
# Deploy to Apps Script
clasp push

# Test the API
curl "https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec?action=test"
# Should return: {"success":true,"data":{"message":"API is working!",...},...}

# Test getTasks endpoint
curl "https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec?action=getTasks&department=sales"
# Should return sample task data
```

**3. Create API Client in Frontend**

```typescript
// src/config/environment.ts
export const config = {
  appsScriptUrl: 'https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec',
  isDevelopment: import.meta.env.DEV,
  apiTimeout: 10000,
};
```

```typescript
// src/services/api.ts
import axios, { AxiosError } from 'axios';
import { config } from '../config/environment';

const api = axios.create({
  baseURL: config.appsScriptUrl,
  timeout: config.apiTimeout,
});

// Request interceptor
api.interceptors.request.use(
  (config) => {
    console.log(`🚀 API Request: ${config.method?.toUpperCase()} ${config.url}`);
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor
api.interceptors.response.use(
  (response) => {
    console.log(`✅ API Response:`, response.data);
    if (response.data && !response.data.success) {
      throw new Error(response.data.data?.error || 'API request failed');
    }
    return response;
  },
  (error: AxiosError) => {
    console.error(`❌ API Error:`, error);
    if (error.code === 'ECONNABORTED') {
      throw new Error('Request timeout - please try again');
    }
    if (!error.response) {
      throw new Error('Network error - please check your connection');
    }
    throw new Error(error.response.data?.error || 'Request failed');
  }
);

export const taskAPI = {
  test: () => api.get('?action=test'),

  getTasks: (department: string) =>
    api.get(`?action=getTasks&department=${encodeURIComponent(department)}`),

  createTask: (task: any) =>
    api.post('?action=createTask', task),
};
```

**Testing Day 2:**
```bash
# Test API from command line
curl "YOUR_APPS_SCRIPT_URL?action=test"

# Test from frontend
npm run dev
# Open browser console and run:
# fetch('YOUR_APPS_SCRIPT_URL?action=test').then(r => r.json()).then(console.log)
```

---

## DAY 3: Basic React Components

### Morning (3-4 hours)
**1. Create TypeScript Types**

```typescript
// src/types/index.ts
export interface Task {
  id: string;
  title: string;
  description: string;
  assignee: string;
  priority: 'High' | 'Medium' | 'Low';
  status: 'Not Started' | 'In Progress' | 'Completed';
  department: string;
  dueDate?: string;
  createdDate: string;
  avatarUrl?: string;
}

export interface Department {
  id: string;
  name: string;
  icon: string;
  color: string;
  gradient: string;
}

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  status: number;
  timestamp: string;
}
```

**2. Department Configuration**

```typescript
// src/config/departments.ts
import { Department } from '../types';

export const DEPARTMENTS: Record<string, Department> = {
  sales: {
    id: 'sales',
    name: 'Sales',
    icon: '📞',
    color: '#3b82f6',
    gradient: 'linear-gradient(135deg, #3b82f6, #2563eb)'
  },
  accounting: {
    id: 'accounting',
    name: 'Accounting',
    icon: '💰',
    color: '#10b981',
    gradient: 'linear-gradient(135deg, #10b981, #059669)'
  },
  tech: {
    id: 'tech',
    name: 'Tech',
    icon: '💻',
    color: '#8b5cf6',
    gradient: 'linear-gradient(135deg, #8b5cf6, #7c3aed)'
  },
  marketing: {
    id: 'marketing',
    name: 'Marketing',
    icon: '🎯',
    color: '#f59e0b',
    gradient: 'linear-gradient(135deg, #f59e0b, #d97706)'
  },
  hr: {
    id: 'hr',
    name: 'HR',
    icon: '👥',
    color: '#ef4444',
    gradient: 'linear-gradient(135deg, #ef4444, #dc2626)'
  }
};
```

### Afternoon (3-4 hours)
**3. Create Basic Components**

```tsx
// src/components/shared/TaskCard.tsx
import React, { useState } from 'react';
import { Task, Department } from '../../types';

interface TaskCardProps {
  task: Task;
  department: Department;
  onUpdate?: (task: Task) => void;
  onDelete?: (id: string) => void;
}

export const TaskCard: React.FC<TaskCardProps> = ({
  task,
  department,
  onUpdate,
  onDelete
}) => {
  const [isEditing, setIsEditing] = useState(false);

  const priorityColors = {
    High: 'bg-red-100 text-red-800 border-red-200',
    Medium: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    Low: 'bg-green-100 text-green-800 border-green-200',
  };

  const statusColors = {
    'Not Started': 'bg-gray-100 text-gray-800',
    'In Progress': 'bg-blue-100 text-blue-800',
    'Completed': 'bg-green-100 text-green-800',
  };

  return (
    <div
      className="bg-white rounded-lg shadow-md border-l-4 p-6 hover:shadow-lg transition-shadow"
      style={{ borderLeftColor: department.color }}
    >
      <div className="flex justify-between items-start mb-4">
        <h3 className="text-lg font-semibold text-gray-900">{task.title}</h3>
        <div className="flex space-x-2">
          <span className={`px-2 py-1 rounded-full text-xs font-medium ${priorityColors[task.priority]}`}>
            {task.priority}
          </span>
          <span className={`px-2 py-1 rounded-full text-xs font-medium ${statusColors[task.status]}`}>
            {task.status}
          </span>
        </div>
      </div>

      <p className="text-gray-600 mb-4">{task.description}</p>

      <div className="flex justify-between items-center">
        <div className="text-sm text-gray-500">
          <p>Assigned to: <span className="font-medium">{task.assignee}</span></p>
          <p>Created: {new Date(task.createdDate).toLocaleDateString()}</p>
        </div>

        {(onUpdate || onDelete) && (
          <div className="flex space-x-2">
            {onUpdate && (
              <button
                onClick={() => setIsEditing(true)}
                className="px-3 py-1 text-sm bg-blue-500 text-white rounded hover:bg-blue-600"
              >
                Edit
              </button>
            )}
            {onDelete && (
              <button
                onClick={() => onDelete(task.id)}
                className="px-3 py-1 text-sm bg-red-500 text-white rounded hover:bg-red-600"
              >
                Delete
              </button>
            )}
          </div>
        )}
      </div>
    </div>
  );
};
```

```tsx
// src/components/layout/DepartmentHeader.tsx
import React from 'react';
import { Department } from '../../types';

interface DepartmentHeaderProps {
  department: Department;
  taskCount?: number;
}

export const DepartmentHeader: React.FC<DepartmentHeaderProps> = ({
  department,
  taskCount = 0
}) => {
  return (
    <div
      className="text-white p-8 rounded-lg mb-6 text-center"
      style={{ background: department.gradient }}
    >
      <div className="text-4xl mb-2">{department.icon}</div>
      <h1 className="text-3xl font-bold mb-2">{department.name}</h1>
      <p className="text-lg opacity-90">
        {taskCount} {taskCount === 1 ? 'task' : 'tasks'} active
      </p>
    </div>
  );
};
```

**Testing Day 3:**
```bash
# Test component rendering
npm run dev
# Components should render without errors

# Test TypeScript compilation
npm run build
# Should compile without type errors
```

---

## DAY 4: State Management & API Integration

### Morning (3-4 hours)
**1. Create Zustand Store**

```typescript
// src/stores/taskStore.ts
import { create } from 'zustand';
import { Task } from '../types';
import { taskAPI } from '../services/api';

interface TaskStore {
  tasks: Task[];
  loading: boolean;
  error: string | null;

  // Actions
  fetchTasks: (department: string) => Promise<void>;
  createTask: (task: Partial<Task>) => Promise<void>;
  updateTask: (id: string, updates: Partial<Task>) => Promise<void>;
  deleteTask: (id: string) => Promise<void>;
  clearError: () => void;
}

export const useTaskStore = create<TaskStore>((set, get) => ({
  tasks: [],
  loading: false,
  error: null,

  fetchTasks: async (department: string) => {
    set({ loading: true, error: null });
    try {
      const response = await taskAPI.getTasks(department);
      set({
        tasks: response.data.data.tasks || [],
        loading: false
      });
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : 'Failed to fetch tasks',
        loading: false
      });
    }
  },

  createTask: async (taskData: Partial<Task>) => {
    const tempId = `temp-${Date.now()}`;
    const tempTask: Task = {
      id: tempId,
      title: taskData.title || 'New Task',
      description: taskData.description || '',
      assignee: taskData.assignee || 'Unassigned',
      priority: taskData.priority || 'Medium',
      status: taskData.status || 'Not Started',
      department: taskData.department || '',
      createdDate: new Date().toISOString(),
      ...taskData
    };

    // Optimistic update
    set(state => ({
      tasks: [...state.tasks, tempTask],
      error: null
    }));

    try {
      const response = await taskAPI.createTask(taskData);
      const newTask = response.data.data;

      // Replace temp task with real task
      set(state => ({
        tasks: state.tasks.map(t => t.id === tempId ? newTask : t)
      }));
    } catch (error) {
      // Rollback optimistic update
      set(state => ({
        tasks: state.tasks.filter(t => t.id !== tempId),
        error: error instanceof Error ? error.message : 'Failed to create task'
      }));
    }
  },

  updateTask: async (id: string, updates: Partial<Task>) => {
    const originalTasks = get().tasks;

    // Optimistic update
    set(state => ({
      tasks: state.tasks.map(t => t.id === id ? { ...t, ...updates } : t),
      error: null
    }));

    try {
      await taskAPI.updateTask({ id, ...updates });
    } catch (error) {
      // Rollback on error
      set({
        tasks: originalTasks,
        error: error instanceof Error ? error.message : 'Failed to update task'
      });
    }
  },

  deleteTask: async (id: string) => {
    const originalTasks = get().tasks;

    // Optimistic update
    set(state => ({
      tasks: state.tasks.filter(t => t.id !== id),
      error: null
    }));

    try {
      await taskAPI.deleteTask(id);
    } catch (error) {
      // Rollback on error
      set({
        tasks: originalTasks,
        error: error instanceof Error ? error.message : 'Failed to delete task'
      });
    }
  },

  clearError: () => set({ error: null })
}));
```

### Afternoon (3-4 hours)
**2. Create Department Page**

```tsx
// src/pages/DepartmentPage.tsx
import React, { useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { useTaskStore } from '../stores/taskStore';
import { TaskCard } from '../components/shared/TaskCard';
import { DepartmentHeader } from '../components/layout/DepartmentHeader';
import { DEPARTMENTS } from '../config/departments';

export const DepartmentPage: React.FC = () => {
  const { departmentId } = useParams<{ departmentId: string }>();
  const { tasks, loading, error, fetchTasks, updateTask, deleteTask, clearError } = useTaskStore();

  const department = departmentId ? DEPARTMENTS[departmentId] : null;

  useEffect(() => {
    if (departmentId) {
      fetchTasks(departmentId);
    }
  }, [departmentId, fetchTasks]);

  if (!department) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-red-600">Department Not Found</h1>
          <p className="text-gray-600 mt-2">The department "{departmentId}" does not exist.</p>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="container mx-auto px-4 py-8">
        <DepartmentHeader department={department} />
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading tasks...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <DepartmentHeader department={department} taskCount={tasks.length} />

      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
          <div className="flex justify-between items-center">
            <span>{error}</span>
            <button
              onClick={clearError}
              className="text-red-500 hover:text-red-700"
            >
              ✕
            </button>
          </div>
        </div>
      )}

      {tasks.length === 0 ? (
        <div className="text-center py-12">
          <div className="text-6xl mb-4">📝</div>
          <h2 className="text-xl font-semibold text-gray-600 mb-2">No tasks yet</h2>
          <p className="text-gray-500">Create your first task to get started!</p>
        </div>
      ) : (
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {tasks.map(task => (
            <TaskCard
              key={task.id}
              task={task}
              department={department}
              onUpdate={(updatedTask) => updateTask(task.id, updatedTask)}
              onDelete={deleteTask}
            />
          ))}
        </div>
      )}
    </div>
  );
};
```

**Testing Day 4:**
```bash
# Test state management
npm run dev

# Test API integration in browser console:
# useTaskStore.getState().fetchTasks('sales')

# Test error handling by using invalid department:
# useTaskStore.getState().fetchTasks('invalid')
```

---

## DAY 5: Routing & Integration Testing

### Morning (2-3 hours)
**1. Set up React Router**

```tsx
// src/App.tsx
import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { DepartmentPage } from './pages/DepartmentPage';
import { HomePage } from './pages/HomePage';

function App() {
  return (
    <Router>
      <div className="min-h-screen bg-gray-50">
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/department/:departmentId" element={<DepartmentPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </div>
    </Router>
  );
}

export default App;
```

```tsx
// src/pages/HomePage.tsx
import React from 'react';
import { Link } from 'react-router-dom';
import { DEPARTMENTS } from '../config/departments';

export const HomePage: React.FC = () => {
  return (
    <div className="container mx-auto px-4 py-8">
      <header className="text-center mb-12">
        <h1 className="text-4xl font-bold text-gray-900 mb-4">TaskMaster</h1>
        <p className="text-xl text-gray-600">Choose your department to manage tasks</p>
      </header>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl mx-auto">
        {Object.values(DEPARTMENTS).map(department => (
          <Link
            key={department.id}
            to={`/department/${department.id}`}
            className="block p-6 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow border-l-4"
            style={{ borderLeftColor: department.color }}
          >
            <div className="text-center">
              <div className="text-4xl mb-3">{department.icon}</div>
              <h2 className="text-xl font-semibold text-gray-900 mb-2">
                {department.name}
              </h2>
              <p className="text-gray-600">Manage {department.name.toLowerCase()} tasks</p>
            </div>
          </Link>
        ))}
      </div>

      <div className="mt-12 text-center">
        <div className="bg-blue-50 rounded-lg p-6 max-w-2xl mx-auto">
          <h3 className="text-lg font-semibold text-blue-900 mb-2">🚧 Migration in Progress</h3>
          <p className="text-blue-700">
            This is the new React interface. The original system is still available as a fallback.
          </p>
        </div>
      </div>
    </div>
  );
};
```

### Afternoon (3-4 hours)
**2. Create Test Suite**

```bash
# Install testing dependencies
npm install -D vitest @testing-library/react @testing-library/jest-dom jsdom
```

```typescript
// vite.config.ts
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
  },
})
```

```typescript
// src/test/setup.ts
import '@testing-library/jest-dom'
```

```typescript
// src/components/shared/__tests__/TaskCard.test.tsx
import { render, screen } from '@testing-library/react';
import { TaskCard } from '../TaskCard';
import { Task, Department } from '../../../types';

const mockTask: Task = {
  id: '1',
  title: 'Test Task',
  description: 'Test Description',
  assignee: 'Test User',
  priority: 'High',
  status: 'In Progress',
  department: 'sales',
  createdDate: '2024-01-01T00:00:00Z'
};

const mockDepartment: Department = {
  id: 'sales',
  name: 'Sales',
  icon: '📞',
  color: '#3b82f6',
  gradient: 'linear-gradient(135deg, #3b82f6, #2563eb)'
};

describe('TaskCard', () => {
  test('renders task information correctly', () => {
    render(<TaskCard task={mockTask} department={mockDepartment} />);

    expect(screen.getByText('Test Task')).toBeInTheDocument();
    expect(screen.getByText('Test Description')).toBeInTheDocument();
    expect(screen.getByText('Test User')).toBeInTheDocument();
    expect(screen.getByText('High')).toBeInTheDocument();
    expect(screen.getByText('In Progress')).toBeInTheDocument();
  });

  test('renders edit and delete buttons when handlers provided', () => {
    const mockUpdate = vi.fn();
    const mockDelete = vi.fn();

    render(
      <TaskCard
        task={mockTask}
        department={mockDepartment}
        onUpdate={mockUpdate}
        onDelete={mockDelete}
      />
    );

    expect(screen.getByText('Edit')).toBeInTheDocument();
    expect(screen.getByText('Delete')).toBeInTheDocument();
  });
});
```

**3. Comprehensive Testing**

```bash
# Run tests
npm run test

# Test API endpoints manually
curl "https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec?action=test"
curl "https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec?action=getTasks&department=sales"

# Test frontend
npm run dev
# Navigate to different departments
# Test error states by modifying API responses
```

**End of Week 1 Validation:**
- ✅ API endpoints responding correctly
- ✅ Frontend components rendering
- ✅ State management working
- ✅ Routing functional
- ✅ Basic tests passing
- ✅ Error handling working

---

# WEEK 2-3: Core Features Implementation
*Goal: Implement full CRUD operations and department-specific features*

## Week 2 Daily Breakdown

### DAY 6 (Monday): Real API Integration

**Morning: Connect to Real Data (3-4 hours)**

**1. Update apiGetTasks to use real Google Sheets data:**

```javascript
// Replace the dummy apiGetTasks function in Code.js with real implementation
function apiGetTasks(params) {
  const department = params.department;
  if (!department) {
    throw new Error('Department parameter required');
  }

  try {
    // Use your existing function logic but return in API format
    const tasks = getTasksByDepartment(department); // Your existing function

    return {
      department: department,
      tasks: tasks.map(task => ({
        id: task.id || `task-${Date.now()}-${Math.random()}`,
        title: task.title || task.taskTitle || '',
        description: task.description || task.details || '',
        assignee: task.assignee || task.assignedTo || 'Unassigned',
        priority: task.priority || 'Medium',
        status: task.status || 'Not Started',
        department: department,
        dueDate: task.dueDate || task.deadline,
        createdDate: task.createdDate || task.dateCreated || new Date().toISOString(),
        avatarUrl: task.avatarUrl || null
      })),
      count: tasks.length,
      lastUpdated: new Date().toISOString()
    };
  } catch (error) {
    console.error('Error in apiGetTasks:', error);
    throw new Error('Failed to fetch tasks: ' + error.message);
  }
}
```

**2. Update apiCreateTask to use real sheet operations:**

```javascript
function apiCreateTask(params) {
  try {
    // Validate required fields
    if (!params.title || !params.department) {
      throw new Error('Title and department are required');
    }

    // Create task object matching your sheet structure
    const newTask = {
      title: params.title,
      description: params.description || '',
      assignee: params.assignee || 'Unassigned',
      priority: params.priority || 'Medium',
      status: params.status || 'Not Started',
      department: params.department,
      dueDate: params.dueDate || '',
      createdDate: new Date().toISOString(),
      createdBy: params.createdBy || 'System'
    };

    // Use your existing createTask function
    const result = createTask(newTask); // Your existing function

    return {
      id: result.id || `task-${Date.now()}`,
      ...newTask,
      message: 'Task created successfully'
    };
  } catch (error) {
    console.error('Error in apiCreateTask:', error);
    throw new Error('Failed to create task: ' + error.message);
  }
}
```

**3. Add apiUpdateTask and apiDeleteTask:**

```javascript
function apiUpdateTask(params) {
  try {
    if (!params.id) {
      throw new Error('Task ID is required');
    }

    // Use your existing updateTask function
    const result = updateTask(params.id, params); // Your existing function

    return {
      id: params.id,
      message: 'Task updated successfully',
      updatedFields: Object.keys(params).filter(key => key !== 'id')
    };
  } catch (error) {
    console.error('Error in apiUpdateTask:', error);
    throw new Error('Failed to update task: ' + error.message);
  }
}

function apiDeleteTask(params) {
  try {
    if (!params.id) {
      throw new Error('Task ID is required');
    }

    // Use your existing deleteTask function
    const result = deleteTask(params.id); // Your existing function

    return {
      id: params.id,
      message: 'Task deleted successfully'
    };
  } catch (error) {
    console.error('Error in apiDeleteTask:', error);
    throw new Error('Failed to delete task: ' + error.message);
  }
}
```

**4. Update API router to handle new endpoints:**

```javascript
// Update the switch statement in doGet function
switch(action) {
  case 'getTasks':
    return jsonResponse(apiGetTasks(e.parameter), cors);
  case 'createTask':
    return jsonResponse(apiCreateTask(e.parameter), cors);
  case 'updateTask':
    return jsonResponse(apiUpdateTask(e.parameter), cors);
  case 'deleteTask':
    return jsonResponse(apiDeleteTask(e.parameter), cors);
  case 'test':
    return jsonResponse({ message: 'API is working!', timestamp: new Date() }, cors);
  default:
    return jsonResponse({error: 'Unknown action: ' + action}, cors, 400);
}
```

**Afternoon: Frontend API Integration (3-4 hours)**

**5. Update API client with all endpoints:**

```typescript
// Update src/services/api.ts
export const taskAPI = {
  test: () => api.get('?action=test'),

  getTasks: (department: string) =>
    api.get(`?action=getTasks&department=${encodeURIComponent(department)}`),

  createTask: (task: Partial<Task>) =>
    api.post('?action=createTask', task),

  updateTask: (task: Partial<Task> & { id: string }) =>
    api.post('?action=updateTask', task),

  deleteTask: (id: string) =>
    api.get(`?action=deleteTask&id=${encodeURIComponent(id)}`),
};
```

**6. Test with real data:**

```bash
# Deploy updated Apps Script
clasp push

# Test each endpoint
curl "YOUR_APPS_SCRIPT_URL?action=getTasks&department=sales"
curl -X POST "YOUR_APPS_SCRIPT_URL" -d '{"action":"createTask","title":"Test API Task","department":"sales"}'

# Test in frontend
npm run dev
# Navigate to /department/sales and verify real tasks load
```

---

### DAY 7 (Tuesday): Task Creation & Editing

**Morning: Task Creation Form (3-4 hours)**

**1. Create TaskCreateModal component:**

```tsx
// src/components/modals/TaskCreateModal.tsx
import React, { useState } from 'react';
import { Task, Department } from '../../types';
import { useTaskStore } from '../../stores/taskStore';

interface TaskCreateModalProps {
  isOpen: boolean;
  onClose: () => void;
  department: Department;
}

export const TaskCreateModal: React.FC<TaskCreateModalProps> = ({
  isOpen,
  onClose,
  department
}) => {
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    assignee: '',
    priority: 'Medium' as Task['priority'],
    status: 'Not Started' as Task['status'],
    dueDate: ''
  });
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { createTask } = useTaskStore();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!formData.title.trim()) return;

    setIsSubmitting(true);
    try {
      await createTask({
        ...formData,
        department: department.id,
        title: formData.title.trim(),
        description: formData.description.trim()
      });

      // Reset form and close modal
      setFormData({
        title: '',
        description: '',
        assignee: '',
        priority: 'Medium',
        status: 'Not Started',
        dueDate: ''
      });
      onClose();
    } catch (error) {
      console.error('Failed to create task:', error);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-semibold text-gray-900">
            Create New Task - {department.name}
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            ✕
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Title *
            </label>
            <input
              type="text"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter task title"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Description
            </label>
            <textarea
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              rows={3}
              placeholder="Enter task description"
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Priority
              </label>
              <select
                value={formData.priority}
                onChange={(e) => setFormData({ ...formData, priority: e.target.value as Task['priority'] })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Status
              </label>
              <select
                value={formData.status}
                onChange={(e) => setFormData({ ...formData, status: e.target.value as Task['status'] })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="Not Started">Not Started</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Assignee
            </label>
            <input
              type="text"
              value={formData.assignee}
              onChange={(e) => setFormData({ ...formData, assignee: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter assignee name"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Due Date
            </label>
            <input
              type="date"
              value={formData.dueDate}
              onChange={(e) => setFormData({ ...formData, dueDate: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div className="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
              disabled={isSubmitting}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              disabled={isSubmitting || !formData.title.trim()}
            >
              {isSubmitting ? 'Creating...' : 'Create Task'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
```

**Afternoon: Task Editing (3-4 hours)**

**2. Create TaskEditModal component:**

```tsx
// src/components/modals/TaskEditModal.tsx
import React, { useState, useEffect } from 'react';
import { Task, Department } from '../../types';
import { useTaskStore } from '../../stores/taskStore';

interface TaskEditModalProps {
  isOpen: boolean;
  onClose: () => void;
  task: Task | null;
  department: Department;
}

export const TaskEditModal: React.FC<TaskEditModalProps> = ({
  isOpen,
  onClose,
  task,
  department
}) => {
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    assignee: '',
    priority: 'Medium' as Task['priority'],
    status: 'Not Started' as Task['status'],
    dueDate: ''
  });
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { updateTask } = useTaskStore();

  useEffect(() => {
    if (task) {
      setFormData({
        title: task.title,
        description: task.description,
        assignee: task.assignee,
        priority: task.priority,
        status: task.status,
        dueDate: task.dueDate || ''
      });
    }
  }, [task]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!task || !formData.title.trim()) return;

    setIsSubmitting(true);
    try {
      await updateTask(task.id, {
        ...formData,
        title: formData.title.trim(),
        description: formData.description.trim()
      });
      onClose();
    } catch (error) {
      console.error('Failed to update task:', error);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen || !task) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-semibold text-gray-900">
            Edit Task - {department.name}
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            ✕
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Same form fields as TaskCreateModal but with pre-filled values */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Title *
            </label>
            <input
              type="text"
              value={formData.title}
              onChange={(e) => setFormData({ ...formData, title: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Description
            </label>
            <textarea
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              rows={3}
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Priority
              </label>
              <select
                value={formData.priority}
                onChange={(e) => setFormData({ ...formData, priority: e.target.value as Task['priority'] })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="Low">Low</option>
                <option value="Medium">Medium</option>
                <option value="High">High</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Status
              </label>
              <select
                value={formData.status}
                onChange={(e) => setFormData({ ...formData, status: e.target.value as Task['status'] })}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="Not Started">Not Started</option>
                <option value="In Progress">In Progress</option>
                <option value="Completed">Completed</option>
              </select>
            </div>
          </div>

          <div className="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
              disabled={isSubmitting}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              disabled={isSubmitting || !formData.title.trim()}
            >
              {isSubmitting ? 'Updating...' : 'Update Task'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
```

**3. Update DepartmentPage to include modals:**

```tsx
// Update src/pages/DepartmentPage.tsx
import React, { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTaskStore } from '../stores/taskStore';
import { TaskCard } from '../components/shared/TaskCard';
import { DepartmentHeader } from '../components/layout/DepartmentHeader';
import { TaskCreateModal } from '../components/modals/TaskCreateModal';
import { TaskEditModal } from '../components/modals/TaskEditModal';
import { DEPARTMENTS } from '../config/departments';
import { Task } from '../types';

export const DepartmentPage: React.FC = () => {
  const { departmentId } = useParams<{ departmentId: string }>();
  const { tasks, loading, error, fetchTasks, updateTask, deleteTask, clearError } = useTaskStore();
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [editingTask, setEditingTask] = useState<Task | null>(null);

  const department = departmentId ? DEPARTMENTS[departmentId] : null;

  useEffect(() => {
    if (departmentId) {
      fetchTasks(departmentId);
    }
  }, [departmentId, fetchTasks]);

  const handleDeleteTask = async (taskId: string) => {
    if (window.confirm('Are you sure you want to delete this task?')) {
      await deleteTask(taskId);
    }
  };

  if (!department) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-red-600">Department Not Found</h1>
          <p className="text-gray-600 mt-2">The department "{departmentId}" does not exist.</p>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="container mx-auto px-4 py-8">
        <DepartmentHeader department={department} />
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading tasks...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <DepartmentHeader department={department} taskCount={tasks.length} />

      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
          <div className="flex justify-between items-center">
            <span>{error}</span>
            <button onClick={clearError} className="text-red-500 hover:text-red-700">
              ✕
            </button>
          </div>
        </div>
      )}

      <div className="mb-6">
        <button
          onClick={() => setIsCreateModalOpen(true)}
          className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
        >
          + Create New Task
        </button>
      </div>

      {tasks.length === 0 ? (
        <div className="text-center py-12">
          <div className="text-6xl mb-4">📝</div>
          <h2 className="text-xl font-semibold text-gray-600 mb-2">No tasks yet</h2>
          <p className="text-gray-500">Create your first task to get started!</p>
        </div>
      ) : (
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {tasks.map(task => (
            <TaskCard
              key={task.id}
              task={task}
              department={department}
              onUpdate={(updatedTask) => setEditingTask(updatedTask)}
              onDelete={handleDeleteTask}
            />
          ))}
        </div>
      )}

      <TaskCreateModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        department={department}
      />

      <TaskEditModal
        isOpen={!!editingTask}
        onClose={() => setEditingTask(null)}
        task={editingTask}
        department={department}
      />
    </div>
  );
};
```

**Testing Day 7:**
```bash
# Test task creation
npm run dev
# Go to any department, click "Create New Task", fill form, submit

# Test task editing
# Click edit on any task, modify fields, save

# Test validation
# Try submitting empty forms, check error handling
```

---

### DAY 8 (Wednesday): Avatar System Integration

**Morning: Avatar API Integration (3-4 hours)**

**1. Add avatar endpoints to Apps Script:**

```javascript
// Add to Code.js
function apiGetAvatars(params) {
  try {
    const avatars = getAllAvatars(); // Your existing function

    return {
      avatars: avatars.map(avatar => ({
        id: avatar.id || avatar.email,
        name: avatar.name || avatar.displayName,
        email: avatar.email,
        avatar: avatar.avatar || avatar.avatarUrl,
        department: avatar.department,
        role: avatar.role || 'Member'
      })),
      count: avatars.length,
      lastUpdated: new Date().toISOString()
    };
  } catch (error) {
    console.error('Error in apiGetAvatars:', error);
    throw new Error('Failed to fetch avatars: ' + error.message);
  }
}

function apiAssignAvatar(params) {
  try {
    if (!params.taskId || !params.avatarId) {
      throw new Error('Task ID and Avatar ID are required');
    }

    // Use your existing avatar assignment logic
    const result = assignAvatarToTask(params.taskId, params.avatarId);

    return {
      taskId: params.taskId,
      avatarId: params.avatarId,
      message: 'Avatar assigned successfully'
    };
  } catch (error) {
    console.error('Error in apiAssignAvatar:', error);
    throw new Error('Failed to assign avatar: ' + error.message);
  }
}

// Update the API router
switch(action) {
  case 'getTasks':
    return jsonResponse(apiGetTasks(e.parameter), cors);
  case 'createTask':
    return jsonResponse(apiCreateTask(e.parameter), cors);
  case 'updateTask':
    return jsonResponse(apiUpdateTask(e.parameter), cors);
  case 'deleteTask':
    return jsonResponse(apiDeleteTask(e.parameter), cors);
  case 'getAvatars':
    return jsonResponse(apiGetAvatars(e.parameter), cors);
  case 'assignAvatar':
    return jsonResponse(apiAssignAvatar(e.parameter), cors);
  case 'test':
    return jsonResponse({ message: 'API is working!', timestamp: new Date() }, cors);
  default:
    return jsonResponse({error: 'Unknown action: ' + action}, cors, 400);
}
```

**2. Create Avatar types and store:**

```typescript
// Add to src/types/index.ts
export interface Avatar {
  id: string;
  name: string;
  email: string;
  avatar: string;
  department?: string;
  role: string;
}
```

```typescript
// src/stores/avatarStore.ts
import { create } from 'zustand';
import { Avatar } from '../types';
import { taskAPI } from '../services/api';

interface AvatarStore {
  avatars: Avatar[];
  loading: boolean;
  error: string | null;

  fetchAvatars: () => Promise<void>;
  assignAvatar: (taskId: string, avatarId: string) => Promise<void>;
  clearError: () => void;
}

export const useAvatarStore = create<AvatarStore>((set, get) => ({
  avatars: [],
  loading: false,
  error: null,

  fetchAvatars: async () => {
    set({ loading: true, error: null });
    try {
      const response = await taskAPI.getAvatars();
      set({
        avatars: response.data.data.avatars || [],
        loading: false
      });
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : 'Failed to fetch avatars',
        loading: false
      });
    }
  },

  assignAvatar: async (taskId: string, avatarId: string) => {
    try {
      await taskAPI.assignAvatar(taskId, avatarId);
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : 'Failed to assign avatar'
      });
      throw error;
    }
  },

  clearError: () => set({ error: null })
}));
```

**Afternoon: Avatar Assignment UI (3-4 hours)**

**3. Create AvatarSelector component:**

```tsx
// src/components/shared/AvatarSelector.tsx
import React, { useEffect, useState } from 'react';
import { useAvatarStore } from '../../stores/avatarStore';
import { Avatar } from '../../types';

interface AvatarSelectorProps {
  onSelect: (avatar: Avatar) => void;
  selectedAvatarId?: string;
  department?: string;
}

export const AvatarSelector: React.FC<AvatarSelectorProps> = ({
  onSelect,
  selectedAvatarId,
  department
}) => {
  const { avatars, loading, error, fetchAvatars } = useAvatarStore();
  const [isOpen, setIsOpen] = useState(false);

  useEffect(() => {
    if (avatars.length === 0) {
      fetchAvatars();
    }
  }, [avatars.length, fetchAvatars]);

  const filteredAvatars = department
    ? avatars.filter(avatar => !avatar.department || avatar.department === department)
    : avatars;

  const selectedAvatar = avatars.find(a => a.id === selectedAvatarId);

  if (loading) {
    return <div className="text-sm text-gray-500">Loading avatars...</div>;
  }

  if (error) {
    return <div className="text-sm text-red-500">Error loading avatars</div>;
  }

  return (
    <div className="relative">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center space-x-2 px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50"
      >
        {selectedAvatar ? (
          <>
            <img
              src={selectedAvatar.avatar}
              alt={selectedAvatar.name}
              className="w-6 h-6 rounded-full"
              onError={(e) => {
                const target = e.target as HTMLImageElement;
                target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(selectedAvatar.name)}&background=random`;
              }}
            />
            <span className="text-sm">{selectedAvatar.name}</span>
          </>
        ) : (
          <>
            <div className="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center">
              <span className="text-xs text-gray-500">?</span>
            </div>
            <span className="text-sm text-gray-500">Select Avatar</span>
          </>
        )}
        <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {isOpen && (
        <div className="absolute z-10 mt-1 w-64 bg-white border border-gray-300 rounded-md shadow-lg">
          <div className="max-h-60 overflow-y-auto">
            {filteredAvatars.map(avatar => (
              <button
                key={avatar.id}
                onClick={() => {
                  onSelect(avatar);
                  setIsOpen(false);
                }}
                className="w-full flex items-center space-x-3 px-3 py-2 hover:bg-gray-50 text-left"
              >
                <img
                  src={avatar.avatar}
                  alt={avatar.name}
                  className="w-8 h-8 rounded-full"
                  onError={(e) => {
                    const target = e.target as HTMLImageElement;
                    target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(avatar.name)}&background=random`;
                  }}
                />
                <div>
                  <div className="text-sm font-medium text-gray-900">{avatar.name}</div>
                  <div className="text-xs text-gray-500">{avatar.email}</div>
                </div>
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};
```

**4. Update TaskCard to show avatar:**

```tsx
// Update src/components/shared/TaskCard.tsx
import React, { useState } from 'react';
import { Task, Department, Avatar } from '../../types';
import { AvatarSelector } from './AvatarSelector';
import { useAvatarStore } from '../../stores/avatarStore';

interface TaskCardProps {
  task: Task;
  department: Department;
  onUpdate?: (task: Task) => void;
  onDelete?: (id: string) => void;
}

export const TaskCard: React.FC<TaskCardProps> = ({
  task,
  department,
  onUpdate,
  onDelete
}) => {
  const [showAvatarSelector, setShowAvatarSelector] = useState(false);
  const { assignAvatar } = useAvatarStore();

  const handleAvatarSelect = async (avatar: Avatar) => {
    try {
      await assignAvatar(task.id, avatar.id);
      if (onUpdate) {
        onUpdate({ ...task, assignee: avatar.name, avatarUrl: avatar.avatar });
      }
    } catch (error) {
      console.error('Failed to assign avatar:', error);
    }
  };

  // ... existing code ...

  return (
    <div
      className="bg-white rounded-lg shadow-md border-l-4 p-6 hover:shadow-lg transition-shadow"
      style={{ borderLeftColor: department.color }}
    >
      {/* ... existing header and content ... */}

      <div className="flex justify-between items-center">
        <div className="text-sm text-gray-500">
          <div className="flex items-center space-x-2 mb-2">
            <span>Assigned to:</span>
            {task.avatarUrl ? (
              <img
                src={task.avatarUrl}
                alt={task.assignee}
                className="w-6 h-6 rounded-full"
                onError={(e) => {
                  const target = e.target as HTMLImageElement;
                  target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(task.assignee)}&background=random`;
                }}
              />
            ) : (
              <div className="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center">
                <span className="text-xs text-gray-500">?</span>
              </div>
            )}
            <span className="font-medium">{task.assignee}</span>
            <button
              onClick={() => setShowAvatarSelector(!showAvatarSelector)}
              className="text-blue-500 hover:text-blue-700 text-xs"
            >
              Change
            </button>
          </div>

          {showAvatarSelector && (
            <div className="mb-3">
              <AvatarSelector
                onSelect={handleAvatarSelect}
                selectedAvatarId={task.avatarUrl}
                department={department.id}
              />
            </div>
          )}

          <p>Created: {new Date(task.createdDate).toLocaleDateString()}</p>
        </div>

        {/* ... existing buttons ... */}
      </div>
    </div>
  );
};
```

**Testing Day 8:**
```bash
# Deploy Apps Script updates
clasp push

# Test avatar endpoints
curl "YOUR_APPS_SCRIPT_URL?action=getAvatars"

# Test in frontend
npm run dev
# Verify avatars load and can be assigned to tasks
```

---

### DAY 9 (Thursday): Sales Metrics Integration

**Morning: HubSpot Integration (3-4 hours)**

**1. Add sales metrics API endpoint:**

```javascript
// Add to Code.js
function apiGetSalesMetrics(params) {
  try {
    const department = params.department;

    if (department === 'sales') {
      // Use your existing getSalesCallMetrics function
      const metrics = getSalesCallMetrics();

      return {
        department: department,
        metrics: metrics,
        lastUpdated: new Date().toISOString(),
        summary: {
          totalCalls: metrics.totalCalls || 0,
          totalReps: (metrics.byRep || []).length,
          averageCallsPerRep: metrics.totalCalls ? Math.round(metrics.totalCalls / (metrics.byRep || []).length) : 0
        }
      };
    } else {
      return {
        department: department,
        metrics: null,
        message: 'Sales metrics only available for sales department'
      };
    }
  } catch (error) {
    console.error('Error in apiGetSalesMetrics:', error);
    throw new Error('Failed to fetch sales metrics: ' + error.message);
  }
}

// Update API router
case 'getSalesMetrics':
  return jsonResponse(apiGetSalesMetrics(e.parameter), cors);
```

**2. Create sales metrics store:**

```typescript
// src/stores/salesStore.ts
import { create } from 'zustand';
import { taskAPI } from '../services/api';

interface SalesRep {
  name: string;
  calls: number;
  email?: string;
}

interface SalesMetrics {
  totalCalls: number;
  byRep: SalesRep[];
  department: string;
  lastUpdated: string;
}

interface SalesStore {
  metrics: SalesMetrics | null;
  loading: boolean;
  error: string | null;

  fetchSalesMetrics: (department: string) => Promise<void>;
  clearError: () => void;
}

export const useSalesStore = create<SalesStore>((set) => ({
  metrics: null,
  loading: false,
  error: null,

  fetchSalesMetrics: async (department: string) => {
    set({ loading: true, error: null });
    try {
      const response = await taskAPI.getSalesMetrics(department);
      set({
        metrics: response.data.data.metrics,
        loading: false
      });
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : 'Failed to fetch sales metrics',
        loading: false
      });
    }
  },

  clearError: () => set({ error: null })
}));
```

**Afternoon: Sales Dashboard Component (3-4 hours)**

**3. Create SalesMetricsPanel component:**

```tsx
// src/components/department/SalesMetricsPanel.tsx
import React, { useEffect } from 'react';
import { useSalesStore } from '../../stores/salesStore';

interface SalesMetricsPanelProps {
  department: string;
}

export const SalesMetricsPanel: React.FC<SalesMetricsPanelProps> = ({ department }) => {
  const { metrics, loading, error, fetchSalesMetrics, clearError } = useSalesStore();

  useEffect(() => {
    if (department === 'sales') {
      fetchSalesMetrics(department);
    }
  }, [department, fetchSalesMetrics]);

  if (department !== 'sales') {
    return null;
  }

  if (loading) {
    return (
      <div className="bg-white rounded-lg shadow-md p-6 mb-6">
        <div className="animate-pulse">
          <div className="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
          <div className="space-y-2">
            <div className="h-3 bg-gray-200 rounded"></div>
            <div className="h-3 bg-gray-200 rounded w-5/6"></div>
          </div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
        <div className="flex justify-between items-center">
          <div>
            <h3 className="text-lg font-semibold text-red-800">Sales Metrics Error</h3>
            <p className="text-red-600 mt-1">{error}</p>
          </div>
          <button
            onClick={clearError}
            className="text-red-500 hover:text-red-700"
          >
            ✕
          </button>
        </div>
      </div>
    );
  }

  if (!metrics) {
    return null;
  }

  return (
    <div className="bg-white rounded-lg shadow-md p-6 mb-6">
      <h3 className="text-lg font-semibold text-gray-900 mb-4 flex items-center">
        📊 HubSpot Sales Metrics
        <span className="ml-2 text-sm text-gray-500">
          (Updated: {new Date(metrics.lastUpdated).toLocaleTimeString()})
        </span>
      </h3>

      {/* Summary Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div className="bg-blue-50 rounded-lg p-4">
          <div className="text-2xl font-bold text-blue-600">{metrics.totalCalls}</div>
          <div className="text-sm text-blue-800">Total Calls Today</div>
        </div>
        <div className="bg-green-50 rounded-lg p-4">
          <div className="text-2xl font-bold text-green-600">{metrics.byRep.length}</div>
          <div className="text-sm text-green-800">Active Reps</div>
        </div>
        <div className="bg-purple-50 rounded-lg p-4">
          <div className="text-2xl font-bold text-purple-600">
            {metrics.totalCalls ? Math.round(metrics.totalCalls / metrics.byRep.length) : 0}
          </div>
          <div className="text-sm text-purple-800">Avg Calls/Rep</div>
        </div>
      </div>

      {/* Rep Performance */}
      {metrics.byRep.length > 0 && (
        <div>
          <h4 className="font-semibold text-gray-900 mb-3">Rep Performance</h4>
          <div className="space-y-2">
            {metrics.byRep
              .sort((a, b) => b.calls - a.calls)
              .map((rep, index) => (
                <div
                  key={rep.name}
                  className="flex items-center justify-between py-2 px-3 bg-gray-50 rounded"
                >
                  <div className="flex items-center space-x-3">
                    <span className="text-sm font-medium text-gray-600">
                      #{index + 1}
                    </span>
                    <span className="font-medium text-gray-900">{rep.name}</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <span className="text-sm font-semibold text-gray-700">
                      {rep.calls} calls
                    </span>
                    <div className="w-16 bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-blue-500 h-2 rounded-full"
                        style={{
                          width: `${Math.min((rep.calls / Math.max(...metrics.byRep.map(r => r.calls))) * 100, 100)}%`
                        }}
                      ></div>
                    </div>
                  </div>
                </div>
              ))}
          </div>
        </div>
      )}
    </div>
  );
};
```

**4. Update DepartmentPage to include sales metrics:**

```tsx
// Update src/pages/DepartmentPage.tsx to include SalesMetricsPanel
import { SalesMetricsPanel } from '../components/department/SalesMetricsPanel';

// Add after DepartmentHeader:
<SalesMetricsPanel department={department.id} />
```

**Testing Day 9:**
```bash
# Test sales metrics API
curl "YOUR_APPS_SCRIPT_URL?action=getSalesMetrics&department=sales"

# Test in frontend
npm run dev
# Navigate to /department/sales
# Verify HubSpot metrics display correctly
```

---

### DAY 10 (Friday): Week 2 Testing & Polish

**Morning: Comprehensive Testing (3-4 hours)**

**1. Create comprehensive test suite:**

```typescript
// src/components/shared/__tests__/TaskCard.test.tsx
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { TaskCard } from '../TaskCard';
import { Task, Department } from '../../../types';

const mockTask: Task = {
  id: '1',
  title: 'Test Task',
  description: 'Test Description',
  assignee: 'Test User',
  priority: 'High',
  status: 'In Progress',
  department: 'sales',
  createdDate: '2024-01-01T00:00:00Z',
  avatarUrl: 'https://example.com/avatar.jpg'
};

const mockDepartment: Department = {
  id: 'sales',
  name: 'Sales',
  icon: '📞',
  color: '#3b82f6',
  gradient: 'linear-gradient(135deg, #3b82f6, #2563eb)'
};

describe('TaskCard', () => {
  test('renders task with avatar', () => {
    render(<TaskCard task={mockTask} department={mockDepartment} />);

    expect(screen.getByText('Test Task')).toBeInTheDocument();
    expect(screen.getByText('Test User')).toBeInTheDocument();
    const avatar = screen.getByAltText('Test User');
    expect(avatar).toHaveAttribute('src', 'https://example.com/avatar.jpg');
  });

  test('shows avatar selector when change button clicked', async () => {
    const mockUpdate = vi.fn();
    render(
      <TaskCard
        task={mockTask}
        department={mockDepartment}
        onUpdate={mockUpdate}
      />
    );

    fireEvent.click(screen.getByText('Change'));
    await waitFor(() => {
      expect(screen.getByText('Select Avatar')).toBeInTheDocument();
    });
  });
});
```

**2. Create API integration tests:**

```typescript
// src/services/__tests__/api.test.ts
import { taskAPI } from '../api';

// Mock axios
vi.mock('axios', () => ({
  default: {
    create: () => ({
      get: vi.fn(),
      post: vi.fn(),
      interceptors: {
        request: { use: vi.fn() },
        response: { use: vi.fn() }
      }
    })
  }
}));

describe('taskAPI', () => {
  test('getTasks calls correct endpoint', async () => {
    const mockGet = vi.fn().mockResolvedValue({
      data: { success: true, data: { tasks: [] } }
    });

    // Test implementation
  });
});
```

**Afternoon: Performance Optimization & Error Handling (3-4 hours)**

**3. Add loading states and error boundaries:**

```tsx
// src/components/shared/LoadingSpinner.tsx
import React from 'react';

interface LoadingSpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({
  size = 'md',
  className = ''
}) => {
  const sizeClasses = {
    sm: 'h-4 w-4',
    md: 'h-8 w-8',
    lg: 'h-12 w-12'
  };

  return (
    <div className={`animate-spin rounded-full border-b-2 border-blue-500 ${sizeClasses[size]} ${className}`}>
    </div>
  );
};
```

```tsx
// src/components/shared/ErrorBoundary.tsx
import React from 'react';

interface ErrorBoundaryState {
  hasError: boolean;
  error?: Error;
}

export class ErrorBoundary extends React.Component<
  React.PropsWithChildren<{}>,
  ErrorBoundaryState
> {
  constructor(props: React.PropsWithChildren<{}>) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    console.error('Error caught by boundary:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50">
          <div className="text-center">
            <div className="text-6xl mb-4">⚠️</div>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">
              Something went wrong
            </h1>
            <p className="text-gray-600 mb-4">
              {this.state.error?.message || 'An unexpected error occurred'}
            </p>
            <button
              onClick={() => window.location.reload()}
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              Reload Page
            </button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
```

**4. Performance optimizations:**

```typescript
// src/hooks/useDebounce.ts
import { useState, useEffect } from 'react';

export function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState<T>(value);

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);

  return debouncedValue;
}
```

**5. Update App.tsx with error boundary:**

```tsx
// src/App.tsx
import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { ErrorBoundary } from './components/shared/ErrorBoundary';
import { DepartmentPage } from './pages/DepartmentPage';
import { HomePage } from './pages/HomePage';

function App() {
  return (
    <ErrorBoundary>
      <Router>
        <div className="min-h-screen bg-gray-50">
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/department/:departmentId" element={<DepartmentPage />} />
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </div>
      </Router>
    </ErrorBoundary>
  );
}

export default App;
```

**End of Week 2 Testing:**
```bash
# Run all tests
npm run test

# Test performance
npm run build
npm run preview

# Test error scenarios
# - Invalid department URLs
# - Network failures
# - Malformed API responses

# Manual testing checklist:
# ✅ Create tasks in different departments
# ✅ Edit existing tasks
# ✅ Delete tasks
# ✅ Assign avatars to tasks
# ✅ View sales metrics (sales department only)
# ✅ Error handling and loading states
```

---

# WEEK 3: Department Expansion & Responsive Design
*Goal: Add remaining departments and mobile optimization*

## Week 3 Daily Breakdown

### DAY 11 (Monday): Remaining Departments Integration

**Morning: Add All Department Configurations (3-4 hours)**

**1. Complete DEPARTMENTS configuration:**

```typescript
// Update src/config/departments.ts with all departments
export const DEPARTMENTS: Record<string, Department> = {
  sales: {
    id: 'sales',
    name: 'Sales',
    icon: '📞',
    color: '#3b82f6',
    gradient: 'linear-gradient(135deg, #3b82f6, #2563eb)'
  },
  accounting: {
    id: 'accounting',
    name: 'Accounting',
    icon: '💰',
    color: '#10b981',
    gradient: 'linear-gradient(135deg, #10b981, #059669)'
  },
  tech: {
    id: 'tech',
    name: 'Tech',
    icon: '💻',
    color: '#8b5cf6',
    gradient: 'linear-gradient(135deg, #8b5cf6, #7c3aed)'
  },
  marketing: {
    id: 'marketing',
    name: 'Marketing',
    icon: '🎯',
    color: '#f59e0b',
    gradient: 'linear-gradient(135deg, #f59e0b, #d97706)'
  },
  hr: {
    id: 'hr',
    name: 'HR',
    icon: '👥',
    color: '#ef4444',
    gradient: 'linear-gradient(135deg, #ef4444, #dc2626)'
  },
  'customer-retention': {
    id: 'customer-retention',
    name: 'Customer Retention',
    icon: '🤝',
    color: '#06b6d4',
    gradient: 'linear-gradient(135deg, #06b6d4, #0891b2)'
  },
  purchasing: {
    id: 'purchasing',
    name: 'Purchasing',
    icon: '🛒',
    color: '#84cc16',
    gradient: 'linear-gradient(135deg, #84cc16, #65a30d)'
  },
  'trade-shows': {
    id: 'trade-shows',
    name: 'Trade Shows',
    icon: '🏢',
    color: '#a855f7',
    gradient: 'linear-gradient(135deg, #a855f7, #9333ea)'
  },
  swag: {
    id: 'swag',
    name: 'Swag',
    icon: '🎁',
    color: '#f97316',
    gradient: 'linear-gradient(135deg, #f97316, #ea580c)'
  },
  ideas: {
    id: 'ideas',
    name: 'Ideas',
    icon: '💡',
    color: '#eab308',
    gradient: 'linear-gradient(135deg, #eab308, #ca8a04)'
  }
};

// Helper function to get department by ID
export const getDepartment = (id: string): Department | null => {
  return DEPARTMENTS[id] || null;
};

// Get all departments as array
export const getAllDepartments = (): Department[] => {
  return Object.values(DEPARTMENTS);
};
```

**2. Update HomePage with better grid layout:**

```tsx
// Update src/pages/HomePage.tsx
import React from 'react';
import { Link } from 'react-router-dom';
import { getAllDepartments } from '../config/departments';

export const HomePage: React.FC = () => {
  const departments = getAllDepartments();

  return (
    <div className="container mx-auto px-4 py-8">
      <header className="text-center mb-12">
        <h1 className="text-5xl font-bold text-gray-900 mb-4">TaskMaster</h1>
        <p className="text-xl text-gray-600 max-w-2xl mx-auto">
          Modern task management for every department. Choose your department to get started.
        </p>
      </header>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 max-w-7xl mx-auto">
        {departments.map(department => (
          <Link
            key={department.id}
            to={`/department/${department.id}`}
            className="group block p-6 bg-white rounded-xl shadow-md hover:shadow-xl transition-all duration-200 border-l-4 transform hover:-translate-y-1"
            style={{ borderLeftColor: department.color }}
          >
            <div className="text-center">
              <div className="text-5xl mb-4 group-hover:scale-110 transition-transform duration-200">
                {department.icon}
              </div>
              <h2 className="text-xl font-semibold text-gray-900 mb-2">
                {department.name}
              </h2>
              <p className="text-gray-600 text-sm">
                Manage {department.name.toLowerCase()} tasks and workflows
              </p>
            </div>
          </Link>
        ))}
      </div>

      <div className="mt-12 text-center">
        <div className="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-8 max-w-4xl mx-auto border border-blue-100">
          <h3 className="text-2xl font-semibold text-blue-900 mb-3">🚧 Migration in Progress</h3>
          <p className="text-blue-700 text-lg mb-4">
            Welcome to the new React-powered TaskMaster interface!
          </p>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div className="bg-white rounded-lg p-4 border border-blue-200">
              <div className="text-2xl mb-2">⚡</div>
              <div className="font-semibold text-blue-800">10x Faster</div>
              <div className="text-blue-600">Modern React performance</div>
            </div>
            <div className="bg-white rounded-lg p-4 border border-blue-200">
              <div className="text-2xl mb-2">🎨</div>
              <div className="font-semibold text-blue-800">Better UX</div>
              <div className="text-blue-600">Responsive & intuitive</div>
            </div>
            <div className="bg-white rounded-lg p-4 border border-blue-200">
              <div className="text-2xl mb-2">🔄</div>
              <div className="font-semibold text-blue-800">Same Data</div>
              <div className="text-blue-600">All your existing tasks</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
```

**Afternoon: Mobile Responsive Design (3-4 hours)**

**3. Create responsive navigation:**

```tsx
// src/components/layout/Navigation.tsx
import React, { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { getAllDepartments } from '../../config/departments';

export const Navigation: React.FC = () => {
  const [isOpen, setIsOpen] = useState(false);
  const location = useLocation();
  const departments = getAllDepartments();

  const currentDepartment = departments.find(dept =>
    location.pathname.includes(`/department/${dept.id}`)
  );

  return (
    <nav className="bg-white shadow-lg border-b">
      <div className="max-w-7xl mx-auto px-4">
        <div className="flex justify-between items-center h-16">
          {/* Logo */}
          <Link to="/" className="flex items-center space-x-3">
            <div className="text-2xl">📋</div>
            <span className="text-xl font-bold text-gray-900">TaskMaster</span>
          </Link>

          {/* Current Department (mobile) */}
          {currentDepartment && (
            <div className="md:hidden flex items-center space-x-2">
              <span className="text-lg">{currentDepartment.icon}</span>
              <span className="font-medium text-gray-900">{currentDepartment.name}</span>
            </div>
          )}

          {/* Desktop Navigation */}
          <div className="hidden md:flex items-center space-x-8">
            <Link
              to="/"
              className={`px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                location.pathname === '/'
                  ? 'bg-blue-100 text-blue-700'
                  : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
              }`}
            >
              All Departments
            </Link>

            {currentDepartment && (
              <div className="flex items-center space-x-2 px-3 py-2 bg-gray-50 rounded-md">
                <span className="text-lg">{currentDepartment.icon}</span>
                <span className="font-medium text-gray-900">{currentDepartment.name}</span>
              </div>
            )}
          </div>

          {/* Mobile menu button */}
          <button
            className="md:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100"
            onClick={() => setIsOpen(!isOpen)}
          >
            <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
              {isOpen ? (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              ) : (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
              )}
            </svg>
          </button>
        </div>

        {/* Mobile menu */}
        {isOpen && (
          <div className="md:hidden">
            <div className="px-2 pt-2 pb-3 space-y-1 bg-gray-50 rounded-lg mb-4">
              <Link
                to="/"
                className="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100"
                onClick={() => setIsOpen(false)}
              >
                🏠 All Departments
              </Link>

              <div className="pt-2 border-t border-gray-200">
                <div className="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                  Quick Access
                </div>
                {departments.slice(0, 5).map(dept => (
                  <Link
                    key={dept.id}
                    to={`/department/${dept.id}`}
                    className="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-100"
                    onClick={() => setIsOpen(false)}
                  >
                    <span className="mr-2">{dept.icon}</span>
                    {dept.name}
                  </Link>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>
    </nav>
  );
};
```

**4. Make TaskCard responsive:**

```tsx
// Update src/components/shared/TaskCard.tsx for mobile
// Add responsive classes and touch-friendly interactions

const TaskCard: React.FC<TaskCardProps> = ({ task, department, onUpdate, onDelete }) => {
  // ... existing code ...

  return (
    <div
      className="bg-white rounded-lg shadow-md border-l-4 p-4 sm:p-6 hover:shadow-lg transition-shadow touch-manipulation"
      style={{ borderLeftColor: department.color }}
    >
      {/* Mobile-optimized header */}
      <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-2 sm:space-y-0">
        <h3 className="text-lg font-semibold text-gray-900 break-words">{task.title}</h3>
        <div className="flex flex-wrap gap-2">
          <span className={`px-2 py-1 rounded-full text-xs font-medium ${priorityColors[task.priority]}`}>
            {task.priority}
          </span>
          <span className={`px-2 py-1 rounded-full text-xs font-medium ${statusColors[task.status]}`}>
            {task.status}
          </span>
        </div>
      </div>

      <p className="text-gray-600 mb-4 text-sm sm:text-base">{task.description}</p>

      {/* Mobile-optimized bottom section */}
      <div className="flex flex-col space-y-3">
        <div className="flex items-center space-x-2 text-sm text-gray-500">
          <span>Assigned to:</span>
          {task.avatarUrl ? (
            <img
              src={task.avatarUrl}
              alt={task.assignee}
              className="w-6 h-6 rounded-full"
              onError={(e) => {
                const target = e.target as HTMLImageElement;
                target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(task.assignee)}&background=random`;
              }}
            />
          ) : (
            <div className="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center">
              <span className="text-xs text-gray-500">?</span>
            </div>
          )}
          <span className="font-medium">{task.assignee}</span>
        </div>

        <div className="text-xs text-gray-500">
          Created: {new Date(task.createdDate).toLocaleDateString()}
        </div>

        {/* Mobile-friendly buttons */}
        {(onUpdate || onDelete) && (
          <div className="flex space-x-2 pt-2">
            {onUpdate && (
              <button
                onClick={() => onUpdate(task)}
                className="flex-1 sm:flex-none px-4 py-2 text-sm bg-blue-500 text-white rounded-md hover:bg-blue-600 active:bg-blue-700 transition-colors"
              >
                Edit
              </button>
            )}
            {onDelete && (
              <button
                onClick={() => onDelete(task.id)}
                className="flex-1 sm:flex-none px-4 py-2 text-sm bg-red-500 text-white rounded-md hover:bg-red-600 active:bg-red-700 transition-colors"
              >
                Delete
              </button>
            )}
          </div>
        )}
      </div>
    </div>
  );
};
```

**Testing Day 11:**
```bash
# Test all departments
npm run dev
# Navigate to each department, verify functionality

# Test mobile responsiveness
# Use browser dev tools to test different screen sizes
# Test touch interactions on mobile
```

---

### DAY 12 (Tuesday): Department-Specific Customizations
**Focus:** Implement unique features and configurations for each department

**Morning Tasks (3-4 hours):**
1. **Department-Specific Field Configurations**
   ```typescript
   // src/types/departments.ts
   export interface DepartmentConfig {
     id: string;
     name: string;
     fields: DepartmentField[];
     priorities: string[];
     statuses: string[];
     features: DepartmentFeature[];
     colors: DepartmentColors;
   }

   export interface DepartmentField {
     id: string;
     label: string;
     type: 'text' | 'number' | 'date' | 'select' | 'textarea';
     required: boolean;
     options?: string[];
     visible: boolean;
   }

   export const DEPARTMENT_CONFIGS: Record<string, DepartmentConfig> = {
     sales: {
       id: 'sales',
       name: 'Sales',
       fields: [
         { id: 'dealValue', label: 'Deal Value', type: 'number', required: false, visible: true },
         { id: 'clientName', label: 'Client Name', type: 'text', required: true, visible: true },
         { id: 'closeDate', label: 'Expected Close', type: 'date', required: false, visible: true },
         { id: 'leadSource', label: 'Lead Source', type: 'select', required: false, visible: true,
           options: ['Website', 'Referral', 'Cold Call', 'Trade Show', 'Partner'] }
       ],
       priorities: ['Hot Lead', 'Warm Lead', 'Cold Lead', 'Follow-up'],
       statuses: ['Prospecting', 'Qualified', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost'],
       features: ['hubspotIntegration', 'salesMetrics', 'dealTracking'],
       colors: { primary: '#10B981', secondary: '#059669', accent: '#ECFDF5' }
     },

     accounting: {
       id: 'accounting',
       name: 'Accounting',
       fields: [
         { id: 'invoiceNumber', label: 'Invoice #', type: 'text', required: false, visible: true },
         { id: 'amount', label: 'Amount', type: 'number', required: false, visible: true },
         { id: 'dueDate', label: 'Due Date', type: 'date', required: false, visible: true },
         { id: 'vendor', label: 'Vendor', type: 'text', required: false, visible: true }
       ],
       priorities: ['Urgent Payment', 'Standard', 'Low Priority'],
       statuses: ['Pending Review', 'Approved', 'Processing', 'Completed', 'On Hold'],
       features: ['numberFormatting', 'dateValidation'],
       colors: { primary: '#3B82F6', secondary: '#2563EB', accent: '#EFF6FF' }
     },

     tech: {
       id: 'tech',
       name: 'Technology',
       fields: [
         { id: 'priority', label: 'Bug Priority', type: 'select', required: true, visible: true,
           options: ['P0 - Critical', 'P1 - High', 'P2 - Medium', 'P3 - Low'] },
         { id: 'component', label: 'Component', type: 'text', required: false, visible: true },
         { id: 'environment', label: 'Environment', type: 'select', required: false, visible: true,
           options: ['Production', 'Staging', 'Development', 'Testing'] }
       ],
       priorities: ['P0 - Critical', 'P1 - High', 'P2 - Medium', 'P3 - Low'],
       statuses: ['Backlog', 'In Progress', 'Code Review', 'Testing', 'Done', 'Blocked'],
       features: ['codeSnippets', 'gitIntegration'],
       colors: { primary: '#8B5CF6', secondary: '#7C3AED', accent: '#F3F4F6' }
     }
   };
   ```

2. **Dynamic Department Configuration Hook**
   ```typescript
   // src/hooks/useDepartmentConfig.ts
   import { useMemo } from 'react';
   import { useParams } from 'react-router-dom';
   import { DEPARTMENT_CONFIGS, DepartmentConfig } from '../types/departments';

   export const useDepartmentConfig = (): DepartmentConfig | null => {
     const { department } = useParams();

     return useMemo(() => {
       if (!department || !DEPARTMENT_CONFIGS[department]) {
         return null;
       }
       return DEPARTMENT_CONFIGS[department];
     }, [department]);
   };

   export const useDepartmentFields = () => {
     const config = useDepartmentConfig();

     return useMemo(() => {
       if (!config) return [];
       return config.fields.filter(field => field.visible);
     }, [config]);
   };

   export const useDepartmentColors = () => {
     const config = useDepartmentConfig();

     return useMemo(() => {
       return config?.colors || {
         primary: '#6B7280',
         secondary: '#4B5563',
         accent: '#F9FAFB'
       };
     }, [config]);
   };
   ```

**Afternoon Tasks (3-4 hours):**
1. **Enhanced TaskCard with Department Fields**
   ```typescript
   // src/components/TaskCard.tsx - Enhanced version
   import React from 'react';
   import { Task } from '../types/task';
   import { useDepartmentConfig, useDepartmentColors } from '../hooks/useDepartmentConfig';

   interface TaskCardProps {
     task: Task;
     onEdit: (task: Task) => void;
     onDelete: (taskId: string) => void;
   }

   export const TaskCard: React.FC<TaskCardProps> = ({ task, onEdit, onDelete }) => {
     const config = useDepartmentConfig();
     const colors = useDepartmentColors();

     const renderDepartmentField = (fieldId: string, value: any) => {
       const field = config?.fields.find(f => f.id === fieldId);
       if (!field || !value) return null;

       return (
         <div key={fieldId} className="text-sm">
           <span className="font-medium text-gray-600">{field.label}:</span>
           <span className="ml-2 text-gray-800">
             {field.type === 'number' && fieldId === 'amount'
               ? `$${Number(value).toLocaleString()}`
               : field.type === 'date'
               ? new Date(value).toLocaleDateString()
               : value}
           </span>
         </div>
       );
     };

     return (
       <div
         className="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow"
         style={{ borderLeftColor: colors.primary, borderLeftWidth: '4px' }}
       >
         <div className="flex justify-between items-start mb-3">
           <h3 className="font-semibold text-gray-900 flex-1">{task.title}</h3>
           <div className="flex space-x-2 ml-2">
             <button
               onClick={() => onEdit(task)}
               className="p-1 text-gray-400 hover:text-blue-600 transition-colors"
             >
               ✏️
             </button>
             <button
               onClick={() => onDelete(task.id)}
               className="p-1 text-gray-400 hover:text-red-600 transition-colors"
             >
               🗑️
             </button>
           </div>
         </div>

         {task.description && (
           <p className="text-gray-600 text-sm mb-3 line-clamp-2">{task.description}</p>
         )}

         {/* Department-specific fields */}
         <div className="space-y-1 mb-3">
           {config?.fields.map(field =>
             renderDepartmentField(field.id, task.customFields?.[field.id])
           )}
         </div>

         <div className="flex items-center justify-between text-sm">
           <span
             className="px-2 py-1 rounded-full text-xs font-medium"
             style={{
               backgroundColor: colors.accent,
               color: colors.secondary
             }}
           >
             {task.status}
           </span>

           <div className="flex items-center space-x-2 text-gray-500">
             {task.assignee && (
               <span className="flex items-center">
                 <span className="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center text-xs mr-1">
                   {task.assignee.charAt(0).toUpperCase()}
                 </span>
                 {task.assignee}
               </span>
             )}
           </div>
         </div>

         {task.dueDate && (
           <div className="mt-2 text-xs text-gray-500">
             Due: {new Date(task.dueDate).toLocaleDateString()}
           </div>
         )}
       </div>
     );
   };
   ```

2. **Department-Specific Task Form**
   ```typescript
   // src/components/DepartmentTaskForm.tsx
   import React, { useState } from 'react';
   import { Task } from '../types/task';
   import { useDepartmentConfig } from '../hooks/useDepartmentConfig';

   interface DepartmentTaskFormProps {
     task?: Task;
     onSubmit: (taskData: Partial<Task>) => void;
     onCancel: () => void;
   }

   export const DepartmentTaskForm: React.FC<DepartmentTaskFormProps> = ({
     task,
     onSubmit,
     onCancel
   }) => {
     const config = useDepartmentConfig();
     const [formData, setFormData] = useState({
       title: task?.title || '',
       description: task?.description || '',
       assignee: task?.assignee || '',
       priority: task?.priority || config?.priorities[0] || 'Medium',
       status: task?.status || config?.statuses[0] || 'Not Started',
       dueDate: task?.dueDate || '',
       customFields: task?.customFields || {}
     });

     const handleSubmit = (e: React.FormEvent) => {
       e.preventDefault();
       onSubmit(formData);
     };

     const handleCustomFieldChange = (fieldId: string, value: any) => {
       setFormData(prev => ({
         ...prev,
         customFields: {
           ...prev.customFields,
           [fieldId]: value
         }
       }));
     };

     const renderCustomField = (field: any) => {
       const value = formData.customFields[field.id] || '';

       switch (field.type) {
         case 'select':
           return (
             <select
               value={value}
               onChange={(e) => handleCustomFieldChange(field.id, e.target.value)}
               className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
               required={field.required}
             >
               <option value="">Select {field.label}</option>
               {field.options?.map((option: string) => (
                 <option key={option} value={option}>{option}</option>
               ))}
             </select>
           );

         case 'number':
           return (
             <input
               type="number"
               value={value}
               onChange={(e) => handleCustomFieldChange(field.id, e.target.value)}
               className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
               required={field.required}
               placeholder={`Enter ${field.label.toLowerCase()}`}
             />
           );

         case 'date':
           return (
             <input
               type="date"
               value={value}
               onChange={(e) => handleCustomFieldChange(field.id, e.target.value)}
               className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
               required={field.required}
             />
           );

         case 'textarea':
           return (
             <textarea
               value={value}
               onChange={(e) => handleCustomFieldChange(field.id, e.target.value)}
               className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
               rows={3}
               required={field.required}
               placeholder={`Enter ${field.label.toLowerCase()}`}
             />
           );

         default:
           return (
             <input
               type="text"
               value={value}
               onChange={(e) => handleCustomFieldChange(field.id, e.target.value)}
               className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
               required={field.required}
               placeholder={`Enter ${field.label.toLowerCase()}`}
             />
           );
       }
     };

     return (
       <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
         <div className="bg-white rounded-lg max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
           <h2 className="text-xl font-semibold mb-4">
             {task ? 'Edit Task' : `New ${config?.name || 'Department'} Task`}
           </h2>

           <form onSubmit={handleSubmit} className="space-y-4">
             <div>
               <label className="block text-sm font-medium text-gray-700 mb-1">
                 Title *
               </label>
               <input
                 type="text"
                 value={formData.title}
                 onChange={(e) => setFormData(prev => ({ ...prev, title: e.target.value }))}
                 className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                 required
                 placeholder="Enter task title"
               />
             </div>

             <div>
               <label className="block text-sm font-medium text-gray-700 mb-1">
                 Description
               </label>
               <textarea
                 value={formData.description}
                 onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
                 className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                 rows={3}
                 placeholder="Enter task description"
               />
             </div>

             {/* Department-specific custom fields */}
             {config?.fields.filter(field => field.visible).map(field => (
               <div key={field.id}>
                 <label className="block text-sm font-medium text-gray-700 mb-1">
                   {field.label} {field.required && '*'}
                 </label>
                 {renderCustomField(field)}
               </div>
             ))}

             <div className="grid grid-cols-2 gap-4">
               <div>
                 <label className="block text-sm font-medium text-gray-700 mb-1">
                   Priority
                 </label>
                 <select
                   value={formData.priority}
                   onChange={(e) => setFormData(prev => ({ ...prev, priority: e.target.value }))}
                   className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                 >
                   {config?.priorities.map(priority => (
                     <option key={priority} value={priority}>{priority}</option>
                   ))}
                 </select>
               </div>

               <div>
                 <label className="block text-sm font-medium text-gray-700 mb-1">
                   Status
                 </label>
                 <select
                   value={formData.status}
                   onChange={(e) => setFormData(prev => ({ ...prev, status: e.target.value }))}
                   className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                 >
                   {config?.statuses.map(status => (
                     <option key={status} value={status}>{status}</option>
                   ))}
                 </select>
               </div>
             </div>

             <div className="flex justify-end space-x-3 pt-4">
               <button
                 type="button"
                 onClick={onCancel}
                 className="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
               >
                 Cancel
               </button>
               <button
                 type="submit"
                 className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
               >
                 {task ? 'Update Task' : 'Create Task'}
               </button>
             </div>
           </form>
         </div>
       </div>
     );
   };
   ```

**Testing Day 12:**
```bash
# Test department-specific configurations
npm run dev
# Navigate to different departments: /department/sales, /department/accounting, /department/tech
# Verify each department shows custom fields and colors
# Test creating tasks with department-specific fields
# Verify form validation works for required custom fields

# Test department switching
# Navigate between departments and verify configurations change
# Test task display with different custom fields per department
```

### DAY 13 (Wednesday): Mobile Optimization & Responsive Design
**Focus:** Ensure excellent mobile experience across all devices

**Morning Tasks (3-4 hours):**
1. **Mobile-First Responsive Layout**
   ```css
   /* src/styles/mobile.css */

   /* Mobile-first approach - base styles for mobile */
   .task-grid {
     display: grid;
     grid-template-columns: 1fr;
     gap: 1rem;
     padding: 1rem;
   }

   .task-card {
     width: 100%;
     margin-bottom: 1rem;
     min-height: auto;
   }

   /* Navigation optimized for mobile */
   .mobile-nav {
     position: fixed;
     bottom: 0;
     left: 0;
     right: 0;
     background: white;
     border-top: 1px solid #e5e7eb;
     padding: 0.5rem;
     z-index: 50;
   }

   .mobile-nav-items {
     display: flex;
     justify-content: space-around;
     align-items: center;
   }

   .mobile-nav-item {
     display: flex;
     flex-direction: column;
     align-items: center;
     padding: 0.5rem;
     min-width: 60px;
     text-decoration: none;
     color: #6b7280;
     font-size: 0.75rem;
   }

   .mobile-nav-item.active {
     color: #3b82f6;
   }

   .mobile-nav-icon {
     font-size: 1.25rem;
     margin-bottom: 0.25rem;
   }

   /* Tablet styles */
   @media (min-width: 768px) {
     .task-grid {
       grid-template-columns: repeat(2, 1fr);
       gap: 1.5rem;
       padding: 1.5rem;
     }

     .mobile-nav {
       display: none;
     }
   }

   /* Desktop styles */
   @media (min-width: 1024px) {
     .task-grid {
       grid-template-columns: repeat(3, 1fr);
       gap: 2rem;
       padding: 2rem;
     }
   }

   @media (min-width: 1280px) {
     .task-grid {
       grid-template-columns: repeat(4, 1fr);
     }
   }

   /* Mobile form optimizations */
   @media (max-width: 767px) {
     .task-form-modal {
       position: fixed;
       top: 0;
       left: 0;
       right: 0;
       bottom: 0;
       margin: 0;
       border-radius: 0;
       max-width: 100%;
       max-height: 100%;
     }

     .task-form-content {
       height: 100vh;
       overflow-y: auto;
       padding: 1rem;
       padding-bottom: 5rem; /* Account for mobile nav */
     }

     .form-buttons {
       position: fixed;
       bottom: 0;
       left: 0;
       right: 0;
       background: white;
       padding: 1rem;
       border-top: 1px solid #e5e7eb;
       display: flex;
       gap: 1rem;
     }

     .form-button {
       flex: 1;
       padding: 0.75rem;
       font-size: 1rem;
     }
   }

   /* Touch-friendly interactive elements */
   .touch-target {
     min-height: 44px;
     min-width: 44px;
     display: flex;
     align-items: center;
     justify-content: center;
   }

   /* Improved mobile task cards */
   @media (max-width: 767px) {
     .task-card {
       padding: 1rem;
       border-radius: 0.5rem;
       box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
     }

     .task-card-header {
       display: flex;
       justify-content: space-between;
       align-items: flex-start;
       margin-bottom: 0.75rem;
     }

     .task-card-title {
       font-size: 1rem;
       font-weight: 600;
       line-height: 1.4;
       flex: 1;
       margin-right: 0.5rem;
     }

     .task-card-actions {
       display: flex;
       gap: 0.5rem;
     }

     .task-card-action {
       padding: 0.5rem;
       min-width: 44px;
       min-height: 44px;
       border-radius: 0.375rem;
       border: 1px solid #e5e7eb;
       background: white;
       display: flex;
       align-items: center;
       justify-content: center;
     }
   }
   ```

2. **Mobile Navigation Component**
   ```typescript
   // src/components/MobileNavigation.tsx
   import React from 'react';
   import { Link, useLocation } from 'react-router-dom';
   import { useDepartmentConfig } from '../hooks/useDepartmentConfig';

   const DEPARTMENT_ICONS: Record<string, string> = {
     sales: '💼',
     accounting: '📊',
     tech: '💻',
     marketing: '📢',
     hr: '👥',
     operations: '⚙️',
     legal: '⚖️',
     finance: '💰',
     admin: '🏢',
     ideas: '💡'
   };

   export const MobileNavigation: React.FC = () => {
     const location = useLocation();
     const currentDepartment = location.pathname.split('/')[2];

     const departments = Object.keys(DEPARTMENT_ICONS);

     // Only show on mobile
     const [isMobile, setIsMobile] = React.useState(window.innerWidth < 768);

     React.useEffect(() => {
       const handleResize = () => setIsMobile(window.innerWidth < 768);
       window.addEventListener('resize', handleResize);
       return () => window.removeEventListener('resize', handleResize);
     }, []);

     if (!isMobile) return null;

     return (
       <nav className="mobile-nav">
         <div className="mobile-nav-items">
           {departments.slice(0, 5).map(dept => (
             <Link
               key={dept}
               to={`/department/${dept}`}
               className={`mobile-nav-item ${currentDepartment === dept ? 'active' : ''}`}
             >
               <span className="mobile-nav-icon">{DEPARTMENT_ICONS[dept]}</span>
               <span className="capitalize">{dept}</span>
             </Link>
           ))}
         </div>
       </nav>
     );
   };
   ```

**Afternoon Tasks (3-4 hours):**
1. **Touch Gesture Support**
   ```typescript
   // src/hooks/useSwipeGesture.ts
   import { useState, useEffect } from 'react';

   interface SwipeGestureOptions {
     onSwipeLeft?: () => void;
     onSwipeRight?: () => void;
     onSwipeUp?: () => void;
     onSwipeDown?: () => void;
     minSwipeDistance?: number;
   }

   export const useSwipeGesture = (
     elementRef: React.RefObject<HTMLElement>,
     options: SwipeGestureOptions
   ) => {
     const [touchStart, setTouchStart] = useState<{ x: number; y: number } | null>(null);
     const [touchEnd, setTouchEnd] = useState<{ x: number; y: number } | null>(null);

     const minSwipeDistance = options.minSwipeDistance || 50;

     const onTouchStart = (e: TouchEvent) => {
       setTouchEnd(null);
       setTouchStart({
         x: e.targetTouches[0].clientX,
         y: e.targetTouches[0].clientY
       });
     };

     const onTouchMove = (e: TouchEvent) => {
       setTouchEnd({
         x: e.targetTouches[0].clientX,
         y: e.targetTouches[0].clientY
       });
     };

     const onTouchEnd = () => {
       if (!touchStart || !touchEnd) return;

       const deltaX = touchStart.x - touchEnd.x;
       const deltaY = touchStart.y - touchEnd.y;

       const isHorizontalSwipe = Math.abs(deltaX) > Math.abs(deltaY);
       const isVerticalSwipe = Math.abs(deltaY) > Math.abs(deltaX);

       if (isHorizontalSwipe) {
         if (deltaX > minSwipeDistance) {
           options.onSwipeLeft?.();
         } else if (deltaX < -minSwipeDistance) {
           options.onSwipeRight?.();
         }
       }

       if (isVerticalSwipe) {
         if (deltaY > minSwipeDistance) {
           options.onSwipeUp?.();
         } else if (deltaY < -minSwipeDistance) {
           options.onSwipeDown?.();
         }
       }
     };

     useEffect(() => {
       const element = elementRef.current;
       if (!element) return;

       element.addEventListener('touchstart', onTouchStart);
       element.addEventListener('touchmove', onTouchMove);
       element.addEventListener('touchend', onTouchEnd);

       return () => {
         element.removeEventListener('touchstart', onTouchStart);
         element.removeEventListener('touchmove', onTouchMove);
         element.removeEventListener('touchend', onTouchEnd);
       };
     }, [elementRef, touchStart, touchEnd]);
   };
   ```

2. **Mobile-Optimized Task List**
   ```typescript
   // src/components/MobileTaskList.tsx
   import React, { useRef } from 'react';
   import { Task } from '../types/task';
   import { useSwipeGesture } from '../hooks/useSwipeGesture';
   import { useDepartmentColors } from '../hooks/useDepartmentConfig';

   interface MobileTaskListProps {
     tasks: Task[];
     onEdit: (task: Task) => void;
     onDelete: (taskId: string) => void;
     onStatusChange: (taskId: string, newStatus: string) => void;
   }

   export const MobileTaskList: React.FC<MobileTaskListProps> = ({
     tasks,
     onEdit,
     onDelete,
     onStatusChange
   }) => {
     const colors = useDepartmentColors();

     return (
       <div className="mobile-task-list pb-20"> {/* Account for mobile nav */}
         {tasks.map(task => (
           <MobileTaskCard
             key={task.id}
             task={task}
             onEdit={onEdit}
             onDelete={onDelete}
             onStatusChange={onStatusChange}
             colors={colors}
           />
         ))}
       </div>
     );
   };

   interface MobileTaskCardProps {
     task: Task;
     onEdit: (task: Task) => void;
     onDelete: (taskId: string) => void;
     onStatusChange: (taskId: string, newStatus: string) => void;
     colors: any;
   }

   const MobileTaskCard: React.FC<MobileTaskCardProps> = ({
     task,
     onEdit,
     onDelete,
     onStatusChange,
     colors
   }) => {
     const cardRef = useRef<HTMLDivElement>(null);
     const [swipeAction, setSwipeAction] = React.useState<'edit' | 'delete' | null>(null);

     useSwipeGesture(cardRef, {
       onSwipeLeft: () => setSwipeAction('delete'),
       onSwipeRight: () => setSwipeAction('edit'),
       minSwipeDistance: 100
     });

     React.useEffect(() => {
       if (swipeAction) {
         const timer = setTimeout(() => {
           if (swipeAction === 'edit') {
             onEdit(task);
           } else if (swipeAction === 'delete') {
             onDelete(task.id);
           }
           setSwipeAction(null);
         }, 200);

         return () => clearTimeout(timer);
       }
     }, [swipeAction, task, onEdit, onDelete]);

     return (
       <div
         ref={cardRef}
         className={`
           mobile-task-card p-4 mb-3 bg-white rounded-lg border-l-4 shadow-sm
           ${swipeAction === 'edit' ? 'bg-blue-50 transform -translate-x-2' : ''}
           ${swipeAction === 'delete' ? 'bg-red-50 transform translate-x-2' : ''}
           transition-all duration-200
         `}
         style={{ borderLeftColor: colors.primary }}
       >
         <div className="flex justify-between items-start mb-2">
           <h3 className="font-semibold text-gray-900 flex-1 pr-2 leading-tight">
             {task.title}
           </h3>
           <div className="flex space-x-1">
             <button
               onClick={() => onEdit(task)}
               className="touch-target p-2 text-gray-400 hover:text-blue-600 rounded"
               aria-label="Edit task"
             >
               ✏️
             </button>
             <button
               onClick={() => onDelete(task.id)}
               className="touch-target p-2 text-gray-400 hover:text-red-600 rounded"
               aria-label="Delete task"
             >
               🗑️
             </button>
           </div>
         </div>

         {task.description && (
           <p className="text-gray-600 text-sm mb-3 line-clamp-2">
             {task.description}
           </p>
         )}

         <div className="flex items-center justify-between mb-2">
           <select
             value={task.status}
             onChange={(e) => onStatusChange(task.id, e.target.value)}
             className="text-xs px-2 py-1 border border-gray-300 rounded"
             style={{ backgroundColor: colors.accent }}
           >
             <option value="Not Started">Not Started</option>
             <option value="In Progress">In Progress</option>
             <option value="Completed">Completed</option>
           </select>

           {task.assignee && (
             <div className="flex items-center text-xs text-gray-600">
               <div className="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center mr-2">
                 {task.assignee.charAt(0).toUpperCase()}
               </div>
               <span className="truncate max-w-20">{task.assignee}</span>
             </div>
           )}
         </div>

         {task.dueDate && (
           <div className="text-xs text-gray-500">
             Due: {new Date(task.dueDate).toLocaleDateString()}
           </div>
         )}

         {swipeAction && (
           <div className="absolute inset-0 flex items-center justify-center bg-black bg-opacity-10 rounded-lg">
             <span className="text-lg font-semibold text-gray-700">
               {swipeAction === 'edit' ? '✏️ Edit' : '🗑️ Delete'}
             </span>
           </div>
         )}
       </div>
     );
   };
   ```

**Testing Day 13:**
```bash
# Test mobile responsiveness
npm run dev

# Use browser dev tools to test different screen sizes:
# - iPhone SE (375x667)
# - iPhone 12 (390x844)
# - iPad (768x1024)
# - Desktop (1920x1080)

# Test touch interactions:
# - Tap buttons and forms
# - Swipe gestures on task cards
# - Mobile navigation
# - Form submission on mobile

# Test mobile-specific features:
# - Mobile navigation appears only on small screens
# - Forms adapt to full-screen on mobile
# - Touch targets are at least 44px
# - Text remains readable at all zoom levels
```

### DAY 14 (Thursday): Week 3 Integration Testing & Polish
**Focus:** Comprehensive testing and final polish for Week 3 features

**Morning Tasks (3-4 hours):**
1. **Comprehensive Integration Testing**
   ```typescript
   // src/tests/integration/departmentIntegration.test.ts
   import { render, screen, fireEvent, waitFor } from '@testing-library/react';
   import { BrowserRouter } from 'react-router-dom';
   import { DepartmentView } from '../components/DepartmentView';
   import { useTaskStore } from '../store/taskStore';

   // Mock the task store
   jest.mock('../store/taskStore');
   const mockUseTaskStore = useTaskStore as jest.MockedFunction<typeof useTaskStore>;

   describe('Department Integration Tests', () => {
     beforeEach(() => {
       mockUseTaskStore.mockReturnValue({
         tasks: [],
         loading: false,
         error: null,
         fetchTasks: jest.fn(),
         createTask: jest.fn(),
         updateTask: jest.fn(),
         deleteTask: jest.fn()
       });
     });

     test('loads department-specific configuration', async () => {
       render(
         <BrowserRouter>
           <DepartmentView />
         </BrowserRouter>
       );

       // Test that department-specific fields are loaded
       // Test that department colors are applied
       // Test that department-specific priorities are available
     });

     test('creates task with department-specific fields', async () => {
       const mockCreateTask = jest.fn();
       mockUseTaskStore.mockReturnValue({
         tasks: [],
         loading: false,
         error: null,
         fetchTasks: jest.fn(),
         createTask: mockCreateTask,
         updateTask: jest.fn(),
         deleteTask: jest.fn()
       });

       render(
         <BrowserRouter>
           <DepartmentView />
         </BrowserRouter>
       );

       // Test creating a task with custom fields
       fireEvent.click(screen.getByText('Create New Task'));

       // Fill in department-specific fields
       // Submit form
       // Verify task was created with custom fields
     });

     test('mobile responsiveness works correctly', () => {
       // Mock mobile viewport
       Object.defineProperty(window, 'innerWidth', {
         writable: true,
         configurable: true,
         value: 375,
       });

       render(
         <BrowserRouter>
           <DepartmentView />
         </BrowserRouter>
       );

       // Test mobile navigation appears
       // Test mobile-optimized layout
       // Test touch-friendly buttons
     });
   });
   ```

2. **Performance Optimization**
   ```typescript
   // src/hooks/useOptimizedTasks.ts
   import { useMemo } from 'react';
   import { Task } from '../types/task';

   export const useOptimizedTasks = (
     tasks: Task[],
     searchTerm: string,
     statusFilter: string,
     priorityFilter: string
   ) => {
     return useMemo(() => {
       let filtered = tasks;

       // Apply search filter
       if (searchTerm) {
         const search = searchTerm.toLowerCase();
         filtered = filtered.filter(task =>
           task.title.toLowerCase().includes(search) ||
           task.description?.toLowerCase().includes(search) ||
           task.assignee?.toLowerCase().includes(search)
         );
       }

       // Apply status filter
       if (statusFilter && statusFilter !== 'all') {
         filtered = filtered.filter(task => task.status === statusFilter);
       }

       // Apply priority filter
       if (priorityFilter && priorityFilter !== 'all') {
         filtered = filtered.filter(task => task.priority === priorityFilter);
       }

       return filtered;
     }, [tasks, searchTerm, statusFilter, priorityFilter]);
   };

   // Memoized task counting
   export const useTaskStats = (tasks: Task[]) => {
     return useMemo(() => {
       const stats = tasks.reduce((acc, task) => {
         acc.total++;
         acc.byStatus[task.status] = (acc.byStatus[task.status] || 0) + 1;
         acc.byPriority[task.priority] = (acc.byPriority[task.priority] || 0) + 1;

         if (task.dueDate) {
           const due = new Date(task.dueDate);
           const today = new Date();
           const diffTime = due.getTime() - today.getTime();
           const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

           if (diffDays < 0) acc.overdue++;
           else if (diffDays <= 7) acc.dueSoon++;
         }

         return acc;
       }, {
         total: 0,
         byStatus: {} as Record<string, number>,
         byPriority: {} as Record<string, number>,
         overdue: 0,
         dueSoon: 0
       });

       return stats;
     }, [tasks]);
   };
   ```

**Afternoon Tasks (3-4 hours):**
1. **Error Handling & User Feedback**
   ```typescript
   // src/components/ErrorBoundary.tsx
   import React, { Component, ErrorInfo, ReactNode } from 'react';

   interface Props {
     children: ReactNode;
     fallback?: ReactNode;
   }

   interface State {
     hasError: boolean;
     error?: Error;
   }

   export class ErrorBoundary extends Component<Props, State> {
     public state: State = {
       hasError: false
     };

     public static getDerivedStateFromError(error: Error): State {
       return { hasError: true, error };
     }

     public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
       console.error('Uncaught error:', error, errorInfo);

       // Log error to monitoring service
       if (process.env.NODE_ENV === 'production') {
         // Send to error tracking service
         this.logErrorToService(error, errorInfo);
       }
     }

     private logErrorToService(error: Error, errorInfo: ErrorInfo) {
       // Implementation for error logging service
       fetch('/api/errors', {
         method: 'POST',
         headers: { 'Content-Type': 'application/json' },
         body: JSON.stringify({
           message: error.message,
           stack: error.stack,
           componentStack: errorInfo.componentStack,
           timestamp: new Date().toISOString(),
           userAgent: navigator.userAgent,
           url: window.location.href
         })
       }).catch(console.error);
     }

     public render() {
       if (this.state.hasError) {
         return this.props.fallback || (
           <div className="min-h-screen flex items-center justify-center bg-gray-50">
             <div className="max-w-md w-full bg-white rounded-lg shadow-md p-6 text-center">
               <div className="text-6xl mb-4">😔</div>
               <h2 className="text-xl font-semibold text-gray-900 mb-2">
                 Something went wrong
               </h2>
               <p className="text-gray-600 mb-4">
                 We're sorry, but something unexpected happened. Please try refreshing the page.
               </p>
               <div className="space-y-2">
                 <button
                   onClick={() => window.location.reload()}
                   className="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                 >
                   Refresh Page
                 </button>
                 <button
                   onClick={() => this.setState({ hasError: false })}
                   className="w-full px-4 py-2 text-blue-600 border border-blue-600 rounded-md hover:bg-blue-50 transition-colors"
                 >
                   Try Again
                 </button>
               </div>
               {process.env.NODE_ENV === 'development' && this.state.error && (
                 <details className="mt-4 text-left">
                   <summary className="cursor-pointer text-sm text-gray-500">
                     Error Details (Development)
                   </summary>
                   <pre className="mt-2 text-xs text-red-600 overflow-auto">
                     {this.state.error.stack}
                   </pre>
                 </details>
               )}
             </div>
           </div>
         );
       }

       return this.props.children;
     }
   }
   ```

2. **Final Polish & Accessibility**
   ```typescript
   // src/components/AccessibilityEnhancements.tsx
   import React from 'react';

   // Keyboard navigation hook
   export const useKeyboardNavigation = (
     items: string[],
     onSelect: (item: string) => void
   ) => {
     const [selectedIndex, setSelectedIndex] = React.useState(0);

     React.useEffect(() => {
       const handleKeyDown = (e: KeyboardEvent) => {
         switch (e.key) {
           case 'ArrowDown':
             e.preventDefault();
             setSelectedIndex(prev => Math.min(prev + 1, items.length - 1));
             break;
           case 'ArrowUp':
             e.preventDefault();
             setSelectedIndex(prev => Math.max(prev - 1, 0));
             break;
           case 'Enter':
             e.preventDefault();
             onSelect(items[selectedIndex]);
             break;
           case 'Escape':
             e.preventDefault();
             setSelectedIndex(0);
             break;
         }
       };

       window.addEventListener('keydown', handleKeyDown);
       return () => window.removeEventListener('keydown', handleKeyDown);
     }, [items, selectedIndex, onSelect]);

     return selectedIndex;
   };

   // Focus management for modals
   export const useFocusManagement = (isOpen: boolean) => {
     const firstFocusableRef = React.useRef<HTMLElement>(null);
     const lastFocusableRef = React.useRef<HTMLElement>(null);

     React.useEffect(() => {
       if (isOpen) {
         firstFocusableRef.current?.focus();

         const handleTabKey = (e: KeyboardEvent) => {
           if (e.key === 'Tab') {
             if (e.shiftKey) {
               if (document.activeElement === firstFocusableRef.current) {
                 e.preventDefault();
                 lastFocusableRef.current?.focus();
               }
             } else {
               if (document.activeElement === lastFocusableRef.current) {
                 e.preventDefault();
                 firstFocusableRef.current?.focus();
               }
             }
           }
         };

         document.addEventListener('keydown', handleTabKey);
         return () => document.removeEventListener('keydown', handleTabKey);
       }
     }, [isOpen]);

     return { firstFocusableRef, lastFocusableRef };
   };

   // Screen reader announcements
   export const useScreenReaderAnnouncement = () => {
     const announce = React.useCallback((message: string) => {
       const announcement = document.createElement('div');
       announcement.setAttribute('aria-live', 'polite');
       announcement.setAttribute('aria-atomic', 'true');
       announcement.className = 'sr-only';
       announcement.textContent = message;

       document.body.appendChild(announcement);

       setTimeout(() => {
         document.body.removeChild(announcement);
       }, 1000);
     }, []);

     return announce;
   };
   ```

**Testing Day 14:**
```bash
# Comprehensive Week 3 testing
npm run test
npm run test:integration

# Manual testing checklist:
echo "Week 3 Testing Checklist:"
echo "✅ Department-specific configurations load correctly"
echo "✅ Custom fields appear for each department"
echo "✅ Department colors and branding apply correctly"
echo "✅ Mobile navigation works on small screens"
echo "✅ Touch gestures work on mobile devices"
echo "✅ Forms adapt to mobile layout"
echo "✅ Responsive design works across all breakpoints"
echo "✅ Error boundaries catch and handle errors gracefully"
echo "✅ Performance is acceptable with large task lists"
echo "✅ Accessibility features work with keyboard navigation"
echo "✅ Screen readers can navigate the interface"

# Performance testing
npm run build
npm run preview
# Test with Lighthouse for performance scores
# Aim for >90 on mobile and desktop

# Cross-browser testing
# Test on Chrome, Firefox, Safari, Edge
# Test on actual mobile devices if available

# Load testing
# Create 100+ tasks and test performance
# Test with slow network conditions
```

**End of Week 3 Validation:**
```bash
# Final Week 3 validation
echo "Week 3 Completion Checklist:"
echo "✅ All 10 departments have unique configurations"
echo "✅ Mobile experience is optimized and tested"
echo "✅ Touch gestures enhance mobile usability"
echo "✅ Error handling provides good user feedback"
echo "✅ Performance is optimized for large datasets"
echo "✅ Accessibility standards are met"
echo "✅ Cross-browser compatibility verified"
echo "✅ Integration tests pass"
echo "✅ Manual testing completed"
echo "✅ Ready for Week 4 advanced features"
```

---

# WEEK 4-5: Advanced Features
*Goal: CSV import, Kanban view, Leadership portal*

## Week 4 Daily Breakdown

### DAY 15 (Monday): CSV Import System

**Morning: CSV Import Backend (3-4 hours)**

**1. Add CSV import API endpoints:**

```javascript
// Add to Code.js
function apiProcessCSVImport(params) {
  try {
    if (!params.csvData || !params.department) {
      throw new Error('CSV data and department are required');
    }

    // Use your existing CSV processing logic
    const result = processBulkCSVImport(params);

    return {
      department: params.department,
      imported: result.imported || 0,
      failed: result.failed || 0,
      errors: result.errors || [],
      tasks: result.tasks || [],
      message: `Successfully imported ${result.imported} tasks`
    };
  } catch (error) {
    console.error('Error in apiProcessCSVImport:', error);
    throw new Error('Failed to process CSV import: ' + error.message);
  }
}

function apiGetCSVTemplate(params) {
  try {
    const department = params.department;

    // Generate CSV template for the department
    const template = generateCSVTemplate(department);

    return {
      department: department,
      template: template,
      headers: ['title', 'description', 'assignee', 'priority', 'status', 'dueDate'],
      sample: [
        'Sample Task Title',
        'This is a sample task description',
        'John Doe',
        'Medium',
        'Not Started',
        '2024-12-31'
      ]
    };
  } catch (error) {
    console.error('Error in apiGetCSVTemplate:', error);
    throw new Error('Failed to generate CSV template: ' + error.message);
  }
}

// Update API router
case 'processCSVImport':
  return jsonResponse(apiProcessCSVImport(e.parameter), cors);
case 'getCSVTemplate':
  return jsonResponse(apiGetCSVTemplate(e.parameter), cors);
```

**2. Create CSV utilities:**

```typescript
// src/utils/csvUtils.ts
export interface CSVTask {
  title: string;
  description: string;
  assignee: string;
  priority: 'High' | 'Medium' | 'Low';
  status: 'Not Started' | 'In Progress' | 'Completed';
  dueDate?: string;
}

export const parseCSVFile = (file: File): Promise<CSVTask[]> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();

    reader.onload = (e) => {
      try {
        const csv = e.target?.result as string;
        const lines = csv.split('\n').filter(line => line.trim());

        if (lines.length < 2) {
          reject(new Error('CSV file must have at least a header row and one data row'));
          return;
        }

        const headers = lines[0].split(',').map(h => h.trim().toLowerCase());
        const expectedHeaders = ['title', 'description', 'assignee', 'priority', 'status'];

        // Validate headers
        for (const required of expectedHeaders) {
          if (!headers.includes(required)) {
            reject(new Error(`Missing required column: ${required}`));
            return;
          }
        }

        const tasks: CSVTask[] = [];

        for (let i = 1; i < lines.length; i++) {
          const values = lines[i].split(',').map(v => v.trim().replace(/^"|"$/g, ''));

          if (values.length < expectedHeaders.length) continue;

          const task: CSVTask = {
            title: values[headers.indexOf('title')] || '',
            description: values[headers.indexOf('description')] || '',
            assignee: values[headers.indexOf('assignee')] || 'Unassigned',
            priority: (values[headers.indexOf('priority')] as CSVTask['priority']) || 'Medium',
            status: (values[headers.indexOf('status')] as CSVTask['status']) || 'Not Started',
            dueDate: values[headers.indexOf('duedate')] || values[headers.indexOf('due_date')] || ''
          };

          if (task.title) {
            tasks.push(task);
          }
        }

        resolve(tasks);
      } catch (error) {
        reject(new Error('Failed to parse CSV file: ' + (error as Error).message));
      }
    };

    reader.onerror = () => reject(new Error('Failed to read file'));
    reader.readAsText(file);
  });
};

export const generateCSVTemplate = (department: string): string => {
  const headers = ['title', 'description', 'assignee', 'priority', 'status', 'dueDate'];
  const sample = [
    `Sample ${department} Task`,
    'This is a sample task description',
    'John Doe',
    'Medium',
    'Not Started',
    '2024-12-31'
  ];

  return [
    headers.join(','),
    sample.map(value => `"${value}"`).join(',')
  ].join('\n');
};

export const validateCSVTasks = (tasks: CSVTask[]): { valid: CSVTask[], errors: string[] } => {
  const valid: CSVTask[] = [];
  const errors: string[] = [];

  tasks.forEach((task, index) => {
    const rowNum = index + 2; // +2 because index starts at 0 and CSV has header

    if (!task.title?.trim()) {
      errors.push(`Row ${rowNum}: Title is required`);
      return;
    }

    if (!['High', 'Medium', 'Low'].includes(task.priority)) {
      errors.push(`Row ${rowNum}: Priority must be High, Medium, or Low`);
      return;
    }

    if (!['Not Started', 'In Progress', 'Completed'].includes(task.status)) {
      errors.push(`Row ${rowNum}: Status must be "Not Started", "In Progress", or "Completed"`);
      return;
    }

    if (task.dueDate && !isValidDate(task.dueDate)) {
      errors.push(`Row ${rowNum}: Invalid date format (use YYYY-MM-DD)`);
      return;
    }

    valid.push(task);
  });

  return { valid, errors };
};

const isValidDate = (dateString: string): boolean => {
  const date = new Date(dateString);
  return date instanceof Date && !isNaN(date.getTime());
};
```

**Afternoon: CSV Import UI (3-4 hours)**

**3. Create CSV Import Modal:**

```tsx
// src/components/modals/CSVImportModal.tsx
import React, { useState, useRef } from 'react';
import { Department } from '../../types';
import { parseCSVFile, validateCSVTasks, CSVTask } from '../../utils/csvUtils';
import { useTaskStore } from '../../stores/taskStore';
import { taskAPI } from '../../services/api';

interface CSVImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  department: Department;
}

export const CSVImportModal: React.FC<CSVImportModalProps> = ({
  isOpen,
  onClose,
  department
}) => {
  const [step, setStep] = useState<'upload' | 'preview' | 'importing' | 'results'>('upload');
  const [file, setFile] = useState<File | null>(null);
  const [parsedTasks, setParsedTasks] = useState<CSVTask[]>([]);
  const [validTasks, setValidTasks] = useState<CSVTask[]>([]);
  const [errors, setErrors] = useState<string[]>([]);
  const [importResults, setImportResults] = useState<{ imported: number; failed: number; errors: string[] } | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const { fetchTasks } = useTaskStore();

  const handleFileSelect = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = event.target.files?.[0];
    if (!selectedFile) return;

    if (!selectedFile.name.toLowerCase().endsWith('.csv')) {
      setErrors(['Please select a CSV file']);
      return;
    }

    setFile(selectedFile);
    setErrors([]);

    try {
      const tasks = await parseCSVFile(selectedFile);
      const { valid, errors: validationErrors } = validateCSVTasks(tasks);

      setParsedTasks(tasks);
      setValidTasks(valid);
      setErrors(validationErrors);
      setStep('preview');
    } catch (error) {
      setErrors([error instanceof Error ? error.message : 'Failed to parse CSV file']);
    }
  };

  const handleImport = async () => {
    if (validTasks.length === 0) return;

    setStep('importing');

    try {
      const csvData = validTasks.map(task => ({
        ...task,
        department: department.id
      }));

      const response = await taskAPI.processCSVImport({
        csvData: JSON.stringify(csvData),
        department: department.id
      });

      setImportResults({
        imported: response.data.data.imported,
        failed: response.data.data.failed,
        errors: response.data.data.errors || []
      });

      setStep('results');

      // Refresh tasks
      await fetchTasks(department.id);
    } catch (error) {
      setErrors([error instanceof Error ? error.message : 'Import failed']);
      setStep('preview');
    }
  };

  const downloadTemplate = async () => {
    try {
      const response = await taskAPI.getCSVTemplate(department.id);
      const template = response.data.data.template;

      const blob = new Blob([template], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${department.id}-tasks-template.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Failed to download template:', error);
    }
  };

  const resetModal = () => {
    setStep('upload');
    setFile(null);
    setParsedTasks([]);
    setValidTasks([]);
    setErrors([]);
    setImportResults(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleClose = () => {
    resetModal();
    onClose();
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-2xl font-semibold text-gray-900">
            Import CSV - {department.name}
          </h2>
          <button
            onClick={handleClose}
            className="text-gray-400 hover:text-gray-600"
          >
            ✕
          </button>
        </div>

        {step === 'upload' && (
          <div className="space-y-6">
            <div className="text-center">
              <div className="text-6xl mb-4">📁</div>
              <h3 className="text-lg font-semibold text-gray-900 mb-2">
                Upload CSV File
              </h3>
              <p className="text-gray-600 mb-4">
                Import multiple tasks at once using a CSV file
              </p>
            </div>

            <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition-colors">
              <input
                ref={fileInputRef}
                type="file"
                accept=".csv"
                onChange={handleFileSelect}
                className="hidden"
              />
              <button
                onClick={() => fileInputRef.current?.click()}
                className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
              >
                Choose CSV File
              </button>
              <p className="text-sm text-gray-500 mt-2">
                Or drag and drop a CSV file here
              </p>
            </div>

            <div className="bg-blue-50 rounded-lg p-4">
              <h4 className="font-semibold text-blue-900 mb-2">Need a template?</h4>
              <p className="text-blue-700 text-sm mb-3">
                Download a CSV template with the correct format and sample data.
              </p>
              <button
                onClick={downloadTemplate}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
              >
                Download Template
              </button>
            </div>

            {errors.length > 0 && (
              <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 className="font-semibold text-red-800 mb-2">Errors:</h4>
                <ul className="list-disc list-inside text-red-700 text-sm space-y-1">
                  {errors.map((error, index) => (
                    <li key={index}>{error}</li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        )}

        {step === 'preview' && (
          <div className="space-y-6">
            <div className="flex justify-between items-center">
              <h3 className="text-lg font-semibold text-gray-900">
                Preview Import ({validTasks.length} valid tasks)
              </h3>
              <button
                onClick={() => setStep('upload')}
                className="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
              >
                Back
              </button>
            </div>

            {errors.length > 0 && (
              <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 className="font-semibold text-yellow-800 mb-2">
                  {errors.length} validation errors found:
                </h4>
                <ul className="list-disc list-inside text-yellow-700 text-sm space-y-1 max-h-32 overflow-y-auto">
                  {errors.map((error, index) => (
                    <li key={index}>{error}</li>
                  ))}
                </ul>
              </div>
            )}

            {validTasks.length > 0 && (
              <div>
                <h4 className="font-semibold text-gray-900 mb-3">Valid Tasks to Import:</h4>
                <div className="bg-gray-50 rounded-lg p-4 max-h-64 overflow-y-auto">
                  <div className="space-y-2">
                    {validTasks.slice(0, 10).map((task, index) => (
                      <div key={index} className="bg-white rounded p-3 border border-gray-200">
                        <div className="font-medium text-gray-900">{task.title}</div>
                        <div className="text-sm text-gray-600">
                          {task.assignee} • {task.priority} • {task.status}
                        </div>
                      </div>
                    ))}
                    {validTasks.length > 10 && (
                      <div className="text-center text-sm text-gray-500 py-2">
                        ...and {validTasks.length - 10} more tasks
                      </div>
                    )}
                  </div>
                </div>
              </div>
            )}

            <div className="flex justify-end space-x-3">
              <button
                onClick={handleClose}
                className="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50"
              >
                Cancel
              </button>
              <button
                onClick={handleImport}
                disabled={validTasks.length === 0}
                className="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50"
              >
                Import {validTasks.length} Tasks
              </button>
            </div>
          </div>
        )}

        {step === 'importing' && (
          <div className="text-center py-12">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
            <h3 className="text-lg font-semibold text-gray-900 mb-2">Importing Tasks...</h3>
            <p className="text-gray-600">Please wait while we process your CSV file.</p>
          </div>
        )}

        {step === 'results' && importResults && (
          <div className="space-y-6">
            <div className="text-center">
              <div className="text-6xl mb-4">✅</div>
              <h3 className="text-lg font-semibold text-gray-900 mb-2">
                Import Complete!
              </h3>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="bg-green-50 rounded-lg p-4 text-center">
                <div className="text-2xl font-bold text-green-600">{importResults.imported}</div>
                <div className="text-sm text-green-800">Tasks Imported</div>
              </div>
              <div className="bg-red-50 rounded-lg p-4 text-center">
                <div className="text-2xl font-bold text-red-600">{importResults.failed}</div>
                <div className="text-sm text-red-800">Tasks Failed</div>
              </div>
            </div>

            {importResults.errors.length > 0 && (
              <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 className="font-semibold text-red-800 mb-2">Import Errors:</h4>
                <ul className="list-disc list-inside text-red-700 text-sm space-y-1">
                  {importResults.errors.map((error, index) => (
                    <li key={index}>{error}</li>
                  ))}
                </ul>
              </div>
            )}

            <div className="flex justify-end">
              <button
                onClick={handleClose}
                className="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
              >
                Done
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
```

**4. Add CSV import button to DepartmentPage:**

```tsx
// Update src/pages/DepartmentPage.tsx
import { CSVImportModal } from '../components/modals/CSVImportModal';

// Add state
const [isCSVImportOpen, setIsCSVImportOpen] = useState(false);

// Add button to action bar
<div className="mb-6 flex flex-col sm:flex-row gap-3">
  <button
    onClick={() => setIsCreateModalOpen(true)}
    className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
  >
    + Create New Task
  </button>
  <button
    onClick={() => setIsCSVImportOpen(true)}
    className="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium"
  >
    📁 Import CSV
  </button>
</div>

// Add modal to bottom
<CSVImportModal
  isOpen={isCSVImportOpen}
  onClose={() => setIsCSVImportOpen(false)}
  department={department}
/>
```

**Testing Day 15:**
```bash
# Test CSV import
npm run dev
# Create sample CSV file, test import process
# Test validation and error handling

# Deploy Apps Script updates
clasp push
```

---

### DAY 16 (Tuesday): Kanban Board View

**Morning: Kanban Backend Support (2-3 hours)**

**1. Add Kanban API endpoint:**

```javascript
// Add to Code.js
function apiGetKanbanData(params) {
  try {
    const department = params.department;
    if (!department) {
      throw new Error('Department parameter required');
    }

    // Use your existing getKanbanData function
    const kanbanData = getKanbanData(department);

    return {
      department: department,
      columns: {
        'not-started': {
          id: 'not-started',
          title: 'Not Started',
          taskIds: kanbanData.notStarted || []
        },
        'in-progress': {
          id: 'in-progress',
          title: 'In Progress',
          taskIds: kanbanData.inProgress || []
        },
        'completed': {
          id: 'completed',
          title: 'Completed',
          taskIds: kanbanData.completed || []
        }
      },
      tasks: kanbanData.tasks || {},
      columnOrder: ['not-started', 'in-progress', 'completed']
    };
  } catch (error) {
    console.error('Error in apiGetKanbanData:', error);
    throw new Error('Failed to fetch kanban data: ' + error.message);
  }
}

function apiUpdateTaskStatus(params) {
  try {
    if (!params.taskId || !params.status) {
      throw new Error('Task ID and status are required');
    }

    // Use your existing updateTaskStatus function
    const result = updateTaskStatus(params.taskId, params.status);

    return {
      taskId: params.taskId,
      status: params.status,
      message: 'Task status updated successfully'
    };
  } catch (error) {
    console.error('Error in apiUpdateTaskStatus:', error);
    throw new Error('Failed to update task status: ' + error.message);
  }
}

// Update API router
case 'getKanbanData':
  return jsonResponse(apiGetKanbanData(e.parameter), cors);
case 'updateTaskStatus':
  return jsonResponse(apiUpdateTaskStatus(e.parameter), cors);
```

**Afternoon: Kanban UI Components (4-5 hours)**

**2. Create Kanban board components:**

```tsx
// src/components/kanban/KanbanBoard.tsx
import React, { useState, useEffect } from 'react';
import { DragDropContext, Droppable, Draggable, DropResult } from 'react-beautiful-dnd';
import { Task, Department } from '../../types';
import { KanbanCard } from './KanbanCard';
import { useTaskStore } from '../../stores/taskStore';
import { taskAPI } from '../../services/api';

interface KanbanColumn {
  id: string;
  title: string;
  taskIds: string[];
}

interface KanbanData {
  columns: Record<string, KanbanColumn>;
  tasks: Record<string, Task>;
  columnOrder: string[];
}

interface KanbanBoardProps {
  department: Department;
}

export const KanbanBoard: React.FC<KanbanBoardProps> = ({ department }) => {
  const [kanbanData, setKanbanData] = useState<KanbanData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const { tasks } = useTaskStore();

  useEffect(() => {
    fetchKanbanData();
  }, [department.id]);

  const fetchKanbanData = async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await taskAPI.getKanbanData(department.id);
      setKanbanData(response.data.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load kanban data');
    } finally {
      setLoading(false);
    }
  };

  const onDragEnd = async (result: DropResult) => {
    const { destination, source, draggableId } = result;

    if (!destination || !kanbanData) return;

    if (
      destination.droppableId === source.droppableId &&
      destination.index === source.index
    ) {
      return;
    }

    const start = kanbanData.columns[source.droppableId];
    const finish = kanbanData.columns[destination.droppableId];

    if (start === finish) {
      // Moving within same column
      const newTaskIds = Array.from(start.taskIds);
      newTaskIds.splice(source.index, 1);
      newTaskIds.splice(destination.index, 0, draggableId);

      const newColumn = {
        ...start,
        taskIds: newTaskIds,
      };

      setKanbanData({
        ...kanbanData,
        columns: {
          ...kanbanData.columns,
          [newColumn.id]: newColumn,
        },
      });
    } else {
      // Moving to different column
      const startTaskIds = Array.from(start.taskIds);
      startTaskIds.splice(source.index, 1);
      const newStart = {
        ...start,
        taskIds: startTaskIds,
      };

      const finishTaskIds = Array.from(finish.taskIds);
      finishTaskIds.splice(destination.index, 0, draggableId);
      const newFinish = {
        ...finish,
        taskIds: finishTaskIds,
      };

      // Update local state immediately
      setKanbanData({
        ...kanbanData,
        columns: {
          ...kanbanData.columns,
          [newStart.id]: newStart,
          [newFinish.id]: newFinish,
        },
      });

      // Update backend
      try {
        const newStatus = getStatusFromColumnId(destination.droppableId);
        await taskAPI.updateTaskStatus(draggableId, newStatus);
      } catch (error) {
        console.error('Failed to update task status:', error);
        // Revert on error
        fetchKanbanData();
      }
    }
  };

  const getStatusFromColumnId = (columnId: string): Task['status'] => {
    switch (columnId) {
      case 'not-started':
        return 'Not Started';
      case 'in-progress':
        return 'In Progress';
      case 'completed':
        return 'Completed';
      default:
        return 'Not Started';
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        <span className="ml-2 text-gray-600">Loading kanban board...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
        <div className="text-red-600 mb-2">Failed to load kanban board</div>
        <div className="text-sm text-red-500 mb-4">{error}</div>
        <button
          onClick={fetchKanbanData}
          className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
        >
          Try Again
        </button>
      </div>
    );
  }

  if (!kanbanData) return null;

  return (
    <div className="h-full">
      <DragDropContext onDragEnd={onDragEnd}>
        <div className="flex space-x-4 h-full overflow-x-auto pb-4">
          {kanbanData.columnOrder.map(columnId => {
            const column = kanbanData.columns[columnId];
            const columnTasks = column.taskIds.map(taskId => kanbanData.tasks[taskId]).filter(Boolean);

            return (
              <div key={column.id} className="flex-shrink-0 w-80">
                <div className="bg-gray-100 rounded-lg p-4 h-full">
                  <div className="flex items-center justify-between mb-4">
                    <h3 className="font-semibold text-gray-900">{column.title}</h3>
                    <span className="bg-gray-200 text-gray-700 px-2 py-1 rounded-full text-sm">
                      {columnTasks.length}
                    </span>
                  </div>

                  <Droppable droppableId={column.id}>
                    {(provided, snapshot) => (
                      <div
                        ref={provided.innerRef}
                        {...provided.droppableProps}
                        className={`space-y-3 min-h-[200px] p-2 rounded-md transition-colors ${
                          snapshot.isDraggingOver ? 'bg-blue-50' : ''
                        }`}
                      >
                        {columnTasks.map((task, index) => (
                          <Draggable key={task.id} draggableId={task.id} index={index}>
                            {(provided, snapshot) => (
                              <div
                                ref={provided.innerRef}
                                {...provided.draggableProps}
                                {...provided.dragHandleProps}
                                className={`transition-transform ${
                                  snapshot.isDragging ? 'rotate-2 scale-105' : ''
                                }`}
                              >
                                <KanbanCard task={task} department={department} />
                              </div>
                            )}
                          </Draggable>
                        ))}
                        {provided.placeholder}
                      </div>
                    )}
                  </Droppable>
                </div>
              </div>
            );
          })}
        </div>
      </DragDropContext>
    </div>
  );
};
```

**3. Create KanbanCard component:**

```tsx
// src/components/kanban/KanbanCard.tsx
import React from 'react';
import { Task, Department } from '../../types';

interface KanbanCardProps {
  task: Task;
  department: Department;
}

export const KanbanCard: React.FC<KanbanCardProps> = ({ task, department }) => {
  const priorityColors = {
    High: 'bg-red-100 text-red-800 border-red-200',
    Medium: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    Low: 'bg-green-100 text-green-800 border-green-200',
  };

  return (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-move">
      <div className="flex items-start justify-between mb-2">
        <h4 className="font-medium text-gray-900 text-sm break-words flex-1 mr-2">
          {task.title}
        </h4>
        <span className={`px-2 py-1 rounded-full text-xs font-medium flex-shrink-0 ${priorityColors[task.priority]}`}>
          {task.priority}
        </span>
      </div>

      {task.description && (
        <p className="text-gray-600 text-sm mb-3 line-clamp-2">
          {task.description}
        </p>
      )}

      <div className="flex items-center justify-between text-xs text-gray-500">
        <div className="flex items-center space-x-2">
          {task.avatarUrl ? (
            <img
              src={task.avatarUrl}
              alt={task.assignee}
              className="w-5 h-5 rounded-full"
              onError={(e) => {
                const target = e.target as HTMLImageElement;
                target.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(task.assignee)}&background=random&size=20`;
              }}
            />
          ) : (
            <div className="w-5 h-5 rounded-full bg-gray-200 flex items-center justify-center">
              <span className="text-xs text-gray-500">?</span>
            </div>
          )}
          <span className="truncate">{task.assignee}</span>
        </div>

        {task.dueDate && (
          <div className="text-right">
            <div>Due: {new Date(task.dueDate).toLocaleDateString()}</div>
          </div>
        )}
      </div>
    </div>
  );
};
```

**4. Add Kanban view toggle to DepartmentPage:**

```tsx
// Update src/pages/DepartmentPage.tsx
import { KanbanBoard } from '../components/kanban/KanbanBoard';

// Add state
const [viewMode, setViewMode] = useState<'grid' | 'kanban'>('grid');

// Add view toggle buttons
<div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
  <div className="flex gap-3">
    <button
      onClick={() => setIsCreateModalOpen(true)}
      className="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
    >
      + Create New Task
    </button>
    <button
      onClick={() => setIsCSVImportOpen(true)}
      className="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium"
    >
      📁 Import CSV
    </button>
  </div>

  <div className="flex bg-gray-100 rounded-lg p-1">
    <button
      onClick={() => setViewMode('grid')}
      className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
        viewMode === 'grid'
          ? 'bg-white text-gray-900 shadow-sm'
          : 'text-gray-600 hover:text-gray-900'
      }`}
    >
      📋 Grid View
    </button>
    <button
      onClick={() => setViewMode('kanban')}
      className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
        viewMode === 'kanban'
          ? 'bg-white text-gray-900 shadow-sm'
          : 'text-gray-600 hover:text-gray-900'
      }`}
    >
      📊 Kanban View
    </button>
  </div>
</div>

// Replace task display section
{tasks.length === 0 ? (
  <div className="text-center py-12">
    <div className="text-6xl mb-4">📝</div>
    <h2 className="text-xl font-semibold text-gray-600 mb-2">No tasks yet</h2>
    <p className="text-gray-500">Create your first task to get started!</p>
  </div>
) : viewMode === 'grid' ? (
  <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
    {tasks.map(task => (
      <TaskCard
        key={task.id}
        task={task}
        department={department}
        onUpdate={(updatedTask) => setEditingTask(updatedTask)}
        onDelete={handleDeleteTask}
      />
    ))}
  </div>
) : (
  <div className="h-[600px]">
    <KanbanBoard department={department} />
  </div>
)}
```

**5. Install drag and drop dependency:**

```bash
npm install react-beautiful-dnd
npm install -D @types/react-beautiful-dnd
```

**Testing Day 16:**
```bash
# Test Kanban board
npm run dev
# Test drag and drop functionality
# Verify status updates work correctly

# Deploy Apps Script updates
clasp push
```

---

### DAY 17 (Wednesday): Leadership Portal

**Morning: Leadership API Endpoints (3-4 hours)**

**1. Add leadership-specific API endpoints:**

```javascript
// Add to Code.js
function apiGetLeadershipDashboard(params) {
  try {
    const email = params.email;
    if (!email) {
      throw new Error('Email parameter required');
    }

    // Verify leadership access
    const authResult = verifyLeadershipAccess(email);
    if (!authResult.success) {
      throw new Error('Unauthorized access - leadership only');
    }

    // Get dashboard data for all departments
    const departments = ['sales', 'accounting', 'tech', 'marketing', 'hr', 'customer-retention', 'purchasing', 'trade-shows', 'swag', 'ideas'];
    const dashboardData = {};

    departments.forEach(dept => {
      try {
        const tasks = getTasksByDepartment(dept);
        dashboardData[dept] = {
          totalTasks: tasks.length,
          completed: tasks.filter(t => t.status === 'Completed').length,
          inProgress: tasks.filter(t => t.status === 'In Progress').length,
          notStarted: tasks.filter(t => t.status === 'Not Started').length,
          highPriority: tasks.filter(t => t.priority === 'High').length,
          overdue: tasks.filter(t => t.dueDate && new Date(t.dueDate) < new Date()).length
        };
      } catch (error) {
        dashboardData[dept] = { error: error.message };
      }
    });

    return {
      email: email,
      departments: dashboardData,
      summary: {
        totalTasks: Object.values(dashboardData).reduce((sum, dept) => sum + (dept.totalTasks || 0), 0),
        totalCompleted: Object.values(dashboardData).reduce((sum, dept) => sum + (dept.completed || 0), 0),
        totalInProgress: Object.values(dashboardData).reduce((sum, dept) => sum + (dept.inProgress || 0), 0),
        totalHighPriority: Object.values(dashboardData).reduce((sum, dept) => sum + (dept.highPriority || 0), 0)
      },
      lastUpdated: new Date().toISOString()
    };
  } catch (error) {
    console.error('Error in apiGetLeadershipDashboard:', error);
    throw new Error('Failed to fetch leadership dashboard: ' + error.message);
  }
}

function apiGetDepartmentReport(params) {
  try {
    const { email, department, startDate, endDate } = params;

    // Verify leadership access
    const authResult = verifyLeadershipAccess(email);
    if (!authResult.success) {
      throw new Error('Unauthorized access - leadership only');
    }

    if (!department) {
      throw new Error('Department parameter required');
    }

    // Get detailed department data
    const tasks = getTasksByDepartment(department);
    const filteredTasks = startDate && endDate
      ? tasks.filter(t => {
          const createdDate = new Date(t.createdDate);
          return createdDate >= new Date(startDate) && createdDate <= new Date(endDate);
        })
      : tasks;

    // Calculate metrics
    const metrics = {
      totalTasks: filteredTasks.length,
      completionRate: filteredTasks.length > 0
        ? Math.round((filteredTasks.filter(t => t.status === 'Completed').length / filteredTasks.length) * 100)
        : 0,
      averageCompletionTime: calculateAverageCompletionTime(filteredTasks),
      tasksByPriority: {
        High: filteredTasks.filter(t => t.priority === 'High').length,
        Medium: filteredTasks.filter(t => t.priority === 'Medium').length,
        Low: filteredTasks.filter(t => t.priority === 'Low').length
      },
      tasksByStatus: {
        'Not Started': filteredTasks.filter(t => t.status === 'Not Started').length,
        'In Progress': filteredTasks.filter(t => t.status === 'In Progress').length,
        'Completed': filteredTasks.filter(t => t.status === 'Completed').length
      },
      topAssignees: getTopAssignees(filteredTasks),
      overdueCount: filteredTasks.filter(t => t.dueDate && new Date(t.dueDate) < new Date()).length
    };

    return {
      department: department,
      dateRange: { startDate, endDate },
      metrics: metrics,
      tasks: filteredTasks.slice(0, 50), // Limit for performance
      generatedAt: new Date().toISOString()
    };
  } catch (error) {
    console.error('Error in apiGetDepartmentReport:', error);
    throw new Error('Failed to generate department report: ' + error.message);
  }
}

function calculateAverageCompletionTime(tasks) {
  const completedTasks = tasks.filter(t => t.status === 'Completed' && t.createdDate && t.completedDate);
  if (completedTasks.length === 0) return 0;

  const totalTime = completedTasks.reduce((sum, task) => {
    const created = new Date(task.createdDate);
    const completed = new Date(task.completedDate);
    return sum + (completed.getTime() - created.getTime());
  }, 0);

  return Math.round(totalTime / completedTasks.length / (1000 * 60 * 60 * 24)); // Average days
}

function getTopAssignees(tasks) {
  const assigneeCounts = {};
  tasks.forEach(task => {
    if (task.assignee && task.assignee !== 'Unassigned') {
      assigneeCounts[task.assignee] = (assigneeCounts[task.assignee] || 0) + 1;
    }
  });

  return Object.entries(assigneeCounts)
    .sort(([,a], [,b]) => b - a)
    .slice(0, 5)
    .map(([name, count]) => ({ name, taskCount: count }));
}

// Update API router
case 'getLeadershipDashboard':
  return jsonResponse(apiGetLeadershipDashboard(e.parameter), cors);
case 'getDepartmentReport':
  return jsonResponse(apiGetDepartmentReport(e.parameter), cors);
```

**2. Create leadership store:**

```typescript
// src/stores/leadershipStore.ts
import { create } from 'zustand';
import { taskAPI } from '../services/api';

interface DepartmentSummary {
  totalTasks: number;
  completed: number;
  inProgress: number;
  notStarted: number;
  highPriority: number;
  overdue: number;
  error?: string;
}

interface LeadershipDashboard {
  departments: Record<string, DepartmentSummary>;
  summary: {
    totalTasks: number;
    totalCompleted: number;
    totalInProgress: number;
    totalHighPriority: number;
  };
  lastUpdated: string;
}

interface DepartmentReport {
  department: string;
  dateRange: { startDate?: string; endDate?: string };
  metrics: {
    totalTasks: number;
    completionRate: number;
    averageCompletionTime: number;
    tasksByPriority: Record<string, number>;
    tasksByStatus: Record<string, number>;
    topAssignees: Array<{ name: string; taskCount: number }>;
    overdueCount: number;
  };
  tasks: any[];
  generatedAt: string;
}

interface LeadershipStore {
  dashboard: LeadershipDashboard | null;
  currentReport: DepartmentReport | null;
  loading: boolean;
  error: string | null;
  userEmail: string | null;

  setUserEmail: (email: string) => void;
  fetchDashboard: () => Promise<void>;
  fetchDepartmentReport: (department: string, startDate?: string, endDate?: string) => Promise<void>;
  clearError: () => void;
}

export const useLeadershipStore = create<LeadershipStore>((set, get) => ({
  dashboard: null,
  currentReport: null,
  loading: false,
  error: null,
  userEmail: null,

  setUserEmail: (email: string) => set({ userEmail: email }),

  fetchDashboard: async () => {
    const { userEmail } = get();
    if (!userEmail) {
      set({ error: 'User email not set' });
      return;
    }

    set({ loading: true, error: null });
    try {
      const response = await taskAPI.getLeadershipDashboard(userEmail);
      set({
        dashboard: response.data.data,
        loading: false
      });
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : 'Failed to fetch dashboard',
        loading: false
      });
    }
  },

  fetchDepartmentReport: async (department: string, startDate?: string, endDate?: string) => {
    const { userEmail } = get();
    if (!userEmail) {
      set({ error: 'User email not set' });
      return;
    }

    set({ loading: true, error: null });
    try {
      const response = await taskAPI.getDepartmentReport({
        email: userEmail,
        department,
        startDate,
        endDate
      });
      set({
        currentReport: response.data.data,
        loading: false
      });
    } catch (error) {
      set({
        error: error instanceof Error ? error.message : 'Failed to fetch report',
        loading: false
      });
    }
  },

  clearError: () => set({ error: null })
}));
```

**Afternoon: Leadership Dashboard UI (3-4 hours)**

**3. Create Leadership Dashboard page:**

```tsx
// src/pages/LeadershipDashboard.tsx
import React, { useEffect, useState } from 'react';
import { useLeadershipStore } from '../stores/leadershipStore';
import { DEPARTMENTS } from '../config/departments';
import { LoadingSpinner } from '../components/shared/LoadingSpinner';

export const LeadershipDashboard: React.FC = () => {
  const [userEmail, setUserEmailState] = useState('');
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  const {
    dashboard,
    loading,
    error,
    setUserEmail,
    fetchDashboard,
    clearError
  } = useLeadershipStore();

  const handleEmailSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!userEmail.trim()) return;

    setUserEmail(userEmail.trim());
    setIsAuthenticated(true);
    await fetchDashboard();
  };

  const getDepartmentName = (deptId: string) => {
    return DEPARTMENTS[deptId]?.name || deptId;
  };

  const getDepartmentIcon = (deptId: string) => {
    return DEPARTMENTS[deptId]?.icon || '📋';
  };

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-lg shadow-md p-8 w-full max-w-md">
          <div className="text-center mb-6">
            <div className="text-4xl mb-3">🔐</div>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">Leadership Portal</h1>
            <p className="text-gray-600">Enter your email to access the dashboard</p>
          </div>

          <form onSubmit={handleEmailSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Email Address
              </label>
              <input
                type="email"
                value={userEmail}
                onChange={(e) => setUserEmailState(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="your.email@company.com"
                required
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 flex items-center justify-center"
            >
              {loading ? <LoadingSpinner size="sm" className="mr-2" /> : null}
              {loading ? 'Verifying...' : 'Access Dashboard'}
            </button>
          </form>

          {error && (
            <div className="mt-4 bg-red-50 border border-red-200 rounded-md p-3">
              <div className="text-red-600 text-sm">{error}</div>
            </div>
          )}
        </div>
      </div>
    );
  }

  if (loading && !dashboard) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <LoadingSpinner size="lg" className="mx-auto mb-4" />
          <p className="text-gray-600">Loading leadership dashboard...</p>
        </div>
      </div>
    );
  }

  if (error && !dashboard) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white rounded-lg shadow-md p-8 w-full max-w-md text-center">
          <div className="text-4xl mb-3">⚠️</div>
          <h2 className="text-xl font-bold text-gray-900 mb-2">Access Denied</h2>
          <p className="text-gray-600 mb-4">{error}</p>
          <button
            onClick={() => setIsAuthenticated(false)}
            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
          >
            Try Different Email
          </button>
        </div>
      </div>
    );
  }

  if (!dashboard) return null;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white shadow-sm border-b">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-3">
              <div className="text-2xl">👨‍💼</div>
              <div>
                <h1 className="text-xl font-bold text-gray-900">Leadership Dashboard</h1>
                <p className="text-sm text-gray-600">Welcome, {userEmail}</p>
              </div>
            </div>
            <div className="flex items-center space-x-4">
              <div className="text-sm text-gray-500">
                Last updated: {new Date(dashboard.lastUpdated).toLocaleString()}
              </div>
              <button
                onClick={fetchDashboard}
                className="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
              >
                Refresh
              </button>
            </div>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-8">
        {/* Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center">
              <div className="text-3xl text-blue-600 mr-4">📊</div>
              <div>
                <div className="text-2xl font-bold text-gray-900">{dashboard.summary.totalTasks}</div>
                <div className="text-sm text-gray-600">Total Tasks</div>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center">
              <div className="text-3xl text-green-600 mr-4">✅</div>
              <div>
                <div className="text-2xl font-bold text-gray-900">{dashboard.summary.totalCompleted}</div>
                <div className="text-sm text-gray-600">Completed</div>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center">
              <div className="text-3xl text-yellow-600 mr-4">⏳</div>
              <div>
                <div className="text-2xl font-bold text-gray-900">{dashboard.summary.totalInProgress}</div>
                <div className="text-sm text-gray-600">In Progress</div>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex items-center">
              <div className="text-3xl text-red-600 mr-4">🔥</div>
              <div>
                <div className="text-2xl font-bold text-gray-900">{dashboard.summary.totalHighPriority}</div>
                <div className="text-sm text-gray-600">High Priority</div>
              </div>
            </div>
          </div>
        </div>

        {/* Department Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {Object.entries(dashboard.departments).map(([deptId, data]) => (
            <div key={deptId} className="bg-white rounded-lg shadow-md p-6">
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center space-x-3">
                  <span className="text-2xl">{getDepartmentIcon(deptId)}</span>
                  <h3 className="text-lg font-semibold text-gray-900">
                    {getDepartmentName(deptId)}
                  </h3>
                </div>
                {data.error && (
                  <span className="text-red-500 text-sm">Error</span>
                )}
              </div>

              {data.error ? (
                <div className="text-sm text-red-600">
                  Failed to load data: {data.error}
                </div>
              ) : (
                <div className="space-y-3">
                  <div className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                      <div className="text-gray-600">Total Tasks</div>
                      <div className="text-xl font-semibold text-gray-900">{data.totalTasks}</div>
                    </div>
                    <div>
                      <div className="text-gray-600">Completed</div>
                      <div className="text-xl font-semibold text-green-600">{data.completed}</div>
                    </div>
                    <div>
                      <div className="text-gray-600">In Progress</div>
                      <div className="text-xl font-semibold text-yellow-600">{data.inProgress}</div>
                    </div>
                    <div>
                      <div className="text-gray-600">High Priority</div>
                      <div className="text-xl font-semibold text-red-600">{data.highPriority}</div>
                    </div>
                  </div>

                  {data.overdue > 0 && (
                    <div className="bg-red-50 rounded-md p-2">
                      <div className="text-sm text-red-800">
                        ⚠️ {data.overdue} overdue tasks
                      </div>
                    </div>
                  )}

                  <div className="pt-2">
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-green-500 h-2 rounded-full"
                        style={{
                          width: `${data.totalTasks > 0 ? (data.completed / data.totalTasks) * 100 : 0}%`
                        }}
                      />
                    </div>
                    <div className="text-xs text-gray-500 mt-1">
                      {data.totalTasks > 0 ? Math.round((data.completed / data.totalTasks) * 100) : 0}% complete
                    </div>
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
```

**4. Add Leadership route:**

```tsx
// Update src/App.tsx
import { LeadershipDashboard } from './pages/LeadershipDashboard';

// Add route
<Route path="/leadership" element={<LeadershipDashboard />} />
```

**5. Update API client:**

```typescript
// Add to src/services/api.ts
export const taskAPI = {
  // ... existing methods ...

  getLeadershipDashboard: (email: string) =>
    api.get(`?action=getLeadershipDashboard&email=${encodeURIComponent(email)}`),

  getDepartmentReport: (params: {
    email: string;
    department: string;
    startDate?: string;
    endDate?: string;
  }) => {
    const queryParams = new URLSearchParams({
      action: 'getDepartmentReport',
      email: params.email,
      department: params.department,
      ...(params.startDate && { startDate: params.startDate }),
      ...(params.endDate && { endDate: params.endDate })
    });
    return api.get(`?${queryParams.toString()}`);
  },
};
```

**Testing Day 17:**
```bash
# Test leadership portal
npm run dev
# Navigate to /leadership
# Test with authorized and unauthorized emails

# Deploy Apps Script updates
clasp push
```

---

### DAY 18 (Thursday): Advanced Feature Integration & Testing
**Focus:** Integrate all advanced features and ensure they work together seamlessly

**Morning Tasks (3-4 hours):**
1. **Cross-Feature Integration Testing**
   ```typescript
   // src/tests/integration/advancedFeatures.test.ts
   import { render, screen, fireEvent, waitFor } from '@testing-library/react';
   import { BrowserRouter } from 'react-router-dom';
   import { DepartmentView } from '../components/DepartmentView';
   import { useTaskStore } from '../store/taskStore';

   describe('Advanced Features Integration Tests', () => {
     test('CSV import works with Kanban view', async () => {
       const mockTasks = [
         { id: '1', title: 'Test Task', status: 'To Do', department: 'sales' },
         { id: '2', title: 'Another Task', status: 'In Progress', department: 'sales' }
       ];

       render(
         <BrowserRouter>
           <DepartmentView />
         </BrowserRouter>
       );

       // Test CSV import
       fireEvent.click(screen.getByText('Import CSV'));
       // Upload test CSV file
       // Verify tasks are imported

       // Switch to Kanban view
       fireEvent.click(screen.getByText('Kanban View'));

       // Verify imported tasks appear in correct columns
       await waitFor(() => {
         expect(screen.getByText('Test Task')).toBeInTheDocument();
       });
     });

     test('Leadership portal shows comprehensive data', async () => {
       // Mock leadership user
       const mockLeadershipData = {
         allDepartments: true,
         taskStats: { total: 150, completed: 75, inProgress: 45, pending: 30 },
         departmentBreakdown: {
           sales: { total: 30, completed: 15 },
           tech: { total: 40, completed: 20 },
           marketing: { total: 25, completed: 12 }
         }
       };

       render(
         <BrowserRouter>
           <DepartmentView />
         </BrowserRouter>
       );

       // Navigate to leadership portal
       fireEvent.click(screen.getByText('Leadership Dashboard'));

       // Verify comprehensive stats are displayed
       await waitFor(() => {
         expect(screen.getByText('150')).toBeInTheDocument(); // Total tasks
         expect(screen.getByText('Sales: 30 tasks')).toBeInTheDocument();
       });
     });

     test('Kanban drag-and-drop updates task status', async () => {
       const mockUpdateTask = jest.fn();

       render(
         <BrowserRouter>
           <DepartmentView />
         </BrowserRouter>
       );

       // Switch to Kanban view
       fireEvent.click(screen.getByText('Kanban View'));

       // Simulate drag and drop (using react-beautiful-dnd testing patterns)
       const taskCard = screen.getByText('Test Task');
       const dropZone = screen.getByText('In Progress');

       // Verify task status was updated
       expect(mockUpdateTask).toHaveBeenCalledWith(
         expect.objectContaining({ status: 'In Progress' })
       );
     });
   });
   ```

2. **Performance Optimization for Advanced Features**
   ```typescript
   // src/hooks/useAdvancedFeatureOptimization.ts
   import { useMemo, useCallback } from 'react';
   import { Task } from '../types/task';

   export const useKanbanOptimization = (tasks: Task[]) => {
     // Memoize kanban columns to prevent unnecessary re-renders
     const kanbanColumns = useMemo(() => {
       const columns = {
         'To Do': [],
         'In Progress': [],
         'Review': [],
         'Done': []
       };

       tasks.forEach(task => {
         const status = task.status || 'To Do';
         if (columns[status]) {
           columns[status].push(task);
         }
       });

       return columns;
     }, [tasks]);

     // Optimized drag handler
     const optimizedDragEnd = useCallback((result, updateTask) => {
       if (!result.destination) return;

       const { source, destination, draggableId } = result;

       // Only update if status actually changed
       if (source.droppableId !== destination.droppableId) {
         updateTask(draggableId, { status: destination.droppableId });
       }
     }, []);

     return { kanbanColumns, optimizedDragEnd };
   };

   export const useCSVImportOptimization = () => {
     // Chunk large CSV files for better performance
     const processCSVInChunks = useCallback(async (csvData: any[], chunkSize = 50) => {
       const chunks = [];
       for (let i = 0; i < csvData.length; i += chunkSize) {
         chunks.push(csvData.slice(i, i + chunkSize));
       }

       const results = [];
       for (const chunk of chunks) {
         const result = await processChunk(chunk);
         results.push(result);

         // Add small delay to prevent overwhelming the API
         await new Promise(resolve => setTimeout(resolve, 100));
       }

       return results.flat();
     }, []);

     const processChunk = async (chunk: any[]) => {
       // Process individual chunk
       return chunk.map(row => ({
         ...row,
         id: `csv-${Date.now()}-${Math.random()}`,
         createdAt: new Date().toISOString()
       }));
     };

     return { processCSVInChunks };
   };

   export const useLeadershipDataOptimization = (allTasks: Task[]) => {
     // Optimize leadership dashboard calculations
     const dashboardStats = useMemo(() => {
       const stats = {
         totalTasks: allTasks.length,
         completedTasks: 0,
         overdueTasks: 0,
         departmentStats: {},
         priorityBreakdown: {},
         recentActivity: []
       };

       const now = new Date();
       const oneWeekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);

       allTasks.forEach(task => {
         // Count completed tasks
         if (task.status === 'Completed' || task.status === 'Done') {
           stats.completedTasks++;
         }

         // Count overdue tasks
         if (task.dueDate && new Date(task.dueDate) < now && task.status !== 'Completed') {
           stats.overdueTasks++;
         }

         // Department breakdown
         if (!stats.departmentStats[task.department]) {
           stats.departmentStats[task.department] = { total: 0, completed: 0 };
         }
         stats.departmentStats[task.department].total++;
         if (task.status === 'Completed') {
           stats.departmentStats[task.department].completed++;
         }

         // Priority breakdown
         const priority = task.priority || 'Medium';
         stats.priorityBreakdown[priority] = (stats.priorityBreakdown[priority] || 0) + 1;

         // Recent activity (tasks created in last week)
         if (task.createdDate && new Date(task.createdDate) > oneWeekAgo) {
           stats.recentActivity.push(task);
         }
       });

       // Sort recent activity by date
       stats.recentActivity.sort((a, b) =>
         new Date(b.createdDate).getTime() - new Date(a.createdDate).getTime()
       );

       return stats;
     }, [allTasks]);

     return dashboardStats;
   };
   ```

**Afternoon Tasks (3-4 hours):**
1. **Advanced Error Handling & Recovery**
   ```typescript
   // src/utils/advancedErrorHandling.ts
   export class FeatureError extends Error {
     constructor(
       message: string,
       public feature: string,
       public code: string,
       public recoverable: boolean = true
     ) {
       super(message);
       this.name = 'FeatureError';
     }
   }

   export const handleCSVImportError = (error: any) => {
     if (error.message.includes('file too large')) {
       return new FeatureError(
         'CSV file is too large. Please split into smaller files (max 1000 rows).',
         'csvImport',
         'FILE_TOO_LARGE',
         true
       );
     }

     if (error.message.includes('invalid format')) {
       return new FeatureError(
         'CSV format is invalid. Please check column headers and data format.',
         'csvImport',
         'INVALID_FORMAT',
         true
       );
     }

     return new FeatureError(
       'Failed to import CSV file. Please try again.',
       'csvImport',
       'IMPORT_FAILED',
       true
     );
   };

   export const handleKanbanError = (error: any) => {
     if (error.message.includes('drag')) {
       return new FeatureError(
         'Failed to move task. The task may have been modified by another user.',
         'kanban',
         'DRAG_FAILED',
         true
       );
     }

     return new FeatureError(
       'Kanban board encountered an error. Please refresh the page.',
       'kanban',
       'BOARD_ERROR',
       false
     );
   };

   export const handleLeadershipError = (error: any) => {
     if (error.message.includes('permission')) {
       return new FeatureError(
         'You do not have permission to access the leadership dashboard.',
         'leadership',
         'PERMISSION_DENIED',
         false
       );
     }

     return new FeatureError(
       'Failed to load leadership data. Please try again.',
       'leadership',
       'DATA_LOAD_FAILED',
       true
     );
   };

   // Advanced error recovery component
   export const AdvancedErrorRecovery: React.FC<{
     error: FeatureError;
     onRetry: () => void;
     onFallback: () => void;
   }> = ({ error, onRetry, onFallback }) => {
     return (
       <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
         <div className="flex items-start">
           <div className="flex-shrink-0">
             <span className="text-red-400 text-xl">⚠️</span>
           </div>
           <div className="ml-3 flex-1">
             <h3 className="text-sm font-medium text-red-800">
               {error.feature.charAt(0).toUpperCase() + error.feature.slice(1)} Error
             </h3>
             <p className="text-sm text-red-700 mt-1">{error.message}</p>
             <div className="mt-3 flex space-x-2">
               {error.recoverable && (
                 <button
                   onClick={onRetry}
                   className="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700"
                 >
                   Try Again
                 </button>
               )}
               <button
                 onClick={onFallback}
                 className="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700"
               >
                 Use Alternative
               </button>
             </div>
           </div>
         </div>
       </div>
     );
   };
   ```

2. **Feature Analytics & Monitoring**
   ```typescript
   // src/utils/featureAnalytics.ts
   interface FeatureUsage {
     feature: string;
     action: string;
     timestamp: string;
     user?: string;
     department?: string;
     metadata?: Record<string, any>;
   }

   class FeatureAnalytics {
     private events: FeatureUsage[] = [];

     track(feature: string, action: string, metadata?: Record<string, any>) {
       const event: FeatureUsage = {
         feature,
         action,
         timestamp: new Date().toISOString(),
         user: this.getCurrentUser(),
         department: this.getCurrentDepartment(),
         metadata
       };

       this.events.push(event);
       this.sendToAnalytics(event);
     }

     trackCSVImport(rowCount: number, success: boolean, errorCount: number = 0) {
       this.track('csvImport', 'import', {
         rowCount,
         success,
         errorCount,
         fileSize: this.getFileSize()
       });
     }

     trackKanbanDrag(fromStatus: string, toStatus: string, taskId: string) {
       this.track('kanban', 'dragTask', {
         fromStatus,
         toStatus,
         taskId
       });
     }

     trackLeadershipView(viewType: string, dataRange: string) {
       this.track('leadership', 'viewDashboard', {
         viewType,
         dataRange,
         loadTime: performance.now()
       });
     }

     getUsageStats() {
       const now = new Date();
       const oneWeekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);

       const recentEvents = this.events.filter(
         event => new Date(event.timestamp) > oneWeekAgo
       );

       const stats = {
         totalEvents: recentEvents.length,
         byFeature: {},
         byAction: {},
         byDepartment: {}
       };

       recentEvents.forEach(event => {
         stats.byFeature[event.feature] = (stats.byFeature[event.feature] || 0) + 1;
         stats.byAction[event.action] = (stats.byAction[event.action] || 0) + 1;
         if (event.department) {
           stats.byDepartment[event.department] = (stats.byDepartment[event.department] || 0) + 1;
         }
       });

       return stats;
     }

     private getCurrentUser(): string {
       // Implementation to get current user
       return 'current-user';
     }

     private getCurrentDepartment(): string {
       // Implementation to get current department
       return window.location.pathname.split('/')[2] || 'unknown';
     }

     private getFileSize(): number {
       // Implementation to get last uploaded file size
       return 0;
     }

     private async sendToAnalytics(event: FeatureUsage) {
       try {
         // Send to analytics service (could be Google Analytics, custom endpoint, etc.)
         await fetch('/api/analytics', {
           method: 'POST',
           headers: { 'Content-Type': 'application/json' },
           body: JSON.stringify(event)
         });
       } catch (error) {
         console.warn('Failed to send analytics event:', error);
       }
     }
   }

   export const featureAnalytics = new FeatureAnalytics();

   // Hook for easy analytics tracking
   export const useFeatureAnalytics = () => {
     const trackFeatureUsage = (feature: string, action: string, metadata?: Record<string, any>) => {
       featureAnalytics.track(feature, action, metadata);
     };

     return { trackFeatureUsage, featureAnalytics };
   };
   ```

**Testing Day 18:**
```bash
# Integration testing for advanced features
npm run test:integration

# Performance testing
npm run build
npm run preview

# Test CSV import with large files (1000+ rows)
# Test Kanban drag performance with 100+ tasks
# Test Leadership dashboard with comprehensive data

# Feature analytics testing
# Verify tracking events are sent correctly
# Check analytics data collection

# Error handling testing
# Test error recovery flows
# Verify fallback mechanisms work
```

### DAY 19 (Friday): Performance Optimization & Caching
**Focus:** Optimize performance for production usage and implement advanced caching

**Morning Tasks (3-4 hours):**
1. **Advanced Caching Strategy**
   ```typescript
   // src/utils/advancedCaching.ts
   interface CacheEntry<T> {
     data: T;
     timestamp: number;
     expiry: number;
     version: string;
   }

   class AdvancedCache {
     private cache = new Map<string, CacheEntry<any>>();
     private readonly CACHE_VERSION = '1.0.0';

     set<T>(key: string, data: T, ttlMs: number = 300000): void {
       const entry: CacheEntry<T> = {
         data,
         timestamp: Date.now(),
         expiry: Date.now() + ttlMs,
         version: this.CACHE_VERSION
       };

       this.cache.set(key, entry);

       // Persist to localStorage for longer-term caching
       try {
         localStorage.setItem(`cache_${key}`, JSON.stringify(entry));
       } catch (error) {
         console.warn('Failed to persist cache to localStorage:', error);
       }
     }

     get<T>(key: string): T | null {
       // Check memory cache first
       let entry = this.cache.get(key);

       // Fall back to localStorage
       if (!entry) {
         try {
           const stored = localStorage.getItem(`cache_${key}`);
           if (stored) {
             entry = JSON.parse(stored);
             if (entry && entry.version === this.CACHE_VERSION) {
               this.cache.set(key, entry);
             }
           }
         } catch (error) {
           console.warn('Failed to load cache from localStorage:', error);
         }
       }

       if (!entry) return null;

       // Check if expired
       if (Date.now() > entry.expiry) {
         this.cache.delete(key);
         localStorage.removeItem(`cache_${key}`);
         return null;
       }

       // Check version compatibility
       if (entry.version !== this.CACHE_VERSION) {
         this.cache.delete(key);
         localStorage.removeItem(`cache_${key}`);
         return null;
       }

       return entry.data;
     }

     invalidate(pattern?: string): void {
       if (pattern) {
         // Invalidate keys matching pattern
         const regex = new RegExp(pattern);
         for (const key of this.cache.keys()) {
           if (regex.test(key)) {
             this.cache.delete(key);
             localStorage.removeItem(`cache_${key}`);
           }
         }
       } else {
         // Clear all cache
         this.cache.clear();
         Object.keys(localStorage)
           .filter(key => key.startsWith('cache_'))
           .forEach(key => localStorage.removeItem(key));
       }
     }

     getStats() {
       const stats = {
         memoryEntries: this.cache.size,
         localStorageEntries: 0,
         totalSize: 0
       };

       // Count localStorage entries
       Object.keys(localStorage).forEach(key => {
         if (key.startsWith('cache_')) {
           stats.localStorageEntries++;
           stats.totalSize += localStorage.getItem(key)?.length || 0;
         }
       });

       return stats;
     }
   }

   export const advancedCache = new AdvancedCache();

   // Caching hooks for different data types
   export const useCachedTasks = (department: string) => {
     const cacheKey = `tasks_${department}`;

     const getCachedTasks = () => advancedCache.get(cacheKey);
     const setCachedTasks = (tasks: any[]) => advancedCache.set(cacheKey, tasks, 300000); // 5 minutes
     const invalidateTaskCache = () => advancedCache.invalidate(`tasks_${department}`);

     return { getCachedTasks, setCachedTasks, invalidateTaskCache };
   };

   export const useCachedLeadershipData = () => {
     const cacheKey = 'leadership_dashboard';

     const getCachedData = () => advancedCache.get(cacheKey);
     const setCachedData = (data: any) => advancedCache.set(cacheKey, data, 600000); // 10 minutes
     const invalidateLeadershipCache = () => advancedCache.invalidate(cacheKey);

     return { getCachedData, setCachedData, invalidateLeadershipCache };
   };
   ```

2. **Component Performance Optimization**
   ```typescript
   // src/components/optimized/OptimizedTaskList.tsx
   import React, { memo, useMemo, useCallback } from 'react';
   import { FixedSizeList as List } from 'react-window';
   import { Task } from '../../types/task';

   interface OptimizedTaskListProps {
     tasks: Task[];
     onTaskEdit: (task: Task) => void;
     onTaskDelete: (taskId: string) => void;
     itemHeight?: number;
     maxHeight?: number;
   }

   // Memoized task item component
   const TaskItem = memo<{
     index: number;
     style: React.CSSProperties;
     data: {
       tasks: Task[];
       onEdit: (task: Task) => void;
       onDelete: (taskId: string) => void;
     };
   }>(({ index, style, data }) => {
     const task = data.tasks[index];

     const handleEdit = useCallback(() => {
       data.onEdit(task);
     }, [task, data.onEdit]);

     const handleDelete = useCallback(() => {
       data.onDelete(task.id);
     }, [task.id, data.onDelete]);

     return (
       <div style={style} className="px-2">
         <div className="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow">
           <div className="flex justify-between items-start mb-2">
             <h3 className="font-semibold text-gray-900 flex-1">{task.title}</h3>
             <div className="flex space-x-2">
               <button
                 onClick={handleEdit}
                 className="p-1 text-gray-400 hover:text-blue-600 transition-colors"
               >
                 ✏️
               </button>
               <button
                 onClick={handleDelete}
                 className="p-1 text-gray-400 hover:text-red-600 transition-colors"
               >
                 🗑️
               </button>
             </div>
           </div>

           {task.description && (
             <p className="text-gray-600 text-sm mb-2 line-clamp-2">{task.description}</p>
           )}

           <div className="flex justify-between items-center text-sm">
             <span className="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
               {task.status}
             </span>
             {task.assignee && (
               <span className="text-gray-500">{task.assignee}</span>
             )}
           </div>
         </div>
       </div>
     );
   });

   export const OptimizedTaskList: React.FC<OptimizedTaskListProps> = memo(({
     tasks,
     onTaskEdit,
     onTaskDelete,
     itemHeight = 120,
     maxHeight = 600
   }) => {
     const itemData = useMemo(() => ({
       tasks,
       onEdit: onTaskEdit,
       onDelete: onTaskDelete
     }), [tasks, onTaskEdit, onTaskDelete]);

     const listHeight = Math.min(tasks.length * itemHeight, maxHeight);

     if (tasks.length === 0) {
       return (
         <div className="text-center py-8 text-gray-500">
           No tasks found
         </div>
       );
     }

     return (
       <List
         height={listHeight}
         itemCount={tasks.length}
         itemSize={itemHeight}
         itemData={itemData}
         overscanCount={5}
       >
         {TaskItem}
       </List>
     );
   });

   // Optimized Kanban board with virtual scrolling
   export const OptimizedKanbanColumn = memo<{
     title: string;
     tasks: Task[];
     onTaskMove: (taskId: string, newStatus: string) => void;
   }>(({ title, tasks, onTaskMove }) => {
     const handleDrop = useCallback((taskId: string) => {
       onTaskMove(taskId, title);
     }, [title, onTaskMove]);

     return (
       <div className="bg-gray-50 rounded-lg p-4 min-h-96">
         <h3 className="font-semibold mb-4 text-gray-800">{title}</h3>
         <div className="space-y-2">
           {tasks.length > 20 ? (
             <List
               height={400}
               itemCount={tasks.length}
               itemSize={100}
               itemData={{ tasks, onDrop: handleDrop }}
               overscanCount={3}
             >
               {({ index, style, data }) => (
                 <div style={style} className="px-1">
                   <div className="bg-white rounded border p-3 mb-2 cursor-pointer hover:shadow-sm">
                     <h4 className="font-medium text-sm">{data.tasks[index].title}</h4>
                     <p className="text-xs text-gray-500 mt-1">
                       {data.tasks[index].assignee}
                     </p>
                   </div>
                 </div>
               )}
             </List>
           ) : (
             tasks.map(task => (
               <div key={task.id} className="bg-white rounded border p-3 cursor-pointer hover:shadow-sm">
                 <h4 className="font-medium text-sm">{task.title}</h4>
                 <p className="text-xs text-gray-500 mt-1">{task.assignee}</p>
               </div>
             ))
           )}
         </div>
       </div>
     );
   });
   ```

**Afternoon Tasks (3-4 hours):**
1. **API Request Optimization**
   ```typescript
   // src/utils/apiOptimization.ts
   interface RequestCache {
     [key: string]: {
       promise: Promise<any>;
       timestamp: number;
     };
   }

   class APIOptimizer {
     private requestCache: RequestCache = {};
     private requestQueue: Array<() => Promise<any>> = [];
     private isProcessingQueue = false;
     private readonly BATCH_SIZE = 5;
     private readonly BATCH_DELAY = 100; // ms

     // Deduplicate identical requests
     async deduplicateRequest<T>(key: string, requestFn: () => Promise<T>): Promise<T> {
       const now = Date.now();
       const cached = this.requestCache[key];

       // Return existing promise if request is already in flight
       if (cached && (now - cached.timestamp) < 5000) {
         return cached.promise;
       }

       // Create new request
       const promise = requestFn().finally(() => {
         // Clean up cache after request completes
         setTimeout(() => {
           delete this.requestCache[key];
         }, 1000);
       });

       this.requestCache[key] = { promise, timestamp: now };
       return promise;
     }

     // Batch multiple requests together
     async batchRequests<T>(requests: Array<() => Promise<T>>): Promise<T[]> {
       return new Promise((resolve, reject) => {
         this.requestQueue.push(...requests);

         if (!this.isProcessingQueue) {
           this.processQueue().then(resolve).catch(reject);
         }
       });
     }

     private async processQueue(): Promise<any[]> {
       this.isProcessingQueue = true;
       const results: any[] = [];

       while (this.requestQueue.length > 0) {
         const batch = this.requestQueue.splice(0, this.BATCH_SIZE);

         try {
           const batchResults = await Promise.all(
             batch.map(request => request())
           );
           results.push(...batchResults);
         } catch (error) {
           console.error('Batch request failed:', error);
           // Continue processing remaining batches
         }

         // Small delay between batches to prevent API overwhelm
         if (this.requestQueue.length > 0) {
           await new Promise(resolve => setTimeout(resolve, this.BATCH_DELAY));
         }
       }

       this.isProcessingQueue = false;
       return results;
     }

     // Retry failed requests with exponential backoff
     async retryRequest<T>(
       requestFn: () => Promise<T>,
       maxRetries: number = 3,
       baseDelay: number = 1000
     ): Promise<T> {
       let lastError: Error;

       for (let attempt = 0; attempt <= maxRetries; attempt++) {
         try {
           return await requestFn();
         } catch (error) {
           lastError = error as Error;

           if (attempt === maxRetries) {
             throw lastError;
           }

           // Exponential backoff: 1s, 2s, 4s, etc.
           const delay = baseDelay * Math.pow(2, attempt);
           await new Promise(resolve => setTimeout(resolve, delay));
         }
       }

       throw lastError!;
     }
   }

   export const apiOptimizer = new APIOptimizer();

   // Optimized API hooks
   export const useOptimizedTaskAPI = () => {
     const fetchTasks = useCallback(async (department: string) => {
       return apiOptimizer.deduplicateRequest(
         `tasks_${department}`,
         () => taskAPI.getTasks(department)
       );
     }, []);

     const batchUpdateTasks = useCallback(async (updates: Array<{ id: string; data: Partial<Task> }>) => {
       const requests = updates.map(update => () => taskAPI.updateTask(update.id, update.data));
       return apiOptimizer.batchRequests(requests);
     }, []);

     const retryTaskOperation = useCallback(async (operation: () => Promise<any>) => {
       return apiOptimizer.retryRequest(operation, 3, 1000);
     }, []);

     return { fetchTasks, batchUpdateTasks, retryTaskOperation };
   };
   ```

2. **Memory Management & Cleanup**
   ```typescript
   // src/hooks/useMemoryManagement.ts
   import { useEffect, useRef, useCallback } from 'react';

   export const useMemoryManagement = () => {
     const mountedRef = useRef(true);
     const timersRef = useRef<Set<NodeJS.Timeout>>(new Set());
     const listenersRef = useRef<Array<{ element: EventTarget; event: string; handler: EventListener }>([]);

     // Safe async operations that check if component is still mounted
     const safeAsync = useCallback(async <T>(asyncFn: () => Promise<T>): Promise<T | null> => {
       if (!mountedRef.current) return null;

       try {
         const result = await asyncFn();
         return mountedRef.current ? result : null;
       } catch (error) {
         if (mountedRef.current) {
           throw error;
         }
         return null;
       }
     }, []);

     // Safe setTimeout that auto-cleans up
     const safeSetTimeout = useCallback((callback: () => void, delay: number) => {
       const timer = setTimeout(() => {
         if (mountedRef.current) {
           callback();
         }
         timersRef.current.delete(timer);
       }, delay);

       timersRef.current.add(timer);
       return timer;
     }, []);

     // Safe event listener management
     const safeAddEventListener = useCallback((
       element: EventTarget,
       event: string,
       handler: EventListener,
       options?: boolean | AddEventListenerOptions
     ) => {
       element.addEventListener(event, handler, options);
       listenersRef.current.push({ element, event, handler });
     }, []);

     // Memory usage monitoring
     const getMemoryUsage = useCallback(() => {
       if ('memory' in performance) {
         return {
           used: (performance as any).memory.usedJSHeapSize,
           total: (performance as any).memory.totalJSHeapSize,
           limit: (performance as any).memory.jsHeapSizeLimit
         };
       }
       return null;
     }, []);

     // Cleanup function
     useEffect(() => {
       return () => {
         mountedRef.current = false;

         // Clear all timers
         timersRef.current.forEach(timer => clearTimeout(timer));
         timersRef.current.clear();

         // Remove all event listeners
         listenersRef.current.forEach(({ element, event, handler }) => {
           element.removeEventListener(event, handler);
         });
         listenersRef.current.length = 0;
       };
     }, []);

     return {
       safeAsync,
       safeSetTimeout,
       safeAddEventListener,
       getMemoryUsage,
       isMounted: () => mountedRef.current
     };
   };

   // Hook for monitoring component performance
   export const usePerformanceMonitoring = (componentName: string) => {
     const renderCount = useRef(0);
     const lastRenderTime = useRef(0);

     useEffect(() => {
       renderCount.current++;
       const now = performance.now();

       if (lastRenderTime.current > 0) {
         const timeSinceLastRender = now - lastRenderTime.current;

         // Log slow renders (>16ms for 60fps)
         if (timeSinceLastRender > 16) {
           console.warn(`Slow render in ${componentName}: ${timeSinceLastRender.toFixed(2)}ms`);
         }
       }

       lastRenderTime.current = now;
     });

     const getStats = useCallback(() => ({
       componentName,
       renderCount: renderCount.current,
       averageRenderTime: lastRenderTime.current / renderCount.current
     }), [componentName]);

     return { getStats };
   };
   ```

**Testing Day 19:**
```bash
# Performance testing
npm run build
npm run preview

# Test with large datasets
# - 1000+ tasks in Kanban view
# - Large CSV imports (500+ rows)
# - Leadership dashboard with all departments

# Memory usage testing
# - Monitor for memory leaks
# - Test component cleanup
# - Verify cache management

# API optimization testing
# - Test request deduplication
# - Verify batch processing
# - Test retry mechanisms

# Cache testing
# - Verify cache persistence
# - Test cache invalidation
# - Monitor cache performance
```

## WEEK 5: Security & Final Polish

### DAY 20 (Monday): Security Implementation & Validation
**Focus:** Implement comprehensive security measures and validate all security aspects

**Morning Tasks (3-4 hours):**
1. **Input Validation & Sanitization**
   ```typescript
   // src/utils/security/inputValidation.ts
   import DOMPurify from 'dompurify';

   export interface ValidationRule {
     required?: boolean;
     minLength?: number;
     maxLength?: number;
     pattern?: RegExp;
     customValidator?: (value: any) => string | null;
   }

   export interface ValidationSchema {
     [field: string]: ValidationRule;
   }

   export class InputValidator {
     static sanitizeHTML(input: string): string {
       return DOMPurify.sanitize(input, {
         ALLOWED_TAGS: [], // No HTML tags allowed
         ALLOWED_ATTR: []
       });
     }

     static sanitizeForSQL(input: string): string {
       // Basic SQL injection prevention
       return input.replace(/['";\\]/g, '');
     }

     static validateEmail(email: string): boolean {
       const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
       return emailRegex.test(email);
     }

     static validateTaskData(data: any): { isValid: boolean; errors: string[] } {
       const errors: string[] = [];

       // Title validation
       if (!data.title || typeof data.title !== 'string') {
         errors.push('Title is required and must be a string');
       } else if (data.title.length > 200) {
         errors.push('Title must be less than 200 characters');
       }

       // Description validation
       if (data.description && typeof data.description !== 'string') {
         errors.push('Description must be a string');
       } else if (data.description && data.description.length > 2000) {
         errors.push('Description must be less than 2000 characters');
       }

       // Status validation
       const validStatuses = ['Not Started', 'In Progress', 'Completed', 'On Hold'];
       if (data.status && !validStatuses.includes(data.status)) {
         errors.push('Invalid status value');
       }

       // Priority validation
       const validPriorities = ['Low', 'Medium', 'High', 'Critical'];
       if (data.priority && !validPriorities.includes(data.priority)) {
         errors.push('Invalid priority value');
       }

       // Due date validation
       if (data.dueDate) {
         const dueDate = new Date(data.dueDate);
         if (isNaN(dueDate.getTime())) {
           errors.push('Invalid due date format');
         }
       }

       return {
         isValid: errors.length === 0,
         errors
       };
     }

     static validateCSVData(csvData: any[]): { isValid: boolean; errors: string[]; validRows: any[] } {
       const errors: string[] = [];
       const validRows: any[] = [];

       if (!Array.isArray(csvData)) {
         return { isValid: false, errors: ['CSV data must be an array'], validRows: [] };
       }

       if (csvData.length > 1000) {
         errors.push('CSV file too large (max 1000 rows)');
       }

       csvData.forEach((row, index) => {
         const rowValidation = this.validateTaskData(row);
         if (rowValidation.isValid) {
           validRows.push({
             ...row,
             title: this.sanitizeHTML(row.title),
             description: row.description ? this.sanitizeHTML(row.description) : '',
             assignee: row.assignee ? this.sanitizeHTML(row.assignee) : ''
           });
         } else {
           errors.push(`Row ${index + 1}: ${rowValidation.errors.join(', ')}`);
         }
       });

       return {
         isValid: errors.length === 0,
         errors,
         validRows
       };
     }

     static validateFormData(data: any, schema: ValidationSchema): { isValid: boolean; errors: Record<string, string> } {
       const errors: Record<string, string> = {};

       Object.keys(schema).forEach(field => {
         const rule = schema[field];
         const value = data[field];

         // Required validation
         if (rule.required && (!value || value.toString().trim() === '')) {
           errors[field] = `${field} is required`;
           return;
         }

         // Skip further validation if field is empty and not required
         if (!value && !rule.required) return;

         // Length validation
         if (rule.minLength && value.length < rule.minLength) {
           errors[field] = `${field} must be at least ${rule.minLength} characters`;
         }

         if (rule.maxLength && value.length > rule.maxLength) {
           errors[field] = `${field} must be less than ${rule.maxLength} characters`;
         }

         // Pattern validation
         if (rule.pattern && !rule.pattern.test(value)) {
           errors[field] = `${field} format is invalid`;
         }

         // Custom validation
         if (rule.customValidator) {
           const customError = rule.customValidator(value);
           if (customError) {
             errors[field] = customError;
           }
         }
       });

       return {
         isValid: Object.keys(errors).length === 0,
         errors
       };
     }
   }

   // Secure form validation hook
   export const useSecureForm = (schema: ValidationSchema) => {
     const [errors, setErrors] = useState<Record<string, string>>({});
     const [isSubmitting, setIsSubmitting] = useState(false);

     const validateField = useCallback((field: string, value: any) => {
       if (!schema[field]) return;

       const fieldSchema = { [field]: schema[field] };
       const fieldData = { [field]: value };
       const validation = InputValidator.validateFormData(fieldData, fieldSchema);

       setErrors(prev => ({
         ...prev,
         [field]: validation.errors[field] || ''
       }));
     }, [schema]);

     const validateForm = useCallback((data: any) => {
       const validation = InputValidator.validateFormData(data, schema);
       setErrors(validation.errors);
       return validation.isValid;
     }, [schema]);

     const submitSecurely = useCallback(async (data: any, submitFn: (data: any) => Promise<any>) => {
       if (isSubmitting) return;

       setIsSubmitting(true);
       try {
         const isValid = validateForm(data);
         if (!isValid) {
           return { success: false, errors };
         }

         // Sanitize data before submission
         const sanitizedData = Object.keys(data).reduce((acc, key) => {
           if (typeof data[key] === 'string') {
             acc[key] = InputValidator.sanitizeHTML(data[key]);
           } else {
             acc[key] = data[key];
           }
           return acc;
         }, {} as any);

         const result = await submitFn(sanitizedData);
         return { success: true, data: result };
       } catch (error) {
         return { success: false, error: error.message };
       } finally {
         setIsSubmitting(false);
       }
     }, [isSubmitting, validateForm, errors]);

     return {
       errors,
       isSubmitting,
       validateField,
       validateForm,
       submitSecurely
     };
   };
   ```

2. **API Security & Rate Limiting**
   ```typescript
   // src/utils/security/apiSecurity.ts
   class APISecurityManager {
     private requestCounts = new Map<string, { count: number; resetTime: number }>();
     private readonly RATE_LIMIT = 100; // requests per minute
     private readonly RATE_WINDOW = 60000; // 1 minute in ms

     // Rate limiting
     checkRateLimit(identifier: string): boolean {
       const now = Date.now();
       const userLimits = this.requestCounts.get(identifier);

       if (!userLimits || now > userLimits.resetTime) {
         this.requestCounts.set(identifier, {
           count: 1,
           resetTime: now + this.RATE_WINDOW
         });
         return true;
       }

       if (userLimits.count >= this.RATE_LIMIT) {
         return false;
       }

       userLimits.count++;
       return true;
     }

     // Request signing for API integrity
     signRequest(data: any, secret: string): string {
       const timestamp = Date.now().toString();
       const payload = JSON.stringify(data) + timestamp;

       // Simple HMAC-like signing (in production, use proper crypto library)
       return btoa(payload + secret).slice(0, 32) + '.' + timestamp;
     }

     verifyRequestSignature(data: any, signature: string, secret: string): boolean {
       try {
         const [sig, timestamp] = signature.split('.');
         const now = Date.now();
         const reqTime = parseInt(timestamp);

         // Check if request is too old (5 minutes)
         if (now - reqTime > 300000) {
           return false;
         }

         const expectedSig = this.signRequest(data, secret).split('.')[0];
         return sig === expectedSig;
       } catch {
         return false;
       }
     }

     // Secure headers for API requests
     getSecureHeaders(): Record<string, string> {
       return {
         'Content-Type': 'application/json',
         'X-Requested-With': 'XMLHttpRequest',
         'X-Client-Version': '1.0.0',
         'X-Request-ID': crypto.randomUUID(),
         'Cache-Control': 'no-store'
       };
     }

     // Content Security Policy validation
     validateCSP(): boolean {
       const csp = document.querySelector('meta[http-equiv="Content-Security-Policy"]');
       if (!csp) {
         console.warn('Content Security Policy not found');
         return false;
       }

       const content = csp.getAttribute('content') || '';
       const requiredPolicies = [
         "default-src 'self'",
         "script-src 'self'",
         "style-src 'self' 'unsafe-inline'",
         "img-src 'self' data:",
         "connect-src 'self'"
       ];

       return requiredPolicies.every(policy => content.includes(policy.split(' ')[0]));
     }
   }

   export const apiSecurity = new APISecurityManager();

   // Secure API hook
   export const useSecureAPI = () => {
     const makeSecureRequest = useCallback(async (
       url: string,
       options: RequestInit = {},
       requireAuth: boolean = true
     ) => {
       const userId = getUserId(); // Implement user identification

       // Check rate limit
       if (!apiSecurity.checkRateLimit(userId)) {
         throw new Error('Rate limit exceeded. Please try again later.');
       }

       // Add security headers
       const secureHeaders = {
         ...apiSecurity.getSecureHeaders(),
         ...options.headers
       };

       // Sign request if data is present
       if (options.body && requireAuth) {
         const data = JSON.parse(options.body as string);
         const signature = apiSecurity.signRequest(data, getClientSecret());
         secureHeaders['X-Signature'] = signature;
       }

       const secureOptions: RequestInit = {
         ...options,
         headers: secureHeaders,
         credentials: 'same-origin',
         mode: 'cors'
       };

       const response = await fetch(url, secureOptions);

       // Verify response security headers
       if (!response.headers.get('X-Content-Type-Options')) {
         console.warn('Missing X-Content-Type-Options header');
       }

       if (!response.ok) {
         const errorData = await response.json().catch(() => ({}));
         throw new Error(errorData.message || 'API request failed');
       }

       return response.json();
     }, []);

     return { makeSecureRequest };
   };

   // Helper functions (implement these based on your auth system)
   function getUserId(): string {
     return localStorage.getItem('userId') || 'anonymous';
   }

   function getClientSecret(): string {
     return process.env.REACT_APP_CLIENT_SECRET || 'default-secret';
   }
   ```

**Afternoon Tasks (3-4 hours):**
1. **Data Encryption & Storage Security**
   ```typescript
   // src/utils/security/dataEncryption.ts
   class DataEncryption {
     private readonly algorithm = 'AES-GCM';
     private readonly keyLength = 256;

     // Generate encryption key from password
     async deriveKey(password: string, salt: Uint8Array): Promise<CryptoKey> {
       const encoder = new TextEncoder();
       const keyMaterial = await crypto.subtle.importKey(
         'raw',
         encoder.encode(password),
         'PBKDF2',
         false,
         ['deriveKey']
       );

       return crypto.subtle.deriveKey(
         {
           name: 'PBKDF2',
           salt: salt,
           iterations: 100000,
           hash: 'SHA-256'
         },
         keyMaterial,
         { name: this.algorithm, length: this.keyLength },
         false,
         ['encrypt', 'decrypt']
       );
     }

     // Encrypt sensitive data
     async encryptData(data: string, password: string): Promise<string> {
       try {
         const encoder = new TextEncoder();
         const salt = crypto.getRandomValues(new Uint8Array(16));
         const iv = crypto.getRandomValues(new Uint8Array(12));

         const key = await this.deriveKey(password, salt);
         const encodedData = encoder.encode(data);

         const encryptedData = await crypto.subtle.encrypt(
           { name: this.algorithm, iv: iv },
           key,
           encodedData
         );

         // Combine salt, iv, and encrypted data
         const combined = new Uint8Array(salt.length + iv.length + encryptedData.byteLength);
         combined.set(salt, 0);
         combined.set(iv, salt.length);
         combined.set(new Uint8Array(encryptedData), salt.length + iv.length);

         return btoa(String.fromCharCode(...combined));
       } catch (error) {
         console.error('Encryption failed:', error);
         throw new Error('Failed to encrypt data');
       }
     }

     // Decrypt sensitive data
     async decryptData(encryptedData: string, password: string): Promise<string> {
       try {
         const combined = new Uint8Array(
           atob(encryptedData).split('').map(char => char.charCodeAt(0))
         );

         const salt = combined.slice(0, 16);
         const iv = combined.slice(16, 28);
         const data = combined.slice(28);

         const key = await this.deriveKey(password, salt);

         const decryptedData = await crypto.subtle.decrypt(
           { name: this.algorithm, iv: iv },
           key,
           data
         );

         return new TextDecoder().decode(decryptedData);
       } catch (error) {
         console.error('Decryption failed:', error);
         throw new Error('Failed to decrypt data');
       }
     }
   }

   export const dataEncryption = new DataEncryption();

   // Secure storage utilities
   export class SecureStorage {
     private static readonly ENCRYPTION_KEY = 'taskmaster-encryption-key';

     static async setSecure(key: string, value: any): Promise<void> {
       try {
         const serialized = JSON.stringify(value);
         const encrypted = await dataEncryption.encryptData(serialized, this.ENCRYPTION_KEY);
         localStorage.setItem(`secure_${key}`, encrypted);
       } catch (error) {
         console.error('Secure storage failed:', error);
         throw new Error('Failed to store data securely');
       }
     }

     static async getSecure(key: string): Promise<any> {
       try {
         const encrypted = localStorage.getItem(`secure_${key}`);
         if (!encrypted) return null;

         const decrypted = await dataEncryption.decryptData(encrypted, this.ENCRYPTION_KEY);
         return JSON.parse(decrypted);
       } catch (error) {
         console.error('Secure retrieval failed:', error);
         // Clean up corrupted data
         localStorage.removeItem(`secure_${key}`);
         return null;
       }
     }

     static removeSecure(key: string): void {
       localStorage.removeItem(`secure_${key}`);
     }

     static clearAllSecure(): void {
       Object.keys(localStorage)
         .filter(key => key.startsWith('secure_'))
         .forEach(key => localStorage.removeItem(key));
     }
   }

   // Secure task data handling
   export const useSecureTaskData = () => {
     const storeTaskSecurely = useCallback(async (task: any) => {
       // Remove sensitive fields before general storage
       const { sensitiveNotes, ...publicTask } = task;

       // Store public data normally
       await taskAPI.updateTask(task.id, publicTask);

       // Store sensitive data encrypted locally
       if (sensitiveNotes) {
         await SecureStorage.setSecure(`task_notes_${task.id}`, sensitiveNotes);
       }
     }, []);

     const getSecureTaskData = useCallback(async (taskId: string) => {
       // Get public task data
       const publicTask = await taskAPI.getTask(taskId);

       // Get encrypted sensitive data
       const sensitiveNotes = await SecureStorage.getSecure(`task_notes_${taskId}`);

       return {
         ...publicTask,
         sensitiveNotes: sensitiveNotes || ''
       };
     }, []);

     return { storeTaskSecurely, getSecureTaskData };
   };
   ```

**Testing Day 20:**
```bash
# Security testing
npm run test:security

# Input validation testing
# - Test XSS prevention
# - Test SQL injection protection
# - Test malformed data handling

# API security testing
# - Test rate limiting
# - Test request signing
# - Test invalid requests

# Data encryption testing
# - Test encrypt/decrypt cycle
# - Test key derivation
# - Test secure storage

# CSP and security headers testing
# - Verify Content Security Policy
# - Test HTTPS enforcement
# - Check security headers
```

### DAY 21 (Friday): Week 4-5 Comprehensive Testing & Validation
**Focus:** Complete testing of all advanced features and ensure production readiness

**Morning Tasks (3-4 hours):**
1. **End-to-End Testing Suite**
   ```typescript
   // src/tests/e2e/advancedFeatures.e2e.test.ts
   import { test, expect } from '@playwright/test';

   test.describe('Advanced Features End-to-End Tests', () => {
     test.beforeEach(async ({ page }) => {
       await page.goto('/department/sales');
       await page.waitForLoadState('networkidle');
     });

     test('Complete CSV import workflow', async ({ page }) => {
       // Create test CSV content
       const csvContent = `title,description,assignee,priority,status
   "Test Task 1","Description 1","John Doe","High","Not Started"
   "Test Task 2","Description 2","Jane Smith","Medium","In Progress"
   "Test Task 3","Description 3","Bob Johnson","Low","Completed"`;

       // Open CSV import modal
       await page.click('button:text("Import CSV")');
       await expect(page.locator('.csv-import-modal')).toBeVisible();

       // Upload CSV file
       await page.setInputFiles('input[type="file"]', {
         name: 'test-tasks.csv',
         mimeType: 'text/csv',
         buffer: Buffer.from(csvContent)
       });

       // Verify file upload and preview
       await expect(page.locator('.csv-preview')).toBeVisible();
       await expect(page.locator('.csv-row')).toHaveCount(3);

       // Proceed with import
       await page.click('button:text("Import Tasks")');

       // Wait for import completion
       await expect(page.locator('.import-success')).toBeVisible();
       await expect(page.locator('.import-results')).toContainText('3 tasks imported');

       // Close modal and verify tasks appear in list
       await page.click('button:text("Close")');
       await expect(page.locator('.task-card:has-text("Test Task 1")')).toBeVisible();
       await expect(page.locator('.task-card:has-text("Test Task 2")')).toBeVisible();
       await expect(page.locator('.task-card:has-text("Test Task 3")')).toBeVisible();
     });

     test('Kanban drag and drop functionality', async ({ page }) => {
       // Switch to Kanban view
       await page.click('button:text("Kanban View")');
       await expect(page.locator('.kanban-board')).toBeVisible();

       // Find a task in "To Do" column
       const todoColumn = page.locator('.kanban-column:has-text("To Do")');
       const task = todoColumn.locator('.task-card').first();
       const taskTitle = await task.locator('h4').textContent();

       // Drag task to "In Progress" column
       const inProgressColumn = page.locator('.kanban-column:has-text("In Progress")');
       await task.dragTo(inProgressColumn);

       // Verify task moved to correct column
       const movedTask = inProgressColumn.locator(`.task-card:has-text("${taskTitle}")`);
       await expect(movedTask).toBeVisible();

       // Verify task is no longer in "To Do" column
       const remainingInTodo = todoColumn.locator(`.task-card:has-text("${taskTitle}")`);
       await expect(remainingInTodo).toHaveCount(0);
     });

     test('Leadership dashboard access and data', async ({ page }) => {
       // Navigate to leadership dashboard
       await page.goto('/leadership');

       // Verify authentication check (if applicable)
       const isAuthRequired = await page.locator('.auth-required').isVisible();
       if (isAuthRequired) {
         await page.fill('input[type="email"]', 'leader@company.com');
         await page.click('button:text("Access Dashboard")');
       }

       // Verify dashboard loads
       await expect(page.locator('.leadership-dashboard')).toBeVisible();

       // Check key metrics are displayed
       await expect(page.locator('.metric-total-tasks')).toBeVisible();
       await expect(page.locator('.metric-completion-rate')).toBeVisible();
       await expect(page.locator('.department-breakdown')).toBeVisible();

       // Test department filtering
       await page.selectOption('select[name="department-filter"]', 'sales');
       await expect(page.locator('.filtered-stats')).toBeVisible();

       // Test date range filtering
       await page.click('button:text("Last 30 Days")');
       await expect(page.locator('.date-filtered-stats')).toBeVisible();
     });

     test('Cross-feature integration workflow', async ({ page }) => {
       // Import tasks via CSV
       await page.click('button:text("Import CSV")');
       const csvContent = 'title,description,assignee,priority,status\n"Integration Test","Test description","Test User","High","To Do"';
       await page.setInputFiles('input[type="file"]', {
         name: 'integration-test.csv',
         mimeType: 'text/csv',
         buffer: Buffer.from(csvContent)
       });
       await page.click('button:text("Import Tasks")');
       await page.click('button:text("Close")');

       // Switch to Kanban view
       await page.click('button:text("Kanban View")');

       // Move imported task through workflow
       const task = page.locator('.task-card:has-text("Integration Test")');
       await task.dragTo(page.locator('.kanban-column:has-text("In Progress")'));
       await task.dragTo(page.locator('.kanban-column:has-text("Done")'));

       // Verify in leadership dashboard
       await page.goto('/leadership');
       await expect(page.locator('.recent-activity:has-text("Integration Test")')).toBeVisible();
     });

     test('Performance under load', async ({ page }) => {
       // Generate large dataset
       const largeCsvContent = 'title,description,assignee,priority,status\n' +
         Array.from({ length: 100 }, (_, i) =>
           `"Task ${i}","Description ${i}","User ${i % 5}","Medium","To Do"`
         ).join('\n');

       // Import large CSV
       await page.click('button:text("Import CSV")');
       await page.setInputFiles('input[type="file"]', {
         name: 'large-dataset.csv',
         mimeType: 'text/csv',
         buffer: Buffer.from(largeCsvContent)
       });

       // Measure import time
       const startTime = Date.now();
       await page.click('button:text("Import Tasks")');
       await expect(page.locator('.import-success')).toBeVisible();
       const importTime = Date.now() - startTime;

       expect(importTime).toBeLessThan(10000); // Should complete within 10 seconds

       // Test Kanban performance with large dataset
       await page.click('button:text("Close")');
       await page.click('button:text("Kanban View")');

       // Verify board loads within reasonable time
       const boardLoadStart = Date.now();
       await expect(page.locator('.kanban-board')).toBeVisible();
       const boardLoadTime = Date.now() - boardLoadStart;

       expect(boardLoadTime).toBeLessThan(3000); // Should load within 3 seconds
     });
   });
   ```

2. **Security Penetration Testing**
   ```typescript
   // src/tests/security/penetrationTests.test.ts
   import { test, expect } from '@playwright/test';

   test.describe('Security Penetration Tests', () => {
     test('XSS prevention in task forms', async ({ page }) => {
       await page.goto('/department/sales');

       // Test script injection in task title
       await page.click('button:text("Create New Task")');
       await page.fill('input[name="title"]', '<script>alert("xss")</script>');
       await page.fill('textarea[name="description"]', '<img src="x" onerror="alert(\'xss\')"');
       await page.click('button:text("Create Task")');

       // Verify scripts are not executed
       const alerts = [];
       page.on('dialog', dialog => {
         alerts.push(dialog.message());
         dialog.dismiss();
       });

       await page.waitForTimeout(1000);
       expect(alerts).toHaveLength(0);

       // Verify content is sanitized
       const taskCard = page.locator('.task-card').last();
       const title = await taskCard.locator('h3').textContent();
       expect(title).not.toContain('<script>');
       expect(title).not.toContain('alert');
     });

     test('CSV injection prevention', async ({ page }) => {
       await page.goto('/department/sales');

       // Create malicious CSV content
       const maliciousCsv = `title,description,assignee,priority,status
   "=CMD|' /C calc'!A0","Normal description","User","High","To Do"
   "@SUM(1+1)*cmd|' /C calc'!A0","Another description","User","Medium","In Progress"`;

       await page.click('button:text("Import CSV")');
       await page.setInputFiles('input[type="file"]', {
         name: 'malicious.csv',
         mimeType: 'text/csv',
         buffer: Buffer.from(maliciousCsv)
       });

       // Verify malicious content is sanitized
       await expect(page.locator('.csv-preview')).toBeVisible();
       const previewContent = await page.locator('.csv-preview').textContent();
       expect(previewContent).not.toContain('=CMD');
       expect(previewContent).not.toContain('@SUM');
     });

     test('API rate limiting', async ({ page }) => {
       // Test rate limiting by making rapid API calls
       const responses = [];

       for (let i = 0; i < 150; i++) {
         try {
           const response = await page.request.get('/api/tasks?department=sales');
           responses.push(response.status());
         } catch (error) {
           responses.push(429); // Too Many Requests
         }
       }

       // Verify rate limiting kicks in
       const rateLimitedResponses = responses.filter(status => status === 429);
       expect(rateLimitedResponses.length).toBeGreaterThan(0);
     });

     test('SQL injection prevention', async ({ page }) => {
       await page.goto('/department/sales');

       // Test SQL injection in search
       const searchInput = page.locator('input[placeholder*="search"], input[name="search"]');
       if (await searchInput.isVisible()) {
         await searchInput.fill("'; DROP TABLE tasks; --");
         await page.keyboard.press('Enter');

         // Verify application still functions
         await expect(page.locator('.task-card')).toBeVisible();

         // Verify no error indicating SQL injection succeeded
         const errorMessage = page.locator('.error, .alert-error');
         if (await errorMessage.isVisible()) {
           const errorText = await errorMessage.textContent();
           expect(errorText).not.toContain('SQL');
           expect(errorText).not.toContain('syntax');
         }
       }
     });

     test('Authentication bypass attempts', async ({ page }) => {
       // Test direct navigation to protected routes
       await page.goto('/leadership');

       // Should be redirected or show auth required
       const isProtected = await page.locator('.auth-required, .login-form').isVisible();
       expect(isProtected).toBe(true);

       // Test token manipulation
       await page.evaluate(() => {
         localStorage.setItem('auth-token', 'fake-token');
         localStorage.setItem('user-role', 'admin');
       });

       await page.reload();

       // Should still require proper authentication
       const stillProtected = await page.locator('.auth-required, .login-form').isVisible();
       expect(stillProtected).toBe(true);
     });
   });
   ```

**Afternoon Tasks (3-4 hours):**
1. **Performance Benchmarking**
   ```bash
   #!/bin/bash
   # Performance benchmarking script

   echo "🚀 Starting TaskMaster Performance Benchmarks"

   # Build optimized version
   npm run build

   # Start preview server
   npm run preview &
   SERVER_PID=$!

   # Wait for server to start
   sleep 5

   echo "📊 Running Lighthouse performance tests..."

   # Desktop performance test
   npx lighthouse http://localhost:4173 \
     --only-categories=performance \
     --form-factor=desktop \
     --output=json \
     --output-path=./performance-reports/desktop-performance.json

   # Mobile performance test
   npx lighthouse http://localhost:4173 \
     --only-categories=performance \
     --form-factor=mobile \
     --output=json \
     --output-path=./performance-reports/mobile-performance.json

   echo "🧪 Running load tests..."

   # Load testing with artillery
   npx artillery quick --count 50 --num 100 http://localhost:4173/department/sales

   echo "📈 Running bundle analysis..."

   # Bundle size analysis
   npx bundlesize

   # Clean up
   kill $SERVER_PID

   echo "✅ Performance benchmarking complete!"
   echo "📋 Results:"
   echo "- Desktop Performance: ./performance-reports/desktop-performance.json"
   echo "- Mobile Performance: ./performance-reports/mobile-performance.json"
   echo "- Load Test Results: See output above"
   ```

2. **Final Validation Checklist**
   ```typescript
   // src/tests/validation/finalValidation.test.ts
   import { test, expect } from '@playwright/test';

   test.describe('Final Production Validation', () => {
     const validationChecklist = [
       'CSV import handles large files (1000+ rows)',
       'Kanban board supports 500+ tasks without performance issues',
       'Leadership dashboard loads within 3 seconds',
       'Mobile experience is fully functional',
       'All security measures are active',
       'Error handling provides clear user feedback',
       'Data persistence works correctly',
       'Cross-browser compatibility verified'
     ];

     test('CSV import scalability', async ({ page }) => {
       await page.goto('/department/sales');

       // Generate large CSV (1000 rows)
       const largeCsv = 'title,description,assignee,priority,status\n' +
         Array.from({ length: 1000 }, (_, i) =>
           `"Scale Test ${i}","Description ${i}","User${i % 10}","${['Low','Medium','High'][i % 3]}","To Do"`
         ).join('\n');

       await page.click('button:text("Import CSV")');

       const startTime = Date.now();
       await page.setInputFiles('input[type="file"]', {
         name: 'scale-test.csv',
         mimeType: 'text/csv',
         buffer: Buffer.from(largeCsv)
       });

       await page.click('button:text("Import Tasks")');
       await expect(page.locator('.import-success')).toBeVisible({ timeout: 30000 });

       const duration = Date.now() - startTime;
       console.log(`✅ CSV import (1000 rows): ${duration}ms`);
       expect(duration).toBeLessThan(30000); // 30 second limit
     });

     test('Kanban performance with large dataset', async ({ page }) => {
       await page.goto('/department/sales');
       await page.click('button:text("Kanban View")');

       const startTime = Date.now();
       await expect(page.locator('.kanban-board')).toBeVisible();

       // Test drag performance
       const dragStartTime = Date.now();
       const firstTask = page.locator('.task-card').first();
       const inProgressColumn = page.locator('.kanban-column:has-text("In Progress")');

       await firstTask.dragTo(inProgressColumn);
       const dragDuration = Date.now() - dragStartTime;

       console.log(`✅ Kanban drag operation: ${dragDuration}ms`);
       expect(dragDuration).toBeLessThan(1000); // 1 second limit
     });

     test('Leadership dashboard performance', async ({ page }) => {
       const startTime = Date.now();
       await page.goto('/leadership');

       await expect(page.locator('.leadership-dashboard')).toBeVisible();
       const loadTime = Date.now() - startTime;

       console.log(`✅ Leadership dashboard load: ${loadTime}ms`);
       expect(loadTime).toBeLessThan(3000); // 3 second limit
     });

     test('Cross-browser compatibility', async ({ page, browserName }) => {
       await page.goto('/department/sales');

       // Test core functionality across browsers
       await expect(page.locator('.task-grid, .task-list')).toBeVisible();

       // Test CSV import
       await page.click('button:text("Import CSV")');
       await expect(page.locator('.csv-import-modal')).toBeVisible();
       await page.click('button:text("Cancel")');

       // Test Kanban view
       await page.click('button:text("Kanban View")');
       await expect(page.locator('.kanban-board')).toBeVisible();

       console.log(`✅ ${browserName} compatibility verified`);
     });

     test('Mobile responsiveness validation', async ({ page }) => {
       // Set mobile viewport
       await page.setViewportSize({ width: 375, height: 667 });
       await page.goto('/department/sales');

       // Verify mobile navigation
       await expect(page.locator('.mobile-nav')).toBeVisible();

       // Test mobile task creation
       await page.click('button:text("Create New Task")');
       await expect(page.locator('.task-form-modal')).toBeVisible();

       // Verify form is mobile-optimized
       const modal = page.locator('.task-form-modal');
       const modalBox = await modal.boundingBox();
       expect(modalBox?.width).toBeLessThanOrEqual(375);

       console.log(`✅ Mobile responsiveness verified`);
     });
   });

   // Production readiness report
   test('Generate production readiness report', async ({ page }) => {
     const report = {
       timestamp: new Date().toISOString(),
       features: {
         csvImport: '✅ Ready',
         kanbanBoard: '✅ Ready',
         leadershipDashboard: '✅ Ready',
         mobileOptimization: '✅ Ready',
         security: '✅ Ready',
         performance: '✅ Ready'
       },
       metrics: {
         pageLoadTime: '< 3s',
         csvImportCapacity: '1000+ rows',
         kanbanTaskLimit: '500+ tasks',
         mobileScore: '90+',
         securityScore: '95+',
         performanceScore: '90+'
       },
       status: 'READY FOR PRODUCTION'
     };

     console.log('\n🎯 PRODUCTION READINESS REPORT');
     console.log('================================');
     console.log(JSON.stringify(report, null, 2));
   });
   ```

**Testing Day 21:**
```bash
# Comprehensive final testing
npm run test:e2e
npm run test:security
npm run test:performance

# Manual validation checklist
echo "Final Validation Checklist:"
echo "✅ CSV import works with large files"
echo "✅ Kanban board handles high task volumes"
echo "✅ Leadership dashboard loads quickly"
echo "✅ Mobile experience is optimized"
echo "✅ Security measures are active"
echo "✅ Error handling is user-friendly"
echo "✅ Cross-browser compatibility verified"
echo "✅ Performance meets targets"

# Performance benchmarking
./scripts/performance-benchmark.sh

# Generate final report
npm run test:validation
```

**End of Week 4-5 Validation:**
```bash
echo "🎊 WEEK 4-5 COMPLETION SUMMARY"
echo "================================"
echo "✅ DAY 15: CSV Import System - Complete"
echo "✅ DAY 16: Kanban Board View - Complete"
echo "✅ DAY 17: Leadership Portal - Complete"
echo "✅ DAY 18: Advanced Feature Integration - Complete"
echo "✅ DAY 19: Performance Optimization - Complete"
echo "✅ DAY 20: Security Implementation - Complete"
echo "✅ DAY 21: Comprehensive Testing - Complete"
echo ""
echo "🚀 READY FOR WEEK 6-7: PRODUCTION DEPLOYMENT"
```

---

# WEEK 6-7: Production Deployment & Final Migration
*Goal: Deploy to production, user training, complete migration*

## Week 6 Daily Breakdown

### DAY 22 (Monday): Production Deployment Setup

**Morning: Netlify Production Configuration (3-4 hours)**

**1. Optimize build for production:**

```typescript
// Update vite.config.ts for production optimization
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    target: 'es2015',
    outDir: 'dist',
    sourcemap: false,
    minify: 'terser',
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['react', 'react-dom'],
          router: ['react-router-dom'],
          ui: ['zustand', 'axios']
        }
      }
    }
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
  },
})
```

**2. Environment configuration:**

```typescript
// src/config/environment.ts - Production ready
const getConfig = () => {
  const env = import.meta.env;

  return {
    appsScriptUrl: env.VITE_APPS_SCRIPT_URL || 'https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec',
    isDevelopment: env.DEV,
    isProduction: env.PROD,
    apiTimeout: parseInt(env.VITE_API_TIMEOUT || '10000'),
    enableAnalytics: env.VITE_ENABLE_ANALYTICS === 'true',
    version: env.VITE_APP_VERSION || '1.0.0'
  };
};

export const config = getConfig();

// Validate required environment variables
if (config.isProduction && !config.appsScriptUrl.includes('YOUR_SCRIPT_ID')) {
  console.warn('⚠️ Production deployment requires proper VITE_APPS_SCRIPT_URL');
}
```

**3. Create production build script:**

```json
// Update package.json
{
  "scripts": {
    "dev": "vite",
    "build": "tsc && vite build",
    "build:prod": "NODE_ENV=production tsc && vite build",
    "preview": "vite preview",
    "test": "vitest",
    "test:ci": "vitest run --coverage",
    "lint": "eslint src --ext ts,tsx --report-unused-disable-directives --max-warnings 0",
    "lint:fix": "eslint src --ext ts,tsx --fix",
    "typecheck": "tsc --noEmit",
    "analyze": "npm run build && npx vite-bundle-analyzer dist"
  }
}
```

**4. Netlify configuration:**

```toml
# netlify.toml - Production configuration
[build]
  command = "npm run build:prod"
  publish = "dist"

[build.environment]
  NODE_VERSION = "18"
  NPM_VERSION = "9"

# Production environment variables
[context.production.environment]
  VITE_APPS_SCRIPT_URL = "https://script.google.com/macros/s/YOUR_ACTUAL_SCRIPT_ID/exec"
  VITE_API_TIMEOUT = "15000"
  VITE_ENABLE_ANALYTICS = "true"
  VITE_APP_VERSION = "1.0.0"

# Staging environment
[context.deploy-preview.environment]
  VITE_APPS_SCRIPT_URL = "https://script.google.com/macros/s/YOUR_STAGING_SCRIPT_ID/exec"
  VITE_API_TIMEOUT = "10000"
  VITE_ENABLE_ANALYTICS = "false"

# Redirects for SPA
[[redirects]]
  from = "/*"
  to = "/index.html"
  status = 200

# Security headers
[[headers]]
  for = "/*"
  [headers.values]
    X-Frame-Options = "DENY"
    X-XSS-Protection = "1; mode=block"
    X-Content-Type-Options = "nosniff"
    Referrer-Policy = "strict-origin-when-cross-origin"
    Content-Security-Policy = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' https://script.google.com https://ui-avatars.com;"

# Cache static assets
[[headers]]
  for = "/static/*"
  [headers.values]
    Cache-Control = "public, max-age=31536000, immutable"
```

**Afternoon: Performance Optimization (3-4 hours)**

**5. Add performance monitoring:**

```typescript
// src/utils/performance.ts
export const performanceMonitor = {
  // Track page load times
  trackPageLoad: (pageName: string) => {
    if (typeof window !== 'undefined') {
      window.addEventListener('load', () => {
        const loadTime = performance.now();
        console.log(`📊 ${pageName} loaded in ${Math.round(loadTime)}ms`);

        // Send to analytics if enabled
        if (config.enableAnalytics) {
          // Add your analytics tracking here
        }
      });
    }
  },

  // Track API call performance
  trackAPICall: (endpoint: string, duration: number) => {
    console.log(`🚀 API ${endpoint} completed in ${Math.round(duration)}ms`);

    if (duration > 3000) {
      console.warn(`⚠️ Slow API call: ${endpoint} took ${Math.round(duration)}ms`);
    }
  },

  // Track user interactions
  trackUserAction: (action: string, details?: any) => {
    if (config.enableAnalytics) {
      console.log(`👤 User action: ${action}`, details);
    }
  }
};

// Enhanced API client with performance tracking
export const createAPIClient = () => {
  const api = axios.create({
    baseURL: config.appsScriptUrl,
    timeout: config.apiTimeout,
  });

  api.interceptors.request.use((config) => {
    config.metadata = { startTime: performance.now() };
    return config;
  });

  api.interceptors.response.use(
    (response) => {
      const duration = performance.now() - response.config.metadata.startTime;
      performanceMonitor.trackAPICall(response.config.url || 'unknown', duration);
      return response;
    },
    (error) => {
      const duration = performance.now() - error.config?.metadata?.startTime;
      performanceMonitor.trackAPICall(error.config?.url || 'unknown', duration);
      return Promise.reject(error);
    }
  );

  return api;
};
```

**6. Add lazy loading and code splitting:**

```tsx
// src/App.tsx - with lazy loading
import React, { Suspense } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { ErrorBoundary } from './components/shared/ErrorBoundary';
import { LoadingSpinner } from './components/shared/LoadingSpinner';

// Lazy load pages
const HomePage = React.lazy(() => import('./pages/HomePage').then(module => ({ default: module.HomePage })));
const DepartmentPage = React.lazy(() => import('./pages/DepartmentPage').then(module => ({ default: module.DepartmentPage })));
const LeadershipDashboard = React.lazy(() => import('./pages/LeadershipDashboard').then(module => ({ default: module.LeadershipDashboard })));

const LoadingFallback = () => (
  <div className="min-h-screen bg-gray-50 flex items-center justify-center">
    <div className="text-center">
      <LoadingSpinner size="lg" className="mx-auto mb-4" />
      <p className="text-gray-600">Loading...</p>
    </div>
  </div>
);

function App() {
  return (
    <ErrorBoundary>
      <Router>
        <div className="min-h-screen bg-gray-50">
          <Suspense fallback={<LoadingFallback />}>
            <Routes>
              <Route path="/" element={<HomePage />} />
              <Route path="/department/:departmentId" element={<DepartmentPage />} />
              <Route path="/leadership" element={<LeadershipDashboard />} />
              <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </Suspense>
        </div>
      </Router>
    </ErrorBoundary>
  );
}

export default App;
```

**7. Create deployment checklist:**

```markdown
# Production Deployment Checklist

## Pre-Deployment
- [ ] All tests passing (`npm run test:ci`)
- [ ] TypeScript compilation clean (`npm run typecheck`)
- [ ] Linting clean (`npm run lint`)
- [ ] Build successful (`npm run build:prod`)
- [ ] Environment variables configured in Netlify
- [ ] Apps Script deployed to production

## Performance Checks
- [ ] Bundle size < 500KB (check with `npm run analyze`)
- [ ] Page load time < 3 seconds
- [ ] API response time < 2 seconds
- [ ] Lighthouse score > 90

## Security Checks
- [ ] No hardcoded secrets in code
- [ ] HTTPS only
- [ ] Security headers configured
- [ ] CORS properly configured

## Functionality Tests
- [ ] All departments accessible
- [ ] Task CRUD operations working
- [ ] CSV import functional
- [ ] Kanban board operational
- [ ] Leadership portal accessible
- [ ] Avatar assignment working
- [ ] Sales metrics displaying

## Post-Deployment
- [ ] Smoke tests on production URL
- [ ] Performance monitoring enabled
- [ ] Error tracking configured
- [ ] User feedback collection ready
```

**Testing Day 22:**
```bash
# Run full test suite
npm run test:ci
npm run typecheck
npm run lint

# Build and analyze
npm run build:prod
npm run analyze

# Deploy to Netlify staging
netlify deploy --dir=dist

# Test staging deployment
```

---

### DAY 23 (Tuesday): User Training & Documentation

**Morning: Create User Training Materials (3-4 hours)**

**1. Create user guide:**

```markdown
# TaskMaster User Guide
*Version 1.0 - New React Interface*

## 🎯 Quick Start Guide

### Accessing Your Department
1. Go to the TaskMaster homepage
2. Click on your department tile
3. Start creating and managing tasks!

### Creating a New Task
1. Click the "**+ Create New Task**" button
2. Fill in the task details:
   - **Title** (required)
   - **Description**
   - **Assignee**
   - **Priority** (High/Medium/Low)
   - **Status** (Not Started/In Progress/Completed)
   - **Due Date** (optional)
3. Click "**Create Task**"

### Editing a Task
1. Find your task in the grid view
2. Click the "**Edit**" button on the task card
3. Make your changes
4. Click "**Update Task**"

### Assigning Tasks to Team Members
1. On any task card, click "**Change**" next to the assignee
2. Select from the avatar dropdown
3. The task is automatically updated

## 📊 Views and Features

### Grid View vs Kanban View
- **Grid View**: See all tasks as cards in a grid layout
- **Kanban View**: Drag and drop tasks between status columns
- Switch between views using the toggle buttons

### CSV Import (Bulk Task Creation)
1. Click "**📁 Import CSV**"
2. Download the template if needed
3. Upload your completed CSV file
4. Review and confirm the import

### Sales Metrics (Sales Department Only)
- View HubSpot call metrics at the top of the sales department page
- See individual rep performance and total calls

## 🎨 What's New in the React Interface

### ✅ Improvements
- **10x Faster** loading and interactions
- **Mobile Responsive** - works great on phones and tablets
- **Better Search** and filtering options
- **Real-time Updates** across team members
- **Improved Error Handling** with helpful messages

### 📱 Mobile Usage
- All features work on mobile devices
- Touch-friendly buttons and interactions
- Optimized layouts for small screens

## 🆘 Need Help?

### Common Issues
**Q: Tasks not loading?**
A: Check your internet connection and refresh the page

**Q: Can't create tasks?**
A: Make sure you have the required permissions for your department

**Q: CSV import failing?**
A: Download the template and ensure your data matches the format

### Getting Support
- Contact your department lead for task management questions
- Technical issues can be reported to the IT team
- Feedback and feature requests are welcome!

---

*This new interface maintains all your existing data while providing a modern, faster experience. Your tasks, assignments, and department information remain exactly the same.*
```

**2. Create department-specific training:**

```typescript
// src/components/training/TrainingModal.tsx
import React, { useState } from 'react';
import { Department } from '../../types';

interface TrainingModalProps {
  isOpen: boolean;
  onClose: () => void;
  department: Department;
}

export const TrainingModal: React.FC<TrainingModalProps> = ({
  isOpen,
  onClose,
  department
}) => {
  const [currentStep, setCurrentStep] = useState(0);

  const trainingSteps = [
    {
      title: `Welcome to ${department.name} Tasks`,
      content: (
        <div className="text-center">
          <div className="text-6xl mb-4">{department.icon}</div>
          <h3 className="text-lg font-semibold mb-2">You're in the {department.name} department!</h3>
          <p className="text-gray-600">
            This is your dedicated space for managing {department.name.toLowerCase()} tasks.
            Let's take a quick tour of the new features.
          </p>
        </div>
      )
    },
    {
      title: 'Creating Tasks',
      content: (
        <div>
          <h3 className="text-lg font-semibold mb-3">Creating a New Task</h3>
          <div className="space-y-3">
            <div className="flex items-start space-x-3">
              <div className="bg-blue-100 text-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-sm font-medium">1</div>
              <div>Click the "**+ Create New Task**" button</div>
            </div>
            <div className="flex items-start space-x-3">
              <div className="bg-blue-100 text-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-sm font-medium">2</div>
              <div>Fill in the task details (title is required)</div>
            </div>
            <div className="flex items-start space-x-3">
              <div className="bg-blue-100 text-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-sm font-medium">3</div>
              <div>Assign priority and status</div>
            </div>
            <div className="flex items-start space-x-3">
              <div className="bg-blue-100 text-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-sm font-medium">4</div>
              <div>Click "Create Task" to save</div>
            </div>
          </div>
        </div>
      )
    },
    {
      title: 'View Options',
      content: (
        <div>
          <h3 className="text-lg font-semibold mb-3">Grid vs Kanban View</h3>
          <div className="grid grid-cols-2 gap-4">
            <div className="border rounded-lg p-3">
              <div className="text-2xl mb-2">📋</div>
              <h4 className="font-medium">Grid View</h4>
              <p className="text-sm text-gray-600">See all tasks as cards</p>
            </div>
            <div className="border rounded-lg p-3">
              <div className="text-2xl mb-2">📊</div>
              <h4 className="font-medium">Kanban View</h4>
              <p className="text-sm text-gray-600">Drag & drop between status columns</p>
            </div>
          </div>
        </div>
      )
    },
    {
      title: 'You\'re Ready!',
      content: (
        <div className="text-center">
          <div className="text-6xl mb-4">🎉</div>
          <h3 className="text-lg font-semibold mb-2">You're all set!</h3>
          <p className="text-gray-600">
            Start creating and managing your {department.name.toLowerCase()} tasks.
            Remember, all your existing data is still here - just with a better interface!
          </p>
        </div>
      )
    }
  ];

  if (!isOpen) return null;

  const currentStepData = trainingSteps[currentStep];
  const isLastStep = currentStep === trainingSteps.length - 1;

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div className="bg-white rounded-lg p-6 w-full max-w-md">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-xl font-semibold text-gray-900">
            {currentStepData.title}
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            ✕
          </button>
        </div>

        <div className="mb-6">
          {currentStepData.content}
        </div>

        {/* Progress bar */}
        <div className="mb-6">
          <div className="w-full bg-gray-200 rounded-full h-2">
            <div
              className="bg-blue-500 h-2 rounded-full transition-all duration-300"
              style={{ width: `${((currentStep + 1) / trainingSteps.length) * 100}%` }}
            />
          </div>
          <div className="text-center text-sm text-gray-500 mt-2">
            Step {currentStep + 1} of {trainingSteps.length}
          </div>
        </div>

        <div className="flex justify-between">
          <button
            onClick={() => setCurrentStep(Math.max(0, currentStep - 1))}
            disabled={currentStep === 0}
            className="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
          >
            Previous
          </button>

          {isLastStep ? (
            <button
              onClick={onClose}
              className="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700"
            >
              Get Started!
            </button>
          ) : (
            <button
              onClick={() => setCurrentStep(Math.min(trainingSteps.length - 1, currentStep + 1))}
              className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              Next
            </button>
          )}
        </div>
      </div>
    </div>
  );
};
```

**Afternoon: Migration Communication Plan (3-4 hours)**

**3. Create migration announcement:**

```markdown
# 🚀 TaskMaster System Upgrade - New React Interface

**Effective Date:** [Your Go-Live Date]

## What's Changing?

We're upgrading TaskMaster with a brand new, faster interface built with modern React technology.

### ✅ What Stays the Same
- **All your existing tasks and data** - nothing is lost!
- **Same task management workflows** you're used to
- **Same department structure** and permissions
- **All existing features** are preserved

### 🎉 What's Better
- **10x Faster** loading and performance
- **Mobile-friendly** responsive design
- **Better user experience** with modern interface
- **Improved reliability** and error handling
- **New features** like Kanban boards and CSV import

## Migration Timeline

### Phase 1: Gradual Rollout (Week 1)
- **Sales Department** goes live first
- **Tech, Marketing, HR** follow
- Both old and new systems available

### Phase 2: Full Migration (Week 2)
- **All departments** migrated
- **Leadership portal** activated
- Old system retired

## What You Need to Do

### Before Migration
- [ ] **Bookmark** the new URL (will be provided)
- [ ] **Complete** any urgent tasks in current system
- [ ] **Review** the user guide (attached)

### During Migration
- [ ] **Attend** 15-minute department training session
- [ ] **Test** creating and editing tasks
- [ ] **Provide feedback** on any issues

### After Migration
- [ ] **Update bookmarks** to new URL
- [ ] **Use new features** like Kanban view
- [ ] **Report any issues** to department leads

## Training Sessions

**15-minute sessions scheduled for each department:**

- Sales: [Date/Time]
- Tech: [Date/Time]
- Marketing: [Date/Time]
- HR: [Date/Time]
- [Continue for all departments]

*Sessions will be recorded for those who can't attend*

## Support During Migration

### Week 1-2: Extra Support
- **Department leads** available for questions
- **IT support** for technical issues
- **Quick response** to feedback

### Getting Help
- **Slack:** #taskmaster-migration
- **Email:** taskmaster-support@company.com
- **Department leads** for workflow questions

## FAQ

**Q: Will I lose any of my tasks?**
A: No! All existing tasks, assignments, and data remain exactly the same.

**Q: Do I need to learn new workflows?**
A: The core workflows are the same, just with a better interface. Training will cover any new features.

**Q: What if I prefer the old system?**
A: We'll run both systems in parallel for 2 weeks to ensure a smooth transition.

**Q: Will this affect my daily work?**
A: Minimal impact. The new system is designed to make your work faster and easier.

## Benefits You'll See Immediately

- ⚡ **Faster loading** - no more waiting for pages
- 📱 **Mobile access** - manage tasks from your phone
- 🎯 **Better organization** - improved task views
- 🔄 **Real-time updates** - see changes instantly
- 📊 **New insights** - better reporting and metrics

---

**Questions?** Contact your department lead or IT support.

**Excited about the upgrade?** We are too! This modernization will make TaskMaster faster, more reliable, and easier to use for everyone.
```

**4. Create department lead checklist:**

```markdown
# Department Lead Migration Checklist

## Pre-Migration (1 week before)
- [ ] Review new interface during demo session
- [ ] Identify department-specific workflows to test
- [ ] Schedule team training session
- [ ] Communicate migration timeline to team
- [ ] Identify potential concerns or questions

## Migration Day
- [ ] Attend department training session
- [ ] Test all critical workflows:
  - [ ] Creating tasks
  - [ ] Editing tasks
  - [ ] Assigning team members
  - [ ] Department-specific features
- [ ] Document any issues or concerns
- [ ] Provide immediate feedback to IT team

## Post-Migration (1-2 weeks after)
- [ ] Monitor team adoption
- [ ] Collect user feedback
- [ ] Report any ongoing issues
- [ ] Help team members with questions
- [ ] Validate all data migrated correctly

## Training Your Team
- [ ] Schedule 15-minute team meeting
- [ ] Walk through key features:
  - [ ] Navigation and department access
  - [ ] Creating and editing tasks
  - [ ] Grid vs Kanban views
  - [ ] Mobile usage tips
- [ ] Address team-specific questions
- [ ] Follow up with individual help as needed

## Success Metrics to Monitor
- [ ] Team members successfully accessing new system
- [ ] Tasks being created and updated
- [ ] No major workflow disruptions
- [ ] Positive feedback on performance improvements
- [ ] Reduced support tickets over time

## Escalation Path
- **Technical Issues:** IT Support (support@company.com)
- **Workflow Questions:** Department Lead Training
- **Feature Requests:** Product Team via IT
- **Urgent Issues:** Direct escalation to IT Lead
```

**Testing Day 23:**
```bash
# Test all training materials
# Review user guides with department leads
# Validate training modal functionality
# Test migration communication channels
```

### DAY 24: Migration Execution & Go-Live
**Focus:** Execute final migration for remaining departments

**Morning Tasks:**
1. **Final Department Migration**
   ```bash
   # Deploy final department configurations
   npm run build:production
   netlify deploy --prod --dir=dist

   # Update environment variables for remaining departments
   export REACT_APP_DEPARTMENTS="sales,accounting,tech,marketing,hr,operations,legal,finance,admin,ideas"
   ```

2. **Go-Live Checklist**
   ```javascript
   // Final pre-launch validation
   const goLiveChecklist = {
     performance: {
       loadTime: '< 2 seconds',
       apiResponse: '< 500ms',
       bundleSize: '< 500KB'
     },
     functionality: {
       taskCRUD: 'tested',
       avatarSystem: 'tested',
       csvImport: 'tested',
       kanbanDragDrop: 'tested',
       leadershipPortal: 'tested'
     },
     security: {
       tokenValidation: 'verified',
       roleBasedAccess: 'verified',
       dataEncryption: 'verified'
     }
   };
   ```

3. **Communication Rollout**
   ```html
   <!-- Go-live announcement email template -->
   <div class="announcement-email">
     <h2>🚀 TaskMaster Migration Complete!</h2>
     <p>The new TaskMaster interface is now live for all departments.</p>

     <div class="key-improvements">
       <h3>What's New:</h3>
       <ul>
         <li>📱 Modern, responsive interface</li>
         <li>⚡ 5x faster performance</li>
         <li>🎯 Improved Kanban boards</li>
         <li>📊 Enhanced leadership dashboard</li>
         <li>📤 Streamlined CSV import</li>
       </ul>
     </div>

     <div class="access-info">
       <h3>Access Your Department:</h3>
       <p><strong>URL:</strong> https://taskmaster.company.com</p>
       <p><strong>Login:</strong> Use your existing company email</p>
       <p><strong>Support:</strong> help@company.com</p>
     </div>
   </div>
   ```

**Afternoon Tasks:**
1. **Live Monitoring Setup**
   ```javascript
   // Real-time monitoring dashboard
   const monitoringConfig = {
     alerts: {
       errorRate: { threshold: '5%', action: 'notify-dev-team' },
       responseTime: { threshold: '2s', action: 'scale-resources' },
       userFeedback: { threshold: 'negative', action: 'priority-support' }
     },
     metrics: {
       activeUsers: 'real-time',
       taskOperations: 'per-minute',
       departmentUsage: 'hourly',
       performanceScores: 'continuous'
     }
   };
   ```

2. **Rollback Procedures**
   ```bash
   # Emergency rollback script
   #!/bin/bash
   echo "EMERGENCY ROLLBACK PROCEDURE"

   # 1. Switch DNS back to old system
   # 2. Notify all users immediately
   # 3. Preserve any new data created
   # 4. Schedule emergency maintenance window

   echo "Rollback completed. Investigating issues..."
   ```

**Testing Day 24:**
```bash
# Validate go-live checklist
# Test emergency rollback procedures
# Monitor real-time metrics
# Validate all departments accessible
```

### DAY 25: Post-Migration Monitoring & Support
**Focus:** Monitor system performance and provide user support

**Morning Tasks:**
1. **Performance Monitoring**
   ```javascript
   // Performance tracking implementation
   const performanceTracker = {
     metrics: {
       pageLoadTime: performance.timing.loadEventEnd - performance.timing.navigationStart,
       apiLatency: [],
       userInteractions: [],
       errorRates: {}
     },

     track: function(metric, value) {
       this.metrics[metric].push({
         value,
         timestamp: Date.now(),
         userAgent: navigator.userAgent,
         department: getCurrentDepartment()
       });
     },

     report: function() {
       // Send metrics to monitoring service
       fetch('/api/metrics', {
         method: 'POST',
         body: JSON.stringify(this.metrics)
       });
     }
   };
   ```

2. **User Feedback Collection**
   ```html
   <!-- Feedback widget for early issues -->
   <div class="feedback-widget">
     <button id="feedback-btn" class="feedback-toggle">
       💬 Quick Feedback
     </button>

     <div id="feedback-form" class="feedback-form hidden">
       <h3>How's the new TaskMaster?</h3>
       <div class="rating-buttons">
         <button data-rating="5">😍 Love it</button>
         <button data-rating="4">👍 Good</button>
         <button data-rating="3">😐 Okay</button>
         <button data-rating="2">👎 Issues</button>
         <button data-rating="1">😡 Problems</button>
       </div>
       <textarea placeholder="Tell us more... (optional)"></textarea>
       <button class="submit-feedback">Send Feedback</button>
     </div>
   </div>
   ```

**Afternoon Tasks:**
1. **Issue Triage System**
   ```javascript
   // Issue tracking and response system
   const issueTracker = {
     categories: {
       critical: { response: '< 1 hour', escalation: 'immediate' },
       high: { response: '< 4 hours', escalation: 'same-day' },
       medium: { response: '< 24 hours', escalation: 'next-day' },
       low: { response: '< 72 hours', escalation: 'weekly' }
     },

     processIssue: function(issue) {
       const priority = this.determinePriority(issue);
       const category = this.categories[priority];

       // Auto-assign based on issue type
       const assignee = this.getAssignee(issue.type);

       // Create ticket and notify team
       this.createTicket(issue, priority, assignee);
       this.notifyTeam(issue, category.response);
     }
   };
   ```

**Testing Day 25:**
```bash
# Monitor performance metrics
# Test feedback collection system
# Validate issue triage workflow
# Track user support requests
```

### DAY 26: Optimization & Fine-tuning
**Focus:** Optimize performance and address early feedback

**Morning Tasks:**
1. **Performance Optimization**
   ```javascript
   // Code splitting for better performance
   const LazyKanbanView = lazy(() => import('./views/KanbanView'));
   const LazyLeadershipDashboard = lazy(() => import('./views/LeadershipDashboard'));
   const LazyCSVImport = lazy(() => import('./components/CSVImport'));

   // Implement progressive loading
   const ProgressiveTaskList = () => {
     const [visibleTasks, setVisibleTasks] = useState(20);
     const [loading, setLoading] = useState(false);

     const loadMoreTasks = useCallback(async () => {
       setLoading(true);
       // Load next batch of tasks
       setVisibleTasks(prev => prev + 20);
       setLoading(false);
     }, []);

     return (
       <InfiniteScroll
         hasMore={visibleTasks < totalTasks}
         loadMore={loadMoreTasks}
         loader={<TaskSkeleton />}
       >
         {tasks.slice(0, visibleTasks).map(task =>
           <TaskCard key={task.id} task={task} />
         )}
       </InfiniteScroll>
     );
   };
   ```

2. **Caching Improvements**
   ```javascript
   // Enhanced caching strategy
   const cacheManager = {
     strategies: {
       tasks: { ttl: 300000, strategy: 'network-first' },
       avatars: { ttl: 3600000, strategy: 'cache-first' },
       departments: { ttl: 86400000, strategy: 'cache-first' },
       leadership: { ttl: 600000, strategy: 'network-first' }
     },

     get: async function(key, category) {
       const strategy = this.strategies[category];
       const cached = await this.getCached(key);

       if (strategy.strategy === 'cache-first' && cached) {
         return cached;
       }

       try {
         const fresh = await this.fetchFresh(key);
         this.setCached(key, fresh, strategy.ttl);
         return fresh;
       } catch (error) {
         return cached || null;
       }
     }
   };
   ```

**Afternoon Tasks:**
1. **User Experience Improvements**
   ```javascript
   // Enhanced error handling with user-friendly messages
   const ErrorBoundary = ({ children }) => {
     const [hasError, setHasError] = useState(false);
     const [errorInfo, setErrorInfo] = useState(null);

     useEffect(() => {
       const handleError = (error, errorInfo) => {
         setHasError(true);
         setErrorInfo({
           message: getUserFriendlyMessage(error),
           action: getSuggestedAction(error),
           reportId: generateErrorReport(error, errorInfo)
         });
       };

       window.addEventListener('error', handleError);
       return () => window.removeEventListener('error', handleError);
     }, []);

     if (hasError) {
       return (
         <div className="error-recovery">
           <h3>Oops! Something went wrong</h3>
           <p>{errorInfo.message}</p>
           <div className="error-actions">
             <button onClick={() => window.location.reload()}>
               🔄 Refresh Page
             </button>
             <button onClick={() => setHasError(false)}>
               ↩️ Try Again
             </button>
           </div>
           <details className="error-details">
             <summary>Technical Details</summary>
             <p>Report ID: {errorInfo.reportId}</p>
           </details>
         </div>
       );
     }

     return children;
   };
   ```

**Testing Day 26:**
```bash
# Test performance optimizations
# Validate caching improvements
# Test error boundary functionality
# Monitor user experience metrics
```

### DAY 27: Final Validation & Documentation
**Focus:** Complete final testing and create maintenance documentation

**Morning Tasks:**
1. **Comprehensive System Validation**
   ```javascript
   // End-to-end validation suite
   const systemValidation = {
     async runFullValidation() {
       const results = await Promise.all([
         this.validateTaskOperations(),
         this.validateAvatarSystem(),
         this.validateCSVImport(),
         this.validateKanbanBoards(),
         this.validateLeadershipPortal(),
         this.validatePerformance(),
         this.validateSecurity()
       ]);

       return {
         overall: results.every(r => r.passed),
         details: results,
         timestamp: new Date().toISOString()
       };
     },

     async validateTaskOperations() {
       // Test all CRUD operations
       const testTask = await this.createTestTask();
       const updated = await this.updateTestTask(testTask.id);
       const deleted = await this.deleteTestTask(testTask.id);

       return {
         passed: testTask && updated && deleted,
         operations: ['create', 'read', 'update', 'delete']
       };
     }
   };
   ```

2. **Documentation Creation**
   ```markdown
   # TaskMaster System Maintenance Guide

   ## Daily Monitoring Checklist
   - [ ] Check system performance metrics
   - [ ] Review error logs and user feedback
   - [ ] Verify API response times < 500ms
   - [ ] Confirm all departments accessible
   - [ ] Monitor user activity levels

   ## Weekly Maintenance Tasks
   - [ ] Update dependency versions
   - [ ] Review and optimize database queries
   - [ ] Clean up old cache entries
   - [ ] Backup system configurations
   - [ ] Performance optimization review

   ## Monthly Reviews
   - [ ] Security audit and updates
   - [ ] User feedback analysis and prioritization
   - [ ] Capacity planning and scaling review
   - [ ] Documentation updates
   - [ ] Training material updates

   ## Emergency Procedures
   ### System Down
   1. Check hosting service status
   2. Verify DNS configuration
   3. Review recent deployments
   4. Activate rollback if necessary
   5. Communicate with users

   ### Performance Issues
   1. Check current load metrics
   2. Identify bottleneck components
   3. Scale resources if needed
   4. Optimize problematic queries
   5. Update caching strategies
   ```

**Afternoon Tasks:**
1. **Knowledge Transfer Documentation**
   ```markdown
   # Developer Handoff Guide

   ## Architecture Overview
   - **Frontend:** React + TypeScript + Tailwind CSS
   - **State Management:** Zustand
   - **Build Tool:** Vite
   - **Hosting:** Netlify
   - **Backend:** Google Apps Script (REST API)
   - **Database:** Google Sheets

   ## Key Components
   - `TaskStore` - Central state management
   - `TaskCard` - Individual task display
   - `KanbanBoard` - Drag-and-drop interface
   - `CSVImport` - Bulk task import
   - `LeadershipDashboard` - Admin interface

   ## Development Workflow
   1. Pull latest changes
   2. Create feature branch
   3. Develop and test locally
   4. Run validation suite
   5. Deploy to staging
   6. User acceptance testing
   7. Deploy to production

   ## Troubleshooting Common Issues
   - **Slow loading:** Check bundle size and lazy loading
   - **API errors:** Verify Google Apps Script permissions
   - **Cache issues:** Clear browser cache and CDN
   - **Department access:** Check user permissions and routing
   ```

**Testing Day 27:**
```bash
# Run comprehensive system validation
# Test all maintenance procedures
# Validate documentation accuracy
# Complete knowledge transfer review
```

### DAY 28: Project Completion & Handoff
**Focus:** Complete migration project and establish ongoing support

**Morning Tasks:**
1. **Final Project Review**
   ```javascript
   // Migration success metrics validation
   const migrationMetrics = {
     performance: {
       before: { loadTime: '5-8s', fileSize: '40-47KB', duplicateCode: '90%' },
       after: { loadTime: '<2s', fileSize: '<500KB', duplicateCode: '<5%' },
       improvement: 'Load time: 75% faster, Code duplication: 95% reduction'
     },

     functionality: {
       preserved: ['Task CRUD', 'Avatar system', 'HubSpot integration', 'Email actions'],
       enhanced: ['Kanban boards', 'CSV import', 'Leadership dashboard', 'Mobile responsive'],
       new: ['Real-time updates', 'Progressive loading', 'Advanced filtering']
     },

     maintenance: {
       before: '10+ files to update per change',
       after: 'Single source of truth',
       improvement: '90% reduction in maintenance overhead'
     }
   };
   ```

2. **Support System Setup**
   ```html
   <!-- Ongoing support contact methods -->
   <div class="support-system">
     <h3>TaskMaster Support</h3>

     <div class="support-channels">
       <div class="priority-support">
         <h4>🚨 Critical Issues</h4>
         <p><strong>Email:</strong> critical@company.com</p>
         <p><strong>Response:</strong> < 1 hour</p>
       </div>

       <div class="general-support">
         <h4>💬 General Support</h4>
         <p><strong>Email:</strong> taskmaster@company.com</p>
         <p><strong>Response:</strong> < 24 hours</p>
       </div>

       <div class="feature-requests">
         <h4>💡 Feature Requests</h4>
         <p><strong>Portal:</strong> features.company.com</p>
         <p><strong>Review:</strong> Monthly planning</p>
       </div>
     </div>
   </div>
   ```

**Afternoon Tasks:**
1. **Project Completion Report**
   ```markdown
   # TaskMaster Migration - Project Completion Report

   ## Executive Summary
   ✅ **Migration Completed Successfully**
   - 7-week timeline executed on schedule
   - All 10 departments migrated without data loss
   - 90% reduction in code duplication achieved
   - 75% improvement in page load performance

   ## Key Achievements
   - **Architecture Modernization:** Migrated from legacy HTML to React
   - **Performance Optimization:** Sub-2-second load times achieved
   - **Code Quality:** Single source of truth established
   - **User Experience:** Modern, responsive interface deployed
   - **Maintenance Efficiency:** 90% reduction in update overhead

   ## Migration Statistics
   - **Files Consolidated:** 10 department HTML files → 1 React app
   - **Code Reduction:** 19,469 lines → 8,500 lines (55% reduction)
   - **Performance Gain:** 5-8s load time → <2s (75% faster)
   - **User Training:** 100% department coverage completed
   - **Zero Downtime:** Achieved through gradual rollout

   ## Ongoing Support Structure
   - **Technical Lead:** [Assigned developer]
   - **Support Channels:** Email, portal, escalation procedures
   - **Maintenance Schedule:** Daily monitoring, weekly updates
   - **Review Cycle:** Monthly feature planning and optimization

   ## Success Metrics Met
   - ✅ Page load time < 2 seconds
   - ✅ Code duplication < 5%
   - ✅ Single file updates for changes
   - ✅ All departments successfully migrated
   - ✅ User training 100% completion
   - ✅ Zero data loss during migration

   ## Next Steps
   1. **Month 1:** Monitor performance and gather user feedback
   2. **Month 2:** Implement priority feature requests
   3. **Month 3:** Performance optimization and scaling review
   4. **Ongoing:** Regular security updates and maintenance
   ```

2. **Celebration & Team Recognition**
   ```markdown
   # 🎉 Migration Success Celebration

   ## Project Team Recognition
   Thank you to everyone who contributed to the successful TaskMaster migration:

   - **Development Team:** Exceptional technical execution
   - **Department Leaders:** Valuable feedback and testing
   - **End Users:** Patience and enthusiasm during transition
   - **Support Team:** Outstanding user assistance

   ## What We Accomplished Together
   - Transformed a maintenance nightmare into a streamlined system
   - Delivered a modern, fast, and reliable task management platform
   - Reduced development time for future features by 90%
   - Created a foundation for continued innovation and growth

   ## Looking Forward
   The new TaskMaster platform positions us for:
   - Rapid feature development and deployment
   - Enhanced user experience and productivity
   - Scalable growth as our organization expands
   - Continued innovation in task management

   **Welcome to the future of TaskMaster! 🚀**
   ```

**Testing Day 28:**
```bash
# Final migration metrics validation
# Test all support channels
# Complete project documentation review
# Celebrate successful migration completion!
```

---

# FINAL TESTING & VALIDATION GUIDE

## Comprehensive Testing Checklist

### Pre-Migration Testing (Week 1-5)
- [ ] **API Functionality Testing**
  - [ ] All CRUD operations working correctly
  - [ ] Authentication and authorization
  - [ ] Error handling and recovery
  - [ ] Performance benchmarking

- [ ] **Component Testing**
  - [ ] TaskCard rendering and interactions
  - [ ] KanbanBoard drag-and-drop functionality
  - [ ] CSVImport file processing
  - [ ] LeadershipDashboard access controls

- [ ] **Integration Testing**
  - [ ] React app ↔ Google Apps Script API
  - [ ] Zustand state management
  - [ ] Department routing and access
  - [ ] Real-time updates

- [ ] **Performance Testing**
  - [ ] Page load times < 2 seconds
  - [ ] API response times < 500ms
  - [ ] Bundle size optimization
  - [ ] Mobile responsiveness

### Migration Testing (Week 6)
- [ ] **Data Migration Validation**
  - [ ] All tasks preserved during migration
  - [ ] User assignments maintained
  - [ ] Department access verified
  - [ ] Avatar assignments intact

- [ ] **User Acceptance Testing**
  - [ ] Department lead validation
  - [ ] End-user feedback collection
  - [ ] Workflow verification
  - [ ] Training material effectiveness

- [ ] **Security Testing**
  - [ ] Authentication mechanisms
  - [ ] Role-based access control
  - [ ] Data encryption validation
  - [ ] Token security verification

### Post-Migration Testing (Week 7)
- [ ] **Production Monitoring**
  - [ ] Real-time performance metrics
  - [ ] Error rate monitoring
  - [ ] User activity tracking
  - [ ] System stability validation

- [ ] **Support System Testing**
  - [ ] Issue tracking workflow
  - [ ] Response time validation
  - [ ] Escalation procedures
  - [ ] Feedback collection system

## Validation Scripts

### Performance Validation
```javascript
// Automated performance testing
const performanceTest = {
  async validateLoadTimes() {
    const start = performance.now();
    await fetch('/api/tasks?department=sales');
    const end = performance.now();

    const loadTime = end - start;
    console.log(`Load time: ${loadTime}ms`);

    return {
      passed: loadTime < 2000,
      actual: loadTime,
      expected: '< 2000ms'
    };
  }
};
```

### Data Integrity Validation
```javascript
// Validate data migration completeness
const dataValidation = {
  async validateTaskMigration() {
    const oldData = await this.getOldSystemData();
    const newData = await this.getNewSystemData();

    return {
      taskCount: oldData.length === newData.length,
      dataIntegrity: this.compareTaskData(oldData, newData),
      assignmentsPreserved: this.validateAssignments(oldData, newData)
    };
  }
};
```

## Success Criteria

### Technical Metrics
- ✅ Page load time < 2 seconds
- ✅ API response time < 500ms
- ✅ Bundle size < 500KB
- ✅ Error rate < 1%
- ✅ Uptime > 99.9%

### Business Metrics
- ✅ Zero data loss during migration
- ✅ 100% department migration completion
- ✅ User training completion > 95%
- ✅ Support ticket reduction > 50%
- ✅ User satisfaction score > 4.0/5.0

### Maintenance Metrics
- ✅ Code duplication < 5%
- ✅ Single file updates for changes
- ✅ Development time reduction > 80%
- ✅ Bug resolution time < 24 hours
- ✅ Feature delivery time > 70% faster

---

**🎯 MIGRATION PROJECT COMPLETE**

*This comprehensive 7-week implementation guide provides everything needed to successfully migrate from the current Google Apps Script system to a modern React-based TaskMaster platform. Each day includes specific tasks, code examples, testing procedures, and validation steps to ensure a smooth and successful transition.*

**Total Estimated Effort:** 7 weeks (28 working days)
**Expected Outcome:** 90% reduction in maintenance overhead, 75% performance improvement, modern user experience