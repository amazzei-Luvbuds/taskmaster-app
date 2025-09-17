# External APIs

## Gemini AI API
- **Purpose:** Natural language processing and intent detection
- **Documentation:** https://ai.google.dev/api/rest
- **Base URL(s):** https://generativelanguage.googleapis.com/v1beta/
- **Authentication:** API Key
- **Rate Limits:** 60 requests per minute

**Key Endpoints Used:**
- `POST /models/gemini-pro:generateContent` - Text generation and intent detection
- `POST /models/gemini-pro-vision:generateContent` - Audio transcription (if available)

**Integration Notes:** Implement exponential backoff for rate limiting, cache common responses

## Google Workspace APIs (via Advanced Services)
- **Purpose:** Access Google Workspace data
- **Documentation:** Built into Apps Script
- **Base URL(s):** N/A (native integration)
- **Authentication:** OAuth 2.0 (automatic)
- **Rate Limits:** Various per service

**Key Services:**
- Gmail: 250 quota units per user per second
- Calendar: 500 queries per day
- Tasks: 50,000 queries per day
- People: 90 queries per minute

**Integration Notes:** Use batch operations where possible, implement caching strategy
