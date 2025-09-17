# API Integration

## Service Template

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

## API Client Configuration

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
