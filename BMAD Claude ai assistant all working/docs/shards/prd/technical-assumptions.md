# Technical Assumptions

## Repository Structure: Monorepo
All code will be contained within a single Google Apps Script project for simplified deployment and maintenance.

## Service Architecture
**CRITICAL DECISION** - Serverless architecture using Google Apps Script as the sole runtime environment, with all processing happening within Google's infrastructure.

## Testing Requirements
**CRITICAL DECISION** - Unit tests for each agent module, integration tests for agent interactions, UI testing scenarios for critical paths, with manual testing convenience methods for development.

## Additional Technical Assumptions and Requests
- Gemini AI API will be used for all NLP and intent detection
- Google Workspace APIs will be accessed via Apps Script's built-in services
- Properties Service will be used for configuration and user preferences
- Cache Service will be used for performance optimization
- No external databases - all persistence via Google services
- Deployment via Google Apps Script web app
- Authentication handled by Google OAuth with appropriate scopes
