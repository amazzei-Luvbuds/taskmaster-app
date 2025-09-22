# Enhanced Pin System Documentation

## Overview

The Enhanced Pin System provides visually distinct pin indicators for personal vs global pins with improved accessibility, performance, and user experience.

## Features

### Visual Distinction
- **Personal Pins**: Golden/amber tracing light animation (2.5s duration)
- **Global Pins**: Blue/purple tracing light animation (2s duration)
- **Priority-based Intensity**: Animation intensity varies based on pin priority (1-10)

### Accessibility
- **Screen Reader Support**: Announces pin type and priority
- **Reduced Motion**: Animations disabled when user prefers reduced motion
- **High Contrast Mode**: Adjusted colors and effects
- **Keyboard Navigation**: Proper focus indicators
- **WCAG AA Compliance**: All colors meet contrast ratio standards

### Performance
- **Hardware Acceleration**: GPU-optimized animations
- **Memoized Calculations**: Expensive computations cached
- **CSS Custom Properties**: Efficient theme switching
- **Minimal Re-renders**: Optimized React components

## Components

### PinnedCardWrapper

Enhanced wrapper component with pin type support.

```typescript
interface PinnedCardWrapperProps {
  children: ReactNode;
  isPinned?: boolean; // Backward compatibility
  pinType?: PinType | null; // New pin type prop
  priority?: number; // Pin priority (1-10)
  className?: string;
  isDarkMode?: boolean; // Manual dark mode override
}
```

#### Usage Examples

```tsx
// Personal pin with medium priority
<PinnedCardWrapper pinType={PinType.PERSONAL} priority={5}>
  <TaskCard task={task} />
</PinnedCardWrapper>

// Global pin with high priority
<PinnedCardWrapper pinType={PinType.GLOBAL} priority={9}>
  <TaskCard task={task} />
</PinnedCardWrapper>

// Backward compatibility
<PinnedCardWrapper isPinned={true}>
  <TaskCard task={task} />
</PinnedCardWrapper>

// No pin
<PinnedCardWrapper pinType={null}>
  <TaskCard task={task} />
</PinnedCardWrapper>
```

### PinType Enum

```typescript
export enum PinType {
  PERSONAL = 'personal',
  GLOBAL = 'global'
}
```

## Hooks

### usePinDisplay

Get pin display information for a single task.

```typescript
const pinInfo = usePinDisplay(taskId);
// Returns: { pinType, priority, isPinned, isPersonalPin, isGlobalPin, ... }
```

### useBulkPinDisplay

Optimized hook for getting pin information for multiple tasks.

```typescript
const bulkPinInfo = useBulkPinDisplay([taskId1, taskId2, taskId3]);
// Returns: { [taskId]: PinDisplayInfo }
```

### useHasPinnedTasks

Check if any tasks in a list are pinned (useful for bulk operations).

```typescript
const { hasAnyPinned, hasPersonalPins, hasGlobalPins, totalPinned } = useHasPinnedTasks(taskIds);
```

## Color Schemes

### Personal Pin Colors (Golden/Amber)
- **Light Mode**: #fbbf24, #f59e0b, #d97706, #b45309
- **Dark Mode**: #fcd34d, #fbbf24, #f59e0b, #d97706
- **Glow**: rgba(251, 191, 36, 0.5) / rgba(252, 211, 77, 0.6)

### Global Pin Colors (Blue/Purple)
- **Light Mode**: #3b82f6, #8b5cf6, #6366f1, #7c3aed
- **Dark Mode**: #60a5fa, #a78bfa, #818cf8, #8b5cf6
- **Glow**: rgba(59, 130, 246, 0.5) / rgba(96, 165, 250, 0.6)

## Animation Configuration

### Personal Pins
- **Duration**: 2.5s
- **Easing**: Linear
- **Intensity**: Based on priority (1-10)

### Global Pins
- **Duration**: 2s (faster than personal)
- **Easing**: Linear
- **Intensity**: Based on priority (1-10)

### Intensity Levels
- **Priority 1-3**: Subtle (lighter glow, less blur)
- **Priority 4-7**: Normal (standard glow and blur)
- **Priority 8-10**: Strong (intense glow, more blur)

## Integration with Pin Store

The enhanced pin system integrates seamlessly with the existing pin store:

```tsx
import { usePinDisplay } from '../hooks/usePinDisplay';

function TaskCard({ task }) {
  const pinInfo = usePinDisplay(task.taskID);

  return (
    <PinnedCardWrapper
      pinType={pinInfo.pinType}
      priority={pinInfo.priority}
    >
      <div>Task content...</div>
    </PinnedCardWrapper>
  );
}
```

## CSS Custom Properties

The system uses CSS custom properties for efficient theming:

```css
.pinned-card-wrapper {
  --pin-primary: #fbbf24;
  --pin-secondary: #f59e0b;
  --pin-tertiary: #d97706;
  --pin-quaternary: #b45309;
  --pin-glow: rgba(251, 191, 36, 0.5);
  --pin-animation-duration: 2.5s;
  --pin-animation-name: personal-pin-trace;
}
```

## Utilities

### Color Scheme Functions

```typescript
// Get color scheme for pin type
const colors = getPinColorScheme(PinType.PERSONAL, isDarkMode);

// Generate gradient for border
const gradient = generatePinGradient(PinType.GLOBAL, isDarkMode);

// Generate glow gradient
const glowGradient = generatePinGlowGradient(PinType.PERSONAL, isDarkMode);

// Get CSS properties
const cssProps = getPinCSSProperties(PinType.GLOBAL, isDarkMode);
```

### Preference Detection

```typescript
// Check user preferences
const reducedMotion = prefersReducedMotion();
const darkMode = prefersDarkMode();

// Validate accessibility
const isAccessible = validateColorContrast(foreground, background);
```

## Performance Considerations

### Optimizations
1. **Memoized Calculations**: Color schemes and animations are memoized
2. **CSS Hardware Acceleration**: Uses `transform: translateZ(0)` and `will-change`
3. **Minimal Re-renders**: React components optimized with useMemo
4. **Efficient Gradients**: Conic gradients cached in CSS custom properties

### Best Practices
1. Use `useBulkPinDisplay` for lists of tasks
2. Avoid frequent pin type changes (causes gradient recalculation)
3. Use priority-based intensity sparingly (intensive visual effects)
4. Consider reduced motion preferences for accessibility

## Browser Support

### Modern Features
- CSS Custom Properties (IE 11+)
- Conic Gradients (Chrome 69+, Firefox 83+, Safari 12.1+)
- CSS Animations (All modern browsers)

### Fallbacks
- Reduced motion: Static borders instead of animations
- Unsupported gradients: Solid color borders
- High contrast mode: Simplified color schemes

## Testing

### Accessibility Testing
- Screen reader announcements
- Keyboard navigation
- Color contrast ratios
- Reduced motion preferences

### Performance Testing
- Animation frame rate (should maintain 60fps)
- Memory usage with multiple pins
- GPU acceleration verification

### Visual Regression Testing
- Pin appearance in light/dark modes
- Animation consistency
- Cross-browser compatibility

## Migration Guide

### From Old PinnedCardWrapper

```tsx
// Before
<PinnedCardWrapper isPinned={true}>
  <TaskCard />
</PinnedCardWrapper>

// After (backward compatible)
<PinnedCardWrapper isPinned={true}>
  <TaskCard />
</PinnedCardWrapper>

// Enhanced (recommended)
<PinnedCardWrapper pinType={PinType.PERSONAL} priority={5}>
  <TaskCard />
</PinnedCardWrapper>
```

### Integration Steps
1. Import new utilities: `import { PinType } from '../utils/colorSchemes'`
2. Use pin display hook: `const pinInfo = usePinDisplay(taskId)`
3. Update component props: `pinType={pinInfo.pinType} priority={pinInfo.priority}`
4. Test accessibility and performance

## Examples

See `src/components/PinnedCardExamples.tsx` for comprehensive usage examples including:
- Personal vs global pin comparison
- Priority-based intensity demonstration
- Dark mode support
- Performance testing with multiple pins
- Accessibility features showcase