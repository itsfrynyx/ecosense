<?php
/**
 * Sign out. Requires POST + CSRF so a stray link or prefetch cannot end a
 * session. A GET leaves the session alone and bounces back to the dashboard.
 */

require_once __DIR__ . '/includes/auth.php';

auth_start_session();

if (!is_post()) {
    redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
}

if (!verify_csrf()) {
    flash('crit', 'That sign-out link expired. Please try again.');
    redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
}

auth_logout_equivalent();

flash('info', 'Signed out. Your session on this device was cleared.');
redirect('login.php');

/**
 * auth_destroy_session() also clears $_SESSION, so re-open a blank session to
 * carry the flash message across the redirect.
 */
function auth_logout_equivalent()
{
    auth_destroy_session();
    auth_start_session();
}