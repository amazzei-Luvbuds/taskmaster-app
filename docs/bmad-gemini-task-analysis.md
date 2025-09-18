## Epic: BMAD AI Analyst (Gemini) — Task Analysis and Subtask Generation

### Goal
Enable a feature-flagged Gemini-backed analysis path that generates structured analysis, subtasks, and recommendations for a TaskMaster task, returning strict JSON to the UI. Keep the heuristic path as default for reliability.

### Business Value
- Faster, more complete task breakdowns with low PM overhead
- Consistent output schema to feed Kanban, estimates, and dependency views
- Opt-in, controllable costs via feature flag and caching

---

### Single, Self-Contained Prompt (no external includes)

Use this entire body as the prompt. Fill placeholders with actual task fields. Keep temperature low (0.2–0.4). Validate JSON strictly.

System:
You are the BMAD Analyst for TaskMaster. Be concise, deterministic, and follow the schema exactly. No prose outside JSON. No markdown. Prefer practical steps over theory.

Context:
- You analyze a single task and produce: analysis, subtasks, and recommendations.
- Departments imply domain constraints (Sales, Accounting/Finance, Tech/Engineering, Marketing, HR/People, Customer Retention/Support, Purchasing, Trade Shows & Merchandising, Swag, Content & Documentation, Training, Platform Customization, User Management).
- Keep outputs minimal but actionable. Avoid hallucinations. If information is missing, make conservative assumptions and note them inside fields that support text.

Input Task:
- id: {{task.id}}
- title: {{task.title}}
- description: {{task.description}}
- department: {{task.department}}  // optional
- priority: {{task.priority}}      // 1–10, optional

Requirements:
1) analysis object must include:
   - complexity: { level: "simple"|"moderate"|"complex"|"enterprise", score: 1-10, factors: [{factor: string, impact: "low"|"medium"|"high", description: string}] }
   - estimatedEffort: number  // total hours, integer or .5 steps
   - skillsRequired: string[] // concise, role/skill words only
   - dependencies: [{ type: "external"|"internal"|"data"|"approval"|"tech", description: string }]
   - risks: [{ severity: "low"|"medium"|"high"|"critical", category: string, description: string, mitigation?: string }]
   - breakdown: [{ phase: string, deliverables: string[], exitCriteria?: string }]
   - departmentSpecific: object  // brief, 0–6 concise keys relevant to the department

2) Generate 3–8 subtasks with tight scoping:
   - Each: { id: string, title: string, estimatedHours: number, priority: 1-10, prerequisites: string[] }
   - IDs must be deterministic using task id, e.g., "{{task.id}}-s1", "{{task.id}}-s2", …
   - Sequence sensibly; reflect real dependencies in prerequisites.

3) Provide 2–6 recommendations (strings) that improve speed, quality, or risk posture.

4) Output JSON ONLY. No commentary, no markdown, no code fences.

Output Schema (must match exactly):
{
  "analysis": {
    "complexity": { "level": "...", "score": 0, "factors": [ { "factor": "...", "impact": "...", "description": "..." } ] },
    "estimatedEffort": 0,
    "skillsRequired": ["..."],
    "dependencies": [ { "type": "...", "description": "..." } ],
    "risks": [ { "severity": "...", "category": "...", "description": "...", "mitigation": "..." } ],
    "breakdown": [ { "phase": "...", "deliverables": ["..."], "exitCriteria": "..." } ],
    "departmentSpecific": { }
  },
  "subtasks": [
    { "id": "{{task.id}}-s1", "title": "...", "estimatedHours": 0, "priority": 5, "prerequisites": [] }
  ],
  "recommendations": ["..."]
}

Constraints:
- Keep total tokens small. Avoid repeating the task description verbatim.
- Prefer whole/fractional (.5) hours.
- If little info is available, keep outputs conservative and note assumptions inside factors/risks.

---

### User Stories

- As a PM, I can enable Gemini analysis so that task detail pages show AI-generated analysis and subtasks in a consistent schema.  
  Acceptance:
  - Feature flag `VITE_BMAD_USE_GEMINI` controls usage (false by default).
  - When on, analysis/subtasks originate from Gemini; when off, heuristics.

- As a developer, I can rely on strict JSON parsing so the UI never crashes on malformed output.  
  Acceptance:
  - JSON parsing has error handling; falls back to heuristics with a warning.
  - Invalid fields are sanitized; schema gaps are defaulted safely.

- As an operator, I can control costs and performance via caching.  
  Acceptance:
  - Cache keyed by task hash (id+title+desc+dept+priority) for 6h TTL.
  - A clear button or code path can invalidate cache for a task.

---

### Implementation Plan (Minimal, Feature-Flagged)

1) Config
   - Add env: `VITE_BMAD_USE_GEMINI=false` (default), `VITE_GEMINI_API_KEY`, optional `VITE_BMAD_TEMP=0.3`.

2) Service switch (keep heuristics default)
   - `src/services/bmad/agents/analystAgent.ts`
     - In `analyzeTask(...)`: if `import.meta.env.VITE_BMAD_USE_GEMINI==='true'` then call `geminiAnalyzeTask(task)`; else existing heuristics.

3) New integration function
   - `src/services/bmad/llm/gemini.ts`
     - `geminiAnalyzeTask(task): Promise<TaskAnalysis>`
       - Build prompt above, substitute values.
       - Call Gemini with low temperature.
       - Strictly parse JSON and map to `TaskAnalysis`.

4) Caching layer
   - `src/services/bmad/cache.ts` with simple in-memory Map keyed by task hash. TTL 6h.

5) Error handling
   - If Gemini fails or returns invalid JSON → log warning, fall back to heuristics; surface non-blocking toast in dev.

6) Tests
   - Unit: prompt builder, parser, cache key stability, fallback path.
   - Integration: toggle flag true/false; ensure schema invariants held.

---

### Acceptance Checklist
- [ ] Env flag off by default; app builds with no key present
- [ ] When flag on and key set, Gemini path returns valid schema
- [ ] On provider error/invalid JSON, heuristic fallback works seamlessly
- [ ] Cache reduces repeat calls; invalidation path documented
- [ ] No UI regressions on task detail; strict types preserved

---

### Developer Notes
- Keep the prompt ≤ ~1.5KB; avoid verbose echoes.
- Don’t couple UI directly to provider; keep a clean interface.
- Avoid adding new global state; stay within existing BMAD hooks/services.


