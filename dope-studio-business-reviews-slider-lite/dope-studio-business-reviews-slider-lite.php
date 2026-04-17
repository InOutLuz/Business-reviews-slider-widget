<?php
/**
 * Plugin Name: Dope Studio Business Reviews Slider Lite
 * Description: Fetch and display Google reviews with a customizable slider widget.
 * Version: 1.0.16
 * Author: Dope Studio
 * Author URI: https://profiles.wordpress.org/dopestudio
 * License: GPL-2.0+
 * Text Domain: dope-studio-business-reviews-slider-lite
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

class DSBRSL_Google_Reviews_Slider_Lite
{
    private const PLUGIN_VERSION = '1.0.16';
    private const SETTINGS_OPTION = 'dsbrsl_settings';
    private const CACHE_OPTION = 'dsbrsl_reviews_cache';
    private const SHORTCODE_GOOGLE = 'dsbrsl_google_reviews_slider';
    private const AJAX_ACTION = 'dsbrsl_fetch_reviews';
    private const CRON_HOOK = 'dsbrsl_cron_fetch_reviews';
    private const TELEMETRY_ENDPOINT = 'https://products.dopestudio.co.uk/brs/telemetry/collect.php';
    private const INSTALL_ID_OPTION = 'dsbrsl_install_id';
    private const DEACTIVATE_FEEDBACK_AJAX = 'dsbrsl_deactivation_feedback';

    public function __construct()
    {
        add_action('admin_init', [$this, 'maybe_repair_legacy_defaults'], 5);
        add_action('admin_init', [$this, 'handle_run_wizard_request'], 6);
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'register_frontend_assets']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajax_fetch_reviews']);
        add_action('wp_ajax_' . self::DEACTIVATE_FEEDBACK_AJAX, [$this, 'ajax_deactivation_feedback']);
        add_action(self::CRON_HOOK, [$this, 'run_scheduled_fetch']);
        add_action('update_option_' . self::SETTINGS_OPTION, [$this, 'on_settings_updated'], 10, 2);
        add_action('init', [$this, 'ensure_cron_schedule']);
        add_filter('cron_schedules', [$this, 'register_cron_schedules']);

        add_shortcode(self::SHORTCODE_GOOGLE, [$this, 'render_shortcode']);
    }

    public function register_admin_page(): void
    {
        add_menu_page(
            __('Dope Studio Business Reviews Slider Lite', 'dope-studio-business-reviews-slider-lite'),
            __('Business Reviews Lite', 'dope-studio-business-reviews-slider-lite'),
            'manage_options',
            'dsbrsl-dope-studio-business-reviews-slider-lite',
            [$this, 'render_admin_page'],
            'dashicons-star-filled',
            58
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'dsbrsl_settings_group',
            self::SETTINGS_OPTION,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [
                    'enable_google' => 1,
                    'token' => '',
                    'place_id' => '',
                    'place_url' => '',
                    'google_places_api_key' => '',
                    'use_places_api_summary' => 0,
                    'max_reviews' => 0,
                    'language' => 'en',
                    'theme' => 'dark',
                    'autoplay_default' => 1,
                    'autoplay_interval_default' => 5500,
                    'loop_infinite_default' => 1,
                    'show_dots_default' => 1,
                    'swipe_default' => 1,
                    'slides_mobile_default' => 1,
                    'slides_tablet_default' => 2,
                    'slides_desktop_default' => 3,
                    'display_limit_default' => 0,
                    'display_limit_default_touched' => 0,
                    'schema_review_limit_default' => 0,
                    'schema_review_limit_default_touched' => 0,
                    'show_summary_default' => 1,
                    'show_read_on_google_default' => 1,
                    'rating_mode_default' => 'auto',
                    'manual_rating_default' => 5,
                    'review_count_mode' => 'fetched',
                    'custom_review_count' => 0,
                    'title_default' => 'Latest reviews',
                    'show_no_comment' => 0,
                    'min_rating_default' => 0,
                    'cron_enabled' => 0,
                    'cron_frequency' => 'weekly',
                    'cron_time' => '03:00',
                    'cron_max_reviews' => 1,
                    'cron_fetch_scope' => 'enabled',
                    'wizard_completed' => 0,
                    'delete_on_uninstall' => 0,
                ],
            ]
        );
    }

    public function maybe_repair_legacy_defaults(): void
    {
        $settings = get_option(self::SETTINGS_OPTION, null);
        if (is_array($settings)) {
            $displayLimitTouched = isset($settings['display_limit_default_touched']) ? (int) $settings['display_limit_default_touched'] : 0;
            if (! isset($settings['display_limit_default_touched'])) {
                $settings['display_limit_default_touched'] = 0;
            }

            $schemaLimitTouched = isset($settings['schema_review_limit_default_touched']) ? (int) $settings['schema_review_limit_default_touched'] : 0;
            if (! isset($settings['schema_review_limit_default_touched'])) {
                $settings['schema_review_limit_default_touched'] = 0;
            }

            if (isset($settings['display_limit_default']) && (int) $settings['display_limit_default'] === 6 && $displayLimitTouched !== 1) {
                $settings['display_limit_default'] = 0;
                $settings['display_limit_default_touched'] = 0;
            }

            if (isset($settings['schema_review_limit_default']) && (int) $settings['schema_review_limit_default'] === 10 && $schemaLimitTouched !== 1) {
                $settings['schema_review_limit_default'] = 0;
                $settings['schema_review_limit_default_touched'] = 0;
            }

            if (! isset($settings['wizard_completed'])) {
                $settings['wizard_completed'] = 1;
            }

            update_option(self::SETTINGS_OPTION, $settings);
        }

        if (get_option('dsbrsl_legacy_defaults_fixed_103', '0') === '1') {
            return;
        }

        if (! is_array($settings)) {
            update_option('dsbrsl_legacy_defaults_fixed_103', '1', false);
            return;
        }

        $changed = false;

        if (! isset($settings['wizard_completed'])) {
            $settings['wizard_completed'] = 1;
            $changed = true;
        }

        foreach (['use_places_api_summary', 'cron_enabled', 'delete_on_uninstall'] as $checkboxKey) {
            if (isset($settings[$checkboxKey]) && (int) $settings[$checkboxKey] === 1) {
                $settings[$checkboxKey] = 0;
                $changed = true;
            }
        }

        if (isset($settings['max_reviews']) && (int) $settings['max_reviews'] === 1) {
            $settings['max_reviews'] = 0;
            $changed = true;
        }

        if (isset($settings['display_limit_default']) && (int) $settings['display_limit_default'] === 6 && (! isset($settings['display_limit_default_touched']) || (int) $settings['display_limit_default_touched'] !== 1)) {
            $settings['display_limit_default'] = 0;
            $settings['display_limit_default_touched'] = 0;
            $changed = true;
        }

        if (isset($settings['schema_review_limit_default']) && (int) $settings['schema_review_limit_default'] === 10 && (! isset($settings['schema_review_limit_default_touched']) || (int) $settings['schema_review_limit_default_touched'] !== 1)) {
            $settings['schema_review_limit_default'] = 0;
            $settings['schema_review_limit_default_touched'] = 0;
            $changed = true;
        }

        if ($changed) {
            update_option(self::SETTINGS_OPTION, $settings);
        }

        update_option('dsbrsl_legacy_defaults_fixed_103', '1', false);
    }

    public function handle_run_wizard_request(): void
    {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return;
        }

        $page = sanitize_key((string) filter_input(INPUT_GET, 'page', FILTER_UNSAFE_RAW));
        if ($page !== 'dsbrsl-dope-studio-business-reviews-slider-lite') {
            return;
        }

        $runWizard = sanitize_key((string) filter_input(INPUT_GET, 'run_wizard', FILTER_UNSAFE_RAW));
        $skipWizard = sanitize_key((string) filter_input(INPUT_GET, 'skip_wizard', FILTER_UNSAFE_RAW));
        $wizardNonce = sanitize_text_field((string) filter_input(INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW));
        if ($runWizard !== '1' && $skipWizard !== '1') {
            return;
        }

        $expectedAction = $runWizard === '1' ? 'dsbrsl_run_wizard' : 'dsbrsl_skip_wizard';
        if (! wp_verify_nonce($wizardNonce, $expectedAction)) {
            return;
        }

        $settings = get_option(self::SETTINGS_OPTION, []);
        if (! is_array($settings)) {
            $settings = [];
        }

        $settings['wizard_completed'] = $runWizard === '1' ? 0 : 1;
        update_option(self::SETTINGS_OPTION, $settings, false);

        wp_safe_redirect(add_query_arg(array_filter([
            'page' => 'dsbrsl-dope-studio-business-reviews-slider-lite',
            'wizard_reset' => $runWizard === '1' ? '1' : null,
        ]), admin_url('admin.php')));
        exit;
    }

    public function sanitize_settings(array $input): array
    {
        $output = [];

        $output['enable_google'] = $this->sanitize_checkbox_value($input, 'enable_google');
        $output['token'] = isset($input['token']) ? sanitize_text_field($input['token']) : '';
        $output['place_id'] = isset($input['place_id']) ? sanitize_text_field($input['place_id']) : '';
        $output['place_url'] = isset($input['place_url']) ? esc_url_raw((string) $input['place_url']) : '';
        $output['google_places_api_key'] = isset($input['google_places_api_key']) ? sanitize_text_field($input['google_places_api_key']) : '';
        $output['use_places_api_summary'] = $this->sanitize_checkbox_value($input, 'use_places_api_summary');

        $maxReviewsRaw = isset($input['max_reviews']) ? trim((string) $input['max_reviews']) : '';
        if ($maxReviewsRaw === '') {
            $output['max_reviews'] = 0;
        } else {
            $output['max_reviews'] = max(1, min(500, absint($maxReviewsRaw)));
        }

        $output['language'] = isset($input['language']) ? sanitize_text_field((string) $input['language']) : 'en';

        $theme = isset($input['theme']) ? sanitize_key($input['theme']) : 'dark';
        $output['theme'] = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';

        $output['autoplay_default'] = $this->sanitize_checkbox_value($input, 'autoplay_default');
        $output['autoplay_interval_default'] = isset($input['autoplay_interval_default'])
            ? max(1500, min(20000, absint($input['autoplay_interval_default'])))
            : 5500;
        $output['loop_infinite_default'] = $this->sanitize_checkbox_value($input, 'loop_infinite_default');
        $output['show_dots_default'] = $this->sanitize_checkbox_value($input, 'show_dots_default');
        $output['swipe_default'] = $this->sanitize_checkbox_value($input, 'swipe_default');
        $output['slides_mobile_default'] = isset($input['slides_mobile_default']) ? max(1, min(6, absint($input['slides_mobile_default']))) : 1;
        $output['slides_tablet_default'] = isset($input['slides_tablet_default']) ? max(1, min(6, absint($input['slides_tablet_default']))) : 2;
        $output['slides_desktop_default'] = isset($input['slides_desktop_default']) ? max(1, min(6, absint($input['slides_desktop_default']))) : 3;

        $displayLimitRaw = isset($input['display_limit_default']) ? trim((string) $input['display_limit_default']) : '';
        if ($displayLimitRaw === '' || absint($displayLimitRaw) <= 0) {
            $output['display_limit_default'] = 0;
            $output['display_limit_default_touched'] = 0;
        } else {
            $output['display_limit_default'] = max(6, min(500, absint($displayLimitRaw)));
            $output['display_limit_default_touched'] = 1;
        }

        $schemaLimitRaw = isset($input['schema_review_limit_default']) ? trim((string) $input['schema_review_limit_default']) : '';
        if ($schemaLimitRaw === '' || absint($schemaLimitRaw) <= 0) {
            $output['schema_review_limit_default'] = 0;
            $output['schema_review_limit_default_touched'] = 0;
        } else {
            $output['schema_review_limit_default'] = max(1, min(100, absint($schemaLimitRaw)));
            $output['schema_review_limit_default_touched'] = 1;
        }

        $output['show_summary_default'] = $this->sanitize_checkbox_value($input, 'show_summary_default');
        $output['show_read_on_google_default'] = $this->sanitize_checkbox_value($input, 'show_read_on_google_default');
        $output['show_no_comment'] = $this->sanitize_checkbox_value($input, 'show_no_comment');

        $minRating = isset($input['min_rating_default']) ? absint($input['min_rating_default']) : 0;
        $output['min_rating_default'] = in_array($minRating, [0, 2, 3, 4, 5], true) ? $minRating : 0;

        $ratingMode = isset($input['rating_mode_default']) ? sanitize_key($input['rating_mode_default']) : 'auto';
        $output['rating_mode_default'] = in_array($ratingMode, ['auto', 'manual'], true) ? $ratingMode : 'auto';
        $output['manual_rating_default'] = isset($input['manual_rating_default']) ? max(1, min(5, (float) $input['manual_rating_default'])) : 5;

        $reviewCountMode = isset($input['review_count_mode']) ? sanitize_key($input['review_count_mode']) : 'fetched';
        $output['review_count_mode'] = in_array($reviewCountMode, ['fetched', 'custom'], true) ? $reviewCountMode : 'fetched';
        $output['custom_review_count'] = isset($input['custom_review_count']) ? max(0, absint($input['custom_review_count'])) : 0;

        $titleDefault = isset($input['title_default']) ? trim((string) $input['title_default']) : '';
        $output['title_default'] = $titleDefault !== '' ? sanitize_text_field($titleDefault) : 'Latest reviews';

        $output['cron_enabled'] = $this->sanitize_checkbox_value($input, 'cron_enabled');

        $cronFrequency = isset($input['cron_frequency']) ? sanitize_key((string) $input['cron_frequency']) : 'weekly';
        $allowedFrequencies = ['twicedaily', 'daily', 'weekly', 'dsbrsl_every_2_days', 'dsbrsl_monthly'];
        $output['cron_frequency'] = in_array($cronFrequency, $allowedFrequencies, true) ? $cronFrequency : 'weekly';

        $cronTime = isset($input['cron_time']) ? sanitize_text_field((string) $input['cron_time']) : '03:00';
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $cronTime)) {
            $cronTime = '03:00';
        }
        $output['cron_time'] = $cronTime;

        $cronMaxReviewsRaw = isset($input['cron_max_reviews']) ? trim((string) $input['cron_max_reviews']) : '';
        if ($cronMaxReviewsRaw === '') {
            $output['cron_max_reviews'] = 1;
        } else {
            $output['cron_max_reviews'] = max(1, min(50, absint($cronMaxReviewsRaw)));
        }

        // Lite is Google-only, so cron fetch scope is fixed.
        $output['cron_fetch_scope'] = 'enabled';

        $output['wizard_completed'] = $this->sanitize_checkbox_value($input, 'wizard_completed');

        $output['delete_on_uninstall'] = $this->sanitize_checkbox_value($input, 'delete_on_uninstall');

        return $output;
    }

    private function sanitize_checkbox_value(array $input, string $key): int
    {
        if (! array_key_exists($key, $input)) {
            return 0;
        }

        $value = $input[$key];
        if (is_array($value)) {
            $value = end($value);
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if ($hook === 'plugins.php') {
            $this->enqueue_deactivation_feedback_assets();
            return;
        }

        if ($hook !== 'toplevel_page_dsbrsl-dope-studio-business-reviews-slider-lite') {
            return;
        }

        $adminCssPath = plugin_dir_path(__FILE__) . 'assets/admin.css';
        $adminJsPath = plugin_dir_path(__FILE__) . 'assets/admin.js';

        wp_enqueue_style(
            'dsbrsl-admin-style',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            file_exists($adminCssPath) ? (string) filemtime($adminCssPath) : self::PLUGIN_VERSION
        );

        wp_enqueue_script(
            'dsbrsl-admin-script',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            [],
            file_exists($adminJsPath) ? (string) filemtime($adminJsPath) : self::PLUGIN_VERSION,
            true
        );

        wp_localize_script('dsbrsl-admin-script', 'dsbrslAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action'  => self::AJAX_ACTION,
            'nonce'   => wp_create_nonce(self::AJAX_ACTION),
        ]);
    }

        private function enqueue_deactivation_feedback_assets(): void
        {
                if (! current_user_can('activate_plugins')) {
                        return;
                }

                $handle = 'dsbrsl-deactivation-feedback';
                wp_register_script($handle, '', [], self::PLUGIN_VERSION, true);
                wp_enqueue_script($handle);

                $payload = [
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce(self::DEACTIVATE_FEEDBACK_AJAX),
                        'action' => self::DEACTIVATE_FEEDBACK_AJAX,
                        'pluginBasename' => plugin_basename(__FILE__),
                        'title' => __('Quick feedback before you deactivate', 'dope-studio-business-reviews-slider-lite'),
                        'subtitle' => __('What made you deactivate the plugin?', 'dope-studio-business-reviews-slider-lite'),
                        'placeholder' => __('Optional: add a few details to help us improve', 'dope-studio-business-reviews-slider-lite'),
                        'emailPlaceholder' => __('Optional: your email if you want a reply', 'dope-studio-business-reviews-slider-lite'),
                        'skipLabel' => __('Skip and deactivate', 'dope-studio-business-reviews-slider-lite'),
                        'submitLabel' => __('Submit and deactivate', 'dope-studio-business-reviews-slider-lite'),
                        'reasons' => [
                            ['value' => 'switched_to_pro', 'label' => __('I switched to PRO version', 'dope-studio-business-reviews-slider-lite')],
                                ['value' => 'not_working', 'label' => __('The plugin is not working as expected', 'dope-studio-business-reviews-slider-lite')],
                                ['value' => 'missing_feature', 'label' => __('It is missing a feature I need', 'dope-studio-business-reviews-slider-lite')],
                                ['value' => 'hard_to_use', 'label' => __('It is hard to use or configure', 'dope-studio-business-reviews-slider-lite')],
                                ['value' => 'switched_plugin', 'label' => __('I switched to another plugin', 'dope-studio-business-reviews-slider-lite')],
                                ['value' => 'temporary', 'label' => __('Temporary deactivation', 'dope-studio-business-reviews-slider-lite')],
                                ['value' => 'other', 'label' => __('Other', 'dope-studio-business-reviews-slider-lite')],
                        ],
                ];

                wp_add_inline_script($handle, 'window.dsbrslDeactivateFeedback = ' . wp_json_encode($payload) . ';', 'before');

                $script = <<<'JS'
(function () {
    var config = window.dsbrslDeactivateFeedback;
    if (!config || !config.pluginBasename) {
        return;
    }

    var deactivateLink = document.querySelector('tr[data-plugin="' + config.pluginBasename + '"] .deactivate a');
    if (!deactivateLink) {
        return;
    }

    var deactivateUrl = '';
    var overlay = null;

    function buildModal() {
        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:100000;';
        overlay.innerHTML = '' +
            '<div style="background:#fff;width:560px;max-width:calc(100vw - 32px);border-radius:8px;padding:20px 20px 16px;box-shadow:0 10px 30px rgba(0,0,0,.2);">' +
            '<h2 style="margin:0 0 8px;font-size:20px;line-height:1.3;">' + config.title + '</h2>' +
            '<p style="margin:0 0 12px;color:#50575e;">' + config.subtitle + '</p>' +
            '<div id="dsbrsl-feedback-reasons" style="display:grid;gap:8px;margin-bottom:12px;"></div>' +
            '<textarea id="dsbrsl-feedback-comment" rows="3" style="width:100%;margin:0 0 10px;" placeholder="' + config.placeholder + '"></textarea>' +
            '<input id="dsbrsl-feedback-email" type="email" style="width:100%;margin:0 0 14px;" placeholder="' + config.emailPlaceholder + '" />' +
            '<div style="display:flex;justify-content:flex-end;gap:8px;">' +
            '<button type="button" class="button" id="dsbrsl-feedback-skip">' + config.skipLabel + '</button>' +
            '<button type="button" class="button button-primary" id="dsbrsl-feedback-submit">' + config.submitLabel + '</button>' +
            '</div>' +
            '</div>';

        document.body.appendChild(overlay);
        var reasonsWrap = overlay.querySelector('#dsbrsl-feedback-reasons');
        (config.reasons || []).forEach(function (item, index) {
            var row = document.createElement('label');
            row.style.cssText = 'display:flex;gap:8px;align-items:flex-start;';
            row.innerHTML = '<input type="radio" name="dsbrsl_feedback_reason" value="' + item.value + '" ' + (index === 0 ? 'checked' : '') + ' /><span>' + item.label + '</span>';
            reasonsWrap.appendChild(row);
        });

        overlay.querySelector('#dsbrsl-feedback-skip').addEventListener('click', function () {
            window.location.href = deactivateUrl;
        });

        overlay.querySelector('#dsbrsl-feedback-submit').addEventListener('click', function () {
            var checked = overlay.querySelector('input[name="dsbrsl_feedback_reason"]:checked');
            var comment = overlay.querySelector('#dsbrsl-feedback-comment').value || '';
            var email = overlay.querySelector('#dsbrsl-feedback-email').value || '';
            var body = new URLSearchParams();
            body.set('action', config.action);
            body.set('_ajax_nonce', config.nonce);
            body.set('reason', checked ? checked.value : 'other');
            body.set('comment', comment);
            body.set('email', email);

            fetch(config.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
                credentials: 'same-origin'
            }).finally(function () {
                window.location.href = deactivateUrl;
            });
        });

        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) {
                overlay.remove();
                overlay = null;
            }
        });

        return overlay;
    }

    deactivateLink.addEventListener('click', function (event) {
        event.preventDefault();
        deactivateUrl = deactivateLink.getAttribute('href') || '';
        buildModal();
    });
})();
JS;

                wp_add_inline_script($handle, $script, 'after');
        }

        public function ajax_deactivation_feedback(): void
        {
                check_ajax_referer(self::DEACTIVATE_FEEDBACK_AJAX);

                if (! current_user_can('activate_plugins')) {
                        wp_send_json_error(['message' => 'forbidden'], 403);
                }

                $reason = sanitize_key((string) filter_input(INPUT_POST, 'reason', FILTER_UNSAFE_RAW));
                $allowedReasons = ['switched_to_pro', 'not_working', 'missing_feature', 'hard_to_use', 'switched_plugin', 'temporary', 'other'];
                if (! in_array($reason, $allowedReasons, true)) {
                        $reason = 'other';
                }

                $comment = trim((string) filter_input(INPUT_POST, 'comment', FILTER_UNSAFE_RAW));
                $comment = sanitize_textarea_field($comment);
                if (strlen($comment) > 600) {
                        $comment = substr($comment, 0, 600);
                }

                $email = sanitize_email((string) filter_input(INPUT_POST, 'email', FILTER_UNSAFE_RAW));

                $this->send_telemetry_event('deactivate_feedback', [
                        'reason' => $reason,
                        'comment' => $comment,
                        'email' => $email,
                ]);

                wp_send_json_success(['ok' => true]);
        }

        private function send_telemetry_event(string $event, array $extra = []): void
        {
                self::send_telemetry_event_static($event, $extra);
        }

        private static function send_telemetry_event_static(string $event, array $extra = []): void
        {
                $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
                $payload = array_merge([
                        'event' => sanitize_key($event),
                        'plugin' => 'lite',
                        'version' => self::PLUGIN_VERSION,
                        'install_id' => self::get_or_create_install_id(),
                        'site_hash' => hash('sha256', strtolower(untrailingslashit(home_url('/')))),
                        'home_host' => strtolower($host),
                        'wp_version' => get_bloginfo('version'),
                        'php_version' => PHP_VERSION,
                        'locale' => get_locale(),
                        'timestamp_utc' => gmdate('c'),
                ], $extra);

                wp_remote_post(self::TELEMETRY_ENDPOINT, [
                        'timeout' => 5,
                        'blocking' => false,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => wp_json_encode($payload),
                ]);
        }

        private static function get_or_create_install_id(): string
        {
                $installId = get_option(self::INSTALL_ID_OPTION, '');
                if (is_string($installId) && preg_match('/^[a-zA-Z0-9\-]{8,64}$/', $installId) === 1) {
                        return $installId;
                }

                $installId = wp_generate_uuid4();
                update_option(self::INSTALL_ID_OPTION, $installId, false);

                return $installId;
        }

    public function register_frontend_assets(): void
    {
        wp_register_style(
            'dsbrsl-frontend-style',
            plugin_dir_url(__FILE__) . 'assets/frontend.css',
            [],
            '1.0.5'
        );

        wp_register_script(
            'dsbrsl-frontend-script',
            plugin_dir_url(__FILE__) . 'assets/frontend.js',
            [],
            '1.0.10',
            true
        );
    }

    public function render_admin_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = get_option(self::SETTINGS_OPTION, []);
        $cache = get_option(self::CACHE_OPTION, []);
        $googleCount = isset($cache['reviews']) && is_array($cache['reviews']) ? count($cache['reviews']) : 0;
        $count = $googleCount;
        $updated = isset($cache['updated_at']) ? (int) $cache['updated_at'] : 0;
        $nextCronTs = wp_next_scheduled(self::CRON_HOOK);
        $settingsUpdated = filter_input(INPUT_GET, 'settings-updated', FILTER_UNSAFE_RAW);
        $isSettingsUpdated = is_string($settingsUpdated) && $settingsUpdated === 'true';
        $wizardCompleted = isset($settings['wizard_completed']) && (int) $settings['wizard_completed'] === 1;

        $wizardReset = sanitize_key((string) filter_input(INPUT_GET, 'wizard_reset', FILTER_UNSAFE_RAW)) === '1';

        $activeTabNonce = sanitize_text_field((string) filter_input(INPUT_GET, '_wpnonce', FILTER_UNSAFE_RAW));
        $activeTab = sanitize_key((string) filter_input(INPUT_GET, 'tab', FILTER_UNSAFE_RAW));
        if ($activeTab === '') {
            $activeTab = 'general';
        }
        if ($activeTab !== 'general' && ! wp_verify_nonce($activeTabNonce, 'dsbrsl_admin_tab')) {
            $activeTab = 'general';
        }
        if (! in_array($activeTab, ['general', 'google'], true)) {
            $activeTab = 'general';
        }
        $rowStyleGeneral = $activeTab === 'general' ? '' : 'display:none;';
        $rowStyleGoogle = $activeTab === 'google' ? '' : 'display:none;';
        $tabGeneralUrl = wp_nonce_url(add_query_arg([
            'page' => 'dsbrsl-dope-studio-business-reviews-slider-lite',
            'tab'  => 'general',
        ], admin_url('admin.php')), 'dsbrsl_admin_tab');
        $tabGoogleUrl = wp_nonce_url(add_query_arg([
            'page' => 'dsbrsl-dope-studio-business-reviews-slider-lite',
            'tab'  => 'google',
        ], admin_url('admin.php')), 'dsbrsl_admin_tab');
        $runWizardUrl = wp_nonce_url(add_query_arg([
            'page' => 'dsbrsl-dope-studio-business-reviews-slider-lite',
            'run_wizard' => '1',
        ], admin_url('admin.php')), 'dsbrsl_run_wizard');
        $skipWizardUrl = wp_nonce_url(add_query_arg([
            'page' => 'dsbrsl-dope-studio-business-reviews-slider-lite',
            'skip_wizard' => '1',
        ], admin_url('admin.php')), 'dsbrsl_skip_wizard');
        ?>
        <div class="wrap grs-wrap" data-settings-updated="<?php echo $isSettingsUpdated ? '1' : '0'; ?>" data-wizard-reset="<?php echo $wizardReset ? '1' : '0'; ?>">
            <h1><?php esc_html_e('Dope Studio Business Reviews Slider Lite', 'dope-studio-business-reviews-slider-lite'); ?></h1>
            <p><?php esc_html_e('Let’s set everything up in a few simple steps. Friendly, fast, and beginner-safe.', 'dope-studio-business-reviews-slider-lite'); ?></p>

            <div class="grs-cards">
                <div class="grs-card">
                    <?php if (! $wizardCompleted) : ?>
                    <div class="grs-wizard-header">
                        <div class="grs-wizard-kicker"><?php esc_html_e('Setup wizard', 'dope-studio-business-reviews-slider-lite'); ?></div>
                        <h2><?php esc_html_e('Business Reviews Lite onboarding', 'dope-studio-business-reviews-slider-lite'); ?></h2>
                        <p class="description"><?php esc_html_e('Answer each question and we will configure everything for you.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                        <p><a class="button button-secondary" href="<?php echo esc_url($skipWizardUrl); ?>"><?php esc_html_e('Skip wizard', 'dope-studio-business-reviews-slider-lite'); ?></a></p>
                        <div class="grs-wizard-progress">
                            <div class="grs-wizard-progress-bar" id="grs-wizard-progress-bar"></div>
                        </div>
                        <p class="grs-wizard-progress-text" id="grs-wizard-progress-text"></p>
                    </div>

                    <form method="post" action="options.php" id="grs-settings-form">
                        <?php settings_fields('dsbrsl_settings_group'); ?>
                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[enable_google]" value="1" />
                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_fetch_scope]" value="enabled" />
                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[wizard_completed]" value="1" />

                        <section class="grs-wizard-step" data-step="1">
                            <h3><?php esc_html_e('Step 1: Connect your Apify account', 'dope-studio-business-reviews-slider-lite'); ?></h3>
                            <p class="description"><?php esc_html_e('Do you already have an Apify token? Paste it below to unlock fetching.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            <div class="grs-field">
                                <label for="grs_token"><?php esc_html_e('Apify account token', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <input id="grs_token" type="password" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[token]" class="regular-text" value="<?php echo esc_attr($settings['token'] ?? ''); ?>" placeholder="apify_api_..." required />
                                <p class="description"><?php esc_html_e('Apify has a generous free tier enough for most websites. Register now no credit card required.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                <p class="description">
                                    <a class="button" href="<?php echo esc_url('https://console.apify.com/sign-up'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Create Apify account', 'dope-studio-business-reviews-slider-lite'); ?></a>
                                    &nbsp;
                                    <a class="button" href="<?php echo esc_url('https://console.apify.com/settings/integrations'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get token', 'dope-studio-business-reviews-slider-lite'); ?></a>
                                </p>
                                <p class="grs-status" id="grs-step1-status"></p>
                            </div>
                        </section>

                        <section class="grs-wizard-step" data-step="2">
                            <h3><?php esc_html_e('Step 2: Choose your Google place and fetch reviews', 'dope-studio-business-reviews-slider-lite'); ?></h3>
                            <p class="description"><?php esc_html_e('What Google Maps URL should we use? Open Google Maps, search for your company, click on your business profile, and copy the URL from your browser.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            <p class="description"><a class="button" href="<?php echo esc_url('https://maps.google.com'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open Google Maps', 'dope-studio-business-reviews-slider-lite'); ?></a></p>

                            <div class="grs-field">
                                <label for="grs_place_url"><?php esc_html_e('Google Maps URL', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <input id="grs_place_url" type="url" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[place_url]" class="regular-text" value="<?php echo esc_attr($settings['place_url'] ?? ''); ?>" placeholder="https://www.google.com/maps/place/..." required />
                                <p class="description"><?php esc_html_e('Paste the share URL from Google Maps for your business.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field">
                                <label for="grs_max_reviews"><?php esc_html_e('How many reviews should we fetch?', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <input id="grs_max_reviews" type="number" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[max_reviews]" value="<?php echo esc_attr((string) ((int) ($settings['max_reviews'] ?? 0) <= 0 ? '' : (int) ($settings['max_reviews'] ?? 0))); ?>" min="1" max="500" placeholder="<?php esc_attr_e('Leave empty for all', 'dope-studio-business-reviews-slider-lite'); ?>" />
                                <p class="description"><?php esc_html_e('For first setup, fetching all reviews is recommended. Later, for cron, use 1-5.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field">
                                <label for="grs_language"><?php esc_html_e('Language', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <input id="grs_language" type="text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[language]" value="<?php echo esc_attr($settings['language'] ?? 'en'); ?>" class="small-text" />
                            </div>

                            <details class="grs-advanced-toggle">
                                <summary><?php esc_html_e('Optional advanced source settings', 'dope-studio-business-reviews-slider-lite'); ?></summary>
                                <div class="grs-field">
                                    <label for="grs_place_id"><?php esc_html_e('Place ID (optional fallback)', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                    <input id="grs_place_id" type="text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[place_id]" class="regular-text" value="<?php echo esc_attr($settings['place_id'] ?? ''); ?>" placeholder="ChIJ..." />
                                </div>
                                <div class="grs-field">
                                    <label for="grs_google_places_api_key"><?php esc_html_e('Google Places API key (optional)', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                    <input id="grs_google_places_api_key" type="password" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[google_places_api_key]" class="regular-text" value="<?php echo esc_attr($settings['google_places_api_key'] ?? ''); ?>" placeholder="AIza..." />
                                </div>
                                <div class="grs-field">
                                    <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[use_places_api_summary]" value="0" />
                                    <label for="grs_use_places_api_summary">
                                        <input id="grs_use_places_api_summary" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[use_places_api_summary]" value="1" <?php checked((int) ($settings['use_places_api_summary'] ?? 0), 1); ?> />
                                        <?php esc_html_e('Use Places API totals for top summary (requires Place ID + API key)', 'dope-studio-business-reviews-slider-lite'); ?>
                                    </label>
                                </div>
                            </details>

                            <p class="grs-status" id="grs-step2-fetch-status"></p>
                            <div class="grs-fetch-confirm" id="grs-fetch-confirm" style="display:none; gap:8px;">
                                <button type="button" class="button" id="grs-fetch-retry" style="display:none;"><?php esc_html_e('Try again', 'dope-studio-business-reviews-slider-lite'); ?></button>
                            </div>
                        </section>

                        <section class="grs-wizard-step" data-step="3">
                            <h3><?php esc_html_e('Step 3: Configure your slider style and behavior', 'dope-studio-business-reviews-slider-lite'); ?></h3>
                            <p class="description"><?php esc_html_e('Set up your slider appearance.', 'dope-studio-business-reviews-slider-lite'); ?></p>

                            <div class="grs-field">
                                <label for="grs_show_no_comment">
                                    <input id="grs_show_no_comment" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[show_no_comment]" value="1" <?php checked((int) ($settings['show_no_comment'] ?? 0), 1); ?> />
                                    <?php esc_html_e('Hide reviews without comment?', 'dope-studio-business-reviews-slider-lite'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Turn this on to show only reviews that contain written text. Ratings-only reviews will be hidden.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field">
                                <label for="grs_show_summary_default">
                                    <input id="grs_show_summary_default" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[show_summary_default]" value="1" <?php checked((int) ($settings['show_summary_default'] ?? 1), 1); ?> />
                                    <?php esc_html_e('Show top summary block?', 'dope-studio-business-reviews-slider-lite'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('The top summary block is the header area above the slider with overall stars and total review count.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field grs-summary-dependent">
                                <label for="grs_review_count_mode"><?php esc_html_e('Header review count source', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <select id="grs_review_count_mode" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[review_count_mode]">
                                    <option value="fetched" <?php selected(($settings['review_count_mode'] ?? 'fetched'), 'fetched'); ?>><?php esc_html_e('Use fetched reviews count', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                    <option value="custom" <?php selected(($settings['review_count_mode'] ?? ''), 'custom'); ?>><?php esc_html_e('Use custom count', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Choose whether the summary should show the real number of fetched reviews, or a number you set manually.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field grs-summary-dependent" id="grs-custom-review-count-wrap">
                                <label for="grs_custom_review_count"><?php esc_html_e('Custom review count', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <input id="grs_custom_review_count" type="number" min="0" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[custom_review_count]" value="<?php echo esc_attr((string) ($settings['custom_review_count'] ?? 0)); ?>" />
                                <p class="description"><?php esc_html_e('Used only when Header review count source is set to custom.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field grs-summary-dependent">
                                <label for="grs_rating_mode_default"><?php esc_html_e('Star rating source', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <select id="grs_rating_mode_default" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[rating_mode_default]">
                                    <option value="auto" <?php selected(($settings['rating_mode_default'] ?? 'auto'), 'auto'); ?>><?php esc_html_e('Auto (from fetched reviews)', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                    <option value="manual" <?php selected(($settings['rating_mode_default'] ?? ''), 'manual'); ?>><?php esc_html_e('Manual', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Auto calculates stars from fetched reviews and can differ from your public Google profile rating. Use Manual if you need an exact value.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field grs-summary-dependent" id="grs-manual-rating-wrap">
                                <label for="grs_manual_rating_default"><?php esc_html_e('Manual star rating (0-5)', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <input id="grs_manual_rating_default" type="number" min="0" max="5" step="0.1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[manual_rating_default]" value="<?php echo esc_attr((string) ($settings['manual_rating_default'] ?? 5)); ?>" />
                                <p class="description"><?php esc_html_e('This value is used only when Star rating source is set to Manual.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field">
                                <label for="grs_display_limit_default"><?php esc_html_e('How many reviews to display?', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <input id="grs_display_limit_default" type="number" min="6" max="500" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[display_limit_default]" value="<?php echo esc_attr((string) (((int) ($settings['display_limit_default_touched'] ?? 0) === 1 && (int) ($settings['display_limit_default'] ?? 0) > 0) ? (int) ($settings['display_limit_default'] ?? 0) : '')); ?>" placeholder="<?php esc_attr_e('Leave empty for all', 'dope-studio-business-reviews-slider-lite'); ?>" />
                                <p class="description"><?php esc_html_e('Frontend display only: this controls how many reviews are shown on your site from the fetched pool.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field">
                                <label for="grs_show_read_on_google_default">
                                    <input id="grs_show_read_on_google_default" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[show_read_on_google_default]" value="1" <?php checked((int) ($settings['show_read_on_google_default'] ?? 1), 1); ?> />
                                    <?php esc_html_e('Show review link button?', 'dope-studio-business-reviews-slider-lite'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Shows a button on each card that opens the original review on Google.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field">
                                <label for="grs_show_dots_default">
                                    <input id="grs_show_dots_default" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[show_dots_default]" value="1" <?php checked((int) ($settings['show_dots_default'] ?? 1), 1); ?> />
                                    <?php esc_html_e('Show progress bar?', 'dope-studio-business-reviews-slider-lite'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Displays a visual progress indicator under the slider.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field">
                                <label for="grs_loop_infinite_default">
                                    <input id="grs_loop_infinite_default" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[loop_infinite_default]" value="1" <?php checked((int) ($settings['loop_infinite_default'] ?? 1), 1); ?> />
                                    <?php esc_html_e('Use infinite loop?', 'dope-studio-business-reviews-slider-lite'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('When enabled, the slider loops forever and wraps back to the beginning.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field">
                                <label for="grs_autoplay_default">
                                    <input id="grs_autoplay_default" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[autoplay_default]" value="1" <?php checked((int) ($settings['autoplay_default'] ?? 1), 1); ?> />
                                    <?php esc_html_e('Enable autoplay?', 'dope-studio-business-reviews-slider-lite'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Automatically moves to the next review card after a delay.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field" id="grs-autoplay-interval-wrap">
                                <label for="grs_autoplay_interval_default"><?php esc_html_e('Autoplay interval (ms)', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <input id="grs_autoplay_interval_default" type="number" min="1500" max="20000" step="100" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[autoplay_interval_default]" value="<?php echo esc_attr((string) ($settings['autoplay_interval_default'] ?? 5500)); ?>" />
                                <p class="description"><?php esc_html_e('How long each review stays visible before sliding to the next one.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <div class="grs-field">
                                <label for="grs_theme"><?php esc_html_e('Default slider theme', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <select id="grs_theme" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[theme]">
                                    <option value="dark" <?php selected(($settings['theme'] ?? 'dark'), 'dark'); ?>><?php esc_html_e('Dark', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                    <option value="light" <?php selected(($settings['theme'] ?? ''), 'light'); ?>><?php esc_html_e('Light', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Choose the visual style used by default in the shortcode output.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <details class="grs-advanced-toggle">
                                <summary><?php esc_html_e('Optional advanced slider settings', 'dope-studio-business-reviews-slider-lite'); ?></summary>
                                <div class="grs-field">
                                    <label for="grs_title_default"><?php esc_html_e('Slider heading text', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                    <input id="grs_title_default" type="text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[title_default]" class="regular-text" value="<?php echo esc_attr((string) ($settings['title_default'] ?? 'Latest reviews')); ?>" />
                                </div>
                                <div class="grs-field">
                                    <label for="grs_min_rating_default"><?php esc_html_e('Default rating filter', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                    <select id="grs_min_rating_default" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[min_rating_default]">
                                        <option value="0" <?php selected((int) ($settings['min_rating_default'] ?? 0), 0); ?>><?php esc_html_e('All ratings', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                        <option value="5" <?php selected((int) ($settings['min_rating_default'] ?? 0), 5); ?>><?php esc_html_e('Only 5 stars', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                        <option value="4" <?php selected((int) ($settings['min_rating_default'] ?? 0), 4); ?>><?php esc_html_e('4 stars and above', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                        <option value="3" <?php selected((int) ($settings['min_rating_default'] ?? 0), 3); ?>><?php esc_html_e('3 stars and above', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                        <option value="2" <?php selected((int) ($settings['min_rating_default'] ?? 0), 2); ?>><?php esc_html_e('2 stars and above', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                    </select>
                                </div>
                                <div class="grs-field">
                                    <label for="grs_swipe_default">
                                        <input id="grs_swipe_default" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[swipe_default]" value="1" <?php checked((int) ($settings['swipe_default'] ?? 1), 1); ?> />
                                        <?php esc_html_e('Enable touch swipe?', 'dope-studio-business-reviews-slider-lite'); ?>
                                    </label>
                                </div>
                                <div class="grs-field grs-inline-grid">
                                    <div>
                                        <label for="grs_slides_mobile_default"><?php esc_html_e('Cards on mobile', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                        <input id="grs_slides_mobile_default" type="number" min="1" max="6" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[slides_mobile_default]" value="<?php echo esc_attr((string) ($settings['slides_mobile_default'] ?? 1)); ?>" />
                                    </div>
                                    <div>
                                        <label for="grs_slides_tablet_default"><?php esc_html_e('Cards on tablet', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                        <input id="grs_slides_tablet_default" type="number" min="1" max="6" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[slides_tablet_default]" value="<?php echo esc_attr((string) ($settings['slides_tablet_default'] ?? 2)); ?>" />
                                    </div>
                                    <div>
                                        <label for="grs_slides_desktop_default"><?php esc_html_e('Cards on desktop', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                        <input id="grs_slides_desktop_default" type="number" min="1" max="6" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[slides_desktop_default]" value="<?php echo esc_attr((string) ($settings['slides_desktop_default'] ?? 3)); ?>" />
                                    </div>
                                </div>
                                <div class="grs-field">
                                    <label for="grs_schema_review_limit_default"><?php esc_html_e('Schema reviews (JSON-LD)', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                    <input id="grs_schema_review_limit_default" type="number" min="1" max="100" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[schema_review_limit_default]" value="<?php echo esc_attr((string) (((int) ($settings['schema_review_limit_default_touched'] ?? 0) === 1 && (int) ($settings['schema_review_limit_default'] ?? 0) > 0) ? (int) ($settings['schema_review_limit_default'] ?? 0) : '')); ?>" placeholder="<?php esc_attr_e('Leave empty to match slider output', 'dope-studio-business-reviews-slider-lite'); ?>" />
                                </div>
                            </details>
                        </section>

                        <section class="grs-wizard-step" data-step="4">
                            <h3><?php esc_html_e('Step 4: Do you want to set up cron auto-fetch?', 'dope-studio-business-reviews-slider-lite'); ?></h3>
                            <p class="description"><?php esc_html_e('Should the plugin refresh reviews automatically on a schedule?', 'dope-studio-business-reviews-slider-lite'); ?></p>

                            <div class="grs-field">
                                <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_enabled]" value="0" />
                                <label for="grs_cron_enabled">
                                    <input id="grs_cron_enabled" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_enabled]" value="1" <?php checked((int) ($settings['cron_enabled'] ?? 0), 1); ?> />
                                    <?php esc_html_e('Yes, enable cron auto-fetch', 'dope-studio-business-reviews-slider-lite'); ?>
                                </label>
                            </div>

                            <div class="grs-field grs-cron-dependent">
                                <label for="grs_cron_frequency"><?php esc_html_e('How often should we fetch?', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <select id="grs_cron_frequency" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_frequency]">
                                    <option value="twicedaily" <?php selected(($settings['cron_frequency'] ?? 'daily'), 'twicedaily'); ?>><?php esc_html_e('Every 12 hours', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                    <option value="daily" <?php selected(($settings['cron_frequency'] ?? 'daily'), 'daily'); ?>><?php esc_html_e('Daily', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                    <option value="dsbrsl_every_2_days" <?php selected(($settings['cron_frequency'] ?? 'daily'), 'dsbrsl_every_2_days'); ?>><?php esc_html_e('Every 2 days', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                    <option value="weekly" <?php selected(($settings['cron_frequency'] ?? 'daily'), 'weekly'); ?>><?php esc_html_e('Weekly (recommended)', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                    <option value="dsbrsl_monthly" <?php selected(($settings['cron_frequency'] ?? 'daily'), 'dsbrsl_monthly'); ?>><?php esc_html_e('Monthly', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                </select>
                            </div>

                            <div class="grs-field grs-cron-dependent">
                                <label for="grs_cron_time"><?php esc_html_e('Start time', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                <input id="grs_cron_time" type="time" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_time]" value="<?php echo esc_attr((string) ($settings['cron_time'] ?? '03:00')); ?>" />
                                <p class="description"><?php esc_html_e('Use your site local time.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>

                            <details class="grs-advanced-toggle grs-cron-dependent">
                                <summary><?php esc_html_e('Advanced cron setting', 'dope-studio-business-reviews-slider-lite'); ?></summary>
                                <div class="grs-field">
                                    <label for="grs_cron_max_reviews"><?php esc_html_e('How many reviews should cron fetch each run?', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                    <input id="grs_cron_max_reviews" type="number" min="1" max="50" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_max_reviews]" value="<?php echo esc_attr((string) ((int) ($settings['cron_max_reviews'] ?? 1) <= 0 ? 1 : (int) ($settings['cron_max_reviews'] ?? 1))); ?>" />
                                    <p class="description"><?php esc_html_e('Default is 1. This overrides the initial fetch amount during cron runs so daily sync can stay lightweight and catch new reviews.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                </div>
                            </details>

                            <div class="grs-field">
                                <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[delete_on_uninstall]" value="0" />
                                <label for="grs_delete_on_uninstall">
                                    <input id="grs_delete_on_uninstall" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[delete_on_uninstall]" value="1" <?php checked((int) ($settings['delete_on_uninstall'] ?? 0), 1); ?> />
                                    <?php esc_html_e('Delete plugin data on uninstall?', 'dope-studio-business-reviews-slider-lite'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Keep this off if you might upgrade to Pro and want to keep data.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            </div>
                        </section>

                        <section class="grs-wizard-step" data-step="5">
                            <h3><?php esc_html_e('Final step: Save and copy your shortcode', 'dope-studio-business-reviews-slider-lite'); ?></h3>
                            <p class="description"><?php esc_html_e('You are done. Save your choices, then copy shortcode and paste it on any page/post.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                            <div class="grs-shortcode-box">
                                <code id="grs-shortcode-text">[dsbrsl_google_reviews_slider]</code>
                                <button type="button" class="button" id="grs-copy-shortcode"><?php esc_html_e('Copy shortcode', 'dope-studio-business-reviews-slider-lite'); ?></button>
                                <span class="grs-status" id="grs-copy-status"></span>
                            </div>
                        </section>

                        <div class="grs-wizard-nav">
                            <button type="button" class="button" id="grs-wizard-prev"><?php esc_html_e('Back', 'dope-studio-business-reviews-slider-lite'); ?></button>
                            <div style="display:flex; gap:8px;">
                                <div id="grs-wizard-save-wrap">
                                    <button type="submit" class="button button-primary" id="grs-wizard-save"><?php esc_html_e('Save and finish', 'dope-studio-business-reviews-slider-lite'); ?></button>
                                </div>
                                <button type="button" class="button button-primary" id="grs-wizard-next"><?php esc_html_e('Next', 'dope-studio-business-reviews-slider-lite'); ?></button>
                            </div>
                        </div>
                    </form>
                    <?php else : ?>
                    <h2><?php esc_html_e('Settings', 'dope-studio-business-reviews-slider-lite'); ?></h2>
                    <p class="description"><?php esc_html_e('Setup is completed. You can fine-tune all settings below or rerun the wizard anytime.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                    <p><a class="button" href="<?php echo esc_url($runWizardUrl); ?>"><?php esc_html_e('Run setup wizard again', 'dope-studio-business-reviews-slider-lite'); ?></a></p>
                    <nav class="nav-tab-wrapper" style="margin-bottom: 14px;">
                        <a href="<?php echo esc_url($tabGeneralUrl); ?>" class="nav-tab <?php echo $activeTab === 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General settings', 'dope-studio-business-reviews-slider-lite'); ?></a>
                        <a href="<?php echo esc_url($tabGoogleUrl); ?>" class="nav-tab <?php echo $activeTab === 'google' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Google settings', 'dope-studio-business-reviews-slider-lite'); ?></a>
                    </nav>

                    <form method="post" action="options.php" id="grs-settings-form-classic" data-active-tab="<?php echo esc_attr($activeTab); ?>">
                        <?php settings_fields('dsbrsl_settings_group'); ?>
                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[enable_google]" value="1" />
                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_fetch_scope]" value="enabled" />
                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[wizard_completed]" value="1" />
                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_max_reviews]" value="<?php echo esc_attr((string) ((int) ($settings['cron_max_reviews'] ?? 1) <= 0 ? 1 : (int) ($settings['cron_max_reviews'] ?? 1))); ?>" />
                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[title_default]" value="<?php echo esc_attr((string) ($settings['title_default'] ?? 'Latest reviews')); ?>" />
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr<?php echo $rowStyleGeneral !== '' ? ' style="' . esc_attr($rowStyleGeneral) . '"' : ''; ?>>
                                    <th scope="row">
                                        <label for="grs_token_classic"><?php esc_html_e('Apify token', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                        <details class="grs-help-inline">
                                            <summary aria-label="<?php esc_attr_e('How to get Apify token', 'dope-studio-business-reviews-slider-lite'); ?>">?</summary>
                                            <div class="grs-help-content">
                                                <strong><?php esc_html_e('How to get it:', 'dope-studio-business-reviews-slider-lite'); ?></strong>
                                                <ol>
                                                    <li><?php esc_html_e('Log in to Apify.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                    <li><?php esc_html_e('Open Settings → Integrations/API.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                    <li><?php esc_html_e('Create/copy your API token and paste it here.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                </ol>
                                            </div>
                                        </details>
                                    </th>
                                    <td>
                                        <input id="grs_token_classic" type="password" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[token]" class="regular-text" value="<?php echo esc_attr($settings['token'] ?? ''); ?>" placeholder="apify_api_..." />
                                        <p class="description"><?php esc_html_e('Apify API is used to fetch the reviews. It has a pretty generous free tier 1000 reviews+. Just register an account, create/copy your API token, and paste it here.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                        <p class="description">
                                            <a href="<?php echo esc_url('https://apify.com'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Apify', 'dope-studio-business-reviews-slider-lite'); ?></a>
                                            &nbsp;|&nbsp;
                                            <a href="<?php echo esc_url('https://console.apify.com/settings/integrations'); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get your API key here', 'dope-studio-business-reviews-slider-lite'); ?></a>
                                        </p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGeneral !== '' ? ' style="' . esc_attr($rowStyleGeneral) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Platforms to enable', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <label for="grs_enable_google_classic" style="display:block; margin-bottom:6px;">
                                            <input id="grs_enable_google_classic" type="checkbox" checked="checked" />
                                            <?php esc_html_e('Enable Google reviews', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGeneral !== '' ? ' style="' . esc_attr($rowStyleGeneral) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Auto-fetch cron', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_enabled]" value="0" />
                                        <label for="grs_cron_enabled_classic">
                                            <input id="grs_cron_enabled_classic" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_enabled]" value="1" <?php checked((int) ($settings['cron_enabled'] ?? 0), 1); ?> />
                                            <?php esc_html_e('Enable scheduled automatic fetch', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGeneral !== '' ? ' style="' . esc_attr($rowStyleGeneral) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_cron_frequency_classic"><?php esc_html_e('Cron frequency', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <select id="grs_cron_frequency_classic" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_frequency]">
                                            <option value="twicedaily" <?php selected(($settings['cron_frequency'] ?? 'weekly'), 'twicedaily'); ?>><?php esc_html_e('Every 12 hours', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="daily" <?php selected(($settings['cron_frequency'] ?? 'weekly'), 'daily'); ?>><?php esc_html_e('Daily', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="dsbrsl_every_2_days" <?php selected(($settings['cron_frequency'] ?? 'weekly'), 'dsbrsl_every_2_days'); ?>><?php esc_html_e('Every 2 days', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="dsbrsl_monthly" <?php selected(($settings['cron_frequency'] ?? 'weekly'), 'dsbrsl_monthly'); ?>><?php esc_html_e('Once per month', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="weekly" <?php selected(($settings['cron_frequency'] ?? 'weekly'), 'weekly'); ?>><?php esc_html_e('Weekly (recommended)', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGeneral !== '' ? ' style="' . esc_attr($rowStyleGeneral) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_cron_time_classic"><?php esc_html_e('Cron start time', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <input id="grs_cron_time_classic" type="time" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[cron_time]" value="<?php echo esc_attr((string) ($settings['cron_time'] ?? '03:00')); ?>" />
                                        <p class="description"><?php esc_html_e('Site local time. The event repeats based on selected frequency.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                        <p class="description" style="color:#b32d2e;"><?php esc_html_e('Tip: Avoid running daily full fetches of all reviews. For daily cron, run one full fetch first with Max reviews empty in the Google platform tab, then set Max reviews to 1-5 there. Existing stored reviews are kept and only new unique reviews are added, which helps avoid exhausting your Apify monthly quota.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGeneral !== '' ? ' style="' . esc_attr($rowStyleGeneral) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Data cleanup', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[delete_on_uninstall]" value="0" />
                                        <label for="grs_delete_on_uninstall_classic">
                                            <input id="grs_delete_on_uninstall_classic" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[delete_on_uninstall]" value="1" <?php checked((int) ($settings['delete_on_uninstall'] ?? 0), 1); ?> />
                                            <?php esc_html_e('Delete all plugin data when uninstalling the plugin', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('Default is OFF (unchecked). Keep this OFF if you plan to migrate to Pro, so your Lite settings and cached reviews remain available for import.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                        <p class="description"><?php esc_html_e('If enabled, Lite settings and cached reviews will be removed when you uninstall the plugin.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row" colspan="2"><h3 style="margin:10px 0 0;"><?php esc_html_e('Google API settings', 'dope-studio-business-reviews-slider-lite'); ?></h3></th>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row">
                                        <label for="grs_place_id_classic"><?php esc_html_e('Place ID', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                        <details class="grs-help-inline">
                                            <summary aria-label="<?php esc_attr_e('How to get Place ID', 'dope-studio-business-reviews-slider-lite'); ?>">?</summary>
                                            <div class="grs-help-content">
                                                <strong><?php esc_html_e('How to get it:', 'dope-studio-business-reviews-slider-lite'); ?></strong>
                                                <ol>
                                                    <li><?php esc_html_e('Open your business in Google Maps.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                    <li><?php esc_html_e('Use Google Place ID Finder tool and paste the Maps URL.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                    <li><?php esc_html_e('Copy the Place ID (starts with ChIJ...).', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                </ol>
                                            </div>
                                        </details>
                                    </th>
                                    <td>
                                        <input id="grs_place_id_classic" type="text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[place_id]" class="regular-text" value="<?php echo esc_attr((string) ($settings['place_id'] ?? '')); ?>" placeholder="ChIJ..." />
                                        <p class="description"><?php esc_html_e('Use either Place ID or Google Maps URL (one is enough).', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row">
                                        <label for="grs_place_url_classic"><?php esc_html_e('Google Maps URL', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                        <details class="grs-help-inline">
                                            <summary aria-label="<?php esc_attr_e('How to get Google Maps URL', 'dope-studio-business-reviews-slider-lite'); ?>">?</summary>
                                            <div class="grs-help-content">
                                                <strong><?php esc_html_e('How to get it:', 'dope-studio-business-reviews-slider-lite'); ?></strong>
                                                <ol>
                                                    <li><?php esc_html_e('Open the business in Google Maps.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                    <li><?php esc_html_e('Click Share.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                    <li><?php esc_html_e('Copy the place URL and paste it here.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                </ol>
                                            </div>
                                        </details>
                                    </th>
                                    <td>
                                        <input id="grs_place_url_classic" type="url" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[place_url]" class="regular-text" value="<?php echo esc_attr($settings['place_url'] ?? ''); ?>" placeholder="https://www.google.com/maps/place/..." />
                                        <p class="description"><?php esc_html_e('If Google Maps URL is provided, Place ID is optional.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row">
                                        <label for="grs_google_places_api_key_classic"><?php esc_html_e('Google Places API key (optional)', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                        <details class="grs-help-inline">
                                            <summary aria-label="<?php esc_attr_e('How to get Google Places API key', 'dope-studio-business-reviews-slider-lite'); ?>">?</summary>
                                            <div class="grs-help-content">
                                                <strong><?php esc_html_e('How to get it:', 'dope-studio-business-reviews-slider-lite'); ?></strong>
                                                <ol>
                                                    <li><?php esc_html_e('Open Google Cloud Console and create/select a project.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                    <li><?php esc_html_e('Enable Places API.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                    <li><?php esc_html_e('Create an API key (APIs & Services → Credentials) and paste it here.', 'dope-studio-business-reviews-slider-lite'); ?></li>
                                                </ol>
                                            </div>
                                        </details>
                                    </th>
                                    <td><input id="grs_google_places_api_key_classic" type="password" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[google_places_api_key]" class="regular-text" value="<?php echo esc_attr((string) ($settings['google_places_api_key'] ?? '')); ?>" placeholder="AIza..." /></td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Places API summary (optional)', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <input type="hidden" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[use_places_api_summary]" value="0" />
                                        <label for="grs_use_places_api_summary_classic">
                                            <input id="grs_use_places_api_summary_classic" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[use_places_api_summary]" value="1" <?php checked((int) ($settings['use_places_api_summary'] ?? 0), 1); ?> />
                                            <?php esc_html_e('Use Places API rating and total reviews in header (requires Place ID + API key).', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_max_reviews_classic"><?php esc_html_e('Max reviews', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <input id="grs_max_reviews_classic" type="number" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[max_reviews]" value="<?php echo esc_attr((string) ((int) ($settings['max_reviews'] ?? 0) <= 0 ? '' : (int) ($settings['max_reviews'] ?? 0))); ?>" min="1" max="500" placeholder="<?php esc_attr_e('Leave empty for all', 'dope-studio-business-reviews-slider-lite'); ?>" />
                                        <p class="description"><?php esc_html_e('Leave empty to fetch all available reviews (can consume more Apify credits).', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                        <p class="description"><?php esc_html_e('Recommended workflow: fetch all reviews once initially, then set this to 1-5 for daily cron. Existing stored reviews are kept and only new unique reviews are added. This keeps Apify token usage low and helps avoid exhausting your monthly quota.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_language_classic"><?php esc_html_e('Language', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td><input id="grs_language_classic" type="text" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[language]" value="<?php echo esc_attr((string) ($settings['language'] ?? 'en')); ?>" class="small-text" /></td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row" colspan="2"><h3 style="margin:10px 0 0;"><?php esc_html_e('Google slider settings', 'dope-studio-business-reviews-slider-lite'); ?></h3></th>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_theme_classic"><?php esc_html_e('Default slider theme', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <select id="grs_theme_classic" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[theme]">
                                            <option value="dark" <?php selected(($settings['theme'] ?? 'dark'), 'dark'); ?>><?php esc_html_e('Dark', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="light" <?php selected(($settings['theme'] ?? ''), 'light'); ?>><?php esc_html_e('Light', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Reviews without comment', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <label for="grs_show_no_comment_classic">
                                            <input id="grs_show_no_comment_classic" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[show_no_comment]" value="1" <?php checked((int) ($settings['show_no_comment'] ?? 0), 1); ?> />
                                            <?php esc_html_e('Hide ratings-only reviews (no text comment)', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Autoplay', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <label for="grs_autoplay_default_classic">
                                            <input id="grs_autoplay_default_classic" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[autoplay_default]" value="1" <?php checked((int) ($settings['autoplay_default'] ?? 1), 1); ?> />
                                            <?php esc_html_e('Enable automatic sliding by default', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_autoplay_interval_default_classic"><?php esc_html_e('Autoplay interval (ms)', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <input id="grs_autoplay_interval_default_classic" type="number" min="1500" max="20000" step="100" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[autoplay_interval_default]" value="<?php echo esc_attr((string) ($settings['autoplay_interval_default'] ?? 5500)); ?>" />
                                        <p class="description"><?php esc_html_e('Default autoplay speed for the widget.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Infinite loop', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <label for="grs_loop_infinite_default_classic">
                                            <input id="grs_loop_infinite_default_classic" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[loop_infinite_default]" value="1" <?php checked((int) ($settings['loop_infinite_default'] ?? 1), 1); ?> />
                                            <?php esc_html_e('Loop slides infinitely and fill incomplete last page with next reviews', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Progress bar', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <label for="grs_show_dots_default_classic">
                                            <input id="grs_show_dots_default_classic" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[show_dots_default]" value="1" <?php checked((int) ($settings['show_dots_default'] ?? 1), 1); ?> />
                                            <?php esc_html_e('Show slider progress bar', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Top summary block', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <label for="grs_show_summary_default_classic">
                                            <input id="grs_show_summary_default_classic" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[show_summary_default]" value="1" <?php checked((int) ($settings['show_summary_default'] ?? 1), 1); ?> />
                                            <?php esc_html_e('Show top block with overall rating information', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Review link button', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <label for="grs_show_read_on_google_default_classic">
                                            <input id="grs_show_read_on_google_default_classic" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[show_read_on_google_default]" value="1" <?php checked((int) ($settings['show_read_on_google_default'] ?? 1), 1); ?> />
                                            <?php esc_html_e('Show "Read on Google" button on cards', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_rating_mode_default_classic"><?php esc_html_e('Star rating source', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <select id="grs_rating_mode_default_classic" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[rating_mode_default]">
                                            <option value="auto" <?php selected(($settings['rating_mode_default'] ?? 'auto'), 'auto'); ?>><?php esc_html_e('Auto (from fetched reviews)', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="manual" <?php selected(($settings['rating_mode_default'] ?? ''), 'manual'); ?>><?php esc_html_e('Manual', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('Apify does not provide a dedicated business-wide star rating field. Auto mode calculates rating from fetched reviews and may differ from your public profile rating.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_manual_rating_default_classic"><?php esc_html_e('Star manual rating (0-5)', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <input id="grs_manual_rating_default_classic" type="number" min="0" max="5" step="0.1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[manual_rating_default]" value="<?php echo esc_attr((string) ($settings['manual_rating_default'] ?? 5)); ?>" />
                                        <p class="description"><?php esc_html_e('Used when Star rating source is set to Manual.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Swipe', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <label for="grs_swipe_default_classic">
                                            <input id="grs_swipe_default_classic" type="checkbox" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[swipe_default]" value="1" <?php checked((int) ($settings['swipe_default'] ?? 1), 1); ?> />
                                            <?php esc_html_e('Enable touch swipe on mobile/tablet', 'dope-studio-business-reviews-slider-lite'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><?php esc_html_e('Cards per view', 'dope-studio-business-reviews-slider-lite'); ?></th>
                                    <td>
                                        <label for="grs_slides_mobile_default_classic"><?php esc_html_e('Mobile', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                        <input id="grs_slides_mobile_default_classic" type="number" min="1" max="6" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[slides_mobile_default]" value="<?php echo esc_attr((string) ($settings['slides_mobile_default'] ?? 1)); ?>" />
                                        &nbsp;&nbsp;
                                        <label for="grs_slides_tablet_default_classic"><?php esc_html_e('Tablet', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                        <input id="grs_slides_tablet_default_classic" type="number" min="1" max="6" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[slides_tablet_default]" value="<?php echo esc_attr((string) ($settings['slides_tablet_default'] ?? 2)); ?>" />
                                        &nbsp;&nbsp;
                                        <label for="grs_slides_desktop_default_classic"><?php esc_html_e('Desktop', 'dope-studio-business-reviews-slider-lite'); ?></label>
                                        <input id="grs_slides_desktop_default_classic" type="number" min="1" max="6" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[slides_desktop_default]" value="<?php echo esc_attr((string) ($settings['slides_desktop_default'] ?? 3)); ?>" />
                                        <p class="description"><?php esc_html_e('How many review cards to show at once by device size.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_display_limit_default_classic"><?php esc_html_e('Reviews to display', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <input id="grs_display_limit_default_classic" type="number" min="6" max="500" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[display_limit_default]" value="<?php echo esc_attr((string) (((int) ($settings['display_limit_default_touched'] ?? 0) === 1 && (int) ($settings['display_limit_default'] ?? 0) > 0) ? (int) ($settings['display_limit_default'] ?? 0) : '')); ?>" placeholder="<?php esc_attr_e('Leave empty for all', 'dope-studio-business-reviews-slider-lite'); ?>" />
                                        <p class="description"><?php esc_html_e('Frontend only: limit shown reviews. Leave empty to show all fetched reviews. Minimum when set: 6. Applied after filters (no-comment/rating).', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_schema_review_limit_default_classic"><?php esc_html_e('Schema reviews (JSON-LD)', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <input id="grs_schema_review_limit_default_classic" type="number" min="1" max="100" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[schema_review_limit_default]" value="<?php echo esc_attr((string) (((int) ($settings['schema_review_limit_default_touched'] ?? 0) === 1 && (int) ($settings['schema_review_limit_default'] ?? 0) > 0) ? (int) ($settings['schema_review_limit_default'] ?? 0) : '')); ?>" placeholder="<?php esc_attr_e('Leave empty to match slider output', 'dope-studio-business-reviews-slider-lite'); ?>" />
                                        <p class="description"><?php esc_html_e('Leave empty to use exactly the reviews shown by the slider after filters/limits. Set a number only if you want fewer schema reviews than displayed.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_min_rating_default_classic"><?php esc_html_e('Default rating filter', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <select id="grs_min_rating_default_classic" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[min_rating_default]">
                                            <option value="0" <?php selected((int) ($settings['min_rating_default'] ?? 0), 0); ?>><?php esc_html_e('All ratings', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="5" <?php selected((int) ($settings['min_rating_default'] ?? 0), 5); ?>><?php esc_html_e('Only 5 stars', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="4" <?php selected((int) ($settings['min_rating_default'] ?? 0), 4); ?>><?php esc_html_e('4 stars and above', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="3" <?php selected((int) ($settings['min_rating_default'] ?? 0), 3); ?>><?php esc_html_e('3 stars and above', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="2" <?php selected((int) ($settings['min_rating_default'] ?? 0), 2); ?>><?php esc_html_e('2 stars and above', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_review_count_mode_classic"><?php esc_html_e('Header review count source', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <select id="grs_review_count_mode_classic" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[review_count_mode]">
                                            <option value="fetched" <?php selected(($settings['review_count_mode'] ?? 'fetched'), 'fetched'); ?>><?php esc_html_e('Use fetched reviews count', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                            <option value="custom" <?php selected(($settings['review_count_mode'] ?? ''), 'custom'); ?>><?php esc_html_e('Use custom count', 'dope-studio-business-reviews-slider-lite'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('The total reviews count.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                                <tr<?php echo $rowStyleGoogle !== '' ? ' style="' . esc_attr($rowStyleGoogle) . '"' : ''; ?>>
                                    <th scope="row"><label for="grs_custom_review_count_classic"><?php esc_html_e('Custom review count', 'dope-studio-business-reviews-slider-lite'); ?></label></th>
                                    <td>
                                        <input id="grs_custom_review_count_classic" type="number" min="0" step="1" name="<?php echo esc_attr(self::SETTINGS_OPTION); ?>[custom_review_count]" value="<?php echo esc_attr((string) ($settings['custom_review_count'] ?? 0)); ?>" />
                                        <p class="description"><?php esc_html_e('Used only when "Use custom count" is selected.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <?php submit_button(__('Save settings', 'dope-studio-business-reviews-slider-lite')); ?>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="grs-card grs-info">
                    <h2><?php esc_html_e('Data status', 'dope-studio-business-reviews-slider-lite'); ?></h2>
                    <p><strong><?php echo esc_html((string) $count); ?></strong> <?php esc_html_e('reviews stored in DB.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                    <p class="description"><?php
                        /* translators: %d: Number of Google reviews in cache. */
                        echo esc_html(sprintf(__('Google: %d', 'dope-studio-business-reviews-slider-lite'), (int) $googleCount));
                    ?></p>
                    <p>
                        <?php esc_html_e('Last update:', 'dope-studio-business-reviews-slider-lite'); ?>
                        <strong><?php echo $updated ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $updated)) : esc_html__('Never', 'dope-studio-business-reviews-slider-lite'); ?></strong>
                    </p>
                    <p>
                        <?php esc_html_e('Next scheduled fetch:', 'dope-studio-business-reviews-slider-lite'); ?>
                        <strong><?php echo $nextCronTs ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $nextCronTs)) : esc_html__('Not scheduled', 'dope-studio-business-reviews-slider-lite'); ?></strong>
                    </p>

                    <hr />

                    <h3><?php esc_html_e('Shortcode', 'dope-studio-business-reviews-slider-lite'); ?></h3>
                    <p><code>[dsbrsl_google_reviews_slider]</code></p>
                    <p><?php esc_html_e('Optional attributes:', 'dope-studio-business-reviews-slider-lite'); ?> <code>theme="dark|light" limit="0" autoplay="1" interval="5500" loop="0|1" show_dots="0|1" swipe="0|1" mobile="1-6" tablet="1-6" desktop="1-6" show_summary="0|1" show_read_on_google="0|1" rating_mode="auto|manual" manual_rating="0-5" min_rating="0|2|3|4|5" show_no_comment="1"</code></p>
                    <p class="description"><?php esc_html_e('These attributes are optional. If you configure settings in the admin panel, use the default shortcode without attributes and those settings will be applied automatically.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                    <p class="description"><?php esc_html_e('If you add attributes in the shortcode, they override the admin settings for that shortcode instance only. This lets you place different slider variations on different pages.', 'dope-studio-business-reviews-slider-lite'); ?></p>
                    

                    <hr />
                    <p><strong><?php esc_html_e('Need more platforms and features? Download Pro for free:', 'dope-studio-business-reviews-slider-lite'); ?></strong></p>
                    <p>
                        <a class="button button-secondary" href="<?php echo esc_url('https://products.dopestudio.co.uk/brs'); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Download Pro for free', 'dope-studio-business-reviews-slider-lite'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_fetch_reviews(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'dope-studio-business-reviews-slider-lite'),
            ], 403);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field((string) wp_unslash($_POST['nonce'])) : '';
        if (! wp_verify_nonce($nonce, self::AJAX_ACTION)) {
            wp_send_json_error([
                'message' => __('Invalid request.', 'dope-studio-business-reviews-slider-lite'),
            ], 400);
        }

        $settings = get_option(self::SETTINGS_OPTION, []);

        $draftSettingsRaw = isset($_POST['draft_settings']) ? wp_unslash((string) $_POST['draft_settings']) : '';
        if ($draftSettingsRaw !== '') {
            $draftSettings = json_decode($draftSettingsRaw, true);
            if (is_array($draftSettings)) {
                $draftToken = isset($draftSettings['token']) ? sanitize_text_field((string) $draftSettings['token']) : '';
                if ($draftToken !== '') {
                    $settings['token'] = $draftToken;
                }

                if (isset($draftSettings['place_url'])) {
                    $settings['place_url'] = esc_url_raw((string) $draftSettings['place_url']);
                }

                if (isset($draftSettings['place_id'])) {
                    $settings['place_id'] = sanitize_text_field((string) $draftSettings['place_id']);
                }

                if (isset($draftSettings['max_reviews'])) {
                    $maxReviewsRaw = trim((string) $draftSettings['max_reviews']);
                    if ($maxReviewsRaw === '') {
                        $settings['max_reviews'] = 0;
                    } else {
                        $settings['max_reviews'] = max(1, min(500, absint($maxReviewsRaw)));
                    }
                }

                if (isset($draftSettings['language'])) {
                    $settings['language'] = sanitize_text_field((string) $draftSettings['language']);
                }

                if (isset($draftSettings['google_places_api_key'])) {
                    $settings['google_places_api_key'] = sanitize_text_field((string) $draftSettings['google_places_api_key']);
                }

                if (isset($draftSettings['use_places_api_summary'])) {
                    $settings['use_places_api_summary'] = ((string) $draftSettings['use_places_api_summary'] === '1') ? 1 : 0;
                }
            }
        }

        $token = isset($settings['token']) ? trim((string) $settings['token']) : '';
        if ($token === '') {
            wp_send_json_error([
                'message' => __('Add your Apify token first.', 'dope-studio-business-reviews-slider-lite'),
            ], 400);
        }

        $result = $this->fetch_google_reviews($settings, $token);
        if (! $result['success']) {
            wp_send_json_error([
                'message' => isset($result['message']) ? (string) $result['message'] : __('Fetch failed.', 'dope-studio-business-reviews-slider-lite'),
            ], isset($result['status']) ? (int) $result['status'] : 500);
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %d: loaded review count. */
                _n('Loaded %d review.', 'Loaded %d reviews.', (int) $result['count'], 'dope-studio-business-reviews-slider-lite'),
                (int) $result['count']
            ),
        ]);
    }

    public function run_scheduled_fetch(): void
    {
        $settings = get_option(self::SETTINGS_OPTION, []);
        if (! isset($settings['cron_enabled']) || (int) $settings['cron_enabled'] !== 1) {
            return;
        }

        $googleEnabled = ! isset($settings['enable_google']) || (int) $settings['enable_google'] === 1;
        $shouldFetch = $googleEnabled;
        if (! $shouldFetch) {
            return;
        }

        $token = isset($settings['token']) ? trim((string) $settings['token']) : '';
        if ($token === '') {
            return;
        }

        $cronMaxReviews = isset($settings['cron_max_reviews']) ? absint($settings['cron_max_reviews']) : 1;
        if ($cronMaxReviews <= 0) {
            $cronMaxReviews = 1;
        }
        $settings['max_reviews'] = $cronMaxReviews;

        $this->fetch_google_reviews($settings, $token);
    }

    public function on_settings_updated($oldValue, $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $this->reschedule_cron($value);
    }

    public function ensure_cron_schedule(): void
    {
        $settings = get_option(self::SETTINGS_OPTION, []);
        if (! is_array($settings)) {
            return;
        }

        $enabled = isset($settings['cron_enabled']) && (int) $settings['cron_enabled'] === 1;
        $scheduled = wp_next_scheduled(self::CRON_HOOK);

        if ($enabled && ! $scheduled) {
            $this->reschedule_cron($settings);
        }

        if (! $enabled && $scheduled) {
            $this->unschedule_cron();
        }
    }

    public function register_cron_schedules(array $schedules): array
    {
        $schedules['dsbrsl_every_2_days'] = [
            'interval' => 2 * DAY_IN_SECONDS,
            'display'  => __('Every 2 days', 'dope-studio-business-reviews-slider-lite'),
        ];

        $schedules['dsbrsl_monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display'  => __('Once per month', 'dope-studio-business-reviews-slider-lite'),
        ];

        return $schedules;
    }

    private function reschedule_cron(array $settings): void
    {
        $this->unschedule_cron();

        if (! isset($settings['cron_enabled']) || (int) $settings['cron_enabled'] !== 1) {
            return;
        }

        $frequency = isset($settings['cron_frequency']) ? sanitize_key((string) $settings['cron_frequency']) : 'weekly';
        if (! in_array($frequency, ['twicedaily', 'daily', 'weekly', 'dsbrsl_every_2_days', 'dsbrsl_monthly'], true)) {
            $frequency = 'weekly';
        }

        $timeString = isset($settings['cron_time']) ? (string) $settings['cron_time'] : '03:00';
        $timestamp = $this->next_run_timestamp($timeString, $frequency);

        wp_schedule_event($timestamp, $frequency, self::CRON_HOOK);
    }

    private function unschedule_cron(): void
    {
        $next = wp_next_scheduled(self::CRON_HOOK);
        while ($next) {
            wp_unschedule_event($next, self::CRON_HOOK);
            $next = wp_next_scheduled(self::CRON_HOOK);
        }
    }

    private function next_run_timestamp(string $timeString, string $frequency): int
    {
        $tz = wp_timezone();
        $now = new DateTimeImmutable('now', $tz);

        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $timeString, $m)) {
            $timeString = '03:00';
            preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $timeString, $m);
        }

        $hour = isset($m[1]) ? (int) $m[1] : 3;
        $minute = isset($m[2]) ? (int) $m[2] : 0;

        $target = $now->setTime($hour, $minute, 0);
        $interval = $this->schedule_interval_seconds($frequency);
        $ts = $target->getTimestamp();

        if ($ts <= time()) {
            $ts += $interval;
        }

        while ($ts <= time()) {
            $ts += $interval;
        }

        return $ts;
    }

    private function schedule_interval_seconds(string $frequency): int
    {
        $schedules = wp_get_schedules();
        if (isset($schedules[$frequency]['interval'])) {
            return (int) $schedules[$frequency]['interval'];
        }

        return WEEK_IN_SECONDS;
    }

    private function fetch_google_reviews(array $settings, string $token): array
    {
        $placeId = isset($settings['place_id']) ? trim((string) $settings['place_id']) : '';
        $placeUrlInput = isset($settings['place_url']) ? trim((string) $settings['place_url']) : '';
        $placesApiKey = isset($settings['google_places_api_key']) ? trim((string) $settings['google_places_api_key']) : '';
        $usePlacesApiSummary = isset($settings['use_places_api_summary']) && (int) $settings['use_places_api_summary'] === 1;
        $maxReviews = isset($settings['max_reviews']) ? max(0, min(500, (int) $settings['max_reviews'])) : 0;
        $language = isset($settings['language']) && $settings['language'] !== '' ? (string) $settings['language'] : 'en';

        $placeUrl = $placeUrlInput;
        if ($placeUrl === '' && $placeId !== '') {
            $placeUrl = 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode($placeId);
        }

        if ($placeUrl === '') {
            return [
                'success' => false,
                'status'  => 400,
                'message' => (string) __('Provide Place ID or Google Maps URL.', 'dope-studio-business-reviews-slider-lite'),
            ];
        }

        $actorId = 'compass~google-maps-reviews-scraper';
        $endpoint = sprintf(
            'https://api.apify.com/v2/acts/%s/run-sync-get-dataset-items?token=%s',
            rawurlencode($actorId),
            rawurlencode($token)
        );

        $input = [
            'startUrls' => [
                ['url' => esc_url_raw($placeUrl)],
            ],
            'language' => sanitize_text_field($language),
        ];

        if ($maxReviews > 0) {
            $input['maxReviews'] = $maxReviews;
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 300,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($input),
        ]);

        if (is_wp_error($response)) {
            $errorMessage = (string) $response->get_error_message();
            if (stripos($errorMessage, 'cURL error 28') !== false) {
                $errorMessage = (string) __('Request timed out while waiting for Apify. Try a smaller review count or run again.', 'dope-studio-business-reviews-slider-lite');
            }

            return [
                'success' => false,
                'status'  => 500,
                'message' => $errorMessage,
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            return [
                'success' => false,
                'status'  => $status,
                /* translators: %d: HTTP status code. */
                'message' => sprintf(__('Google fetch failed (%d).', 'dope-studio-business-reviews-slider-lite'), (int) $status),
                'body'    => $body,
            ];
        }

        $items = json_decode($body, true);
        if (! is_array($items)) {
            $items = [];
        }

        $reviews = $this->normalise_reviews($items);
        $existingCache = get_option(self::CACHE_OPTION, []);
        $existingReviews = isset($existingCache['reviews']) && is_array($existingCache['reviews'])
            ? $existingCache['reviews']
            : [];
        $mergedReviews = $this->merge_unique_reviews($existingReviews, $reviews);
        $totalReviews = $this->extract_total_reviews($items, count($mergedReviews));
        $totalReviews = max($totalReviews, count($mergedReviews));
        $placesSummary = [];

        if ($usePlacesApiSummary && $placeId !== '' && $placesApiKey !== '') {
            $placesSummary = $this->fetch_places_summary($placeId, $placesApiKey);
        }

        update_option(self::CACHE_OPTION, [
            'reviews'              => $mergedReviews,
            'total_reviews'        => $totalReviews,
            'places_rating'        => isset($placesSummary['rating']) ? (float) $placesSummary['rating'] : 0,
            'places_total_reviews' => isset($placesSummary['total_reviews']) ? absint($placesSummary['total_reviews']) : 0,
            'places_url'           => isset($placesSummary['url']) ? esc_url_raw((string) $placesSummary['url']) : '',
            'updated_at'           => time(),
        ], false);

        return [
            'success' => true,
            'count'   => count($reviews),
            'total'   => $totalReviews,
        ];
    }

    private function normalise_reviews(array $items): array
    {
        $out = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $author = $item['name'] ?? __('Anonymous', 'dope-studio-business-reviews-slider-lite');
            $rating = $item['stars'] ?? 0;
            $text = $item['text'] ?? '';
            $date = $item['publishedAtDate']
                ?? ($item['publishedAt']
                    ?? ($item['reviewedAt']
                        ?? ($item['date'] ?? '')));
            $url = $item['reviewUrl'] ?? '';

            $entry = [
                'author' => sanitize_text_field((string) $author),
                'rating' => max(0, min(5, (float) $rating)),
                'text'   => sanitize_textarea_field((string) $text),
                'date'   => sanitize_text_field((string) $date),
                'url'    => esc_url_raw((string) $url),
                'avatar' => '',
            ];

            if ($entry['text'] === '' && $entry['rating'] <= 0) {
                continue;
            }

            $out[] = $entry;
        }

        return $out;
    }

    private function merge_unique_reviews(array $existingReviews, array $fetchedReviews): array
    {
        $map = [];

        foreach ($existingReviews as $review) {
            if (! is_array($review)) {
                continue;
            }

            $map[$this->review_dedupe_key($review)] = $review;
        }

        foreach ($fetchedReviews as $review) {
            if (! is_array($review)) {
                continue;
            }

            $map[$this->review_dedupe_key($review)] = $review;
        }

        return $this->sort_reviews_newest_first(array_values($map));
    }

    private function review_dedupe_key(array $review): string
    {
        $url = trim((string) ($review['url'] ?? ''));
        if ($url !== '') {
            return 'url:' . $url;
        }

        $author = mb_strtolower(trim((string) ($review['author'] ?? '')));
        $date = trim((string) ($review['date'] ?? ''));
        $rating = number_format((float) ($review['rating'] ?? 0), 2, '.', '');
        $headline = mb_strtolower(trim((string) ($review['headline'] ?? '')));
        $text = mb_strtolower(trim((string) ($review['text'] ?? '')));

        return 'hash:' . md5($author . '|' . $date . '|' . $rating . '|' . $headline . '|' . $text);
    }

    private function render_review_schema_script(array $reviews, float $ratingValue, int $reviewCount, string $headline = '', int $maxReviewItems = 0): string
    {
        if (empty($reviews)) {
            return '';
        }

        $businessName = trim((string) get_bloginfo('name'));
        if ($businessName === '') {
            $businessName = 'Business';
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $businessName,
            'url' => esc_url_raw(home_url('/')),
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => max(1, min(5, round($ratingValue, 1))),
                'reviewCount' => max(1, $reviewCount),
            ],
        ];

        if ($headline !== '') {
            $schema['description'] = sanitize_text_field($headline);
        }

        $schemaReviews = [];
        $schemaSourceReviews = $maxReviewItems > 0
            ? array_slice($reviews, 0, max(1, min(100, $maxReviewItems)))
            : $reviews;

        foreach ($schemaSourceReviews as $review) {
            if (! is_array($review)) {
                continue;
            }

            $author = trim((string) ($review['author'] ?? 'Anonymous'));
            $text = trim((string) ($review['text'] ?? ''));
            $date = $this->schema_review_date((string) ($review['date'] ?? ''));
            $rating = max(1, min(5, (float) ($review['rating'] ?? 0)));

            if ($text === '') {
                continue;
            }

            $item = [
                '@type' => 'Review',
                'author' => [
                    '@type' => 'Person',
                    'name' => $author !== '' ? $author : 'Anonymous',
                ],
                'reviewBody' => sanitize_textarea_field($text),
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => round($rating, 1),
                    'bestRating' => 5,
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => 'Google',
                ],
            ];

            if ($date !== '') {
                $item['datePublished'] = $date;
            }

            $schemaReviews[] = $item;
        }

        if (! empty($schemaReviews)) {
            $schema['review'] = $schemaReviews;
        }

        return '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    private function schema_review_date(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }

        return gmdate('Y-m-d', $ts);
    }

    public function render_shortcode(array $atts = []): string
    {
        $settings = get_option(self::SETTINGS_OPTION, []);
        if (isset($settings['enable_google']) && (int) $settings['enable_google'] !== 1) {
            return '';
        }

        $cache = get_option(self::CACHE_OPTION, []);
        $reviews = isset($cache['reviews']) && is_array($cache['reviews']) ? $cache['reviews'] : [];

        $fetchedReviewCount = count($reviews);
        $reviewCountMode = isset($settings['review_count_mode']) ? (string) $settings['review_count_mode'] : 'fetched';
        $customReviewCount = isset($settings['custom_review_count']) ? absint($settings['custom_review_count']) : 0;
        $usePlacesApiSummary = isset($settings['use_places_api_summary']) && (int) $settings['use_places_api_summary'] === 1;
        $placesTotalReviews = isset($cache['places_total_reviews']) ? absint($cache['places_total_reviews']) : 0;
        $placesRating = isset($cache['places_rating']) ? (float) $cache['places_rating'] : 0;
        $headerReviewCount = $reviewCountMode === 'custom' && $customReviewCount > 0
            ? $customReviewCount
            : (($usePlacesApiSummary && $placesTotalReviews > 0) ? $placesTotalReviews : $fetchedReviewCount);

        $defaultTheme = isset($settings['theme']) ? (string) $settings['theme'] : 'dark';
        $defaultAutoplay = isset($settings['autoplay_default']) ? (int) $settings['autoplay_default'] : 1;
        $defaultInterval = isset($settings['autoplay_interval_default']) ? max(1500, min(20000, absint($settings['autoplay_interval_default']))) : 5500;
        $defaultLoop = isset($settings['loop_infinite_default']) ? (int) $settings['loop_infinite_default'] : 1;
        $defaultShowDots = isset($settings['show_dots_default']) ? (int) $settings['show_dots_default'] : 1;
        $defaultSwipe = isset($settings['swipe_default']) ? (int) $settings['swipe_default'] : 1;
        $defaultMobile = isset($settings['slides_mobile_default']) ? max(1, min(6, absint($settings['slides_mobile_default']))) : 1;
        $defaultTablet = isset($settings['slides_tablet_default']) ? max(1, min(6, absint($settings['slides_tablet_default']))) : 2;
        $defaultDesktop = isset($settings['slides_desktop_default']) ? max(1, min(6, absint($settings['slides_desktop_default']))) : 3;
        $defaultDisplayLimitTouched = isset($settings['display_limit_default_touched']) ? (int) $settings['display_limit_default_touched'] : 0;
        $defaultDisplayLimit = $defaultDisplayLimitTouched === 1 && isset($settings['display_limit_default']) ? max(0, min(500, absint($settings['display_limit_default']))) : 0;
        $defaultSchemaReviewLimitTouched = isset($settings['schema_review_limit_default_touched']) ? (int) $settings['schema_review_limit_default_touched'] : 0;
        $defaultSchemaReviewLimit = $defaultSchemaReviewLimitTouched === 1 && isset($settings['schema_review_limit_default']) ? max(1, min(100, absint($settings['schema_review_limit_default']))) : 0;
        $defaultShowSummary = isset($settings['show_summary_default']) ? (int) $settings['show_summary_default'] : 1;
        $defaultShowReadOnGoogle = isset($settings['show_read_on_google_default']) ? (int) $settings['show_read_on_google_default'] : 1;
        $defaultRatingMode = isset($settings['rating_mode_default']) ? (string) $settings['rating_mode_default'] : 'auto';
        $defaultManualRating = isset($settings['manual_rating_default']) ? (float) $settings['manual_rating_default'] : 5;
        $defaultMinRating = isset($settings['min_rating_default']) ? absint($settings['min_rating_default']) : 0;
        $defaultTitle = isset($settings['title_default']) ? (string) $settings['title_default'] : 'Latest reviews';

        $googleMapsUrl = isset($cache['places_url']) ? trim((string) $cache['places_url']) : '';
        if ($googleMapsUrl === '') {
            $googleMapsUrl = isset($settings['place_url']) ? trim((string) $settings['place_url']) : '';
        }
        if ($googleMapsUrl === '') {
            $placeId = isset($settings['place_id']) ? trim((string) $settings['place_id']) : '';
            if ($placeId !== '') {
                $googleMapsUrl = 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode($placeId);
            }
        }

        $atts = shortcode_atts([
            'theme'    => $defaultTheme,
            'limit'    => (string) $defaultDisplayLimit,
            'autoplay' => $defaultAutoplay,
            'interval' => $defaultInterval,
            'loop'     => $defaultLoop,
            'show_dots' => $defaultShowDots,
            'swipe'     => $defaultSwipe,
            'mobile'    => (string) $defaultMobile,
            'tablet'    => (string) $defaultTablet,
            'desktop'   => (string) $defaultDesktop,
            'show_summary' => $defaultShowSummary,
            'show_read_on_google' => $defaultShowReadOnGoogle,
            'rating_mode'  => $defaultRatingMode,
            'manual_rating' => (string) $defaultManualRating,
            'title'    => $defaultTitle,
            'min_rating' => (string) $defaultMinRating,
            'show_no_comment' => isset($settings['show_no_comment']) ? (string) ((int) $settings['show_no_comment']) : '1',
        ], $atts, self::SHORTCODE_GOOGLE);

        $theme = in_array((string) $atts['theme'], ['dark', 'light'], true) ? (string) $atts['theme'] : 'dark';
        $limit = absint((string) $atts['limit']);
        $autoplay = (int) $atts['autoplay'] === 1 ? '1' : '0';
        $interval = max(1500, min(20000, absint($atts['interval'])));
        $loop = (int) $atts['loop'] === 1 ? '1' : '0';
        $showDots = (int) $atts['show_dots'] === 1 ? '1' : '0';
        $swipe = (int) $atts['swipe'] === 1 ? '1' : '0';
        $mobileCols = max(1, min(6, absint((string) $atts['mobile'])));
        $tabletCols = max(1, min(6, absint((string) $atts['tablet'])));
        $desktopCols = max(1, min(6, absint((string) $atts['desktop'])));
        $showSummary = (int) $atts['show_summary'] === 1 ? '1' : '0';
        $showReadOnGoogle = (int) $atts['show_read_on_google'] === 1 ? '1' : '0';
        $ratingMode = in_array((string) $atts['rating_mode'], ['auto', 'manual'], true) ? (string) $atts['rating_mode'] : 'auto';
        $manualRating = max(0, min(5, (float) $atts['manual_rating']));
        $minRating = absint((string) $atts['min_rating']);
        $minRating = in_array($minRating, [0, 2, 3, 4, 5], true) ? $minRating : 0;
        $hideNoComment = (string) $atts['show_no_comment'] === '1';

        $rawReviews = $reviews;

        if ($hideNoComment) {
            $reviews = array_values(array_filter($reviews, static function (array $review): bool {
                $text = isset($review['text']) ? trim((string) $review['text']) : '';
                return $text !== '';
            }));
        }

        if ($minRating > 0) {
            $reviews = array_values(array_filter($reviews, static function (array $review) use ($minRating): bool {
                $rating = isset($review['rating']) ? (float) $review['rating'] : 0;
                return $rating >= $minRating;
            }));
        }

        $reviews = $this->sort_reviews_newest_first($reviews);

        if ($limit > 0) {
            $reviews = array_slice($reviews, 0, min(100, $limit));
        }

        if (empty($reviews) && ! empty($rawReviews)) {
            $reviews = $rawReviews;
        }

        $schemaRating = $ratingMode === 'manual'
            ? $manualRating
            : (($usePlacesApiSummary && $placesRating > 0) ? $placesRating : (count($reviews) > 0 ? array_sum(array_map(static function (array $review): float {
                return (float) ($review['rating'] ?? 0);
            }, $reviews)) / count($reviews) : 0));
        $schemaReviewCount = count($reviews);
        $schemaScript = $this->render_review_schema_script($reviews, $schemaRating, $schemaReviewCount, (string) $atts['title'], $defaultSchemaReviewLimit);

        wp_enqueue_style('dsbrsl-frontend-style');
        wp_enqueue_script('dsbrsl-frontend-script');

        ob_start();
        ?>
        <section class="grs-slider-shell grs-theme-<?php echo esc_attr($theme); ?>" data-grs-slider data-autoplay="<?php echo esc_attr($autoplay); ?>" data-interval="<?php echo esc_attr((string) $interval); ?>" data-autoplay-interval="<?php echo esc_attr((string) $interval); ?>" data-loop="<?php echo esc_attr($loop); ?>" data-show-dots="<?php echo esc_attr($showDots); ?>" data-swipe="<?php echo esc_attr($swipe); ?>" data-mobile="<?php echo esc_attr((string) $mobileCols); ?>" data-tablet="<?php echo esc_attr((string) $tabletCols); ?>" data-desktop="<?php echo esc_attr((string) $desktopCols); ?>">
            <?php if (! empty($reviews) && $showSummary === '1') :
                $sum = 0;
                foreach ($reviews as $r) {
                    $sum += isset($r['rating']) ? (float) $r['rating'] : 0;
                }
                $autoAvg = count($reviews) > 0 ? round($sum / count($reviews), 1) : 0;
                $avg = $ratingMode === 'manual' ? $manualRating : (($usePlacesApiSummary && $placesRating > 0) ? $placesRating : $autoAvg);
                $ratingLabel = $this->rating_label($avg);
                ?>
                <div class="grs-header-score">
                    <div class="grs-score-title"><?php echo esc_html($ratingLabel); ?></div>
                    <div class="grs-score-stars" aria-hidden="true"><?php echo wp_kses_post($this->star_html($avg)); ?></div>
                    <div class="grs-score-meta">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: Number of reviews. */
                                _n('Based on %d review', 'Based on %d reviews', $headerReviewCount, 'dope-studio-business-reviews-slider-lite'),
                                $headerReviewCount
                            )
                        );
                        ?>
                    </div>
                    <?php if ($googleMapsUrl !== '') : ?>
                        <a class="grs-google-logo" href="<?php echo esc_url($googleMapsUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Open Google Maps place page', 'dope-studio-business-reviews-slider-lite'); ?>">
                            <span class="g-b">G</span><span class="g-r">o</span><span class="g-y">o</span><span class="g-b">g</span><span class="g-g">l</span><span class="g-r">e</span>
                        </a>
                    <?php else : ?>
                        <div class="grs-google-logo" aria-label="Google" role="img">
                            <span class="g-b">G</span><span class="g-r">o</span><span class="g-y">o</span><span class="g-b">g</span><span class="g-g">l</span><span class="g-r">e</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="grs-slider-top">
                <div class="grs-slider-title"><?php echo esc_html((string) $atts['title']); ?></div>
                <div class="grs-controls">
                    <button class="grs-icon-btn" data-grs-prev aria-label="<?php esc_attr_e('Previous', 'dope-studio-business-reviews-slider-lite'); ?>">&#x2039;</button>
                    <button class="grs-icon-btn" data-grs-next aria-label="<?php esc_attr_e('Next', 'dope-studio-business-reviews-slider-lite'); ?>">&#x203A;</button>
                </div>
            </div>

            <div class="grs-slider" aria-live="polite">
                <div class="grs-track" data-grs-track>
                    <?php if (empty($reviews)) : ?>
                        <div class="grs-slide">
                            <article class="grs-card">
                                <div class="grs-text"><?php esc_html_e('No reviews loaded yet.', 'dope-studio-business-reviews-slider-lite'); ?></div>
                            </article>
                        </div>
                    <?php else : ?>
                        <?php foreach ($reviews as $review) :
                            $author = isset($review['author']) ? $review['author'] : __('Anonymous', 'dope-studio-business-reviews-slider-lite');
                            $text = isset($review['text']) ? $review['text'] : '';
                            $date = isset($review['date']) ? $this->format_date((string) $review['date']) : '';
                            $rating = isset($review['rating']) ? (float) $review['rating'] : 0;
                            $url = isset($review['url']) ? $review['url'] : '';
                            ?>
                            <div class="grs-slide">
                                <article class="grs-card">
                                    <div class="grs-card-top">
                                        <div class="grs-author">
                                            <div class="grs-avatar"><?php echo esc_html($this->initials($author)); ?></div>
                                            <div class="grs-author-meta">
                                                <div class="grs-name"><?php echo esc_html($author); ?></div>
                                                <div class="grs-meta"><?php echo esc_html($date); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grs-stars grs-stars-row" title="<?php echo esc_attr(number_format_i18n($rating, 1)); ?> / 5"><?php echo wp_kses_post($this->star_html($rating)); ?></div>
                                    <div class="grs-text"><?php echo esc_html($text !== '' ? $text : __('No comment text provided.', 'dope-studio-business-reviews-slider-lite')); ?></div>
                                    <?php if ($showReadOnGoogle === '1' && $url !== '') : ?>
                                        <a class="grs-read-google" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Read on Google', 'dope-studio-business-reviews-slider-lite'); ?></a>
                                    <?php endif; ?>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grs-dots" data-grs-dots></div>
        </section>
        <?php

        return (string) ob_get_clean() . $schemaScript;
    }

    private function star_html(float $rating): string
    {
        $r = max(0, min(5, $rating));
        $full = (int) floor($r);
        $fraction = $r - $full;
        $half = $fraction >= 0.5 ? 1 : 0;
        $empty = max(0, 5 - $full - $half);

        $html = '<span class="grs-star-set">';
        for ($i = 0; $i < $full; $i++) {
            $html .= '<span class="grs-star is-full">★</span>';
        }
        if ($half === 1) {
            $html .= '<span class="grs-star is-half">★</span>';
        }
        for ($i = 0; $i < $empty; $i++) {
            $html .= '<span class="grs-star is-empty">★</span>';
        }
        $html .= '</span>';

        return $html;
    }

    private function fetch_places_summary(string $placeId, string $apiKey): array
    {
        $placeId = trim($placeId);
        $apiKey = trim($apiKey);

        if ($placeId === '' || $apiKey === '') {
            return [];
        }

        $endpoint = add_query_arg([
            'place_id' => $placeId,
            'fields'   => 'rating,user_ratings_total,url',
            'key'      => $apiKey,
        ], 'https://maps.googleapis.com/maps/api/place/details/json');

        $response = wp_remote_get($endpoint, [
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($data) || ! isset($data['result']) || ! is_array($data['result'])) {
            return [];
        }

        $result = $data['result'];

        return [
            'rating'        => isset($result['rating']) ? max(0, min(5, (float) $result['rating'])) : 0,
            'total_reviews' => isset($result['user_ratings_total']) ? absint($result['user_ratings_total']) : 0,
            'url'           => isset($result['url']) ? esc_url_raw((string) $result['url']) : '',
        ];
    }

    private function rating_label(float $rating): string
    {
        $r = max(0, min(5, $rating));

        if ($r < 1.0) {
            return (string) __('Very poor', 'dope-studio-business-reviews-slider-lite');
        }

        if ($r < 2.0) {
            return (string) __('Poor', 'dope-studio-business-reviews-slider-lite');
        }

        if ($r < 3.0) {
            return (string) __('Fair', 'dope-studio-business-reviews-slider-lite');
        }

        if ($r < 4.0) {
            return (string) __('Good', 'dope-studio-business-reviews-slider-lite');
        }

        if ($r >= 4.5) {
            return (string) __('Excellent', 'dope-studio-business-reviews-slider-lite');
        }

        return (string) __('Very good', 'dope-studio-business-reviews-slider-lite');
    }

    private function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name);
        if (! is_array($parts)) {
            return 'U';
        }

        $parts = array_slice($parts, 0, 2);
        $result = '';

        foreach ($parts as $part) {
            $first = mb_substr($part, 0, 1);
            $result .= mb_strtoupper($first);
        }

        return $result !== '' ? $result : 'U';
    }

    private function format_date(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }

        return date_i18n(get_option('date_format'), $timestamp);
    }

    private function sort_reviews_newest_first(array $reviews): array
    {
        usort($reviews, function (array $a, array $b): int {
            $ta = $this->review_timestamp($a);
            $tb = $this->review_timestamp($b);

            if ($ta === $tb) {
                return 0;
            }

            return $ta > $tb ? -1 : 1;
        });

        return $reviews;
    }

    private function review_timestamp(array $review): int
    {
        $value = isset($review['date']) ? trim((string) $review['date']) : '';
        if ($value === '') {
            return 0;
        }

        $ts = strtotime($value);

        return $ts === false ? 0 : (int) $ts;
    }

    private function extract_total_reviews(array $items, int $fallback): int
    {
        $keys = [
            'reviewsCount',
            'reviewCount',
            'userReviewsCount',
            'totalReviews',
            'totalReviewCount',
            'numberOfReviews',
            'reviewsNumber',
            'reviewsTotal',
            'totalRatings',
        ];

        $stack = [$items];
        $candidates = [];

        while (! empty($stack)) {
            $current = array_pop($stack);
            if (! is_array($current)) {
                continue;
            }

            foreach ($current as $key => $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }

                if (is_string($key) && in_array($key, $keys, true)) {
                    $parsed = $this->to_positive_int($value);
                    if ($parsed > 0) {
                        $candidates[] = $parsed;
                    }
                }

                if (is_string($key) && (is_string($value) || is_int($value) || is_float($value))) {
                    $keyLower = strtolower($key);
                    if (strpos($keyLower, 'review') !== false || strpos($keyLower, 'rating') !== false) {
                        $parsed = $this->to_positive_int($value);
                        if ($parsed > 0) {
                            $candidates[] = $parsed;
                        }
                    }
                }

                if (is_string($value) && stripos($value, 'review') !== false) {
                    $parsed = $this->to_positive_int($value);
                    if ($parsed > 0) {
                        $candidates[] = $parsed;
                    }
                }
            }
        }

        if (empty($candidates)) {
            return max(0, $fallback);
        }

        rsort($candidates, SORT_NUMERIC);

        return max((int) $candidates[0], $fallback);
    }

    private function to_positive_int($value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_float($value)) {
            return max(0, (int) round($value));
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));

            if (preg_match('/([0-9]+(?:[.,][0-9]+)?)\s*([km])\b/', $lower, $kMatch) === 1) {
                $base = (float) str_replace(',', '.', $kMatch[1]);
                $mul = $kMatch[2] === 'm' ? 1000000 : 1000;

                return max(0, (int) round($base * $mul));
            }

            if (preg_match_all('/[0-9]+(?:[.,][0-9]+)*/', $lower, $matches) !== 1 || empty($matches[0])) {
                return 0;
            }

            $candidates = [];
            foreach ($matches[0] as $token) {
                $token = str_replace(' ', '', $token);

                if (strpos($token, ',') !== false && strpos($token, '.') === false) {
                    $token = str_replace(',', '', $token);
                } elseif (strpos($token, '.') !== false && strpos($token, ',') !== false) {
                    $token = str_replace([',', '.'], '', $token);
                } elseif (strpos($token, '.') !== false) {
                    $parts = explode('.', $token);
                    if (count($parts) === 2 && strlen($parts[1]) <= 2) {
                        $candidates[] = (int) round((float) $token);
                        continue;
                    }
                    $token = str_replace('.', '', $token);
                }

                if (ctype_digit($token)) {
                    $candidates[] = (int) $token;
                }
            }

            if (empty($candidates)) {
                return 0;
            }

            rsort($candidates, SORT_NUMERIC);

            return max(0, (int) $candidates[0]);
        }

        return 0;
    }

    public static function deactivate(): void
    {
        $next = wp_next_scheduled(self::CRON_HOOK);
        while ($next) {
            wp_unschedule_event($next, self::CRON_HOOK);
            $next = wp_next_scheduled(self::CRON_HOOK);
        }
    }
}

register_deactivation_hook(__FILE__, ['DSBRSL_Google_Reviews_Slider_Lite', 'deactivate']);
new DSBRSL_Google_Reviews_Slider_Lite();
