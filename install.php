<?php
/**
 * KaamConnect - Automated Database Installer & Verification Utility
 * Can be run via browser: http://localhost/install.php
 * or included to ensure seamless initial setup.
 */

declare(strict_types=1);

define('APP_ROOT', __DIR__);
require_once __DIR__ . '/config/database.php';

$message = '';
$status = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_install'])) {
    try {
        // Connect to MySQL server without database name first
        $dsnWithoutDb = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        $rootPdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, $options);
        
        // Read SQL schema file
        $sqlFile = __DIR__ . '/database/kaamconnect.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL schema file not found at: {$sqlFile}");
        }

        $sqlContent = file_get_contents($sqlFile);
        
        // Execute batch SQL
        $rootPdo->exec($sqlContent);

        $message = "Database 'kaamconnect_db' installed and seeded successfully! You can now log in.";
        $status = 'success';
    } catch (PDOException $e) {
        $message = "PDO Error during installation: " . $e->getMessage();
        $status = 'danger';
    } catch (Exception $e) {
        $message = "Installation Error: " . $e->getMessage();
        $status = 'danger';
    }
}

// Check current database status
$dbExists = false;
$tableCount = 0;
try {
    $checkPdo = getDBConnection();
    $tables = $checkPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $dbExists = true;
    $tableCount = count($tables);
} catch (Exception $e) {
    $dbExists = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Installer | KaamConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light py-5">
    <div class="container" style="max-width: 680px;">
        <div class="text-center mb-4">
            <h1 class="fw-bold text-primary"><i class="fas fa-handshake text-warning me-2"></i>KaamConnect</h1>
            <p class="text-muted">Automated Database Setup & Diagnostics Utility</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $status ?> shadow-sm mb-4">
                <i class="fas fa-<?= ($status === 'success') ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-white py-3 fw-bold border-bottom">
                <i class="fas fa-database text-primary me-2"></i>Database Environment Diagnostics
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush mb-4">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Database Host & Port</span>
                        <code class="fw-bold"><?= htmlspecialchars(DB_HOST . ':' . DB_PORT) ?></code>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Database User</span>
                        <code class="fw-bold"><?= htmlspecialchars(DB_USER) ?></code>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Target Database Name</span>
                        <code class="fw-bold"><?= htmlspecialchars(DB_NAME) ?></code>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>Database Status</span>
                        <?php if ($dbExists): ?>
                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Connected (<?= $tableCount ?> tables found)</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle me-1"></i>Not Installed Yet</span>
                        <?php endif; ?>
                    </li>
                </ul>

                <form method="POST">
                    <input type="hidden" name="run_install" value="1">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold" onclick="return confirm('Execute database initialization? This will create/reset kaamconnect_db tables and seed default accounts.');">
                            <i class="fas fa-play-circle me-2"></i><?= $dbExists ? 'Re-install / Reset Database Schema' : 'Install Database & Seed Data' ?>
                        </button>
                        <a href="/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>Go to KaamConnect Homepage
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white py-3 fw-bold border-bottom">
                <i class="fas fa-key text-warning me-2"></i>Default Seed Credentials
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Role</th>
                                <th>Email / Login</th>
                                <th>Phone</th>
                                <th>Default Password</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-danger">Admin</span></td>
                                <td><code>admin@kaamconnect.com</code></td>
                                <td>9876543210</td>
                                <td><code>Admin@123</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">Worker</span></td>
                                <td><code>ramesh.worker@kaamconnect.com</code></td>
                                <td>9876500001</td>
                                <td><code>Password@123</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">Customer</span></td>
                                <td><code>sunita.customer@kaamconnect.com</code></td>
                                <td>9876500003</td>
                                <td><code>Password@123</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
