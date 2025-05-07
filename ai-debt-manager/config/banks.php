<?php
// Configuración de bancos soportados
$SUPPORTED_BANKS = [
    'banamex' => [
        'name' => 'Citibanamex',
        'login_url' => 'https://www.banamex.com/es/personas/',
        'type' => 'selenium', // Tipo de scraping requerido
        'fields' => [
            'username' => [
                'type' => 'text',
                'label' => 'Número de Tarjeta o Usuario',
                'placeholder' => 'Ingresa tu número de tarjeta o usuario'
            ],
            'password' => [
                'type' => 'password',
                'label' => 'Contraseña',
                'placeholder' => 'Ingresa tu contraseña'
            ]
        ]
    ],
    'banorte' => [
        'name' => 'Banorte',
        'login_url' => 'https://www.banorte.com/wps/portal/banorte/Home',
        'type' => 'selenium',
        'fields' => [
            'username' => [
                'type' => 'text',
                'label' => 'Usuario',
                'placeholder' => 'Ingresa tu usuario'
            ],
            'password' => [
                'type' => 'password',
                'label' => 'Contraseña',
                'placeholder' => 'Ingresa tu contraseña'
            ]
        ]
    ],
    'santander' => [
        'name' => 'Santander',
        'login_url' => 'https://www.santander.com.mx/',
        'type' => 'selenium',
        'fields' => [
            'username' => [
                'type' => 'text',
                'label' => 'Usuario',
                'placeholder' => 'Ingresa tu usuario'
            ],
            'password' => [
                'type' => 'password',
                'label' => 'Contraseña',
                'placeholder' => 'Ingresa tu contraseña'
            ]
        ]
    ]
];

// Configuración de seguridad
define('ENCRYPTION_KEY', 'tu_clave_de_encriptacion_secreta'); // Cambiar en producción
define('ENCRYPTION_METHOD', 'aes-256-cbc');

// Configuración de Selenium
define('SELENIUM_HOST', 'localhost');
define('SELENIUM_PORT', '4444');
define('SELENIUM_BROWSER', 'chrome');

// Configuración de almacenamiento
define('BANK_DATA_RETENTION_DAYS', 90); // Días que se mantienen los datos bancarios
define('MAX_LOGIN_ATTEMPTS', 3); // Intentos máximos de inicio de sesión
define('LOGIN_TIMEOUT', 300); // Tiempo máximo de espera para inicio de sesión (segundos)

// Función para encriptar credenciales bancarias
function encryptBankCredentials($data) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt(
        json_encode($data),
        ENCRYPTION_METHOD,
        ENCRYPTION_KEY,
        0,
        $iv
    );
    return base64_encode($iv . $encrypted);
}

// Función para desencriptar credenciales bancarias
function decryptBankCredentials($encryptedData) {
    $data = base64_decode($encryptedData);
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    $decrypted = openssl_decrypt(
        $encrypted,
        ENCRYPTION_METHOD,
        ENCRYPTION_KEY,
        0,
        $iv
    );
    return json_decode($decrypted, true);
} 