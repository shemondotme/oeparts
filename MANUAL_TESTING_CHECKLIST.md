# MANUAL TESTING CHECKLIST - Before Production Deployment

**Date**: Monday, Apr 27, 2026
**Deployment Target**: Production Server

---

## 📋 PHASE 1: BASIC FUNCTIONALITY (1-2 hours)

### 1. Homepage & Navigation ✅
- [ ] Homepage loads correctly
- [ ] Navigation menu works (all 5 languages)
- [ ] Search bar visible and functional
- [ ] Cart icon shows item count
- [ ] Mobile responsive (test on phone/tablet)

### 2. Product Search (OEM-Based) ✅
- [ ] Search by OEM number works
- [ ] Results display correctly
- [ ] Pagination works (if >20 results)
- [ ] Filter by condition works
- [ ] Filter by manufacturer works
- [ ] Sort by price works
- [ ] Sort by rating works (if enabled)

### 3. Product Details Page ✅
- [ ] Product info displays correctly
- [ ] Price shown (ex VAT)
- [ ] Condition badge shows correct color
- [ ] Add to cart button works
- [ ] Stock status shows correctly
- [ ] Cross-references display (if any)

### 4. Shopping Cart ✅
- [ ] Add item to cart works
- [ ] Quantity can be updated
- [ ] Remove item works
- [ ] Cart total calculates correctly
- [ ] VAT calculation correct (21%)
- [ ] Shipping cost updates on selection
- [ ] Cart persists after page reload (for logged-in users)

### 5. Checkout - All 5 Steps ✅

**Step 1: Contact Information**
- [ ] Email field required
- [ ] "Verify Email" button sends OTP
- [ ] OTP email arrives in inbox
- [ ] Can enter 6-digit code
- [ ] Phone field optional (but test both)
- [ ] B2B toggle works
- [ ] Form validation works (email format)

**Step 2: Shipping Address**
- [ ] Address form displays all fields
- [ ] Postal code validation works
- [ ] Country dropdown works (all 5 languages)
- [ ] EU country selection works
- [ ] Address can be saved

**Step 3: Shipping Method**
- [ ] Shipping options display
- [ ] Price updates based on selection
- [ ] Delivery time shown correctly
- [ ] Can select different methods

**Step 4: Payment**
- [ ] Order review shows all items
- [ ] Total calculation correct
- [ ] Payment method options (Airwallex + Bank)
- [ ] Airwallex payment form loads
- [ ] Test payment with test card: 4111 1111 1111 1111
- [ ] Payment redirects back correctly

**Step 5: Order Confirmation**
- [ ] Order number displays
- [ ] Order details correct
- [ ] Invoice link works
- [ ] "Download PDF" button works
- [ ] Email received with order confirmation

### 6. Authentication - User Flow ✅

**Registration**
- [ ] Registration page accessible
- [ ] Email validation works
- [ ] Password strength requirements show
- [ ] Terms checkbox required
- [ ] Submit button works
- [ ] Confirmation email sent
- [ ] OTP verification required
- [ ] User redirected to email verification page
- [ ] After OTP verify → can login

**Login**
- [ ] Login page loads
- [ ] Email + password login works
- [ ] Wrong password shows error
- [ ] Unverified email blocks login
- [ ] "Forgot Password" link works
- [ ] Remember me checkbox works

**Email Verification**
- [ ] OTP email arrives
- [ ] Can resend OTP
- [ ] 6-digit code entry works
- [ ] Wrong code shows error (3 attempts)
- [ ] After verification → login allowed

**Password Reset**
- [ ] "Forgot Password" form works
- [ ] Reset email sent
- [ ] OTP verification required
- [ ] Can set new password
- [ ] New password works for login

---

## 📋 PHASE 2: SPECIAL FEATURES (30-45 minutes)

### 7. Multilingual (5 Languages) ✅
- [ ] English (en) - all pages
- [ ] German (de) - all pages
- [ ] Lithuanian (lt) - all pages
- [ ] French (fr) - all pages
- [ ] Spanish (es) - all pages
- [ ] Language switcher works
- [ ] User preference saved

### 8. B2B Features ✅
- [ ] B2B toggle on checkout step 1
- [ ] B2B toggle → requires VAT number on step 2
- [ ] VAT number validation works
- [ ] VIES validation (EU VAT check) works
- [ ] VAT exemption applies (0% VAT)
- [ ] Order shows "B2B" label

### 9. Account Dashboard (Logged-in Users) ✅
- [ ] Dashboard loads with user name
- [ ] My Orders shows all orders
- [ ] Order details page shows full info
- [ ] Settings tab works
- [ ] Can update profile (name, email, phone)
- [ ] Can change password
- [ ] Email verification option visible (if not verified)
- [ ] Notification preferences work

### 10. Contact Form ✅
- [ ] Contact form loads
- [ ] "Verify Email" button works
- [ ] OTP required for submission
- [ ] After verify → form submittable
- [ ] Form sends message
- [ ] Confirmation email sent to user

---

## 📋 PHASE 3: ADMIN PANEL (30-45 minutes)

### 11. Admin Login ✅
- [ ] Admin login page loads
- [ ] Email + password login works
- [ ] Only admins can access (not customers)
- [ ] Dashboard loads

### 12. Products Management ✅
- [ ] Product list loads
- [ ] Can create new product
- [ ] Can edit product
- [ ] Can delete product (soft delete)
- [ ] Search/filter products works
- [ ] Bulk actions work (if implemented)

### 13. Orders Management ✅
- [ ] Orders list loads
- [ ] Can view order details
- [ ] Can update order status
- [ ] Status changes reflected
- [ ] Order notes can be added

### 14. CMS Management ✅
- [ ] Sections list loads
- [ ] Can create section
- [ ] Can edit section with rich editor
- [ ] Draft/Publish status works
- [ ] Live preview works
- [ ] Version history shows changes
- [ ] Can restore previous version

### 15. Settings ✅
- [ ] Settings page accessible
- [ ] Can update site name
- [ ] Can upload logo
- [ ] Can change primary color
- [ ] Payment settings editable
- [ ] Email settings display correctly

---

## 📋 PHASE 4: PAYMENTS & WEBHOOKS (20-30 minutes)

### 16. Airwallex Payment Test ✅
- [ ] Airwallex form loads
- [ ] Test card accepted: 4111 1111 1111 1111
- [ ] Payment succeeds
- [ ] Order status changes to "Paid"
- [ ] Order confirmation email sent
- [ ] Invoice generated

### 17. Bank Transfer Payment ✅
- [ ] Bank transfer option selectable
- [ ] Bank details displayed correctly
- [ ] Order marked as "Pending Payment"
- [ ] Confirmation email with bank details sent

### 18. Webhook Testing ✅
- [ ] Payment webhook received (check logs)
- [ ] Order status updated automatically
- [ ] Email sent when status changes
- [ ] Webhook signature verification works

---

## 📋 PHASE 5: EMAIL VERIFICATION (15-20 minutes)

### 19. Critical - Email Verification is WORKING ✅
- [ ] Guest checkout OTP required ← **MOST CRITICAL**
- [ ] OTP email arrives within 30 seconds
- [ ] 6-digit code format correct
- [ ] Wrong code blocked (3 attempts)
- [ ] OTP expires after 10 minutes
- [ ] Can resend OTP (30-second throttle)
- [ ] After verification → checkout continues

### 20. Registration Email Verification ✅
- [ ] New user must verify email before login
- [ ] OTP sent to email
- [ ] User cannot login without verification

### 21. Password Reset Email ✅
- [ ] Reset email sent
- [ ] OTP required
- [ ] After verify → can set new password

---

## 📋 PHASE 6: SECURITY & PERFORMANCE (15-20 minutes)

### 22. Security Checks ✅
- [ ] HTTPS working (lock icon in browser)
- [ ] No XSS warnings
- [ ] CSRF token on all forms
- [ ] SQL injection not possible (test with SQL in search)
- [ ] Password hashing works
- [ ] Honeypot field on contact form
- [ ] Rate limiting active (try 10 failed logins)

### 23. Performance ✅
- [ ] Homepage loads in <2 seconds
- [ ] Product search responds in <1 second
- [ ] Checkout doesn't have delays
- [ ] Images load correctly (lazy loading)
- [ ] No console errors in browser

### 24. Browser Compatibility ✅
- [ ] Chrome - all works
- [ ] Firefox - all works
- [ ] Safari - all works
- [ ] Edge - all works
- [ ] Mobile browsers - responsive

---

## 📋 PHASE 7: ERROR HANDLING (10-15 minutes)

### 25. Error Scenarios ✅
- [ ] 404 page when accessing non-existent route
- [ ] 500 page displays (not code dump)
- [ ] Network error handled gracefully
- [ ] Session timeout shows message
- [ ] Invalid input shows validation error
- [ ] Out of stock shows message (not error)

### 26. Edge Cases ✅
- [ ] Empty cart checkout blocked
- [ ] Duplicate order prevention works
- [ ] Concurrent payment attempts handled
- [ ] Zero-price order handling
- [ ] Very large order handling
- [ ] Currency conversion (if applicable)

---

## 📋 PHASE 8: DATA INTEGRITY (10-15 minutes)

### 27. Database Checks ✅
- [ ] Products showing correct count
- [ ] Orders saving correctly
- [ ] User accounts creating properly
- [ ] Email logs recording
- [ ] Payment records accurate
- [ ] No duplicate orders
- [ ] Stock levels updating

### 28. Cache Checks ✅
- [ ] Settings cache working
- [ ] Product listings cached
- [ ] Page loads from cache (faster 2nd time)
- [ ] Cache invalidates when needed

---

## 📊 TESTING CHECKLIST - QUICK REFERENCE

### Must Pass (Critical) 🔴
- [ ] Email verification working (OTP required for guests)
- [ ] Checkout all 5 steps complete
- [ ] Payment processing working
- [ ] User registration + verification
- [ ] Admin dashboard functional
- [ ] HTTPS active

### Should Pass (Important) 🟡
- [ ] All 5 languages work
- [ ] Mobile responsive
- [ ] Search functionality
- [ ] Order history displays
- [ ] Email notifications sent

### Nice to Pass (Enhancements) 🟢
- [ ] Performance <2 seconds
- [ ] All browsers compatible
- [ ] Bulk admin actions
- [ ] Advanced filtering

---

## 🚀 FINAL CHECKLIST BEFORE GO-LIVE

```
BEFORE PUSHING TO PRODUCTION:

✅ All Phase 1-8 tests completed
✅ All critical items passing (Phase 1-5)
✅ No console errors
✅ No database errors
✅ Email verification ENABLED (CHECKOUT_SKIP_OTP=false)
✅ Airwallex credentials configured (live)
✅ SMTP email configured
✅ Redis running
✅ Database migrated
✅ SSL certificate valid
✅ DNS pointing to server
✅ Backups configured
✅ Monitoring alerts set
✅ Support team trained
✅ Customer FAQ published
```

---

## 📝 TEST REPORT TEMPLATE

**Date**: [Date tested]
**Environment**: Production
**Tester**: [Your name]
**Duration**: [How long did testing take]

### Results
- Total Tests: [number]
- Passed: [number] ✅
- Failed: [number] ❌
- Not Applicable: [number]

### Issues Found
1. [Issue description] - Severity: [Critical/High/Medium/Low]
2. [Issue description] - Severity: [Critical/High/Medium/Low]

### Recommendations
- [What to fix]
- [What to improve]

### Approval
- [ ] Approve for production deployment
- [ ] Do not deploy (fix issues first)

---

## ⏱️ ESTIMATED TIME

- Phase 1 (Basic): 1-2 hours
- Phase 2 (Special): 30-45 minutes
- Phase 3 (Admin): 30-45 minutes
- Phase 4 (Payments): 20-30 minutes
- Phase 5 (Email): 15-20 minutes
- Phase 6 (Security): 15-20 minutes
- Phase 7 (Errors): 10-15 minutes
- Phase 8 (Data): 10-15 minutes

**TOTAL: 3-5 hours**

---

## ✨ KEY POINTS TO REMEMBER

1. **Email Verification is CRITICAL** 🔴
   - MUST be enabled (CHECKOUT_SKIP_OTP=false)
   - Guest checkout MUST require OTP
   - Test this first!

2. **Payment Testing**
   - Test with Airwallex test card: 4111 1111 1111 1111
   - Test bank transfer option
   - Verify webhook receives updates

3. **All 5 Languages**
   - Test each language fully
   - Verify translations are correct
   - Check special characters display

4. **Mobile Testing**
   - Test on actual phones (not just browser)
   - Check form inputs (OTP, email, card)
   - Verify navigation works

5. **Security**
   - HTTPS must be active
   - No sensitive data in logs
   - Rate limiting working

---

## 🎯 GREEN LIGHT FOR DEPLOYMENT

Once ALL tests pass:
1. Create final backup
2. Document any known issues
3. Train support team
4. Set up monitoring
5. **DEPLOY WITH CONFIDENCE** 🚀

---

**Good luck! You've got this!** 💪
