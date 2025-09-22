# TaskMaster Database Migration Guide

This guide will help you migrate from Google Sheets to a MySQL database with a PHP REST API.

## Prerequisites

- Web hosting with PHP 7.4+ and MySQL 5.7+
- cPanel or database management access
- FTP/SFTP access to upload files

## Step 1: Create MySQL Database

1. **Login to cPanel** on your web hosting account
2. **Go to MySQL Databases** section
3. **Create new database**: `taskmaster_db`
4. **Create new user**: `taskmaster_api`
5. **Set strong password** for the user
6. **Add user to database** with all privileges

## Step 2: Import Database Schema

1. **Open phpMyAdmin** from cPanel
2. **Select your database** (`taskmaster_db`)
3. **Go to Import tab**
4. **Upload the file**: `schema.sql`
5. **Click Go** to execute

The schema will create all necessary tables and default data.

## Step 3: Upload API Files

1. **Create folder** in your web root: `/api/` (or `/public_html/api/`)
2. **Upload all files** from the `/api/` directory:
   - `config.php`
   - `index.php`
   - `tasks.php`
   - `departments.php`
   - `.htaccess`

## Step 4: Configure Database Connection

1. **Edit `config.php`**
2. **Update database credentials**:
   ```php
   define('DB_HOST', 'localhost');           // Usually 'localhost'
   define('DB_NAME', 'taskmaster_db');       // Your database name
   define('DB_USER', 'taskmaster_api');      // Your database user
   define('DB_PASS', 'your_secure_password'); // Your database password
   ```

3. **Set production settings**:
   ```php
   define('DEBUG_MODE', false);              // Disable debug in production
   define('CORS_ORIGIN', 'yourdomain.com');  // Your actual domain
   ```

## Step 5: Test API

1. **Visit your API endpoint**: `https://yourdomain.com/api/health`
2. **Should return**:
   ```json
   {
     "success": true,
     "data": {
       "status": "healthy",
       "database": "connected",
       "version": "1.0.0"
     }
   }
   ```

3. **Test endpoints**:
   - `GET /api/tasks` - Get all tasks
   - `GET /api/departments` - Get departments
   - `POST /api/tasks` - Create task (test with JSON data)

## Step 6: Update React App

1. **Update `.env.local`** in your React app:
   ```env
   VITE_APP_API_URL=https://yourdomain.com/api
   ```

2. **The existing API client** should work without changes since it follows the same response format

## Step 7: Data Migration (Optional)

If you have existing data in Google Sheets:

1. **Export from Google Sheets** as CSV
2. **Create migration script** or manually insert data
3. **Use phpMyAdmin** to import CSV data into the `tasks` table

## Step 8: Security Hardening

1. **Change default passwords**
2. **Restrict database user permissions**
3. **Enable HTTPS** on your domain
4. **Update CORS settings** to your specific domain
5. **Monitor API logs** for suspicious activity

## API Endpoints

Your new API will provide these endpoints:

### Tasks
- `GET /api/tasks` - Get all tasks
- `GET /api/tasks/{id}` - Get single task
- `POST /api/tasks` - Create new task
- `PUT /api/tasks/{id}` - Update task
- `PUT /api/tasks` - Update task status (kanban)
- `DELETE /api/tasks/{id}` - Delete task

### Departments
- `GET /api/departments` - Get all departments

### Utility
- `GET /api/health` - Health check
- `GET /api` - API documentation

## Troubleshooting

### Database Connection Issues
- Check database credentials in `config.php`
- Verify database user has proper permissions
- Check if MySQL service is running

### API Not Working
- Check `.htaccess` file is uploaded and working
- Verify PHP version (7.4+ required)
- Check error logs in cPanel

### CORS Issues
- Update `CORS_ORIGIN` in `config.php`
- Check browser console for CORS errors
- Verify `.htaccess` CORS headers

### Permission Errors
- Check file permissions (755 for directories, 644 for files)
- Verify database user permissions
- Check web server error logs

## Benefits of Database Migration

✅ **Better Performance** - Direct SQL queries vs Google Sheets API
✅ **More Reliable** - No Google API rate limits or authentication issues
✅ **Easier Scaling** - Handle thousands of tasks efficiently
✅ **Better Security** - Full control over data and access
✅ **Offline Development** - Work without internet connection
✅ **Advanced Features** - Complex queries, joins, transactions

## Support

If you encounter issues:
1. Check web server error logs
2. Enable debug mode temporarily
3. Test API endpoints with Postman/curl
4. Verify database connection in phpMyAdmin