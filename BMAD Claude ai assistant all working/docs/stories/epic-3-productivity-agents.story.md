# Story: Epic 3 — Productivity Agents

## Source
- From: docs/shards/prd/epic-3-productivity-agents.md

## Summary
Add Tasks, Docs, and People agents for workspace automation.

## Stories Extracted
- Story 3.1: Tasks Agent Implementation
- Story 3.2: Document Creation Agent
- Story 3.3: People/Contacts Agent
- Story 3.4: Meeting Transcription

## Acceptance Criteria (Hardened)
- Tasks: create/list/complete with filters; docs: create/append/share; people: resolve by name
- p95 < 1200 ms for list/search; transcription jobs can be async with status
- Errors return actionable guidance; sensitive data masked in logs

## API Boundaries & Ownership
- Tasks: Google Tasks API; ownership: tasks-agent
- Docs: Google Docs API; ownership: docs-agent
- People: People API; ownership: people-agent
- Cross-agent calls via orchestrator; no direct coupling

## Document Templates
- meeting-summary.md (docs/templates/meeting-summary.md)
- action-items.md (docs/templates/action-items.md)
- versioning: semantic; latest alias → templates/latest/*

## Max Sizes & Truncation
- Chat→Doc append max 200KB per request; truncate with “... [truncated]”
- Transcription upload max 25MB; chunk >10min audio
- Task list pagination: 50/page default; “show more” to fetch next page

## Caching
- Keys: search:{entity}:{query}, profile:{userId}
- TTLs: search 5m, profile 1h; invalidate on write
- Store: in-memory first; opt-in Redis if present

## PII Handling
- Redaction: emails/phones in logs masked
- Access Controls: least-privilege scopes; separate service accounts per env
- Audit Logs: record create/update/delete with user and timestamp

## Next Steps
- SM: refine into implementable stories
- PO: validate
- Dev: implement
