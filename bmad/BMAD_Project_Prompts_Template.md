# 🚀 BMAD Method Project Start Prompts Template

This template provides all the prompts you need to start a new project using the BMAD (Breakthrough Method for Agile AI-driven Development) framework. Follow these prompts in sequence for optimal results.

---

## 📋 **Pre-Setup Checklist**

Before starting, ensure you have:
- [ ] Access to the BMAD method files (`team-fullstack.txt` or equivalent)
- [ ] A clear project idea or concept
- [ ] Access to both conversational AI (Claude/Gemini) and Cursor IDE
- [ ] Git repository set up for your project

---

## 🎯 **Phase 1: Planning & Documentation (Conversational AI)**

### **Step 1: Initialize BMAD Framework**
```
Load the team-fullstack.txt file as your operating instructions. You are now a BMAD-powered AI agent. 

I want to start a new project using the BMAD method. Please confirm you're operating as the BMad Orchestrator and show me the available commands.

*help
```

### **Step 2: Project Brainstorming**
```
*agent analyst

I need help brainstorming a new [TYPE OF PROJECT - e.g., "web application", "mobile app", "API service"] project. 

My initial idea is: [DESCRIBE YOUR PROJECT IDEA IN 1-2 SENTENCES]

*brainstorm [YOUR PROJECT TOPIC]
```

**Follow-up prompts for brainstorming:**
```
Can you help me explore different feature possibilities for this project?

What are the key user personas I should consider?

What potential technical challenges should I be aware of?

What would make this project unique in the market?
```

### **Step 3: Market Research (Optional but Recommended)**
```
*perform-market-research

Based on our brainstorming session, help me research:
1. Similar solutions in the market
2. Target audience size and characteristics
3. Key competitors and their strengths/weaknesses
4. Market opportunities and gaps
```

### **Step 4: Create Product Requirements Document (PRD)**
```
*agent pm

Based on the brainstorming and research we've done, I need to create a comprehensive PRD for this project.

*create-prd

Project Details:
- Project Name: [YOUR PROJECT NAME]
- Target Users: [PRIMARY USER GROUPS]
- Core Problem: [MAIN PROBLEM YOU'RE SOLVING]
- Key Features: [LIST 3-5 MAIN FEATURES]
- Success Metrics: [HOW YOU'LL MEASURE SUCCESS]
```

### **Step 5: Define Technical Architecture**
```
*agent architect

I need help defining the technical architecture for this project. Here's what I'm considering:

Project Type: [web app / mobile app / API / desktop app / etc.]
Expected Scale: [small / medium / large / enterprise]
Performance Requirements: [any specific performance needs]
Integration Needs: [external services, APIs, databases]
Team Size: [solo / small team / large team]
Timeline: [rough timeline or urgency]

Please guide me through creating the architecture document.
```

---

## 🛠️ **Phase 2: Project Setup & Development (Cursor IDE)**

### **Step 6: Project Installation & Setup**

**Terminal Command:**
```bash
cd /path/to/your/project
npx bmad-method install
```

**Manual Steps:**
1. Create a `docs` folder in your project root
2. Save your `prd.md` and `architecture.md` files to the `docs` folder
3. Open your project in Cursor

### **Step 7: Initialize Product Owner Agent**

**Cursor Chat Prompt:**
```
I'm starting a new project using the BMAD method. I have my PRD and architecture documents ready in the docs/ folder.

Please help me initialize as the Product Owner and shard my documents into manageable development tasks.

@PO 
/shard doc docs/prd.md docs/architecture.md
```

### **Step 8: Create Development Stories**

**Cursor Chat Prompt:**
```
Initialize the Scrum Master agent to help me create epics and stories from the sharded documents.

@Scrum Master
/draft

Please analyze the sharded documents and create:
1. High-level epics for major features
2. Detailed user stories for each epic
3. Proper story prioritization
4. Dependencies between stories
```

### **Step 9: Start Development Cycle**

**For Each Story - Implementation Prompt:**
```
I'm ready to implement Story [X.X]. I've changed its status to "approved" in the story file.

@Dev
Implement story [X.X]

Please:
1. Read the story requirements carefully
2. Follow the architectural guidelines
3. Write clean, well-documented code
4. Include appropriate error handling
5. Update the story status when complete
```

**For Each Story - Review Prompt:**
```
Story [X.X] has been implemented and is ready for review.

@Review
/review method

Please:
1. Verify all requirements are met
2. Check code quality and best practices
3. Test the implementation
4. Flag any issues or improvements needed
5. Update story status to "done" if approved
```

---

## 🔄 **Advanced Workflow Prompts**

### **Parallel Development Setup**
```
I want to set up parallel development for faster iteration. Help me:

1. Identify stories that can be developed simultaneously
2. Set up Git worktrees for parallel development
3. Plan the merge strategy for completed work

*workflow-guidance
```

### **Project Status Check**
```
*status

Please show me:
- Current progress on all stories and epics
- Any blockers or issues
- Next recommended actions
- Overall project health
```

### **Course Correction**
```
*agent pm
*correct-course

I'm experiencing [DESCRIBE ISSUE/CHALLENGE]. Help me:
1. Analyze what's not working
2. Identify root causes
3. Propose solutions
4. Update project plan if needed
```

---

## 🎨 **Specialized Project Type Prompts**

### **For Frontend/UI Projects**
```
*agent ux-expert

I need help with the user experience design for this project.

Please help me:
1. Create user journey maps
2. Design wireframes for key screens
3. Define the design system
4. Plan responsive design approach
5. Consider accessibility requirements
```

### **For API/Backend Projects**
```
*agent architect

Focus on backend architecture for this API project:

1. Database design and schema
2. API endpoint structure
3. Authentication and security
4. Performance and scaling considerations
5. Integration patterns
```

### **For Mobile App Projects**
```
*agent architect

Help me plan mobile-specific considerations:

1. Platform strategy (iOS/Android/Cross-platform)
2. Mobile UI/UX patterns
3. Performance optimization
4. Offline functionality
5. App store requirements
```

---

## 🏁 **Project Completion Prompts**

### **Final Review**
```
*agent pm

We've completed all stories for this project. Please help me:

1. Conduct final project review
2. Verify all requirements are met
3. Document lessons learned
4. Plan deployment strategy
5. Create project handover documentation
```

### **Deployment Planning**
```
*agent architect

Help me plan the deployment strategy:

1. Production environment setup
2. CI/CD pipeline configuration
3. Monitoring and logging setup
4. Backup and disaster recovery
5. Performance optimization
```

---

## 💡 **Tips for Effective BMAD Usage**

### **General Best Practices:**
1. **Always start commands with `*`** - The framework requires this prefix
2. **Use numbered lists** - The agents respond well to structured input
3. **Be specific** - Provide context and details for better results
4. **Follow the sequence** - Don't skip phases unless you have a specific reason
5. **Stay in character** - Let each agent complete their work before switching

### **Troubleshooting Prompts:**
```
*help
*status
*agent [name] (to see available agents)
*task [name] (to see available tasks)
*exit (to return to orchestrator)
```

### **Emergency Reset:**
```
*exit
*help

I need to restart the BMAD process. Please reset to the BMad Orchestrator and show me available options.
```

---

## 📝 **Quick Reference**

### **Key Agents:**
- `*agent analyst` - Brainstorming, market research, competitive analysis
- `*agent pm` - PRD creation, product strategy, roadmap planning
- `*agent architect` - Technical architecture, system design
- `*agent ux-expert` - User experience design, wireframes
- `*agent po` - Story creation, backlog management (Cursor)
- `*agent dev` - Code implementation (Cursor)
- `*agent review` - Code review, quality assurance (Cursor)

### **Key Commands:**
- `*help` - Show available commands
- `*status` - Check current progress
- `*workflow-guidance` - Get workflow recommendations
- `*yolo` - Skip confirmations (use carefully)
- `*exit` - Return to orchestrator

---

**Remember:** The BMAD method is designed to be iterative and adaptive. Don't hesitate to circle back to earlier phases if you discover new requirements or need to adjust your approach!
