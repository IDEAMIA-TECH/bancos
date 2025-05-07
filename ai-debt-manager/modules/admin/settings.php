<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();
requireAdmin();

$section = $_GET['section'] ?? 'general';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    $error = null;

    switch ($section) {
        case 'general':
            // Update general settings
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'app_name'
            ");
            $stmt->execute([$_POST['app_name']]);

            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'app_description'
            ");
            $stmt->execute([$_POST['app_description']]);

            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'maintenance_mode'
            ");
            $stmt->execute([isset($_POST['maintenance_mode']) ? '1' : '0']);

            $success = true;
            break;

        case 'security':
            // Update security settings
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'password_min_length'
            ");
            $stmt->execute([$_POST['password_min_length']]);

            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'require_2fa'
            ");
            $stmt->execute([isset($_POST['require_2fa']) ? '1' : '0']);

            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'session_timeout'
            ");
            $stmt->execute([$_POST['session_timeout']]);

            $success = true;
            break;

        case 'notifications':
            // Update notification settings
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'email_notifications'
            ");
            $stmt->execute([isset($_POST['email_notifications']) ? '1' : '0']);

            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'push_notifications'
            ");
            $stmt->execute([isset($_POST['push_notifications']) ? '1' : '0']);

            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'notification_frequency'
            ");
            $stmt->execute([$_POST['notification_frequency']]);

            $success = true;
            break;

        case 'ai':
            // Update AI settings
            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'ai_model'
            ");
            $stmt->execute([$_POST['ai_model']]);

            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'prediction_confidence'
            ");
            $stmt->execute([$_POST['prediction_confidence']]);

            $stmt = $pdo->prepare("
                UPDATE system_settings 
                SET value = ? 
                WHERE setting_key = 'auto_categorization'
            ");
            $stmt->execute([isset($_POST['auto_categorization']) ? '1' : '0']);

            $success = true;
            break;
    }

    if ($success) {
        header("Location: settings.php?section=$section&success=1");
        exit;
    }
}

// Get current settings
$stmt = $pdo->prepare("SELECT setting_key, value FROM system_settings");
$stmt->execute();
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="admin-header">
            <h1>Configuración del Sistema</h1>
            <p>Gestiona la configuración general del sistema</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                La configuración se actualizó correctamente.
            </div>
        <?php endif; ?>

        <div class="settings-container">
            <div class="settings-sidebar">
                <a href="?section=general" class="settings-nav-item <?php echo $section === 'general' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    Configuración General
                </a>
                <a href="?section=security" class="settings-nav-item <?php echo $section === 'security' ? 'active' : ''; ?>">
                    <i class="fas fa-shield-alt"></i>
                    Seguridad
                </a>
                <a href="?section=notifications" class="settings-nav-item <?php echo $section === 'notifications' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    Notificaciones
                </a>
                <a href="?section=ai" class="settings-nav-item <?php echo $section === 'ai' ? 'active' : ''; ?>">
                    <i class="fas fa-robot"></i>
                    Configuración IA
                </a>
            </div>

            <div class="settings-content">
                <div class="admin-card">
                    <?php if ($section === 'general'): ?>
                        <form method="POST" class="settings-form">
                            <div class="form-group">
                                <label for="app_name">Nombre de la Aplicación</label>
                                <input type="text" id="app_name" name="app_name" value="<?php echo htmlspecialchars($settings['app_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="app_description">Descripción</label>
                                <textarea id="app_description" name="app_description" rows="3"><?php echo htmlspecialchars($settings['app_description'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="maintenance_mode" <?php echo ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    Modo Mantenimiento
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </form>

                    <?php elseif ($section === 'security'): ?>
                        <form method="POST" class="settings-form">
                            <div class="form-group">
                                <label for="password_min_length">Longitud Mínima de Contraseña</label>
                                <input type="number" id="password_min_length" name="password_min_length" value="<?php echo htmlspecialchars($settings['password_min_length'] ?? '8'); ?>" min="6" max="32" required>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="require_2fa" <?php echo ($settings['require_2fa'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    Requerir Autenticación de Dos Factores
                                </label>
                            </div>

                            <div class="form-group">
                                <label for="session_timeout">Tiempo de Sesión (minutos)</label>
                                <input type="number" id="session_timeout" name="session_timeout" value="<?php echo htmlspecialchars($settings['session_timeout'] ?? '30'); ?>" min="5" max="1440" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </form>

                    <?php elseif ($section === 'notifications'): ?>
                        <form method="POST" class="settings-form">
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="email_notifications" <?php echo ($settings['email_notifications'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    Habilitar Notificaciones por Email
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="push_notifications" <?php echo ($settings['push_notifications'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    Habilitar Notificaciones Push
                                </label>
                            </div>

                            <div class="form-group">
                                <label for="notification_frequency">Frecuencia de Notificaciones</label>
                                <select id="notification_frequency" name="notification_frequency">
                                    <option value="realtime" <?php echo ($settings['notification_frequency'] ?? '') === 'realtime' ? 'selected' : ''; ?>>Tiempo Real</option>
                                    <option value="daily" <?php echo ($settings['notification_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Diario</option>
                                    <option value="weekly" <?php echo ($settings['notification_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>Semanal</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </form>

                    <?php elseif ($section === 'ai'): ?>
                        <form method="POST" class="settings-form">
                            <div class="form-group">
                                <label for="ai_model">Modelo de IA</label>
                                <select id="ai_model" name="ai_model">
                                    <option value="gpt-4" <?php echo ($settings['ai_model'] ?? '') === 'gpt-4' ? 'selected' : ''; ?>>GPT-4</option>
                                    <option value="gpt-3.5" <?php echo ($settings['ai_model'] ?? '') === 'gpt-3.5' ? 'selected' : ''; ?>>GPT-3.5</option>
                                    <option value="claude" <?php echo ($settings['ai_model'] ?? '') === 'claude' ? 'selected' : ''; ?>>Claude</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="prediction_confidence">Umbral de Confianza (%)</label>
                                <input type="number" id="prediction_confidence" name="prediction_confidence" value="<?php echo htmlspecialchars($settings['prediction_confidence'] ?? '80'); ?>" min="0" max="100" required>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="auto_categorization" <?php echo ($settings['auto_categorization'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    Habilitar Categorización Automática
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 