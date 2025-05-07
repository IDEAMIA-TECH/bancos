<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_module = basename(dirname($_SERVER['PHP_SELF']));

// Get user's name for display
$user_name = $_SESSION['user_first_name'] ?? 'Usuario';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="<?php echo APP_URL; ?>"><?php echo APP_NAME; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_module === 'dashboard' ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>/dashboard">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_module === 'debts' ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>/debts/list">
                            <i class="fas fa-list"></i> Mis Deudas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_module === 'analysis' ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>/analysis">
                            <i class="fas fa-chart-pie"></i> Análisis
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_module === 'liquidity' ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>/liquidity">
                            <i class="fas fa-money-bill-wave"></i> Liquidez
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_module === 'bank' ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>/bank/connect">
                            <i class="fas fa-university"></i> Conexión Bancaria
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo $current_module === 'transactions' ? 'active' : ''; ?>" 
                           href="#" role="button" data-bs-toggle="dropdown">
                            Categorización
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/transactions/categorize">
                                    Categorización
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo $current_module === 'admin' ? 'active' : ''; ?>" 
                               href="#" role="button" data-bs-toggle="dropdown">
                                Administración
                            </a>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin">
                                        Panel de Control
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/users">
                                        Usuarios
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/connections">
                                        Conexiones
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/alerts">
                                        Alertas
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/admin/settings">
                                        Configuración
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/profile">
                                    <i class="fas fa-user-cog"></i> Perfil
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/settings">
                                    <i class="fas fa-cog"></i> Configuración
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo APP_URL; ?>/logout">
                                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'login' ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>/login">
                            Iniciar Sesión
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page === 'register' ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>/register">
                            Registrarse
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav> 