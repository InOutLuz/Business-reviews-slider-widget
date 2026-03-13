=== Dope Studio Business Reviews Slider Lite ===
Contributors: dopestudio
Tags: reviews, google reviews, slider
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google review slider plugin with manual and automatic fetching, rich display controls, and fast cached frontend output.

== Description ==

By [Dope Studio](https://profiles.wordpress.org/dopestudio)

Dope Studio Business Reviews Slider Lite helps you fetch and display Google reviews in responsive, customizable sliders.

**Pro version available (Free):** includes additional review platforms and more advanced controls.

### Core highlights

- Google reviews support
- Cron job for automatic scheduled fetching
- Manual fetch option
- Cached storage in WordPress options
- Fast shortcode rendering on frontend

### Slider UI/UX

- Dark and light themes
- Autoplay with custom interval
- Optional infinite loop
- Dots navigation
- Mobile/tablet swipe support
- Per-device cards per view (mobile / tablet / desktop)
- Optional top summary block

### Review filtering and header controls

- Hide textless reviews
- Minimum rating filter
- Frontend display limit
- Newest-first ordering
- Rating source: Auto or Manual
- Header review count: Fetched or Custom

### Optional Google summary enrichment

- Option 1: Use Google Places API for automatic fetching of rating and review-count summary in header
- Option 2: Keep review text/cards sourced from fetched reviews only (No Google Places API required)


== External services ==

This plugin connects to third-party services to fetch review data and optional rating summary data.

1) Apify (review fetching)
- Purpose: fetches Google reviews.
- When data is sent: only when you manually run a fetch from wp-admin.
- Data sent: Apify API token, Google Place ID and/or Google Maps URL, selected language, max review limit.
- Terms: https://apify.com/terms
- Privacy policy: https://apify.com/privacy

1) Google Places API (optional summary enrichment)
- Purpose: retrieves Google place summary fields (for example rating and rating count) when Places summary mode is enabled.
- When data is sent: during Google fetch operations, only if Places summary mode is enabled and a Places API key is configured.
- Data sent: Google Places API key and Google Place ID.
- Terms: https://cloud.google.com/maps-platform/terms
- Privacy policy: https://policies.google.com/privacy

== Requirements ==

- WordPress 6.0+
- PHP 7.4+
- Outbound HTTP access from server
- Apify account + API token

Optional:
- Google Places API key (for optional Places summary enrichment)

== Quick start ==

1. Open **Business Reviews Lite** in wp-admin.
2. Add your **Apify token**.
3. Add Google source:
	- Place ID and/or Google Maps URL
4. (Optional) add Google Places API key and enable Places summary.
5. Click **Fetch Google reviews now**.
6. Add shortcode to a page/post:
	- `[dsbrsl_google_reviews_slider]`

== Installation ==

1. Upload plugin files to `/wp-content/plugins/dope-studio-business-reviews-slider-lite`.
2. Activate the plugin through the WordPress plugins screen.
3. Open **Business Reviews Lite** in wp-admin.
4. Add your Apify token and Google source data.
5. Click **Fetch Google reviews now**.

== Admin settings overview ==

### Data source
- Enable Google reviews
- Apify token
- Google Place ID
- Google Maps URL
- Max reviews to fetch
- Language

### Optional Places summary
- Google Places API key
- Use Places summary toggle

### Slider defaults
- Theme
- Autoplay + interval
- Infinite loop
- Dots
- Swipe
- Slides per view (mobile/tablet/desktop)
- Slider title
- Display limit
- Show summary toggle
- Show “Read on Google” link toggle

### Rating and review counters
- Rating mode (Auto / Manual)
- Manual rating value
- Review count mode (Fetched / Custom)
- Custom review count

### Filters
- Minimum rating
- Hide textless reviews

== Usage ==

Shortcode:
`[dsbrsl_google_reviews_slider]`

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
- `show_no_comment="1"`

== Storage ==

The plugin stores settings and cached review data in WordPress options:

- `dsbrsl_settings`
- `dsbrsl_reviews_cache`

== Troubleshooting ==

### Fetch timeout
- Try lowering max reviews
- Retry after a short wait
- Check host outbound request limits/timeouts

### No reviews displayed
- Confirm shortcode is `[dsbrsl_google_reviews_slider]`
- Ensure Google reviews are enabled in plugin settings
- Run a manual fetch and verify cache count in admin
- Verify Place ID / Maps URL is valid

### Header rating/count differs from fetched reviews
- If Places summary is enabled, header can use Places API values
- Disable Places summary to use fetched review-derived values

== FAQ ==

= Does Lite include scheduled cron fetch? =
Yes. Lite includes cron auto-fetch options (enable, frequency, start time, and scope), matching Pro for Google features.

= Can I show unlimited reviews? =
Yes. Keep fetch/display limits empty or set as needed.

= Does Lite support multiple review platforms? =
Lite is Google-only. Pro includes additional platforms and controls and is completely free.

== Changelog ==

= 1.0.0 =
* Initial Lite release with Google reviews only.
* Manual fetch workflow and responsive slider controls.
* Optional Places API summary integration.
