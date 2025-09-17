# Requirements

## Functional
- FR1: The system shall authenticate users via Google OAuth and maintain secure sessions
- FR2: The system shall accept both text and voice input for all commands
- FR3: The system shall process natural language commands through Gemini AI for intent detection
- FR4: The system shall draft, send, and manage emails based on conversational instructions
- FR5: The system shall schedule, reschedule, and manage calendar events with conflict detection
- FR6: The system shall create and manage tasks from email content or direct commands
- FR7: The system shall transcribe meeting audio and generate structured summaries
- FR8: The system shall provide a web-based dashboard showing recent activities and quick actions
- FR9: The system shall maintain conversation context across multiple interactions
- FR10: The system shall resolve contact names to email addresses automatically

## Non Functional
- NFR1: All API responses must complete within 8 seconds for complex operations, 4 seconds for simple queries
- NFR2: The system must handle up to 100 concurrent users without degradation
- NFR3: All user data must remain within Google's infrastructure for security compliance
- NFR4: The system must gracefully handle API quota limits with user-friendly error messages
- NFR5: The interface must be mobile-responsive and accessible (WCAG AA compliant)
- NFR6: The system must maintain 99.5% uptime during business hours
- NFR7: All sensitive operations must require explicit user confirmation
- NFR8: The system must provide detailed audit logs for all actions taken
- NFR9: Data retention policies shall follow these guidelines:
  - Conversation history: 90 days rolling window in Cache Service
  - User preferences: Persistent in User Properties (no expiration)
  - Session data: 24 hours in Document Properties
  - Audit logs: 180 days in designated Google Drive folder
  - Meeting transcriptions: 30 days in Drive, then archived
  - Email drafts: 7 days if not sent
  - Cached API responses: 1 hour for calendar, 15 minutes for email
  - Error logs: 30 days rolling window
- NFR10: Audio transcription shall support:
  - Maximum file size: 25MB per recording (Apps Script limit)
  - Supported formats: WAV, MP3, M4A, OGG, WebM
  - Maximum duration: 10 minutes per recording (can be extended post-MVP)
  - Sampling rate: 16kHz minimum, 48kHz maximum
  - Fallback to chunked processing for larger files
  - Automatic format conversion via Gemini Audio API when possible
