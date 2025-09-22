# Epic: Gmail Authentication & Department Permissions Integration

## Epic Overview
**Goal:** Implement real Gmail/Google OAuth authentication to replace mock authentication system and enable true department-based permissions tied to verified email accounts.

**Business Value:** Ensure security, user verification, and proper access control by authenticating users against their actual Gmail accounts before granting department-specific permissions.

**Current State:** Demo system with hardcoded users and simple password authentication
**Target State:** Full Google OAuth integration with email-verified department permissions

---

## Story 1: Google OAuth 2.0 Setup & Configuration
**Story Points:** 5
**Priority:** Must Have

**As a** system administrator
**I want** to configure Google OAuth 2.0 for the application
**So that** users can authenticate with their Gmail accounts

### Acceptance Criteria:
- [ ] Google Cloud Console project created with OAuth 2.0 credentials
- [ ] OAuth consent screen configured with proper scopes
- [ ] Client ID and secret securely stored in environment variables
- [ ] Redirect URIs configured for development and production
- [ ] Proper error handling for OAuth failures

### Technical Requirements:
- Set up Google Cloud Console project
- Configure OAuth 2.0 credentials
- Define required scopes: `email`, `profile`, `openid`
- Configure environment variables for client credentials
- Set up proper redirect URIs

### Definition of Done:
- OAuth credentials are configured and tested
- Environment variables are properly set
- Documentation exists for OAuth setup

---

## Story 2: Frontend OAuth Integration
**Story Points:** 8
**Priority:** Must Have

**As a** user
**I want** to log in with my Gmail account
**So that** I can access the system with verified credentials

### Acceptance Criteria:
- [ ] Google Sign-In button implemented in React
- [ ] OAuth flow handles authorization and token exchange
- [ ] User profile information extracted from Google ID token
- [ ] JWT tokens stored securely (httpOnly cookies or secure storage)
- [ ] Logout functionality clears all authentication data
- [ ] Loading states and error handling for auth flow

### Technical Requirements:
- Install and configure `@google-cloud/auth-library` or similar
- Implement OAuth flow in React components
- Create authentication context/hooks
- Implement secure token storage
- Add loading and error states
- Create logout functionality

### Definition of Done:
- Users can successfully log in with Gmail
- Authentication state is managed properly
- Logout works correctly
- Error handling covers all failure scenarios

---

## Story 3: Backend Authentication Middleware
**Story Points:** 6
**Priority:** Must Have

**As a** system
**I want** to verify JWT tokens on API requests
**So that** only authenticated users can access protected resources

### Acceptance Criteria:
- [ ] JWT token verification middleware implemented
- [ ] Google ID token validation against Google's public keys
- [ ] User email extraction from verified tokens
- [ ] Protected API endpoints require valid authentication
- [ ] Proper error responses for invalid/expired tokens

### Technical Requirements:
- Implement JWT verification middleware (PHP and/or Node.js)
- Validate tokens against Google's public key endpoints
- Extract user email and profile from validated tokens
- Protect all sensitive API endpoints
- Return appropriate HTTP status codes for auth failures

### Definition of Done:
- All API endpoints are properly protected
- Token validation works correctly
- Appropriate error responses are returned
- User information is extracted from tokens

---

## Story 4: User Database Integration with Gmail Verification
**Story Points:** 5
**Priority:** Must Have

**As a** system administrator
**I want** users to be automatically matched to their department profiles
**So that** Gmail-authenticated users get appropriate permissions

### Acceptance Criteria:
- [ ] User profiles linked to Gmail addresses in database
- [ ] Automatic user lookup by authenticated email address
- [ ] New user registration flow for first-time Gmail users
- [ ] User profile updates when Gmail profile changes
- [ ] Admin interface for managing user-email mappings

### Technical Requirements:
- Update user database schema to link Gmail addresses
- Implement user lookup by email address
- Create new user registration workflow
- Update existing admin panel for email management
- Handle profile synchronization

### Definition of Done:
- Users are automatically matched to profiles via Gmail
- New users can be registered and assigned departments
- Admin panel supports email-based user management

---

## Story 5: Department Permissions with Real Authentication
**Story Points:** 7
**Priority:** Must Have

**As a** department member
**I want** my department permissions to be based on my verified Gmail account
**So that** I only see and edit tasks appropriate to my verified role

### Acceptance Criteria:
- [ ] Department filtering based on authenticated user's email
- [ ] Task editing restrictions enforced for authenticated users
- [ ] Sensitive field visibility based on verified department membership
- [ ] Manager override permissions for authenticated managers
- [ ] Real-time permission updates when user roles change

### Technical Requirements:
- Replace mock authentication in department permission system
- Integrate real user lookup with permission calculations
- Update React components to use authenticated user context
- Implement real-time permission checking
- Add role-based access control

### Definition of Done:
- All department permissions work with real Gmail authentication
- Mock user selection is removed
- Permission system uses actual authenticated user data
- Role changes are reflected immediately

---

## Story 6: Session Management & Security
**Story Points:** 4
**Priority:** Must Have

**As a** security-conscious system
**I want** proper session management and security measures
**So that** user authentication is secure and reliable

### Acceptance Criteria:
- [ ] Secure session management with appropriate timeouts
- [ ] Token refresh mechanism for long-lived sessions
- [ ] CSRF protection for state-changing operations
- [ ] Secure cookie configuration
- [ ] Session invalidation on logout

### Technical Requirements:
- Implement secure session storage
- Add token refresh mechanism
- Configure CSRF protection
- Set up secure cookie policies
- Implement proper session cleanup

### Definition of Done:
- Sessions are secure and properly managed
- Token refresh works automatically
- CSRF protection is active
- Security best practices are followed

---

## Story 7: Admin Panel OAuth Integration
**Story Points:** 3
**Priority:** Should Have

**As a** system administrator
**I want** the admin panel to use Gmail authentication
**So that** admin access is properly secured and audited

### Acceptance Criteria:
- [ ] Admin panel requires Gmail authentication
- [ ] Admin users verified against whitelist of admin email addresses
- [ ] Admin actions logged with authenticated user information
- [ ] Simple password authentication removed
- [ ] Admin role verification through Gmail account

### Technical Requirements:
- Replace password authentication in admin panel
- Implement Gmail-based admin verification
- Add admin email whitelist configuration
- Log admin actions with user identity
- Update admin UI for OAuth login

### Definition of Done:
- Admin panel uses Gmail authentication
- Only authorized Gmail accounts can access admin functions
- All admin actions are properly logged

---

## Story 8: Testing & Validation
**Story Points:** 6
**Priority:** Must Have

**As a** development team
**I want** comprehensive testing of the authentication system
**So that** the OAuth integration is reliable and secure

### Acceptance Criteria:
- [ ] Unit tests for authentication components
- [ ] Integration tests for OAuth flow
- [ ] End-to-end tests for complete user journeys
- [ ] Security testing for token handling
- [ ] Performance testing for authentication endpoints

### Technical Requirements:
- Write unit tests for auth components
- Create integration tests for OAuth flow
- Implement E2E tests for user authentication
- Test security aspects of token handling
- Performance test authentication endpoints

### Definition of Done:
- All authentication components have adequate test coverage
- OAuth flow is thoroughly tested
- Security aspects are validated
- Performance requirements are met

---

## Epic Acceptance Criteria:
- [ ] Users can log in with their Gmail accounts
- [ ] Department permissions are enforced based on verified email addresses
- [ ] Mock authentication system is completely removed
- [ ] All API endpoints are properly secured
- [ ] Admin panel uses Gmail authentication
- [ ] System is thoroughly tested and secure

## Technical Dependencies:
- Google Cloud Console access for OAuth setup
- Update to React authentication system
- Backend API authentication middleware
- Database schema updates for user-email mapping
- Security review and testing

## Risks & Mitigation:
- **Risk:** Google OAuth API changes
  **Mitigation:** Use stable OAuth 2.0 APIs and monitor Google's deprecation notices

- **Risk:** Token security vulnerabilities
  **Mitigation:** Follow OAuth 2.0 security best practices and conduct security review

- **Risk:** User onboarding complexity
  **Mitigation:** Create clear user registration flow and admin tools

## Definition of Epic Done:
✅ All stories are completed and acceptance criteria met
✅ Security review passed
✅ Performance requirements satisfied
✅ Documentation updated
✅ Deployed to production and working correctly