<?php

return [
    // Breadcrumb / page chrome
    'breadcrumb_home' => 'Inicio',
    'breadcrumb_contact_us' => 'Contacto',
    'eyebrow_contact_desk' => 'Contacto · Equipo',
    'eyebrow_direct_channel' => 'Directo · Canal',
    'channel_email_label' => 'Correo',
    'channel_phone_label' => 'Teléfono',
    'hours_label' => 'Horario',
    'eyebrow_enquiry_form' => 'Consulta · Formulario',
    'spam_protected_note' => 'Protegido contra spam · Conforme al RGPD',
    'eyebrow_info_rail' => 'Info · Columna',
    'looking_for_part_heading' => '¿Busca una pieza?',
    'looking_for_part_body' => 'Para buscar piezas, use la consola de búsqueda directamente — obtendrá resultados en segundos.',
    'open_search_btn' => 'Abrir búsqueda',
    'network_error' => 'Error de red. Inténtelo de nuevo.',

    // Validation messages (ContactFormRequest)
    'validation_email_required' => 'Introduzca su dirección de correo electrónico.',
    'validation_email_invalid' => 'Introduzca una dirección de correo electrónico válida.',
    'validation_name_required' => 'Introduzca su nombre.',
    'validation_subject_required' => 'Seleccione un asunto.',
    'validation_message_required' => 'Introduzca su mensaje.',
    'validation_message_min' => 'Su mensaje debe tener al menos 10 caracteres.',
    'validation_message_max' => 'Su mensaje no puede superar los 5000 caracteres.',

    // Hero
    'title' => 'Contacte con nuestro equipo',
    'description' => 'Póngase en contacto con el equipo de OeParts para consultas de abastecimiento, pedidos o asociaciones. Cada mensaje se dirige al especialista adecuado y se responde en un día laborable.',

    // Form fields
    'name' => 'Nombre completo',
    'name_placeholder' => 'Juana Pérez',
    'email' => 'Correo electrónico',
    'email_placeholder' => 'nombre@ejemplo.es',

    // Email verification
    'verify_email' => 'Enviar código',
    'sending' => 'Enviando',
    'email_verification_note' => 'Le enviaremos un código de 6 dígitos para verificar la dirección antes de enviar su mensaje.',
    'verification_code' => 'Código de verificación',
    'verify' => 'Verificar',
    'verifying' => 'Verificando',
    'email_verified' => 'Correo verificado',
    'change_email' => 'Cambiar correo',
    'code_sent_note' => 'Código enviado. Introduzca el código de 6 dígitos de su bandeja de entrada para continuar.',
    'resend_code' => 'Reenviar código',

    // Subject
    'subject' => 'Asunto',
    'select_subject' => 'Seleccione un asunto…',
    'subjects' => [
        'general_inquiry' => 'Consulta general',
        'part_not_found' => 'Pieza no encontrada',
        'order_issue' => 'Problema con un pedido existente',
        'shipping_question' => 'Pregunta sobre el envío',
        'return_refund' => 'Devolución o reembolso',
        'b2b_partnership' => 'Asociación B2B',
        'other' => 'Otro',
    ],

    // Optional / conditional fields
    'order_number' => 'Número de pedido',
    'order_number_placeholder' => 'ORD-2026-00123',
    'oem_number' => 'Número OEM',
    'oem_number_placeholder' => '11127556503',
    'manufacturer' => 'Fabricante / marca',
    'manufacturer_placeholder' => 'BMW, Audi, Mercedes…',
    'company_name' => 'Nombre de la empresa',
    'company_name_placeholder' => 'Acme Automotive S.L.',
    'car_model' => 'Modelo del vehículo',
    'car_model_placeholder' => 'Serie 3, A4, Clase C…',
    'vehicle_year' => 'Año',
    'vehicle_year_placeholder' => '2018',
    'vin_number' => 'VIN (opcional)',
    'vin_number_placeholder' => 'Número de identificación del vehículo de 17 caracteres',
    'section_order_details' => 'Detalles del pedido',
    'section_part_details' => 'Detalles de la pieza y el vehículo',
    'section_b2b_details' => 'Detalles de la empresa',

    // Message
    'message' => 'Su mensaje',
    'message_placeholder' => 'Cuéntenos qué necesita — pieza, vehículo, cantidad, plazo…',
    'message_min_length' => 'Mínimo 10 caracteres.',

    // Submit
    'send_message' => 'Enviar mensaje',

    // Sidebar info cards
    'email_us' => 'Escríbanos',
    'whatsapp_label' => 'WhatsApp',
    'viber_label' => 'Viber',
    'address_label' => 'Dirección',
    'response_time' => 'Tiempo de respuesta',
    'response_time_value' => 'En 1 día laborable',
    'secure' => 'Canal seguro',
    'secure_note' => 'Su mensaje se envía a través de una conexión cifrada (HTTPS/TLS) y sus datos se tratan conforme al RGPD.',

    // Flash / status
    'sent_success' => 'Mensaje enviado — nos pondremos en contacto con usted en breve.',
    'sent_failed' => 'Se produjo un error al enviar su mensaje. Inténtelo de nuevo.',
    'otp_invalid' => 'Ese código es incorrecto o ha caducado.',
];
