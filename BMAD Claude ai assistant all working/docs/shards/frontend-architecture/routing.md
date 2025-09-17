# Routing

## Route Configuration

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
