# Circuit Breaker Implementation

```javascript
// CircuitBreaker.gs
class CircuitBreaker {
  constructor(name, options = {}) {
    this.name = name;
    this.failureThreshold = options.failureThreshold || 5;
    this.resetTimeout = options.resetTimeout || 60000; // 1 minute
    this.state = 'CLOSED'; // CLOSED, OPEN, HALF_OPEN
    this.failures = 0;
    this.lastFailureTime = null;
    this.cache = CacheService.getScriptCache();
  }
  
  async execute(fn) {
    const stateKey = `circuit_${this.name}_state`;
    const savedState = this.cache.get(stateKey);
    
    if (savedState) {
      const parsed = JSON.parse(savedState);
      this.state = parsed.state;
      this.failures = parsed.failures;
      this.lastFailureTime = parsed.lastFailureTime;
    }
    
    if (this.state === 'OPEN') {
      if (Date.now() - this.lastFailureTime > this.resetTimeout) {
        this.state = 'HALF_OPEN';
        this.failures = 0;
      } else {
        throw new Error(`Circuit breaker is OPEN for ${this.name}`);
      }
    }
    
    try {
      const result = await fn();
      
      if (this.state === 'HALF_OPEN') {
        this.state = 'CLOSED';
        this.failures = 0;
      }
      
      this.saveState_();
      return result;
      
    } catch (error) {
      this.failures++;
      this.lastFailureTime = Date.now();
      
      if (this.failures >= this.failureThreshold) {
        this.state = 'OPEN';
        Logger.log(`Circuit breaker OPENED for ${this.name}`);
      }
      
      this.saveState_();
      throw error;
    }
  }
  
  saveState_() {
    const state = {
      state: this.state,
      failures: this.failures,
      lastFailureTime: this.lastFailureTime
    };
    
    this.cache.put(
      `circuit_${this.name}_state`,
      JSON.stringify(state),
      this.resetTimeout / 1000
    );
  }
  
  getStatus() {
    return {
      name: this.name,
      state: this.state,
      failures: this.failures,
      threshold: this.failureThreshold,
      willResetAt: this.state === 'OPEN' 
        ? new Date(this.lastFailureTime + this.resetTimeout)
        : null
    };
  }
}

// Usage in GeminiApi.gs
class GeminiApi {
  constructor() {
    this.circuitBreaker = new CircuitBreaker('gemini_api', {
      failureThreshold: 5,
      resetTimeout: 60000
    });
  }
  
  async generateContent(prompt) {
    return this.circuitBreaker.execute(async () => {
      // Actual API call
      const response = await UrlFetchApp.fetch(this.endpoint, {
        method: 'POST',
        headers: this.headers,
        payload: JSON.stringify({ prompt })
      });
      
      if (response.getResponseCode() !== 200) {
        throw new Error(`API error: ${response.getResponseCode()}`);
      }
      
      return JSON.parse(response.getContentText());
    });
  }
}
```
