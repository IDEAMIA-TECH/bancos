<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's bank connections
$stmt = $pdo->prepare("
    SELECT bc.*, b.name as bank_name, b.logo_url 
    FROM bank_connections bc 
    JOIN banks b ON bc.bank_id = b.id 
    WHERE bc.user_id = ? 
    ORDER BY bc.created_at DESC
");
$stmt->execute([$user_id]);
$connections = $stmt->fetchAll();

// Get available banks
$stmt = $pdo->prepare("SELECT * FROM banks WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$banks = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AI Debt Manager</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="info-box">
            <h2>Welcome to AI Debt Manager</h2>
            <p>Connect your bank accounts to start managing your finances with AI.</p>
        </div>

        <section class="connections-list">
            <h2>Your Bank Connections</h2>
            <?php if (empty($connections)): ?>
                <p>You haven't connected any bank accounts yet.</p>
            <?php else: ?>
                <?php foreach ($connections as $connection): ?>
                    <div class="connection-card">
                        <div class="connection-info">
                            <h3><?php echo htmlspecialchars($connection['bank_name']); ?></h3>
                            <p>Last synced: <?php echo date('M j, Y g:i A', strtotime($connection['last_sync'])); ?></p>
                            <p>Status: <?php echo $connection['status']; ?></p>
                        </div>
                        <div class="connection-actions">
                            <button class="btn-secondary" onclick="syncConnection('<?php echo $connection['id']; ?>')">
                                Sync Now
                            </button>
                            <button class="btn-danger" onclick="deleteConnection('<?php echo $connection['id']; ?>')">
                                Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="connect-new">
            <h2>Connect a New Bank</h2>
            <div class="institutions-grid">
                <?php foreach ($banks as $bank): ?>
                    <div class="institution-card" onclick="connectBank('<?php echo $bank['id']; ?>')">
                        <img src="<?php echo htmlspecialchars($bank['logo_url']); ?>" 
                             alt="<?php echo htmlspecialchars($bank['name']); ?> logo">
                        <h3><?php echo htmlspecialchars($bank['name']); ?></h3>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <script>
        function syncConnection(connectionId) {
            fetch('/api/belvo/sync.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ connection_id: connectionId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Account synced successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while syncing the account.');
            });
        }

        function deleteConnection(connectionId) {
            if (!confirm('Are you sure you want to delete this connection?')) {
                return;
            }

            fetch('/api/belvo/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ connection_id: connectionId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Connection deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the connection.');
            });
        }

        function connectBank(bankId) {
            window.location.href = `connect.php?bank_id=${bankId}`;
        }
    </script>
</body>
</html> 