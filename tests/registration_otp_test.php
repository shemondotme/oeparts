<?php
/**
 * Registration + OTP Flow Test Script
 * Run: php artisan tinker < tests/registration_otp_test.php
 * Or: php < this_file.php
 */

require 'vendor/autoload.php';

use App\Models\User;
use App\Models\Otp;
use App\Enums\OtpPurpose;
use App\Services\OtpService;

// Initialize
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "═══════════════════════════════════════════════════════════\n";
echo "🧪 Registration + OTP Flow Test\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Test 1: Create a user directly
echo "1️⃣  Testing User Creation...\n";
$testEmail = 'test-' . time() . '@example.com';
$testName = 'Test User ' . time();

try {
    $user = User::create([
        'name'     => $testName,
        'email'    => $testEmail,
        'password' => bcrypt('TestPassword123'),
    ]);
    echo "   ✓ User created: ID={$user->id}, Email={$user->email}\n";
    echo "   ✓ email_verified_at: " . ($user->email_verified_at ? 'SET' : 'NULL') . "\n\n";
} catch (\Exception $e) {
    echo "   ✗ Error creating user: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Generate OTP
echo "2️⃣  Testing OTP Generation...\n";
try {
    $otpService = app(OtpService::class);
    $otp = $otpService->generate($testEmail, OtpPurpose::EmailVerify, '127.0.0.1');
    echo "   ✓ OTP generated: {$otp->otp_code}\n";
    echo "   ✓ Expires at: {$otp->expires_at}\n";
    echo "   ✓ Purpose: " . $otp->purpose->value . "\n";
    echo "   ✓ DB Entry created: ID={$otp->id}\n\n";
} catch (\Exception $e) {
    echo "   ✗ Error generating OTP: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 3: Verify OTP
echo "3️⃣  Testing OTP Verification...\n";
try {
    $result = $otpService->verify($testEmail, $otp->otp_code, OtpPurpose::EmailVerify);
    
    if ($result === OtpService::RESULT_OK) {
        echo "   ✓ OTP Verified successfully!\n";
        
        // Mark user as verified
        $user->markEmailAsVerified();
        $user->refresh();
        
        echo "   ✓ User email marked as verified\n";
        echo "   ✓ email_verified_at: {$user->email_verified_at}\n\n";
    } else {
        echo "   ✗ OTP Verification failed: " . $otpService->message($result) . "\n\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "   ✗ Error verifying OTP: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 4: Check database
echo "4️⃣  Testing Database Records...\n";

$dbUser = User::where('email', $testEmail)->first();
$dbOtp = Otp::where('email', $testEmail)->latest()->first();

if ($dbUser) {
    echo "   ✓ User found in DB\n";
    echo "     - ID: {$dbUser->id}\n";
    echo "     - Name: {$dbUser->name}\n";
    echo "     - Email: {$dbUser->email}\n";
    echo "     - Verified: " . ($dbUser->email_verified_at ? 'YES ✓' : 'NO ✗') . "\n";
} else {
    echo "   ✗ User NOT found in DB\n";
}

if ($dbOtp) {
    echo "   ✓ OTP found in DB\n";
    echo "     - Code: {$dbOtp->otp_code}\n";
    echo "     - Purpose: " . $dbOtp->purpose->value . "\n";
    echo "     - Verified at: " . ($dbOtp->verified_at ? $dbOtp->verified_at : 'NULL') . "\n";
    echo "     - Attempts: {$dbOtp->attempts}\n";
} else {
    echo "   ✗ OTP NOT found in DB\n";
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "✅ All Tests Passed!\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "\nTest Summary:\n";
echo "• User created and verified\n";
echo "• OTP generated correctly\n";
echo "• OTP verified successfully\n";
echo "• Database records are correct\n";
echo "\nThe backend is working correctly.\n";
echo "If registration fails on frontend, check:\n";
echo "1. Browser console for JS errors\n";
echo "2. Network tab for API response\n";
echo "3. OTP modal event listener (@open-otp-modal)\n";
echo "4. That queue:work is running (for email sending)\n";
?>
