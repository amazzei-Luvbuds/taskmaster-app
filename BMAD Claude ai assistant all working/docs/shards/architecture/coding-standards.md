# Coding Standards

## Core Standards
- **Languages & Runtimes:** JavaScript ES6+ on Apps Script V8 runtime
- **Style & Linting:** Google JavaScript Style Guide
- **Test Organization:** Tests in `/Tests` folder, one file per agent

## Naming Conventions
| Element | Convention | Example |
|---------|------------|---------|
| Files | PascalCase.gs | `GmailAgent.gs` |
| Functions | camelCase | `sendEmail()` |
| Constants | UPPER_SNAKE | `MAX_RETRIES` |
| Private functions | underscore suffix | `validateInput_()` |

## Critical Rules
- **Never use console.log in production code - use Logger:** All logging must go through Logger.log() for Stackdriver
- **All API responses must use standardized response wrapper:** Every agent must return `{success: boolean, data: any, error?: string}`
- **Cache all Google API calls with appropriate TTL:** Use CacheManager for all external API responses
- **Input validation on every public function:** Use ValidationUtils before processing
- **Rate limiting must be enforced at agent level:** Each agent tracks its own quota usage

## Language-Specific Guidelines

### JavaScript/Apps Script Specifics
- **Async handling:** Use Promises consistently, no callbacks
- **Error boundaries:** Every agent method wrapped in try-catch
- **Memory management:** Clear large objects after use
- **Execution time:** Monitor 6-minute limit, implement chunking
