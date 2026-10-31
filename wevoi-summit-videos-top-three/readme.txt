=== WeVoi Summit Videos Top Three ===
Contributors: kenan
Tags: wevoi, summit, videos, carousel, competition, results
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display top-three videos from the most recent completed WeVoi Summit in a beautiful sliding carousel format on your homepage.

== Description ==

The WeVoi Summit Videos Top Three plugin displays the top-three videos from each age category of the most recent completed WeVoi Summit in an attractive sliding carousel format. Perfect for showcasing competition results on your homepage.

**Features:**

* Automatically fetches the most recent completed WeVoi Summit data
* Displays top-three videos from each age category
* Beautiful responsive carousel with Swiper.js
* YouTube video integration with modal playback
* Customizable settings (autoplay, speed, slides per view)
* Admin settings page for easy configuration
* Mobile-responsive design
* Flag icons for participant countries
* Medal display (Gold, Silver, Bronze, Success)
* Points and ranking information

**Shortcode Usage:**

`[wevoi_summit_videos]`

**Shortcode Parameters:**

* `competition_code` - Specific competition code (optional)
* `show_title` - Show competition title (true/false)
* `show_date` - Show competition date (true/false)
* `autoplay` - Enable autoplay (true/false)
* `speed` - Autoplay speed in milliseconds
* `slides_per_view` - Number of slides visible at once
* `space_between` - Space between slides in pixels

**Example:**

`[wevoi_summit_videos autoplay="true" speed="4000" slides_per_view="3"]`

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wevoi-summit-videos-top-three` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the `[wevoi_summit_videos]` shortcode on any page or post where you want to display the summit videos.
4. Configure settings in Settings > WeVoi Summit Videos.

== Frequently Asked Questions ==

= Does this plugin work with any WeVoi competition? =

Yes, the plugin automatically detects the most recent completed WeVoi Summit and displays the top-three videos from each age category.

= Can I customize the appearance? =

Yes, the plugin includes extensive CSS customization options and you can override the default styles in your theme.

= Does it work on mobile devices? =

Yes, the plugin is fully responsive and includes mobile-optimized video playback.

= Can I show videos from a specific competition? =

Yes, you can use the `competition_code` parameter in the shortcode to display videos from a specific competition.

== Screenshots ==

1. Carousel display with video thumbnails
2. Video modal playback
3. Admin settings page
4. Mobile responsive view

== Changelog ==

= 1.0.0 =
* Initial release
* Carousel display of top-three summit videos
* YouTube integration
* Admin settings page
* Mobile responsive design
* Flag icons and medal display

== Upgrade Notice ==

= 1.0.0 =
Initial release of WeVoi Summit Videos Top Three plugin.
