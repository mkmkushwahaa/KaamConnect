<?php
/**
 * KaamConnect - Worker Notifications
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireWorker();

$user = currentUser();
$userId = (int)$user['id'];

$pageTitle = 'Notifications - KaamConnect';

// Mark a notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notificationId = (int)$_POST['mark_read'];

    dbExecute(
        "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
        [$notificationId, $userId]
    );

    redirect('/worker/notifications.php');
}

// Mark all notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {

    dbExecute(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
        [$userId]
    );

    redirect('/worker/notifications.php');
}

// Fetch only this worker's notifications
$notifications = dbFetchAll(
    "SELECT * FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 100",
    [$userId]
);

// Load header only after processing POST requests
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1">
            <i class="fas fa-bell text-primary me-2"></i>Notifications
        </h2>
        <p class="text-muted small mb-0">
            Stay updated about your KaamConnect account and work.
        </p>
    </div>

    <form method="POST">
        <button type="submit" name="mark_all_read" value="1"
                class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="fas fa-check-double me-1"></i>Mark All Read
        </button>
    </form>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="list-group list-group-flush">

        <?php if (empty($notifications)): ?>

            <div class="text-center py-5 text-muted">
                <i class="fas fa-bell-slash fa-3x mb-3"></i>
                <p class="fw-semibold mb-0">No notifications yet.</p>
            </div>

        <?php else: ?>

            <?php foreach ($notifications as $notif): ?>

                <div class="list-group-item p-3 p-md-4
                    <?= empty($notif['is_read']) ? 'bg-primary-subtle bg-opacity-25' : '' ?>">

                    <div class="d-flex align-items-start gap-3">

                        <div class="text-primary fs-4 mt-1">
                            <i class="fas fa-bell"></i>
                        </div>

                        <div class="flex-grow-1">

                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <h6 class="fw-bold mb-1">
                                    <?= e($notif['title']) ?>
                                </h6>

                                <span class="small text-muted">
                                    <?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?>
                                </span>
                            </div>

                            <p class="text-secondary small mb-2">
                                <?= nl2br(e($notif['message'])) ?>
                            </p>

                            <div class="d-flex gap-3 align-items-center flex-wrap">

                                <?php if (!empty($notif['link'])): ?>
                                    <a href="<?= e(url($notif['link'])) ?>"
                                       class="small text-primary fw-semibold text-decoration-none">
                                        View Details <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (empty($notif['is_read'])): ?>
                                    <form method="POST" class="d-inline">
                                        <button type="submit"
                                                name="mark_read"
                                                value="<?= (int)$notif['id'] ?>"
                                                class="btn btn-sm btn-outline-primary">
                                            Mark as Read
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">
                                        Read
                                    </span>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>