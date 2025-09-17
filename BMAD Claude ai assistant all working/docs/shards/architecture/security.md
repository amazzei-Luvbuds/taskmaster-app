# Security

## Input Validation
- **Validation Library:** ValidationUtils.gs
- **Validation Location:** At API boundary in WebApp.gs
- **Required Rules:**
  - All external inputs MUST be validated
  - Validation at API boundary before processing
  - Whitelist approach preferred over blacklist

## Authentication & Authorization
- **Auth Method:** Google OAuth 2.0 with Apps Script
- **Session Management:** Properties Service with timeout
- **Required Patterns:**
  - Session validation on every request
  - Scope verification before API calls

## Secrets Management
- **Development:** Script Properties (encrypted)
- **Production:** Google Secret Manager (future)
- **Code Requirements:**
  - NEVER hardcode secrets
  - Access via configuration service only
  - No secrets in logs or error messages

## API Security
- **Rate Limiting:** Token bucket per user
- **CORS Policy:** Apps Script managed
- **Security Headers:** Content-Security-Policy set
- **HTTPS Enforcement:** Automatic with Apps Script

## Data Protection
- **Encryption at Rest:** Google-managed encryption
- **Encryption in Transit:** HTTPS only
- **PII Handling:** No PII in logs, anonymized IDs only
- **Logging Restrictions:** No passwords, tokens, or email content

## Dependency Security
- **Scanning Tool:** Manual review (limited in Apps Script)
- **Update Policy:** Quarterly review of Gemini API changes
- **Approval Process:** Architecture review for new APIs

## Security Testing
- **SAST Tool:** ESLint with security rules
- **DAST Tool:** Manual penetration testing
- **Penetration Testing:** Annual third-party review
