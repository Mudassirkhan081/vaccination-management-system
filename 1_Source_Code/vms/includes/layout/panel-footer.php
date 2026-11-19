<?php
/**
 * ---------------------------------------------------------------------
 * File    : includes/layout/panel-footer.php
 * Purpose : Closes the dashboard layout and loads scripts.
 *           Pages may set $pageScripts (array) and $inlineScript (string).
 * ---------------------------------------------------------------------
 */
?>
        </main><!-- /.panel-content -->

        <footer class="mt-auto px-4 py-3 small-note no-print">
            <?= e(APP_NAME) ?> <?= e(APP_TAGLINE) ?>, version <?= e(APP_VERSION) ?>
        </footer>
    </div><!-- /.panel-main -->
</div><!-- /.panel-shell -->

<script src="<?= asset('vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
    <script src="<?= asset($script) ?>"></script>
<?php endforeach; ?>
<script src="<?= asset('js/app.js') ?>"></script>
<?php if (!empty($inlineScript)): ?>
    <script><?= $inlineScript ?></script>
<?php endif; ?>
</body>
</html>
