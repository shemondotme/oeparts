@extends('layouts.app')

@section('title', 'OTP Modal Test')

@section('content')
<div class="min-h-screen bg-paper p-8">
    <div class="max-w-2xl mx-auto space-y-8">
        <div class="bg-ink text-ivory p-6 border border-amber">
            <h1 class="text-3xl font-bold mb-2">🧪 OTP Modal Test</h1>
            <p class="text-ivory/70">Direct test to verify OTP modal is working</p>
        </div>

        <div class="space-y-4">
            <h2 class="text-xl font-bold text-ink">Test Controls</h2>
            
            {{-- Manual OTP Modal Trigger --}}
            <button 
                onclick="testOtpModal()"
                class="px-6 py-3 bg-amber text-ink font-bold border-2 border-amber hover:bg-amber/90 transition"
            >
                🎯 Open OTP Modal (Email Verify)
            </button>

            {{-- Manual Guest Checkout OTP Trigger --}}
            <button 
                onclick="testGuestCheckout()"
                class="px-6 py-3 bg-blue-600 text-white font-bold border-2 border-blue-600 hover:bg-blue-700 transition"
            >
                🛒 Open OTP Modal (Guest Checkout)
            </button>

            {{-- Check if Alpine is loaded --}}
            <button 
                onclick="checkAlpine()"
                class="px-6 py-3 bg-green-600 text-white font-bold border-2 border-green-600 hover:bg-green-700 transition"
            >
                ✓ Check Alpine.js Status
            </button>

            {{-- Test Registration Auto-Dispatch --}}
            <button 
                onclick="simulateRegistration()"
                class="px-6 py-3 bg-purple-600 text-white font-bold border-2 border-purple-600 hover:bg-purple-700 transition"
            >
                📝 Simulate Registration (Auto OTP)
            </button>
        </div>

        {{-- Console Output --}}
        <div class="bg-ink text-ivory p-4 border border-amber font-mono text-sm space-y-2 max-h-96 overflow-y-auto rounded">
            <p class="text-amber">📋 Console Output:</p>
            <div id="console-output"></div>
        </div>

        {{-- Instructions --}}
        <div class="bg-blue-50 border border-blue-200 p-6 rounded space-y-3">
            <h3 class="font-bold text-blue-900 text-lg">📖 Instructions</h3>
            <ol class="space-y-2 text-blue-800 list-decimal list-inside">
                <li>Open <strong>DevTools (F12)</strong> → <strong>Console</strong> tab</li>
                <li>Click one of the buttons above</li>
                <li>Check if OTP modal appears</li>
                <li>Check console for logs (both below and in DevTools)</li>
                <li>If modal appears: ✅ Frontend works</li>
                <li>If modal doesn't appear: Check console for errors</li>
            </ol>
        </div>

        {{-- Expected Behavior --}}
        <div class="bg-green-50 border border-green-200 p-6 rounded space-y-3">
            <h3 class="font-bold text-green-900 text-lg">✅ Expected Behavior</h3>
            <ul class="space-y-2 text-green-800 list-disc list-inside">
                <li>When you click a button, OTP modal should appear</li>
                <li>Modal shows: "Check your email" with email address</li>
                <li>6 input fields for OTP code</li>
                <li>"Resend code" and "Cancel verification" buttons</li>
                <li>Console shows success logs</li>
            </ul>
        </div>

        {{-- Troubleshooting --}}
        <div class="bg-red-50 border border-red-200 p-6 rounded space-y-3">
            <h3 class="font-bold text-red-900 text-lg">🐛 If Modal Doesn't Appear</h3>
            <ol class="space-y-2 text-red-800 list-decimal list-inside">
                <li>Check console for JavaScript errors (red)</li>
                <li>Verify Alpine.js is loaded (click "Check Alpine.js Status")</li>
                <li>Check browser DevTools console for event dispatch logs</li>
                <li>Check if `x-show="show"` CSS is blocking visibility</li>
                <li>Try refreshing the page</li>
            </ol>
        </div>
    </div>
</div>

<script>
// Utility: Log to both console and page
function log(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const prefix = {
        'info': 'ℹ️',
        'success': '✅',
        'error': '❌',
        'warn': '⚠️'
    }[type] || '•';
    
    const fullMsg = `[${timestamp}] ${prefix} ${message}`;
    console.log(fullMsg);
    
    const output = document.getElementById('console-output');
    if (output) {
        const line = document.createElement('div');
        line.textContent = fullMsg;
        line.style.color = {
            'success': '#10b981',
            'error': '#ef4444',
            'warn': '#f59e0b'
        }[type] || '#a0aec0';
        output.appendChild(line);
        output.parentElement.scrollTop = output.parentElement.scrollHeight;
    }
}

// Test 1: Direct OTP Modal Trigger (Email Verify)
function testOtpModal() {
    log('🎯 Testing OTP Modal - Email Verify', 'info');
    
    const testEmail = 'test-' + Date.now() + '@example.com';
    const detail = {
        email: testEmail,
        purpose: 'email_verify'
    };
    
    log(`Dispatching with email: ${testEmail}`, 'info');
    log(`Detail object: ${JSON.stringify(detail)}`, 'info');
    
    // Method 1: Alpine dispatch
    try {
        window.dispatchEvent(new CustomEvent('open-otp-modal', {
            detail: detail
        }));
        log('✓ Event dispatched via window.dispatchEvent', 'success');
    } catch (e) {
        log(`✗ Error: ${e.message}`, 'error');
    }
}

// Test 2: Guest Checkout OTP
function testGuestCheckout() {
    log('🛒 Testing OTP Modal - Guest Checkout', 'info');
    
    const detail = {
        email: 'guest-' + Date.now() + '@example.com',
        purpose: 'guest_checkout'
    };
    
    log(`Dispatching with email: ${detail.email}`, 'info');
    
    try {
        window.dispatchEvent(new CustomEvent('open-otp-modal', {
            detail: detail
        }));
        log('✓ Event dispatched', 'success');
    } catch (e) {
        log(`✗ Error: ${e.message}`, 'error');
    }
}

// Test 3: Check Alpine.js
function checkAlpine() {
    log('🔍 Checking Alpine.js...', 'info');
    
    if (window.Alpine) {
        log('✓ Alpine.js is loaded', 'success');
        
        // Check if otpInput component is registered
        if (window.Alpine.data && window.Alpine.data.otpInput) {
            log('✓ otpInput Alpine component is registered', 'success');
        } else {
            log('⚠️ otpInput component not found', 'warn');
        }
        
        // Try to check if store exists
        try {
            const store = window.Alpine.store?.('oemSearchView');
            if (store) {
                log('✓ Alpine stores are working', 'success');
            }
        } catch (e) {
            log(`Alpine info: ${e.message}`, 'warn');
        }
    } else {
        log('❌ Alpine.js is NOT loaded!', 'error');
    }
    
    // Check for OTP modal element
    const modalElement = document.querySelector('[x-data*="show: false"]');
    if (document.querySelector('div[x-data*="open(detail)"]')) {
        log('✓ OTP modal element found in DOM', 'success');
    } else {
        log('⚠️ OTP modal element not found in DOM', 'warn');
    }
}

// Test 4: Simulate Registration (Auto-dispatch OTP)
function simulateRegistration() {
    log('📝 Simulating Registration...', 'info');
    
    const testEmail = 'simulated-' + Date.now() + '@example.com';
    
    // Simulate the auth-modal registration success handler
    log(`Creating test user with email: ${testEmail}`, 'info');
    
    // Close auth modal first (simulate)
    log('Simulating: Auth modal closing...', 'info');
    
    // Then dispatch OTP modal with delay (like in auth-modal.blade.php)
    setTimeout(() => {
        log(`Dispatching OTP modal after 300ms delay...`, 'info');
        
        window.dispatchEvent(new CustomEvent('open-otp-modal', {
            detail: {
                email: testEmail,
                purpose: 'email_verify'
            }
        }));
        
        log('✓ OTP modal dispatched (like registration does)', 'success');
    }, 300);
}

// Log when page loads
document.addEventListener('DOMContentLoaded', () => {
    log('🚀 OTP Modal Test Page Loaded', 'success');
    checkAlpine(); // Auto-check Alpine on load
});

// Log any Alpine init
if (window.Alpine) {
    document.addEventListener('alpine:init', () => {
        log('✓ Alpine.js initialized', 'success');
    });
}

// Listen for OTP modal events
window.addEventListener('open-otp-modal', (e) => {
    log(`📨 open-otp-modal event received: ${JSON.stringify(e.detail)}`, 'success');
});
</script>
@endsection
