=== Business Reviews Slider Widget ===
Contributors: dopestudio
Tags: reviews, google reviews, trustpilot, slider
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google and Trustpilot review sliders with manual and scheduled fetching.

== Description ==

By [Dope Studio](https://profiles.wordpress.org/dopestudio)

Business Reviews Slider Widget is a WordPress plugin that fetches and displays customer reviews in responsive sliders.

This plugin uses third-party services to fetch and enrich review data:
- Apify (Google and Trustpilot review fetching)
- Google Places API (optional, for Google summary enrichment)

Apify free tier allows 1000+ reviews to be fetched per month.

The plugin stores fetched data in WordPress options and renders the frontend using shortcodes.

---

## 1) Features

### Core
- Google + Trustpilot support
- Manual fetch from admin
- Scheduled auto-fetch via WP-Cron
- Cached storage in DB options
- Separate platform settings and toggles

### Slider UI/UX
- Dark and light themes
- Autoplay + interval
- Infinite loop mode
- Dots navigation
- Mobile/tablet swipe
- Per-device cards per view
- Optional top summary block

### Review Filtering
- Hide reviews without text comments
- Minimum rating filter
- Frontend display limit
- Newest-first ordering

### Header Controls
- Star rating source: Auto or Manual
- Header review count source: Fetched or Custom
- Platform-specific title controls

### Trustpilot-Specific
- Company domain fetch target
- Logo variant selection
- Optional review headline/title visibility
- Equalized headline area for aligned card body text

---

## 2) Requirements

- WordPress 6.x+
- PHP 7.4+ (8.0+ recommended)
- Outbound HTTP access from server (to Apify and optional APIs)
- Apify account + API token (Free tier available 1000+ reviews per month)

Optional:
- Google Places API key (if using Places summary mode)

---

## 3) Installation

1. Place plugin folder in `wp-content/plugins/`.
2. Ensure main plugin file is:
   - `business-reviews-slider-widget.php`
3. Activate **Business Reviews Slider Widget** from WordPress Admin → Plugins.

---

## 4) Quick Start

1. Open **Business Reviews Slider Widget** admin page.
2. Add your **Apify token**.
3. Enable one or more platforms.
4. Configure source settings:
   - Google: Place ID or Google Maps URL
   - Trustpilot: company domain
5. Click fetch:
   - Fetch all reviews
   - Fetch Google reviews
   - Fetch Trustpilot reviews
6. Add shortcode to page/post:
   - `[google_reviews_slider]`
   - `[trustpilot_reviews_slider]`

---

## 5) Admin Settings Overview

### General Settings
- Apify token
- Platform enable toggles (Google / Trustpilot)
- Auto-fetch cron settings
- Cron frequency/time/scope

### Google API Settings
- Place ID
- Google Maps URL
- Google Places API key (optional)
- Places API summary toggle (optional)
- Max reviews

### Google Slider Settings
- Theme
- Autoplay / interval
- Loop / dots / swipe
- Cards per view (mobile/tablet/desktop)
- Slider title
- Display limit
- Top summary block toggle
- Rating mode + manual rating
- Review count mode + custom count
- Review filters

### Trustpilot Settings
- Company domain
- Max reviews

### Trustpilot Slider Settings
- Theme
- Logo color
- Autoplay / interval
- Loop / dots / swipe
- Cards per view (mobile/tablet/desktop)
- Slider title
- Display limit
- Top summary block toggle
- Review titles toggle
- Rating mode + manual rating
- Review count mode + custom count
- Review filters

---

## 6) Shortcodes

## Google
`[google_reviews_slider]`

Common attributes:
- `theme="dark|light"`
- `limit="0"`
- `autoplay="0|1"`
- `interval="1500-20000"`
- `loop="0|1"`
- `show_dots="0|1"`
- `swipe="0|1"`
- `mobile="1-6"`
- `tablet="1-6"`
- `desktop="1-6"`
- `show_summary="0|1"`
- `show_read_on_google="0|1"`
- `rating_mode="auto|manual"`
- `manual_rating="0-5"`
- `min_rating="0|2|3|4|5"`
- `show_no_comment="1"` (hide ratings-only textless reviews)

## Trustpilot
`[trustpilot_reviews_slider]`

Common attributes:
- `theme="dark|light"`
- `logo_variant="white|black"`
- `limit="0"`
- `autoplay="0|1"`
- `interval="1500-20000"`
- `loop="0|1"`
- `show_dots="0|1"`
- `swipe="0|1"`
- `mobile="1-6"`
- `tablet="1-6"`
- `desktop="1-6"`
- `show_summary="0|1"`
- `show_titles="0|1"`
- `rating_mode="auto|manual"`
- `manual_rating="0-5"`
- `review_count_mode="fetched|custom"`
- `custom_review_count="0+"`
- `min_rating="0|2|3|4|5"`
- `show_no_comment="1"`

---

## 7) Cron Automation

When cron is enabled:
- The plugin schedules fetch jobs through WP-Cron.
- Frequency options include daily-style intervals and weekly/monthly options.
- Scope can target enabled platforms only, all platforms, or a single platform.

Notes:
- WP-Cron depends on site traffic unless server cron triggers `wp-cron.php`.
- Frequent fetches can consume Apify credits quickly.

---

## 8) Storage

The plugin stores settings and cached review data in WordPress options.

Current option keys:
- `brsw_settings`
- `brsw_reviews_cache`
- `brsw_trustpilot_reviews_cache`

---

## 9) Troubleshooting

### Fetch timeout
- Symptom: request timeout / cURL timeout
- Fix:
  - lower max reviews
  - retry fetch
  - verify host outbound HTTP permissions

### Empty slider
- Ensure platform is enabled
- Check required source input:
  - Google: Place ID or Maps URL
  - Trustpilot: company domain
- Trigger manual fetch and verify stored count in admin

### Cron not running
- Confirm cron enabled in plugin settings
- Verify WordPress cron works on host
- Consider server-side cron trigger

### Rating differences in Auto mode
- Auto summary is calculated from fetched data; platform totals may differ.
- Use Manual rating mode if exact marketing number is needed.

---

## 10) Security Notes

- Nonces are used for admin AJAX fetch actions.
- Settings are sanitized before storage.
- Output is escaped for frontend/admin rendering.

---

## 11) Changelog (local)

### 1.0.0
- Initial release with Google + Trustpilot review slider
- Admin fetch and cron automation
- Per-platform slider settings
- Rebrand to Business Reviews Slider Widget

---

## 12) License

GPL-2.0+
