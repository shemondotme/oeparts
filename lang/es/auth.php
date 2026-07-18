<?php

return [
    // Modal chrome
    'close' => 'Cerrar',
    'welcome_back' => 'Bienvenido de nuevo',
    'create_account' => 'Crear cuenta',
    'verify_email' => 'Verificar correo',
    'sign_in_subtitle' => 'Inicie sesión para continuar · Sesión segura',
    'register_subtitle' => 'Cuenta gratuita · Correo verificado',
    'otp_subtitle' => 'Código de un solo uso · Verificación segura',
    'sign_in' => 'Iniciar sesión',
    'register' => 'Registrarse',

    // Login form
    'email_address' => 'Correo electrónico',
    'password' => 'Contraseña',
    'forgot' => '¿Olvidada?',
    'remember_me' => 'Recordarme',
    'signing_in' => 'Iniciando sesión…',
    'new_here' => '¿Nuevo aquí?',
    'create_free_account' => 'Crear cuenta gratuita',

    // Register form
    'full_name' => 'Nombre completo',
    'password_min_chars' => 'Contraseña · mín. :min caracteres, mayúsculas/minúsculas, números y símbolos',
    'min_characters' => 'Mín. :min caracteres',
    'show_password' => 'Mostrar contraseña',
    'hide_password' => 'Ocultar contraseña',
    'confirm_password' => 'Confirmar contraseña',
    'agree_terms_prefix' => 'Acepto los',
    'terms_of_service' => 'Términos de servicio',
    'and' => 'y la',
    'privacy_policy' => 'Política de privacidad',
    'creating' => 'Creando…',
    'already_a_member' => '¿Ya es miembro?',
    'sign_in_instead' => 'Iniciar sesión',

    // OTP verification
    'enter_code_emailed_to' => 'Introduzca el código que enviamos a',
    'verification_code' => 'Código de verificación',
    'verifying' => 'Verificando…',
    'verify_and_continue' => 'Verificar y continuar',
    'resend_code' => 'Reenviar código',
    'back_to_sign_in' => 'Volver a iniciar sesión',

    // JS-side messages (component's Alpine script)
    'invalid_credentials' => 'Credenciales no válidas',
    'registration_failed' => 'Error en el registro',
    'registration_disabled' => 'El registro de nuevas cuentas no está disponible actualmente. Inténtelo más tarde o contacte con soporte.',
    'session_expired' => 'Su sesión ha caducado por inactividad. Vuelva a iniciar sesión.',
    'invalid_or_expired_code' => 'Código no válido o caducado.',
    'email_verified_please_sign_in' => 'Correo verificado — inicie sesión.',
    'new_code_sent' => 'Se ha enviado un nuevo código a su correo.',
    'could_not_resend_code' => 'No se pudo reenviar el código.',

    // Controller JSON responses (App\Http\Controllers\Frontend\AuthController)
    'validation_failed' => 'Error de validación',
    'invalid_email_or_password' => 'Correo o contraseña no válidos.',
    'account_deactivated' => 'Su cuenta ha sido desactivada.',
    'email_verification_required' => 'Se requiere verificación de correo.',
    'login_successful' => 'Inicio de sesión correcto.',
    'registration_successful' => 'Registro correcto. Verifique su correo electrónico.',
    'logged_out_successfully' => 'Sesión cerrada correctamente.',
    'invalid_input' => 'Entrada no válida',
    'otp_verified_successfully' => 'Código verificado correctamente.',
    'otp_resent_successfully' => 'Código reenviado correctamente.',
    'too_many_login_attempts' => 'Demasiados intentos de inicio de sesión. Inténtelo de nuevo en :minutes minutos.',

    // Password reset pages (auth/passwords/email.blade.php + reset.blade.php)
    'breadcrumb_home' => 'Inicio',
    'breadcrumb_reset_password' => 'Restablecer contraseña',
    'breadcrumb_new_password' => 'Nueva contraseña',
    'reset_password_title' => 'Restablecer contraseña',
    'eyebrow_request_link' => '01 · Solicitar enlace',
    'eyebrow_set_new_password' => '02 · Establecer nueva contraseña',
    'reset_password_heading' => 'Restablecer contraseña',
    'new_password_heading' => 'Nueva contraseña',
    'request_link_subtitle' => 'Introduzca su correo · le enviaremos un enlace seguro',
    'new_password_subtitle' => 'Elija una contraseña segura · mín. :min caracteres',
    'email_verification_eyebrow' => 'Verificación de correo',
    'credentials_reset_eyebrow' => 'Restablecimiento de credenciales',
    'send_reset_link' => 'Enviar enlace',
    'or_divider' => 'o',
    'back_to_homepage' => 'Volver al inicio',
    'expires_minutes' => 'CADUCA · :minutes MIN',
    'token_single_use' => 'Token · Un solo uso',
];
