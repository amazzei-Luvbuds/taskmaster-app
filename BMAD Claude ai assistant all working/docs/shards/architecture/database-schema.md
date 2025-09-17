# Database Schema

Since we're using Properties Service and Cache Service instead of a traditional database:

```javascript
// Script Properties (Global Configuration)
{
  "config": {
    "GEMINI_API_KEY": "encrypted_key",
    "DEFAULT_TIMEZONE": "America/Los_Angeles",
    "WORKING_HOURS_START": "09:00",
    "WORKING_HOURS_END": "17:00",
    "MAX_AUDIO_SIZE_MB": 25,
    "CACHE_TTL_SECONDS": 3600
  }
}

// User Properties (Per-User Storage)
{
  "user:{email}": {
    "preferences": {
      "autoSendEmails": false,
      "meetingBuffer": 15,
      "defaultMeetingDuration": 30,
      "voiceEnabled": true,
      "theme": "light"
    },
    "workingHours": {
      "monday": {"start": "09:00", "end": "17:00"},
      // ... other days
    }
  },
  "session:{sessionId}": {
    "userId": "user@company.com",
    "startTime": "ISO-8601",
    "lastActive": "ISO-8601",
    "context": {}
  }
}

// Cache Service (Temporary Storage)
{
  "intent:{hash}": {
    "intent": "schedule_meeting",
    "confidence": 0.95,
    "ttl": 3600
  },
  "calendar:{userId}:{date}": {
    "events": [...],
    "ttl": 900  // 15 minutes
  },
  "emails:{userId}:recent": {
    "messages": [...],
    "ttl": 900
  }
}
```
