# Epic 2: Core Communication Agents

**Goal:** Implement Gmail and Calendar agents to handle email and scheduling operations through natural language commands.

## Story 2.1: Gmail Agent - Email Reading

As a user,
I want to ask about my recent emails,
so that I can quickly review my inbox without opening Gmail.

**Acceptance Criteria:**
1. Function to list recent/important emails implemented
2. Email search by keyword or sender working
3. Thread summarization using Gemini AI functional
4. Results formatted clearly in chat interface
5. Pagination for large result sets
6. Error handling for Gmail API failures

## Story 2.2: Gmail Agent - Email Composition

As a user,
I want to draft and send emails using natural language,
so that I can manage email without leaving the chat.

**Acceptance Criteria:**
1. Draft creation from natural language instructions
2. Ability to review and edit drafts before sending
3. Contact resolution for recipient names
4. Reply to thread functionality working
5. Confirmation required before sending
6. Attachments handling (at least acknowledgment)

## Story 2.3: Calendar Agent - View Schedule

As a user,
I want to check my calendar and availability,
so that I can manage my time effectively.

**Acceptance Criteria:**
1. Today's agenda display functional
2. Multi-day schedule view available
3. Free/busy time detection working
4. Meeting details shown clearly
5. Multiple calendar support (if user has multiple)
6. Timezone handling correct

## Story 2.4: Calendar Agent - Event Creation

As a user,
I want to schedule meetings using natural language,
so that I can quickly set up events.

**Acceptance Criteria:**
1. Event creation from natural language working
2. Attendee resolution and invitation sending
3. Conflict detection and warning
4. Meeting room/resource booking (if applicable)
5. Recurring event support
6. Event modification and cancellation

## Story 2.5: Voice Input Integration

As a user,
I want to use voice commands,
so that I can interact hands-free.

**Acceptance Criteria:**
1. Voice recording button in UI
2. Audio capture and upload working
3. Transcription via Gemini or Speech-to-Text API
4. Transcribed text shown before processing
5. Error handling for audio issues
6. Visual feedback during recording
