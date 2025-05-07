<?php
/**
 * Funciones de autenticación
 */

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    // Mínimo 8 caracteres, al menos una letra y un número
    return strlen($password) >= 8 && 
           preg_match('/[A-Za-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/');
        exit;
    }
}

function logout() {
    session_destroy();
    header('Location: ' . APP_URL . '/');
    exit;
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function getUserData($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, email, full_name, status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
} 