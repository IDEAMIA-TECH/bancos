<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Validate bank_id parameter
if (!isset($_GET['bank_id'])) {
    header('Location: dashboard.php');
    exit();
}

$bank_id = $_GET['bank_id'];

// Get bank information
$stmt = $pdo->prepare("SELECT * FROM banks WHERE id = ? AND is_active = 1");
$stmt->execute([$bank_id]);
$bank = $stmt->fetch();

if (!$bank) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect Bank - AI Debt Manager</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="info-box">
            <h2>Connect to <?php echo htmlspecialchars($bank['name']); ?></h2>
            <p>You'll be redirected to your bank's secure login page to authorize the connection.</p>
        </div>

        <div class="connection-container">
            <div class="bank-info">
                <img src="<?php echo htmlspecialchars($bank['logo_url']); ?>" 
                     alt="<?php echo htmlspecialchars($bank['name']); ?> logo"
                     class="bank-logo">
                <h3><?php echo htmlspecialchars($bank['name']); ?></h3>
            </div>

            <div class="connection-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Secure Login</h4>
                        <p>You'll be redirected to your bank's secure login page.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Authorize Access</h4>
                        <p>Grant permission to access your account information.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Complete Connection</h4>
                        <p>Your accounts will be automatically synced.</p>
                    </div>
                </div>
            </div>

            <div class="connection-actions">
                <button onclick="startConnection()" class="btn-primary">Start Connection</button>
                <a href="dashboard.php" class="btn-secondary">Cancel</a>
            </div>
        </div>
    </div>

    <script src="assets/js/belvo.js"></script>
    <script>
        function startConnection() {
            belvoSDK.createWidget({
                institution: '<?php echo $bank['belvo_institution_id']; ?>',
                callback: function(link) {
                    // Save the connection
                    fetch('/api/belvo/connect.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            institution_id: '<?php echo $bank['belvo_institution_id']; ?>',
                            belvo_link_id: link
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'dashboard.php';
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while connecting to the bank.');
                    });
                }
            });
        }
    </script>
</body>
</html> 