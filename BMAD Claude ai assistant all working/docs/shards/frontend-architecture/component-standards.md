# Component Standards

## Component Template

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

## Naming Conventions
- **Components:** PascalCase (e.g., `ChatInterface`, `VoiceRecorder`)
- **Files:** PascalCase.html (e.g., `Chat.html`, `Dashboard.html`)
- **CSS Classes:** BEM methodology (e.g., `jarvis-chat__message--sent`)
- **JavaScript Functions:** camelCase (e.g., `sendMessage`, `handleVoiceInput`)
- **Constants:** UPPER_SNAKE_CASE (e.g., `MAX_MESSAGE_LENGTH`)
