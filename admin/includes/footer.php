<?php
/**
 * KaamConnect - Admin Console Footer
 */

declare(strict_types=1);
?>
        </main>
        <!-- Page Main Container Ends -->

        <!-- Admin Footer -->
        <footer class="bg-white border-top py-3 px-4 text-center text-md-between d-flex flex-column flex-md-row justify-content-between align-items-center small text-muted">
            <div>
                &copy; 2026 <strong>KaamConnect</strong> Admin Console. B.Tech Major Project.
            </div>
            <div class="mt-2 mt-md-0">
                <span class="badge bg-light text-dark border">
                    <i class="fas fa-server text-success me-1"></i>Database: MySQL PDO Connected (Port: <?= DB_PORT ?>)
                </span>
            </div>
        </footer>
    </div>
</div>

<!-- Bootstrap 5.3.3 Bundle with Popper JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Leaflet 1.9.4 JS for Coordinates Map -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Mobile Sidebar Toggle
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebar = document.getElementById('adminSidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }
});
</script>

</body>
</html>

