# Story: Epic 5 — Advanced Workflows

## Source
- From: docs/shards/prd/epic-5-advanced-workflows.md

## Summary
Implement advanced multi-step workflows and user-facing automations.

## Acceptance Criteria (Hardened)
- Each workflow has ID, trigger, timeout policy, and compensation steps
- Operations are idempotent; dedupe windows documented
- End-to-end traces present; alerts configured for failures/latency

## Workflow Catalog
- WF-EMAIL-ROUTER: classify → summarize → route → notify
- WF-CAL-MEETING-CREATE: propose slots → confirm → create → invite
- WF-DOC-MEETING-NOTES: transcribe → summarize → doc → share

## Triggers
- Events: new-email, calendar-invite, file-upload
- Time: cron expressions for maintenance jobs
- Commands: chat commands like /schedule, /summarize

## Timeouts
- Per operation: 10s default; long ops up to 60s (transcription)
- Overall workflow: 2 minutes; extend with async continuation tokens

## Idempotency
- Keys: hash of input payload + user + intent
- Dedupe window: 10 minutes per workflow ID
- Store: short-term cache; persistent store (optional)

## Compensation
- Email: unsend/cancel draft; post message with failure
- Calendar: delete event and notify attendees
- Docs: revert to prior version and post link

## Observability
- Traces: workflow and step spans with IDs
- Metrics: success rate, latency p50/p95, retries, compensation count
- Logs: structured JSON with workflowId, stepId, status
- Alerts: error-rate >2%, p95 > 2s, retries > 3 per minute

## Next Steps
- SM: refine
- PO: validate
- Dev: implement
