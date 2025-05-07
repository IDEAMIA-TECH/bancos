<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();
requireAdmin();

// Get connections with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total connections count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bank_connections");
$stmt->execute();
$total_connections = $stmt->fetchColumn();
$total_pages = ceil($total_connections / $per_page);

// Get connections for current page
$stmt = $pdo->prepare("
    SELECT bc.*, 
           u.email,
           u.full_name,
           (SELECT COUNT(*) FROM accounts a WHERE a.bank_connection_id = bc.id) as account_count,
           (SELECT COUNT(*) FROM transactions t 
            JOIN accounts a ON t.account_id = a.id 
            WHERE a.bank_connection_id = bc.id) as transaction_count
    FROM bank_connections bc
    JOIN users u ON bc.user_id = u.id
    ORDER BY bc.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$per_page, $offset]);
$connections = $stmt->fetchAll();

// Handle connection status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['connection_id'])) {
    $connection_id = $_POST['connection_id'];
    $action = $_POST['action'];
    
    if ($action === 'activate' || $action === 'revoke') {
        $new_status = $action === 'activate' ? 'active' : 'revoked';
        $stmt = $pdo->prepare("UPDATE bank_connections SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $connection_id]);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            'connection_status_update',
            json_encode(['connection_id' => $connection_id, 'new_status' => $new_status]),
            $_SERVER['REMOTE_ADDR']
        ]);
        
        header("Location: connections.php?page=$page&success=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Conexiones - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="admin-header">
            <h1>Gestión de Conexiones</h1>
            <p>Administra las conexiones bancarias del sistema</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                La operación se realizó correctamente.
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="card-header">
                <h3>Lista de Conexiones</h3>
                <div class="header-actions">
                    <input type="text" id="searchInput" placeholder="Buscar conexiones..." class="search-input">
                    <select id="statusFilter" class="status-filter">
                        <option value="">Todos los estados</option>
                        <option value="active">Activas</option>
                        <option value="expired">Expiradas</option>
                        <option value="revoked">Revocadas</option>
                    </select>
                </div>
            </div>

            <div class="connections-table">
                <table>
                    <thead>
                        <tr>
                            <th>Institución</th>
                            <th>Usuario</th>
                            <th>Cuentas</th>
                            <th>Transacciones</th>
                            <th>Estado</th>
                            <th>Última Actualización</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($connections as $connection): ?>
                            <tr>
                                <td>
                                    <div class="connection-info">
                                        <h4><?php echo htmlspecialchars($connection['institution_id']); ?></h4>
                                        <p class="date">Creada: <?php echo date('d/m/Y', strtotime($connection['created_at'])); ?></p>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <p class="name"><?php echo htmlspecialchars($connection['full_name']); ?></p>
                                        <p class="email"><?php echo htmlspecialchars($connection['email']); ?></p>
                                    </div>
                                </td>
                                <td><?php echo $connection['account_count']; ?></td>
                                <td><?php echo $connection['transaction_count']; ?></td>
                                <td>
                                    <span class="connection-status <?php echo $connection['status']; ?>">
                                        <?php echo ucfirst($connection['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($connection['last_sync'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($connection['status'] === 'active'): ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="connection_id" value="<?php echo $connection['id']; ?>">
                                                <input type="hidden" name="action" value="revoke">
                                                <button type="submit" class="btn btn-warning">Revocar</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="connection_id" value="<?php echo $connection['id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-success">Activar</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="connection_details.php?id=<?php echo $connection['id']; ?>" class="btn btn-primary">Detalles</a>
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
                
                const statusCell = row.querySelector('.connection-status');
                row.style.display = statusCell.classList.contains(status) ? '' : 'none';
            });
        });
    </script>
</body>
</html> 