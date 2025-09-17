# Story: Epic 6 — UI Enhancement & Polish

## Source
- From: docs/shards/prd/epic-6-ui-enhancement-polish.md

## Summary
Enhance the user interface and polish interaction flows.

## Acceptance Criteria (Hardened)
- Meets WCAG 2.2 AA; keyboard-only navigation across all interactive elements
- Screen reader support with proper roles/labels; focus visible states
- Dark mode via design tokens; contrast ratios ≥ 7:1 for text on dark backgrounds
- Performance budgets respected on target hardware/network

## Accessibility Targets
- WCAG: 2.2 AA
- Keyboard Navigation: tab order defined; skip links; trap prevention
- Screen Readers: aria-labels/roles/landmarks; announce live regions

## Dark Mode
- Tokens: color.primary, color.surface, color.textPrimary, spacing.*, radius.*
- Contrast: text-on-surface ≥ 7:1; secondary ≥ 4.5:1
- Auto: prefers-color-scheme respected; manual toggle persisted

## Performance Budgets
- TTI ≤ 2.5 s, LCP ≤ 2.0 s, CLS ≤ 0.1 (on Fast 3G/4x CPU slow-down)
- Bundle: initial JS ≤ 200KB gzip; route splits ≤ 100KB gzip
- Images: AVIF/WebP, responsive sizes; lazy-load below the fold

## Next Steps
- SM: refine implementable tasks
- PO: validate
- Dev: implement
