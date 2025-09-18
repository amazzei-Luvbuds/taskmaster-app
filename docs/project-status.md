## TaskMaster Project Status (Consolidated)

Last updated: <insert date>

This document consolidates the current state across the docs and codebase. It lists what is DONE and what remains TODO so we can reduce scattered notes.

---

### DONE

- Core API URL configuration moved to env (`VITE_APP_API_URL`) and set to current GAS endpoint
- Local dev server stabilized (Vite 5173/5174), auto restart guidance added
- Department color stripes restored on Task cards (left and right)
- Dark mode implemented with `ThemeProvider` and Tailwind `dark` class; fixed white backgrounds and quick‑status contrast; modal border fix
- Dashboard sorting: completed tasks moved to bottom with dim styling
- Filters default adjusted so user doesn’t need to clear to see all tasks
- Avatars: owner avatars displayed on cards and multiple owners supported; default avatar fallback updated
- “Last Updated By” surfaced on cards (via GAS `Last_Updated_By` column)
- DevTools button wired (only shown in dev / when enabled via localStorage)
- BMAD module import issues resolved (`import type` and interface order)
- Accounting & Tech fields added to `Task` shape in `api/client.ts`
- Kanban improvements: department color mapping and styling
- CSV department color references integrated
- New docs created:
  - `docs/bmad-gemini-task-analysis.md` (Gemini prompt, epic, plan)
  - `docs/epic-pin-tasks.md` (Pin tasks epic and stories)

---

### TODO (Prioritized)

1) Pin tasks feature (Epic: `docs/epic-pin-tasks.md`)
   - Add `pinnedTaskIds` to store + actions
   - Dashboard sort grouping (pinned active → active → pinned completed → completed)
   - TaskCard star toggle (click doesn’t open modal)

2) BMAD Analyst via Gemini (Epic: `docs/bmad-gemini-task-analysis.md`)
   - Feature flag `VITE_BMAD_USE_GEMINI`
   - Implement `geminiAnalyzeTask` path with strict JSON parsing + cache
   - Fallback to heuristics on error; tests

3) HubSpot integration hardening
   - Set `VITE_HUBSPOT_GAS_ENDPOINT` and hide warning
   - Add retry/backoff and input validation for calls

4) Department color consistency audit
   - Ensure mappings in `TaskCard` and `KanbanCard` are identical and case‑safe

5) Kanban: ensure drag‑and‑drop updates always persist
   - Recheck PUT payload (`taskID`, `status`) and error surfacing
   - Add optimistic UI with rollback on failure

6) Access control / env cleanup
   - Confirm `import.meta.env` is used everywhere (no `process.env` in client)
   - Document required envs in `taskmaster-react/README.md`

7) QA pass for DOM/TS warnings
   - Fix any remaining nesting or optional chaining runtime errors in pages/components

8) Performance & UX
   - Debounce expensive filters; memoize derived lists
   - Image/Avatar loading fallbacks and sizing

---

### Nice‑to‑Have / Future

- Shareable team-wide pins and server persistence
- Saved filter presets per user
- Per‑department workflow recommendations on task details
- Full BMAD workflow execution path (beyond analysis/subtasks)

---

### References (curated)

- BMAD + Gemini plan: `docs/bmad-gemini-task-analysis.md`
- Pin tasks epic: `docs/epic-pin-tasks.md`
- Integration overview: `docs/bmad-taskmaster-integration.md`
- Roadmap & backlog: `docs/remaining-implementation-roadmap.md`, `docs/scrum-backlog.md`, `docs/development-tasks.md`


