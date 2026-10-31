<?php

define( 'ABSPATH', '/' );

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return 'http://wevoi-redesign.local/wp-content/plugins/wevoi-redesign/';
}

function add_shortcode() {}
function add_action( $hook, $callback, $priority = 10 ) {
	$GLOBALS['registered_actions'][] = array( $hook, $callback, $priority );
}
function add_filter() {}
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_localize_script() {}
function wp_style_is() { return false; }
function shortcode_exists( $tag ) { return ! empty( $GLOBALS['available_shortcodes'][ $tag ] ); }
function do_shortcode( $value ) {
	$GLOBALS['rendered_shortcodes'][] = $value;
	return '<div class="mock-shortcode-result">' . $value . '</div>';
}
function is_user_logged_in() { return false; }
function wp_get_current_user() { return (object) array( 'roles' => array() ); }
function get_permalink( $post = null ) { return 'http://wevoi-redesign.local/login-page/'; }
function get_post() { return (object) array( 'post_content' => '[wevoi_site]' ); }
function has_shortcode( $content, $tag ) { return false !== strpos( $content, '[' . $tag . ']' ); }
function shortcode_atts( $defaults, $attributes, $tag = '' ) { return array_merge( $defaults, (array) $attributes ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html__( $value, $domain = '' ) { return esc_html( $value ); }
function __( $value, $domain = '' ) { return $value; }
function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_safe_redirect( $url, $status = 302, $x_redirect_by = 'WordPress' ) {
	$GLOBALS['redirect'] = $url;
	$GLOBALS['redirect_args'] = array( $status, $x_redirect_by );
	return isset( $GLOBALS['safe_redirect_result'] ) ? $GLOBALS['safe_redirect_result'] : true;
}
function wp_validate_redirect( $url, $fallback = '' ) {
	if ( ! is_string( $url ) ) {
		return $fallback;
	}
	if ( '' === $url ) {
		return '';
	}
	$parts = parse_url( $url );
	$home  = parse_url( home_url( '/' ) );
	return isset( $parts['host'], $home['host'] ) && $home['host'] === $parts['host'] ? $url : $fallback;
}
function wp_signon() {
	$GLOBALS['signon_calls']++;
	return (object) array( 'roles' => array( 'student' ) );
}
function is_wp_error() { return false; }
function is_ssl() { return false; }
function wp_verify_nonce() { return true; }
function wp_nonce_field() { return ''; }
function wp_login_url( $redirect = '' ) { return ''; }
function wp_registration_url() { return ''; }
function wp_lostpassword_url() { return ''; }
function remove_query_arg( $key, $url ) { return $url; }
function add_query_arg( ...$args ) {
	$key = $args[0];
	if ( is_array( $key ) ) {
		$url = isset( $args[1] ) ? $args[1] : '';
		foreach ( $key as $name => $item ) {
			$url = add_query_arg( $name, $item, $url );
		}
		return $url;
	}
	$value = isset( $args[1] ) ? $args[1] : '';
	$url   = isset( $args[2] ) ? $args[2] : '';
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
}
function apply_filters( $tag, $value ) { return $value; }
function home_url( $path = '/' ) { return isset( $GLOBALS['home_url'] ) ? $GLOBALS['home_url'] : 'http://wevoi-redesign.local/'; }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }

class WP_Post {
	public $post_content = '[wevoi_site]';
}

require dirname( __DIR__ ) . '/wevoi-redesign.php';

function assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

$redirect_method = new ReflectionMethod( 'WeVoi_Redesign', 'login_redirect_url' );
$pages = WeVoi_Redesign::pages();
assert_same( true, isset( $pages['register'] ), 'register route is available in the internal page whitelist' );
assert_same( 'http://wevoi-redesign.local/login-page/?wv=register', WeVoi_Redesign::url( 'register' ), 'register URLs resolve to the local WeVoi route' );

$GLOBALS['available_shortcodes']['wevoi_register_form'] = true;
$GLOBALS['rendered_shortcodes'] = array();
$_GET = array( 'wv' => 'register' );
$register_html = WeVoi_Redesign::render( array() );
assert_same( array( '[wevoi_register_form]' ), $GLOBALS['rendered_shortcodes'], 'register route renders the existing competition registration shortcode' );
assert_same( true, false !== strpos( $register_html, 'mock-shortcode-result' ), 'register route includes the shortcode output' );
assert_same( true, false !== strpos( $register_html, 'href="http://wevoi-redesign.local/login-page/?wv=register"' ), 'Register Now points to the local register route' );
unset( $GLOBALS['available_shortcodes']['wevoi_register_form'] );
$GLOBALS['rendered_shortcodes'] = array();
$register_unavailable_html = WeVoi_Redesign::render( array() );
assert_same( array(), $GLOBALS['rendered_shortcodes'], 'unavailable registration shortcode is not invoked' );
assert_same( true, false !== strpos( $register_unavailable_html, 'competition registration service is not currently available' ), 'register route shows an explicit unavailable notice when the shortcode is missing' );

$role_destinations = array(
	'student'    => 'student-dashboard',
	'supervisor' => 'supervisor-dashboard',
	'parent'     => 'parent-dashboard',
	'agency'     => 'bulk-registration',
);

$login_action = array_filter(
	$GLOBALS['registered_actions'],
	function ( $action ) {
		return 'wp' === $action[0] && array( 'WeVoi_Redesign', 'handle_login_submission' ) === $action[1] && 0 === $action[2];
	}
);
assert_same( 1, count( $login_action ), 'login handler runs early on the wp hook' );

foreach ( $role_destinations as $role => $slug ) {
	$_POST = array();
	$_GET  = array();
	$url   = $redirect_method->invoke( null, (object) array( 'roles' => array( $role ) ), new WP_Post() );
	assert_same( 'http://wevoi-redesign.local/login-page/?wv=' . $slug, $url, $role . ' redirect when the submitted form has no redirect_to' );
}

$_POST = array( 'redirect_to' => 'http://wevoi-redesign.local/custom-destination/' );
$_GET  = array();
$url   = $redirect_method->invoke( null, (object) array( 'roles' => array( 'student' ) ), new WP_Post() );
assert_same( 'http://wevoi-redesign.local/custom-destination/', $url, 'valid explicit redirect takes precedence' );

$_POST = array();
$_GET  = array( 'redirect_to' => 'https://outside.example/redirect' );
$url   = $redirect_method->invoke( null, (object) array( 'roles' => array( 'parent' ) ), new WP_Post() );
assert_same( 'http://wevoi-redesign.local/login-page/?wv=parent-dashboard', $url, 'unsafe redirect falls back to role destination' );

$_POST = array();
$GLOBALS['home_url'] = 'http://wevoi-redesign-alias.local/';
$_GET  = array( 'redirect_to' => 'http://wevoi-redesign-alias.local/2-2/?wv=login' );
$url   = $redirect_method->invoke( null, (object) array( 'roles' => array( 'student' ) ), new WP_Post() );
assert_same( 'http://wevoi-redesign.local/login-page/?wv=student-dashboard', $url, 'a login redirect on an allowed local alias falls back to the student dashboard' );
unset( $GLOBALS['home_url'] );

$_POST = array();
$_GET  = array();
$url   = $redirect_method->invoke( null, (object) array( 'roles' => array( 'judge' ) ), new WP_Post() );
assert_same( 'http://wevoi-redesign.local/login-page/?wv=home', $url, 'unmapped roles retain the existing home redirect' );

$error_notice_method = new ReflectionMethod( 'WeVoi_Redesign', 'login_error_notice' );
$_GET = array( 'wevoi_login_error' => 'invalid_credentials' );
$error_notice = $error_notice_method->invoke( null );
assert_same( true, false !== strpos( $error_notice, 'role="alert"' ), 'invalid credentials display an accessible error' );
assert_same( true, false !== strpos( $error_notice, 'incorrect' ), 'invalid credentials display the expected message' );

$fallback_method = new ReflectionMethod( 'WeVoi_Redesign', 'login_redirect_fallback_html' );
$fallback_html   = $fallback_method->invoke( null, 'http://wevoi-redesign.local/login-page/?wv=student-dashboard' );
assert_same( true, false !== strpos( $fallback_html, 'http://wevoi-redesign.local/login-page/?wv=student-dashboard' ), 'redirect fallback provides the validated destination' );
assert_same( true, false !== strpos( $fallback_html, 'http-equiv="refresh"' ), 'redirect fallback includes a meta refresh' );
assert_same( true, false !== strpos( $fallback_html, 'window.location.replace(' ), 'redirect fallback automatically navigates' );

$response_method = new ReflectionMethod( 'WeVoi_Redesign', 'login_redirect_response' );
$GLOBALS['safe_redirect_result'] = true;
$response_html = $response_method->invoke( null, 'http://wevoi-redesign.local/login-page/?wv=student-dashboard' );
assert_same( '', $response_html, 'successful safe redirects do not render fallback content' );
assert_same( 'http://wevoi-redesign.local/login-page/?wv=student-dashboard', $GLOBALS['redirect'], 'successful safe redirects retain the student destination' );
assert_same( array( 302, 'WeVoi Redesign' ), $GLOBALS['redirect_args'], 'successful logins send an explicit 302 redirect' );

$GLOBALS['safe_redirect_result'] = false;
$fallback_html   = $response_method->invoke( null, 'http://wevoi-redesign.local/login-page/?wv=student-dashboard' );
assert_same( true, false !== strpos( $fallback_html, 'window.location.replace(' ), 'failed safe redirects return a browser fallback instead of an empty response' );
unset( $GLOBALS['safe_redirect_result'] );
$_GET = array();

$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST                     = array();
$_GET                      = array( 'wv' => 'login' );
$GLOBALS['signon_calls']   = 0;
WeVoi_Redesign::handle_login_submission();
assert_same( 0, $GLOBALS['signon_calls'], 'GET requests do not trigger sign-on' );

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST                     = array( 'wevoi_login_form' => '1' );
$_GET                      = array( 'wv' => 'home' );
WeVoi_Redesign::handle_login_submission();
assert_same( 0, $GLOBALS['signon_calls'], 'non-login routes do not trigger sign-on' );

echo "Login redirect tests passed." . PHP_EOL;
