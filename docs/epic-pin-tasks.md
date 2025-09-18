## Epic: Pin Tasks to Top of Dashboard

### Problem
Key tasks get buried in long lists. Users need a quick way to surface priority items without changing other sorting or filters.

### Goal
Allow users to pin/unpin tasks so pinned items always appear at the top of the dashboard (and optionally Kanban), without altering existing sort/filter semantics.

### Non‑Goals
- No server persistence in this iteration (client/local only).
- No team‑wide pin sharing (future iteration).

---

### User Stories

1) Pin/Unpin a task
- As a user, I can click a pin icon on a task card to toggle pin/unpin.
- Acceptance:
  - Clicking the pin icon does not open the task modal.
  - Pinned state is visually indicated (filled star or highlight).
  - State persists across reloads (local persistence).

2) Pinned ordering on Dashboard
- As a user, I see pinned tasks at the top of the dashboard list.
- Acceptance:
  - Ordering rule: pinned active → active → pinned completed → completed.
  - Existing sortBy/sortOrder still apply within each group.

3) Respect filters
- As a user, when I apply filters, only pinned tasks that match filters appear at the top.
- Acceptance:
  - Filtering runs before pin grouping; pins visible only if they pass filters.

4) Kanban (optional, stretch)
- As a user, I can see pinned tasks at the top of their column in Kanban.
- Acceptance:
  - Same grouping rule within each column; existing drag/drop unaffected.

---

### UX Notes
- Pin icon in `TaskCard` header (left of quick actions). Tooltip: "Pin task" / "Unpin task".
- Keyboard focusable; enter/space toggles.

### Technical Plan (Minimal)
1) Store (Zustand) — `src/store/appStore.ts`
   - Add to `UserPreferences`: `pinnedTaskIds: string[]` (default `[]`).
   - Actions:
     - `togglePinnedTask(taskId: string)`
     - `isTaskPinned(taskId: string): boolean`
   - Persist via existing `persist` middleware.

2) Dashboard sorting — `src/components/Dashboard.tsx`
   - After filtering and before final sort merge, split into:
     - `pinnedActive`, `active`, `pinnedCompleted`, `completed`.
   - Apply existing `sortTasks` within each group; then concat.

3) UI — `src/components/TaskCard.tsx`
   - Add pin icon button; stopPropagation on click; call `togglePinnedTask(task.taskID)`.
   - Condition icon styling based on `isTaskPinned(task.taskID)`.

4) Tests (lightweight)
   - Unit: store actions, pinned detection.
   - Integration: filtered set ordering honors pin groups.

### Telemetry/Logging (Dev only)
- Console info when toggling pin in dev builds to verify flows.

### Acceptance Checklist
- [ ] Pin icon toggles without opening modal
- [ ] Pinned state persists across reloads
- [ ] Pinned ordering: pinned active → active → pinned completed → completed
- [ ] Filters respected before pin grouping
- [ ] No regression to drag/drop or existing sorts

### Risks
- Sorting complexity: ensure grouping does not negate current sort settings.
- ID consistency: pinning uses `task.taskID` (verify field presence through UI).

### Future
- Server persistence per user
- Team‑wide shared pins
- Quick “Pinned” filter segment


