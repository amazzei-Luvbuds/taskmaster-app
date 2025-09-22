# 🚀 Vercel Real-Time Notifications Deployment Guide

## 📋 Overview

This guide explains how to deploy the TaskMaster real-time notification system on Vercel using serverless-compatible solutions.

## 🔄 Architecture Changes for Vercel

Since Vercel is a **serverless platform**, we've adapted the real-time system with two approaches:

### 1. **Server-Sent Events (SSE)** ✨ Primary Method
- Real-time streaming connection
- Browser-native support
- Works within Vercel's 5-minute function timeout

### 2. **Smart Polling** 🔄 Fallback Method
- Efficient 3-second intervals
- Automatic fallback if SSE fails
- Minimal server load

## 📁 New Files Created

```
taskmaster-react/
├── api/
│   ├── sse-notifications.ts      # Server-Sent Events endpoint
│   └── polling-notifications.ts  # Polling API endpoint
├── src/services/
│   └── realtimeService.ts       # Adaptive real-time service
├── src/components/
│   └── RealTimeNotifications.tsx # Updated component
└── vercel.json                  # Updated Vercel config
```

## 🚀 Deployment Instructions

### 1. **Install Dependencies**
```bash
cd taskmaster-react
npm install @vercel/node
```

### 2. **Environment Variables**
Set these in your Vercel dashboard or `.env.local`:

```bash
# Real-time configuration
VITE_REALTIME_MODE=sse
VITE_SSE_URL=/api/sse-notifications
VITE_POLLING_URL=/api/polling-notifications

# Database (if using external DB)
DATABASE_URL=your_mysql_connection_string
```

### 3. **Deploy to Vercel**
```bash
# Deploy via Vercel CLI
vercel --prod

# Or connect your GitHub repo to Vercel dashboard
```

### 4. **Test Real-Time Features**

After deployment, your app will automatically:

✅ **Connect via SSE** for real-time notifications
✅ **Fallback to polling** if SSE fails
✅ **Auto-reconnect** on connection loss
✅ **Show connection status** in the UI

## 🔧 How It Works on Vercel

### **SSE Mode (Primary)**
- Browser connects to `/api/sse-notifications`
- Server streams events in real-time
- 5-minute connection limit (Vercel timeout)
- Auto-reconnection when needed

### **Polling Mode (Fallback)**
- Browser polls `/api/polling-notifications` every 3 seconds
- Efficient last-poll timestamp filtering
- Works reliably across all environments

### **Adaptive Behavior**
```typescript
// Automatically detects best method
const mode = environment.supportsSSE ? 'sse' : 'polling';

// Falls back gracefully
if (sseConnectionFails) {
  switchToPolling();
}
```

## 📊 Features Available

### Real-Time Notifications ✨
- **Mentions**: `@username` in comments
- **Comments**: New comments on tasks
- **Task Updates**: Status/assignee changes
- **Assignments**: New task assignments
- **Deadlines**: Upcoming due dates

### UI Features 🎨
- **Toast Notifications**: Animated slide-in alerts
- **Connection Status**: Live connection indicator
- **Read/Unread**: Mark notifications as read
- **Action Buttons**: Quick links to tasks
- **Auto-cleanup**: Notifications disappear after 30s

## 🐛 Debugging & Monitoring

### **Development Debug Panel**
In development mode, you'll see a debug panel showing:
- Connection status
- Active notifications count
- Current user info
- Error messages

### **Console Logging**
Monitor the browser console for:
```
✅ Real-time service connected (sse)
🔔 Received notification: {...}
📨 Received mention: {...}
📝 Received task update: {...}
```

### **Connection Issues**
If you see connection errors:
1. Check Vercel function logs
2. Verify environment variables
3. Test polling fallback
4. Check CORS headers

## 🎯 Testing Real-Time Features

### **Manual Testing**
1. Open app in two browser tabs
2. Login as different users
3. Comment on a task in tab 1
4. See notification appear in tab 2

### **API Testing**
Create test notifications:
```bash
curl -X POST https://your-app.vercel.app/api/polling-notifications \
  -H "Content-Type: application/json" \
  -d '{
    "notification": {
      "type": "mention",
      "title": "Test notification",
      "message": "This is a test",
      "userId": "user@example.com"
    }
  }'
```

## 📈 Performance Optimization

### **SSE Optimizations**
- Keep-alive pings every 30 seconds
- Automatic dead connection cleanup
- Connection pooling per user

### **Polling Optimizations**
- Last-poll timestamp filtering
- Exponential backoff on errors
- Presence data included efficiently

### **Frontend Optimizations**
- Event deduplication
- Notification limits (max 5 shown)
- Auto-cleanup after 30 seconds
- Lazy loading for heavy operations

## 🔒 Security Considerations

### **Authentication**
- User ID validation on connection
- Email verification for notifications
- CORS properly configured

### **Rate Limiting**
- Built-in Vercel limits apply
- Connection limits per user
- Message rate limiting

## 🚨 Production Checklist

Before going live:

- [ ] Test SSE connections work
- [ ] Verify polling fallback works
- [ ] Check all notification types
- [ ] Test with multiple users
- [ ] Verify reconnection logic
- [ ] Check mobile browser support
- [ ] Test with slow connections
- [ ] Validate security headers
- [ ] Monitor function usage/costs

## 💡 Advanced Features

### **Custom Notification Types**
```typescript
await realtimeService.sendNotification({
  type: 'custom_event',
  title: 'Custom Event',
  message: 'Something happened',
  data: { customField: 'value' }
}, {
  targetUser: 'user@example.com'
});
```

### **Broadcasting**
```typescript
// Send to all connected users
await realtimeService.sendNotification(notification, {
  broadcast: true
});

// Send to specific users
await realtimeService.sendNotification(notification, {
  targetUsers: ['user1@example.com', 'user2@example.com']
});
```

## 🔗 Integration with Existing PHP API

The new system integrates with your existing PHP APIs:

- Comments API calls the notification endpoints
- Task updates trigger real-time broadcasts
- User presence syncs with activity
- Authentication uses existing Google OAuth

## 🌐 Browser Compatibility

**SSE Support**: Chrome, Firefox, Safari, Edge (IE11+ with polyfill)
**Polling Support**: All browsers (universal fallback)

The system automatically detects and uses the best available method.

---

**🎉 Your TaskMaster app now has production-ready real-time notifications on Vercel!**