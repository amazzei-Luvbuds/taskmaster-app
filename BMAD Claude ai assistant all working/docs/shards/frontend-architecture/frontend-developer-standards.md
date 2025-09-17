# Frontend Developer Standards

## Critical Coding Rules
1. **Always use google.script.run for backend calls** - Never use fetch() or XMLHttpRequest
2. **Handle all async operations with promises** - Apps Script callbacks must be promisified
3. **Sanitize all user input** - Use DOMPurify or equivalent for HTML content
4. **Component cleanup on destroy** - Remove all event listeners and timers
5. **Use CSS custom properties for theming** - All colors and spacing from variables
6. **Upgrade MDL components after DOM changes** - Call componentHandler.upgradeElements()
7. **Test on actual mobile devices** - Chrome DevTools mobile emulation is not sufficient
8. **Implement proper loading states** - Every async operation needs visual feedback
9. **Cache API responses appropriately** - Use StateManager cache with TTL
10. **Handle Apps Script timeouts gracefully** - 30-second execution limit awareness

## Quick Reference

**Common Commands:**
```bash
# No build commands needed - Apps Script serves directly
# Development happens in Apps Script Editor or clasp

# Local development with clasp
clasp clone SCRIPT_ID
clasp pull
clasp push
clasp open
```

**Key Import Patterns:**
```html
<!-- In HTML files -->
<?!= include('AppCss'); ?>
<?!= include('AppJs'); ?>
```

```javascript
// Calling backend functions
google.script.run
  .withSuccessHandler(onSuccess)
  .withFailureHandler(onError)
  .backendFunction(param1, param2);
```

**File Naming Conventions:**
- HTML files: PascalCase.html
- Include files: PascalCase.html
- Backend references: match .gs file function names

**Project-Specific Patterns:**
- State management: Use global JarvisState
- API calls: Use JarvisApi service
- Components: Extend JarvisComponent base class
- Error handling: Use JarvisApi.showNotification()
- View management: Use ViewManager for navigation

---

**SAVE THIS DOCUMENT AS: `docs/frontend-architecture.md`**

This Frontend Architecture provides a complete foundation for implementing the Jarvis AI Assistant's user interface within Google Apps Script's HTML Service constraints, ensuring a responsive, performant, and user-friendly experience.