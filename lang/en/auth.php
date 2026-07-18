<?php

return [
    // Modal chrome
    'close' => 'Close',
    'welcome_back' => 'Welcome back',
    'create_account' => 'Create account',
    'verify_email' => 'Verify email',
    'sign_in_subtitle' => 'Sign in to continue · Secure session',
    'register_subtitle' => 'Free account · Verified email',
    'otp_subtitle' => 'One-time code · Secure verification',
    'sign_in' => 'Sign in',
    'register' => 'Register',

    // Login form
    'email_address' => 'Email address',
    'password' => 'Password',
    'forgot' => 'Forgot?',
    'remember_me' => 'Remember me',
    'signing_in' => 'Signing in…',
    'new_here' => 'New here?',
    'create_free_account' => 'Create free account',

    // Register form
    'full_name' => 'Full name',
    'password_min_chars' => 'Password · min :min chars, mixed case, numbers & symbols',
    'min_characters' => 'Min :min characters',
    'show_password' => 'Show password',
    'hide_password' => 'Hide password',
    'confirm_password' => 'Confirm password',
    'agree_terms_prefix' => 'I agree to the',
    'terms_of_service' => 'Terms of Service',
    'and' => 'and',
    'privacy_policy' => 'Privacy Policy',
    'creating' => 'Creating…',
    'already_a_member' => 'Already a member?',
    'sign_in_instead' => 'Sign in instead',

    // OTP verification
    'enter_code_emailed_to' => 'Enter the code we emailed to',
    'verification_code' => 'Verification code',
    'verifying' => 'Verifying…',
    'verify_and_continue' => 'Verify & continue',
    'resend_code' => 'Resend code',
    'back_to_sign_in' => 'Back to sign in',

    // JS-side messages (component's Alpine script)
    'invalid_credentials' => 'Invalid credentials',
    'registration_failed' => 'Registration failed',
    'registration_disabled' => 'New account registration is currently unavailable. Please try again later or contact support.',
    'session_expired' => 'Your session has expired due to inactivity. Please sign in again.',
    'invalid_or_expired_code' => 'Invalid or expired code.',
    'email_verified_please_sign_in' => 'Email verified — please sign in.',
    'new_code_sent' => 'A new code has been sent to your email.',
    'could_not_resend_code' => 'Could not resend the code.',

    // Controller JSON responses (App\Http\Controllers\Frontend\AuthController)
    'validation_failed' => 'Validation failed',
    'invalid_email_or_password' => 'Invalid email or password.',
    'account_deactivated' => 'Your account has been deactivated.',
    'email_verification_required' => 'Email verification required.',
    'login_successful' => 'Login successful.',
    'registration_successful' => 'Registration successful. Please verify your email.',
    'logged_out_successfully' => 'Logged out successfully.',
    'invalid_input' => 'Invalid input',
    'otp_verified_successfully' => 'OTP verified successfully.',
    'otp_resent_successfully' => 'OTP resent successfully.',
    'too_many_login_attempts' => 'Too many login attempts. Please try again in :minutes minutes.',

    // Password reset pages (auth/passwords/email.blade.php + reset.blade.php)
    'breadcrumb_home' => 'Home',
    'breadcrumb_reset_password' => 'Reset password',
    'breadcrumb_new_password' => 'New password',
    'reset_password_title' => 'Reset Password',
    'eyebrow_request_link' => '01 · Request link',
    'eyebrow_set_new_password' => '02 · Set new password',
    'reset_password_heading' => 'Reset password',
    'new_password_heading' => 'New password',
    'request_link_subtitle' => 'Enter your email · we\'ll send a secure reset link',
    'new_password_subtitle' => 'Choose a strong password · min :min chars',
    'email_verification_eyebrow' => 'Email verification',
    'credentials_reset_eyebrow' => 'Credentials reset',
    'send_reset_link' => 'Send reset link',
    'or_divider' => 'or',
    'back_to_homepage' => 'Back to homepage',
    'expires_minutes' => 'EXPIRES · :minutes MIN',
    'token_single_use' => 'Token · Single-use',
];
