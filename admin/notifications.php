<?php
declare(strict_types=1);

/**
 * KaamConnect - Admin Notifications & Audit Alerts
 */

$pageTitle = 'Notifications - KaamConnect Admin';

// Load authentication without displaying HTML
require_once __DIR__ . '/includes/auth.php';

// Mark all notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {

    $pdo = getDBConnection();

    $stmt = $pdo->prepare(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ?"
    );

    $stmt->execute([(int)$adminUser['id']]);

    redirect('/admin/notifications.php');
}

// Fetch notifications
$sql = "SELECT n.*, u.full_name AS recipient_name, u.role AS recipient_role
        FROM notifications n
        JOIN users u ON n.user_id = u.id
        ORDER BY n.created_at DESC
        LIMIT 50";

$notifications = dbFetchAll($sql);

// Load HTML header after processing POST
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1 text-dark">
            <i class="fas fa-bell text-primary me-2"></i>Platform Notifications Feed
        </h2>
        <p class="text-muted small mb-0">
            Real-time feed of automated verification alerts, reset requests, and system activity.
        </p>
    </div>

    <form method="POST" action="">
        <button type="submit" name="mark_all_read" value="1"
                class="btn btn-outline-secondary btn-sm rounded-pill fw-semibold">
            <i class="fas fa-check-double me-1"></i>Mark All Read
        </button>
    </form>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="list-group list-group-flush">

        <?php if (empty($notifications)): ?>

            <div class="text-center py-5 text-muted">
                <i class="fas fa-bell-slash fa-3x mb-3 text-secondary opacity-50"></i>
                <p class="mb-0 fw-semibold">No notifications recorded yet.</p>
            </div>

        <?php else: ?>

            <?php foreach ($notifications as $notif): ?>

                <div class="list-group-item p-3 p-md-4 <?= empty($notif['is_read']) ? 'bg-primary-subtle bg-opacity-25' : '' ?>">

                    <div class="d-flex align-items-start gap-3">

                        <div class="p-2 bg-light border rounded-circle text-primary fs-5 mt-1"
                             style="width: 42px; height: 42px; display: flex; align-items: center; justify-content: center;">

                            <i class="<?= match ($notif['type']) {
                                'verification_approved' => 'fas fa-check-circle text-success',
                                'verification_rejected' => 'fas fa-circle-xmark text-danger',
                                'verification_resubmission' => 'fas fa-rotate-left text-warning',
                                'verification_submitted' => 'fas fa-id-card text-info',
                                default => 'fas fa-bell text-primary'
                            } ?>"></i>

                        </div>

                        <div class="flex-grow-1">

                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <?= e($notif['title']) ?>
                                </h6>

                                <span class="small text-muted">
                                    <?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?>
                                </span>
                            </div>

                            <p class="text-secondary small mb-2">
                                <?= nl2br(e($notif['message'])) ?>
                            </p>

                            <div class="d-flex align-items-center gap-2 small">

                                <span class="badge bg-light text-dark border">
                                    Recipient: <?= e($notif['recipient_name']) ?>
                                    (<?= ucfirst($notif['recipient_role']) ?>)
                                </span>

                                <?php if (!empty($notif['link'])): ?>
                                    <a href="<?= e(url($notif['link'])) ?>"
                                       class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold">
                                        View Action <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
