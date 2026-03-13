<?php
/**
 * Uninstall handler for Dope Studio Business Reviews Slider.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$dsbrs_settings = get_option('dsbrs_settings', []);
$dsbrs_delete_data = is_array($dsbrs_settings) && isset($dsbrs_settings['delete_on_uninstall']) && (int) $dsbrs_settings['delete_on_uninstall'] === 1;

// Always clear scheduled cron hook.
wp_clear_scheduled_hook('dsbrs_cron_fetch_reviews');

if (! $dsbrs_delete_data) {
    return;
}

delete_option('dsbrs_settings');
delete_option('dsbrs_reviews_cache');
delete_option('dsbrs_trustpilot_reviews_cache');
