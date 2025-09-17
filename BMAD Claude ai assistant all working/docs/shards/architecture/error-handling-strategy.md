# Error Handling Strategy

## General Approach
- **Error Model:** Try-catch with graceful degradation
- **Exception Hierarchy:** Custom error classes for each agent
- **Error Propagation:** Bubble to orchestrator for user messaging

## Logging Standards
- **Library:** Console and Stackdriver (built-in)
- **Format:** JSON structured logging
- **Levels:** ERROR, WARN, INFO, DEBUG
- **Required Context:**
  - Correlation ID: `${sessionId}-${timestamp}`
  - Service Context: Agent name and method
  - User Context: Anonymized user ID

## Error Handling Patterns

### External API Errors
- **Retry Policy:** Exponential backoff with 3 retries
- **Circuit Breaker:** Trip after 5 consecutive failures
- **Timeout Configuration:** 30s for Gemini, 10s for Google APIs
- **Error Translation:** User-friendly messages for all API errors

### Business Logic Errors
- **Custom Exceptions:** InvalidIntent, QuotaExceeded, AuthorizationError
- **User-Facing Errors:** Clear action messages with recovery steps
- **Error Codes:** JARVIS-XXX format for support reference

### Data Consistency
- **Transaction Strategy:** Compensating transactions for multi-step operations
- **Compensation Logic:** Undo commands for failed operations
- **Idempotency:** Command IDs prevent duplicate execution
