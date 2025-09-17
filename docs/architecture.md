# TaskMaster System Architecture

**Generated from step-by-step implementation guide analysis**
**Date:** September 16, 2024
**Version:** 1.0

---

## Executive Summary

TaskMaster is migrating from a legacy Google Apps Script HTML-based system to a modern, scalable React application. This architecture document defines the complete technology stack, component relationships, data flows, and technical implementation strategy for a 7-week migration project.

**Key Objectives:**
- 90% reduction in code duplication (from 90%+ to <5%)
- 75% performance improvement (5-8s to <2s load times)
- Modern user experience with mobile optimization
- Scalable architecture supporting 10+ departments
- Zero data loss during migration

---

## Technology Stack

### Frontend Architecture
```
┌─────────────────────────────────────────────────────────┐
│                    CLIENT LAYER                         │
├─────────────────────────────────────────────────────────┤
│ React 18+ + TypeScript                                  │
│ ├── UI Framework: Tailwind CSS                         │
│ ├── State Management: Zustand                          │
│ ├── Routing: React Router v6                           │
│ ├── Build Tool: Vite                                   │
│ └── Deployment: Netlify                                │
└─────────────────────────────────────────────────────────┘
```

### Backend Architecture
```
┌─────────────────────────────────────────────────────────┐
│                    SERVER LAYER                         │
├─────────────────────────────────────────────────────────┤
│ Google Apps Script (REST API)                          │
│ ├── API Router: doGet/doPost handlers                  │
│ ├── Business Logic: Task CRUD, Avatar, Metrics        │
│ ├── Authentication: Email-based access control        │
│ └── Security: HMAC signing, input validation          │
└─────────────────────────────────────────────────────────┘
```

### Data Layer
```
┌─────────────────────────────────────────────────────────┐
│                     DATA LAYER                          │
├─────────────────────────────────────────────────────────┤
│ Google Sheets (Primary Database)                       │
│ ├── Task Storage: Department-specific sheets          │
│ ├── Avatar System: Avatar-to-task mappings            │
│ ├── HubSpot Integration: Sales metrics API            │
│ └── Caching: Multi-layer client/server cache          │
└─────────────────────────────────────────────────────────┘
```

---

## System Architecture Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Users         │    │   React App     │    │  Google Apps    │
│   (10 Depts)    │◄──►│   (Frontend)    │◄──►│   Script API    │
│                 │    │                 │    │   (Backend)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                              │                        │
                              ▼                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                    External Services                            │
├─────────────────────────────────────────────────────────────────┤
│ • Netlify (Hosting & CDN)                                      │
│ • Google Sheets (Database)                                     │
│ • HubSpot API (Sales Metrics)                                  │
│ • Mistral AI (Advanced Features)                               │
└─────────────────────────────────────────────────────────────────┘
```

---

## Component Architecture

### Frontend Component Hierarchy

```
App
├── Router
│   ├── HomePage
│   ├── DepartmentPage
│   │   ├── DepartmentHeader
│   │   ├── TaskList / KanbanBoard
│   │   │   ├── TaskCard
│   │   │   ├── TaskCreateModal
│   │   │   └── TaskEditModal
│   │   ├── AvatarSelector
│   │   ├── CSVImportModal
│   │   └── SalesMetricsPanel (sales only)
│   └── LeadershipDashboard
├── Navigation
├── MobileNavigation
├── ErrorBoundary
└── LoadingSpinner
```

### Core Components Detail

#### 1. **TaskCard Component**
```typescript
interface TaskCardProps {
  task: Task;
  department: string;
  onEdit: (task: Task) => void;
  onDelete: (taskId: string) => void;
  onStatusChange?: (taskId: string, status: string) => void;
}
```

#### 2. **DepartmentPage Component**
```typescript
interface DepartmentPageProps {
  department: string;
  view: 'list' | 'kanban';
  features: DepartmentFeature[];
}
```

#### 3. **CSVImportModal Component**
```typescript
interface CSVImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  department: DepartmentConfig;
  onImportComplete: (results: ImportResults) => void;
}
```

#### 4. **KanbanBoard Component**
```typescript
interface KanbanBoardProps {
  department: string;
  tasks: Task[];
  onTaskMove: (taskId: string, newStatus: string) => void;
}
```

#### 5. **LeadershipDashboard Component**
```typescript
interface LeadershipDashboardProps {
  allDepartments: boolean;
  dateRange: DateRange;
  accessLevel: 'read' | 'admin';
}
```

---

## Data Flow Architecture

### 1. **Task Management Flow**
```
User Action → Component → Zustand Store → API Client → Apps Script → Google Sheets
     ▲                                                                      │
     └─────────────── Response ←── Store Update ←── API Response ←─────────┘
```

### 2. **State Management (Zustand)**
```typescript
interface TaskStore {
  // State
  tasks: Task[];
  loading: boolean;
  error: string | null;
  filters: FilterState;

  // Actions
  fetchTasks: (department: string) => Promise<void>;
  createTask: (taskData: Partial<Task>) => Promise<void>;
  updateTask: (id: string, updates: Partial<Task>) => Promise<void>;
  deleteTask: (id: string) => Promise<void>;
  setFilters: (filters: FilterState) => void;
}
```

### 3. **API Communication Layer**
```typescript
export const taskAPI = {
  // Core CRUD operations
  getTasks: (department: string) => Promise<ApiResponse<Task[]>>;
  createTask: (taskData: Partial<Task>) => Promise<ApiResponse<Task>>;
  updateTask: (id: string, data: Partial<Task>) => Promise<ApiResponse<Task>>;
  deleteTask: (id: string) => Promise<ApiResponse<void>>;

  // Advanced features
  getAvatars: () => Promise<ApiResponse<Avatar[]>>;
  assignAvatar: (taskId: string, avatarId: string) => Promise<ApiResponse<void>>;
  getSalesMetrics: (department: string) => Promise<ApiResponse<SalesMetrics>>;
  processCSVImport: (csvData: string, department: string) => Promise<ApiResponse<ImportResults>>;

  // Leadership features
  getLeadershipData: (department?: string) => Promise<ApiResponse<LeadershipData>>;
}
```

---

## Security Architecture

### 1. **Authentication & Authorization**
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   User Email    │───►│   Department    │───►│   Access Level  │
│   Validation    │    │   Verification  │    │   Assignment    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### 2. **Security Layers**
- **Input Validation:** DOMPurify sanitization, type checking
- **API Security:** HMAC request signing, rate limiting
- **Data Encryption:** Client-side encryption for sensitive data
- **CORS Protection:** Proper origin validation
- **CSP Headers:** Content Security Policy implementation

### 3. **Security Implementation**
```typescript
// Input validation
export class InputValidator {
  static sanitizeHTML(input: string): string;
  static validateTaskData(data: any): ValidationResult;
  static validateCSVData(csvData: any[]): ValidationResult;
}

// API security
export class APISecurityManager {
  checkRateLimit(identifier: string): boolean;
  signRequest(data: any, secret: string): string;
  validateCSP(): boolean;
}

// Data encryption
export class DataEncryption {
  encryptData(data: string, password: string): Promise<string>;
  decryptData(encryptedData: string, password: string): Promise<string>;
}
```

---

## Performance Architecture

### 1. **Caching Strategy**
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Memory Cache  │───►│  localStorage   │───►│   API Cache     │
│   (Immediate)   │    │   (Persistent)  │    │   (Server)      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### 2. **Optimization Techniques**
- **Code Splitting:** Lazy loading of routes and components
- **Virtual Scrolling:** Large dataset handling (1000+ tasks)
- **Request Optimization:** Deduplication, batching, retry logic
- **Memory Management:** Component cleanup, safe async operations

### 3. **Performance Monitoring**
```typescript
export const usePerformanceMonitoring = (componentName: string) => {
  const renderCount = useRef(0);
  const lastRenderTime = useRef(0);

  // Track slow renders (>16ms for 60fps)
  // Generate performance reports
  // Monitor memory usage
}
```

---

## Department Configuration Architecture

### 1. **Department-Specific Customization**
```typescript
export interface DepartmentConfig {
  id: string;
  name: string;
  fields: DepartmentField[];
  priorities: string[];
  statuses: string[];
  features: DepartmentFeature[];
  colors: DepartmentColors;
}

export const DEPARTMENT_CONFIGS: Record<string, DepartmentConfig> = {
  sales: {
    id: 'sales',
    name: 'Sales',
    fields: [
      { id: 'dealValue', label: 'Deal Value', type: 'number', required: false },
      { id: 'clientName', label: 'Client Name', type: 'text', required: true },
      { id: 'closeDate', label: 'Expected Close', type: 'date', required: false }
    ],
    features: ['hubspotIntegration', 'salesMetrics', 'dealTracking'],
    colors: { primary: '#10B981', secondary: '#059669', accent: '#ECFDF5' }
  },
  // ... other departments
};
```

### 2. **Dynamic Feature Loading**
```typescript
export const useDepartmentConfig = (): DepartmentConfig | null => {
  const { department } = useParams();
  return useMemo(() => {
    return DEPARTMENT_CONFIGS[department] || null;
  }, [department]);
};
```

---

## Mobile Architecture

### 1. **Responsive Design Strategy**
```css
/* Mobile-first approach */
.task-grid {
  display: grid;
  grid-template-columns: 1fr;        /* Mobile: 1 column */
  gap: 1rem;
  padding: 1rem;
}

@media (min-width: 768px) {
  .task-grid {
    grid-template-columns: repeat(2, 1fr);  /* Tablet: 2 columns */
    gap: 1.5rem;
    padding: 1.5rem;
  }
}

@media (min-width: 1024px) {
  .task-grid {
    grid-template-columns: repeat(3, 1fr);  /* Desktop: 3 columns */
    gap: 2rem;
    padding: 2rem;
  }
}
```

### 2. **Touch Optimization**
```typescript
export const useSwipeGesture = (
  elementRef: React.RefObject<HTMLElement>,
  options: SwipeGestureOptions
) => {
  // Implement swipe left/right for task actions
  // Touch-friendly 44px+ interactive elements
  // Mobile navigation patterns
};
```

---

## Testing Architecture

### 1. **Testing Pyramid**
```
                    ┌─────────────────┐
                    │   E2E Tests     │ ← Playwright
                    │   (Few)         │
                    └─────────────────┘
                  ┌─────────────────────┐
                  │  Integration Tests  │ ← React Testing Library
                  │     (Some)          │
                  └─────────────────────┘
              ┌─────────────────────────────┐
              │     Unit Tests              │ ← Jest + Vitest
              │      (Many)                 │
              └─────────────────────────────┘
```

### 2. **Testing Strategy**
- **Unit Tests:** Component logic, utility functions, API methods
- **Integration Tests:** Component interactions, state management
- **E2E Tests:** Complete user workflows, cross-browser compatibility
- **Security Tests:** XSS prevention, input validation, API security
- **Performance Tests:** Load times, memory usage, scalability

### 3. **Test Implementation**
```typescript
// Unit testing
describe('TaskCard Component', () => {
  test('renders task information correctly', () => {
    render(<TaskCard task={mockTask} onEdit={jest.fn()} onDelete={jest.fn()} />);
    expect(screen.getByText(mockTask.title)).toBeInTheDocument();
  });
});

// E2E testing
test('Complete CSV import workflow', async ({ page }) => {
  await page.goto('/department/sales');
  await page.click('button:text("Import CSV")');
  // ... complete workflow testing
});
```

---

## Deployment Architecture

### 1. **Build & Deployment Pipeline**
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Development   │───►│     Build       │───►│   Production    │
│   (Vite Dev)    │    │   (Vite Build)  │    │   (Netlify)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
        │                       │                       │
        ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Hot Reload      │    │ Optimization    │    │ CDN Delivery    │
│ TypeScript      │    │ Tree Shaking    │    │ HTTPS/Security  │
│ ESLint          │    │ Minification    │    │ Performance     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### 2. **Environment Configuration**
```typescript
// Environment-specific settings
interface EnvironmentConfig {
  API_URL: string;
  DEPARTMENT_LIST: string[];
  FEATURES_ENABLED: string[];
  CACHE_TTL: number;
  DEBUG_MODE: boolean;
}

export const config: EnvironmentConfig = {
  API_URL: import.meta.env.VITE_API_URL,
  DEPARTMENT_LIST: import.meta.env.VITE_DEPARTMENTS?.split(',') || [],
  FEATURES_ENABLED: import.meta.env.VITE_FEATURES?.split(',') || [],
  CACHE_TTL: parseInt(import.meta.env.VITE_CACHE_TTL || '300000'),
  DEBUG_MODE: import.meta.env.DEV
};
```

### 3. **Netlify Configuration**
```toml
# netlify.toml
[build]
  publish = "dist"
  command = "npm run build"

[build.environment]
  NODE_VERSION = "18"

[[redirects]]
  from = "/*"
  to = "/index.html"
  status = 200

[[headers]]
  for = "/*"
  [headers.values]
    X-Frame-Options = "DENY"
    X-Content-Type-Options = "nosniff"
    Referrer-Policy = "strict-origin-when-cross-origin"
```

---

## Migration Strategy

### 1. **Hybrid Deployment Approach**
```
Phase 1: API Transformation
┌─────────────────┐    ┌─────────────────┐
│  Legacy HTML    │    │  Apps Script    │
│  Interface      │◄──►│   + API Layer   │
└─────────────────┘    └─────────────────┘

Phase 2: React Rollout
┌─────────────────┐    ┌─────────────────┐
│   React App     │    │  Apps Script    │
│   (New)         │◄──►│   API Only      │
└─────────────────┘    └─────────────────┘

Phase 3: Complete Migration
┌─────────────────┐    ┌─────────────────┐
│   React App     │    │  Apps Script    │
│   (Production)  │◄──►│   API + Data    │
└─────────────────┘    └─────────────────┘
```

### 2. **Department-by-Department Rollout**
1. **Week 1-2:** Foundation & API setup
2. **Week 3:** Sales department (pilot)
3. **Week 4:** Accounting & Tech departments
4. **Week 5:** Marketing, HR, Operations
5. **Week 6:** Legal, Finance, Admin, Ideas
6. **Week 7:** Leadership portal & final migration

### 3. **Risk Mitigation**
- **Dual System Operation:** Legacy system remains available during migration
- **Gradual User Migration:** Department-by-department rollout
- **Data Validation:** Comprehensive testing at each phase
- **Rollback Procedures:** Quick reversion to legacy system if needed

---

## Success Metrics & Monitoring

### 1. **Technical Metrics**
- **Performance:** Page load time < 2 seconds
- **Reliability:** 99.9% uptime, error rate < 1%
- **Scalability:** Support 1000+ tasks per department
- **Code Quality:** Code duplication < 5%

### 2. **Business Metrics**
- **User Adoption:** 100% department migration
- **Productivity:** 90% reduction in maintenance overhead
- **User Satisfaction:** >4.0/5.0 rating
- **Feature Utilization:** CSV import, Kanban boards, leadership dashboard

### 3. **Monitoring Implementation**
```typescript
export const analyticsTracker = {
  trackPageView: (department: string) => void;
  trackFeatureUsage: (feature: string, metadata: object) => void;
  trackPerformance: (metric: string, value: number) => void;
  trackError: (error: Error, context: object) => void;
};
```

---

## Error Handling & Logging Architecture

### 1. **Error Boundary Strategy**
```typescript
interface ErrorBoundaryArchitecture {
  globalErrorBoundary: {
    component: 'App-level error boundary';
    fallbackUI: 'User-friendly error page';
    errorReporting: 'Automatic error logging';
  };
  featureErrorBoundaries: {
    csvImport: 'CSV import error handling';
    kanbanBoard: 'Kanban interaction errors';
    leadershipDashboard: 'Dashboard data errors';
  };
  apiErrorHandling: {
    networkErrors: 'Offline/connectivity handling';
    authenticationErrors: 'Login redirect handling';
    validationErrors: 'User-friendly form feedback';
  };
}

// Implementation example
export class GlobalErrorBoundary extends Component<Props, State> {
  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    // Log error to monitoring service
    errorLogger.logError({
      error: error.message,
      stack: error.stack,
      componentStack: errorInfo.componentStack,
      timestamp: new Date().toISOString(),
      userId: getCurrentUser()?.id,
      department: getCurrentDepartment(),
      userAgent: navigator.userAgent,
      url: window.location.href
    });

    // Show user-friendly error message
    this.setState({
      hasError: true,
      errorId: generateErrorId(),
      retryable: isRetryableError(error)
    });
  }
}
```

### 2. **Logging Infrastructure**
```typescript
interface LoggingStrategy {
  levels: ['error', 'warn', 'info', 'debug'];
  destinations: {
    console: 'Development logging';
    remoteService: 'Production error tracking';
    localStorage: 'Client-side debugging';
  };
  structured: {
    format: 'JSON structured logging';
    fields: ['timestamp', 'level', 'message', 'context', 'userId', 'department'];
  };
}

export class Logger {
  private static instance: Logger;
  private logLevel: LogLevel;
  private remoteEndpoint: string;

  log(level: LogLevel, message: string, context?: object) {
    const logEntry = {
      timestamp: new Date().toISOString(),
      level,
      message,
      context: {
        ...context,
        userId: getCurrentUser()?.id,
        department: getCurrentDepartment(),
        sessionId: getSessionId(),
        buildVersion: import.meta.env.VITE_BUILD_VERSION
      }
    };

    // Console logging (development)
    if (import.meta.env.DEV) {
      console[level](logEntry);
    }

    // Remote logging (production)
    if (level === 'error' || level === 'warn') {
      this.sendToRemoteService(logEntry);
    }

    // Local storage (debugging)
    this.storeLocally(logEntry);
  }

  private async sendToRemoteService(logEntry: LogEntry) {
    try {
      await fetch(this.remoteEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(logEntry)
      });
    } catch (error) {
      // Fallback to local storage if remote logging fails
      this.storeLocally({ ...logEntry, _remoteFailed: true });
    }
  }
}
```

### 3. **Error Reporting & Monitoring**
```typescript
interface ErrorReportingStrategy {
  automaticReporting: {
    jsErrors: 'Unhandled JavaScript errors';
    promiseRejections: 'Unhandled promise rejections';
    networkErrors: 'Failed API calls and timeouts';
    performanceIssues: 'Slow renders and memory leaks';
  };
  userReporting: {
    feedbackWidget: 'In-app error reporting';
    bugReportForm: 'Detailed issue reporting';
    screenshotCapture: 'Visual error context';
  };
  monitoring: {
    realTimeAlerts: 'Immediate error notifications';
    dashboards: 'Error trend visualization';
    escalation: 'Critical error escalation procedures';
  };
}

export const errorReporter = {
  reportError: (error: Error, context: ErrorContext) => {
    const report = {
      errorId: generateUniqueId(),
      timestamp: new Date().toISOString(),
      error: {
        message: error.message,
        stack: error.stack,
        name: error.name
      },
      context: {
        component: context.component,
        action: context.action,
        department: context.department,
        userId: context.userId
      },
      environment: {
        userAgent: navigator.userAgent,
        url: window.location.href,
        viewport: `${window.innerWidth}x${window.innerHeight}`,
        timestamp: performance.now()
      },
      severity: determineSeverity(error, context)
    };

    // Send to monitoring service
    sendToMonitoringService(report);

    // Store locally for offline scenarios
    storeErrorReport(report);
  }
};
```

### 4. **User Error Experience**
```typescript
interface UserErrorExperience {
  gracefulDegradation: {
    networkOffline: 'Offline mode with cached data';
    partialFailure: 'Show available content, hide failed sections';
    apiTimeout: 'Retry mechanism with user feedback';
  };
  userFeedback: {
    errorMessages: 'Clear, actionable error descriptions';
    retryActions: 'Easy retry buttons and workflows';
    helpResources: 'Links to documentation and support';
  };
  errorRecovery: {
    autoRetry: 'Automatic retry for transient errors';
    manualRetry: 'User-initiated retry mechanisms';
    fallbackOptions: 'Alternative ways to complete tasks';
  };
}
```

---

## DevOps & CI/CD Architecture

### 1. **Continuous Integration Pipeline**
```yaml
# .github/workflows/ci.yml
name: TaskMaster CI/CD Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        node-version: [18.x, 20.x]

    steps:
      - uses: actions/checkout@v4
      - name: Setup Node.js
        uses: actions/setup-node@v4
        with:
          node-version: ${{ matrix.node-version }}
          cache: 'npm'

      - name: Install dependencies
        run: npm ci

      - name: TypeScript check
        run: npm run typecheck

      - name: Lint code
        run: npm run lint

      - name: Unit tests
        run: npm run test:unit

      - name: Integration tests
        run: npm run test:integration

      - name: E2E tests
        run: npm run test:e2e

      - name: Security scan
        run: npm audit --audit-level=high

      - name: Build application
        run: npm run build

      - name: Bundle analysis
        run: npm run analyze
```

### 2. **Automated Testing Strategy**
```typescript
interface AutomatedTestingStrategy {
  unitTests: {
    coverage: '90%+ code coverage requirement';
    tools: ['Jest', 'Vitest', '@testing-library/react'];
    scope: 'Components, hooks, utilities, API methods';
  };
  integrationTests: {
    coverage: 'Feature workflow testing';
    tools: ['React Testing Library', 'MSW for API mocking'];
    scope: 'Component interactions, state management, API integration';
  };
  e2eTests: {
    coverage: 'Critical user journeys';
    tools: ['Playwright', 'cross-browser testing'];
    scope: 'Complete workflows, mobile testing, performance';
  };
  securityTests: {
    coverage: 'Vulnerability scanning';
    tools: ['npm audit', 'Snyk', 'OWASP ZAP'];
    scope: 'Dependency vulnerabilities, XSS, injection attacks';
  };
}

// Test automation configuration
export const testConfig = {
  unit: {
    threshold: {
      statements: 90,
      branches: 90,
      functions: 90,
      lines: 90
    },
    setupFiles: ['<rootDir>/src/test/setup.ts'],
    testEnvironment: 'jsdom'
  },
  e2e: {
    browsers: ['chromium', 'firefox', 'webkit'],
    baseURL: process.env.PLAYWRIGHT_BASE_URL,
    timeout: 30000,
    retries: 2
  }
};
```

### 3. **Deployment Automation**
```typescript
interface DeploymentStrategy {
  environments: {
    development: {
      trigger: 'Every commit to develop branch';
      destination: 'Netlify preview deploy';
      testing: 'Automated smoke tests';
    };
    staging: {
      trigger: 'Every commit to main branch';
      destination: 'Netlify staging environment';
      testing: 'Full E2E test suite';
    };
    production: {
      trigger: 'Manual approval after staging validation';
      destination: 'Netlify production environment';
      testing: 'Deployment verification tests';
    };
  };
  rollback: {
    automatic: 'Failed health checks trigger automatic rollback';
    manual: 'One-click rollback to previous version';
    database: 'Data migration rollback procedures';
  };
}

// Deployment configuration
export const deploymentConfig = {
  build: {
    command: 'npm run build',
    publish: 'dist',
    environment: {
      NODE_VERSION: '18',
      NPM_VERSION: '9'
    }
  },
  redirects: [
    { from: '/api/*', to: 'https://script.google.com/your-apps-script-url/:splat', status: 200 },
    { from: '/*', to: '/index.html', status: 200 }
  ],
  headers: {
    '/*': {
      'X-Frame-Options': 'DENY',
      'X-Content-Type-Options': 'nosniff',
      'Referrer-Policy': 'strict-origin-when-cross-origin',
      'Content-Security-Policy': "default-src 'self'; script-src 'self' 'unsafe-inline'"
    }
  }
};
```

### 4. **Environment Management**
```typescript
interface EnvironmentManagement {
  configuration: {
    development: {
      API_URL: 'http://localhost:3000/api';
      DEBUG_MODE: true;
      CACHE_TTL: 60000;
    };
    staging: {
      API_URL: 'https://staging-api.taskmaster.com';
      DEBUG_MODE: false;
      CACHE_TTL: 300000;
    };
    production: {
      API_URL: 'https://api.taskmaster.com';
      DEBUG_MODE: false;
      CACHE_TTL: 600000;
    };
  };
  secrets: {
    management: 'Netlify environment variables';
    rotation: 'Automated secret rotation procedures';
    access: 'Role-based secret access control';
  };
}
```

---

## Backup & Disaster Recovery Architecture

### 1. **Data Backup Strategy**
```typescript
interface DataBackupStrategy {
  googleSheets: {
    frequency: 'Daily automated backups';
    retention: '30 days rolling retention';
    format: 'JSON export + native Google Sheets backup';
    verification: 'Automated backup integrity checks';
  };
  userGeneratedContent: {
    tasks: 'Real-time backup on creation/modification';
    attachments: 'Incremental backup to cloud storage';
    configurations: 'Department settings backup';
  };
  systemData: {
    avatars: 'Avatar mappings and metadata';
    analytics: 'User behavior and performance metrics';
    logs: 'Error logs and audit trails';
  };
}

// Backup implementation
export class BackupService {
  private scheduleDaily() {
    // Daily full backup
    cron.schedule('0 2 * * *', async () => {
      await this.performFullBackup();
    });
  }

  private async performFullBackup() {
    const backupId = `backup-${new Date().toISOString()}`;

    try {
      // Backup all Google Sheets data
      const sheetsData = await this.exportAllSheets();

      // Backup configuration data
      const configData = await this.exportConfigurations();

      // Store backup
      await this.storeBackup(backupId, {
        sheets: sheetsData,
        configurations: configData,
        timestamp: new Date().toISOString(),
        version: getCurrentVersion()
      });

      // Verify backup integrity
      await this.verifyBackup(backupId);

      // Clean up old backups
      await this.cleanupOldBackups();

    } catch (error) {
      logger.error('Backup failed', { backupId, error });
      await this.notifyBackupFailure(error);
    }
  }
}
```

### 2. **Code Repository Management**
```typescript
interface CodeRepositoryStrategy {
  versionControl: {
    primary: 'Git with GitHub hosting';
    branching: 'GitFlow workflow with feature branches';
    tags: 'Semantic versioning for releases';
  };
  redundancy: {
    mirrors: 'Multiple repository mirrors';
    archives: 'Periodic repository archives';
    documentation: 'Comprehensive documentation backup';
  };
  recovery: {
    quickRestore: 'Automated environment restoration';
    dependencies: 'Package.json and lock file management';
    infrastructure: 'Infrastructure as Code (IaC) backup';
  };
}
```

### 3. **Configuration Backup**
```typescript
interface ConfigurationBackupStrategy {
  applicationConfig: {
    environment: 'Environment variable backups';
    deployment: 'Netlify configuration backup';
    build: 'Build configuration versioning';
  };
  integrations: {
    googleAppsScript: 'Apps Script code and configuration';
    hubspot: 'API configuration and mappings';
    mistralAI: 'AI service configuration';
  };
  userSettings: {
    departments: 'Department configuration backup';
    permissions: 'User access control settings';
    customizations: 'User interface customizations';
  };
}
```

### 4. **Disaster Recovery Procedures**
```typescript
interface DisasterRecoveryStrategy {
  scenarios: {
    totalOutage: {
      rto: '2 hours - Recovery Time Objective';
      rpo: '15 minutes - Recovery Point Objective';
      procedure: 'Full system restoration from backups';
    };
    dataCorruption: {
      rto: '30 minutes';
      rpo: '5 minutes';
      procedure: 'Point-in-time data restoration';
    };
    securityBreach: {
      rto: '1 hour';
      rpo: '0 minutes';
      procedure: 'Secure environment restoration';
    };
  };
  communication: {
    statusPage: 'Public incident status updates';
    userNotifications: 'In-app and email notifications';
    stakeholders: 'Executive and team communications';
  };
  testing: {
    frequency: 'Quarterly disaster recovery drills';
    scenarios: 'Multiple failure scenario testing';
    documentation: 'Updated recovery procedures';
  };
}

// Disaster recovery implementation
export class DisasterRecoveryService {
  async initiateRecovery(scenario: DisasterScenario) {
    const recoveryId = `recovery-${Date.now()}`;

    // Step 1: Assess situation and notify stakeholders
    await this.assessDamage(scenario);
    await this.notifyStakeholders(scenario, recoveryId);

    // Step 2: Isolate affected systems
    await this.isolateAffectedSystems(scenario);

    // Step 3: Begin recovery procedure
    const recoveryPlan = this.getRecoveryPlan(scenario);
    await this.executeRecoveryPlan(recoveryPlan, recoveryId);

    // Step 4: Verify system integrity
    await this.verifySystemIntegrity();

    // Step 5: Resume normal operations
    await this.resumeOperations();

    // Step 6: Post-incident review
    await this.conductPostIncidentReview(recoveryId);
  }
}
```

---

## Observability & Analytics Architecture

### 1. **Application Performance Monitoring (APM)**
```typescript
interface APMStrategy {
  metrics: {
    performance: {
      pageLoadTime: 'Core Web Vitals monitoring';
      apiLatency: 'API response time tracking';
      renderTime: 'Component render performance';
      memoryUsage: 'Memory leak detection';
    };
    reliability: {
      errorRate: 'Application error percentage';
      availability: 'System uptime monitoring';
      crashRate: 'Application crash frequency';
    };
    usage: {
      activeUsers: 'Real-time user count';
      featureAdoption: 'Feature usage analytics';
      userFlows: 'User journey tracking';
    };
  };
  alerting: {
    performance: 'Page load time > 3 seconds';
    errors: 'Error rate > 1%';
    availability: 'Downtime > 1 minute';
  };
}

// APM implementation
export class APMService {
  private metrics: Map<string, MetricBuffer> = new Map();

  trackPerformance(metric: string, value: number, tags?: Record<string, string>) {
    const dataPoint = {
      metric,
      value,
      timestamp: Date.now(),
      tags: {
        ...tags,
        department: getCurrentDepartment(),
        userId: getCurrentUser()?.id,
        version: getAppVersion()
      }
    };

    // Buffer metrics for batch sending
    this.bufferMetric(dataPoint);

    // Check for immediate alerts
    this.checkAlerts(metric, value);
  }

  trackUserInteraction(action: string, element: string, context?: object) {
    this.trackPerformance('user_interaction', 1, {
      action,
      element,
      page: getCurrentPage(),
      ...context
    });
  }

  private checkAlerts(metric: string, value: number) {
    const alert = this.alertRules.get(metric);
    if (alert && value > alert.threshold) {
      this.sendAlert({
        metric,
        value,
        threshold: alert.threshold,
        severity: alert.severity,
        timestamp: new Date().toISOString()
      });
    }
  }
}
```

### 2. **User Analytics & Behavior Tracking**
```typescript
interface UserAnalyticsStrategy {
  behavioral: {
    pageViews: 'Page navigation tracking';
    clickTracking: 'User interaction heatmaps';
    timeOnPage: 'Page engagement metrics';
    userFlows: 'Multi-step process completion';
  };
  feature: {
    csvImport: 'Import usage patterns and success rates';
    kanbanUsage: 'Drag-and-drop interaction frequency';
    leadershipDashboard: 'Dashboard view patterns and time spent';
    mobileUsage: 'Mobile vs desktop usage patterns';
  };
  conversion: {
    taskCreation: 'Task creation funnel analysis';
    taskCompletion: 'Task completion rates by department';
    userOnboarding: 'New user activation metrics';
  };
}

export class UserAnalytics {
  trackEvent(eventName: string, properties?: Record<string, any>) {
    const event = {
      eventName,
      properties: {
        ...properties,
        timestamp: Date.now(),
        sessionId: getSessionId(),
        userId: getCurrentUser()?.id,
        department: getCurrentDepartment(),
        userAgent: navigator.userAgent,
        referrer: document.referrer,
        viewport: `${window.innerWidth}x${window.innerHeight}`
      }
    };

    // Send to analytics service
    this.sendEvent(event);

    // Store locally for offline scenarios
    this.storeEventLocally(event);
  }

  trackPageView(page: string, title?: string) {
    this.trackEvent('page_view', {
      page,
      title: title || document.title,
      url: window.location.href,
      loadTime: performance.timing.loadEventEnd - performance.timing.navigationStart
    });
  }

  trackFeatureUsage(feature: string, action: string, success: boolean, metadata?: object) {
    this.trackEvent('feature_usage', {
      feature,
      action,
      success,
      ...metadata
    });
  }
}
```

### 3. **Business Metrics Dashboard**
```typescript
interface BusinessMetricsStrategy {
  productivity: {
    tasksPerUser: 'Average tasks created/completed per user';
    completionRate: 'Task completion percentage by department';
    timeToComplete: 'Average time from creation to completion';
  };
  adoption: {
    activeUsers: 'Daily/Weekly/Monthly active users';
    featureAdoption: 'New feature uptake rates';
    mobileUsage: 'Mobile vs desktop usage split';
  };
  efficiency: {
    loadTimes: 'Application performance metrics';
    errorRates: 'User-facing error frequencies';
    supportTickets: 'Help desk ticket correlation';
  };
}

export class BusinessMetrics {
  generateDashboard() {
    return {
      realTime: this.getRealTimeMetrics(),
      daily: this.getDailyMetrics(),
      weekly: this.getWeeklyMetrics(),
      monthly: this.getMonthlyMetrics()
    };
  }

  private getRealTimeMetrics() {
    return {
      activeUsers: this.getActiveUserCount(),
      tasksInProgress: this.getTasksInProgress(),
      systemHealth: this.getSystemHealthStatus(),
      apiLatency: this.getCurrentAPILatency()
    };
  }
}
```

### 4. **Alerting & Notification Strategy**
```typescript
interface AlertingStrategy {
  triggers: {
    performance: 'Response time > 2s for 5 minutes';
    errors: 'Error rate > 1% for 2 minutes';
    availability: 'Service unavailable for > 30 seconds';
    security: 'Suspicious activity patterns detected';
  };
  channels: {
    immediate: 'SMS/Slack for critical alerts';
    urgent: 'Email for high priority alerts';
    informational: 'Dashboard notifications for trends';
  };
  escalation: {
    level1: 'Development team notification';
    level2: 'Technical lead escalation';
    level3: 'Management notification';
  };
}
```

---

## Infrastructure & Networking Architecture

### 1. **Network Topology**
```typescript
interface NetworkTopology {
  clientToNetlify: {
    protocol: 'HTTPS/2';
    security: 'TLS 1.3 encryption';
    cdn: 'Global CDN edge locations';
    caching: 'Static asset caching at edge';
  };
  netlifyToAppsScript: {
    protocol: 'HTTPS REST API';
    authentication: 'Bearer token + HMAC signing';
    rateLimiting: '100 requests/minute per user';
    retries: 'Exponential backoff retry logic';
  };
  appsScriptToSheets: {
    protocol: 'Google Sheets API v4';
    authentication: 'Service account credentials';
    batching: 'Batch operations for efficiency';
    caching: 'Server-side result caching';
  };
}
```

### 2. **DNS & CDN Configuration**
```typescript
interface DNSConfiguration {
  primary: {
    domain: 'taskmaster.company.com';
    provider: 'Netlify DNS management';
    records: {
      A: 'Netlify load balancer IPs';
      AAAA: 'IPv6 support';
      CNAME: 'www subdomain redirect';
    };
  };
  cdn: {
    provider: 'Netlify Edge Network';
    locations: 'Global edge locations';
    caching: {
      static: '1 year cache for assets';
      api: '5 minute cache for API responses';
      html: 'No cache for dynamic content';
    };
  };
  monitoring: {
    dnsHealth: 'DNS resolution monitoring';
    cdnPerformance: 'Edge location performance';
    sslCertificate: 'Certificate expiry monitoring';
  };
}
```

### 3. **SSL/TLS Management**
```typescript
interface SSLTLSManagement {
  certificates: {
    provider: 'Let\'s Encrypt via Netlify';
    automation: 'Automatic renewal';
    protocols: ['TLS 1.2', 'TLS 1.3'];
    cipherSuites: 'Modern cipher suite selection';
  };
  security: {
    hsts: 'HTTP Strict Transport Security';
    hpkp: 'HTTP Public Key Pinning';
    ocsp: 'OCSP stapling for performance';
  };
  monitoring: {
    expiry: 'Certificate expiration alerts';
    validation: 'SSL configuration validation';
    performance: 'TLS handshake performance';
  };
}
```

### 4. **Infrastructure Monitoring**
```typescript
interface InfrastructureMonitoring {
  availability: {
    uptime: 'End-to-end availability monitoring';
    healthChecks: 'Application health endpoints';
    synthetic: 'Synthetic transaction monitoring';
  };
  performance: {
    latency: 'Network latency monitoring';
    throughput: 'Request throughput tracking';
    capacity: 'Infrastructure capacity planning';
  };
  security: {
    ddos: 'DDoS attack detection';
    anomalies: 'Traffic pattern anomaly detection';
    compliance: 'Security compliance monitoring';
  };
}
```

---

## Accessibility Architecture

### 1. **WCAG 2.1 Compliance Strategy**
```typescript
interface AccessibilityCompliance {
  level: 'WCAG 2.1 AA compliance';
  guidelines: {
    perceivable: {
      textAlternatives: 'Alt text for all images and icons';
      colorContrast: 'Minimum 4.5:1 contrast ratio';
      textSpacing: 'Adequate line height and spacing';
      resizeText: 'Support up to 200% zoom';
    };
    operable: {
      keyboardAccessible: 'Full keyboard navigation';
      seizures: 'No content causing seizures';
      navigable: 'Clear navigation structure';
      inputMethods: 'Multiple input method support';
    };
    understandable: {
      readable: 'Clear language and instructions';
      predictable: 'Consistent navigation patterns';
      inputAssistance: 'Clear error messages and help';
    };
    robust: {
      compatible: 'Assistive technology compatibility';
      futureProof: 'Forward-compatible markup';
    };
  };
}

// Accessibility implementation
export class AccessibilityService {
  validateCompliance() {
    return {
      colorContrast: this.checkColorContrast(),
      keyboardNavigation: this.testKeyboardNavigation(),
      screenReader: this.validateScreenReaderSupport(),
      focusManagement: this.testFocusManagement()
    };
  }

  private checkColorContrast() {
    // Automated color contrast validation
    const elements = document.querySelectorAll('*');
    const violations = [];

    elements.forEach(element => {
      const styles = getComputedStyle(element);
      const contrast = this.calculateContrast(
        styles.color,
        styles.backgroundColor
      );

      if (contrast < 4.5) {
        violations.push({
          element: element.tagName,
          contrast,
          required: 4.5
        });
      }
    });

    return violations;
  }
}
```

### 2. **Screen Reader Support**
```typescript
interface ScreenReaderSupport {
  aria: {
    labels: 'Comprehensive ARIA labeling';
    roles: 'Semantic role definitions';
    states: 'Dynamic state announcements';
    descriptions: 'Detailed element descriptions';
  };
  semanticHTML: {
    headings: 'Proper heading hierarchy (h1-h6)';
    landmarks: 'Navigation, main, aside landmarks';
    lists: 'Semantic list structures';
    forms: 'Properly labeled form controls';
  };
  announcements: {
    liveRegions: 'Dynamic content announcements';
    statusUpdates: 'Operation status announcements';
    errorMessages: 'Clear error announcements';
  };
}

// Screen reader implementation
export const useScreenReaderAnnouncement = () => {
  const announce = useCallback((message: string, priority: 'polite' | 'assertive' = 'polite') => {
    const announcement = document.createElement('div');
    announcement.setAttribute('aria-live', priority);
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

### 3. **Keyboard Navigation**
```typescript
interface KeyboardAccessibility {
  navigation: {
    tabOrder: 'Logical tab order throughout application';
    skipLinks: 'Skip to main content links';
    shortcuts: 'Keyboard shortcuts for common actions';
  };
  interactions: {
    buttons: 'Enter and Space key activation';
    dropdowns: 'Arrow key navigation';
    modals: 'Escape key dismissal';
    drag: 'Keyboard alternative for drag-and-drop';
  };
  focus: {
    visible: 'Clear focus indicators';
    management: 'Proper focus management in SPAs';
    trapping: 'Focus trapping in modals';
  };
}

// Keyboard navigation implementation
export const useKeyboardNavigation = (items: string[], onSelect: (item: string) => void) => {
  const [selectedIndex, setSelectedIndex] = useState(0);

  useEffect(() => {
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
        case ' ':
          e.preventDefault();
          onSelect(items[selectedIndex]);
          break;
        case 'Escape':
          e.preventDefault();
          setSelectedIndex(0);
          break;
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [items, selectedIndex, onSelect]);

  return selectedIndex;
};
```

### 4. **Color & Contrast Accessibility**
```typescript
interface ColorAccessibility {
  contrast: {
    normal: 'Minimum 4.5:1 ratio for normal text';
    large: 'Minimum 3:1 ratio for large text';
    nonText: 'Minimum 3:1 ratio for UI components';
  };
  colorBlindness: {
    testing: 'Protanopia, Deuteranopia, Tritanopia testing';
    indicators: 'Non-color status indicators';
    patterns: 'Pattern and texture alternatives';
  };
  darkMode: {
    support: 'System preference detection';
    toggle: 'Manual dark mode toggle';
    contrast: 'Maintained contrast in dark mode';
  };
}

// Color accessibility implementation
export const useColorAccessibility = () => {
  const [colorScheme, setColorScheme] = useState<'light' | 'dark'>('light');

  useEffect(() => {
    // Detect system preference
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    setColorScheme(mediaQuery.matches ? 'dark' : 'light');

    const handleChange = (e: MediaQueryListEvent) => {
      setColorScheme(e.matches ? 'dark' : 'light');
    };

    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, []);

  const toggleColorScheme = () => {
    setColorScheme(prev => prev === 'light' ? 'dark' : 'light');
  };

  return { colorScheme, toggleColorScheme };
};
```

---

## Future Architecture Considerations

### 1. **Scalability Enhancements**
- **Database Migration:** Potential move from Google Sheets to proper database
- **Microservices:** Break down monolithic Apps Script into focused services
- **Real-time Features:** WebSocket implementation for live updates
- **Mobile App:** React Native implementation using shared components

### 2. **Advanced Features**
- **AI Integration:** Enhanced Mistral AI features for task automation
- **Advanced Analytics:** Custom dashboard and reporting system
- **Third-party Integrations:** Additional CRM and productivity tool connections
- **Workflow Automation:** Advanced task routing and approval processes

### 3. **Technology Evolution**
- **Framework Updates:** React 19+ features and concurrent rendering
- **Build Optimization:** Advanced bundling and deployment strategies
- **Performance Monitoring:** Real-time performance tracking and optimization
- **Security Enhancements:** Advanced threat detection and prevention

---

## Conclusion

This architecture provides a comprehensive foundation for migrating TaskMaster from a legacy Google Apps Script system to a modern, scalable React application. The design emphasizes:

- **Maintainability:** Single source of truth, component-based architecture
- **Performance:** Optimized loading, caching, and responsive design
- **Security:** Multiple layers of protection and validation
- **Scalability:** Department-specific customization and growth support
- **User Experience:** Modern interface with mobile optimization

The 7-week implementation plan provides a structured approach to achieving these architectural goals while minimizing risk and ensuring business continuity.

**Total Implementation Effort:** 28 working days
**Expected Outcomes:** 90% maintenance reduction, 75% performance improvement, modern user experience