# 🔐 LOGIN CREDENTIALS - For Testing & Deployment

**Last Updated**: Monday, Apr 27, 2026

---

## 👨‍💼 ADMIN LOGIN

### Credentials
```
Email:    admin@example.com
Password: password
```

### URL
```
Local:      http://oemhub.test/admin/login
Production: https://yourdomain.com/admin/login
```

### Access
- ✅ Full admin panel
- ✅ Product management
- ✅ Order management
- ✅ CMS management
- ✅ Settings
- ✅ Reports

### Steps to Login
1. Go to `/admin/login`
2. Enter email: `admin@example.com`
3. Enter password: `password`
4. Click "Login"

---

## 👥 CUSTOMER LOGIN

### Demo Customer (Pre-created)
```
Email:    customer@example.com
Password: password
```

### URL
```
Local:      http://oemhub.test/en/login (or register)
Production: https://yourdomain.com/en/login (or register)
```

### Access
- ✅ View profile
- ✅ Order history
- ✅ Account settings
- ✅ Checkout
- ✅ Cart

### Steps to Login
1. Go to `/en/login`
2. Enter email: `customer@example.com`
3. Enter password: `password`
4. Click "Login"

---

## 📝 TEST CUSTOMER ACCOUNTS

### For Testing Registration
```
Test Email 1: test1@example.com
Test Email 2: test2@example.com
Test Email 3: test3@example.com

Password: Password123! (or set your own)
```

### During Registration
1. Go to registration page
2. Fill in details
3. Set strong password (min 8 chars, uppercase, lowercase, number, special char)
4. Check "I agree to terms"
5. Click "Register"
6. Verify email via OTP (check email)
7. Login

---

## 🛒 GUEST CHECKOUT (No Login Required)

### Process
1. Add items to cart
2. Click "Checkout"
3. Enter email address
4. Click "Verify Email"
5. Receive OTP in email
6. Enter 6-digit OTP code
7. Continue checkout
8. Complete purchase

**Note**: No password needed for guest checkout

---

## 💳 TEST PAYMENT CREDENTIALS

### Airwallex Test Card
```
Card Number: 4111 1111 1111 1111
Expiry Date: Any future date (e.g., 12/25)
CVV:         Any 3 digits (e.g., 123)
Result:      Payment will be PENDING (not charged)
```

### Bank Transfer
```
Payment Method: Bank Transfer
Status: Order marked as "Pending Payment"
Note: Admin must manually confirm when payment received
```

---

## 📧 TEST EMAIL ADDRESSES

**For Testing Email Verification**:
```
test@example.com
customer@example.com
admin@example.com
test1@example.com
test2@example.com
test3@example.com
```

**Note**: If using local development with `MAIL_MAILER=log`, emails are logged to `storage/logs/laravel.log`

---

## 🔑 ADMIN PANEL FEATURES TO TEST

### With `admin@example.com`

**✅ Dashboard**
- View stats
- Recent orders
- Key metrics

**✅ Products**
- Create product
- Edit product
- Delete product
- Search products
- Bulk actions

**✅ Orders**
- View all orders
- Update status
- Add notes
- View customer info

**✅ CMS**
- Manage sections
- Draft/Publish status
- Version history
- Live preview

**✅ Settings**
- General settings
- Payment settings
- Email settings
- Admin settings

**✅ Reports** (if available)
- Revenue reports
- Customer reports
- Product reports

---

## 👤 CUSTOMER FEATURES TO TEST

### With `customer@example.com`

**✅ Profile**
- View profile
- Edit details
- Change password
- Verify email

**✅ Orders**
- View all orders
- View order details
- Download invoices
- Track shipment (if available)

**✅ Addresses**
- Add address
- Edit address
- Delete address
- Set default

**✅ Settings**
- Change email
- Change password
- Notification preferences
- Language preference

**✅ Shopping**
- Browse products
- Search by OEM
- Add to cart
- Complete checkout

---

## 🔐 PASSWORD REQUIREMENTS

When setting your own password:
```
Minimum 8 characters
At least 1 uppercase letter (A-Z)
At least 1 lowercase letter (a-z)
At least 1 number (0-9)
At least 1 special character (!@#$%^&*)
```

### Valid Examples
```
Password@123
Test123!Pass
SecureP@ss99
MyAdm1n@2024
```

### Invalid Examples
```
password         (no uppercase, no number, no special)
Password123      (no special character)
Pass@1           (too short)
ADMIN@123        (no lowercase)
```

---

## 🌐 MULTILINGUAL TESTING

Test login in all 5 languages:

```
English:    /en/login
German:     /de/login
Lithuanian: /lt/login
French:     /fr/login
Spanish:    /es/login
```

Admin panel language preference:
- Set in account settings
- Language preference saved
- Admin panel displays in selected language

---

## 📱 MOBILE TESTING

### Using same credentials on mobile:
- Responsive design works
- Forms are mobile-friendly
- OTP input works on phone keyboard
- Payment form accessible

### Test Devices
- iPhone (Safari)
- Android (Chrome)
- Tablet (iPad, Android)
- Desktop (Chrome, Firefox, Safari, Edge)

---

## 🚀 QUICK TEST SCENARIOS

### Scenario 1: Complete Customer Journey
```
1. Register: customer@example.com / Password@123
2. Verify email via OTP
3. Login
4. Browse products (search OEM)
5. Add to cart
6. Checkout (5 steps)
7. Complete payment
8. View order in dashboard
```

### Scenario 2: Admin Order Management
```
1. Login: admin@example.com / password
2. Go to Orders
3. View recent order (from scenario 1)
4. Update order status
5. Add internal note
6. View customer details
```

### Scenario 3: Guest Checkout
```
1. Add items to cart (no login)
2. Click Checkout
3. Enter email: guest@example.com
4. Verify email via OTP
5. Enter shipping address
6. Select shipping method
7. Choose payment method
8. Complete payment
9. Receive confirmation email
```

### Scenario 4: Product Management
```
1. Login: admin@example.com / password
2. Go to Products
3. Create new product (test OEM: TEST123)
4. Set price, condition, stock
5. Publish
6. Search for product (TEST123)
7. View in catalog
8. Add to cart and checkout
```

---

## ⚠️ IMPORTANT NOTES

### For Local Development
```
Email can't be verified (no real SMTP)
Set MAIL_MAILER=log in .env
Check logs at storage/logs/laravel.log
For testing OTP, use log output
```

### For Production
```
Real email service required
SMTP credentials must be configured
OTP emails sent within 30 seconds
Enable CHECKOUT_SKIP_OTP=false
```

### Security Reminder
```
✅ Change default passwords before going live
✅ Use strong passwords for production
✅ Don't share credentials in version control
✅ Use .env for sensitive data
✅ Rotate credentials regularly
```

---

## 📋 TESTING CHECKLIST

- [ ] Admin can login with credentials
- [ ] Customer can login with credentials
- [ ] Guest checkout works (OTP verification)
- [ ] New customer registration works
- [ ] Email verification via OTP works
- [ ] Payment with test card works
- [ ] Order appears in admin dashboard
- [ ] Order appears in customer account
- [ ] All 5 languages work with login
- [ ] Mobile login works
- [ ] Password reset works
- [ ] Change password works
- [ ] Account settings editable
- [ ] Order history visible

---

## 🆘 TROUBLESHOOTING

### Can't login?
- [ ] Check email spelling
- [ ] Check password spelling (case-sensitive)
- [ ] Email must be verified (OTP) for new accounts
- [ ] Account must be active (not disabled by admin)

### OTP not received?
- [ ] Check spam/junk folder
- [ ] Check MAIL_MAILER setting (.env)
- [ ] If `MAIL_MAILER=log`, check `storage/logs/laravel.log`
- [ ] Wait 30 seconds before resend

### Can't complete payment?
- [ ] Use test card: 4111 1111 1111 1111
- [ ] Check Airwallex credentials configured
- [ ] Check payment form loads properly
- [ ] Check browser console for errors

### Can't create account?
- [ ] Email must be unique (not used before)
- [ ] Password must meet requirements
- [ ] Terms checkbox must be checked
- [ ] Check for validation errors

---

## 📞 SUPPORT

If having issues:
1. Check error message
2. Review logs: `storage/logs/laravel.log`
3. Check `.env` configuration
4. Verify database connection
5. Clear cache: `php artisan cache:clear`

---

**Good luck with testing!** 🚀

Once all tests pass with these credentials, you're ready to deploy!
