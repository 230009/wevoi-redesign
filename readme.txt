=== WeVoi Redesign ===
Version: 0.1.24
Requires at least: 5.8
Requires PHP: 7.2
License: GPL-2.0-or-later

Renders the redesigned WeVoi website through the [wevoi_site] shortcode. The plugin does not change the theme, existing pages or other plugins.

The custom WeVoi Redesign account-creation handler is enabled only when the WordPress home URL is exactly http://wevoi-redesign.local. It remains disabled on all other sites, where the Sign Up link uses the site's existing WordPress registration URL. The custom handler uses the Local WeVoi country and school tables and should not be enabled on production. The Login page continues to use native WordPress authentication. The redesigned registration styles load only on the standard WordPress registration screen and do not change its form or processing.

On pages containing [wevoi_site] (unless chrome="no" is set), the redesign hides the theme header and page title without affecting the WordPress admin toolbar. Authenticated users see a role-appropriate Dashboard link and Log Out; guest navigation is unchanged.

Successful logins through the WeVoi Redesign form default to the authenticated user's role-specific dashboard. A valid explicit redirect destination takes precedence; invalid destinations safely fall back to the role-based route. Login submissions are handled before template rendering, and the handler checks whether headers have already been sent before redirecting. If WordPress cannot send the redirect header, the form displays a safe browser-side redirect with a manual link instead of a blank page. Other WordPress login flows are not changed.

== Usage ==
1. Plugins > Add New > Upload Plugin > wevoi-redesign-0.1.24.zip > Activate.
2. Create or choose a WordPress page for the redesign.
3. Add a Shortcode block containing: [wevoi_site]
4. Publish the page. Internal navigation uses ?wv=<page> on the same URL, e.g. /redesign/?wv=rounds

== Attributes ==
[wevoi_site page="rounds"]    start page (default: home)
[wevoi_site fullwidth="yes"]  break out of a narrow theme content column
[wevoi_site chrome="no"]      hide the WeVoi header/footer (use the theme's)

== Page slugs ==
home, rounds, wevoi-spark, wevoi-reload, wevoi-summit, forum, how-it-works, voicehub, speech-guidelines, evaluation-criteria, video-recording-guidelines, how-to-prepare, ambassadors-partners, partner-with-wevoi, student-ambassadors, about, workshops, results, blog, startervoi, youngstervoi, juniorvoi, teenvoi, spark-poster, login, signup, supervisor-dashboard, parent-dashboard, student-dashboard, submissions, bulk-registration

== Account dashboards ==
The supervisor-dashboard, parent-dashboard, student-dashboard, submissions, and bulk-registration routes render existing approved WeVoi shortcodes inside the redesign layout. The corresponding shortcode-provider plugins must be installed and active. Each dashboard route requires its matching user role; bulk registration is restricted to supervisors and agencies.

== Developer hook ==
add_filter( 'wevoi_rd_page_url', fn( $url, $slug ) => $url, 10, 2 ); // map a slug to a real WP page URL later.

== Release package ==
Run bash build-release.sh --check to verify the explicit production-file allowlist without creating an archive. Run bash build-release.sh to create the ZIP next to the plugin directory. The archive excludes nested reference plugins, local-only helpers and operating-system metadata.

== Removal ==
Deactivate and delete the plugin.
