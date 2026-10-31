<?php
/**
 * Plugin Name:       WeVoi Redesign
 * Description:       Renders the redesigned WeVoi website (home, rounds, guidelines, forum, VoiceHub, etc.) through the [wevoi_site] shortcode. Self-contained: it does not modify the theme, existing pages or any other plugin.
 * Version:           0.1.24
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            WeVoi
 * License:           GPL-2.0-or-later
 * Text Domain:       wevoi-redesign
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WEVOI_RD_VERSION', '0.1.24' );
define( 'WEVOI_RD_DIR', plugin_dir_path( __FILE__ ) );
define( 'WEVOI_RD_URL', plugin_dir_url( __FILE__ ) );

final class WeVoi_Redesign {

	const SHORTCODE = 'wevoi_site';
	const QUERY_VAR = 'wv';

	/** slug => browser title */
	private static $pages = array(
		'about' => 'About | WeVoi',
		'ambassadors-partners' => 'Ambassadors &amp; Partners | WeVoi',
		'blog' => 'Blog | WeVoi',
		'evaluation-criteria' => 'Evaluation Criteria | WeVoi',
		'forum' => 'WeVoi Forum | WeVoi',
		'how-it-works' => 'How It Works | WeVoi',
		'how-to-prepare' => 'How to Prepare | WeVoi',
		'home' => 'WeVoi | From Thought to Voice',
		'juniorvoi' => 'JuniorVoi | WeVoi',
		'login' => 'Log In | WeVoi',
		'partner-with-wevoi' => 'Partner With WeVoi | WeVoi',
		'results' => 'Results | WeVoi',
		'rounds' => 'Rounds | WeVoi',
		'signup' => 'Sign Up | WeVoi',
		'spark-poster' => 'Spark Poster | WeVoi',
		'speech-guidelines' => 'Speech Guidelines | WeVoi',
		'startervoi' => 'StarterVoi | WeVoi',
		'submissions' => 'Submissions | WeVoi',
		'supervisor-dashboard' => 'Supervisor Dashboard | WeVoi',
		'parent-dashboard' => 'Parent Dashboard | WeVoi',
		'student-dashboard' => 'Student Dashboard | WeVoi',
		'register' => 'Register | WeVoi',
		'bulk-registration' => 'Bulk Registration | WeVoi',
		'student-ambassadors' => 'Student Ambassadors | WeVoi',
		'teenvoi' => 'TeenVoi | WeVoi',
		'video-recording-guidelines' => 'Video Recording Guidelines | WeVoi',
		'voicehub' => 'VoiceHub | WeVoi',
		'wevoi-reload' => 'WeVoi Reload | WeVoi',
		'wevoi-spark' => 'WeVoi Spark | WeVoi',
		'wevoi-summit' => 'WeVoi Summit | WeVoi',
		'workshops' => 'Workshops | WeVoi',
		'youngstervoi' => 'YoungsterVoi | WeVoi'
	);

	/** Internal route => approved shortcode and eligible roles. */
	private static $shortcode_routes = array(
		'supervisor-dashboard' => array(
			'tag'   => 'wpie_supervisor_profile',
			'roles' => array( 'supervisor' ),
		),
		'parent-dashboard' => array(
			'tag'   => 'wpie_parent_profile',
			'roles' => array( 'parent' ),
		),
		'student-dashboard' => array(
			'tag'   => 'wevoi_student_dashboard',
			'roles' => array( 'student' ),
		),
		'submissions' => array(
			'tag'   => 'wevoi_my_competitions',
			'roles' => array( 'administrator', 'agency', 'judge', 'parent', 'student', 'supervisor' ),
		),
		'bulk-registration' => array(
			'tag'   => 'wevoi_bulk_register_form',
			'roles' => array( 'agency', 'supervisor' ),
		),
	);

	/** URL of the WP page that holds the shortcode (set while rendering). */
	private static $base_url = '';
	private static $signup_errors = array();
	private static $signup_values = array();
	private static $signup_countries = array();
	private static $signup_schools = array();
	private static $signup_lookups_loaded = false;
	private static $signup_lookup_error = '';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_shortcode_route_assets' ), 20 );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_registration_styles' ) );
		add_action( 'wp', array( __CLASS__, 'handle_login_submission' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_signup_submission' ), 1 );
		add_filter( 'document_title_parts', array( __CLASS__, 'filter_title' ) );
		add_filter( 'body_class', array( __CLASS__, 'filter_body_class' ) );
	}

	/** Pages in the redesign (slug => title). */
	public static function pages() {
		return self::$pages;
	}

	/**
	 * Slug requested through ?wv=, validated against the whitelist.
	 *
	 * @return string
	 */
	private static function requested_slug( $default = 'home' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing, whitelisted below.
		$slug = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : '';
		if ( $slug && isset( self::$pages[ $slug ] ) ) {
			return $slug;
		}
		return isset( self::$pages[ $default ] ) ? $default : 'home';
	}

	private static function custom_signup_enabled() {
		return 'http://wevoi-redesign.local' === untrailingslashit( home_url( '/' ) );
	}

	private static function post_has_shortcode() {
		$post = get_post();
		return $post && has_shortcode( $post->post_content, self::SHORTCODE );
	}

	public static function register_assets() {
		wp_register_style( 'wevoi-redesign', WEVOI_RD_URL . 'assets/css/wevoi.css', array(), WEVOI_RD_VERSION );
		wp_register_script( 'wevoi-redesign', WEVOI_RD_URL . 'assets/js/wevoi.js', array(), WEVOI_RD_VERSION, true );
		// Load in <head> only on pages that contain the shortcode.
		if ( is_singular() && self::post_has_shortcode() ) {
			wp_enqueue_style( 'wevoi-redesign' );
			wp_enqueue_script( 'wevoi-redesign' );
		}
	}

	/**
	 * Enqueue shortcode-provider assets before wp_head prints styles and scripts.
	 */
	public static function enqueue_shortcode_route_assets() {
		if ( ! is_singular() || ! self::post_has_shortcode() ) {
			return;
		}

		$slug = self::requested_slug();
		if ( ! isset( self::$shortcode_routes[ $slug ] ) || ! self::route_user_is_allowed( $slug ) ) {
			return;
		}

		$route = self::$shortcode_routes[ $slug ];
		if ( ! shortcode_exists( $route['tag'] ) ) {
			return;
		}

		if ( 'wpie_supervisor_profile' === $route['tag'] ) {
			wp_enqueue_style( 'wpie-supervisor-profile' );
			wp_enqueue_script( 'jquery' );
			return;
		}

		if ( 'wpie_parent_profile' === $route['tag'] ) {
			wp_enqueue_style( 'wpie-parent-profile' );
			wp_enqueue_script( 'jquery' );
			return;
		}

		if ( ! defined( 'WEVOI_REGISTER_PLUGIN_URL' ) || ! defined( 'WEVOI_REGISTER_VERSION' ) ) {
			return;
		}

		if ( 'wevoi_my_competitions' === $route['tag'] ) {
			wp_enqueue_style(
				'wevoi-my-competitions-style',
				WEVOI_REGISTER_PLUGIN_URL . 'public/css/my-competitions.css',
				array(),
				WEVOI_REGISTER_VERSION
			);
			wp_enqueue_script(
				'wevoi-my-competitions',
				WEVOI_REGISTER_PLUGIN_URL . 'public/js/my-competitions-admin.js',
				array( 'jquery' ),
				WEVOI_REGISTER_VERSION,
				true
			);
			wp_localize_script(
				'wevoi-my-competitions',
				'wevoiRegister',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wevoi_register_nonce' ),
				)
			);
			return;
		}

		if ( 'wevoi_bulk_register_form' === $route['tag'] ) {
			wp_enqueue_style(
				'wevoi-register-style',
				WEVOI_REGISTER_PLUGIN_URL . 'public/css/form-styles.css',
				array(),
				WEVOI_REGISTER_VERSION
			);
			wp_enqueue_script( 'jquery' );
		}
	}

	private static function route_user_is_allowed( $slug ) {
		if ( ! is_user_logged_in() || ! isset( self::$shortcode_routes[ $slug ] ) ) {
			return false;
		}

		$user  = wp_get_current_user();
		$roles = isset( $user->roles ) ? (array) $user->roles : array();

		return (bool) array_intersect( self::$shortcode_routes[ $slug ]['roles'], $roles );
	}

	private static function render_shortcode_route( $slug, $page_html ) {
		$route = self::$shortcode_routes[ $slug ];
		if ( false === strpos( $page_html, '{{dashboard_content}}' ) ) {
			return '<section class="wv-dashboard-page wv-section wv-soft"><div class="wv-container"><div class="wv-dashboard-notice" role="alert"><h2>' . esc_html__( 'Page temporarily unavailable', 'wevoi-redesign' ) . '</h2><p>' . esc_html__( 'The dashboard page template is not available.', 'wevoi-redesign' ) . '</p></div></div></section>';
		}

		if ( ! is_user_logged_in() ) {
			$message  = '<div class="wv-dashboard-notice" role="status"><h2>' . esc_html__( 'Log in to continue', 'wevoi-redesign' ) . '</h2>';
			$message .= '<p>' . esc_html__( 'Please log in with an eligible WeVoi account to view this page.', 'wevoi-redesign' ) . '</p>';
			$message .= '<a class="wv-btn wv-primary" href="' . esc_url( wp_login_url( self::url( $slug ) ) ) . '">' . esc_html__( 'Log In', 'wevoi-redesign' ) . '</a></div>';
			return str_replace( '{{dashboard_content}}', $message, $page_html );
		}

		if ( ! self::route_user_is_allowed( $slug ) ) {
			$message = '<div class="wv-dashboard-notice" role="alert"><h2>' . esc_html__( 'Access denied', 'wevoi-redesign' ) . '</h2><p>' . esc_html__( 'This page is not available for your account role.', 'wevoi-redesign' ) . '</p></div>';
			return str_replace( '{{dashboard_content}}', $message, $page_html );
		}

		if ( ! shortcode_exists( $route['tag'] ) ) {
			$message = '<div class="wv-dashboard-notice" role="status"><h2>' . esc_html__( 'Page temporarily unavailable', 'wevoi-redesign' ) . '</h2><p>' . esc_html__( 'The required WeVoi feature is not currently available. Please contact the site administrator.', 'wevoi-redesign' ) . '</p></div>';
			return str_replace( '{{dashboard_content}}', $message, $page_html );
		}

		$content = do_shortcode( '[' . $route['tag'] . ']' );
		return str_replace( '{{dashboard_content}}', '<div class="wv-shortcode-content">' . $content . '</div>', $page_html );
	}

	public static function enqueue_registration_styles() {
		global $pagenow;

		if (
			'wp-login.php' !== $pagenow ||
			! isset( $_GET['action'] ) ||
			! is_string( $_GET['action'] ) ||
			'register' !== sanitize_key( wp_unslash( $_GET['action'] ) )
		) {
			return;
		}

		wp_enqueue_style(
			'wevoi-register',
			WEVOI_RD_URL . 'assets/css/wevoi-register.css',
			array(),
			WEVOI_RD_VERSION
		);
	}

	public static function filter_title( $parts ) {
		if ( is_singular() && self::post_has_shortcode() && isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$slug           = self::requested_slug();
			$parts['title'] = self::$pages[ $slug ];
		}
		return $parts;
	}

	public static function filter_body_class( $classes ) {
		if ( is_singular() && self::post_uses_redesign_chrome() ) {
			$classes[] = 'wevoi-redesign-page';
		}
		return $classes;
	}

	private static function post_uses_redesign_chrome() {
		$post = get_post();
		if ( ! $post || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return false;
		}

		if ( ! preg_match_all( '/\[wevoi_site\b([^\]]*)\]/', $post->post_content, $matches ) ) {
			return false;
		}

		foreach ( $matches[1] as $attribute_text ) {
			$attributes = shortcode_parse_atts( $attribute_text );
			if ( ! isset( $attributes['chrome'] ) || 'no' !== strtolower( $attributes['chrome'] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function account_navigation() {
		if ( ! is_user_logged_in() ) {
			return '<div class="wv-navitem"><a class="wv-navlink" href="{{url:login}}">Log In</a></div><div class="wv-navitem"><a class="wv-navlink" href="{{url:signup}}">Sign Up</a></div>';
		}

		$user  = wp_get_current_user();
		$roles = isset( $user->roles ) ? (array) $user->roles : array();
		$destinations = array(
			'supervisor'    => 'supervisor-dashboard',
			'parent'        => 'parent-dashboard',
			'student'       => 'student-dashboard',
			'agency'        => 'bulk-registration',
			'administrator' => 'submissions',
			'judge'          => 'submissions',
		);
		$dashboard = 'submissions';
		foreach ( $destinations as $role => $slug ) {
			if ( in_array( $role, $roles, true ) ) {
				$dashboard = $slug;
				break;
			}
		}

		return '<div class="wv-navitem"><a class="wv-navlink" href="' . esc_url( self::url( $dashboard ) ) . '">Dashboard</a></div><div class="wv-navitem"><a class="wv-navlink" href="' . esc_url( wp_logout_url( self::url( 'home' ) ) ) . '">Log Out</a></div>';
	}

	/** Public URL of an internal page, e.g. url( 'rounds' ) => /test/?wv=rounds */
	public static function url( $slug ) {
		$base = self::$base_url ? self::$base_url : get_permalink();
		$url  = ( 'home' === $slug ) ? remove_query_arg( self::QUERY_VAR, $base ) : add_query_arg( self::QUERY_VAR, $slug, $base );
		return apply_filters( 'wevoi_rd_page_url', $url, $slug );
	}

	private static function read( $relative ) {
		$file = WEVOI_RD_DIR . 'templates/' . $relative;
		return is_readable( $file ) ? file_get_contents( $file ) : '';
	}

	private static function login_redirect_url( $user, $post ) {
		$roles = isset( $user->roles ) ? (array) $user->roles : array();
		$destinations = array(
			'student'    => 'student-dashboard',
			'supervisor' => 'supervisor-dashboard',
			'parent'     => 'parent-dashboard',
			'agency'     => 'bulk-registration',
		);
		$slug = 'home';
		foreach ( $destinations as $role => $destination ) {
			if ( in_array( $role, $roles, true ) ) {
				$slug = $destination;
				break;
			}
		}

		$default = add_query_arg( 'wv', $slug, get_permalink( $post ) );
		$redirect_to = '';
		if ( isset( $_POST['redirect_to'] ) && is_string( $_POST['redirect_to'] ) ) {
			$redirect_to = wp_unslash( $_POST['redirect_to'] );
		} elseif ( isset( $_GET['redirect_to'] ) && is_string( $_GET['redirect_to'] ) ) {
			$redirect_to = wp_unslash( $_GET['redirect_to'] );
		}

		if ( self::is_login_route_redirect( $redirect_to, $post ) ) {
			$redirect_to = '';
		}

		return '' === $redirect_to ? $default : wp_validate_redirect( $redirect_to, $default );
	}

	private static function is_login_route_redirect( $url, $login_post = null ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return true;
		}

		$validated = wp_validate_redirect( $url, '' );
		if ( '' === $validated ) {
			return true;
		}

		$parsed = function_exists( 'wp_parse_url' ) ? wp_parse_url( $validated ) : parse_url( $validated );
		if ( ! is_array( $parsed ) ) {
			return true;
		}

		$path = isset( $parsed['path'] ) ? trim( (string) $parsed['path'], '/' ) : '';
		if ( 'wp-login.php' === $path ) {
			return true;
		}

		$query = array();
		if ( isset( $parsed['query'] ) ) {
			parse_str( (string) $parsed['query'], $query );
		}
		if ( isset( $query['wv'] ) && 'login' === sanitize_key( (string) $query['wv'] ) ) {
			return true;
		}

		if ( $login_post instanceof WP_Post ) {
			$login_page = function_exists( 'wp_parse_url' )
				? wp_parse_url( get_permalink( $login_post ) )
				: parse_url( get_permalink( $login_post ) );
			if ( is_array( $login_page ) ) {
				$login_path = isset( $login_page['path'] ) ? trim( (string) $login_page['path'], '/' ) : '';
				$redirect_query = isset( $query['wv'] ) ? sanitize_key( (string) $query['wv'] ) : '';
				if ( $path === $login_path && '' === $redirect_query ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function login_redirect_field() {
		if ( ! isset( $_GET['redirect_to'] ) || ! is_string( $_GET['redirect_to'] ) ) {
			return '';
		}

		$redirect_to = wp_unslash( $_GET['redirect_to'] );
		if ( self::is_login_route_redirect( $redirect_to ) ) {
			return '';
		}

		return '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect_to ) . '">';
	}

	public static function handle_login_submission() {
		if (
			'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ||
			! isset( $_POST['wevoi_login_form'] ) ||
			! is_string( $_POST['wevoi_login_form'] ) ||
			'1' !== wp_unslash( $_POST['wevoi_login_form'] ) ||
			'login' !== self::requested_slug()
		) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
		}

		$nonce = isset( $_POST['wevoi_login_nonce'] ) && is_string( $_POST['wevoi_login_nonce'] )
			? wp_unslash( $_POST['wevoi_login_nonce'] )
			: '';
		if ( ! wp_verify_nonce( $nonce, 'wevoi_redesign_login' ) ) {
			self::redirect_login_error( $post, 'invalid_request' );
		}

		$username = isset( $_POST['log'] ) && is_string( $_POST['log'] )
			? sanitize_text_field( wp_unslash( $_POST['log'] ) )
			: '';
		$password = isset( $_POST['pwd'] ) && is_string( $_POST['pwd'] )
			? wp_unslash( $_POST['pwd'] )
			: '';

		if ( '' === $username || '' === $password ) {
			self::redirect_login_error( $post, 'missing_credentials' );
		}

		$remember = isset( $_POST['rememberme'] ) && is_string( $_POST['rememberme'] )
			&& '1' === wp_unslash( $_POST['rememberme'] );
		$user     = wp_signon(
			array(
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => $remember,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			$error_code = $user->get_error_code();
			if ( in_array( $error_code, array( 'empty_username', 'empty_password' ), true ) ) {
				self::redirect_login_error( $post, 'missing_credentials' );
			}
			if ( in_array( $error_code, array( 'invalid_username', 'incorrect_password', 'invalid_email', 'authentication_failed' ), true ) ) {
				self::redirect_login_error( $post, 'invalid_credentials' );
			}
			self::redirect_login_error( $post, 'login_failed' );
		}

		$redirect_url = self::login_redirect_url( $user, $post );
		$fallback = self::login_redirect_response( $redirect_url );
		if ( '' !== $fallback ) {
			echo $fallback; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the response is built from a validated and escaped URL.
		}
		exit;
	}

	private static function redirect_login_error( $post, $error_code ) {
		$fallback = self::login_redirect_response(
			add_query_arg(
				array(
					'wv'               => 'login',
					'wevoi_login_error' => $error_code,
				),
				get_permalink( $post )
			)
		);
		if ( '' !== $fallback ) {
			echo $fallback; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the response is built from a validated and escaped URL.
		}
		exit;
	}

	private static function login_redirect_response( $url ) {
		$url = wp_validate_redirect( $url, home_url( '/' ) );
		$headers_file = '';
		$headers_line = 0;
		if ( headers_sent( $headers_file, $headers_line ) ) {
			error_log(
				sprintf(
					'WeVoi Redesign: login redirect headers were already sent at %s:%d; using an in-page redirect fallback.',
					$headers_file,
					$headers_line
				)
			);
			return self::login_redirect_fallback_html( $url );
		}

		if ( wp_safe_redirect( $url, 302, 'WeVoi Redesign' ) ) {
			return '';
		}

		error_log( 'WeVoi Redesign: safe login redirect returned false; using an in-page redirect fallback.' );
		return self::login_redirect_fallback_html( $url );
	}

	private static function login_redirect_fallback_html( $url ) {
		$url = wp_validate_redirect( $url, home_url( '/' ) );
		$encoded_url = wp_json_encode( $url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

		return '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . esc_attr( $url ) . '"><title>' . esc_html__( 'Continuing to WeVoi', 'wevoi-redesign' ) . '</title></head><body><p>' . esc_html__( 'Your request was received. If you are not redirected, continue to WeVoi.', 'wevoi-redesign' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Continue', 'wevoi-redesign' ) . '</a></p><script>window.location.replace(' . $encoded_url . ');</script></body></html>';
	}

	private static function signup_post_value( $key ) {
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] )
			? trim( wp_unslash( $_POST[ $key ] ) )
			: '';
	}

	private static function load_signup_lookups() {
		if ( self::$signup_lookups_loaded ) {
			return;
		}

		self::$signup_lookups_loaded = true;
		global $wpdb;
		self::$signup_countries = $wpdb->get_results( 'SELECT countryid, countryname FROM wpie_wevoi_countries ORDER BY countryname ASC' );
		self::$signup_schools   = $wpdb->get_results( 'SELECT schoolid, schoolname, countryid FROM wpie_wevoi_schools ORDER BY schoolname ASC' );

		if ( ! is_array( self::$signup_countries ) || ! is_array( self::$signup_schools ) ) {
			self::$signup_lookup_error = __( 'The country and school lists are temporarily unavailable. Please try again later.' );
			self::$signup_countries    = array();
			self::$signup_schools      = array();
		} elseif ( empty( self::$signup_countries ) ) {
			self::$signup_lookup_error = __( 'Registration is unavailable because no countries are configured.' );
		}
	}

	private static function signup_role_is_safe( $role ) {
		$approved_roles = array( 'student', 'supervisor', 'judge', 'parent', 'agency' );
		if ( ! in_array( $role, $approved_roles, true ) ) {
			return false;
		}

		$role_object = get_role( $role );
		if ( ! $role_object || empty( $role_object->capabilities['read'] ) ) {
			return false;
		}

		foreach ( $role_object->capabilities as $capability => $enabled ) {
			if ( $enabled && 'read' !== $capability ) {
				return false;
			}
		}

		return true;
	}

	private static function signup_kenan_hooks_available() {
		return defined( 'WEVOI_SIGNUP_VERSION' )
			&& function_exists( 'register_new_user' )
			&& false !== has_filter( 'registration_errors' )
			&& false !== has_action( 'user_register' );
	}

	private static function signup_availability_error() {
		if ( ! get_option( 'users_can_register' ) ) {
			return __( 'Account registration is currently unavailable.' );
		}

		if ( ! self::signup_kenan_hooks_available() ) {
			return __( 'Registration is unavailable because the WeVoi Signup service is not active.' );
		}

		self::load_signup_lookups();
		if ( self::$signup_lookup_error ) {
			return self::$signup_lookup_error;
		}

		foreach ( array( 'student', 'supervisor', 'judge', 'parent', 'agency' ) as $role ) {
			if ( ! self::signup_role_is_safe( $role ) ) {
				return __( 'Registration is unavailable because an approved account role is missing or has unsafe permissions.' );
			}
		}

		return '';
	}

	private static function register_signup_user( $values ) {
		if ( ! self::signup_kenan_hooks_available() ) {
			return new WP_Error( 'signup_unavailable', __( 'Registration is unavailable because the WeVoi Signup service is not active.' ) );
		}

		return register_new_user( $values['user_login'], $values['user_email'] );
	}

	public static function handle_signup_submission() {
		if ( ! self::custom_signup_enabled() ) {
			return;
		}

		if (
			'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ||
			! isset( $_POST['wevoi_signup_form'] ) ||
			! is_string( $_POST['wevoi_signup_form'] ) ||
			'1' !== wp_unslash( $_POST['wevoi_signup_form'] ) ||
			'signup' !== self::requested_slug()
		) {
			return;
		}

		$post = get_queried_object();
		if ( ! ( $post instanceof WP_Post ) || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
		}

		$nonce = self::signup_post_value( 'wevoi_signup_nonce' );
		if ( ! wp_verify_nonce( $nonce, 'wevoi_redesign_signup' ) ) {
			self::$signup_errors[] = __( 'Your registration request expired. Please refresh the page and try again.' );
			return;
		}

		self::$signup_values = array(
			'role'         => sanitize_key( self::signup_post_value( 'role' ) ),
			'user_login'   => sanitize_user( self::signup_post_value( 'user_login' ), true ),
			'user_email'   => sanitize_email( self::signup_post_value( 'user_email' ) ),
			'first_name'   => sanitize_text_field( self::signup_post_value( 'first_name' ) ),
			'last_name'    => sanitize_text_field( self::signup_post_value( 'last_name' ) ),
			'birth_date'   => sanitize_text_field( self::signup_post_value( 'birth_date' ) ),
			'gender'       => sanitize_text_field( self::signup_post_value( 'gender' ) ),
			'countryid'    => sanitize_text_field( self::signup_post_value( 'countryid' ) ),
			'schoolid'     => sanitize_text_field( self::signup_post_value( 'schoolid' ) ),
			'new_school'   => sanitize_text_field( self::signup_post_value( 'new_school' ) ),
			'company_name' => sanitize_text_field( self::signup_post_value( 'company_name' ) ),
		);
		$values = self::$signup_values;

		if ( is_user_logged_in() ) {
			self::$signup_errors[] = __( 'You are already logged in.' );
			return;
		}

		$availability_error = self::signup_availability_error();
		if ( $availability_error ) {
			self::$signup_errors[] = $availability_error;
			return;
		}

		$role = $values['role'];
		if ( ! self::signup_role_is_safe( $role ) ) {
			self::$signup_errors[] = __( 'Select one of the approved account roles.' );
		}

		if ( '' === $values['user_login'] || ! validate_username( $values['user_login'] ) || strlen( $values['user_login'] ) > 60 ) {
			self::$signup_errors[] = __( 'Enter a valid username of no more than 60 characters.' );
		} elseif ( username_exists( $values['user_login'] ) ) {
			self::$signup_errors[] = __( 'That username is already in use. Choose another username.' );
		}

		if ( ! is_email( $values['user_email'] ) ) {
			self::$signup_errors[] = __( 'Enter a valid email address.' );
		} elseif ( email_exists( $values['user_email'] ) ) {
			self::$signup_errors[] = __( 'That email address is already registered. Log in or use another email address.' );
		}

		if ( '' === $values['first_name'] ) {
			self::$signup_errors[] = __( 'Enter your first name.' );
		}
		if ( '' === $values['last_name'] ) {
			self::$signup_errors[] = __( 'Enter your last name.' );
		}

		$country_id = filter_var( $values['countryid'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		if ( false === $country_id || ! self::signup_country_exists( $country_id ) ) {
			self::$signup_errors[] = __( 'Select a valid country.' );
		}

		if ( 'student' === $role ) {
			if ( ! self::signup_valid_birth_date( $values['birth_date'] ) ) {
				self::$signup_errors[] = __( 'Enter a valid birth date. Students must be between 6 and 24 years old.' );
			}
			if ( ! in_array( $values['gender'], array( 'Male', 'Female' ), true ) ) {
				self::$signup_errors[] = __( 'Select a gender.' );
			}
		}

		$needs_school = in_array( $role, array( 'student', 'supervisor', 'judge' ), true );
		$school_id    = 0;
		if ( $needs_school && false !== $country_id ) {
			if ( 'other' === $values['schoolid'] ) {
				if ( '' === $values['new_school'] ) {
					self::$signup_errors[] = __( 'Enter the name of your school.' );
				} elseif ( function_exists( 'mb_strlen' ) ? mb_strlen( $values['new_school'], 'UTF-8' ) > 191 : strlen( $values['new_school'] ) > 191 ) {
					self::$signup_errors[] = __( 'The school name must be no longer than 191 characters.' );
				}
			} else {
				$school_id = filter_var( $values['schoolid'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
				if ( false === $school_id || ! self::signup_school_matches_country( $school_id, $country_id ) ) {
					self::$signup_errors[] = __( 'Select a school that belongs to the selected country.' );
				}
			}
		}

		if ( 'agency' === $role && '' === $values['company_name'] ) {
			self::$signup_errors[] = __( 'Enter your company name.' );
		}

		if ( self::$signup_errors ) {
			return;
		}

		$user_id = self::register_signup_user( $values );
		if ( is_wp_error( $user_id ) || ! $user_id ) {
			$error_code = is_wp_error( $user_id ) ? $user_id->get_error_code() : '';
			if ( in_array( $error_code, array( 'existing_user_login', 'username_exists' ), true ) ) {
				self::$signup_errors[] = __( 'That username is already in use. Choose another username.' );
			} elseif ( in_array( $error_code, array( 'existing_user_email', 'email_exists' ), true ) ) {
				self::$signup_errors[] = __( 'That email address is already registered. Log in or use another email address.' );
			} else {
				self::$signup_errors[] = __( 'We could not create your account. Please check your details and try again.' );
			}
			return;
		}

		$meta = array(
			'first_name' => $values['first_name'],
			'last_name'  => $values['last_name'],
			'countryid'  => (string) $country_id,
		);
		if ( $needs_school ) {
			$meta['schoolid'] = 'other' === $values['schoolid'] ? '' : (string) $school_id;
			if ( 'other' === $values['schoolid'] ) {
				$meta['new_school'] = $values['new_school'];
			}
		}
		if ( 'student' === $role ) {
			$meta['birth_date'] = $values['birth_date'];
			$meta['gender']     = $values['gender'];
		}
		if ( 'agency' === $role ) {
			$meta['company_name'] = $values['company_name'];
		}

		$meta_saved = true;
		if ( 'other' === $values['schoolid'] && $needs_school ) {
			$result = update_user_meta( $user_id, 'new_school', $values['new_school'] );
			if ( false === $result && (string) get_user_meta( $user_id, 'new_school', true ) !== $values['new_school'] ) {
				$meta_saved = false;
			}
		}

		$display_name_result = wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => trim( $values['first_name'] . ' ' . $values['last_name'] ),
			)
		);
		if ( is_wp_error( $display_name_result ) || ! $display_name_result ) {
			$meta_saved = false;
		}

		if ( $needs_school && 'other' === $values['schoolid'] ) {
			$school_meta = get_user_meta( $user_id, 'schoolid', true );
			if ( '' === (string) $school_meta || '0' === (string) $school_meta ) {
				$meta_saved = false;
			}
			unset( $meta['schoolid'] );
		}

		foreach ( $meta as $key => $value ) {
			if ( (string) get_user_meta( $user_id, $key, true ) !== (string) $value ) {
				$meta_saved = false;
			}
		}

		if ( ! $meta_saved ) {
			error_log( 'WeVoi Redesign: registration profile metadata could not be fully saved for user ID ' . (int) $user_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'wv'          => 'signup',
					'wevoi_signup'=> $meta_saved ? 'success' : 'partial',
				),
				get_permalink( $post )
			)
		);
		exit;
	}

	private static function signup_country_exists( $country_id ) {
		global $wpdb;
		return (string) $country_id === (string) $wpdb->get_var(
			$wpdb->prepare( 'SELECT countryid FROM wpie_wevoi_countries WHERE countryid = %d LIMIT 1', $country_id )
		);
	}

	private static function signup_school_matches_country( $school_id, $country_id ) {
		global $wpdb;
		$match = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT schoolid FROM wpie_wevoi_schools WHERE schoolid = %d AND countryid = %d LIMIT 1',
				$school_id,
				$country_id
			)
		);
		return (string) $school_id === (string) $match;
	}

	private static function signup_valid_birth_date( $date ) {
		$birth_date = DateTime::createFromFormat( '!Y-m-d', $date );
		$errors     = DateTime::getLastErrors();
		if ( ! $birth_date || ( $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) || $birth_date->format( 'Y-m-d' ) !== $date ) {
			return false;
		}

		$today = new DateTime( 'today' );
		if ( $birth_date > $today ) {
			return false;
		}

		$age = $birth_date->diff( $today )->y;
		return $age >= 6 && $age <= 24;
	}

	private static function signup_field_value( $key ) {
		return isset( self::$signup_values[ $key ] ) ? esc_attr( self::$signup_values[ $key ] ) : '';
	}

	private static function signup_role_checked( $role ) {
		return isset( self::$signup_values['role'] ) && self::$signup_values['role'] === $role ? ' checked' : '';
	}

	private static function signup_country_options() {
		self::load_signup_lookups();
		$html = '';
		foreach ( self::$signup_countries as $country ) {
			$selected = isset( self::$signup_values['countryid'] ) && (string) self::$signup_values['countryid'] === (string) $country->countryid;
			$html .= '<option value="' . esc_attr( $country->countryid ) . '"' . selected( $selected, true, false ) . '>' . esc_html( $country->countryname ) . '</option>';
		}
		return $html;
	}

	private static function signup_school_options() {
		self::load_signup_lookups();
		$html = '';
		foreach ( self::$signup_schools as $school ) {
			$selected = isset( self::$signup_values['schoolid'], self::$signup_values['countryid'] )
				&& (string) self::$signup_values['schoolid'] === (string) $school->schoolid
				&& (string) self::$signup_values['countryid'] === (string) $school->countryid;
			$html .= '<option value="' . esc_attr( $school->schoolid ) . '" data-country="' . esc_attr( $school->countryid ) . '"' . selected( $selected, true, false ) . '>' . esc_html( $school->schoolname ) . '</option>';
		}
		$selected_other = isset( self::$signup_values['schoolid'] ) && 'other' === self::$signup_values['schoolid'];
		$html .= '<option value="other"' . selected( $selected_other, true, false ) . '>' . esc_html__( 'My school is not in the list' ) . '</option>';
		return $html;
	}

	private static function signup_notice() {
		if ( self::$signup_errors ) {
			$html = '<div class="wv-signup-error" role="alert"><strong>' . esc_html__( 'Please review the following:' ) . '</strong><ul>';
			foreach ( self::$signup_errors as $error ) {
				$html .= '<li>' . esc_html( $error ) . '</li>';
			}
			return $html . '</ul></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only success flag.
		if ( isset( $_GET['wevoi_signup'] ) && is_string( $_GET['wevoi_signup'] ) && 'partial' === wp_unslash( $_GET['wevoi_signup'] ) ) {
			return '<div class="wv-signup-error" role="alert"><strong>' . esc_html__( 'Your account was created, but some profile details could not be saved.' ) . '</strong><p>' . esc_html__( 'WordPress sent a secure password setup link to your email. Please contact the site administrator before continuing.' ) . '</p></div>';
		}

		if ( isset( $_GET['wevoi_signup'] ) && is_string( $_GET['wevoi_signup'] ) && 'success' === wp_unslash( $_GET['wevoi_signup'] ) ) {
			return '<div class="wv-signup-success" role="status"><strong>' . esc_html__( 'Your account has been created.' ) . '</strong><p>' . esc_html__( 'WordPress sent a secure password setup link to the email address you provided. Follow that link to choose your password.' ) . '</p><p>' . esc_html__( 'Already set your password?' ) . ' <a href="' . esc_url( self::url( 'login' ) ) . '">' . esc_html__( 'Log in' ) . '</a></p></div>';
		}

		$availability_error = self::signup_availability_error();
		if ( $availability_error ) {
			return '<div class="wv-signup-error" role="alert">' . esc_html( $availability_error ) . '</div>';
		}

		return '';
	}

	private static function login_error_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, allowlisted error code.
		$error = isset( $_GET['wevoi_login_error'] ) && is_string( $_GET['wevoi_login_error'] )
			? sanitize_key( wp_unslash( $_GET['wevoi_login_error'] ) )
			: '';
		$messages = array(
			'invalid_request'      => __( 'Your login request expired. Please refresh the page and try again.' ),
			'missing_credentials'  => __( 'Enter your username or email address and password.' ),
			'invalid_credentials'  => __( 'The username/email address or password is incorrect.' ),
			'login_failed'         => __( 'We could not log you in. Please try again or reset your password.' ),
		);

		if ( ! isset( $messages[ $error ] ) ) {
			return '';
		}

		return '<div class="wv-login-error" role="alert">' . esc_html( $messages[ $error ] ) . '</div>';
	}

	private static function resolve_tokens( $html ) {
		return preg_replace_callback(
			'/\{\{(url|asset|auth):([^}]+)\}\}/',
			function ( $m ) {
				if ( 'url' === $m[1] ) {
					if ( 'signup' === $m[2] && ! self::custom_signup_enabled() ) {
						return esc_url( wp_registration_url() );
					}
					return esc_url( self::url( $m[2] ) );
				}
				if ( 'auth' === $m[1] ) {
					if ( 'login' === $m[2] ) {
						return esc_url( wp_login_url() );
					}
					if ( 'register' === $m[2] ) {
						return esc_url( wp_registration_url() );
					}
					if ( 'lostpassword' === $m[2] ) {
						return esc_url( wp_lostpassword_url() );
					}
					return $m[0];
				}
				return esc_url( WEVOI_RD_URL . 'assets/img/' . $m[2] );
			},
			$html
		);
	}

	/**
	 * [wevoi_site]                    full site, routed with ?wv=slug
	 * [wevoi_site page="rounds"]      start on another page
	 * [wevoi_site fullwidth="yes"]    break out of a narrow theme content column
	 * [wevoi_site chrome="no"]        content only, without WeVoi header/footer
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'page'      => 'home',
				'fullwidth' => 'no',
				'chrome'    => 'yes',
			),
			$atts,
			self::SHORTCODE
		);

		wp_enqueue_style( 'wevoi-redesign' );
		wp_enqueue_script( 'wevoi-redesign' 		);

		self::$base_url = get_permalink();
		$slug           = self::requested_slug( sanitize_key( $atts['page'] ) );
		$chrome         = 'no' !== strtolower( $atts['chrome'] );
		$classes        = 'wevoi-app' . ( 'yes' === strtolower( $atts['fullwidth'] ) ? ' wv-fullwidth' : '' );

		$html  = '<div class="' . esc_attr( $classes ) . '" data-wv-page="' . esc_attr( $slug ) . '">';
		$header = $chrome ? str_replace( '{{auth_navigation}}', self::account_navigation(), self::read( 'partials/header.html' ) ) : '';
		$html .= $header;
		$page_html = self::read( 'pages/' . $slug . '.html' );
		if ( isset( self::$shortcode_routes[ $slug ] ) ) {
			$page_html = self::render_shortcode_route( $slug, $page_html );
		} elseif ( 'login' === $slug ) {
			$replacements = array(
				'{{login_nonce}}'        => wp_nonce_field( 'wevoi_redesign_login', 'wevoi_login_nonce', false, false ),
				'{{login_error}}'        => self::login_error_notice(),
				'{{login_redirect_to}}'  => self::login_redirect_field(),
			);
			$page_html = str_replace( array_keys( $replacements ), array_values( $replacements ), $page_html );
		} elseif ( 'register' === $slug ) {
			$registration_content = shortcode_exists( 'wevoi_register_form' )
				? do_shortcode( '[wevoi_register_form]' )
				: '<div class="wv-dashboard-notice" role="status"><h2>' . esc_html__( 'Registration temporarily unavailable', 'wevoi-redesign' ) . '</h2><p>' . esc_html__( 'The competition registration service is not currently available.', 'wevoi-redesign' ) . '</p></div>';
			$page_html = str_replace( '{{registration_content}}', $registration_content, $page_html );
		} elseif ( 'signup' === $slug && ! self::custom_signup_enabled() ) {
			$page_html = self::signup_disabled_page();
		} elseif ( 'signup' === $slug ) {
			$availability_error = self::signup_availability_error();
			$replacements       = array(
				'{{signup_nonce}}'            => wp_nonce_field( 'wevoi_redesign_signup', 'wevoi_signup_nonce', false, false ),
				'{{signup_timestamp}}'        => '<input type="hidden" name="timestamp" value="' . esc_attr( time() ) . '">',
				'{{signup_notice}}'           => self::signup_notice(),
				'{{signup_countries}}'        => self::signup_country_options(),
				'{{signup_schools}}'           => self::signup_school_options(),
				'{{signup_submit_disabled}}'   => $availability_error ? ' disabled' : '',
				'{{signup_checked:student}}'   => self::signup_role_checked( 'student' ),
				'{{signup_checked:supervisor}}'=> self::signup_role_checked( 'supervisor' ),
				'{{signup_checked:judge}}'     => self::signup_role_checked( 'judge' ),
				'{{signup_checked:parent}}'    => self::signup_role_checked( 'parent' ),
				'{{signup_checked:agency}}'    => self::signup_role_checked( 'agency' ),
				'{{signup_gender:Male}}'       => isset( self::$signup_values['gender'] ) && 'Male' === self::$signup_values['gender'] ? ' selected' : '',
				'{{signup_gender:Female}}'     => isset( self::$signup_values['gender'] ) && 'Female' === self::$signup_values['gender'] ? ' selected' : '',
			);
			foreach ( array( 'user_login', 'user_email', 'first_name', 'last_name', 'birth_date', 'new_school', 'company_name' ) as $field ) {
				$replacements[ '{{signup_value:' . $field . '}}' ] = self::signup_field_value( $field );
			}
			$page_html = str_replace( array_keys( $replacements ), array_values( $replacements ), $page_html );
		}
		$html .= '<main class="wv-main">' . $page_html . '</main>';
		$html .= $chrome ? self::read( 'partials/footer.html' ) : '';
		$html .= '</div>';

		return self::resolve_tokens( $html );
	}

	private static function signup_disabled_page() {
		return '<section class="wv-signup-page wv-section wv-soft"><div class="wv-container"><div class="wv-signup-heading wv-reveal"><div class="wv-eyebrow">' . esc_html__( 'Join the WeVoi community' ) . '</div><h1>' . esc_html__( 'Create your WeVoi account' ) . '</h1><p class="wv-lead">' . esc_html__( 'Account registration is handled through our existing registration service.' ) . '</p><p><a class="wv-btn wv-primary" href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'Continue to Sign Up' ) . '</a></p></div></div></section>';
	}
}

WeVoi_Redesign::init();
