<?php
/**
 * Uninstall handler for Dope Studio Business Reviews Slider Lite.
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$dsbrsl_settings = get_option('dsbrsl_settings', []);
$dsbrsl_delete_data = is_array($dsbrsl_settings) && isset($dsbrsl_settings['delete_on_uninstall']) && (int) $dsbrsl_settings['delete_on_uninstall'] === 1;

// Always clear scheduled cron hook.
wp_clear_scheduled_hook('dsbrsl_cron_fetch_reviews');

if (! $dsbrsl_delete_data) {
    return;
}

delete_option('dsbrsl_settings');
delete_option('dsbrsl_reviews_cache');
