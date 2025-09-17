# Source Tree

```plaintext
jarvis-ai-assistant/
├── Code.gs files:
│   ├── WebApp.gs                 # Entry point, HTTP routing
│   ├── Auth.gs                   # Authentication & sessions
│   ├── Orchestrator.gs           # Intent detection & routing
│   ├── BaseAgent.gs              # Abstract agent base class
│   ├── Agents/
│   │   ├── GmailAgent.gs         # Email operations
│   │   ├── CalendarAgent.gs      # Calendar operations
│   │   ├── TasksAgent.gs         # Task management
│   │   ├── DocsAgent.gs          # Document creation
│   │   └── PeopleAgent.gs        # Contact resolution
│   ├── Services/
│   │   ├── GeminiApi.gs          # Gemini AI integration
│   │   ├── CacheManager.gs       # Cache operations
│   │   ├── QuotaManager.gs       # Quota tracking
│   │   ├── ExecutionManager.gs   # Execution time management
│   │   ├── CircuitBreaker.gs     # Circuit breaker pattern
│   │   └── PropertiesManager.gs  # Properties operations
│   ├── Utils/
│   │   ├── Utils.gs              # Common utilities
│   │   ├── DateTimeUtils.gs      # Date/time helpers
│   │   ├── ValidationUtils.gs    # Input validation
│   │   └── ErrorHandler.gs       # Error handling
│   └── Tests/
│       ├── TestFramework.gs      # Test framework
│       ├── TestFixtures.gs       # Test data
│       ├── AgentTests.gs         # Agent unit tests
│       └── IntegrationTests.gs   # Integration tests
│
├── HTML files:
│   ├── Index.html                # Main application
│   ├── Login.html                # Authentication page
│   ├── Dashboard.html            # Dashboard view
│   ├── Chat.html                 # Chat interface
│   └── Components/
│       ├── AppCss.html           # Styles
│       ├── AppJs.html            # Client JavaScript
│       └── VoiceRecorder.html   # Voice component
│
└── Configuration:
    └── appsscript.json           # Manifest with scopes
```
