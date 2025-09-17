# Story: Technical Assumptions

## Source
- From: docs/shards/prd/technical-assumptions.md

## Summary
Capture and validate technical assumptions. Convert to risks/migrations as needed.

## Experiments
- E1: Model context limit — run synthetic long-context prompts; record degradation curve
- E2: Gmail throughput — k6 test Gmail list/search mocks; validate quotas/backoff
- E3: Calendar event latency — simulate create/modify; measure p50/p95; tune retries

## Risks & Mitigations
- R1: API quota exhaustion → proactive throttling, alerts, fallback cache
- R2: Token cost spikes → cost ceiling, lower-context variant, batch operations
- R3: PII leakage in logs → redaction, reviews, restricted access

## Kill-switches
- Feature flags: INTEL_LAYER_V1, WORKFLOW_ENGINE_V1, VOICE_INPUT
- Rollback: disable flag, revert to previous version/config; invalidate sessions if needed

## Next Steps
- SM: enumerate implications
- PO: validate
- Dev: implement mitigations
