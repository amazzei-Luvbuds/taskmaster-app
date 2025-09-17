**Acceptance Criteria:**
- [ ] Edit modal pre-populates with current values
- [ ] Unsaved changes warning prevents data loss
- [ ] Changes appear immediately after saving
- [ ] Cancel restores original values
- [ ] Concurrent edit conflicts are handled

**Conflict Resolution:**
- Detect when another user has modified the same task
- Show diff of changes
- Allow user to merge or overwrite

### **User Story C2.4: Department-Specific Customization**
**Story Points:** 8
**Priority:** Must Have
**Sprint:** 3

**Story:**
As a **department user**
I want **to see fields and options relevant to my work**
So that **I can track information that matters to my department**

**Acceptance Criteria:**
- [ ] Sales sees deal value, client name, close date fields
- [ ] Accounting sees invoice number, amount, vendor fields
- [ ] Tech sees bug priority, component, environment fields
- [ ] Each department has unique visual branding
- [ ] Custom field validation works correctly


### **User Story C2.7: Sales Metrics Integration**
**Story Points:** 4
**Priority:** Should Have
**Sprint:** 4

**Story:**
As a **sales team member**
I want **to see HubSpot metrics alongside my tasks**
So that **I can make data-driven decisions about my sales activities**

**Acceptance Criteria:**
- [ ] HubSpot API integration works reliably
- [ ] Metrics display only in sales department
- [ ] Data refreshes automatically
- [ ] API failures don't break the page
- [ ] Metrics are relevant to task management


### **User Story A3.2: Kanban Board View**
**Story Points:** 13
**Priority:** Must Have
**Sprint:** 4-5

**Story:**
As a **department user**
I want **to view and manage tasks in a visual Kanban board**
So that **I can see workflow status and move tasks between stages**

**Acceptance Criteria:**
- [ ] Smooth drag-and-drop between columns
- [ ] Customizable columns per department
- [ ] Virtual scrolling for large datasets (500+ tasks)
- [ ] Real-time status updates when tasks move
- [ ] Mobile-friendly touch interactions
- [ ] Keyboard accessibility as alternative to drag-and-drop

**Performance Requirements:**
- Handle 500+ tasks without performance degradation
- Drag-and-drop response time < 100ms
- Column customization saves immediately

**Column Examples:**
- Sales: "Prospecting → Qualified → Proposal → Negotiation → Closed"
- Tech: "Backlog → In Progress → Code Review → Testing → Done"
- Accounting: "Pending → Review → Approved → Processing → Complete"


### **User Story A3.4: Leadership Dashboard**
**Story Points:** 4
**Priority:** Should Have
**Sprint:** 5

**Story:**
As a **leadership team member**
I want **to view performance metrics across all departments**
So that **I can make informed decisions about resource allocation**

**Acceptance Criteria:**
- [ ] Cross-department task statistics
- [ ] Completion rate trends over time
- [ ] Department performance comparison
- [ ] Exportable reports in PDF/Excel format
- [ ] Role-based access control
- [ ] Real-time data updates

**Metrics to Display:**
- Total tasks by department
- Completion rates (weekly/monthly)
- Average time to completion
- Overdue task counts
- User activity levels
- Department workload distribution

this needs to also be locked by certain emails 



