<?php

return [
    'home' => 'Inicio',
    'homepage' => 'Página de inicio',
    'return_back' => 'Volver',
    'status' => 'Estado',
    'what_occurred' => 'Qué ocurrió',

    '401' => [
        'breadcrumb' => 'No autorizado',
        'heading' => 'Autenticación requerida',
        'intro' => 'El acceso a este registro de catálogo requiere tokens de autenticación válidos. Inicie sesión para verificar su identidad.',
        'glyph_label' => 'No autenticado',
        'prerequisite_label' => 'Requisito previo',
        'prerequisite_value' => 'Sesión de usuario',
        'identity_key_label' => 'Clave de identidad',
        'identity_key_value' => 'Invitado',
        'explanation' => 'El directorio solicitado está protegido. El acceso está limitado a cuentas comerciales autenticadas o compradores B2C registrados. Abra el panel de inicio de sesión a continuación para establecer una sesión segura.',
        'open_login' => 'Abrir inicio de sesión',
    ],

    '403' => [
        'breadcrumb' => 'Acceso limitado',
        'heading' => 'Acceso prohibido',
        'intro' => 'Sus parámetros de solicitud o encabezados de autorización no otorgan acceso de lectura/escritura a este registro restringido.',
        'glyph_label' => 'Acceso denegado',
        'prerequisite_label' => 'Requisito previo',
        'prerequisite_value' => 'Clave de autorización',
        'explanation' => 'El sistema de directorio detectó tráfico no autorizado. Esta ruta está restringida a operadores con claves de verificación superiores o permisos administrativos válidos. Inicie sesión con credenciales válidas o contacte con soporte.',
    ],

    '404' => [
        'breadcrumb' => 'No encontrado',
        'heading' => 'Documento no encontrado',
        'intro' => 'La ruta solicitada o el número de pieza OEM no existe en nuestro registro de catálogo. Verifique los datos introducidos e inténtelo de nuevo.',
        'glyph_label' => 'Recurso no encontrado',
        'resolution_label' => 'Resolución',
        'resolution_value' => 'Verificar consulta',
        'explanation' => 'La ruta URL introducida no corresponde a ningún endpoint activo, o el ID de la pieza OEM referenciada ha sido eliminado de nuestro directorio activo. Vuelva a la consola de búsqueda para enviar una nueva consulta.',
        'search_console' => 'Consola de búsqueda',
    ],

    '419' => [
        'breadcrumb' => 'Sesión caducada',
        'heading' => 'Verificación de sesión caducada',
        'intro' => 'Su clave de validación de seguridad caducó debido a un período de inactividad. Es necesario recargar la página.',
        'glyph_label' => 'Página caducada',
        'handshake_label' => 'Verificación',
        'handshake_value' => 'Token CSRF',
        'action_label' => 'Acción',
        'action_value' => 'Recargar página',
        'explanation' => 'Por seguridad, todos los formularios envían tokens de verificación basados en sesión (claves CSRF). Como su conexión permaneció inactiva, la clave de sesión caducó. Recargue el documento para solicitar un nuevo token criptográfico.',
        'reload_page' => 'Recargar página',
    ],

    '429' => [
        'breadcrumb' => 'Límite de solicitudes',
        'glyph_label' => 'Demasiadas solicitudes',
        'retry_after' => 'Reintentar después de',
        'what_happened' => 'Qué ocurrió',
        'explanation' => 'Nuestros sistemas recibieron demasiadas solicitudes desde su dirección en poco tiempo. Esta es una salvaguarda automática — reduzca la velocidad e inténtelo de nuevo en un momento.',
    ],

    '500' => [
        'breadcrumb' => 'Error del sistema',
        'heading' => 'Anomalía interna del servidor',
        'intro' => 'El compilador de la base de datos o la matriz de cálculo encontró una excepción no controlada al procesar su solicitud.',
        'glyph_label' => 'Anomalía del sistema',
        'reporting_label' => 'Notificación',
        'reporting_value' => 'Automática',
        'explanation' => 'El servidor no pudo calibrar la matriz de salida para su solicitud debido a una excepción del sistema no controlada. Este error se ha registrado automáticamente. Nuestro equipo de catálogo está corrigiendo los índices.',
        'return_home' => 'Volver al inicio',
        'support_desk' => 'Soporte',
    ],
];
