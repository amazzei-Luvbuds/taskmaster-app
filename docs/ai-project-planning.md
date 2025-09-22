# AI-Powered Project Planning

This feature provides AI-generated project plans for tasks using Mistral AI and Google Gemini. It generates structured implementation roadmaps with goals, benefits, milestones, and risk assessments.

## Features

- **Dual AI Provider Support**: Choose between Mistral AI and Google Gemini
- **Comprehensive Planning**: Goals, benefits, milestones, resources, risks, and success metrics
- **Smart Caching**: Responses are cached to optimize performance and reduce API costs
- **Persistent Storage**: Plans are stored locally and persist across sessions
- **Interactive UI**: Expandable sections with detailed milestone breakdowns
- **Department Context**: Plans are tailored to specific department needs

## Configuration

### Environment Variables

Add the following to your `.env.local` file:

```bash
# AI Services Configuration
# Configure at least one AI provider

# Mistral AI Configuration
VITE_MISTRAL_API_KEY=your_mistral_api_key_here

# Google Gemini Configuration
VITE_GEMINI_API_KEY=your_gemini_api_key_here

# AI Settings (optional)
VITE_DEFAULT_AI_PROVIDER=mistral  # or 'gemini'
```

### API Keys Setup

#### Mistral AI
1. Visit [Mistral AI Console](https://console.mistral.ai/)
2. Create an account and generate an API key
3. Add the key to `VITE_MISTRAL_API_KEY`

#### Google Gemini
1. Visit [Google AI Studio](https://aistudio.google.com/)
2. Create an API key for Gemini
3. Add the key to `VITE_GEMINI_API_KEY`

## Usage

### In Task Form

1. **Open a task** for editing or create a new task
2. **Fill in basic details** (title, description, department)
3. **Click "Generate Project Plan"** in the AI Project Planning section
4. **Select AI provider** (Mistral or Gemini) from the dropdown
5. **Click Generate** to create the plan
6. **Expand sections** to view detailed goals, milestones, and requirements

### Project Plan Components

#### Goals
Clear, measurable objectives for the project

#### Benefits
Expected outcomes and value delivery

#### Milestones
Structured implementation phases with:
- Estimated duration
- Dependencies
- Deliverables
- Sequential numbering

#### Resources
Required tools, personnel, and materials

#### Risk Assessment
Potential challenges and mitigation strategies

#### Success Metrics
Measurable criteria for project success

## Technical Architecture

### Services

#### AIService (`src/services/aiService.ts`)
- Handles communication with AI providers
- Manages provider selection and fallbacks
- Implements response parsing and validation
- Provides caching mechanisms

#### ProjectPlanStorage (`src/services/projectPlanStorage.ts`)
- Local storage management for plans
- Automatic expiration (24 hours)
- Import/export functionality
- Storage statistics and cleanup

### Components

#### AIProjectPlan (`src/components/AIProjectPlan.tsx`)
- Main UI component for plan generation and display
- Provider selection interface
- Expandable sections for plan details
- Error handling and loading states

### Data Flow

1. **User Input**: Task details entered in form
2. **AI Request**: Structured prompt sent to selected AI provider
3. **Response Processing**: JSON response parsed and validated
4. **Local Storage**: Plan cached for persistence
5. **UI Display**: Interactive plan rendered with expandable sections

## Plan Generation Process

### Prompt Engineering

The system generates context-aware prompts including:
- Task title and description
- Department context
- Structured JSON schema requirements
- Department-specific considerations

### Response Processing

AI responses are processed to:
- Extract JSON from markdown code blocks
- Validate required fields
- Generate unique IDs for milestones
- Handle parsing errors gracefully

### Caching Strategy

- **Cache Key**: Combination of provider, task title, and description
- **TTL**: 24 hours for stored plans
- **Performance**: Reduces API calls and improves response time
- **Cost Optimization**: Prevents redundant AI requests

## Error Handling

### Configuration Errors
- Missing API keys detected and reported
- Provider availability checked on component mount
- Clear configuration instructions provided

### API Errors
- Network failures handled gracefully
- Provider-specific error messages
- Retry mechanisms for transient failures

### Parsing Errors
- Robust JSON extraction from AI responses
- Fallback handling for malformed responses
- Default values for missing fields

## Best Practices

### For Users
1. **Provide detailed task descriptions** for better AI context
2. **Select appropriate department** for relevant suggestions
3. **Review and customize** generated plans as needed
4. **Use regenerate** if the first plan doesn't meet needs

### For Developers
1. **Monitor API usage** to manage costs
2. **Implement rate limiting** if needed
3. **Cache responses** aggressively to reduce calls
4. **Handle failures gracefully** with user-friendly messages

## Cost Considerations

### API Costs
- Mistral AI: Pay-per-token pricing
- Google Gemini: Free tier available, then pay-per-use
- Caching reduces repeated requests significantly

### Optimization Strategies
- Local storage prevents re-generation of identical plans
- Structured prompts minimize token usage
- Response size limits prevent excessive costs

## Future Enhancements

### Planned Features
- [ ] Plan editing and customization
- [ ] Team collaboration on plans
- [ ] Progress tracking against milestones
- [ ] Integration with calendar systems
- [ ] Export to project management tools

### Potential Integrations
- Microsoft Project export
- Notion workspace creation
- Slack milestone notifications
- GitHub issue generation

## Troubleshooting

### Common Issues

#### No AI Providers Configured
**Problem**: Yellow warning about missing configuration
**Solution**: Add at least one API key to environment variables

#### Plan Generation Fails
**Problem**: Error message during generation
**Solution**:
1. Check API key validity
2. Verify internet connection
3. Try different AI provider
4. Reduce task description length

#### Plans Not Persisting
**Problem**: Plans disappear after page refresh
**Solution**:
1. Check browser local storage permissions
2. Clear browser cache and try again
3. Ensure task has a valid ID

### Debug Mode

Enable debug logging by adding:
```bash
VITE_DEBUG_AI=true
```

This will log:
- API requests and responses
- Cache operations
- Error details
- Performance metrics

## Security Considerations

### Data Privacy
- Task data sent to AI providers for processing
- No sensitive data should be included in task descriptions
- Plans stored locally, not transmitted to external services

### API Key Security
- Store API keys in environment variables only
- Never commit API keys to version control
- Use different keys for development and production

### Rate Limiting
- Implement client-side rate limiting if needed
- Monitor API usage to prevent unexpected charges
- Consider implementing user quotas for high-volume usage