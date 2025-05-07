<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_module = basename(dirname($_SERVER['PHP_SELF']));
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
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo $current_module === 'debts' ? 'active' : ''; ?>" 
                           href="#" role="button" data-bs-toggle="dropdown">
                            Deudas
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/debts/list">
                                    Lista de Deudas
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/debts/consolidate">
                                    Consolidación
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/debts/strategy">
                                    Estrategia de Pago
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo $current_module === 'analysis' ? 'active' : ''; ?>" 
                           href="#" role="button" data-bs-toggle="dropdown">
                            Análisis
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/analysis">
                                    Análisis Financiero
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/liquidity">
                                    Predicción de Liquidez
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_module === 'transactions' ? 'active' : ''; ?>" 
                           href="<?php echo APP_URL; ?>/transactions/categorize">
                            Categorización
                        </a>
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
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/profile">
                                    Mi Perfil
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/logout">
                                    Cerrar Sesión
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