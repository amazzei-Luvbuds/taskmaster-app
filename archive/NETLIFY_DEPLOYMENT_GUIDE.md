# TaskMaster React - Netlify Deployment Guide

## Quick Deployment Steps

### 1. Prepare Your Repository
```bash
# Make sure you're in the React project directory
cd taskmaster-react

# Create a git repository if you haven't already
git init
git add .
git commit -m "Initial TaskMaster React application"

# Push to GitHub/GitLab (or connect folder directly to Netlify)
```

### 2. Netlify Deployment Options

#### Option A: GitHub/Git Integration (Recommended)
1. Push your code to GitHub
2. Connect GitHub repository to Netlify
3. Netlify will auto-deploy on every push

#### Option B: Drag & Drop Deploy
1. Build the project locally: `npm run build`
2. Drag the `dist` folder to Netlify deploy area
3. Manual deployment (no auto-updates)

### 3. Configure Build Settings in Netlify

**Build Settings:**
- **Build command:** `npm run build`
- **Publish directory:** `dist`
- **Node version:** `18` (set in netlify.toml)

### 4. Environment Variables in Netlify

Go to **Site settings > Environment variables** and add:

```
VITE_API_BASE_URL=https://script.google.com/macros/s/YOUR_NEW_SCRIPT_ID/exec
VITE_ENV=production
VITE_DEBUG_API=false
```

**Important:** Replace `YOUR_NEW_SCRIPT_ID` with your new Google Apps Script deployment ID.

### 5. Test Your Deployment

After deployment, visit your Netlify URL and:
1. Check the "Environment Test" tab works
2. Go to "API Integration Test" tab
3. Verify connection to your Google Apps Script

## Files Created for Netlify

### `netlify.toml`
- Configures build settings
- Sets up SPA redirects
- Environment-specific configurations

### `public/_redirects`
- Ensures React Router works properly
- All routes redirect to index.html

## Google Apps Script Setup

### Create New Apps Script Project
1. Go to script.google.com
2. Create new project: "TaskMaster API"
3. Copy your existing functions from original Code.js
4. Add the API transformation code from `api-transformation.js`
5. Deploy as web app:
   - Execute as: Me
   - Who has access: Anyone

### Update React Environment
```bash
# Update .env.local with your new script URL
VITE_API_BASE_URL=https://script.google.com/macros/s/YOUR_NEW_SCRIPT_ID/exec
```

## Deployment Checklist

- [ ] Code pushed to repository
- [ ] Netlify site created and connected
- [ ] Build settings configured (npm run build, dist folder)
- [ ] Environment variables set in Netlify
- [ ] New Google Apps Script project created
- [ ] API transformation code added to Apps Script
- [ ] Apps Script deployed as web app
- [ ] React app updated with new script URL
- [ ] API Integration Test passes on deployed site

## Expected Results

**Netlify URL:** `https://your-site-name.netlify.app`

**Working Features:**
- ✅ React development environment demo
- ✅ API connection test interface
- ✅ Error handling demonstration
- ✅ Real-time API endpoint testing

## Next Steps After Deployment

1. **Share with stakeholders** for feedback
2. **Test API connectivity** with the new Apps Script
3. **Begin Sprint 2** development (Core Task Management)
4. **Gradual migration** planning from legacy system

## Troubleshooting

**Build Fails:**
- Check Node.js version (should be 18+)
- Verify all dependencies in package.json
- Check for TypeScript errors

**API Connection Fails:**
- Verify Google Apps Script URL is correct
- Ensure Apps Script is deployed with "Anyone" access
- Check CORS settings in Apps Script

**Routing Issues:**
- Ensure _redirects file is in public folder
- Check netlify.toml redirect configuration

This setup gives you a completely separate, safe development environment for building and testing the new TaskMaster React application!