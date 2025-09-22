# Epic: Dual-Tier Pin System Enhancement

## Epic Overview

Enhance the existing pin system to support both personal pins (individual user control) and global pins (leadership control) with distinct visual differentiation and hierarchical ordering.

## Epic Goals

1. Enable leadership to pin tasks globally with authority-level visual indicators
2. Maintain existing personal pin functionality with updated visual hierarchy
3. Implement role-based permissions for global pin management
4. Create clear visual distinction between personal and global pins

## User Stories Summary

### Phase 1: Data Layer (Backend/API)
- 1.1: Extend Task Schema for Dual-Pin Support
- 1.2: Create Global Pin Management API
- 1.3: Implement Role-Based Authorization

### Phase 2: State Management (Frontend Store)
- 2.1: Enhance Pin State Management
- 2.2: Add Permission-Aware Pin Actions
- 2.3: Update Persistence Layer

### Phase 3: Visual Components (UI Layer)
- 3.1: Enhanced PinnedCardWrapper with Color Schemes
- 3.2: Dual Pin Icon System
- 3.3: Leadership Pin Controls

### Phase 4: Integration (Dashboard Logic)
- 4.1: Dual-Tier Sorting Algorithm
- 4.2: Pin Notification System
- 4.3: Audit Logging and Metadata

## Technical Architecture Overview

```typescript
// Core Types
enum PinType {
  PERSONAL = 'personal',
  GLOBAL = 'global'
}

interface TaskPin {
  taskId: string;
  type: PinType;
  pinnedBy: string;
  pinnedAt: Date;
  priority?: number; // for global pins
  reason?: string;   // for global pins
}

// Visual Color Schemes
const PIN_COLORS = {
  personal: { primary: '#fbbf24', secondary: '#f59e0b', tertiary: '#d97706' },
  global: { primary: '#3b82f6', secondary: '#8b5cf6', tertiary: '#6366f1' }
};
```

## Visual Hierarchy Rules

1. **Global pins** (blue trace) appear first
2. **Personal pins** (golden trace) follow
3. Within each tier: sort by priority, then date
4. Animation differences: Global pins rotate faster (2s vs 2.5s)

## Success Criteria

- [ ] Leadership can create global pins with blue visual indicators
- [ ] Personal pins retain golden visual indicators with proper hierarchy
- [ ] Role-based permissions prevent unauthorized global pin creation
- [ ] Visual distinction is clear and professional
- [ ] No performance degradation in dashboard rendering
- [ ] All existing pin functionality preserved

---

*This epic enhances the existing pin system without breaking current functionality while adding the hierarchical control leadership needs.*