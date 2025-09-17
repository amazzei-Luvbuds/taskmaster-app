# Environment Configuration

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
