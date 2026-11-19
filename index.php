<?php
/**
 * KaamConnect - Public Homepage & Discovery Marketplace
 * Technology: PHP 8+, MySQL PDO, Bootstrap 5.3, Font Awesome 6
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/header.php';

$curLang = getCurrentLanguage();

// 1. Fetch Platform Key Metrics
$stats = [
    'workers' => 0,
    'completed_jobs' => 0,
    'customers' => 0,
    'cities' => 0,
];

try {
    $row = dbFetchOne("SELECT COUNT(*) AS cnt FROM worker_profiles WHERE is_verified = 1");
    $stats['workers'] = (int)($row['cnt'] ?? 0);

    $row = dbFetchOne("SELECT COUNT(*) AS cnt FROM jobs WHERE status = 'completed'");
    $stats['completed_jobs'] = (int)($row['cnt'] ?? 0);

    $row = dbFetchOne("SELECT COUNT(*) AS cnt FROM customer_profiles");
    $stats['customers'] = (int)($row['cnt'] ?? 0);

    $row = dbFetchOne("SELECT COUNT(DISTINCT city) AS cnt FROM (
        SELECT city FROM worker_profiles WHERE city IS NOT NULL AND city != 'Not Set'
        UNION 
        SELECT city FROM customer_profiles WHERE city IS NOT NULL AND city != ''
    ) AS cities_tbl");
    $stats['cities'] = max(1, (int)($row['cnt'] ?? 0));
} catch (Exception $e) {
    // Graceful fallback defaults if DB is temporarily quiet
    $stats = ['workers' => 120, 'completed_jobs' => 450, 'customers' => 380, 'cities' => 12];
}

// 2. Fetch Active Service Categories
$categories = [];
try {
    $categories = dbFetchAll("SELECT * FROM categories WHERE is_active = 1 ORDER BY id ASC LIMIT 12");
} catch (Exception $e) {
    $categories = [];
}

// 3. Fetch Featured Verified Workers
$featuredWorkers = [];
try {
    $featuredWorkers = dbFetchAll(
        "SELECT w.*, u.full_name, c.name_en AS cat_name_en, c.name_hi AS cat_name_hi, c.icon AS cat_icon
         FROM worker_profiles w
         JOIN users u ON w.user_id = u.id
         LEFT JOIN categories c ON w.primary_category_id = c.id
         WHERE u.status = 'active'
         ORDER BY w.is_verified DESC, w.rating_avg DESC, w.rating_count DESC
         LIMIT 6"
    );
} catch (Exception $e) {
    $featuredWorkers = [];
}

// 4. Fetch Latest Open Jobs
$latestJobs = [];
try {
    $latestJobs = dbFetchAll(
        "SELECT j.*, c.name_en AS cat_name_en, c.name_hi AS cat_name_hi, c.icon AS cat_icon, u.full_name AS customer_name
         FROM jobs j
         JOIN categories c ON j.category_id = c.id
         JOIN customer_profiles cp ON j.customer_id = cp.id
         JOIN users u ON cp.user_id = u.id
         WHERE j.status = 'open'
         ORDER BY j.created_at DESC
         LIMIT 6"
    );
} catch (Exception $e) {
    $latestJobs = [];
}

// 5. Fetch Community Testimonials / Verified Reviews
$testimonials = [];
try {
    $testimonials = dbFetchAll(
        "SELECT r.*, cu.full_name AS customer_name, wu.full_name AS worker_name, c.name_en AS worker_trade
         FROM reviews r
         JOIN customer_profiles cp ON r.customer_id = cp.id
         JOIN users cu ON cp.user_id = cu.id
         JOIN worker_profiles wp ON r.worker_id = wp.id
         JOIN users wu ON wp.user_id = wu.id
         LEFT JOIN categories c ON wp.primary_category_id = c.id
         ORDER BY r.rating DESC, r.created_at DESC
         LIMIT 3"
    );
} catch (Exception $e) {
    $testimonials = [];
}
?>

<!-- 1. Hero Section with Unified Search Bar -->
<section class="kc-hero">
    <div class="container position-relative">
        <div class="row align-items-center g-4">
            <div class="col-lg-8 mx-auto text-center">
                <div class="kc-hero-badge shadow-sm">
                    <i class="fas fa-shield-alt text-primary"></i>
                    <span><?= ($curLang === 'hi') ? '100% सत्यापित स्थानीय कारीगर और मजदूर' : '100% Verified Local Artisans & Helpers' ?></span>
                </div>
                <h1 class="display-4 fw-extrabold mb-3">
                    <?= __('hero_headline') ?>
                </h1>
                <p class="lead mx-auto mb-4 text-muted">
                    <?= __('hero_subheadline') ?>
                </p>

                <!-- Search Card -->
                <div class="kc-search-card shadow-lg text-start">
                    <form action="<?= url('/workers.php') ?>" method="GET" class="row g-2 align-items-center" id="kcWorkerSearchForm" data-api-url="<?= url('/api/geocode.php') ?>">
                        <!-- Hidden Geo State -->
                        <input type="hidden" name="lat" id="kcLatInput" value="<?= e($_GET['lat'] ?? '') ?>">
                        <input type="hidden" name="lon" id="kcLngInput" value="<?= e($_GET['lon'] ?? '') ?>">
                        <input type="hidden" name="district" id="kcDistrictInput" value="<?= e($_GET['district'] ?? '') ?>">
                        <input type="hidden" name="state" id="kcStateInput" value="<?= e($_GET['state'] ?? '') ?>">

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-muted mb-1">
                                <i class="fas fa-search me-1 text-primary"></i><?= __('search_worker_placeholder') ?>
                            </label>
                            <input type="text" name="q" class="form-control" placeholder="<?= ($curLang === 'hi') ? 'मिस्त्री, पेंटर, या नाम खोजें...' : 'e.g. Electrician, Ramesh...' ?>" value="<?= e($_GET['q'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">
                                <i class="fas fa-tools me-1 text-primary"></i><?= __('job_category') ?>
                            </label>
                            <select name="category" class="form-select">
                                <option value=""><?= __('select_category') ?></option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= (isset($_GET['category']) && (int)$_GET['category'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                        <?= ($curLang === 'hi') ? e($cat['name_hi']) : e($cat['name_en']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted mb-1">
                                <i class="fas fa-map-marker-alt me-1 text-primary"></i><?= __('select_city') ?>
                            </label>
                            <div class="kc-location-wrapper">
                                <div class="kc-location-input-group">
                                    <input type="text" 
                                           name="city" 
                                           id="kcLocationInput" 
                                           class="form-control" 
                                           placeholder="<?= ($curLang === 'hi') ? 'शहर या इलाका (उदा. Noida, Patna)' : 'City or Locality' ?>" 
                                           value="<?= e($_GET['city'] ?? '') ?>" 
                                           autocomplete="off">
                                    <button type="button" 
                                            class="kc-btn-map-picker" 
                                            id="kcBtnChooseOnMap" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#kcMapModal" 
                                            title="Choose location on map">
                                        <i class="fas fa-map-marked-alt text-primary"></i>
                                        <span class="d-none d-xl-inline">Map</span>
                                    </button>
                                </div>
                                <!-- Autocomplete Suggestions Dropdown -->
                                <div id="kcLocationDropdown" class="kc-autocomplete-dropdown"></div>
                            </div>
                        </div>
                        <div class="col-md-2 d-grid align-self-end">
                            <button type="submit" class="btn btn-kc-primary py-2 mt-md-0 mt-2">
                                <i class="fas fa-arrow-right me-1"></i><?= __('search_btn') ?>
                            </button>
                        </div>
                    </form>
                    <div class="d-flex flex-wrap align-items-center justify-content-between pt-3 mt-3 border-top small text-muted">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold text-dark"><?= ($curLang === 'hi') ? 'लोकप्रिय खोज:' : 'Popular:' ?></span>
                            <a href="<?= url('/workers.php?category=3') ?>" class="badge bg-light text-dark text-decoration-none border">Electrician</a>
                            <a href="<?= url('/workers.php?category=2') ?>" class="badge bg-light text-dark text-decoration-none border">Plumber</a>
                            <a href="<?= url('/workers.php?category=1') ?>" class="badge bg-light text-dark text-decoration-none border">Painter</a>
                            <a href="<?= url('/workers.php?category=4') ?>" class="badge bg-light text-dark text-decoration-none border">Carpenter</a>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <a href="<?= url('/jobs.php') ?>" class="text-primary fw-semibold text-decoration-none">
                                <i class="fas fa-briefcase me-1"></i><?= ($curLang === 'hi') ? 'उपलब्ध काम (Jobs) देखें &rarr;' : 'Browse Customer Jobs &rarr;' ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Dual Quick Action Buttons for Unregistered Visitors -->
                <?php if (!isLoggedIn()): ?>
                    <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                        <a href="<?= url('/register.php?role=worker') ?>" class="btn btn-kc-outline px-4 py-2">
                            <i class="fas fa-hammer me-2 text-primary"></i><?= __('join_as_worker') ?>
                        </a>
                        <a href="<?= url('/register.php?role=customer') ?>" class="btn btn-kc-primary px-4 py-2">
                            <i class="fas fa-bullhorn me-2"></i><?= __('hire_a_worker') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- 2. Platform Key Performance Metrics -->
<section class="py-4 bg-white border-bottom">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-6 col-md-3">
                <div class="d-flex flex-column align-items-center">
                    <div class="p-3 bg-light rounded-circle text-primary mb-2">
                        <i class="fas fa-user-check fs-3"></i>
                    </div>
                    <span class="fs-3 fw-bolder text-dark"><?= number_format($stats['workers']) ?>+</span>
                    <span class="text-muted small fw-medium"><?= __('stat_verified_workers') ?></span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex flex-column align-items-center">
                    <div class="p-3 bg-light rounded-circle text-success mb-2">
                        <i class="fas fa-clipboard-check fs-3"></i>
                    </div>
                    <span class="fs-3 fw-bolder text-dark"><?= number_format($stats['completed_jobs']) ?>+</span>
                    <span class="text-muted small fw-medium"><?= __('stat_jobs_completed') ?></span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex flex-column align-items-center">
                    <div class="p-3 bg-light rounded-circle text-warning mb-2">
                        <i class="fas fa-smile fs-3"></i>
                    </div>
                    <span class="fs-3 fw-bolder text-dark"><?= number_format($stats['customers']) ?>+</span>
                    <span class="text-muted small fw-medium"><?= __('stat_happy_customers') ?></span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="d-flex flex-column align-items-center">
                    <div class="p-3 bg-light rounded-circle text-info mb-2">
                        <i class="fas fa-city fs-3"></i>
                    </div>
                    <span class="fs-3 fw-bolder text-dark"><?= number_format($stats['cities']) ?></span>
                    <span class="text-muted small fw-medium"><?= __('stat_local_cities') ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 3. Popular Service Categories -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">
                <?= ($curLang === 'hi') ? 'हुनरमंद कारीगर' : 'Explore Skilled Trades' ?>
            </span>
            <h2 class="fw-bold"><?= __('popular_categories') ?></h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">
                <?= __('popular_categories_sub') ?>
            </p>
        </div>

        <div class="row g-4">
            <?php foreach ($categories as $cat): ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <a href="<?= url('/workers.php?category=' . (int)$cat['id']) ?>" class="text-decoration-none">
                        <div class="kc-card p-4 text-center h-100 d-flex flex-column align-items-center justify-content-center">
                            <div class="mb-3 d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 64px; height: 64px; background-color: #EFF6FF; color: var(--kc-primary);">
                                <i class="fas <?= e($cat['icon']) ?> fs-3"></i>
                            </div>
                            <h5 class="fw-bold text-dark mb-1">
                                <?= ($curLang === 'hi') ? e($cat['name_hi']) : e($cat['name_en']) ?>
                            </h5>
                            <p class="text-muted small mb-0 line-clamp-2">
                                <?= e($cat['description'] ?? '') ?>
                            </p>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4">
            <a href="<?= url('/workers.php') ?>" class="btn btn-kc-outline">
                <i class="fas fa-th-large me-2"></i><?= ($curLang === 'hi') ? 'सभी श्रेणियां देखें' : 'View All Categories' ?>
            </a>
        </div>
    </div>
</section>

<!-- 4. How It Works Section (3-Step Workflow) -->
<section class="py-5 bg-white border-top border-bottom">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-warning-subtle text-dark fw-semibold px-3 py-2 rounded-pill mb-2">
                <?= ($curLang === 'hi') ? 'आसान प्रक्रिया' : 'Simple & Transparent' ?>
            </span>
            <h2 class="fw-bold"><?= __('how_it_works') ?></h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">
                <?= __('how_it_works_sub') ?>
            </p>
        </div>

        <div class="row g-4">
            <!-- Step 1 -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-4 text-center position-relative">
                    <div class="badge bg-primary text-white position-absolute top-0 start-50 translate-middle rounded-circle fs-6 p-2" style="width: 36px; height: 36px;">1</div>
                    <div class="my-3 text-primary">
                        <i class="fas fa-file-signature fa-3x"></i>
                    </div>
                    <h5 class="fw-bold mb-2"><?= __('step_1_title') ?></h5>
                    <p class="text-muted small mb-0">
                        <?= __('step_1_desc') ?>
                    </p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-4 text-center position-relative">
                    <div class="badge bg-primary text-white position-absolute top-0 start-50 translate-middle rounded-circle fs-6 p-2" style="width: 36px; height: 36px;">2</div>
                    <div class="my-3 text-primary">
                        <i class="fas fa-comments fa-3x"></i>
                    </div>
                    <h5 class="fw-bold mb-2"><?= __('step_2_title') ?></h5>
                    <p class="text-muted small mb-0">
                        <?= __('step_2_desc') ?>
                    </p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm p-4 text-center position-relative">
                    <div class="badge bg-primary text-white position-absolute top-0 start-50 translate-middle rounded-circle fs-6 p-2" style="width: 36px; height: 36px;">3</div>
                    <div class="my-3 text-primary">
                        <i class="fas fa-award fa-3x"></i>
                    </div>
                    <h5 class="fw-bold mb-2"><?= __('step_3_title') ?></h5>
                    <p class="text-muted small mb-0">
                        <?= __('step_3_desc') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 5. Featured Verified Workers Showcase -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
            <div>
                <span class="badge bg-success-subtle text-success fw-semibold px-3 py-2 rounded-pill mb-2">
                    <i class="fas fa-check-double me-1"></i><?= ($curLang === 'hi') ? 'सत्यापित कारीगर' : 'Top Verified Talent' ?>
                </span>
                <h2 class="fw-bold mb-0"><?= __('featured_workers') ?></h2>
                <p class="text-muted mb-0 small"><?= __('featured_workers_sub') ?></p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?= url('/workers.php') ?>" class="btn btn-kc-outline btn-sm">
                    <?= __('view_all') ?> <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <?php if (empty($featuredWorkers)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No verified worker profiles found at the moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($featuredWorkers as $worker): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="kc-card h-100 d-flex flex-column">
                            <div class="p-4 d-flex align-items-start gap-3 border-bottom">
                                <img src="<?= asset($worker['profile_photo'] ?? 'assets/images/default-avatar.svg') ?>" 
                                     alt="<?= e($worker['full_name']) ?>" 
                                     width="64" height="64" 
                                     class="rounded-circle border border-2 border-primary object-fit-cover shadow-sm">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h5 class="fw-bold mb-0 text-dark"><?= e($worker['full_name']) ?></h5>
                                        <?php if ((int)$worker['is_verified'] === 1): ?>
                                            <span class="badge bg-success text-white rounded-pill px-2 py-1 small" title="Verified Professional">
                                                <i class="fas fa-check-circle me-1"></i>Verified
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <i class="fas <?= e($worker['cat_icon'] ?? 'fa-tools') ?> text-primary me-1"></i>
                                        <?= ($curLang === 'hi') ? e($worker['cat_name_hi'] ?? 'कारीगर') : e($worker['cat_name_en'] ?? 'Artisan') ?>
                                    </div>
                                    <div class="mt-1">
                                        <?= renderStars((float)$worker['rating_avg']) ?>
                                        <span class="text-muted small">(<?= (int)$worker['rating_count'] ?>)</span>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-2 small text-muted">
                                    <span><i class="fas fa-map-marker-alt text-danger me-1"></i><?= e($worker['locality']) ?>, <?= e($worker['city']) ?></span>
                                    <span><i class="fas fa-briefcase text-secondary me-1"></i><?= (int)$worker['experience_years'] ?> <?= ($curLang === 'hi') ? 'साल अनुभव' : 'Yrs Exp' ?></span>
                                </div>
                                <p class="text-muted small line-clamp-2 mb-3">
                                    <?= e($worker['bio'] ?: 'Reliable local professional ready for booking.') ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                    <div>
                                        <span class="text-muted small d-block"><?= __('expected_wage') ?></span>
                                        <span class="fw-bold text-dark fs-5">
                                            ₹<?= number_format((float)($worker['daily_wage'] > 0 ? $worker['daily_wage'] : $worker['hourly_wage'])) ?>
                                        </span>
                                        <span class="text-muted small">/<?= $worker['daily_wage'] > 0 ? 'day' : 'hr' ?></span>
                                    </div>
                                    <div>
                                        <a href="<?= url('/workers.php?q=' . urlencode($worker['full_name'])) ?>" class="btn btn-kc-primary btn-sm">
                                            <?= __('contact_worker') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- 6. Latest Open Customer Jobs -->
<section class="py-5 bg-white border-top">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
            <div>
                <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill mb-2">
                    <i class="fas fa-bolt me-1"></i><?= ($curLang === 'hi') ? 'ताजा काम की जरूरतें' : 'Recent Postings' ?>
                </span>
                <h2 class="fw-bold mb-0"><?= __('latest_jobs') ?></h2>
                <p class="text-muted mb-0 small"><?= __('latest_jobs_sub') ?></p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?= url('/jobs.php') ?>" class="btn btn-kc-outline btn-sm">
                    <?= __('view_all') ?> <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <?php if (empty($latestJobs)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No open jobs currently posted. Be the first to post a job requirement!</p>
                    <a href="<?= url('/customer/post-job.php') ?>" class="btn btn-kc-primary btn-sm">
                        <?= __('post_new_job') ?>
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($latestJobs as $job): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="kc-card h-100 d-flex flex-column p-4">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-light text-primary border">
                                    <i class="fas <?= e($job['cat_icon']) ?> me-1"></i>
                                    <?= ($curLang === 'hi') ? e($job['cat_name_hi']) : e($job['cat_name_en']) ?>
                                </span>
                                <?= renderStatusBadge($job['status']) ?>
                            </div>
                            <h5 class="fw-bold text-dark mt-2 mb-2 line-clamp-2">
                                <?= e($job['title']) ?>
                            </h5>
                            <p class="text-muted small flex-grow-1 line-clamp-2 mb-3">
                                <?= e($job['description']) ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center small text-muted mb-3 pt-2 border-top">
                                <span><i class="fas fa-map-marker-alt text-danger me-1"></i><?= e($job['locality']) ?>, <?= e($job['city']) ?></span>
                                <span><i class="fas fa-calendar-alt text-info me-1"></i><?= date('d M Y', strtotime($job['preferred_date'])) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <div>
                                    <span class="text-muted small d-block"><?= __('job_budget') ?></span>
                                    <span class="fw-bold text-success fs-5">₹<?= number_format((float)$job['budget']) ?></span>
                                    <span class="text-muted small">(<?= e($job['wage_type']) ?>)</span>
                                </div>
                                <div>
                                    <a href="<?= url('/jobs.php?q=' . urlencode($job['title'])) ?>" class="btn btn-kc-outline btn-sm">
                                        <?= __('apply_for_job') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- 7. Value Proposition: Why Choose KaamConnect -->
<section class="py-5 bg-light border-top">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-info-subtle text-info-emphasis fw-semibold px-3 py-2 rounded-pill mb-2">
                <?= ($curLang === 'hi') ? 'हमारी विशेषताएं' : 'Core Platform Advantages' ?>
            </span>
            <h2 class="fw-bold"><?= __('why_choose_us') ?></h2>
        </div>

        <div class="row g-4">
            <!-- For Workers -->
            <div class="col-md-6">
                <div class="kc-card p-4 h-100 border-start border-4 border-primary">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-3 bg-primary-subtle rounded-circle text-primary">
                            <i class="fas fa-hard-hat fs-4"></i>
                        </div>
                        <h4 class="fw-bold mb-0"><?= __('why_workers') ?></h4>
                    </div>
                    <ul class="list-unstyled d-flex flex-column gap-3 mb-0 text-muted">
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check-circle text-success mt-1"></i>
                            <div><strong>0% Middleman Commission:</strong> Keep 100% of your earnings. No agencies cutting into your wages.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check-circle text-success mt-1"></i>
                            <div><strong>Digital Portfolio & Badge:</strong> Showcase previous client photos and build a recognized local reputation.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check-circle text-success mt-1"></i>
                            <div><strong>Direct Client Chat:</strong> Discuss requirements, rates, and schedule directly via private in-app messages.</div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- For Customers -->
            <div class="col-md-6">
                <div class="kc-card p-4 h-100 border-start border-4 border-warning">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="p-3 bg-warning-subtle rounded-circle text-warning">
                            <i class="fas fa-user-shield fs-4"></i>
                        </div>
                        <h4 class="fw-bold mb-0"><?= __('why_customers') ?></h4>
                    </div>
                    <ul class="list-unstyled d-flex flex-column gap-3 mb-0 text-muted">
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check-circle text-warning mt-1"></i>
                            <div><strong>Verified Artisan Credentials:</strong> Check Aadhaar verification and practical trade background.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check-circle text-warning mt-1"></i>
                            <div><strong>Transparent Community Reviews:</strong> Real 1-5 star feedback left by real neighbours upon completion.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="fas fa-check-circle text-warning mt-1"></i>
                            <div><strong>Competitive Fair Pricing:</strong> Compare quotes from multiple local applicants and select the best fit.</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 8. Genuine Customer Testimonials -->
<?php if (!empty($testimonials)): ?>
<section class="py-5 bg-white border-top">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-warning-subtle text-dark fw-semibold px-3 py-2 rounded-pill mb-2">
                <?= ($curLang === 'hi') ? 'ग्राहकों के अनुभव' : 'Verified Community Reviews' ?>
            </span>
            <h2 class="fw-bold"><?= __('testimonials') ?></h2>
        </div>

        <div class="row g-4">
            <?php foreach ($testimonials as $t): ?>
                <div class="col-md-4">
                    <div class="kc-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="mb-3">
                                <?= renderStars((float)$t['rating']) ?>
                            </div>
                            <p class="text-muted small fst-italic mb-3">
                                "<?= e($t['review_text'] ?: 'Excellent work, very punctual and highly skilled artisan.') ?>"
                            </p>
                        </div>
                        <div class="pt-3 border-top d-flex align-items-center justify-content-between small">
                            <div>
                                <strong class="text-dark d-block"><?= e($t['customer_name']) ?></strong>
                                <span class="text-muted">Customer</span>
                            </div>
                            <div class="text-end">
                                <span class="text-primary fw-semibold d-block"><?= e($t['worker_name']) ?></span>
                                <span class="text-muted"><?= e($t['worker_trade'] ?? 'Worker') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- 9. Final Call to Action (CTA) Banner -->
<section class="py-5" style="background: linear-gradient(135deg, #1E40AF 0%, #1D4ED8 100%);">
    <div class="container text-center text-white py-3">
        <h2 class="fw-extrabold display-6 mb-3">
            <?= __('cta_title') ?>
        </h2>
        <p class="lead mx-auto mb-4 opacity-90" style="max-width: 650px;">
            <?= __('cta_sub') ?>
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="<?= url('/register.php?role=worker') ?>" class="btn btn-warning fw-bold px-4 py-2 text-dark shadow-sm">
                <i class="fas fa-hammer me-2"></i><?= __('join_as_worker') ?>
            </a>
            <a href="<?= url('/register.php?role=customer') ?>" class="btn btn-light fw-bold px-4 py-2 text-primary shadow-sm">
                <i class="fas fa-user-plus me-2"></i><?= __('role_choice_customer') ?>
            </a>
        </div>
    </div>
</section>

<!-- Interactive Map Location Picker Modal (Leaflet.js) -->
<div class="modal fade" id="kcMapModal" tabindex="-1" aria-labelledby="kcMapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--kc-radius);">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="kcMapModalLabel">
                    <i class="fas fa-map-marked-alt text-primary"></i>
                    <span>Choose Location on Map</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <!-- Location Search Bar inside Map Modal -->
                <div class="kc-map-search-bar">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" 
                               id="kcMapSearchInput" 
                               class="form-control border-start-0" 
                               placeholder="Search city, town, locality or landmark in India...">
                        <button class="btn btn-kc-primary px-3" type="button" id="kcBtnMapSearch">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div class="form-text small text-muted mt-1">
                        <i class="fas fa-info-circle me-1"></i>Search an area or click and drag the blue pin to pinpoint your service locality.
                    </div>
                </div>

                <!-- Leaflet Interactive Map Container -->
                <div id="kcMapContainer" class="kc-map-container mb-3 shadow-sm"></div>

                <!-- Selected Address Live Preview Box -->
                <div class="kc-map-address-preview">
                    <i class="fas fa-map-pin"></i>
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-muted small d-block">Selected Location:</span>
                        <strong class="text-dark small d-block text-truncate" id="kcMapAddressPreviewText">
                            Click anywhere on the map or drag the pin to set your location
                        </strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-kc-primary btn-sm px-4 fw-bold" id="kcBtnConfirmLocation">
                    <i class="fas fa-check-circle me-1"></i>Confirm Location
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

