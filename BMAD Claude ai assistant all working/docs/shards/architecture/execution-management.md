# Execution Management

## Apps Script Execution Limits
Given Apps Script's 6-minute execution limit, the architecture implements systematic execution management:

```javascript
// ExecutionManager.gs - Core execution time management
class ExecutionManager {
  constructor(maxRuntime = 300000) { // 5 minutes, leaving 1-minute buffer
    this.startTime = Date.now();
    this.maxRuntime = maxRuntime;
    this.checkpoints = [];
  }
  
  canContinue() {
    return (Date.now() - this.startTime) < this.maxRuntime;
  }
  
  checkpoint(operation, state) {
    if (!this.canContinue()) {
      // Save state for continuation
      const continuation = {
        operation: operation,
        state: state,
        timestamp: new Date().toISOString(),
        checkpoints: this.checkpoints
      };
      
      PropertiesService.getUserProperties()
        .setProperty('continuation', JSON.stringify(continuation));
      
      // Trigger continuation via time-based trigger
      ScriptApp.newTrigger('continueLongOperation')
        .timeBased()
        .after(1000)
        .create();
        
      return false;
    }
    
    this.checkpoints.push({
      operation: operation,
      timestamp: Date.now() - this.startTime
    });
    
    return true;
  }
  
  static continue() {
    const props = PropertiesService.getUserProperties();
    const continuation = JSON.parse(props.getProperty('continuation') || '{}');
    
    if (continuation.operation) {
      // Resume operation
      const agent = AgentFactory.create(continuation.operation.agent);
      return agent.resume(continuation.state);
    }
  }
}

// Example usage in long-running operations
function processLargeEmailBatch(emails) {
  const execMgr = new ExecutionManager();
  const batchSize = 10;
  
  for (let i = 0; i < emails.length; i += batchSize) {
    const batch = emails.slice(i, i + batchSize);
    
    // Check if we can continue
    if (!execMgr.checkpoint('email_batch', {
      processed: i,
      total: emails.length,
      remaining: emails.slice(i)
    })) {
      // Will resume via trigger
      return {
        success: true,
        partial: true,
        processed: i,
        message: 'Processing will continue automatically'
      };
    }
    
    // Process batch
    processBatch(batch);
  }
  
  return {success: true, processed: emails.length};
}
```
