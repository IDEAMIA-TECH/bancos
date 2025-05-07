<?php
session_start();
require_once 'config/config.php';

// Basic routing
$request = $_SERVER['REQUEST_URI'];
$basePath = '/deudas';

// Remove base path from request
$request = str_replace($basePath, '', $request);

// Simple router
switch ($request) {
    case '':
    case '/':
        require __DIR__ . '/modules/auth/login.php';
        break;
    case '/register':
        require __DIR__ . '/modules/auth/register.php';
        break;
    case '/dashboard':
        require __DIR__ . '/modules/dashboard/index.php';
        break;
    default:
        http_response_code(404);
        require __DIR__ . '/includes/404.php';
        break;
} 