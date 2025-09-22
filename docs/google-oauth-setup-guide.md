# Google OAuth 2.0 Setup Guide for TaskMaster

This guide walks you through setting up Google OAuth 2.0 authentication for the TaskMaster application.

## Prerequisites
- Google account
- Access to Google Cloud Console
- TaskMaster project cloned locally

## Step 1: Google Cloud Console Setup

### 1.1 Create/Select Project
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Either:
   - **New Project**: Click "Select a project" → "New Project" → Enter "TaskMaster OAuth" → Create
   - **Existing Project**: Select your existing project
3. **Important**: Note your Project ID (shown in project selector)

### 1.2 Enable Required APIs
1. Go to **APIs & Services** → **Library**
2. Search and enable these APIs:
   - **Google+ API** (for basic profile info)
   - **People API** (for advanced profile data)
   - **OAuth2 API** (automatically enabled with credentials)





### 1.3 Configure OAuth Consent Screen
1. Go to **APIs & Services** → **OAuth consent screen**
2. Choose **External** (unless you have Google Workspace)
3. Fill out **OAuth consent screen**:
   ```
   App name: TaskMaster
   User support email: [your email]
   App logo: [optional - upload TaskMaster logo]
   App domain: [your domain or leave empty for development]
   Authorized domains: [your domain or leave empty]
   Developer contact: [your email]
   ```
4. **Scopes** page: Click "Add or Remove Scopes" and add:
   - `email` (See your primary Google Account email address)
   - `profile` (See your personal info)
   - `openid` (Associate you with your personal info on Google)

5. **Test users** (for development):
   - Add Gmail addresses that will test the application
   - Add: `amazzei@luvbuds.co` (your main account)

### 1.4 Create OAuth 2.0 Credentials
1. Go to **APIs & Services** → **Credentials**
2. Click **+ CREATE CREDENTIALS** → **OAuth client ID**
3. Application type: **Web application**
4. Name: `TaskMaster Web Client`
5. **Authorized JavaScript origins** (for development):
   ```
   http://localhost:5173
   http://127.0.0.1:5173
   ```
6. **Authorized redirect URIs**:
   ```
   http://localhost:5173/auth/callback
   http://127.0.0.1:5173/auth/callback
   ```
7. Click **Create**
8. **Copy the credentials** - you'll need:
   - Client ID (ends with `.apps.googleusercontent.com`)
   - Client Secret

## Step 2: Configure Environment Variables

### 2.1 Update Local Environment
1. Open `taskmaster-react/.env.local`
2. Replace the placeholder values:
   ```env
   # Replace with your actual credentials from Google Cloud Console
   VITE_GOOGLE_CLIENT_ID=123456789012-abcdefghijklmnopqrstuvwxyz123456.apps.googleusercontent.com
   VITE_GOOGLE_CLIENT_SECRET=ABCDEF-GhIjKlMnOpQrStUvWxYz

   # These should already be correct for local development
   VITE_OAUTH_REDIRECT_URI=http://localhost:5173/auth/callback
   VITE_OAUTH_SCOPES=openid email profile
   ```

### 2.2 Verify Configuration
The application should now have:
- ✅ Google Cloud project with OAuth enabled
- ✅ OAuth consent screen configured
- ✅ Web application credentials created
- ✅ Environment variables updated

## Step 3: Production Setup (Future)

When deploying to production, you'll need to:

1. **Update Authorized Origins** in Google Cloud Console:
   ```
   https://yourdomain.com
   ```

2. **Update Authorized Redirect URIs**:
   ```
   https://yourdomain.com/auth/callback
   ```

3. **Update Environment Variables** for production:
   ```env
   VITE_OAUTH_REDIRECT_URI=https://yourdomain.com/auth/callback
   ```

4. **Publish OAuth App**:
   - In OAuth consent screen, submit for verification if needed
   - Remove test user restrictions

## Security Notes

- **Never commit `.env.local`** to version control
- **Client Secret** should be kept secure (though for public web apps, it's less critical)
- **Redirect URIs** must match exactly what's configured in Google Cloud Console
- **HTTPS required** for production

## Testing

After setup, you can test authentication by:
1. Starting the dev server: `npm run dev`
2. Navigating to the login page
3. Clicking "Sign in with Google"
4. Verifying successful authentication and user data retrieval

## Troubleshooting

### Common Issues:
- **400: redirect_uri_mismatch**: Check redirect URIs match exactly
- **403: access_blocked**: Add email to test users in OAuth consent screen
- **400: invalid_request**: Check client ID format and scopes

### Debug Mode:
Set `VITE_DEBUG_API=true` in `.env.local` for detailed OAuth logs.

---

**Next Steps**: Once this setup is complete, proceed to implement the frontend OAuth integration in React.