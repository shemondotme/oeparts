# Email Verification & Payment System - Testing Bypasses

**Testing Purpose এর জন্য যেখানে যেখানে bypass করা হয়েছে:**

---

## 🔴 EMAIL VERIFICATION BYPASS

### 1. **Checkout Guest OTP Skip**

**Location**: `.env` file (Line 14)
```env
CHECKOUT_SKIP_OTP=true
```

**Config File**: `config/app.php` (Line 98)
```php
'checkout_skip_otp' => env('CHECKOUT_SKIP_OTP', false),
```

**What It Does**:
- Guest users can checkout WITHOUT email verification
- OTP email is NOT sent
- OTP input form is HIDDEN
- Guest can proceed directly to step 2

**Where It's Used**:

1. **CheckoutController.php** (Line 131)
```php
$skipOtp = (bool) config('app.checkout_skip_otp');

if ($isGuest && $otpValue && !$skipOtp) {
    // Only verify OTP if NOT skipped
    $result = $this->otpService->verify(...);
}

if ($skipOtp) {
    $otpVerified = true; // Mark as verified without checking
}
```

2. **CheckoutService.php** (Line 128)
```php
$skipOtp = (bool) config('app.checkout_skip_otp');

if ($isGuest
    && ($skipOtp || $data['otp_verified'] === true || auth()->check())
) {
    // Allow guest to proceed
}
```

3. **step1.blade.php** (Line 84, 93-94, 100, 211)
```blade
@php $skipOtp = (bool) config('app.checkout_skip_otp'); @endphp

@if($skipOtp)
    <div class="bg-amber-100 border border-amber-400 p-4 rounded">
        <p class="bp-spec text-amber-ink">§ TEST · OTP · BYPASSED</p>
        <p class="text-xs text-body mt-1">
            Email verification is temporarily disabled 
            (<code>CHECKOUT_SKIP_OTP=true</code>). 
            Re-enable in .env before going live.
        </p>
    </div>
@endif

<!-- OTP form only shown if NOT skipped -->
<template x-if="!{{ $skipOtp ? 'true' : 'false' }} && isGuest && !otpVerified">
    {{-- OTP input form --}}
</template>

<!-- Initial state reflects bypass -->
otpVerified: {{ ($checkoutData['otp_verified'] ?? false) || config('app.checkout_skip_otp') ? 'true' : 'false' }}
```

**🟡 Current Status**: 
```
CHECKOUT_SKIP_OTP=true
⚠️ EMAIL VERIFICATION IS BYPASSED FOR LOCAL TESTING
```

**⚠️ WARNING**: This must be set to `false` before production!

---

## 💳 PAYMENT SYSTEM BYPASS

### 1. **Airwallex Sandbox Environment**

**Location**: `.env` file (Line 63)
```env
AIRWALLEX_ENV=demo
```

**SettingsSeeder.php** (Line 127)
```php
['group' => 'payment', 'key' => 'airwallex_environment', 'value' => 'sandbox', 'type' => $s]
```

**PaymentService.php** (Line 46)
```php
$environment = $this->settings->get('payment.airwallex_environment', 'sandbox');

// API endpoints:
// Sandbox: https://api.airwallex.com/api/v1
// Live: https://api.airwallex.com/api/v1
// (same endpoint, but credentials determine sand/live)
```

**What It Does**:
- Uses Airwallex SANDBOX (demo/test) environment
- Test credit cards work (actual payment NOT charged)
- Webhook events are simulated
- No real money involved

**🟡 Current Status**:
```
AIRWALLEX_ENV=demo (Sandbox)
AIRWALLEX_CLIENT_ID= (empty - requires actual key)
AIRWALLEX_API_KEY= (empty - requires actual key)
AIRWALLEX_WEBHOOK_SECRET= (empty - requires actual secret)

⚠️ PAYMENT SYSTEM NOT FULLY CONFIGURED FOR LOCAL USE
```

**Test Cards Available** (Airwallex Sandbox):
```
Card Number: 4111 1111 1111 1111
Expiry: Any future date (e.g., 12/25)
CVV: Any 3 digits (e.g., 123)
Result: Payment will be PENDING (not actually charged)
```

---

### 2. **No Actual Payment Credentials**

**Current .env**:
```env
AIRWALLEX_CLIENT_ID=        # ← EMPTY
AIRWALLEX_API_KEY=          # ← EMPTY  
AIRWALLEX_WEBHOOK_SECRET=   # ← EMPTY
```

**What This Means**:
- Payment intents CANNOT be created
- Checkout will FAIL at step 4 (payment)
- Real testing of payment flow requires actual Airwallex credentials

**Error That Will Occur** (PaymentService.php, Line 48-49):
```php
if (empty($apiKey) || empty($clientId)) {
    throw new \RuntimeException('Airwallex credentials not configured.');
}
```

---

## 📋 SUMMARY OF BYPASSES

| Component | Bypass | Location | Status | Production |
|-----------|--------|----------|--------|------------|
| **Guest Checkout OTP** | ✅ ACTIVE | `.env` line 14 | `CHECKOUT_SKIP_OTP=true` | ❌ MUST BE FALSE |
| **Email Verification** | ✅ BYPASSED | CheckoutController | Guests skip OTP | ❌ MUST BE ENABLED |
| **Airwallex Payment** | ⚠️ SANDBOX | PaymentService | Demo mode | ✅ OK for local |
| **Payment Credentials** | ❌ EMPTY | `.env` | Not configured | ⚠️ NEEDS SETUP |
| **Test Card Support** | ✅ YES | Airwallex | Use test cards | ✅ OK for testing |

---

## 🟢 WHAT WORKS WITHOUT BYPASS

✅ **User Registration + Email Verification** → Works normally, requires OTP
✅ **User Login + Email Verification** → Works normally, requires OTP  
✅ **Password Reset via OTP** → Works normally, requires OTP
✅ **Contact Form with OTP** → Works normally, requires OTP
✅ **Account Settings Email Verify** → Works normally, requires OTP

---

## 🔴 WHAT'S BYPASSED

❌ **Guest Checkout Email Verification** → SKIPPED (if `CHECKOUT_SKIP_OTP=true`)
❌ **Payment Processing** → Not testable (no credentials)

---

## 🚀 TO FIX BEFORE PRODUCTION

### Step 1: Disable OTP Bypass
```env
# Change from:
CHECKOUT_SKIP_OTP=true

# To:
CHECKOUT_SKIP_OTP=false
```

### Step 2: Get Airwallex Production Credentials
- Log in to Airwallex dashboard
- Generate API keys (production)
- Set in .env:
```env
AIRWALLEX_CLIENT_ID=your_live_client_id
AIRWALLEX_API_KEY=your_live_api_key
AIRWALLEX_WEBHOOK_SECRET=your_live_webhook_secret
AIRWALLEX_ENV=live
```

### Step 3: Update Settings in DB
```php
// Or via admin panel:
// Payment settings → Airwallex environment: live
// Payment settings → Client ID: production_id
// Payment settings → API Key: production_key
// Payment settings → Webhook Secret: production_secret
```

### Step 4: Verify All Tests Pass
```bash
php artisan test tests/Feature/AuthTest.php      # Email verification
php artisan test tests/Feature/CheckoutFlowTest.php  # Checkout with OTP
php artisan test tests/Unit/Services/PaymentServiceTest.php  # Payment
```

---

## 📝 CODE COMMENTS IN FILES

### CheckoutController.php (Line 129-130)
```php
// Testing bypass — see CHECKOUT_SKIP_OTP in .env.
// When enabled, guest email verification is treated as passed.
```

### CheckoutService.php (Line 127-128)
```php
// Testing bypass — see CHECKOUT_SKIP_OTP in .env.
```

### step1.blade.php (Line 93-94)
```blade
<p class="bp-spec text-amber-ink">§ TEST · OTP · BYPASSED</p>
<p class="text-xs text-body mt-1">
    Email verification is temporarily disabled (<code>CHECKOUT_SKIP_OTP=true</code>). 
    Re-enable in .env before going live.
</p>
```

### config/app.php (Line 88-94)
```php
/*
|--------------------------------------------------------------------------
| Checkout — Skip Email OTP (testing only)
|--------------------------------------------------------------------------
|
| When CHECKOUT_SKIP_OTP=true in .env, guest checkout will bypass the
| 6-digit email verification step. Use this only for local testing.
| ALWAYS keep this false in production.
|
*/
```

---

## ✅ VERIFICATION CHECKLIST

- [ ] Review `.env` for `CHECKOUT_SKIP_OTP=true`
- [ ] Review `.env` for empty payment credentials
- [ ] All tests pass with OTP enabled
- [ ] Contact form requires OTP ✅
- [ ] Registration requires email verification ✅
- [ ] Login requires email verification ✅
- [ ] Password reset requires OTP ✅
- [ ] Checkout guest DOES require OTP (when enabled) ⚠️ Currently bypassed
- [ ] Payment system has valid credentials (not setup locally)

---

## 🎯 LOCAL VS PRODUCTION

### Local Development (.env)
```env
APP_ENV=local                          # ← Local
APP_DEBUG=true                         # ← Debug enabled
CHECKOUT_SKIP_OTP=true                # ⚠️ OTP bypassed
AIRWALLEX_ENV=demo                    # ✅ Sandbox OK
AIRWALLEX_CLIENT_ID=                  # ✅ Empty OK
MAIL_MAILER=log                       # ✅ Log emails
```

### Production (.env or secrets)
```env
APP_ENV=production                     # ← Production
APP_DEBUG=false                        # ← Debug disabled
CHECKOUT_SKIP_OTP=false               # ✅ OTP required
AIRWALLEX_ENV=live                    # ✅ Live credentials
AIRWALLEX_CLIENT_ID=<real_id>         # ✅ Real credentials
AIRWALLEX_API_KEY=<real_key>          # ✅ Real credentials
AIRWALLEX_WEBHOOK_SECRET=<real>       # ✅ Real credentials
MAIL_MAILER=smtp                      # ✅ Real SMTP
```

---

## 🚨 SECURITY IMPLICATIONS

### Current Local Setup
- ✅ Safe for local development
- ✅ OTP bypass documented
- ✅ All warnings in code/UI visible
- ✅ Tests verify real OTP works when enabled

### If Deployed as-is
- ❌ **CRITICAL**: Guest checkout skips email verification
- ❌ **CRITICAL**: Spam/abuse risk
- ❌ **CRITICAL**: Unverified email addresses
- ⚠️ Payment system won't work

**Status**: ✅ Safe for local, but MUST fix before production deployment
