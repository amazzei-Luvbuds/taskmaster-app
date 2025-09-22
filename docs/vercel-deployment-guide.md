# Vercel Deployment Guide for TaskMaster React

This guide walks you through deploying the TaskMaster React app to Vercel for OAuth integration.

## Prerequisites
- Vercel CLI installed (✅ done)
- TaskMaster React repository pushed to GitHub (✅ done)
- Vercel account (create at [vercel.com](https://vercel.com))

## Step 1: Login to Vercel

In your terminal, from the `taskmaster-react` directory:

```bash
cd "/Users/alexandermazzei2020/Documents/cursor projects/taskmasterdoneworking sept 16 backup/taskmaster-react"
vercel login
```

This will:
1. Show you a URL like: `https://vercel.com/oauth/device?user_code=XXXX-XXXX`
2. Open your browser and authenticate with GitHub/Google/Email
3. Return to terminal once authenticated

## Step 2: Deploy to Vercel

Once logged in, deploy:

```bash
vercel --prod
```

During deployment, Vercel will ask:
- **Set up and deploy?** → `Y`
- **Which scope?** → Choose your personal account or team
- **Link to existing project?** → `N` (first time)
- **What's your project's name?** → `taskmaster-oauth` or similar
- **In which directory is your code located?** → `./` (current directory)

## Step 3: Note Your Deployment URL

After deployment, Vercel will provide URLs like:
- **Production URL**: `https://taskmaster-oauth-xyz.vercel.app`
- **Preview URLs**: For feature branches

**Important**: Copy the production URL - you'll need it for Google OAuth configuration.

## Step 4: Configure Environment Variables

In Vercel dashboard or via CLI:

```bash
# Set environment variables for production
vercel env add VITE_GOOGLE_CLIENT_ID
vercel env add VITE_GOOGLE_CLIENT_SECRET
vercel env add VITE_OAUTH_REDIRECT_URI
vercel env add VITE_OAUTH_SCOPES
```

When prompted for values:
- `VITE_OAUTH_REDIRECT_URI` = `https://your-app-name.vercel.app/auth/callback`
- `VITE_OAUTH_SCOPES` = `openid email profile`
- Client ID and Secret = (from Google Cloud Console - to be set up next)

## Step 5: OAuth Redirect URIs

Once you have your Vercel URL, you'll configure these redirect URIs in Google Cloud Console:

**Development:**
- `http://localhost:5173/auth/callback`

**Production:**
- `https://your-app-name.vercel.app/auth/callback`

**Authorized JavaScript Origins:**
- `http://localhost:5173` (development)
- `https://your-app-name.vercel.app` (production)

## Step 6: Test Deployment

Visit your Vercel URL to confirm the app loads correctly. The OAuth functionality won't work yet until we:
1. Get the actual Google OAuth credentials
2. Set them in Vercel environment variables
3. Configure Google Cloud Console with the Vercel URLs

## Alternative: Deploy via Vercel Dashboard

If CLI gives issues:
1. Go to [vercel.com/dashboard](https://vercel.com/dashboard)
2. Click **"New Project"**
3. Import from GitHub: `alexandermazzei/taskmaster-react`
4. Configure build settings (should auto-detect Vite)
5. Deploy

## Next Steps

Once deployed:
1. ✅ **Get Vercel production URL**
2. **Configure Google Cloud Console** with Vercel URLs
3. **Set environment variables** in Vercel dashboard
4. **Test OAuth flow** end-to-end

---

**Ready for**: Google OAuth configuration with production URLs!