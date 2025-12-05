# SendGrid Setup Guide

## 1. Create SendGrid Account

1. Go to https://signup.sendgrid.com/
2. Sign up with your email (use `1yousefsaleh@gmail.com`)
3. Verify your email
4. Complete the onboarding form:
   - Company: "Alpha Educational Platform" 
   - Purpose: "Transactional Emails"
   - Email volume: "Less than 100/day"

## 2. Create API Key

1. Go to https://app.sendgrid.com/settings/api_keys
2. Click **"Create API Key"**
3. Name: `Railway Production`
4. Permissions: **Full Access** (or choose "Restricted" and enable Mail Send only)
5. Click **Create & View**
6. **COPY THE API KEY** (you won't see it again!)

## 3. Add to Railway Environment Variables

In Railway Backend Service → Variables, add:

```
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxx
```

## 4. Deploy

The code will automatically use SendGrid when deployed!

## 5. (Optional) Verify Sender Email

For production, verify your sender email:
1. Go to https://app.sendgrid.com/settings/sender_auth/senders
2. Click "Create New Sender"
3. Fill in your details with `1yousefsaleh@gmail.com`
4. Verify the email

**Note:** Without verification, emails might go to spam. But it will work for testing!
