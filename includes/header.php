<?php
/**
 * KaamConnect - Global Header Template
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = isset($pageTitle) ? e($pageTitle) . ' | ' . __('app_name') : __('app_name') . ' - ' . __('app_tagline');
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?= e($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    
    <!-- Font Awesome 6.5.2 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <!-- Leaflet 1.9.4 CSS for Interactive Map Location Picker -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    
    <!-- KaamConnect Custom Stylesheet -->
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; ?>

<!-- Flash Message Notifications -->
<?php $flash = getFlash(); ?>
<?php if ($flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm d-flex align-items-center gap-2" role="alert">
            <?php if ($flash['type'] === 'success'): ?>
                <i class="fas fa-check-circle fs-5"></i>
            <?php elseif ($flash['type'] === 'danger'): ?>
                <i class="fas fa-exclamation-circle fs-5"></i>
            <?php elseif ($flash['type'] === 'warning'): ?>
                <i class="fas fa-exclamation-triangle fs-5"></i>
            <?php else: ?>
                <i class="fas fa-info-circle fs-5"></i>
            <?php endif; ?>
            <div><?= e($flash['message']) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<main>
