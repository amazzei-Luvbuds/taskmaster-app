# TaskMaster Modernization Plan
## Hybrid Approach: Keep Google Sheets Backend + Modern Frontend

---

## Project Analysis Summary

### Current State
- **Google Apps Script** task management system (Code.js - 641 functions/variables)
- **10 Department interfaces** (each ~1000+ lines of duplicated HTML)
- **Google Sheets** data storage
- **Leadership security** layer
- **CSV import** capabilities
- **Kanban boards** and dashboards

### Key Problems Identified
1. **Massive code duplication** across department HTML files
2. **Monolithic architecture** - single 120KB Code.js file
3. **Mixed concerns** - UI, business logic, and data access combined
4. **Maintenance nightmare** - changes require updating 10+ files
5. **Performance issues** - large HTML files, no optimization

---

## Recommended Solution: Hybrid Approach (Option C)

### Architecture
```
Modern Frontend (Netlify) ←→ Google Apps Script (Backend) ←→ Google Sheets (Data)
     FREE                       FREE                          FREE
```

### Why This Works
- ✅ **Zero additional hosting costs** (Netlify free tier)
- ✅ **Keep existing Google Sheets data** (no migration needed)
- ✅ **Modern development experience** (React, TypeScript, hot reload)
- ✅ **Gradual migration** (department by department)
- ✅ **Better performance** (CDN, code splitting, optimization)
- ✅ **Easier maintenance** (shared components, single codebase)

---

## Phase 1: Backend API Transformation

### Convert Apps Script to Pure API

**Current State Analysis:**
Your current `Code.js` (120KB) has these key patterns:
- ✅ Already has JSON response functions
- ✅ Sheet ID constants defined (`MASTER_SHEET_ID`, `AVATAR_SHEET_ID`)
- ✅ Caching system with `getCachedJSON_()` and `putCachedJSON_()`
- ✅ Security with signed action tokens
- ⚠️ Mixed HTML serving + API functions in single file

**Target State:**
```javascript
// Enhanced API with existing patterns
function doGet(e) {
  const action = e.parameter.action;
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
        return jsonResponse(getTasksByDepartment(e.parameter.department), cors);
      case 'createTask':
        return jsonResponse(createTask(e.parameter), cors);
      case 'updateTask':
        return jsonResponse(updateTask(e.parameter), cors);
      case 'deleteTask':
        return jsonResponse(deleteTask(e.parameter.id), cors);
      case 'verifyLeadership':
        return jsonResponse(verifyLeadershipAccess(e.parameter.email), cors);
      case 'getAvatars':
        return jsonResponse(getAllAvatars(), cors);
      case 'getSalesMetrics':
        return jsonResponse(getSalesCallMetrics(), cors);
      case 'bulkImportCSV':
        return jsonResponse(processBulkCSVImport(e.parameter), cors);
      default:
        return jsonResponse({error: 'Unknown action'}, cors, 400);
    }
  } catch (error) {
    console.error('API Error:', error);
    return jsonResponse({error: 'Internal server error'}, cors, 500);
  }
}

function doPost(e) {
  // Handle POST requests with JSON body
  const postData = JSON.parse(e.postData.contents);
  const mockGet = { parameter: { ...postData, method: 'POST' } };
  return doGet(mockGet);
}

function jsonResponse(data, headers = {}, status = 200) {
  return ContentService
    .createTextOutput(JSON.stringify({ success: status < 400, data, status }))
    .setMimeType(ContentService.MimeType.JSON)
    .setHeaders(headers);
}
```

### API Endpoints to Create
Based on your current functionality:

**Core Task Management:**
- `GET /?action=getTasks&department=sales` - Get department tasks
- `POST /?action=createTask` - Create new task
- `POST /?action=updateTask` - Update existing task
- `DELETE /?action=deleteTask&id=123` - Delete task

**Authentication & Authorization:**
- `GET /?action=verifyLeadership&email=user@domain.com` - Check leadership access
- `GET /?action=getAdminKey` - Get admin authentication

**Advanced Features (existing in your app):**
- `GET /?action=getAvatars` - Get avatar profiles for task assignments
- `GET /?action=getSalesMetrics` - Get HubSpot call metrics
- `POST /?action=bulkImportCSV` - Import CSV task data
- `GET /?action=getStats&department=sales` - Get department statistics
- `POST /?action=processSignedAction` - Handle secure email action links

**Data Export:**
- `GET /?action=exportTasks&department=sales&format=csv` - Export tasks as CSV
- `GET /?action=getKanbanData&department=sales` - Get Kanban board data

---

## Phase 2: Modern Frontend Setup

### Technology Stack
- **Framework:** React 18 with TypeScript
- **Styling:** Tailwind CSS
- **Build Tool:** Vite
- **State Management:** Zustand (lightweight)
- **HTTP Client:** Axios
- **Deployment:** Netlify (free tier)

### Project Structure
```
frontend/
├── src/
│   ├── components/
│   │   ├── shared/           # Reusable components
│   │   │   ├── Button.tsx
│   │   │   ├── Modal.tsx
│   │   │   ├── TaskCard.tsx
│   │   │   └── KanbanBoard.tsx
│   │   ├── department/       # Department-specific components
│   │   │   ├── DepartmentDashboard.tsx
│   │   │   ├── TaskCreator.tsx
│   │   │   └── StatsPanel.tsx
│   │   └── layout/          # Layout components
│   │       ├── Header.tsx
│   │       ├── Sidebar.tsx
│   │       └── Navigation.tsx
│   ├── pages/               # Page components
│   │   ├── Dashboard.tsx
│   │   ├── DepartmentPage.tsx
│   │   ├── KanbanView.tsx
│   │   └── LeadershipPortal.tsx
│   ├── services/           # API integration
│   │   ├── api.ts          # Apps Script API client
│   │   ├── auth.ts         # Leadership authentication
│   │   └── types.ts        # TypeScript types
│   ├── stores/             # State management
│   │   ├── taskStore.ts
│   │   ├── authStore.ts
│   │   └── departmentStore.ts
│   └── utils/              # Helper functions
│       ├── constants.ts
│       ├── formatters.ts
│       └── validators.ts
├── public/
├── package.json
├── vite.config.ts
├── tailwind.config.js
└── netlify.toml
```

### Component Architecture
Based on your current HTML structure analysis:

```typescript
// Core types based on your existing data structure
interface Task {
  id: string;
  title: string;
  description: string;
  assignee: string;
  priority: 'High' | 'Medium' | 'Low';
  status: 'Not Started' | 'In Progress' | 'Completed';
  department: string;
  dueDate?: string;
  createdDate: string;
  avatarUrl?: string; // From your avatar system
}

interface Department {
  id: string;
  name: string;
  icon: string;
  color: string;
  gradient: string;
}

// Shared TaskCard component (replaces 10 duplicated versions)
interface TaskCardProps {
  task: Task;
  department: Department;
  onUpdate: (task: Task) => void;
  onDelete: (id: string) => void;
  onAssignAvatar: (taskId: string, avatarId: string) => void;
}

export const TaskCard: React.FC<TaskCardProps> = ({ task, department, onUpdate, onDelete, onAssignAvatar }) => {
  // Single, reusable component for all departments
  // Includes avatar assignment, priority colors, status updates
};

// Department configuration matching your current setup
const DEPARTMENTS = {
  accounting: {
    id: 'accounting',
    name: 'Accounting',
    icon: '💰',
    color: '#10b981',
    gradient: 'linear-gradient(135deg, #10b981, #059669)'
  },
  sales: {
    id: 'sales',
    name: 'Sales',
    icon: '📞',
    color: '#3b82f6',
    gradient: 'linear-gradient(135deg, #3b82f6, #2563eb)'
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
```

---

## Phase 3: Development Environment Setup

### Prerequisites
- Node.js 18+ installed
- Git repository
- Netlify account (free)

### Initial Setup Commands
```bash
# Create new React TypeScript project
npm create vite@latest taskmaster-frontend -- --template react-ts
cd taskmaster-frontend

# Install dependencies
npm install
npm install -D tailwindcss postcss autoprefixer
npm install axios zustand react-router-dom

# Initialize Tailwind CSS
npx tailwindcss init -p

# Install development tools
npm install -D @types/node
```

### Environment Configuration
```typescript
// src/config/environment.ts
export const config = {
  appsScriptUrl: 'https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec',
  isDevelopment: process.env.NODE_ENV === 'development',
  apiTimeout: 10000,
};
```

### API Service Setup
```typescript
// src/services/api.ts
import axios from 'axios';
import { config } from '../config/environment';

const api = axios.create({
  baseURL: config.appsScriptUrl,
  timeout: config.apiTimeout,
});

export const taskAPI = {
  getTasks: (department: string) => 
    api.get(`?action=getTasks&department=${department}`),
  
  createTask: (task: CreateTaskRequest) =>
    api.post('?action=createTask', task),
  
  updateTask: (task: UpdateTaskRequest) =>
    api.post('?action=updateTask', task),
  
  deleteTask: (id: string) =>
    api.get(`?action=deleteTask&id=${id}`),
};
```

---

## Phase 4: Migration Strategy

### Step 1: Pilot Department (Week 1-2)
1. **Choose Sales department** as pilot (most active)
2. **Build core components:**
   - TaskCard
   - DepartmentDashboard
   - TaskCreator
   - KanbanBoard
3. **Connect to existing Apps Script APIs**
4. **Deploy to Netlify staging**
5. **Test with 2-3 users**

### Step 2: Core Departments (Week 3-4)
1. **Add department configuration system**
2. **Migrate 3-4 more departments:**
   - Tech
   - Marketing
   - Accounting
   - HR
3. **Implement shared navigation**
4. **Add responsive design**
5. **Performance optimization**

### Step 3: Remaining Departments (Week 5-6)
1. **Migrate remaining departments:**
   - Customer Retention
   - Swag
   - Ideas
   - Trade Shows
   - Purchasing
2. **Leadership portal migration**
3. **CSV import functionality**
4. **Final testing and bug fixes**

### Step 4: Cutover (Week 7)
1. **Update main navigation** to point to new frontend
2. **Run both systems in parallel** for 1 week
3. **Monitor performance and user feedback**
4. **Complete migration**
5. **Archive old HTML files**

---

## Phase 5: Netlify Deployment

### Netlify Configuration
```toml
# netlify.toml
[build]
  command = "npm run build"
  publish = "dist"

[build.environment]
  NODE_VERSION = "18"

[[redirects]]
  from = "/*"
  to = "/index.html"
  status = 200

[context.production.environment]
  REACT_APP_API_URL = "https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec"
```

### Deployment Steps
1. **Build the project:** `npm run build`
2. **Connect GitHub repo** to Netlify
3. **Configure build settings**
4. **Deploy automatically** on git push
5. **Set up custom domain** (optional)

### Free Tier Benefits
- **Static hosting:** Unlimited
- **CDN delivery:** Global
- **SSL certificates:** Automatic
- **Deploy previews:** Every PR
- **Build minutes:** 300/month (plenty)
- **Bandwidth:** 100GB/month

---

## Expected Benefits

### Performance Improvements
- **Load time:** 70% faster (CDN + optimized builds)
- **Bundle size:** 80% smaller (code splitting)
- **User experience:** Modern, responsive interface

### Development Benefits
- **Hot reload:** Instant development feedback
- **TypeScript:** Type safety and better IDE support
- **Component reuse:** 90% code reduction across departments
- **Easy testing:** Jest + React Testing Library

### Maintenance Benefits
- **Single codebase:** One place to make changes
- **Version control:** Proper Git workflow
- **Automated deployment:** Push to deploy
- **Error tracking:** Built-in debugging tools

---

## Risk Mitigation & Testing Strategy

### Technical Risk Assessment

**HIGH PRIORITY RISKS:**
1. **Apps Script API Transformation**
   - **Risk:** Breaking existing functionality during API conversion
   - **Mitigation:**
     - Create new API endpoints alongside existing HTML serving
     - Test each endpoint with Postman/curl before frontend integration
     - Use feature flags to gradually switch endpoints
   - **Testing:** Automated API tests for all endpoints

2. **CORS and Security**
   - **Risk:** Browser blocking API calls due to CORS
   - **Mitigation:**
     - Implement proper CORS headers with OPTIONS support
     - Test from multiple domains (localhost, Netlify staging, production)
     - Use Apps Script's built-in security for sensitive operations
   - **Testing:** Cross-origin request testing from different environments

3. **Performance with Large Datasets**
   - **Risk:** Slow API responses with many tasks/departments
   - **Mitigation:**
     - Leverage your existing caching system (`getCachedJSON_`)
     - Implement pagination for large task lists
     - Use sheet signature validation to avoid unnecessary recalculations
   - **Testing:** Load testing with 1000+ tasks per department

**MEDIUM PRIORITY RISKS:**
4. **Data Consistency**
   - **Risk:** Frontend and backend data getting out of sync
   - **Mitigation:**
     - Implement optimistic updates with rollback
     - Add data validation on both frontend and backend
     - Use your existing cache epoch system for invalidation
   - **Testing:** Concurrent user testing

5. **User Authentication Flow**
   - **Risk:** Leadership access controls not working properly
   - **Mitigation:**
     - Preserve existing `verifyLeadershipAccess` logic
     - Add frontend route guards based on email verification
     - Implement session management with tokens
   - **Testing:** Role-based access testing

### Business Risk Assessment

**USER ADOPTION RISKS:**
1. **Interface Change Shock**
   - **Risk:** Users rejecting new interface
   - **Mitigation:**
     - Keep visual design similar to current HTML pages
     - Gradual rollout by department
     - Provide "classic view" fallback during transition
   - **Testing:** User acceptance testing with key stakeholders

2. **Feature Parity**
   - **Risk:** Missing critical features users depend on
   - **Mitigation:**
     - Audit all existing features before migration starts
     - Create feature checklist for each department
     - Test all workflows end-to-end
   - **Testing:** Department-specific workflow testing

3. **Training and Support**
   - **Risk:** Users unable to adapt to new system
   - **Mitigation:**
     - Create migration guides for each department
     - Run parallel systems for 2 weeks minimum
     - Designate department champions for support
   - **Testing:** User training sessions and feedback collection

### Comprehensive Testing Strategy

**Phase 1: API Testing**
```bash
# API endpoint validation script
#!/bin/bash
BASE_URL="https://script.google.com/macros/s/YOUR_SCRIPT_ID/exec"

# Test core endpoints
curl "$BASE_URL?action=getTasks&department=sales" | jq '.'
curl -X POST "$BASE_URL" -d '{"action":"createTask","title":"Test Task"}' | jq '.'
curl "$BASE_URL?action=verifyLeadership&email=admin@company.com" | jq '.'

# Test error handling
curl "$BASE_URL?action=invalid" | jq '.'
```

**Phase 2: Frontend Testing**
```javascript
// React Testing Library tests
describe('TaskCard Component', () => {
  test('renders task with correct department styling', () => {
    // Test department-specific colors and icons
  });

  test('handles task updates correctly', () => {
    // Test optimistic updates and error rollback
  });

  test('respects user permissions', () => {
    // Test leadership vs regular user permissions
  });
});

// E2E Testing with Playwright
test('complete task workflow', async ({ page }) => {
  await page.goto('/sales');
  await page.click('[data-testid="create-task"]');
  await page.fill('[data-testid="task-title"]', 'New Sales Task');
  await page.click('[data-testid="save-task"]');
  await expect(page.locator('[data-testid="task-list"]')).toContainText('New Sales Task');
});
```

**Phase 3: Performance Testing**
```javascript
// Load testing script
const stress = require('loadtest');

const options = {
  url: 'https://your-netlify-app.netlify.app/api/tasks',
  maxRequests: 1000,
  concurrency: 10,
  method: 'GET'
};

stress.loadTest(options, (error, results) => {
  console.log('Max requests per second:', results.rps);
  console.log('Mean latency:', results.meanLatencyMs);
});
```

### Rollback Strategy

**Immediate Rollback (< 5 minutes):**
1. **DNS/Routing Rollback**
   - Change navigation links back to original HTML pages
   - Update index.html to point to old department pages
   - No data loss (Google Sheets unchanged)

2. **Feature Flag Rollback**
   - Toggle API endpoints back to HTML serving mode
   - Revert Apps Script deployment to previous version
   - Users continue with original system

**Gradual Rollback (Department by Department):**
1. **Selective Rollback**
   - Rollback problematic departments while keeping successful ones
   - Use department-specific routing to control access
   - Allow testing and fixes without full system rollback

**Data Recovery Plan:**
- **No data at risk** - Google Sheets remain unchanged
- **Settings backup** - Export all Apps Script properties before migration
- **Code backup** - Git tags for every deployment phase
- **User feedback** - Collect and address issues in real-time

---

## Timeline Summary

| Week | Phase | Deliverables |
|------|-------|-------------|
| 1-2 | Backend API + Pilot | Sales department working |
| 3-4 | Core Migration | 4 departments migrated |
| 5-6 | Complete Migration | All departments + leadership |
| 7 | Cutover | Live system switch |

**Total Timeline:** 7 weeks
**Cost:** $0 (all free services)
**Risk Level:** Low (gradual migration)

---

## Success Metrics

### Technical Metrics
- **Page load time:** < 2 seconds
- **Bundle size:** < 500KB
- **API response time:** < 1 second
- **Error rate:** < 1%

### User Metrics
- **User adoption:** 100% within 2 weeks of department cutover
- **Support tickets:** < 5 per department during migration
- **User satisfaction:** > 90% positive feedback

### Business Metrics
- **Development time:** 50% reduction for new features
- **Bug resolution:** 75% faster
- **Onboarding time:** 60% reduction for new users

---

---

## Detailed Technical Specifications

### Apps Script API Transformation Details

**Function Mapping (120KB Code.js → API endpoints):**

1. **Existing Functions to Convert:**
```javascript
// Current: Mixed HTML/API functions
function doGet() → function doGet(e) // Pure API routing
function getTasksByDepartment() → GET /?action=getTasks&department=X
function createTask() → POST /?action=createTask
function updateTask() → POST /?action=updateTask
function deleteTask() → GET /?action=deleteTask&id=X
function getAllAvatars() → GET /?action=getAvatars
function getSalesCallMetrics() → GET /?action=getSalesMetrics
function verifyLeadershipAccess() → GET /?action=verifyLeadership&email=X
```

2. **Preserve Critical Security Functions:**
```javascript
// Keep these functions exactly as-is
function getLinkSigningSecret() // For secure email actions
function signActionToken() // For secure email actions
function verifyActionToken() // For secure email actions
function getAdminKey_() // For admin authentication
function getCachedJSON_() // For performance optimization
function computeSheetSignature_() // For cache invalidation
```

3. **Enhanced Error Handling:**
```javascript
function apiErrorHandler(error, action) {
  console.error(`API Error in ${action}:`, error);

  // Log to Apps Script for debugging
  if (typeof Logger !== 'undefined') {
    Logger.log(`Error in ${action}: ${error.toString()}`);
  }

  return {
    success: false,
    error: error.message || 'Unknown error',
    action: action,
    timestamp: new Date().toISOString()
  };
}
```

### Frontend Architecture Specifications

**1. State Management with Zustand:**
```typescript
// stores/taskStore.ts
interface TaskStore {
  tasks: Task[];
  loading: boolean;
  error: string | null;

  // Actions
  fetchTasks: (department: string) => Promise<void>;
  createTask: (task: Partial<Task>) => Promise<void>;
  updateTask: (id: string, updates: Partial<Task>) => Promise<void>;
  deleteTask: (id: string) => Promise<void>;

  // Optimistic updates
  optimisticUpdate: (id: string, updates: Partial<Task>) => void;
  rollbackUpdate: (id: string, originalTask: Task) => void;
}

export const useTaskStore = create<TaskStore>((set, get) => ({
  tasks: [],
  loading: false,
  error: null,

  fetchTasks: async (department: string) => {
    set({ loading: true, error: null });
    try {
      const response = await taskAPI.getTasks(department);
      set({ tasks: response.data.data, loading: false });
    } catch (error) {
      set({ error: error.message, loading: false });
    }
  },

  createTask: async (task: Partial<Task>) => {
    const tempId = `temp-${Date.now()}`;
    const tempTask = { ...task, id: tempId } as Task;

    // Optimistic update
    set(state => ({ tasks: [...state.tasks, tempTask] }));

    try {
      const response = await taskAPI.createTask(task);
      const newTask = response.data.data;

      // Replace temp task with real task
      set(state => ({
        tasks: state.tasks.map(t => t.id === tempId ? newTask : t)
      }));
    } catch (error) {
      // Rollback optimistic update
      set(state => ({
        tasks: state.tasks.filter(t => t.id !== tempId),
        error: error.message
      }));
    }
  }
}));
```

**2. API Client with Error Handling:**
```typescript
// services/api.ts
import axios, { AxiosError } from 'axios';
import { config } from '../config/environment';

const api = axios.create({
  baseURL: config.appsScriptUrl,
  timeout: config.apiTimeout,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor for debugging
api.interceptors.request.use(
  (config) => {
    console.log(`API Request: ${config.method?.toUpperCase()} ${config.url}`);
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => {
    if (response.data && !response.data.success) {
      throw new Error(response.data.error || 'API request failed');
    }
    return response;
  },
  (error: AxiosError) => {
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
  getTasks: (department: string) =>
    api.get(`?action=getTasks&department=${encodeURIComponent(department)}`),

  createTask: (task: Partial<Task>) =>
    api.post('?action=createTask', task),

  updateTask: (task: Partial<Task>) =>
    api.post('?action=updateTask', task),

  deleteTask: (id: string) =>
    api.get(`?action=deleteTask&id=${encodeURIComponent(id)}`),

  getAvatars: () =>
    api.get('?action=getAvatars'),

  verifyLeadership: (email: string) =>
    api.get(`?action=verifyLeadership&email=${encodeURIComponent(email)}`),
};
```

**3. Component Specifications:**
```typescript
// components/shared/TaskCard.tsx
interface TaskCardProps {
  task: Task;
  department: Department;
  onUpdate: (task: Task) => void;
  onDelete: (id: string) => void;
  onAssignAvatar: (taskId: string, avatarId: string) => void;
  readOnly?: boolean; // For leadership view
}

export const TaskCard: React.FC<TaskCardProps> = ({
  task,
  department,
  onUpdate,
  onDelete,
  onAssignAvatar,
  readOnly = false
}) => {
  const [isEditing, setIsEditing] = useState(false);
  const [localTask, setLocalTask] = useState(task);
  const [loading, setLoading] = useState(false);

  const handleSave = async () => {
    if (JSON.stringify(localTask) === JSON.stringify(task)) {
      setIsEditing(false);
      return;
    }

    setLoading(true);
    try {
      await onUpdate(localTask);
      setIsEditing(false);
    } catch (error) {
      // Rollback to original task on error
      setLocalTask(task);
      console.error('Failed to save task:', error);
    } finally {
      setLoading(false);
    }
  };

  const priorityColors = {
    High: 'bg-red-100 text-red-800 border-red-200',
    Medium: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    Low: 'bg-green-100 text-green-800 border-green-200',
  };

  return (
    <div
      className={`bg-white rounded-lg shadow-md border-l-4 p-4 hover:shadow-lg transition-shadow`}
      style={{ borderLeftColor: department.color }}
    >
      {/* Task content implementation */}
      {isEditing ? (
        <TaskEditForm
          task={localTask}
          onChange={setLocalTask}
          onSave={handleSave}
          onCancel={() => {
            setLocalTask(task);
            setIsEditing(false);
          }}
          loading={loading}
        />
      ) : (
        <TaskDisplay
          task={task}
          department={department}
          onEdit={() => setIsEditing(true)}
          onDelete={() => onDelete(task.id)}
          readOnly={readOnly}
        />
      )}
    </div>
  );
};
```

### Migration Execution Checklist

**Week 1: Backend API Setup**
- [ ] Backup current Apps Script project
- [ ] Create new API routing function (`doGet`, `doPost`)
- [ ] Test `getTasks` endpoint with Postman
- [ ] Test `createTask` endpoint with Postman
- [ ] Test `updateTask` endpoint with Postman
- [ ] Test `deleteTask` endpoint with Postman
- [ ] Verify CORS headers work from localhost
- [ ] Deploy API changes to Apps Script

**Week 2: Frontend Foundation**
- [ ] Initialize Vite React TypeScript project
- [ ] Set up Tailwind CSS configuration
- [ ] Install and configure Zustand for state management
- [ ] Create base API client with error handling
- [ ] Implement department configuration system
- [ ] Create basic TaskCard component
- [ ] Set up Netlify deployment pipeline

**Week 3-4: Core Features**
- [ ] Implement task CRUD operations in frontend
- [ ] Add avatar assignment functionality
- [ ] Create department-specific routing
- [ ] Implement leadership authentication
- [ ] Add responsive design for mobile/tablet
- [ ] Integrate HubSpot sales metrics display
- [ ] Test complete workflow end-to-end

**Week 5-6: Advanced Features**
- [ ] Implement CSV import functionality
- [ ] Add Kanban board view
- [ ] Create leadership dashboard
- [ ] Add task export functionality
- [ ] Implement offline/error state handling
- [ ] Performance optimization and caching

**Week 7: Production Deployment**
- [ ] Final testing across all departments
- [ ] User acceptance testing with department leads
- [ ] Update main navigation to point to new app
- [ ] Monitor system performance and errors
- [ ] Collect user feedback and address issues
- [ ] Complete migration and archive old HTML files

---

## Next Steps

1. **Review and approve** this comprehensive migration plan
2. **Set up development environment** (Node.js 18+, Git, VS Code)
3. **Create backup** of current Apps Script project
4. **Start with API transformation** - convert first endpoint (getTasks)
5. **Set up basic React project** with TypeScript and Tailwind
6. **Deploy to Netlify staging** for early testing

**Ready to begin?** This plan provides a complete roadmap from your current Google Apps Script setup to a modern, maintainable React application while preserving all existing functionality and data.

The hybrid approach keeps your Google Sheets data intact while dramatically improving the user experience and developer productivity. Would you like me to help implement any specific part of this plan?