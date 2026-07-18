<?php

return [
    // Breadcrumb / page chrome
    'breadcrumb_home' => 'Home',
    'breadcrumb_contact_us' => 'Contact Us',
    'eyebrow_contact_desk' => 'Contact · Desk',
    'eyebrow_direct_channel' => 'Direct · Channel',
    'channel_email_label' => 'Email',
    'channel_phone_label' => 'Phone',
    'hours_label' => 'Hours',
    'eyebrow_enquiry_form' => 'Enquiry · Form',
    'spam_protected_note' => 'Spam-protected · GDPR-safe',
    'eyebrow_info_rail' => 'Info · Rail',
    'looking_for_part_heading' => 'Looking for a part?',
    'looking_for_part_body' => 'For part searches, use the console directly — you will get results in seconds.',
    'open_search_btn' => 'Open search',
    'network_error' => 'Network error. Please try again.',

    // Validation messages (ContactFormRequest)
    'validation_email_required' => 'Please enter your email address.',
    'validation_email_invalid' => 'Please enter a valid email address.',
    'validation_name_required' => 'Please enter your name.',
    'validation_subject_required' => 'Please select a subject.',
    'validation_message_required' => 'Please enter your message.',
    'validation_message_min' => 'Your message must be at least 10 characters.',
    'validation_message_max' => 'Your message cannot exceed 5000 characters.',

    // Hero
    'title' => 'Contact our desk',
    'description' => 'Reach the OeParts team for sourcing, order questions, or partnership enquiries. Every message is routed to the right specialist and answered within one working day.',

    // Form fields
    'name' => 'Full name',
    'name_placeholder' => 'Jane Doe',
    'email' => 'Email address',
    'email_placeholder' => 'name@example.com',

    // Email verification
    'verify_email' => 'Send code',
    'sending' => 'Sending',
    'email_verification_note' => 'We will send a 6-digit code to verify the address before your message is routed.',
    'verification_code' => 'Verification code',
    'verify' => 'Verify',
    'verifying' => 'Verifying',
    'email_verified' => 'Email verified',
    'change_email' => 'Change email',
    'code_sent_note' => 'Code sent. Enter the 6-digit code from your inbox to continue.',
    'resend_code' => 'Resend code',

    // Subject
    'subject' => 'Subject',
    'select_subject' => 'Select a subject…',
    'subjects' => [
        'general_inquiry' => 'General inquiry',
        'part_not_found' => 'Part not found',
        'order_issue' => 'Existing order issue',
        'shipping_question' => 'Shipping question',
        'return_refund' => 'Return or refund',
        'b2b_partnership' => 'B2B partnership',
        'other' => 'Other',
    ],

    // Optional / conditional fields
    'order_number' => 'Order number',
    'order_number_placeholder' => 'ORD-2026-00123',
    'oem_number' => 'OEM number',
    'oem_number_placeholder' => '11127556503',
    'manufacturer' => 'Manufacturer / brand',
    'manufacturer_placeholder' => 'BMW, Audi, Mercedes…',
    'company_name' => 'Company name',
    'company_name_placeholder' => 'Acme Automotive Ltd.',
    'car_model' => 'Vehicle model',
    'car_model_placeholder' => '3 Series, A4, C-Class…',
    'vehicle_year' => 'Year',
    'vehicle_year_placeholder' => '2018',
    'vin_number' => 'VIN (optional)',
    'vin_number_placeholder' => '17-character vehicle identification',
    'section_order_details' => 'Order details',
    'section_part_details' => 'Part & vehicle details',
    'section_b2b_details' => 'Company details',

    // Message
    'message' => 'Your message',
    'message_placeholder' => 'Tell us what you need — part, vehicle, quantity, timeline…',
    'message_min_length' => 'Minimum 10 characters.',

    // Submit
    'send_message' => 'Send message',

    // Sidebar info cards
    'email_us' => 'Email us',
    'whatsapp_label' => 'WhatsApp',
    'viber_label' => 'Viber',
    'address_label' => 'Address',
    'response_time' => 'Response time',
    'response_time_value' => 'Within 1 business day',
    'secure' => 'Secure channel',
    'secure_note' => 'Your message is sent over an encrypted connection (HTTPS/TLS) and your data is handled under GDPR.',

    // Flash / status
    'sent_success' => 'Message sent — we will get back to you shortly.',
    'sent_failed' => 'Something went wrong sending your message. Please try again.',
    'otp_invalid' => 'That code is incorrect or has expired.',
];
