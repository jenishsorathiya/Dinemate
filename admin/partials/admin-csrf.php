<?php
$adminActionCsrfToken = csrfToken('admin_actions');
$adminCsrfIncludeMeta = $adminCsrfIncludeMeta ?? true;
?>
<?php if ($adminCsrfIncludeMeta): ?>
<meta name="csrf-token" content="<?= htmlspecialchars($adminActionCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<script>
(function () {
    if (window.DineMateCsrfFetchInstalled || !window.fetch) {
        return;
    }

    window.DineMateCsrfFetchInstalled = true;
    window.DineMateAdminCsrfToken = <?= json_encode($adminActionCsrfToken) ?>;

    const originalFetch = window.fetch;
    window.fetch = function (input, init) {
        init = init || {};
        const requestUrl = typeof input === 'string' ? input : (input && input.url ? input.url : '');
        const method = String(init.method || (input && input.method) || 'GET').toUpperCase();

        if (window.DineMateAdminCsrfToken && !['GET', 'HEAD', 'OPTIONS'].includes(method)) {
            try {
                const url = new URL(requestUrl || window.location.href, window.location.href);
                if (url.origin === window.location.origin) {
                    const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
                    if (!headers.has('X-CSRF-Token')) {
                        headers.set('X-CSRF-Token', window.DineMateAdminCsrfToken);
                    }
                    init = Object.assign({}, init, { headers: headers });
                }
            } catch (error) {
                // Leave unusual fetch requests untouched.
            }
        }

        return originalFetch.call(this, input, init);
    };
})();
</script>
