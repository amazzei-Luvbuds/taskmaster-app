# Tech Stack

## Cloud Infrastructure
- **Provider:** Google Cloud (via Apps Script)
- **Key Services:** Apps Script Runtime, Google Workspace APIs, Properties Service, Cache Service, URL Fetch Service
- **Deployment Regions:** Global (Google-managed)

## Technology Stack Table

| Category | Technology | Version | Purpose | Rationale |
|----------|------------|---------|---------|-----------|
| **Runtime** | Google Apps Script | V8 | Serverless execution environment | Zero infrastructure, native Google integration |
| **Language** | JavaScript/GAS | ES6+ | Primary development language | Required by Apps Script platform |
| **AI/NLP** | Gemini AI API | 1.5 Pro | Natural language understanding | Best-in-class NLP with function calling support |
| **Email Service** | Gmail API | v1 | Email operations | Native integration via Apps Script |
| **Calendar Service** | Calendar API | v3 | Scheduling operations | Built-in Apps Script service |
| **Task Service** | Tasks API | v1 | Task management | Native Apps Script integration |
| **Storage** | Properties Service | Built-in | Configuration and preferences | No external database needed |
| **Cache** | Cache Service | Built-in | Performance optimization | 6-hour TTL for session data |
| **Documents** | Docs API | v1 | Document creation | Native integration |
| **Contacts** | People API | v1 | Contact resolution | Built-in service |
| **Auth** | Google OAuth 2.0 | Built-in | Authentication | Automatic with Apps Script |
| **UI Framework** | Vanilla JS | ES6 | Frontend interactivity | Minimal overhead, fast loading |
| **CSS Framework** | Material Design Lite | 1.3.0 | UI styling | Google design consistency |
| **Testing** | GAS Testing Framework | Custom | Unit testing | Lightweight custom framework |
| **Logging** | Stackdriver | Built-in | Error tracking | Automatic with Apps Script |
| **IDE** | Apps Script Editor | Current | Development environment | Cloud-based development |
