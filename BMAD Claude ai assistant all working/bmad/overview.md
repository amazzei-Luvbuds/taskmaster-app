https://github.com/bmad-code-org/BMAD-METHOD

To effectively use the BMAD (Breakthrough Method for Agile AI-driven Development) framework with an AI assistant like Cursor, you would provide a series of prompts that guide it through a structured, multi-stage software development lifecycle. This process is divided into a planning phase, which occurs in a conversational AI, and a development phase inside the IDE.

Here is a breakdown of the main parts you would need to give Cursor as prompts.

### **Phase 1: Planning & Documentation (Performed in a Chat AI like Gemini or Claude)**

This initial phase happens outside of your IDE and focuses on generating the foundational documents. You start by loading a dedicated "full stack team" agent file from the BMAD GitHub repository.

1.  **Brainstorm the Application:**
    *   **Prompt:** Start by loading the `team_fullstack.txt` file as instructions for the AI.
    *   **Command:** `*brainstorm`
    *   **What to provide:** Engage in a conversation with the AI, describing your app idea (e.g., "an iOS productivity app"). The agent will ask clarifying questions to help you define features, UI/UX concepts, and create a feature matrix and roadmap. The output is a `brainstorm.md` file.

2.  **Create the Product Requirements Document (PRD):**
    *   **Prompt:** Switch the AI's role to a Product Manager.
    *   **Command:** `*pm` followed by `*create doc`
    *   **What to provide:** The agent will initiate a step-by-step workflow to build the PRD. You will be presented with menus and options to make decisions, just as a real software team would. The final output is the `prd.md` file.

3.  **Define the Application Architecture:**
    *   **Prompt:** Switch the AI's role to an Architect.
    *   **Command:** `*architect`
    *   **What to provide:** Follow the interactive prompts to define the tech stack, component connections, and overall technical plan. This generates the `architecture.md` file.

---

### **Phase 2: Project Setup & Development in Cursor**

Once the PRD and architecture documents are ready, you move into your IDE (Cursor) to begin building the application.

1.  **Installation & Initial Setup:**
    *   **Prompt (in terminal):** Navigate to your project directory in the terminal and run the installation command provided on the BMAD GitHub repository.
    *   **Command:** `npx bmad-method install`
    *   **Manual Step:** Create a new folder named `docs` in your project's root directory.
    *   **Manual Step:** Place the `prd.md` and `architecture.md` files you generated into the `docs` folder.

2.  **Product Owner: Shard Documents into Tasks:**
    *   **Prompt (in Cursor chat):** "Initialize the Product Owner agent." (Type `@PO` and select the agent).
    *   **Command:** `/shard doc`
    *   **What to provide:** Give the agent the path to your `prd.md` and `architecture.md` files. This command breaks the documents down into smaller, manageable chunks or tasks for development.

3.  **Scrum Master: Draft Epics and Stories:**
    *   **Prompt (in a new Cursor chat):** "Initialize the Scrum Master agent." (Type `@Scrum Master` or the equivalent command).
    *   **Command:** `/draft`
    *   **What it does:** The agent reads the sharded documents and generates "epics" (large features) and "stories" (specific development tasks within an epic). Initially, these stories are marked with the status "draft."

4.  **Developer: Implement a Story:**
    *   **Manual Step:** Open the story file you want to work on (e.g., `story-1.1.md`) and change its status from `draft` to `approved`. Save the file.
    *   **Prompt (in a new Cursor chat):** "Initialize the Dev agent."
    *   **Command:** "Implement story 1.1."
    *   **What it does:** The development agent will read the story, which contains all the necessary context, subtasks, and architectural guidance, and write the corresponding code. When finished, it changes the story's status to `ready for review`.

5.  **QA/Reviewer: Test and Finalize the Story:**
    *   **Prompt (in a new Cursor chat):** "Initialize the Review agent."
    *   **Command:** `/review method`
    *   **What it does:** The agent scans the codebase to verify that the requirements of the story were met correctly. It may perform auto-fixes for minor issues. Once it confirms the implementation, it marks the story as `approved` for review and changes the final status to `done`.

6.  **Repeat the Cycle:**
    *   Continue this process by approving the next story, initializing the dev agent to implement it, and having the review agent validate it until all stories and epics are complete.

---

### **Advanced Prompt: Parallel Development Workflow**

To significantly reduce waiting time, you can instruct the AI to structure tasks for simultaneous development using Git Worktrees.

1.  **Generate Parallel Stories:**
    *   **Prompt (during the Scrum Master phase):** When initializing the Scrum Master agent, use a custom prompt: "Examine each story to determine if it can be broken into multiple non-conflicting subtasks suitable for parallel development. If so, split them into separate stories. Ensure the plan accounts for using Git Worktrees to prevent conflicts, with the intention to merge everything back to the main branch upon completion."

2.  **Execute in Parallel (Using a tool like Conductor):**
    *   For each parallel story (e.g., Story 1.1, 1.2, 1.3), create a new Git Worktree.
    *   In each worktree's respective terminal/chat, initialize a separate Dev agent and assign it one of the parallel stories.
    *   The agents will work simultaneously in their isolated environments. The total wait time is reduced to the duration of the longest single task.

3.  **Merge Completed Work:**
    *   As each story is completed and passes the review phase in its worktree, create a pull request and merge it back into the main branch.