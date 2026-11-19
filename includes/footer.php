<?php
/**
 * KaamConnect - Global Footer Template
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
$categories = [];
try {
    $categories = dbFetchAll("SELECT id, name_en, name_hi FROM categories WHERE is_active = 1 LIMIT 6");
} catch (Exception $e) {
    // Graceful fallback
}
$curLang = getCurrentLanguage();
?>
</main>

<footer class="kc-footer">
    <div class="container">
        <div class="row g-4">
            <!-- Brand Column -->
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="fs-4 text-white fw-bold"><i class="fas fa-handshake text-warning me-2"></i>Kaam<span class="text-warning">Connect</span></span>
                </div>
                <p class="small text-secondary mb-3">
                    <?= __('footer_about') ?>
                </p>
                <div class="d-flex align-items-center gap-3 fs-5 text-secondary">
                    <a href="#" class="text-secondary" title="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" class="text-secondary" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-secondary" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="#" class="text-secondary" title="LinkedIn"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 col-6">
                <h5><?= __('quick_links') ?></h5>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="<?= url('/index.php') ?>"><?= __('nav_home') ?></a></li>
                    <li><a href="<?= url('/workers.php') ?>"><?= __('nav_find_workers') ?></a></li>
                    <li><a href="<?= url('/jobs.php') ?>"><?= __('nav_find_jobs') ?></a></li>
                    <li><a href="<?= url('/about.php') ?>"><?= __('nav_about') ?></a></li>
                    <li><a href="<?= url('/contact.php') ?>"><?= __('nav_contact') ?></a></li>
                </ul>
            </div>

            <!-- Categories -->
            <div class="col-lg-3 col-md-6 col-6">
                <h5><?= __('popular_categories') ?></h5>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="<?= url('/workers.php?category=' . (int)$cat['id']) ?>">
                                <?= ($curLang === 'hi') ? e($cat['name_hi']) : e($cat['name_en']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Trust & Project Info -->
            <div class="col-lg-3 col-md-6">
                <h5>Platform Trust</h5>
                <div class="d-flex flex-column gap-3 small">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-id-card-clip text-warning fs-5"></i>
                        <span>Aadhaar & Skill Background Verified Workers</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-comments text-primary fs-5"></i>
                        <span>Private & Direct In-Platform Chat</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-star text-warning fs-5"></i>
                        <span>100% Genuine Customer Ratings & Reviews</span>
                    </div>
                </div>
            </div>
        </div>

        <hr class="border-secondary my-4 opacity-25">

        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between small text-secondary">
            <div>
                &copy; <?= date('Y') ?> <strong>KaamConnect</strong>. <?= __('all_rights_reserved') ?>
            </div>
            <div class="mt-2 mt-md-0">
                Designed & Developed for B.Tech Computer Science and Engineering Major Project.
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3.3 Bundle with Popper JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Leaflet 1.9.4 JS for Interactive Map Location Picker -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<!-- KaamConnect Custom Application JS -->
<script src="<?= asset('assets/js/main.js') ?>"></script>

<!-- KaamConnect Intelligent Location Search & Map Picker JS -->
<script src="<?= asset('assets/js/location-search.js') ?>"></script>

</body>
</html>
