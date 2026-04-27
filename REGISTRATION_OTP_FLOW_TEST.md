# Registration + OTP Flow - Complete Test Guide

## 🎯 End-to-End Test

This guide will help you test the complete registration and OTP email verification flow.

---

## ✅ Prerequisites

Before testing, make sure:

1. **Server is running**
   ```bash
   php artisan serve
   ```

2. **Queue is running** (for email sending)
   ```bash
   php artisan queue:work
   ```

3. **Mail driver is configured** in `.env`:
   ```
   MAIL_MAILER=log       # For local testing (emails logged to storage/logs)
   # OR
   MAIL_MAILER=smtp      # For real SMTP
   MAIL_HOST=smtp.mailtrap.io
   MAIL_PORT=465
   MAIL_USERNAME=...
   MAIL_PASSWORD=...
   ```

4. **Browser DevTools ready** (F12 or Right-click → Inspect)

---

## 🚀 Step-by-Step Test

### Step 1: Open the Site

1. Go to `http://localhost:8000/en`
2. Open DevTools (F12)
3. Go to **Console** tab
4. Go to **Network** tab (optional, for debugging)

---

### Step 2: Click "Sign In" (Auth Modal)

1. Find and click the **"Sign in"** button/link on the page
2. The **Auth Modal** should appear with:
   - Two tabs: "Sign in" and "Register"
   - Login form on the left (currently selected)
   - "Register" tab on the right

**Check Console**: Should be clean (no errors)

---

### Step 3: Click "Register" Tab

1. Click the **"Register"** tab in the modal
2. The registration form should appear with:
   - Name field
   - Email field
   - Password field
   - Confirm Password field
   - ✅ **Terms checkbox** (required)
   - ℹ️ **Blue info box** saying "Email verification required after registration"
   - "Create account" button

**Check Console**: Should be clean (no errors)

---

### Step 4: Fill Registration Form

Enter test data:
```
Full name:           John Doe
Email:               test@example.com  (change this to make unique!)
Password:            TestPassword123
Confirm password:    TestPassword123
Terms:               ✅ Check the box
```

**Important**: Use a **different email each time** you test (e.g., `test-1@example.com`, `test-2@example.com`)

---

### Step 5: Submit Registration Form

1. Click **"Create account"** button
2. **Loading spinner** should appear
3. **Console logs** should show:
   ```
   ✓ Registration success: {success: true, ...}
   📧 Email: test@example.com
   🚀 Dispatching OTP modal...
   📋 OTP Modal Dispatched!
   ```

**Network Tab Check**:
- Look for POST request to `/en/register`
- Response should be:
  ```json
  {
    "success": true,
    "message": "Registration successful. Please verify your email.",
    "data": {
      "requires_otp": true,
      "user": {
        "id": 1,
        "name": "John Doe",
        "email": "test@example.com"
      }
    }
  }
  ```

---

### Step 6: OTP Modal Should Appear

After ~300ms (small delay), the **OTP Modal** should open with:
- 📧 Icon and heading "Check your email."
- Text showing: "Code sent to test@example.com"
- **6 input fields** for OTP digits
- "Resend code" button
- "Cancel verification" link

**Console Log**: Should show success messages above

**If OTP Modal doesn't appear**:
- Check console for errors
- Check that the timer ran (300ms delay)
- Try refreshing and registering again

---

### Step 7: Get the OTP Code

1. Check where your email went:

   **Option A: Local (MAIL_MAILER=log)**
   ```bash
   tail -50 storage/logs/laravel.log | grep -i "otp\|test@example.com"
   ```
   Or check the log file directly in an editor.

   **Option B: Mailtrap**
   1. Go to https://mailtrap.io
   2. Check your inbox for email from test@example.com
   3. Copy the 6-digit code

   **Option C: Database**
   ```bash
   php artisan tinker
   >>> App\Models\Otp::latest()->first()->otp_code;
   # Or
   >>> App\Models\Otp::where('email', 'test@example.com')->latest()->first()->otp_code;
   ```

**Expected Email Content**:
```
Subject: Your Verification Code
Body:
Your verification code is: 123456
This code expires in 10 minutes.
```

---

### Step 8: Enter OTP Code

1. OTP Modal is open and waiting for input
2. You have the 6-digit code (e.g., `123456`)

**Method 1: Manual Input**
1. Click first digit field
2. Enter each digit one by one
3. Focus should auto-advance to next field
4. After entering all 6 digits → automatic verification

**Method 2: Paste Input**
1. Copy the code: `123456`
2. Click first field
3. Paste (Ctrl+V or Cmd+V)
4. All digits should fill and verify automatically

**Expected Console**:
```
(during verification)
Verifying...
(on success)
✓ Email verified successfully.
```

---

### Step 9: Verification Success

After entering correct OTP:

1. **OTP Modal** should show green success message:
   ```
   ✓ Email verified successfully.
   ```

2. **Modal closes** after ~900ms

3. **User is now logged in and email is verified**

4. **Console logs** (check Network tab):
   - POST to `/en/verify-otp`
   - Response:
     ```json
     {
       "success": true,
       "message": "OTP verified successfully."
     }
     ```

---

### Step 10: Verify User was Created

```bash
php artisan tinker
>>> App\Models\User::latest()->first();
# Output should show:
# - id: [recent number]
# - name: "John Doe"
# - email: "test@example.com"
# - email_verified_at: [timestamp]  ← Should NOT be null!
```

Or check database directly:
```sql
SELECT id, name, email, email_verified_at FROM users ORDER BY id DESC LIMIT 1;
```

---

## 🐛 Troubleshooting

### Problem: OTP Modal doesn't appear

**Check**:
1. Console for JavaScript errors
2. Network tab for POST `/en/register` response
3. Is the registration response `success: true`?

**Fix**:
1. Refresh page
2. Clear browser cache (Ctrl+Shift+Delete)
3. Try again with different email

---

### Problem: OTP Email not received

**Check**:
1. `.env` MAIL_MAILER setting
2. `storage/logs/laravel.log` for email sending errors
3. Queue is running (`php artisan queue:work`)

**Fix**:
```bash
# Check if queue is running
ps aux | grep queue

# If not, start it
php artisan queue:work

# Check logs
tail -100 storage/logs/laravel.log

# Look for: "Sending OTP email" or email errors
```

---

### Problem: OTP Code doesn't verify

**Check**:
1. OTP code is correct (not expired?)
2. OTP code is exactly 6 digits
3. Check logs for verification errors

**Fix**:
1. Request new code (click "Resend code")
2. Wait 60 seconds before resending
3. Try again with new code

---

### Problem: Wrong email shown in OTP Modal

**Check**:
1. Is the email in the modal correct?
2. Did you register with the right email?

**Fix**:
1. Close modal and start over
2. Make sure to use correct email

---

### Problem: Can't type in OTP fields

**Check**:
1. Are fields enabled? (check disabled state in DevTools)
2. Is loading spinner showing?

**Fix**:
1. Wait for loading to finish
2. Refresh page

---

## 📋 Database Queries for Verification

**Check OTP was created**:
```sql
SELECT * FROM otps WHERE email = 'test@example.com' ORDER BY id DESC LIMIT 1;
```

Expected fields:
- `email`: test@example.com
- `otp_code`: 123456 (or whatever was generated)
- `purpose`: email_verify
- `attempts`: 0
- `expires_at`: [future timestamp]
- `verified_at`: null (until verified)

**After verification, should show**:
```sql
SELECT * FROM otps WHERE email = 'test@example.com' ORDER BY id DESC LIMIT 1;
```
- `verified_at`: [timestamp] ← NOW populated!

**Check user was created and verified**:
```sql
SELECT id, name, email, email_verified_at FROM users WHERE email = 'test@example.com';
```

Expected:
- `email_verified_at`: [timestamp] ← Should NOT be null!

---

## ✅ Complete Success Checklist

- [ ] Registration form appears after clicking "Register" tab
- [ ] Blue info box visible saying "Email verification required after registration"
- [ ] Can fill all registration fields
- [ ] Can check Terms checkbox
- [ ] "Create account" button works
- [ ] Loading spinner appears
- [ ] Console shows success logs
- [ ] OTP Modal appears after registration
- [ ] Email shows correct recipient
- [ ] OTP email is received
- [ ] OTP code appears in email/logs/database
- [ ] Can enter OTP digits manually OR paste
- [ ] OTP verification works
- [ ] Success message appears
- [ ] Modal closes
- [ ] User is logged in
- [ ] Database shows `email_verified_at` is set
- [ ] OTP table shows `verified_at` is set

---

## 🚀 Full Test Repeat

To ensure consistency, test the complete flow 2-3 times:

1. **Test 1**: With manual OTP input
2. **Test 2**: With paste OTP input  
3. **Test 3**: With "Resend code" functionality

---

## 📞 Debug Info to Collect (if issues)

If something fails, collect:

1. **Browser Console** - screenshot or text
2. **Network Tab** - response bodies
3. **Laravel Logs** - `tail -100 storage/logs/laravel.log`
4. **Database Check** - run SQL queries above
5. **Email System** - MAIL_MAILER setting and logs

---

## 🎓 Summary

The registration flow should work as:

```
1. User clicks "Register"
2. Fills form + checks terms
3. Clicks "Create account"
4. Server creates user + sends OTP
5. OTP Modal appears
6. User enters OTP code
7. Server verifies OTP
8. User is now verified & logged in
9. OTP Modal closes
10. Success!
```

If any step fails, check console and logs for error messages.

---

**Ready to test?** Start from Step 1! 🚀
