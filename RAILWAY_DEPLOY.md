# Alpha Backend - Railway Deployment Guide

## 📦 Railway Setup

### 1. Create New Project on Railway
1. Go to [Railway](https://railway.app)
2. Click "New Project"
3. Choose "Deploy from GitHub repo"
4. Select your backend repository

### 2. Add MySQL Database
1. In your Railway project, click "+ New"
2. Select "Database" → "MySQL"
3. Railway will automatically create a MySQL instance
4. Note: Database credentials will be available as environment variables

### 3. Configure Environment Variables

Add these environment variables in Railway:

```env
# Database (Railway auto-provides these when you add MySQL)
DB_HOST=${{MYSQLHOST}}
DB_PORT=${{MYSQLPORT}}
DB_NAME=${{MYSQL_DATABASE}}
DB_USER=${{MYSQL_USER}}
DB_PASSWORD=${{MYSQL_PASSWORD}}

# Gemini API
GEMINI_API_KEY=AIzaSyDZJj6stCCaMqsoUHNLnVTzWC7Js-FvnU4

# SMTP Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=1yousefsaleh@gmail.com
SMTP_PASSWORD=rrjcrbfcbpklnpjb
SMTP_FROM_EMAIL=1yousefsaleh@gmail.com
SMTP_FROM_NAME=منصة ألفا

# Frontend URL (update after deploying frontend)
FRONTEND_URL=https://your-frontend-url.up.railway.app
```

### 4. Import Database Schema

After deployment:

1. Go to Railway MySQL database
2. Click "Connect" → "MySQL Command"  
3. Run your SQL schema file to create tables

**OR** use Railway's built-in phpMyAdmin:
1. Add phpMyAdmin service to your project
2. Import your `.sql` file

### 5. Update CORS in Backend

Make sure `config/cors.php` allows your frontend domain:

```php
$allowedOrigins = [
    'https://your-frontend-url.up.railway.app'
];
```

## 🚀 Deployment

Railway will automatically:
1. Detect the Dockerfile
2. Build the Docker image
3. Deploy the backend
4. Provide you with a public URL

## 📝 Notes

- Railway provides automatic HTTPS
- Database backups are handled by Railway
- Check logs in Railway dashboard if issues occur
