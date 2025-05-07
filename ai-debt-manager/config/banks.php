<?php
// Configuración de bancos soportados
$SUPPORTED_BANKS = [
    'banamex' => [
        'name' => 'Banamex',
        'fields' => [
            [
                'name' => 'username',
                'label' => 'Usuario',
                'type' => 'text',
                'placeholder' => 'Ingresa tu usuario',
                'required' => true
            ],
            [
                'name' => 'password',
                'label' => 'Contraseña',
                'type' => 'password',
                'placeholder' => 'Ingresa tu contraseña',
                'required' => true
            ]
        ]
    ],
    'banorte' => [
        'name' => 'Banorte',
        'fields' => [
            [
                'name' => 'username',
                'label' => 'Usuario',
                'type' => 'text',
                'placeholder' => 'Ingresa tu usuario',
                'required' => true
            ],
            [
                'name' => 'password',
                'label' => 'Contraseña',
                'type' => 'password',
                'placeholder' => 'Ingresa tu contraseña',
                'required' => true
            ]
        ]
    ],
    'santander' => [
        'name' => 'Santander',
        'fields' => [
            [
                'name' => 'username',
                'label' => 'Usuario',
                'type' => 'text',
                'placeholder' => 'Ingresa tu usuario',
                'required' => true
            ],
            [
                'name' => 'password',
                'label' => 'Contraseña',
                'type' => 'password',
                'placeholder' => 'Ingresa tu contraseña',
                'required' => true
            ]
        ]
    ]
];

// Configuración de encriptación
define('ENCRYPTION_KEY', 'your-encryption-key-here'); // Cambiar en producción
define('ENCRYPTION_METHOD', 'aes-256-cbc');

// Configuración de Selenium
define('SELENIUM_HOST', 'localhost');
define('SELENIUM_PORT', '4444');
define('SELENIUM_BROWSER', 'chrome');

// Configuración de retención de datos
define('BANK_DATA_RETENTION_DAYS', 90);
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOGIN_TIMEOUT', 300); // 5 minutos

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