<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/layout/public-footer.php
 * Purpose : Footer and scripts for the public website.
 * ---------------------------------------------------------------------
 */
?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <!-- About -->
            <div class="col-lg-5">
                <div class="brand mb-3">
                    <img src="<?= asset('img/logo.svg') ?>" alt="">
                    <span>
                        <span class="brand-name"><?= e(APP_NAME) ?></span>
                        <span class="brand-sub"><?= e(APP_TAGLINE) ?></span>
                    </span>
                </div>
                <p class="mb-0">A digital version of your child's vaccination card. Due dates are worked out from the routine
                    childhood schedule, so no visit is forgotten.</p>
            </div>

            <!-- Quick links -->
            <div class="col-6 col-lg-2">
                <h2>Explore</h2>
                <ul class="list-unstyled mb-0">
                    <li><a href="<?= url('schedule.php') ?>">Vaccine schedule</a></li>
                    <li><a href="<?= url('hospitals.php') ?>">Hospitals</a></li>
                    <li><a href="<?= url('contact.php') ?>">Contact</a></li>
                </ul>
            </div>

            <!-- Account links -->
            <div class="col-6 col-lg-2">
                <h2>Accounts</h2>
                <ul class="list-unstyled mb-0">
                    <li><a href="<?= url('register.php') ?>">Register as parent</a></li>
                    <li><a href="<?= url('hospital-register.php') ?>">Register a hospital</a></li>
                    <li><a href="<?= url('login.php?role=hospital') ?>">Hospital login</a></li>
                    <li><a href="<?= url('login.php?role=admin') ?>">Admin login</a></li>
                </ul>
            </div>

            <!-- Helpline -->
            <div class="col-lg-3">
                <h2>Helpline</h2>
                <p class="mb-1"><i class="bi bi-telephone me-2" aria-hidden="true"></i>021-111-832-532</p>
                <p class="mb-1"><i class="bi bi-envelope me-2" aria-hidden="true"></i>help@teekacard.pk</p>
                <p class="mb-0"><i class="bi bi-clock me-2" aria-hidden="true"></i>Mon to Sat, 9 AM to 6 PM</p>
            </div>
        </div>

        <div class="footer-bottom d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. All rights reserved.</span>
            <span>Aptech eProject: Vaccination Management System</span>
        </div>
    </div>
</footer>

<script src="<?= asset('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
