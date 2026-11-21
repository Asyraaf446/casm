        </section>
        <footer class="site-foot"><?= e(APP_NAME) ?> · <?= e(APP_TAGLINE) ?></footer>
    </main>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
<?php foreach (($extraScripts ?? []) as $src): ?>
<script src="<?= url($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
