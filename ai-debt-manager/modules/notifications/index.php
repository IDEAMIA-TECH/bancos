<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

// Mark notifications as read
if (!empty($notifications)) {
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE user_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$_SESSION['user_id']]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/notifications.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="notifications-header">
            <h1>Notificaciones</h1>
            <p>Mantente al día con tus finanzas</p>
        </div>

        <div class="notifications-list">
            <?php if (empty($notifications)): ?>
                <div class="no-notifications">
                    <p>No tienes notificaciones pendientes</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                        <div class="notification-icon">
                            <?php
                            switch ($notification['type']) {
                                case 'payment_due':
                                    echo '💰';
                                    break;
                                case 'low_balance':
                                    echo '⚠️';
                                    break;
                                case 'plan_update':
                                    echo '📊';
                                    break;
                                default:
                                    echo '📢';
                            }
                            ?>
                        </div>
                        <div class="notification-content">
                            <p class="message"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <span class="time"><?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 