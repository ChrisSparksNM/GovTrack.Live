# Google OAuth Setup Guide

## 1. Install Laravel Socialite

Run this command to install Laravel Socialite:
```bash
composer require laravel/socialite
```

## 2. Google Cloud Console Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the Google+ API:
   - Go to "APIs & Services" > "Library"
   - Search for "Google+ API" and enable it
4. Create OAuth 2.0 credentials:
   - Go to "APIs & Services" > "Credentials"
   - Click "Create Credentials" > "OAuth 2.0 Client IDs"
   - Choose "Web application"
   - Add authorized redirect URIs:
     - For local: `http://localhost:8000/auth/google/callback`
     - For production: `https://yourdomain.com/auth/google/callback`

## 3. Environment Variables

Add these to your .env file:
```env
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URL=http://localhost:8000/auth/google/callback
```

## 4. Database Migration

The users table needs a google_id column (already included in migration below).

## 5. Routes

Add the Google OAuth routes (included in routes file below).

## 6. Controller

Create the GoogleController to handle OAuth (included below).

## 7. Update User Model

Add google_id to fillable fields (included below).