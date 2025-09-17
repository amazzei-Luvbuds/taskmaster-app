## 🏗️ Architect Mode Activated

I'm now Winston, the Architect. I'll create the Frontend Architecture Document for the Jarvis AI Assistant, focusing on the chat interface, voice integration, and responsive design within the Google Apps Script constraints.

# Jarvis AI Assistant Frontend Architecture Document

## Template and Framework Selection

### Change Log
| Date | Version | Description | Author |
|------|---------|-------------|--------|
| [Today's Date] | 1.0 | Initial Frontend Architecture | Winston |

**Starter Template Decision:** N/A - Building from scratch within Google Apps Script HTML Service constraints. No external framework starter templates will be used due to the Apps Script environment limitations.

## Frontend Tech Stack

### Technology Stack Table

| Category | Technology | Version | Purpose | Rationale |
|----------|------------|---------|---------|-----------|
| Framework | Vanilla JS | ES6+ | Core interactivity | Apps Script HTML Service constraints |
| UI Library | Material Design Lite | 1.3.0 | Component styling | Google design consistency, lightweight |
| State Management | Custom Store Pattern | N/A | Client state management | Minimal overhead for Apps Script |
| Routing | N/A | N/A | Single-page app | No routing needed |
| Build Tool | None | N/A | Direct serving | Apps Script handles serving |
| Styling | CSS3 + MDL | Current | Visual design | Material Design consistency |
| Testing | Manual + Console | N/A | UI validation | Apps Script limitations |
| Component Library | Material Design Lite | 1.3.0 | UI components | Pre-built Material components |
| Form Handling | Native HTML5 | Current | Input validation | Built-in browser support |
| Animation | CSS Transitions | CSS3 | Micro-interactions | Performance-optimized |
| Dev Tools | Chrome DevTools | Current | Debugging | Standard web debugging |

## Project Structure

```plaintext
HTML Files (served via HtmlService):
├── Index.html                 # Main application shell
├── Login.html                 # Authentication page
├── Dashboard.html             # Dashboard view
├── Chat.html                  # Chat interface component
├── Components/
│   ├── AppCss.html           # Global styles and themes
│   ├── AppJs.html            # Core JavaScript logic
│   ├── VoiceRecorder.html   # Voice recording component
│   ├── MessageList.html     # Chat message display
│   ├── InputBar.html        # Message input component
│   ├── AgentCard.html       # Agent response visualization
│   └── QuickActions.html    # Quick action buttons
└── Includes/
    ├── MaterialIcons.html    # Icon fonts
    └── Analytics.html        # Usage tracking (optional)
```

## Component Standards

### Component Template

```javascript
// Component pattern for Apps Script HTML Service
class JarvisComponent {
  constructor(container, options = {}) {
    this.container = typeof container === 'string' 
      ? document.querySelector(container) 
      : container;
    this.options = { ...this.defaultOptions(), ...options };
    this.state = {};
    this.init();
  }
  
  defaultOptions() {
    return {
      theme: 'light',
      animate: true
    };
  }
  
  init() {
    this.render();
    this.attachEventListeners();
  }
  
  render() {
    this.container.innerHTML = this.template();
    this.afterRender();
  }
  
  template() {
    return `<div class="jarvis-component"></div>`;
  }
  
  afterRender() {
    // MDL component upgrade
    if (typeof componentHandler !== 'undefined') {
      componentHandler.upgradeElements(this.container);
    }
  }
  
  attachEventListeners() {
    // Event delegation for dynamic content
    this.container.addEventListener('click', this.handleClick.bind(this));
  }
  
  handleClick(e) {
    // Handle events
  }
  
  setState(newState) {
    this.state = { ...this.state, ...newState };
    this.render();
  }
  
  destroy() {
    this.container.removeEventListener('click', this.handleClick);
    this.container.innerHTML = '';
  }
}
```

### Naming Conventions
- **Components:** PascalCase (e.g., `ChatInterface`, `VoiceRecorder`)
- **Files:** PascalCase.html (e.g., `Chat.html`, `Dashboard.html`)
- **CSS Classes:** BEM methodology (e.g., `jarvis-chat__message--sent`)
- **JavaScript Functions:** camelCase (e.g., `sendMessage`, `handleVoiceInput`)
- **Constants:** UPPER_SNAKE_CASE (e.g., `MAX_MESSAGE_LENGTH`)

## State Management

### Store Structure

```plaintext
ClientState/
├── user/
│   ├── profile (email, name, preferences)
│   └── session (sessionId, authenticated)
├── chat/
│   ├── messages[] (id, text, sender, timestamp, status)
│   ├── context (conversationId, lastMessageId)
│   └── typing (isTyping, typingMessage)
├── ui/
│   ├── theme (light/dark)
│   ├── view (chat/dashboard)
│   └── loading (global, component-specific)
├── voice/
│   ├── recording (isRecording, duration)
│   └── transcription (text, confidence)
└── cache/
    ├── calendar (events, lastFetch)
    └── emails (recent, unread)
```

### State Management Template

```javascript
// StateManager.js - Simple state management for Apps Script frontend
class StateManager {
  constructor() {
    this.state = {
      user: {
        profile: null,
        session: null
      },
      chat: {
        messages: [],
        context: {},
        typing: false
      },
      ui: {
        theme: 'light',
        view: 'chat',
        loading: {}
      },
      voice: {
        recording: false,
        transcription: null
      },
      cache: {}
    };
    
    this.subscribers = [];
    this.middleware = [];
  }
  
  getState() {
    return { ...this.state };
  }
  
  setState(path, value) {
    const keys = path.split('.');
    let current = this.state;
    
    for (let i = 0; i < keys.length - 1; i++) {
      current = current[keys[i]];
    }
    
    const oldValue = current[keys[keys.length - 1]];
    current[keys[keys.length - 1]] = value;
    
    // Middleware processing
    this.middleware.forEach(fn => fn(path, value, oldValue));
    
    // Notify subscribers
    this.notify(path, value, oldValue);
  }
  
  subscribe(callback) {
    this.subscribers.push(callback);
    return () => {
      this.subscribers = this.subscribers.filter(cb => cb !== callback);
    };
  }
  
  notify(path, value, oldValue) {
    this.subscribers.forEach(callback => {
      callback({ path, value, oldValue });
    });
  }
  
  use(middleware) {
    this.middleware.push(middleware);
  }
  
  // Persistence to sessionStorage
  persist() {
    const persistable = {
      user: this.state.user,
      ui: { theme: this.state.ui.theme }
    };
    sessionStorage.setItem('jarvis_state', JSON.stringify(persistable));
  }
  
  restore() {
    const saved = sessionStorage.getItem('jarvis_state');
    if (saved) {
      const data = JSON.parse(saved);
      Object.assign(this.state, data);
    }
  }
}

// Global instance
window.JarvisState = new StateManager();
```

## API Integration

### Service Template

```javascript
// ApiService.js - Google Apps Script backend integration
class ApiService {
  constructor() {
    this.pendingRequests = new Map();
  }
  
  async call(functionName, ...args) {
    const requestId = this.generateRequestId();
    
    return new Promise((resolve, reject) => {
      // Show loading state
      JarvisState.setState(`ui.loading.${functionName}`, true);
      
      // Store pending request for potential cancellation
      this.pendingRequests.set(requestId, { resolve, reject });
      
      // Call Apps Script backend
      google.script.run
        .withSuccessHandler((result) => {
          JarvisState.setState(`ui.loading.${functionName}`, false);
          this.pendingRequests.delete(requestId);
          resolve(result);
        })
        .withFailureHandler((error) => {
          JarvisState.setState(`ui.loading.${functionName}`, false);
          this.pendingRequests.delete(requestId);
          this.handleError(error);
          reject(error);
        })[functionName](...args);
    });
  }
  
  handleError(error) {
    console.error('API Error:', error);
    
    // User-friendly error messages
    const errorMessages = {
      'ScriptError': 'A system error occurred. Please try again.',
      'NetworkError': 'Connection lost. Please check your internet.',
      'AuthError': 'Session expired. Please log in again.',
      'QuotaError': 'Service limit reached. Please wait a moment.'
    };
    
    const message = errorMessages[error.name] || error.message || 'An unexpected error occurred.';
    
    // Show error notification
    this.showNotification(message, 'error');
  }
  
  showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `mdl-snackbar mdl-snackbar--active jarvis-notification--${type}`;
    notification.innerHTML = `
      <div class="mdl-snackbar__text">${message}</div>
      <button class="mdl-snackbar__action" onclick="this.parentElement.remove()">Dismiss</button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
      notification.remove();
    }, 5000);
  }
  
  generateRequestId() {
    return `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
  }
}

// Global instance
window.JarvisApi = new ApiService();
```

### API Client Configuration

```javascript
// ApiClient.js - Configuration and interceptors
class ApiClient {
  constructor() {
    this.config = {
      timeout: 30000, // 30 seconds
      retryAttempts: 3,
      retryDelay: 1000
    };
    
    this.interceptors = {
      request: [],
      response: [],
      error: []
    };
    
    this.setupDefaultInterceptors();
  }
  
  setupDefaultInterceptors() {
    // Request interceptor - Add auth token
    this.interceptors.request.push((config) => {
      const session = JarvisState.getState().user.session;
      if (session) {
        config.headers = { ...config.headers, 'X-Session-Id': session.id };
      }
      return config;
    });
    
    // Response interceptor - Handle common patterns
    this.interceptors.response.push((response) => {
      if (response.success === false && response.errorCode === 'JARVIS-003') {
        // Authentication error - redirect to login
        window.location.href = '/login';
      }
      return response;
    });
    
    // Error interceptor - Retry logic
    this.interceptors.error.push(async (error, attempt = 1) => {
      if (attempt < this.config.retryAttempts && this.isRetryable(error)) {
        await this.delay(this.config.retryDelay * attempt);
        return this.retry(error.request, attempt + 1);
      }
      throw error;
    });
  }
  
  isRetryable(error) {
    return error.code === 'NetworkError' || error.code === 'Timeout';
  }
  
  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
  
  async execute(request) {
    // Apply request interceptors
    for (const interceptor of this.interceptors.request) {
      request = await interceptor(request);
    }
    
    try {
      let response = await this.sendRequest(request);
      
      // Apply response interceptors
      for (const interceptor of this.interceptors.response) {
        response = await interceptor(response);
      }
      
      return response;
    } catch (error) {
      // Apply error interceptors
      for (const interceptor of this.interceptors.error) {
        try {
          return await interceptor(error);
        } catch (e) {
          error = e;
        }
      }
      throw error;
    }
  }
  
  sendRequest(request) {
    return JarvisApi.call(request.function, ...request.args);
  }
}
```

## Routing

### Route Configuration

```javascript
// Since Apps Script serves single pages, we use a view manager instead of traditional routing
class ViewManager {
  constructor() {
    this.views = {
      login: {
        template: 'Login.html',
        requiresAuth: false,
        init: () => this.initLogin()
      },
      dashboard: {
        template: 'Dashboard.html',
        requiresAuth: true,
        init: () => this.initDashboard()
      },
      chat: {
        template: 'Chat.html',
        requiresAuth: true,
        init: () => this.initChat()
      }
    };
    
    this.currentView = null;
    this.container = document.getElementById('app-container');
  }
  
  async navigateTo(viewName) {
    const view = this.views[viewName];
    
    if (!view) {
      console.error(`View ${viewName} not found`);
      return;
    }
    
    // Check authentication
    if (view.requiresAuth && !this.isAuthenticated()) {
      this.navigateTo('login');
      return;
    }
    
    // Load view template
    JarvisState.setState('ui.loading.view', true);
    
    try {
      const html = await this.loadTemplate(view.template);
      this.container.innerHTML = html;
      
      // Initialize view
      if (view.init) {
        await view.init();
      }
      
      // Update MDL components
      if (typeof componentHandler !== 'undefined') {
        componentHandler.upgradeElements(this.container);
      }
      
      this.currentView = viewName;
      JarvisState.setState('ui.view', viewName);
      
    } catch (error) {
      console.error('Error loading view:', error);
      this.showError('Failed to load view');
    } finally {
      JarvisState.setState('ui.loading.view', false);
    }
  }
  
  async loadTemplate(templateName) {
    // In Apps Script, templates are included at build time
    // This would be replaced with actual template loading
    return google.script.run
      .withSuccessHandler(html => html)
      .withFailureHandler(error => {
        throw error;
      })
      .getTemplate(templateName);
  }
  
  isAuthenticated() {
    const state = JarvisState.getState();
    return state.user.session && state.user.session.authenticated;
  }
  
  initLogin() {
    // Initialize login view
    document.getElementById('login-btn')?.addEventListener('click', () => {
      this.handleLogin();
    });
  }
  
  initDashboard() {
    // Initialize dashboard components
    new DashboardWidget('#calendar-widget');
    new DashboardWidget('#email-widget');
    new DashboardWidget('#tasks-widget');
  }
  
  initChat() {
    // Initialize chat interface
    window.chatInterface = new ChatInterface('#chat-container');
    window.voiceRecorder = new VoiceRecorder('#voice-button');
  }
  
  handleLogin() {
    google.script.run
      .withSuccessHandler((user) => {
        JarvisState.setState('user.profile', user);
        JarvisState.setState('user.session', {
          id: user.sessionId,
          authenticated: true
        });
        this.navigateTo('dashboard');
      })
      .withFailureHandler((error) => {
        this.showError('Login failed. Please try again.');
      })
      .authenticate();
  }
  
  showError(message) {
    JarvisApi.showNotification(message, 'error');
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  window.viewManager = new ViewManager();
  
  // Check authentication status and navigate
  google.script.run
    .withSuccessHandler((user) => {
      if (user) {
        JarvisState.setState('user.profile', user);
        JarvisState.setState('user.session', {
          id: user.sessionId,
          authenticated: true
        });
        viewManager.navigateTo('dashboard');
      } else {
        viewManager.navigateTo('login');
      }
    })
    .withFailureHandler(() => {
      viewManager.navigateTo('login');
    })
    .getCurrentUser();
});
```

## Styling Guidelines

### Styling Approach
The application uses Material Design Lite (MDL) for consistent Google-style components, enhanced with custom CSS for Jarvis-specific elements. CSS custom properties enable theming and maintain consistency across all components.

### Global Theme Variables

```css
/* Global theme variables in AppCss.html */
:root {
  /* Colors - Material Design with Jarvis accent */
  --jarvis-primary: #1976D2;        /* Blue 700 */
  --jarvis-primary-dark: #0D47A1;   /* Blue 900 */
  --jarvis-primary-light: #42A5F5;  /* Blue 400 */
  --jarvis-accent: #00BCD4;         /* Cyan 500 */
  --jarvis-success: #4CAF50;        /* Green 500 */
  --jarvis-warning: #FF9800;        /* Orange 500 */
  --jarvis-error: #F44336;          /* Red 500 */
  --jarvis-info: #2196F3;           /* Blue 500 */
  
  /* Neutral colors */
  --jarvis-text-primary: rgba(0, 0, 0, 0.87);
  --jarvis-text-secondary: rgba(0, 0, 0, 0.54);
  --jarvis-text-disabled: rgba(0, 0, 0, 0.38);
  --jarvis-divider: rgba(0, 0, 0, 0.12);
  --jarvis-background: #FAFAFA;
  --jarvis-surface: #FFFFFF;
  
  /* Spacing - 8px grid system */
  --jarvis-spacing-xs: 4px;
  --jarvis-spacing-sm: 8px;
  --jarvis-spacing-md: 16px;
  --jarvis-spacing-lg: 24px;
  --jarvis-spacing-xl: 32px;
  --jarvis-spacing-xxl: 48px;
  
  /* Typography */
  --jarvis-font-family: 'Roboto', 'Helvetica', 'Arial', sans-serif;
  --jarvis-font-size-xs: 12px;
  --jarvis-font-size-sm: 14px;
  --jarvis-font-size-base: 16px;
  --jarvis-font-size-lg: 18px;
  --jarvis-font-size-xl: 24px;
  --jarvis-font-size-xxl: 32px;
  
  /* Shadows - Material elevation */
  --jarvis-shadow-1: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
  --jarvis-shadow-2: 0 3px 6px rgba(0,0,0,0.16), 0 3px 6px rgba(0,0,0,0.23);
  --jarvis-shadow-3: 0 10px 20px rgba(0,0,0,0.19), 0 6px 6px rgba(0,0,0,0.23);
  --jarvis-shadow-4: 0 14px 28px rgba(0,0,0,0.25), 0 10px 10px rgba(0,0,0,0.22);
  --jarvis-shadow-5: 0 19px 38px rgba(0,0,0,0.30), 0 15px 12px rgba(0,0,0,0.22);
  
  /* Transitions */
  --jarvis-transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
  --jarvis-transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
  --jarvis-transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
  
  /* Borders */
  --jarvis-border-radius-sm: 4px;
  --jarvis-border-radius-md: 8px;
  --jarvis-border-radius-lg: 16px;
  --jarvis-border-radius-round: 50%;
  
  /* Z-index layers */
  --jarvis-z-dropdown: 1000;
  --jarvis-z-sticky: 1020;
  --jarvis-z-fixed: 1030;
  --jarvis-z-modal-backdrop: 1040;
  --jarvis-z-modal: 1050;
  --jarvis-z-popover: 1060;
  --jarvis-z-tooltip: 1070;
}

/* Dark mode support */
[data-theme="dark"] {
  --jarvis-text-primary: rgba(255, 255, 255, 0.87);
  --jarvis-text-secondary: rgba(255, 255, 255, 0.54);
  --jarvis-text-disabled: rgba(255, 255, 255, 0.38);
  --jarvis-divider: rgba(255, 255, 255, 0.12);
  --jarvis-background: #121212;
  --jarvis-surface: #1E1E1E;
  
  /* Adjust shadows for dark mode */
  --jarvis-shadow-1: 0 1px 3px rgba(0,0,0,0.24), 0 1px 2px rgba(0,0,0,0.48);
  --jarvis-shadow-2: 0 3px 6px rgba(0,0,0,0.32), 0 3px 6px rgba(0,0,0,0.46);
}

/* Responsive breakpoints */
@custom-media --mobile (max-width: 599px);
@custom-media --tablet (min-width: 600px) and (max-width: 1023px);
@custom-media --desktop (min-width: 1024px);
@custom-media --wide (min-width: 1440px);

/* Chat-specific styles */
.jarvis-chat {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background: var(--jarvis-background);
}

.jarvis-chat__messages {
  flex: 1;
  overflow-y: auto;
  padding: var(--jarvis-spacing-md);
  scroll-behavior: smooth;
}

.jarvis-chat__message {
  display: flex;
  margin-bottom: var(--jarvis-spacing-md);
  animation: slideIn var(--jarvis-transition-base);
}

.jarvis-chat__message--sent {
  justify-content: flex-end;
}

.jarvis-chat__message--received {
  justify-content: flex-start;
}

.jarvis-chat__bubble {
  max-width: 70%;
  padding: var(--jarvis-spacing-sm) var(--jarvis-spacing-md);
  border-radius: var(--jarvis-border-radius-lg);
  box-shadow: var(--jarvis-shadow-1);
  word-wrap: break-word;
}

.jarvis-chat__message--sent .jarvis-chat__bubble {
  background: var(--jarvis-primary);
  color: white;
  border-bottom-right-radius: var(--jarvis-border-radius-sm);
}

.jarvis-chat__message--received .jarvis-chat__bubble {
  background: var(--jarvis-surface);
  color: var(--jarvis-text-primary);
  border-bottom-left-radius: var(--jarvis-border-radius-sm);
}

/* Animations */
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.jarvis-typing-indicator {
  display: inline-flex;
  align-items: center;
  padding: var(--jarvis-spacing-sm);
}

.jarvis-typing-indicator span {
  height: 8px;
  width: 8px;
  background: var(--jarvis-text-secondary);
  border-radius: var(--jarvis-border-radius-round);
  margin: 0 2px;
  animation: pulse var(--jarvis-transition-slow) infinite;
}

.jarvis-typing-indicator span:nth-child(2) {
  animation-delay: 150ms;
}

.jarvis-typing-indicator span:nth-child(3) {
  animation-delay: 300ms;
}
```

## Testing Requirements

### Component Test Template

```javascript
// TestRunner.js - Simple testing framework for Apps Script frontend
class TestRunner {
  constructor() {
    this.tests = [];
    this.results = [];
  }
  
  describe(description, testFn) {
    this.tests.push({
      description,
      testFn,
      type: 'suite'
    });
  }
  
  it(description, testFn) {
    this.tests.push({
      description,
      testFn,
      type: 'test'
    });
  }
  
  async run() {
    console.log('🧪 Running tests...');
    
    for (const test of this.tests) {
      try {
        if (test.type === 'suite') {
          console.group(test.description);
          await test.testFn();
          console.groupEnd();
        } else {
          await test.testFn();
          this.results.push({
            description: test.description,
            passed: true
          });
          console.log('✅', test.description);
        }
      } catch (error) {
        this.results.push({
          description: test.description,
          passed: false,
          error: error.message
        });
        console.error('❌', test.description, error);
      }
    }
    
    this.printSummary();
  }
  
  printSummary() {
    const passed = this.results.filter(r => r.passed).length;
    const failed = this.results.filter(r => !r.passed).length;
    
    console.log('\n📊 Test Results:');
    console.log(`Passed: ${passed}`);
    console.log(`Failed: ${failed}`);
    console.log(`Total: ${this.results.length}`);
    
    if (failed > 0) {
      console.log('\n❌ Failed tests:');
      this.results
        .filter(r => !r.passed)
        .forEach(r => console.log(`  - ${r.description}: ${r.error}`));
    }
  }
  
  expect(actual) {
    return {
      toBe: (expected) => {
        if (actual !== expected) {
          throw new Error(`Expected ${expected}, got ${actual}`);
        }
      },
      toEqual: (expected) => {
        if (JSON.stringify(actual) !== JSON.stringify(expected)) {
          throw new Error(`Expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);
        }
      },
      toBeTruthy: () => {
        if (!actual) {
          throw new Error(`Expected truthy value, got ${actual}`);
        }
      },
      toBeFalsy: () => {
        if (actual) {
          throw new Error(`Expected falsy value, got ${actual}`);
        }
      },
      toContain: (item) => {
        if (!actual.includes(item)) {
          throw new Error(`Expected to contain ${item}`);
        }
      }
    };
  }
}

// Example component test
const testRunner = new TestRunner();

testRunner.describe('ChatInterface Component', () => {
  testRunner.it('should initialize with empty messages', () => {
    const chat = new ChatInterface('#test-container');
    testRunner.expect(chat.messages).toEqual([]);
  });
  
  testRunner.it('should add message to state', () => {
    const chat = new ChatInterface('#test-container');
    chat.addMessage('Hello', 'user');
    testRunner.expect(chat.messages.length).toBe(1);
    testRunner.expect(chat.messages[0].text).toBe('Hello');
  });
  
  testRunner.it('should render messages in DOM', () => {
    document.body.innerHTML = '<div id="test-container"></div>';
    const chat = new ChatInterface('#test-container');
    chat.addMessage('Test message', 'user');
    chat.render();
    
    const messageElements = document.querySelectorAll('.jarvis-chat__message');
    testRunner.expect(messageElements.length).toBe(1);
  });
  
  testRunner.it('should handle voice input', async () => {
    const chat = new ChatInterface('#test-container');
    const mockAudio = new Blob(['audio'], { type: 'audio/webm' });
    
    // Mock the API call
    window.google = {
      script: {
        run: {
          withSuccessHandler: (fn) => ({
            withFailureHandler: () => ({
              transcribeAudio: () => {
                fn({ text: 'Transcribed text' });
              }
            })
          })
        }
      }
    };
    
    await chat.handleVoiceInput(mockAudio);
    testRunner.expect(chat.messages[0].text).toBe('Transcribed text');
  });
});

// Run tests
testRunner.run();
```

### Testing Best Practices
1. **Unit Tests**: Test individual components in isolation
2. **Integration Tests**: Test component interactions with mocked backend
3. **Manual Testing**: Use Chrome DevTools for debugging and validation
4. **Accessibility Testing**: Use Chrome Lighthouse and axe DevTools
5. **Performance Testing**: Monitor bundle size and runtime performance
6. **Mobile Testing**: Test on actual devices for touch interactions

## Environment Configuration

```javascript
// config.js - Environment configuration for Apps Script frontend
const JarvisConfig = {
  // API endpoints (Apps Script Web App URL)
  API_BASE_URL: 'https://script.google.com/macros/s/SCRIPT_ID/exec',
  
  // Feature flags
  FEATURES: {
    VOICE_ENABLED: true,
    DARK_MODE: true,
    ANIMATIONS: true,
    DEBUG_MODE: false
  },
  
  // Timeouts and limits
  LIMITS: {
    MAX_MESSAGE_LENGTH: 5000,
    MAX_AUDIO_DURATION: 600, // 10 minutes in seconds
    MAX_FILE_SIZE: 25 * 1024 * 1024, // 25MB
    SESSION_TIMEOUT: 30 * 60 * 1000, // 30 minutes
    API_TIMEOUT: 30000 // 30 seconds
  },
  
  // UI Configuration
  UI: {
    MESSAGES_PER_PAGE: 50,
    TYPING_INDICATOR_DELAY: 500,
    NOTIFICATION_DURATION: 5000,
    ANIMATION_DURATION: 300
  },
  
  // Voice Recording
  VOICE: {
    SAMPLE_RATE: 16000,
    AUDIO_FORMAT: 'webm',
    CHUNK_SIZE: 1024,
    SILENCE_DETECTION: true,
    SILENCE_THRESHOLD: -50, // dB
    SILENCE_DURATION: 2000 // ms
  },
  
  // Cache Configuration
  CACHE: {
    MESSAGE_TTL: 3600, // 1 hour
    USER_PREFERENCES_TTL: 86400, // 24 hours
    CALENDAR_TTL: 900, // 15 minutes
    EMAIL_TTL: 900 // 15 minutes
  }
};

// Development vs Production
if (window.location.hostname === 'localhost') {
  JarvisConfig.FEATURES.DEBUG_MODE = true;
  JarvisConfig.API_BASE_URL = 'https://script.google.com/macros/s/DEV_SCRIPT_ID/exec';
}

// Freeze configuration to prevent modifications
Object.freeze(JarvisConfig);
```

## Frontend Developer Standards

### Critical Coding Rules
1. **Always use google.script.run for backend calls** - Never use fetch() or XMLHttpRequest
2. **Handle all async operations with promises** - Apps Script callbacks must be promisified
3. **Sanitize all user input** - Use DOMPurify or equivalent for HTML content
4. **Component cleanup on destroy** - Remove all event listeners and timers
5. **Use CSS custom properties for theming** - All colors and spacing from variables
6. **Upgrade MDL components after DOM changes** - Call componentHandler.upgradeElements()
7. **Test on actual mobile devices** - Chrome DevTools mobile emulation is not sufficient
8. **Implement proper loading states** - Every async operation needs visual feedback
9. **Cache API responses appropriately** - Use StateManager cache with TTL
10. **Handle Apps Script timeouts gracefully** - 30-second execution limit awareness

### Quick Reference

**Common Commands:**
```bash
# No build commands needed - Apps Script serves directly
# Development happens in Apps Script Editor or clasp

# Local development with clasp
clasp clone SCRIPT_ID
clasp pull
clasp push
clasp open
```

**Key Import Patterns:**
```html
<!-- In HTML files -->
<?!= include('AppCss'); ?>
<?!= include('AppJs'); ?>
```

```javascript
// Calling backend functions
google.script.run
  .withSuccessHandler(onSuccess)
  .withFailureHandler(onError)
  .backendFunction(param1, param2);
```

**File Naming Conventions:**
- HTML files: PascalCase.html
- Include files: PascalCase.html
- Backend references: match .gs file function names

**Project-Specific Patterns:**
- State management: Use global JarvisState
- API calls: Use JarvisApi service
- Components: Extend JarvisComponent base class
- Error handling: Use JarvisApi.showNotification()
- View management: Use ViewManager for navigation

---

**SAVE THIS DOCUMENT AS: `docs/frontend-architecture.md`**

This Frontend Architecture provides a complete foundation for implementing the Jarvis AI Assistant's user interface within Google Apps Script's HTML Service constraints, ensuring a responsive, performant, and user-friendly experience.