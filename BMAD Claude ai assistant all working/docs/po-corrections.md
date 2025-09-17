# PO Corrections Checklist (Stories Hardening)

Use this to harden each story before Dev. Apply per section, then re-run validation.

## Epic 1 — Foundation & Authentication
- DoD: tests (unit/e2e), docs updated, dashboards/logging, rollback verified
- Repo/Stack Targets: repo name, runtime (Node/LTS), framework, DB/cache
- Env Vars: list names, sources, rotation policy, secret store
- Auth Flow: reference diagram path(s), success/error paths, refresh policy
- Rollback Plan: triggers, steps, data migration reversal

## Epic 2 — Core Communication Agents
- Gmail/Calendar Scopes: enumerate exact OAuth scopes
- Quotas/Rate Limits: limits, strategy (throttle/backoff), alerts
- Test Accounts: seeded users, data, permissions
- Failure Modes: API errors, timeouts, partial results handling
- Latency SLOs: p50/p95 budgets, timeout thresholds

## Epic 3 — Productivity Agents
- API Boundaries: Tasks/Docs/People endpoints and ownership
- Doc Templates: names, locations, versioning
- Max Sizes: payload/file sizes, truncation strategy
- Caching: keys, TTLs, invalidation
- PII Handling: redaction, access controls, audit logs

## Epic 4 — Intelligence Layer
- Models: provider(s), versions, fallbacks
- Context Limits: max tokens, chunking policy
- Prompt Budget: per-request/feature ceilings, monitoring
- Guardrails: allow/deny lists, content filters
- Retry/Backoff: policy, max attempts, idempotency
- Cost Ceilings: daily/monthly caps, fail-safe behavior

## Epic 5 — Advanced Workflows
- Workflow IDs: list all
- Triggers: events/time/commands
- Timeouts: operation and overall
- Idempotency: keys, dedupe windows
- Compensation: steps for failure paths
- Observability: traces, metrics, logs, alerts

## Epic 6 — UI Enhancement & Polish
- Accessibility: WCAG target, keyboard nav, screen reader support
- Dark Mode: token strategy, contrast ratios
- Performance Budgets: TTI/LCP/CLS targets, bundle limits

## Requirements.story.md
- Traceability: map each requirement → epic/story
- Add acceptance traceability table

## UI Design Goals
- Component Library: chosen lib, rationale
- Theming Tokens: palette, spacing, typography
- Responsive: breakpoints, layout rules
- Interaction Latency: target budgets per interaction

## Performance Baselines & Success Metrics
- Load Profiles: RPS, concurrency, data volumes
- Baseline Metrics: current state, targets
- SLOs: availability/latency/error budgets
- Methodology: how measured, tools
- Test Harness: scripts, datasets, environments

## Technical Assumptions
- Experiments: design to validate each assumption
- Risks: list + mitigations
- Kill-switches: feature flags and rollback

---

## How to Apply (Prompts)
- Validate a story after edits:
```text
@po
*validate-story-draft docs/stories/<file>.md
```
- Ask SM to enrich with strict AC/DoD from shards:
```text
@sm
*draft
```
- Or request targeted edits (replace <file>):
```text
@po
Please update docs/stories/<file>.md with:
- Explicit, testable Acceptance Criteria with measurable thresholds
- Definition of Done (tests, docs, logging, dashboards)
- Dependencies (APIs, scopes, feature flags, data)
- Risks/Mitigations and Rollback
- Non-Functional Requirements (latency, error budget, cost caps)
```
