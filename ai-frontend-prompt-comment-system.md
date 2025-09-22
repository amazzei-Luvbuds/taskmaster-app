# AI Frontend Prompt: Comment/Discussion System Rebuild

## High-Level Goal

Create a modern, comprehensive comment/discussion system component for a task management application with the following capabilities:

- **Threaded comments** with replies and nested discussions
- **@ Mention functionality** with real-time suggestions and notifications
- **Rich text editing** with markdown support and formatting options
- **File attachments** with drag-and-drop upload
- **Reaction system** with emoji reactions and voting
- **Real-time updates** and collaborative editing
- **Mobile-first responsive design** with touch-optimized interactions
- **Dark mode support** with smooth theme transitions
- **Accessibility compliance** (ARIA labels, keyboard navigation, screen reader support)

## Detailed Requirements

### 1. Modal Integration & Layout Management

The comment system will be integrated within a task details modal with these specific constraints:

```typescript
// Modal container: 95vh max height
// Content area: 70vh max height
// Comment section: 60vh max height with proper scrolling
<div className="max-h-[95vh]"> {/* Modal container */}
  <div className="max-h-[70vh] overflow-y-auto"> {/* Content area */}
    <CommentThread maxHeight="max-h-[60vh]" /> {/* Comment section */}
  </div>
</div>
```

**Requirements:**
- Proper overflow handling for long discussions
- Sticky comment input form at bottom
- Smooth scrolling to new comments
- Scroll position memory when switching tabs
- Loading states that don't break layout

### 2. Avatar System with Robust Fallbacks

Implement a comprehensive avatar system with multiple fallback levels:

```typescript
interface AvatarProps {
  src?: string;
  name: string;
  email?: string;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

// Fallback hierarchy:
// 1. User's uploaded avatar (src prop)
// 2. Gravatar using email hash
// 3. UI-Avatars.com generated avatar with initials
// 4. Default brand avatar as final fallback
```

**Features:**
- Graceful degradation through fallback chain
- Consistent sizing across all avatar types
- Loading states with skeleton placeholders
- Error boundary to prevent avatar failures from breaking UI
- Lazy loading for performance optimization

### 3. @ Mention System with Enhanced UX

Create an intelligent mention system with proper z-index management:

```typescript
// Mention dropdown must appear above modal content
<div className="absolute z-[60] w-72 max-h-48 overflow-y-auto">
  {/* Suggestion list with smooth animations */}
</div>
```

**Features:**
- Real-time search as user types after @
- Keyboard navigation (up/down arrows, enter, escape)
- Smart positioning to avoid viewport edges
- Fuzzy search matching names, emails, departments
- Visual hierarchy showing name, role, department
- Touch-friendly selection on mobile
- Debounced API calls to prevent spam

### 4. Comment Thread Architecture

Build a scalable threaded comment system:

```typescript
interface Comment {
  id: string;
  taskId: string;
  parentCommentId?: string; // null for top-level comments
  authorId: string;
  authorName: string;
  authorAvatar?: string;
  content: string;
  contentType: 'plain' | 'rich' | 'markdown';
  mentions: Mention[];
  attachments?: Attachment[];
  reactions: Reaction[];
  createdAt: string;
  editedAt?: string;
  isDeleted: boolean;
  isEdited: boolean;
}
```

**Thread Features:**
- Nested replies with visual indentation (max 3 levels deep)
- Collapsible comment threads for long discussions
- Sort options: newest first, oldest first, most reactions
- Real-time updates without losing scroll position
- Optimistic UI updates with rollback on failure
- Infinite scroll for large comment lists

### 5. Rich Text Editor Integration

Implement a modern rich text editor with these capabilities:

```typescript
interface RichTextEditorProps {
  value: string;
  onChange: (content: string, mentions: Mention[]) => void;
  placeholder: string;
  disabled?: boolean;
  maxLength?: number;
  showToolbar?: boolean;
  compact?: boolean;
}
```

**Editor Features:**
- Toolbar with: Bold, Italic, Underline, Strikethrough, Code, Quote, Lists
- Markdown shortcuts (e.g., **bold**, *italic*, `code`)
- Link insertion with URL validation
- Toggle between rich text and plain text modes
- Character counter near limit
- Auto-save drafts locally
- Paste handling for formatted content

### 6. File Attachment System

Create a robust file upload component:

```typescript
interface FileUploadProps {
  onFilesUploaded: (files: UploadedFile[]) => void;
  onFilesRemoved: (fileIds: string[]) => void;
  maxFiles?: number;
  maxFileSize?: number; // in bytes
  allowedTypes?: string[];
  disabled?: boolean;
}
```

**Features:**
- Drag and drop upload area
- File type validation with clear error messages
- Progress indicators for uploads
- Preview thumbnails for images
- File size limits with human-readable display
- Remove uploaded files before submission
- Multiple file selection support

### 7. Reaction & Interaction System

Build an engaging reaction system:

```typescript
interface ReactionSystemProps {
  commentId: string;
  reactions: Reaction[];
  onReactionToggle: (emoji: string) => void;
  disabled?: boolean;
}

interface Reaction {
  emoji: string;
  count: number;
  users: string[]; // user IDs who reacted
  hasReacted: boolean; // current user
}
```

**Features:**
- Common emoji reactions (👍, 👎, ❤️, 😄, 😮, 😢, 🚀)
- Custom emoji picker with search
- Reaction count with user avatars on hover
- Visual feedback when adding/removing reactions
- Keyboard accessibility for reaction selection

### 8. Mobile-First Responsive Design

Design system optimized for mobile devices:

```css
/* Mobile-first breakpoints */
@media (min-width: 640px) { /* sm */ }
@media (min-width: 768px) { /* md */ }
@media (min-width: 1024px) { /* lg */ }
```

**Mobile Optimizations:**
- Touch-friendly button sizes (minimum 44px)
- Swipe gestures for comment actions
- Collapsible comment composer
- Bottom sheet for mention suggestions on mobile
- Optimized virtual keyboard handling
- Reduced motion for users who prefer it

### 9. Dark Mode Implementation

Comprehensive dark mode support:

```typescript
// Use CSS custom properties for seamless theme switching
const themes = {
  light: {
    '--bg-primary': 'rgb(255, 255, 255)',
    '--bg-secondary': 'rgb(249, 250, 251)',
    '--text-primary': 'rgb(17, 24, 39)',
    '--text-secondary': 'rgb(107, 114, 128)',
    '--border-color': 'rgb(229, 231, 235)',
    '--accent-color': 'rgb(59, 130, 246)',
  },
  dark: {
    '--bg-primary': 'rgb(31, 41, 55)',
    '--bg-secondary': 'rgb(17, 24, 39)',
    '--text-primary': 'rgb(243, 244, 246)',
    '--text-secondary': 'rgb(156, 163, 175)',
    '--border-color': 'rgb(75, 85, 99)',
    '--accent-color': 'rgb(96, 165, 250)',
  }
};
```

### 10. Accessibility Standards

Ensure WCAG 2.1 AA compliance:

```typescript
// Required ARIA attributes and patterns
<div
  role="article"
  aria-label={`Comment by ${authorName}`}
  aria-describedby={`comment-time-${id}`}
>
  <button
    aria-label="Reply to comment"
    aria-expanded={showReplyForm}
    aria-controls={`reply-form-${id}`}
  >
    Reply
  </button>
</div>
```

**A11y Features:**
- Semantic HTML structure with proper headings
- Keyboard navigation for all interactive elements
- Screen reader announcements for new comments
- High contrast mode support
- Focus management in modal context
- Skip links for comment navigation

## Technical Implementation Guidelines

### Component Architecture

```typescript
// Main components structure
<CommentThread taskId={string}>
  <CommentHeader count={number} onRefresh={() => void} />
  <CommentList comments={Comment[]} maxHeight="60vh">
    <CommentItem comment={Comment}>
      <CommentContent />
      <CommentActions onReply onEdit onDelete />
      <CommentReactions />
      <CommentReplies replies={Comment[]} />
    </CommentItem>
  </CommentList>
  <CommentComposer onSubmit={handleSubmit} />
</CommentThread>
```

### State Management

```typescript
// Use React Context for comment thread state
interface CommentContextState {
  comments: Comment[];
  loading: boolean;
  error?: string;
  replyingTo?: string;
  editingComment?: string;
  showComposer: boolean;
}

// Actions for state updates
type CommentAction =
  | { type: 'LOAD_COMMENTS'; payload: Comment[] }
  | { type: 'ADD_COMMENT'; payload: Comment }
  | { type: 'UPDATE_COMMENT'; payload: Comment }
  | { type: 'DELETE_COMMENT'; payload: string }
  | { type: 'SET_REPLYING'; payload: string | undefined }
  | { type: 'SET_EDITING'; payload: string | undefined };
```

### API Integration

```typescript
// Comment service interface
interface CommentService {
  getTaskComments(taskId: string, cursor?: string): Promise<CommentResponse>;
  createComment(data: CreateCommentRequest): Promise<Comment>;
  updateComment(commentId: string, data: UpdateCommentRequest): Promise<Comment>;
  deleteComment(commentId: string): Promise<void>;
  addReaction(commentId: string, emoji: string): Promise<Reaction[]>;
  removeReaction(commentId: string, emoji: string): Promise<Reaction[]>;
  getTeamMembers(taskId: string, query?: string): Promise<TeamMember[]>;
  uploadAttachment(file: File): Promise<UploadedFile>;
}
```

### Performance Optimizations

1. **Virtual scrolling** for large comment threads
2. **Intersection Observer** for lazy loading comments
3. **Debounced search** for mention suggestions
4. **Optimistic updates** with rollback on failure
5. **Image lazy loading** for avatars and attachments
6. **Memoization** for expensive comment tree calculations

### Error Handling

```typescript
// Comprehensive error boundaries
<ErrorBoundary fallback={<CommentSystemError />}>
  <CommentThread />
</ErrorBoundary>

// Graceful degradation for failed features
const CommentWithFallback = ({ comment }: { comment: Comment }) => {
  const [hasError, setHasError] = useState(false);

  if (hasError) {
    return <CommentErrorFallback comment={comment} />;
  }

  return <CommentItem comment={comment} onError={setHasError} />;
};
```

## Example Implementation Structure

```typescript
// CommentThread.tsx - Main container component
export function CommentThread({ taskId, maxHeight = "max-h-96" }: CommentThreadProps) {
  const { comments, loading, error, dispatch } = useCommentThread(taskId);

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg border">
      <CommentHeader
        count={comments.length}
        onRefresh={() => dispatch({ type: 'REFRESH_COMMENTS' })}
      />

      <div className={`${maxHeight} overflow-y-auto`}>
        {loading ? (
          <CommentSkeleton />
        ) : error ? (
          <CommentError error={error} onRetry={() => dispatch({ type: 'RETRY' })} />
        ) : (
          <CommentList comments={comments} />
        )}
      </div>

      <CommentComposer
        taskId={taskId}
        onSubmit={(comment) => dispatch({ type: 'ADD_COMMENT', payload: comment })}
      />
    </div>
  );
}

// CommentItem.tsx - Individual comment component
export function CommentItem({ comment, depth = 0 }: CommentItemProps) {
  const maxDepth = 3;
  const [showActions, setShowActions] = useState(false);

  return (
    <article
      className={`group p-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 ${
        depth > 0 ? 'ml-8 pl-4 border-l-2 border-gray-100 dark:border-gray-700' : ''
      }`}
      onMouseEnter={() => setShowActions(true)}
      onMouseLeave={() => setShowActions(false)}
    >
      <CommentContent comment={comment} />
      <CommentActions
        comment={comment}
        visible={showActions}
        canReply={depth < maxDepth}
      />
      {comment.replies?.map(reply => (
        <CommentItem key={reply.id} comment={reply} depth={depth + 1} />
      ))}
    </article>
  );
}
```

## Design System Integration

Use consistent design tokens throughout:

```typescript
// Spacing scale
const spacing = {
  xs: '0.25rem',   // 4px
  sm: '0.5rem',    // 8px
  md: '0.75rem',   // 12px
  lg: '1rem',      // 16px
  xl: '1.5rem',    // 24px
  '2xl': '2rem',   // 32px
};

// Typography scale
const typography = {
  'text-xs': '0.75rem',    // 12px
  'text-sm': '0.875rem',   // 14px
  'text-base': '1rem',     // 16px
  'text-lg': '1.125rem',   // 18px
};

// Color palette
const colors = {
  primary: {
    50: '#eff6ff',
    500: '#3b82f6',
    600: '#2563eb',
    700: '#1d4ed8',
  },
  gray: {
    50: '#f9fafb',
    100: '#f3f4f6',
    500: '#6b7280',
    800: '#1f2937',
    900: '#111827',
  }
};
```

## Strict Scope Definition

**INCLUDED in this component:**
- Complete comment thread rendering and management
- Real-time comment creation, editing, deletion
- Nested reply system with proper threading
- @ Mention system with user lookup
- Rich text editor with markdown support
- File attachment upload and display
- Emoji reaction system
- Mobile-responsive design
- Dark mode theming
- Accessibility compliance
- Error handling and loading states
- Avatar system with fallbacks

**EXCLUDED from this component:**
- Task management functionality
- User authentication/authorization
- Notification system backend logic
- Real-time WebSocket connection management
- Backend API implementation
- Database schema design
- Email notification templates
- Administrative moderation tools
- Analytics and reporting features
- Integration with external services (Slack, Teams, etc.)

## Success Criteria

The rebuilt comment system should achieve:

1. **Performance**: Smooth scrolling and interactions on mobile devices
2. **Accessibility**: Pass WAVE and axe-core accessibility audits
3. **User Experience**: Intuitive mention system with <200ms response time
4. **Visual Design**: Consistent with existing design system and theme support
5. **Mobile Optimization**: Touch-friendly with proper gesture support
6. **Error Resilience**: Graceful degradation when API calls fail
7. **Cross-browser**: Compatible with modern browsers (Chrome, Safari, Firefox, Edge)

This prompt provides a comprehensive foundation for rebuilding the comment/discussion system with modern UX patterns, robust error handling, and accessibility best practices.