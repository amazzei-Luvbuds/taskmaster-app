# Styling Guidelines

## Styling Approach
The application uses Material Design Lite (MDL) for consistent Google-style components, enhanced with custom CSS for Jarvis-specific elements. CSS custom properties enable theming and maintain consistency across all components.

## Global Theme Variables

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
