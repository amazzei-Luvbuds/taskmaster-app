# Story: User Interface Design Goals

## Source
- From: docs/shards/prd/user-interface-design-goals.md

## Summary
Translate UI design goals into implementable stories and acceptance criteria.

## Component Library
- Choice: shadcn/ui with Tailwind CSS
- Rationale: accessible primitives, headless patterns, flexible tokens, strong community

## Theming Tokens
- Palette: primary, surface, text.primary/text.secondary, success, warning, error
- Spacing: 4/8 base scale; radius: 4/8/12
- Typography: Inter or system stack; sizes xs→xl with line-height tokens

## Responsive Rules
- Breakpoints: sm 640, md 768, lg 1024, xl 1280, 2xl 1536
- Layout: mobile-first; grid areas for app chrome; content max-width 72ch
- Touch Targets: min 44x44 px; hit slop 8 px

## Interaction Latency Budgets
- Navigation: ≤ 150 ms perceived with prefetch; hard ≤ 500 ms
- Button-to-response: p95 ≤ 200 ms for local UI work; ≤ 800 ms for networked
- Input debounce: 150–300 ms; optimistic UI where safe

## Next Steps
- SM: expand
- PO: validate
- Dev: implement
