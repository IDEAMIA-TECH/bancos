<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();
requireAdmin();

// Get alerts with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total alerts count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE type = 'system'");
$stmt->execute();
$total_alerts = $stmt->fetchColumn();
$total_pages = ceil($total_alerts / $per_page);

// Get alerts for current page
$stmt = $pdo->prepare("
    SELECT n.*, u.email, u.full_name
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type = 'system'
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$per_page, $offset]);
$alerts = $stmt->fetchAll();

// Handle alert status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['alert_id'])) {
    $alert_id = $_POST['alert_id'];
    $action = $_POST['action'];
    
    if ($action === 'resolve') {
        $stmt = $pdo->prepare("UPDATE notifications SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$alert_id]);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            'alert_resolve',
            json_encode(['alert_id' => $alert_id]),
            $_SERVER['REMOTE_ADDR']
        ]);
        
        header("Location: alerts.php?page=$page&success=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas del Sistema - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="admin-header">
            <h1>Alertas del Sistema</h1>
            <p>Gestiona las alertas y notificaciones del sistema</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                La operación se realizó correctamente.
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="card-header">
                <h3>Lista de Alertas</h3>
                <div class="header-actions">
                    <input type="text" id="searchInput" placeholder="Buscar alertas..." class="search-input">
                    <select id="statusFilter" class="status-filter">
                        <option value="">Todos los estados</option>
                        <option value="pending">Pendientes</option>
                        <option value="resolved">Resueltas</option>
                    </select>
                </div>
            </div>

            <div class="alerts-table">
                <table>
                    <thead>
                        <tr>
                            <th>Mensaje</th>
                            <th>Usuario</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert): ?>
                            <tr>
                                <td>
                                    <div class="alert-info">
                                        <h4><?php echo htmlspecialchars($alert['message']); ?></h4>
                                        <p class="details"><?php echo htmlspecialchars($alert['details'] ?? ''); ?></p>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <p class="name"><?php echo htmlspecialchars($alert['full_name']); ?></p>
                                        <p class="email"><?php echo htmlspecialchars($alert['email']); ?></p>
                                    </div>
                                </td>
                                <td>
                                    <span class="alert-type <?php echo $alert['severity']; ?>">
                                        <?php echo ucfirst($alert['severity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="alert-status <?php echo $alert['status']; ?>">
                                        <?php echo ucfirst($alert['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($alert['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($alert['status'] === 'pending'): ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                                <input type="hidden" name="action" value="resolve">
                                                <button type="submit" class="btn btn-success">Resolver</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="alert_details.php?id=<?php echo $alert['id']; ?>" class="btn btn-primary">Detalles</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="btn btn-secondary">Anterior</a>
                    <?php endif; ?>
                    
                    <span class="page-info">
                        Página <?php echo $page; ?> de <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="btn btn-secondary">Siguiente</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function(e) {
            const status = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                if (!status) {
                    row.style.display = '';
                    return;
                }
                
                const statusCell = row.querySelector('.alert-status');
                row.style.display = statusCell.classList.contains(status) ? '' : 'none';
            });
        });
    </script>
</body>
</html> 