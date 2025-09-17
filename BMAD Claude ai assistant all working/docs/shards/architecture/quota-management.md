# Quota Management

## Comprehensive Quota Tracking

```javascript
// QuotaManager.gs
class QuotaManager {
  constructor(service) {
    this.service = service;
    this.cache = CacheService.getUserCache();
    this.props = PropertiesService.getUserProperties();
  }
  
  static LIMITS = {
    // Per minute limits
    'gemini_call': { limit: 60, window: 60 },
    
    // Per day limits
    'gmail_send': { limit: 100, window: 86400 },
    'gmail_read': { limit: 20000, window: 86400 },
    'calendar_create': { limit: 500, window: 86400 },
    'calendar_read': { limit: 50000, window: 86400 },
    'tasks_create': { limit: 1000, window: 86400 },
    
    // Per second limits
    'gmail_api': { limit: 250, window: 1 }
  };
  
  checkQuota(operation) {
    const key = `${this.service}_${operation}`;
    const config = QuotaManager.LIMITS[key];
    
    if (!config) {
      return true; // No limit defined
    }
    
    const quotaKey = `quota_${key}_${this.getWindow_(config.window)}`;
    const count = Number(this.cache.get(quotaKey) || 0);
    
    if (count >= config.limit) {
      const error = new QuotaExceededError(
        `Quota exceeded for ${this.service}.${operation}: ${count}/${config.limit}`
      );
      error.retryAfter = this.getRetryAfter_(config.window);
      throw error;
    }
    
    // Increment counter
    this.cache.put(quotaKey, String(count + 1), config.window);
    
    // Track for analytics
    this.trackUsage_(key, count + 1, config.limit);
    
    return true;
  }
  
  getWindow_(seconds) {
    const now = new Date();
    if (seconds === 1) return now.getSeconds();
    if (seconds === 60) return now.getMinutes();
    if (seconds === 86400) return now.getDate();
    return now.getTime();
  }
  
  getRetryAfter_(window) {
    const now = new Date();
    if (window === 1) return 1000;
    if (window === 60) return (60 - now.getSeconds()) * 1000;
    if (window === 86400) {
      const tomorrow = new Date(now);
      tomorrow.setDate(tomorrow.getDate() + 1);
      tomorrow.setHours(0, 0, 0, 0);
      return tomorrow - now;
    }
    return window * 1000;
  }
  
  trackUsage_(operation, current, limit) {
    // Store usage metrics for dashboard
    const metrics = JSON.parse(this.props.getProperty('usage_metrics') || '{}');
    const today = new Date().toDateString();
    
    if (!metrics[today]) {
      metrics[today] = {};
    }
    
    metrics[today][operation] = {
      current: current,
      limit: limit,
      percentage: (current / limit) * 100,
      timestamp: new Date().toISOString()
    };
    
    this.props.setProperty('usage_metrics', JSON.stringify(metrics));
  }
  
  getUsageReport() {
    const metrics = JSON.parse(this.props.getProperty('usage_metrics') || '{}');
    const today = new Date().toDateString();
    const todayMetrics = metrics[today] || {};
    
    const report = Object.entries(QuotaManager.LIMITS).map(([key, config]) => {
      const usage = todayMetrics[key] || { current: 0, limit: config.limit };
      return {
        operation: key,
        current: usage.current,
        limit: config.limit,
        percentage: (usage.current / config.limit) * 100,
        remaining: config.limit - usage.current,
        willResetIn: this.getRetryAfter_(config.window)
      };
    });
    
    return report;
  }
}

// Custom error class
class QuotaExceededError extends Error {
  constructor(message) {
    super(message);
    this.name = 'QuotaExceeded';
    this.retryAfter = 0;
  }
}
```
