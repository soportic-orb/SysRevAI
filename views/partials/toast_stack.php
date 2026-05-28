<?php

declare(strict_types=1);

/**
 * Top-center toast container. Rendered once per page by layouts/app.php
 * and layouts/admin.php. The actual toast nodes are appended by the JS
 * in app.js, either via:
 *   • Auto-migration: any inline .alert--{success,error,warn} found in
 *     the page becomes a toast and the original is removed (unless it
 *     opts out with data-no-toast).
 *   • Direct API: window.SysRevAI.toast('Saved!', 'success').
 */
?>
<div class="toast-stack" id="toastStack" aria-live="polite" aria-atomic="false"></div>
