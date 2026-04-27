# OTP Modal Issue - Troubleshooting & Debugging Guide

**Problem**: After registration, OTP modal doesn't appear

**Root Causes to Check**:

---

## 🔍 Step 1: Browser Console Check

1. Open DevTools (F12 or Right Click → Inspect)
2. Go to Console tab
3. Register with a new account
4. Look for errors or messages

**What to look for**:
- JavaScript errors (red)
- Network errors (red)
- Success messages (green)

---

## 🔧 Step 2: Check Registration Response

Add this debugging code to `auth-modal.blade.php` (line 272-276):

```javascript
.then(async r => {
    const text = await r.text();
    console.log('Registration Response Status:', r.status);
    console.log('Response Body:', text);
    try { 
        const data = JSON.parse(text);
        console.log('Parsed JSON:', data);
        return data; 
    }
    catch { 
        console.error('JSON Parse Error:', text);
        return { success: false, message: r.status + ' ' + r.statusText }; 
    }
})
```

**Expected Output**:
```
Registration Response Status: 200 (or 201)
Response Body: {"success":true,"message":"Account created..."}
Parsed JSON: {success: true, ...}
```

---

## 🎯 Step 3: Check if OTP Dispatch is Working

Add this to the success handler (line 277-280):

```javascript
.then(d => {
    console.log('After Registration:', d);
    if(d.success) {
        console.log('Success! Dispatching OTP modal...');
        console.log('Email:', $refs.regEmail.value);
        console.log('Purpose: email_verify');
        
        close();
        $dispatch('open-otp-modal', { 
            email: $refs.regEmail.value, 
            purpose: 'email_verify' 
        });
        
        console.log('OTP Modal Dispatched!');
    } else {
        console.error('Registration failed:', d.message);
        error = d.message || Object.values(d.errors || {})[0]?.[0] || 'Registration failed';
        loading = false;
    }
})
```

**Expected Console Output**:
```
After Registration: {success: true, ...}
Success! Dispatching OTP modal...
Email: test@example.com
Purpose: email_verify
OTP Modal Dispatched!
```

---

## 📧 Step 4: Check if Email Actually Sent

1. Go to `storage/logs/laravel.log`
2. Search for recent registration
3. Look for email sending logs

**Expected Log Entry**:
```
[2026-04-27 13:00:00] local.INFO: OTP generated for test@example.com
[2026-04-27 13:00:00] local.INFO: Sending OTP email to test@example.com
[2026-04-27 13:00:01] local.INFO: Email sent: OTP code is 123456
```

---

## 🔗 Step 5: Check Network Tab

1. DevTools → Network tab
2. Register
3. Look for POST request to `/en/register`
4. Check the Response tab

**Expected Response**:
```json
{
  "success": true,
  "message": "Account created successfully",
  "email": "test@example.com"
}
```

---

## 🐛 Common Issues & Fixes

### Issue 1: OTP Modal Doesn't Appear
**Cause**: Event dispatch not working
**Fix**: Check if `@open-otp-modal.window` listener is active in `otp-modal.blade.php`

### Issue 2: Registration Fails with Error
**Cause**: Validation error or server error
**Fix**: Check console for actual error message

### Issue 3: OTP Email Not Received
**Cause**: Email not configured or sending failed
**Fix**: Check `.env` MAIL_MAILER setting

### Issue 4: OTP Input Not Visible
**Cause**: Modal loaded but content not showing
**Fix**: Check browser zoom, CSS, or modal z-index

---

## 🛠️ Quick Fix: Add Debug Mode

Temporarily edit `auth-modal.blade.php` line 259 to add logging:

```javascript
@submit.prevent="
    console.log('Registration submitted');
    loading = true; 
    error = '';
    // ... rest of code
"
```

---

## ✅ Verification Checklist

- [ ] Browser console shows no JS errors
- [ ] Registration Response shows `success: true`
- [ ] Dispatch console logs appear
- [ ] OTP Modal Dispatched message shows
- [ ] OTP email appears in logs
- [ ] OTP modal appears on screen
- [ ] Can enter 6-digit code
- [ ] Verification works

---

## 🚀 If Everything Fails

Try this nuclear option - manually trigger OTP modal:

```javascript
// In browser console
document.dispatchEvent(new CustomEvent('open-otp-modal', {
    detail: { 
        email: 'test@example.com', 
        purpose: 'email_verify' 
    }
}));
```

If this works, the issue is with the registration success handler.

---

## 📝 What Needs Fixing

Based on testing, you might need to:

1. **Check Registration Endpoint** → `/en/register`
   - Verify it returns correct JSON
   - Check it sends email successfully

2. **Check Email Configuration** → `.env`
   - MAIL_MAILER must be configured
   - For local: use `log`
   - For production: use `smtp`

3. **Check OTP Service** → `app/Services/OtpService.php`
   - Verify OTP is generated
   - Verify email is sent

4. **Check Auth Modal** → `auth-modal.blade.php`
   - Verify dispatch happens
   - Check for JavaScript errors

---

## 🎯 Expected User Flow

1. User fills registration form
2. Clicks "Create Account"
3. **Loading spinner shows**
4. **Request sent to /en/register**
5. **Server creates user & sends OTP email**
6. **Success response received**
7. **Auth modal closes** ← Must happen
8. **OTP Modal opens** ← Must happen
9. User sees OTP input form
10. User enters 6-digit code
11. Verification succeeds
12. Can login

---

## 🔐 To Verify OTP Email Sent

Check these files:

1. **Database** - `otps` table
   ```sql
   SELECT * FROM otps WHERE email = 'test@example.com' ORDER BY id DESC LIMIT 1;
   ```

2. **Email Logs** - `storage/logs/laravel.log`
   ```
   grep -i "otp\|email" storage/logs/laravel.log | tail -20
   ```

3. **Mail Logs** - (if MAIL_MAILER=log)
   ```
   grep -i "test@example.com" storage/logs/laravel.log
   ```

---

## 💡 Still Not Working?

Please provide:
1. Browser console output (screenshot or text)
2. Network response (from DevTools)
3. Laravel logs (from `storage/logs/laravel.log`)
4. What exactly happens after clicking "Create Account"

---

**Next Step**: Follow this guide and tell me what you find!
