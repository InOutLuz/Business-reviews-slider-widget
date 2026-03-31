=== Dope Studio Business Reviews Slider ===
Contributors: dopestudio
Tags: reviews, google reviews, trustpilot, slider
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.13
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google and Trustpilot review sliders with manual and scheduled fetching.

== Description ==

By [Dope Studio](https://profiles.wordpress.org/dopestudio)

Dope Studio Business Reviews Slider is a WordPress plugin that fetches and displays customer reviews in responsive sliders.

**Fresh reviews every day (recommended setup):**
1. Run your first fetch with **Max reviews** left empty to import your full history.
2. Set **Max reviews** to a small value (for example 1-5).
3. Enable daily cron.

From then on, previously stored reviews are kept, and each cron run adds only newly found unique reviews.

- **Display unlimited Google and Trustpilot reviews for free**  
No artificial caps, no paid tiers just to show more reviews. Fetch hundreds or thousands and display as many as you want.

- **Beautiful, responsive review sliders out of the box**  
Modern, clean sliders with swipe, autoplay, progress bar pagination and per-device layouts, no page builders or extra styling plugins needed.

- **Automatic syncing or manual control, your choice**  
Fetch reviews on demand or keep them fresh automatically with scheduled updates via WP-Cron.

- **Full control over what appears on your site**  
Filter by rating, hide textless reviews, limit displayed cards, customise titles, ratings and review counts per platform.

- **Built for performance, not bloat**  
Reviews are cached in WordPress, not fetched on every page load, fast frontend output with simple shortcodes.

This plugin uses third-party services to fetch and enrich review data:
- Apify (Google and Trustpilot review fetching)
- Google Places API (optional, for Google summary enrichment)

Apify free tier allows 1000+ reviews to be fetched per month.

The plugin stores fetched data in WordPress options and renders the frontend using shortcodes.

== External services ==

This plugin connects to third-party services to fetch review data and optional rating summary data.

1) Apify (review fetching)
- Purpose: fetches Google and Trustpilot reviews.
- When data is sent: only when you manually run a fetch from wp-admin or when scheduled WP-Cron fetching runs.
- Data sent: Apify API token, target source values you configure (Google Place ID and/or Google Maps URL, Trustpilot domain), selected language, and max review limits.
- Terms: https://docs.apify.com/legal/general-terms-and-conditions
- Privacy policy: https://docs.apify.com/legal/privacy-policy

2) Google Places API (optional summary enrichment)
- Purpose: retrieves Google place summary fields (for example rating and rating count) when optional Places summary mode is enabled.
- When data is sent: during Google fetch operations, only if Places summary mode is enabled and a Places API key is configured.
- Data sent: Google Places API key and Google Place ID.
- Terms: https://cloud.google.com/maps-platform/terms
- Privacy policy: https://policies.google.com/privacy

3) Dope Studio update metadata endpoint (plugin updates)
- Purpose: checks whether a newer Pro plugin version is available and provides package metadata for WordPress updates UI.
- When data is sent: when WordPress performs plugin update checks (for example on updates screens / scheduled checks).
- Data sent: basic WordPress update request data (site URL, plugin versions, WordPress/PHP versions, locale) as part of standard update-check requests.
- Endpoint: https://products.dopestudio.co.uk/brs/downloads/dope-studio-business-reviews-slider-update.json

4) Dope Studio telemetry endpoint (activation, heartbeat, deactivation feedback)
- Purpose: estimates active installations and records optional deactivation reasons to improve the plugin.
- When data is sent: on plugin activation, daily heartbeat, plugin deactivation, and when a user submits the deactivation feedback popup.
- Data sent: generated install ID, hashed site URL (SHA-256), site host, plugin version, WordPress/PHP version, locale, and optional feedback reason/comment/email.
- Endpoint: https://products.dopestudio.co.uk/brs/telemetry/collect.php

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
- Progress bar pagination
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
2. Ensure the main plugin file is in the plugin root directory.
3. Activate **Dope Studio Business Reviews Slider** from WordPress Admin → Plugins.

---

## 4) Quick Start

1. Open **Dope Studio Business Reviews Slider** admin page.
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
   - `[dsbrs_google_reviews_slider]`
   - `[dsbrs_trustpilot_reviews_slider]`

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

Default behavior: if you use a shortcode without attributes, admin settings are applied.

If you add attributes, they override admin settings for that shortcode instance only. This allows different slider/widget variants on different pages.

## Google
`[dsbrs_google_reviews_slider]`

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
`[dsbrs_trustpilot_reviews_slider]`

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
- `dsbrs_settings`
- `dsbrs_reviews_cache`
- `dsbrs_trustpilot_reviews_cache`

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

### 1.0.13
- Added deactivation feedback popup on the plugins screen
- Added telemetry pipeline updates for Pro active-install estimation and feedback collection
- Added server-side telemetry file outputs for private cPanel-based review

### 1.0.12
- Added built-in Pro plugin update checker using a remote metadata JSON endpoint
- Added plugin information support so WordPress can show update details for Pro releases
- Added `Update URI` header to ensure correct update source handling for the standalone Pro package

### 1.0.11
- Clarified shortcode behavior: attributes override admin defaults per shortcode instance
- Added shortcode guidance for using different widget variants across different pages
- Clarified `show_no_comment="1"` behavior in shortcode docs

### 1.0.10
- Fixed autoplay interval handling so custom interval values are applied reliably in Google and Trustpilot sliders
- Added compatibility support for legacy interval data attribute naming in frontend slider initialization
- Bumped frontend script version to ensure cache refresh after update

### 1.0.9
- Improved cron guidance wording for clarity (Google + Trustpilot)
- Moved daily token-saving workflow explanation from cron toggle area to cron timing guidance
- Clarified that daily full fetches can exhaust Apify quota and recommended 1-5 max reviews for daily cron after initial full import

### 1.0.8
- New incremental fetch workflow: initial full import + daily small-limit updates
- Merge + dedupe logic now keeps existing stored reviews and appends only new unique reviews
- Added admin guidance under Fetch and Cron settings for the recommended 1-5 daily strategy
- Added clear token-usage guidance to help avoid exhausting Apify monthly quota

### 1.0.6
- Frontend pagination switched from dots to progress bar (Google + Trustpilot)
- Stronger frontend style isolation to reduce theme CSS conflicts
- Admin labels updated from "Dots navigation" to "Progress bar"

### 1.0.0
- Initial release with Google + Trustpilot review slider
- Admin fetch and cron automation
- Per-platform slider settings
- Rebrand to Dope Studio Business Reviews Slider

---

## 12) License

GPL-2.0+
