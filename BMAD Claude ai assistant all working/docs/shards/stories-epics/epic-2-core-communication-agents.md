# **Epic 2: Core Communication Agents**
*Priority: HIGH*
*Dependencies: Epic 1*
*Estimated Stories: 5*

## Goal
Implement Gmail and Calendar agents to handle email and scheduling operations through the chat interface.

## User Stories

### Story 2.1: Chat Interface Implementation
**As a** user,  
**I want** to send text messages to the AI assistant,  
**So that** I can interact with the system using natural language.

**Acceptance Criteria:**
1. Chat.html interface with message input and display
2. Real-time message updates via google.script.run
3. Message history stored in session (90-day retention)
4. Typing indicators during processing
5. Error messages displayed for failed requests

### Story 2.2: Gmail Agent - Email Operations
**As a** user,  
**I want** to manage emails through natural language commands,  
**So that** I can handle email without opening Gmail.

**Acceptance Criteria:**
1. List recent/important emails with summarization
2. Search emails by keyword or sender
3. Draft creation from natural language
4. Send emails with confirmation
5. Thread summarization using Gemini AI

### Story 2.3: Calendar Agent - Schedule Management
**As a** user,  
**I want** to check and manage my calendar through chat,  
**So that** I can efficiently handle scheduling.

**Acceptance Criteria:**
1. Display today's agenda and multi-day schedule
2. Free/busy time detection
3. Meeting conflict detection
4. Event creation from natural language
5. Smart scheduling with working hours consideration

### Story 2.4: Orchestrator & Intent Routing
**As a** system,  
**I want** to route commands to the appropriate agent,  
**So that** user requests are handled correctly.

**Acceptance Criteria:**
1. Intent detection via Gemini AI
2. Multi-agent command support
3. Context-aware routing
4. Fallback handling for unclear intents
5. Performance monitoring per agent

---
