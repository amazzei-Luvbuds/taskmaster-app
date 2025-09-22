# Pagination and Performance Optimization Guide

## Overview

This document outlines the comprehensive pagination and performance optimization system implemented for the TaskMaster comment system. The system provides high-performance data loading with caching, virtualization, and advanced monitoring capabilities.

## Architecture

### Core Components

1. **PaginationService** (`src/services/paginationService.ts`)
   - Cursor-based pagination with caching
   - Background prefetching and queue management
   - Performance metrics and monitoring
   - Virtualization support for large datasets

2. **usePagination Hook** (`src/hooks/usePagination.ts`)
   - React hook for pagination state management
   - Infinite scroll capabilities
   - Error handling and retry logic
   - Performance optimization features

3. **PaginatedCommentThread** (`src/components/comments/PaginatedCommentThread.tsx`)
   - High-performance comment rendering
   - Virtualization for large comment threads
   - Advanced caching and prefetching
   - Real-time performance monitoring

4. **Performance Utilities** (`src/utils/performanceUtils.ts`)
   - Component performance monitoring
   - Network request tracking
   - Memory usage optimization
   - Bundle size analysis

## Pagination Strategy

### Cursor-Based Pagination

The system uses cursor-based pagination instead of offset-based for better performance:

```typescript
interface PaginatedResponse<T> {
  items: T[];
  nextCursor?: string;
  prevCursor?: string;
  hasMore: boolean;
  hasPrevious: boolean;
  totalCount?: number;
  pageInfo: {
    currentPage: number;
    estimatedTotalPages?: number;
    itemsPerPage: number;
  };
}
```

**Advantages:**
- Consistent performance regardless of page depth
- Real-time data consistency (new items don't affect pagination)
- Efficient database queries with indexed cursor fields
- No pagination gaps when data is modified

### Implementation Example

```typescript
// Basic pagination usage
const pagination = usePagination(
  (cursor, limit) => commentService.getTaskComments(taskId, cursor, limit),
  {
    pageSize: 20,
    enablePreload: true,
    refreshInterval: 30000
  }
);

// Load more items
await pagination.loadMore();

// Refresh data
await pagination.refresh();
```

## Caching Strategy

### Multi-Level Caching

1. **Service-Level Cache**
   - LRU cache with configurable size and TTL
   - Automatic cache invalidation on mutations
   - Background cache warming

2. **Browser Cache**
   - HTTP caching headers for static content
   - Service worker for offline support
   - Local storage for user preferences

3. **Database Query Cache**
   - Optimized SQL queries with proper indexing
   - Query result caching with invalidation
   - Connection pooling and query optimization

### Cache Configuration

```typescript
const cacheConfig = {
  pageSize: 20,
  prefetchPages: 2,
  cacheSize: 50,
  cacheTTL: 5 * 60 * 1000, // 5 minutes
  enablePreload: true,
  enableVirtualization: false
};
```

## Performance Optimizations

### Frontend Optimizations

1. **Component Memoization**
   ```typescript
   const MemoizedCommentItem = React.memo(CommentItem, (prevProps, nextProps) => {
     return (
       prevProps.comment.id === nextProps.comment.id &&
       prevProps.comment.updatedAt === nextProps.comment.updatedAt
     );
   });
   ```

2. **Virtual Scrolling**
   ```typescript
   const virtualPagination = useVirtualizedPagination(loadFunction, {
     itemHeight: 120,
     containerHeight: 400,
     pageSize: 20
   });
   ```

3. **Lazy Loading**
   ```typescript
   const LazyCommentThread = performanceUtils.lazy(
     () => import('./PaginatedCommentThread'),
     LoadingSpinner
   );
   ```

4. **Debounced Operations**
   ```typescript
   const debouncedSearch = performanceUtils.debounce(
     (query: string) => searchComments(query),
     300
   );
   ```

### Backend Optimizations

1. **Optimized SQL Queries**
   ```sql
   -- Cursor-based pagination with joins
   SELECT c.*,
          GROUP_CONCAT(m.user_name) as mentions,
          COUNT(r.id) as reaction_count
   FROM comments c
   LEFT JOIN comment_mentions m ON c.id = m.comment_id
   LEFT JOIN comment_reactions r ON c.id = r.comment_id
   WHERE c.task_id = ? AND c.created_at < ?
   GROUP BY c.id
   ORDER BY c.created_at DESC
   LIMIT ?
   ```

2. **Database Indexing**
   ```sql
   -- Composite indexes for efficient pagination
   CREATE INDEX idx_comments_pagination ON comments(task_id, created_at, id);
   CREATE INDEX idx_comments_parent ON comments(parent_comment_id, created_at);
   CREATE INDEX idx_mentions_comment ON comment_mentions(comment_id);
   ```

3. **Response Caching**
   - Redis-based caching for frequent queries
   - ETags for conditional requests
   - Compression for large responses

## Infinite Scroll Implementation

### Automatic Loading

```typescript
const CommentThread = ({ taskId }: { taskId: string }) => {
  const pagination = usePagination(loadComments, {
    pageSize: 20,
    enablePreload: true
  });

  return (
    <div {...pagination.scrollProps} className="max-h-96 overflow-y-auto">
      {pagination.items.map(comment => (
        <CommentItem key={comment.id} comment={comment} />
      ))}

      {pagination.loadingMore && <LoadingSpinner />}

      {pagination.hasMore && (
        <IntersectionObserver
          onIntersect={pagination.loadMore}
          threshold={0.8}
        />
      )}
    </div>
  );
};
```

### Manual Loading

```typescript
<button
  onClick={pagination.loadMore}
  disabled={!pagination.hasMore || pagination.loadingMore}
  className="load-more-button"
>
  {pagination.loadingMore ? 'Loading...' : `Load More (${remainingCount})`}
</button>
```

## Virtualization for Large Datasets

### When to Use Virtualization

- **Large datasets** (1000+ items)
- **Fixed item heights** (required for calculation)
- **Memory constraints** (mobile devices)
- **Smooth scrolling** requirements

### Implementation

```typescript
const VirtualizedComments = () => {
  const pagination = useVirtualizedPagination(loadComments, {
    itemHeight: 120,
    containerHeight: 400,
    pageSize: 50
  });

  return (
    <div
      style={{ height: pagination.totalHeight }}
      {...pagination.scrollProps}
    >
      {pagination.visibleItems.map((comment, index) => (
        <div
          key={comment.id}
          style={pagination.getItemStyle(pagination.visibleRange.startIndex + index)}
        >
          <CommentItem comment={comment} />
        </div>
      ))}
    </div>
  );
};
```

## Performance Monitoring

### Real-Time Metrics

```typescript
// Component performance monitoring
const CommentThread = () => {
  const monitor = usePerformanceMonitor('CommentThread');

  useEffect(() => {
    // Track custom metrics
    performanceMonitor.startTiming('commentLoad');
    loadComments().finally(() => {
      performanceMonitor.endTiming('commentLoad');
    });
  }, []);

  return (
    <Profiler id="CommentThread" onRender={monitor.onRender}>
      {/* Component content */}
    </Profiler>
  );
};
```

### Network Monitoring

```typescript
// Automatic network request monitoring
const loadComments = async (cursor?: string) => {
  const monitor = performanceMonitor.monitorNetworkRequest('/api/comments', 'GET');

  monitor.start();
  try {
    const response = await fetch('/api/comments', { /* options */ });
    monitor.end(response.status, response.headers.get('content-length'));
    return response.json();
  } catch (error) {
    monitor.end(0);
    throw error;
  }
};
```

### Performance Reports

```typescript
// Generate comprehensive performance report
const report = performanceMonitor.generateReport();
console.log('Performance Report:', {
  loadTime: report.loadTime,
  renderTime: report.renderTime,
  memoryUsage: report.memoryUsage,
  cacheHitRate: report.cacheHitRate,
  apiLatency: report.apiLatency
});
```

## Optimization Strategies

### Bundle Size Optimization

1. **Code Splitting**
   ```typescript
   const CommentThread = lazy(() => import('./CommentThread'));
   const FileUpload = lazy(() => import('./FileUpload'));
   ```

2. **Tree Shaking**
   ```typescript
   // Import only what you need
   import { debounce } from 'lodash/debounce';
   // Instead of: import _ from 'lodash';
   ```

3. **Dynamic Imports**
   ```typescript
   const loadHeavyFeature = async () => {
     const { HeavyComponent } = await import('./HeavyComponent');
     return HeavyComponent;
   };
   ```

### Memory Management

1. **Cleanup in useEffect**
   ```typescript
   useEffect(() => {
     const subscription = observable.subscribe(handler);

     return () => {
       subscription.unsubscribe();
       performanceMonitor.recordMemoryUsage('component-cleanup');
     };
   }, []);
   ```

2. **WeakMap for Caching**
   ```typescript
   const cache = new WeakMap();
   const getCachedData = (key: object) => cache.get(key);
   ```

3. **Image Optimization**
   ```typescript
   const OptimizedImage = ({ src, alt }: { src: string; alt: string }) => (
     <img
       src={src}
       alt={alt}
       loading="lazy"
       decoding="async"
       style={{ contentVisibility: 'auto' }}
     />
   );
   ```

## Advanced Features

### Prefetching Strategy

```typescript
// Intelligent prefetching based on user behavior
const prefetchNextPage = useCallback((scrollPosition: number) => {
  if (scrollPosition > 0.7 && !prefetching) {
    const nextCursor = getNextCursor();
    if (nextCursor) {
      pagination.prefetch(nextCursor);
    }
  }
}, [prefetching, pagination]);
```

### Error Recovery

```typescript
// Automatic retry with exponential backoff
const retryWithBackoff = async (operation: () => Promise<any>, maxRetries = 3) => {
  for (let i = 0; i < maxRetries; i++) {
    try {
      return await operation();
    } catch (error) {
      if (i === maxRetries - 1) throw error;

      const delay = Math.pow(2, i) * 1000; // Exponential backoff
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }
};
```

### Offline Support

```typescript
// Service worker for offline caching
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').then(registration => {
    console.log('SW registered:', registration);
  });
}

// Offline detection and queuing
const useOfflineQueue = () => {
  const [isOnline, setIsOnline] = useState(navigator.onLine);
  const [pendingOperations, setPendingOperations] = useState<any[]>([]);

  useEffect(() => {
    const handleOnline = () => {
      setIsOnline(true);
      // Process pending operations
      pendingOperations.forEach(operation => operation());
      setPendingOperations([]);
    };

    const handleOffline = () => setIsOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, [pendingOperations]);

  return { isOnline, queueOperation: setPendingOperations };
};
```

## Configuration Options

### Pagination Configuration

```typescript
interface PaginationConfig {
  pageSize: number;              // Items per page (default: 20)
  prefetchPages: number;         // Pages to prefetch (default: 2)
  cacheSize: number;            // Cache size in pages (default: 50)
  cacheTTL: number;             // Cache TTL in ms (default: 5 minutes)
  enablePreload: boolean;       // Enable prefetching (default: true)
  enableVirtualization: boolean; // Enable virtualization (default: false)
}
```

### Performance Thresholds

```typescript
const performanceThresholds = {
  loadTime: 2000,        // Maximum load time (ms)
  renderTime: 16,        // Maximum render time (ms) for 60fps
  memoryUsage: 50000000, // Maximum memory usage (bytes)
  cacheHitRate: 0.8,     // Minimum cache hit rate
  apiLatency: 500        // Maximum API latency (ms)
};
```

## Testing and Debugging

### Performance Testing

```typescript
// Performance test utilities
export const performanceTests = {
  async measurePageLoad(url: string): Promise<PerformanceMetrics> {
    const startTime = performance.now();

    await fetch(url);

    const endTime = performance.now();
    return {
      loadTime: endTime - startTime,
      // ... other metrics
    };
  },

  async stressTestPagination(loadFunction: Function, pages: number) {
    const metrics = [];

    for (let i = 0; i < pages; i++) {
      const start = performance.now();
      await loadFunction(`cursor_${i}`);
      const end = performance.now();

      metrics.push({
        page: i,
        loadTime: end - start,
        memoryUsage: performance.memory?.usedJSHeapSize
      });
    }

    return metrics;
  }
};
```

### Debug Tools

```typescript
// Development-only performance debugging
if (process.env.NODE_ENV === 'development') {
  // Add performance debug panel
  import('./PerformanceDebugPanel').then(({ PerformanceDebugPanel }) => {
    const debugPanel = document.createElement('div');
    debugPanel.id = 'performance-debug';
    document.body.appendChild(debugPanel);

    ReactDOM.render(<PerformanceDebugPanel />, debugPanel);
  });
}
```

## Production Deployment

### Monitoring Setup

1. **Error Tracking**: Sentry, Bugsnag, or similar
2. **Performance Monitoring**: New Relic, DataDog, or custom solution
3. **Analytics**: Google Analytics, Mixpanel for user behavior
4. **Infrastructure**: CDN, database optimization, server monitoring

### Performance Budgets

```typescript
// CI/CD performance budgets
const performanceBudgets = {
  bundleSize: 250000,     // 250KB maximum bundle size
  loadTime: 3000,         // 3s maximum load time
  firstContentfulPaint: 1500, // 1.5s maximum FCP
  largestContentfulPaint: 2500, // 2.5s maximum LCP
  cumulativeLayoutShift: 0.1,   // Maximum CLS score
};
```

This comprehensive pagination and performance system ensures optimal user experience while maintaining excellent performance characteristics across all devices and network conditions.