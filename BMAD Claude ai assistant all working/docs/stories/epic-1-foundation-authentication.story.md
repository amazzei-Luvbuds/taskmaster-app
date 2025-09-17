# Story: Epic 1 — Foundation & Authentication

## Source
- From: docs/shards/prd/epic-1-foundation-authentication.md

## Summary
Establish core infrastructure, auth, and basic web app framework to enable agent functionality.

## Stories Extracted
- Story 1.1: Project Initialization and Configuration
- Story 1.2: Gemini AI Integration Setup
- Story 1.3: Authentication and Session Management
- Story 1.4: Web Application Framework
- Story 1.5: Basic Chat Interface

## Acceptance Criteria (Hardened)
- End-to-end auth flow works for a seeded test user
- p95 login < 800 ms; first page TTI < 2.5 s on dev hardware
- All auth failures return actionable messages; no stack traces to users
- Session timeout configurable; default 30 min; refresh token rotation enforced
- All secrets read from env/secret store; no secrets in repo

## Definition of Done
- Tests: unit (auth utils), integration (OAuth flow), e2e (login/logout)
- Documentation: README auth section, sequence diagram path referenced
- Observability: auth metrics dashboard, error rate alert, trace for login path
- Security: scopes/permissions reviewed; threat model note added
- Rollback: documented steps and automated toggle/flag

## Repo / Stack Targets
- Repository: <project-repo-name>
- Runtime: Node.js LTS (>= 20)
- Framework: <framework-name>
- Data: <db/cache if applicable>

## Environment Variables
- AUTH_CLIENT_ID (secret store)
- AUTH_CLIENT_SECRET (secret store)
- AUTH_REDIRECT_URI
- SESSION_SECRET (secret store)
- TOKEN_TTL_MIN=30
- LOG_LEVEL=info

## Auth Flow References
- Diagram: docs/architecture/authentication-sequence.md (or mermaid in shard)
- Success path: login → consent → callback → session create → redirect
- Error paths: denied consent, invalid code, expired session, CSRF protection
- Refresh policy: rotating refresh tokens; revoke on logout

## Rollback Plan
- Trigger: auth outage or elevated error rate > 5% for 5 min
- Steps: enable feature flag to previous auth; invalidate new sessions; notify
- Data: no irreversible migrations; tokens invalidated server-side

## Next Steps
- SM: refine into individual implementable stories with clear DoD
- PO: validate draft
- Dev: implement per acceptance criteria
