<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Template Translations (English)
    |--------------------------------------------------------------------------
    */

    // ─── Layout ──────────────────────────────────────────────────────────
    'layout' => [
        'tagline'     => 'Genuine OEM Parts · European Distributor',
        'footer_line1' => '© :year Oe Parts. All rights reserved.',
        'footer_line2' => 'You received this email because you have an account or placed an order with us.',
    ],

    // ─── OTP (One-Time Password) ─────────────────────────────────────────
    'otp' => [
        'subject' => 'Your verification code: :code',
        'title'   => 'Verification Code',
        'body'    => 'Your one-time verification code is:',
        'expiry'  => 'This code will expire in :minutes minutes.',
        'ignore'  => 'If you did not request this code, please ignore this email.',
    ],

    // ─── Order Confirmation ──────────────────────────────────────────────
    'order_confirmation' => [
        'subject'           => 'Order :order_number confirmed — :site',
        'title'             => 'Order Confirmed',
        'greeting'          => 'Dear :name,',
        'body'              => 'Thank you for your order **:order_number**. We have received it and are processing it now.',
        'estimated_delivery' => 'Estimated delivery: :min – :max business days',
        'order_summary'     => 'Order Summary',
        'order_number'      => 'Order Number',
        'order_date'        => 'Order Date',
        'shipping_method'   => 'Shipping Method',
        'shipping_address'  => 'Shipping Address',
        'order_items'       => 'Items Ordered',
        'quantity'          => 'Qty',
        'price'             => 'Price',
        'total'             => 'Total',
        'subtotal'          => 'Subtotal',
        'discount'          => 'Discount',
        'shipping'          => 'Shipping',
        'handling_fee'      => 'Handling Fee',
        'vat'               => 'VAT',
        'grand_total'       => 'Grand Total',
        'footer'            => 'You can view your order details anytime from your account.',
        'view_order'        => 'View Order',
    ],

    // ─── Order Status Update ─────────────────────────────────────────────
    'order_status' => [
        'subject'         => 'Order :order_number is now :status',
        'title'           => 'Order Status Update',
        'body'            => 'The status of your order **:order_number** has been updated.',
        'order_number'    => 'Order Number',
        'previous_status' => 'Previous Status',
        'new_status'      => 'New Status',
        'view_order'      => 'View Order',
    ],

    // Used by HTML template (inconsistency with text template)
    'order_status_update' => [
        'greeting' => 'Dear :name,',
        'body'     => 'The status of your order has been updated.',
    ],

    // ─── Order Shipped ───────────────────────────────────────────────────
    'order_shipped' => [
        'subject'         => 'Your order :order_number has been shipped',
        'title'           => 'Order Shipped',
        'greeting'        => 'Dear :name,',
        'body'            => 'Great news! Your order **:order_number** has been shipped.',
        'carrier'         => 'Carrier',
        'tracking_number' => 'Tracking Number',
        'track_package'   => 'Track Package',
        'view_order'      => 'View Order',
    ],

    // ─── Password Reset ──────────────────────────────────────────────────
    'password_reset' => [
        'subject'      => 'Reset your password',
        'headline'     => 'Reset Your Password',
        'body'         => 'You are receiving this email because we received a password reset request for your account.',
        'cta'          => 'Reset Password',
        'expiry_note'  => 'This password reset link will expire in :minutes minutes.',
        'fallback_note'=> 'If you did not request a password reset, no further action is required.',
    ],

    // ─── Welcome Email ───────────────────────────────────────────────────
    'welcome' => [
        'subject'       => 'Welcome to Oe Parts',
        'title'         => 'Welcome!',
        'greeting'      => 'Dear :name,',
        'body'          => 'Welcome to Oe Parts, :name!',
        'body_intro'    => 'Your account has been created. You can now browse our catalog of genuine OEM parts, save your favourite parts, and track orders.',
        'body_secondary'=> 'Need help finding a part? Our technical team is ready to assist.',
        'cta'           => 'Browse Catalog',
        'support_text'  => 'If you have any questions, simply reply to this email.',
    ],

    // ─── Refund Status Update ────────────────────────────────────────────
    'refund_status' => [
        'subject'         => 'Refund update for order :order_number',
        'title'           => 'Refund Status Update',
        'body'            => 'The refund status for your order **:order_number** has been updated.',
        'order_number'    => 'Order Number',
        'previous_status' => 'Previous Status',
        'new_status'      => 'New Status',
        'view_orders'     => 'View Orders',
    ],

    // Used by HTML template (inconsistency with text template)
    'refund_status_update' => [
        'greeting' => 'Dear :name,',
        'body'     => 'The refund status for your order has been updated.',
    ],

    // ─── Refund Processed ────────────────────────────────────────────────
    'refund_processed' => [
        'subject'         => 'Refund processed for order :order_number',
        'title'           => 'Refund Processed',
        'greeting'        => 'Dear :name,',
        'body'            => 'Your refund for order **:order_number** has been processed.',
        'order_number'    => 'Order Number',
        'refund_amount'   => 'Refund Amount',
        'payment_method'  => 'Payment Method',
        'processing_time' => 'Please allow :days business days for the refund to appear on your statement.',
        'view_orders'     => 'View Orders',
    ],

    // ─── Abandoned Cart ──────────────────────────────────────────────────
    'abandoned_cart' => [
        'subject'  => 'You left parts in your cart',
        'title'    => 'Complete Your Order',
        'greeting' => 'Dear :name,',
        'body'     => 'You left some genuine OEM parts in your cart. Inventory is not reserved until checkout is complete.',
        'cta'      => 'Return to Cart',
    ],

    // ─── Newsletter Confirmation ─────────────────────────────────────────
    'newsletter_confirm' => [
        'subject' => 'Confirm your newsletter subscription',
        'title'   => 'Confirm Your Subscription',
        'body'    => 'Thank you for subscribing to our newsletter. Please confirm your email address by clicking the link below.',
        'cta'     => 'Confirm Subscription',
        'ignore'  => 'If you did not request this subscription, you can safely ignore this email.',
    ],

    // Used by HTML template (inconsistency with text template)
    'newsletter_confirmation' => [
        'greeting' => 'You are one step away from joining the Oe Parts Journal.',
        'body'     => 'We send technical updates, new arrival alerts, and industry insights. No spam, no fluff. Just genuine parts intelligence.',
    ],

    // ─── Newsletter Campaign — compliance footer (appended server-side) ──
    'newsletter_campaign' => [
        'unsubscribe' => 'Unsubscribe from this newsletter',
    ],

    // ─── Contact Reply ───────────────────────────────────────────────────
    'contact_reply' => [
        'subject'          => 'We replied to your inquiry',
        'title'            => 'Reply to Your Inquiry',
        'greeting'         => 'Dear :name,',
        'intro'            => 'We have replied to your inquiry.',
        'original_message' => 'Your original message:',
    ],

    // ─── Part Inquiry ───────────────────────────────────────────────────
    'part_inquiry' => [
        'subject'   => 'New part inquiry: :oem',
        'title'     => 'Part Inquiry Received',
        'greeting'  => 'Dear :name,',
        'body_intro'=> 'Our technical team is reviewing your part inquiry. We will respond with availability, pricing, and cross-reference options within 24 hours.',
    ],

    // ─── Part Inquiry Status Update ──────────────────────────────────────
    'part_inquiry_status' => [
        'subject_sourced'     => 'Good news — we sourced your part :oem',
        'subject_unavailable' => 'Update on your part inquiry :oem',
        'sourced_label'       => 'SUPPORT · PART SOURCED',
        'unavailable_label'   => 'SUPPORT · INQUIRY UPDATE',
        'sourced_title'       => 'We found your part',
        'unavailable_title'   => 'We could not source this part',
        'sourced_body'        => 'We located the part you requested. Our team will contact you shortly with pricing, condition, and delivery options.',
        'unavailable_body'    => 'Despite an extensive search of our supplier network, we could not source this part at this time. We will notify you if it becomes available again.',
        'requested_part'      => 'REQUESTED PART',
        'inquiry_id'          => 'INQUIRY ID',
        'status'              => 'STATUS',
    ],
];
