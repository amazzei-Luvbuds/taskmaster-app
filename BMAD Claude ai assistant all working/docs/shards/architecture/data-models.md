# Data Models

## User
**Purpose:** Store user preferences and session information

**Key Attributes:**
- email: string - User's Google account email
- preferences: object - User-specific settings
- timezone: string - User's timezone for scheduling
- workingHours: object - Start/end times for smart scheduling

**Relationships:**
- Has many Sessions
- Has many AuditLogs

## Session
**Purpose:** Manage conversation context and state

**Key Attributes:**
- sessionId: string - Unique session identifier
- userId: string - Associated user email
- startTime: timestamp - Session start
- messages: array - Conversation history
- context: object - Current conversation context

**Relationships:**
- Belongs to User
- Has many Commands

## Command
**Purpose:** Track executed commands for undo/audit

**Key Attributes:**
- commandId: string - Unique identifier
- sessionId: string - Parent session
- intent: string - Detected intent type
- parameters: object - Command parameters
- result: object - Execution result
- timestamp: timestamp - Execution time

**Relationships:**
- Belongs to Session
- May have related GoogleResource

## GoogleResource
**Purpose:** Track created/modified Google resources

**Key Attributes:**
- resourceId: string - Google resource ID
- resourceType: string - email/event/task/doc
- commandId: string - Creating command
- metadata: object - Resource-specific data

**Relationships:**
- Created by Command
- May be referenced by other Commands
