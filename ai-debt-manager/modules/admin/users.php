<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();
requireAdmin();

// Get users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total users count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
$stmt->execute();
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// Get users for current page
$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM bank_connections bc WHERE bc.user_id = u.id) as connection_count,
           (SELECT COUNT(*) FROM debts d WHERE d.user_id = u.id) as debt_count,
           (SELECT COUNT(*) FROM transactions t 
            JOIN accounts a ON t.account_id = a.id 
            JOIN bank_connections bc ON a.bank_connection_id = bc.id 
            WHERE bc.user_id = u.id) as transaction_count
    FROM users u
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$per_page, $offset]);
$users = $stmt->fetchAll();

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    
    if ($action === 'activate' || $action === 'suspend') {
        $new_status = $action === 'activate' ? 'active' : 'suspended';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            'user_status_update',
            json_encode(['target_user_id' => $user_id, 'new_status' => $new_status]),
            $_SERVER['REMOTE_ADDR']
        ]);
        
        header("Location: users.php?page=$page&success=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="admin-header">
            <h1>Gestión de Usuarios</h1>
            <p>Administra los usuarios del sistema</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                La operación se realizó correctamente.
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="card-header">
                <h3>Lista de Usuarios</h3>
                <div class="header-actions">
                    <input type="text" id="searchInput" placeholder="Buscar usuarios..." class="search-input">
                    <select id="statusFilter" class="status-filter">
                        <option value="">Todos los estados</option>
                        <option value="active">Activos</option>
                        <option value="inactive">Inactivos</option>
                        <option value="suspended">Suspendidos</option>
                    </select>
                </div>
            </div>

            <div class="users-table">
                <table>
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Conexiones</th>
                            <th>Deudas</th>
                            <th>Transacciones</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                        <p class="date">Registro: <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo $user['connection_count']; ?></td>
                                <td><?php echo $user['debt_count']; ?></td>
                                <td><?php echo $user['transaction_count']; ?></td>
                                <td>
                                    <span class="user-status <?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['status'] === 'active'): ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="suspend">
                                                <button type="submit" class="btn btn-warning">Suspender</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="action-form">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-success">Activar</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">Detalles</a>
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
                
                const statusCell = row.querySelector('.user-status');
                row.style.display = statusCell.classList.contains(status) ? '' : 'none';
            });
        });
    </script>
</body>
</html> 