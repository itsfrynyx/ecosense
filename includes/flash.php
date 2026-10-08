<?php
/**
 * Flash toast renderer — outputs nothing when there are no messages.
 */

if (!function_exists('e')) {
    require_once __DIR__ . '/functions.php';
}

$ecoFlashes = get_flashes();
if (!$ecoFlashes) {
    return;
}

$ecoIcons = [
    'success' => 'check-circle',
    'error'   => 'x-circle',
    'danger'  => 'alert-triangle',
    'warning' => 'alert-triangle',
    'info'    => 'info',
];
?>
<div class="flash-stack" id="flashStack" aria-live="polite" aria-atomic="false">
<?php foreach ($ecoFlashes as $ecoFlash):
    $ecoType = isset($ecoFlash['type']) ? strtolower((string) $ecoFlash['type']) : 'info';
    if (!isset($ecoIcons[$ecoType])) {
        $ecoType = 'info';
    }
?>
    <div class="flash flash--<?= e($ecoType) ?>" role="status" data-flash>
        <span class="flash__icon" aria-hidden="true"><?= icon($ecoIcons[$ecoType]) ?></span>
        <p class="flash__msg"><?= e(isset($ecoFlash['message']) ? $ecoFlash['message'] : '') ?></p>
        <button type="button" class="flash__close" data-flash-close aria-label="Dismiss notification">
            <?= icon('close') ?>
        </button>
        <span class="flash__timer" aria-hidden="true"></span>
    </div>
<?php endforeach; ?>
</div>
<script>
(function () {
    var stack = document.getElementById('flashStack');
    if (!stack) return;
    var items = stack.querySelectorAll('[data-flash]');
    var drop = function (el) {
        el.style.transition = 'opacity .3s ease, transform .3s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-8px)';
        setTimeout(function () { el.remove(); }, 320);
    };
    Array.prototype.forEach.call(items, function (el) {
        var close = el.querySelector('[data-flash-close]');
        if (close) close.addEventListener('click', function () { drop(el); });
        setTimeout(function () { drop(el); }, 4000);
    });
}());
</script>
