# State Management

## Store Structure

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

## State Management Template

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
