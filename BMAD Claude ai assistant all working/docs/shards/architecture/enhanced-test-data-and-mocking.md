# Enhanced Test Data and Mocking

```javascript
// TestFramework.gs - Enhanced testing utilities
class TestFramework {
  static setUp() {
    // Initialize test environment
    this.mocks = {};
    this.fixtures = new TestFixtures();
    this.assertions = 0;
    this.failures = [];
  }
  
  static tearDown() {
    // Clean up test environment
    this.clearMocks();
    this.fixtures.cleanup();
    
    // Report results
    Logger.log(`Tests complete: ${this.assertions} assertions, ${this.failures.length} failures`);
    if (this.failures.length > 0) {
      Logger.log('Failures:');
      this.failures.forEach(f => Logger.log(f));
    }
  }
  
  static mock(service, method, returnValue) {
    const key = `${service}.${method}`;
    this.mocks[key] = {
      returnValue: returnValue,
      calls: []
    };
    
    // Override actual method
    const original = global[service][method];
    global[service][method] = function(...args) {
      TestFramework.mocks[key].calls.push(args);
      return TestFramework.mocks[key].returnValue;
    };
    
    // Store original for restoration
    this.mocks[key].original = original;
  }
  
  static clearMocks() {
    Object.entries(this.mocks).forEach(([key, mock]) => {
      const [service, method] = key.split('.');
      global[service][method] = mock.original;
    });
    this.mocks = {};
  }
  
  static assert(condition, message) {
    this.assertions++;
    if (!condition) {
      this.failures.push(message || 'Assertion failed');
      throw new Error(message || 'Assertion failed');
    }
  }
  
  static assertEquals(expected, actual, message) {
    this.assert(
      JSON.stringify(expected) === JSON.stringify(actual),
      message || `Expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`
    );
  }
}

// TestFixtures.gs - Test data management
class TestFixtures {
  constructor() {
    this.data = {
      users: [
        { email: 'test@company.com', name: 'Test User', timezone: 'America/Los_Angeles' }
      ],
      emails: [
        {
          id: 'msg123',
          threadId: 'thread123',
          from: 'sender@example.com',
          to: 'test@company.com',
          subject: 'Test Email',
          body: 'This is a test email',
          date: new Date().toISOString()
        }
      ],
      events: [
        {
          id: 'event123',
          summary: 'Test Meeting',
          start: { dateTime: '2024-01-15T10:00:00-08:00' },
          end: { dateTime: '2024-01-15T11:00:00-08:00' },
          attendees: [{ email: 'test@company.com' }]
        }
      ]
    };
  }
  
  get(type, index = 0) {
    return this.data[type][index];
  }
  
  create(type, overrides = {}) {
    const base = this.get(type);
    return { ...base, ...overrides };
  }
  
  cleanup() {
    // Clean up any test data created
    CacheService.getUserCache().removeAll(['test_']);
  }
}

// Example test
function testGmailAgent() {
  TestFramework.setUp();
  
  try {
    // Arrange
    const agent = new GmailAgent();
    const testEmail = TestFramework.fixtures.create('emails', {
      subject: 'Important Test'
    });
    
    TestFramework.mock('Gmail.Users.Messages', 'list', {
      messages: [testEmail]
    });
    
    // Act
    const result = agent.listEmails({ maxResults: 10 });
    
    // Assert
    TestFramework.assert(result.success, 'Should return success');
    TestFramework.assertEquals(1, result.data.length, 'Should return one email');
    TestFramework.assertEquals('Important Test', result.data[0].subject);
    
  } finally {
    TestFramework.tearDown();
  }
}
```
