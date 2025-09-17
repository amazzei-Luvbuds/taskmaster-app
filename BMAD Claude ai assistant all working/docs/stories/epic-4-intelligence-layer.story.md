# Story: Epic 4 — Intelligence Layer

## Source
- From: docs/shards/prd/epic-4-intelligence-layer.md

## Summary
Establish the intelligence layer for advanced reasoning and orchestration.

## Acceptance Criteria (Hardened)
- Deterministic routing of requests to models based on task class
- p95 latency budgets per operation respected; errors handled with fallbacks
- All prompts and responses logged with redaction; cost tracking enabled

## Models
- Primary: Google Gemini 1.5 Pro (structured reasoning)
- Secondary: OpenAI GPT-4o-mini (fast/light)
- Fallback: Local small model (rules/templates) for resilience

## Context Limits
- Max tokens: 32k input, 8k output default
- Chunking: recursive markdown/text chunking at headings/paragraphs
- Retrieval: top-k=5 with max 3k tokens context per call

## Prompt Budget
- Per request: hard cap $0.02; per feature daily cap $2
- Monitoring: log cost per call; daily rollup with alerts at 80%

## Guardrails
- Allow/Deny lists for tools and domains
- PII filter on output; profanity/abuse filter enabled
- Refuse unsafe actions; require human confirmation for sensitive ops

## Retry & Backoff
- Policy: retry 429/5xx up to 3 attempts
- Backoff: exponential with jitter (200ms base)
- Idempotency: use idempotency keys for tool calls

## Cost Ceilings
- Daily: $5; Monthly: $100 (dev env defaults)
- Behavior at ceiling: fail-safe responses with “cost budget exceeded”

## Next Steps
- SM: expand into implementable stories
- PO: validate
- Dev: implement
