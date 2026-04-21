<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Checkout - Coming Soon') }} | OEMHub</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            color: white;
        }
        .container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 60px 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 800;
        }
        p {
            font-size: 1.2rem;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        .icon {
            font-size: 5rem;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            background: white;
            color: #764ba2;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-top: 20px;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .sprint-info {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🛒</div>
        <h1>{{ __('Checkout Coming Soon') }}</h1>
        <p>{{ __('The checkout system is currently under development as part of Sprint 8.') }}</p>
        <p>{{ __('You can continue browsing and adding items to your cart. The full checkout experience with payment processing, shipping options, and order confirmation will be available soon.') }}</p>
        
        <div class="sprint-info">
            <p><strong>{{ __('Current Sprint:') }}</strong> Sprint 7 - Cart System</p>
            <p><strong>{{ __('Next Sprint:') }}</strong> Sprint 8 - Checkout Flow</p>
            <p><strong>{{ __('Estimated Completion:') }}</strong> {{ __('Following development cycle') }}</p>
        </div>
        
        <a href="{{ route('frontend.cart.index', ['lang' => app()->getLocale()]) }}" class="btn">
            {{ __('Return to Cart') }}
        </a>
    </div>
</body>
</html>