# Next Steps: Prompt Guide (Shards → Stories → Validation → Dev)

## 0) Activate Agents Quickly
- @bmad-orchestrator — overview and guidance
- @pm — PRD work
- @architect — architecture / sharding
- @po — sharding, validation
- @sm — story creation
- @dev — implementation
- @qa — testing

## 1) Sharded PRD → Create Stories (Scrum Master)
In chat:
```text
@sm
```
For each shard you want actionable stories from (paste any/all):
```text
*create-story "docs/shards/prd/epic-1-foundation-authentication.md" docs/stories
*create-story "docs/shards/prd/epic-2-core-communication-agents.md" docs/stories
*create-story "docs/shards/prd/epic-3-productivity-agents.md" docs/stories
*create-story "docs/shards/prd/epic-4-intelligence-layer.md" docs/stories
*create-story "docs/shards/prd/epic-5-advanced-workflows.md" docs/stories
*create-story "docs/shards/prd/epic-6-ui-enhancement-polish.md" docs/stories
*create-story "docs/shards/prd/requirements.md" docs/stories
*create-story "docs/shards/prd/user-interface-design-goals.md" docs/stories
*create-story "docs/shards/prd/performance-baselines-success-metrics.md" docs/stories
*create-story "docs/shards/prd/technical-assumptions.md" docs/stories
```

## 2) Validate Story Drafts (Product Owner)
In chat:
```text
@po
*validate-story-draft docs/stories/epic-1-foundation-authentication.story.md
*validate-story-draft docs/stories/epic-2-core-communication-agents.story.md
*validate-story-draft docs/stories/epic-3-productivity-agents.story.md
*validate-story-draft docs/stories/epic-4-intelligence-layer.story.md
*validate-story-draft docs/stories/epic-5-advanced-workflows.story.md
*validate-story-draft docs/stories/epic-6-ui-enhancement-polish.story.md
*validate-story-draft docs/stories/requirements.story.md
*validate-story-draft docs/stories/user-interface-design-goals.story.md
*validate-story-draft docs/stories/performance-baselines-success-metrics.story.md
*validate-story-draft docs/stories/technical-assumptions.story.md
```

## 3) Plan Sprint / Next Story (Scrum Master)
```text
@sm
*plan-sprint
*create-next-story
```

## 4) Implementation (Dev)
Open validated story and implement:
```text
@dev
*implement docs/stories/<validated-story>.md
```

## 5) QA Validation (QA)
```text
@qa
*plan-tests docs/stories/<validated-story>.md
*validate-acceptance docs/stories/<validated-story>.md
```

## Utility: Shard Remaining Docs (PO)
If needed later:
```text
@po
*shard-doc docs/PDR.md docs/shards/prd
*shard-doc docs/updatedarchitecture.md docs/shards/architecture
*shard-doc docs/frontend-architecture.md docs/shards/frontend-architecture
*shard-doc "docs/stories and epics.md" docs/shards/stories-epics
```

## Current Files Summary
- Shards: docs/shards/prd (16 items)
- Stories: docs/stories (10 items)

Keep running: SM → PO → Dev → QA for each story.
