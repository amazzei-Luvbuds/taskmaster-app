# Story: Performance Baselines & Success Metrics

## Source
- From: docs/shards/prd/performance-baselines-success-metrics.md

## Summary
Define performance baselines and success metrics; derive testable stories.

## Load Profiles
- Read-heavy: 50 RPS, concurrency 100, payload 1–10KB
- Write bursts: 10 RPS, concurrency 20, payload 1–50KB
- Background jobs: hourly, batch size up to 5K items

## Baselines & Targets
- Current baseline: measure with k6; record p50/p95/p99 and error rate
- Targets: p95 API < 800 ms; error rate < 0.5%; uptime 99.9%

## SLOs
- Availability: 99.9% monthly; error budget 0.1%
- Latency: p95 < 800 ms for critical paths; p99 < 2 s
- Throughput: sustain load profiles without degradation

## Methodology
- Tools: k6, Lighthouse, WebPageTest; traces via OpenTelemetry
- Process: warm-up 2 min, test 10 min, cool-down 2 min; 3 runs avg
- Data: stable fixtures; isolate network variability

## Test Harness
- Scripts: /perf/k6/*.js with scenarios for read/write/mix
- Datasets: /perf/data/*.json (sanitized)
- Environments: dev/staging with feature flags; results in /perf/results

## Next Steps
- SM: outline tasks/KPIs
- PO: validate
- Dev: implement
