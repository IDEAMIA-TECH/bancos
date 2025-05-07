<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar configuración
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/belvo.php';
require_once __DIR__ . '/includes/auth_functions.php';

// Obtener la URL solicitada
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/deudas/';
$path = str_replace($base_path, '', $request_uri);
$path = strtok($path, '?'); // Eliminar query string

// Si no hay ruta o es la raíz, redirigir a login o dashboard
if (empty($path) || $path === '/') {
    if (isLoggedIn()) {
        header('Location: ' . APP_URL . '/dashboard');
    } else {
        header('Location: ' . APP_URL . '/login');
    }
    exit;
}

// Mapeo de rutas a archivos
$routes = [
    'login' => 'modules/auth/login.php',
    'register' => 'modules/auth/register.php',
    'dashboard' => 'modules/dashboard/index.php',
    'logout' => 'modules/auth/logout.php',
    'profile' => 'modules/profile/index.php',
    'bank' => 'modules/bank/connect.php',
    'liquidity' => 'modules/liquidity/index.php',
    'debts' => 'modules/debts/index.php',
    'settings' => 'modules/settings/index.php'
];

// Verificar si la ruta existe
if (isset($routes[$path])) {
    $file = __DIR__ . '/' . $routes[$path];
    if (file_exists($file)) {
        require_once $file;
        exit;
    }
}

// Si la ruta no existe, mostrar error 404
header("HTTP/1.0 404 Not Found");
require_once __DIR__ . '/modules/errors/404.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 100px 0;
            margin-bottom: 50px;
        }
        .feature-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            font-size: 2.5rem;
            color: #0d6efd;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="flex-grow-1">
        <section class="hero-section text-center">
            <div class="container">
                <h1 class="display-4 mb-4">Gestiona tus deudas de manera inteligente</h1>
                <p class="lead mb-5">Utiliza nuestra plataforma para analizar, consolidar y optimizar el pago de tus deudas</p>
                <div class="d-flex justify-content-center gap-3">
                    <a href="<?php echo APP_URL; ?>/register" class="btn btn-light btn-lg">Comenzar Ahora</a>
                    <a href="<?php echo APP_URL; ?>/login" class="btn btn-outline-light btn-lg">Iniciar Sesión</a>
                </div>
            </div>
        </section>

        <section class="container mb-5">
            <h2 class="text-center mb-5">Características Principales</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100 p-4">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line feature-icon"></i>
                            <h3 class="h5 mb-3">Análisis Financiero</h3>
                            <p class="card-text">Visualiza tus gastos e ingresos con gráficos interactivos y obtén insights valiosos sobre tu situación financiera.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 p-4">
                        <div class="card-body text-center">
                            <i class="fas fa-piggy-bank feature-icon"></i>
                            <h3 class="h5 mb-3">Consolidación de Deudas</h3>
                            <p class="card-text">Agrupa tus deudas y optimiza tus pagos para reducir intereses y simplificar tu gestión financiera.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card h-100 p-4">
                        <div class="card-body text-center">
                            <i class="fas fa-robot feature-icon"></i>
                            <h3 class="h5 mb-3">IA Predictiva</h3>
                            <p class="card-text">Utiliza nuestra inteligencia artificial para predecir tu flujo de efectivo y planificar mejor tus pagos.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-light py-5">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h2 class="mb-4">¿Por qué elegirnos?</h2>
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-primary me-2"></i>
                                Análisis detallado de tu situación financiera
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-primary me-2"></i>
                                Estrategias personalizadas de pago de deudas
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-primary me-2"></i>
                                Predicciones de liquidez basadas en IA
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-primary me-2"></i>
                                Seguimiento en tiempo real de tus pagos
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <img src="<?php echo APP_URL; ?>/assets/images/financial-dashboard.png" alt="Dashboard Financiero" class="img-fluid rounded shadow">
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html> 