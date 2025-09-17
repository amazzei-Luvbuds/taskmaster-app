# Story: Epic 2 — Core Communication Agents

## Source
- From: docs/shards/prd/epic-2-core-communication-agents.md

## Summary
Implement Gmail and Calendar agents for email and scheduling via natural language.

## Stories Extracted
- Story 2.1: Gmail Agent - Email Reading
- Story 2.2: Gmail Agent - Email Composition
- Story 2.3: Calendar Agent - View Schedule
- Story 2.4: Calendar Agent - Event Creation
- Story 2.5: Voice Input Integration

## Acceptance Criteria (Hardened)
- Gmail read/compose and Calendar view/create work for seeded test users
- p50 < 500 ms and p95 < 1200 ms for basic list/read/calendar views
- Explicit confirmation before send/event creation; idempotency keys for retries
- Errors shown with recovery guidance; no sensitive data leaked

## OAuth Scopes
- Gmail: https://www.googleapis.com/auth/gmail.readonly, https://www.googleapis.com/auth/gmail.modify, https://www.googleapis.com/auth/gmail.send
- Calendar: https://www.googleapis.com/auth/calendar.readonly, https://www.googleapis.com/auth/calendar.events

## Quotas & Rate Limits
- Define per-user QPS and daily caps; default QPS 10/user
- Strategy: client-side throttling + exponential backoff (200ms base, jitter)
- Alerts: error-rate > 2% or latency p95 > 1200 ms for 5 min

## Test Accounts
- users: test1@example.com, test2@example.com (seeded data: emails, events)
- Permissions: minimal scopes per above; separate staging project

## Failure Modes & Handling
- API 4xx/5xx: retry 5xx up to 3x; surface 4xx with guidance
- Timeouts: 5s read/list; 10s send/create; cancel and advise retry
- Partial results: paginate and indicate truncation; allow “show more”

## Latency SLOs
- p50: list/read 500 ms; compose draft 800 ms; create event 1s
- p95: list/read 1200 ms; compose 1500 ms; create event 2s
- Timeouts: list/read 5s; compose/create 10s

## Next Steps
- SM: expand into actionable stories with DoD
- PO: validate
- Dev: implement incrementally
