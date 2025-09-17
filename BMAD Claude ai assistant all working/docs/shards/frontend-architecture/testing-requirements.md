# Testing Requirements

## Component Test Template

```javascript
// TestRunner.js - Simple testing framework for Apps Script frontend
class TestRunner {
  constructor() {
    this.tests = [];
    this.results = [];
  }
  
  describe(description, testFn) {
    this.tests.push({
      description,
      testFn,
      type: 'suite'
    });
  }
  
  it(description, testFn) {
    this.tests.push({
      description,
      testFn,
      type: 'test'
    });
  }
  
  async run() {
    console.log('🧪 Running tests...');
    
    for (const test of this.tests) {
      try {
        if (test.type === 'suite') {
          console.group(test.description);
          await test.testFn();
          console.groupEnd();
        } else {
          await test.testFn();
          this.results.push({
            description: test.description,
            passed: true
          });
          console.log('✅', test.description);
        }
      } catch (error) {
        this.results.push({
          description: test.description,
          passed: false,
          error: error.message
        });
        console.error('❌', test.description, error);
      }
    }
    
    this.printSummary();
  }
  
  printSummary() {
    const passed = this.results.filter(r => r.passed).length;
    const failed = this.results.filter(r => !r.passed).length;
    
    console.log('\n📊 Test Results:');
    console.log(`Passed: ${passed}`);
    console.log(`Failed: ${failed}`);
    console.log(`Total: ${this.results.length}`);
    
    if (failed > 0) {
      console.log('\n❌ Failed tests:');
      this.results
        .filter(r => !r.passed)
        .forEach(r => console.log(`  - ${r.description}: ${r.error}`));
    }
  }
  
  expect(actual) {
    return {
      toBe: (expected) => {
        if (actual !== expected) {
          throw new Error(`Expected ${expected}, got ${actual}`);
        }
      },
      toEqual: (expected) => {
        if (JSON.stringify(actual) !== JSON.stringify(expected)) {
          throw new Error(`Expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);
        }
      },
      toBeTruthy: () => {
        if (!actual) {
          throw new Error(`Expected truthy value, got ${actual}`);
        }
      },
      toBeFalsy: () => {
        if (actual) {
          throw new Error(`Expected falsy value, got ${actual}`);
        }
      },
      toContain: (item) => {
        if (!actual.includes(item)) {
          throw new Error(`Expected to contain ${item}`);
        }
      }
    };
  }
}

// Example component test
const testRunner = new TestRunner();

testRunner.describe('ChatInterface Component', () => {
  testRunner.it('should initialize with empty messages', () => {
    const chat = new ChatInterface('#test-container');
    testRunner.expect(chat.messages).toEqual([]);
  });
  
  testRunner.it('should add message to state', () => {
    const chat = new ChatInterface('#test-container');
    chat.addMessage('Hello', 'user');
    testRunner.expect(chat.messages.length).toBe(1);
    testRunner.expect(chat.messages[0].text).toBe('Hello');
  });
  
  testRunner.it('should render messages in DOM', () => {
    document.body.innerHTML = '<div id="test-container"></div>';
    const chat = new ChatInterface('#test-container');
    chat.addMessage('Test message', 'user');
    chat.render();
    
    const messageElements = document.querySelectorAll('.jarvis-chat__message');
    testRunner.expect(messageElements.length).toBe(1);
  });
  
  testRunner.it('should handle voice input', async () => {
    const chat = new ChatInterface('#test-container');
    const mockAudio = new Blob(['audio'], { type: 'audio/webm' });
    
    // Mock the API call
    window.google = {
      script: {
        run: {
          withSuccessHandler: (fn) => ({
            withFailureHandler: () => ({
              transcribeAudio: () => {
                fn({ text: 'Transcribed text' });
              }
            })
          })
        }
      }
    };
    
    await chat.handleVoiceInput(mockAudio);
    testRunner.expect(chat.messages[0].text).toBe('Transcribed text');
  });
});

// Run tests
testRunner.run();
```

## Testing Best Practices
1. **Unit Tests**: Test individual components in isolation
2. **Integration Tests**: Test component interactions with mocked backend
3. **Manual Testing**: Use Chrome DevTools for debugging and validation
4. **Accessibility Testing**: Use Chrome Lighthouse and axe DevTools
5. **Performance Testing**: Monitor bundle size and runtime performance
6. **Mobile Testing**: Test on actual devices for touch interactions
