<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'WEVOI_SIGNUP_VERSION', '1.0' );

$mock_hooks                 = array();
$register_new_user_calls    = array();
$kenan_registration_called  = 0;
$kenan_user_register_called = 0;
$kenan_validation_error     = false;
$kenan_observed_post        = array();

class WP_Error {
	private $code;

	public function __construct( $code = '' ) {
		$this->code = $code;
	}

	public function get_error_code() {
		return $this->code;
	}
}

function __( $text ) {
	return $text;
}

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return '';
}

function add_shortcode( $tag, $callback ) {
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	global $mock_hooks;
	$mock_hooks[ $tag ][ $priority ][] = array( $callback, $accepted_args );
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	global $mock_hooks;
	$mock_hooks[ $tag ][ $priority ][] = array( $callback, $accepted_args );
}

function has_filter( $tag ) {
	global $mock_hooks;
	return empty( $mock_hooks[ $tag ] ) ? false : 10;
}

function has_action( $tag ) {
	return has_filter( $tag );
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function register_new_user( $username, $email ) {
	global $mock_hooks, $register_new_user_calls;
	$register_new_user_calls[] = array( $username, $email );
	$errors = new WP_Error();

	if ( ! empty( $mock_hooks['registration_errors'] ) ) {
		foreach ( $mock_hooks['registration_errors'] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$errors = call_user_func( $callback[0], $errors, $username, $email );
			}
		}
	}

	if ( is_wp_error( $errors ) && '' !== $errors->get_error_code() ) {
		return $errors;
	}

	if ( ! empty( $mock_hooks['user_register'] ) ) {
		foreach ( $mock_hooks['user_register'] as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				call_user_func( $callback[0], 42 );
			}
		}
	}

	return 42;
}

require dirname( __DIR__ ) . '/wevoi-redesign.php';

function assert_signup_test( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$register_method = new ReflectionMethod( 'WeVoi_Redesign', 'register_signup_user' );
if ( PHP_VERSION_ID < 80100 ) {
	$register_method->setAccessible( true );
}
$signup_values = array(
	'user_login' => 'example-user',
	'user_email' => 'example@example.test',
);

$unavailable = $register_method->invoke( null, $signup_values );
assert_signup_test( is_wp_error( $unavailable ), 'registration fails closed when Kenan hooks are unavailable' );
assert_signup_test( 'signup_unavailable' === $unavailable->get_error_code(), 'the unavailable error is explicit' );
assert_signup_test( empty( $register_new_user_calls ), 'the core registration function is not called without Kenan hooks' );

add_filter(
	'registration_errors',
	function ( $errors ) {
		global $kenan_registration_called, $kenan_validation_error, $kenan_observed_post;
		++$kenan_registration_called;
		$kenan_observed_post = $_POST;
		if ( $kenan_validation_error ) {
			return new WP_Error( 'kenan_validation_error' );
		}
		return $errors;
	}
);
add_action(
	'user_register',
	function ( $user_id ) {
		global $kenan_user_register_called;
		if ( 42 === $user_id ) {
			++$kenan_user_register_called;
		}
	}
);

$_POST = array(
	'role'       => 'student',
	'first_name' => 'Example',
	'last_name'  => 'User',
	'birth_date' => '2010-01-01',
	'gender'     => 'Male',
	'countryid'  => '1',
	'schoolid'   => '2',
);
$user_id = $register_method->invoke( null, $signup_values );
assert_signup_test( 42 === $user_id, 'registration delegates to WordPress register_new_user' );
assert_signup_test( array( array( 'example-user', 'example@example.test' ) ) === $register_new_user_calls, 'username and email are passed to WordPress' );
assert_signup_test( 1 === $kenan_registration_called, 'registration_errors hooks run in the registration flow' );
assert_signup_test( 1 === $kenan_user_register_called, 'user_register hooks run in the registration flow' );
assert_signup_test( 'student' === $kenan_observed_post['role'], 'role data is preserved for the Kenan hooks' );
assert_signup_test( 'Example' === $kenan_observed_post['first_name'] && '2' === $kenan_observed_post['schoolid'], 'profile metadata inputs are preserved for the Kenan hooks' );

$kenan_validation_error = true;
$result = $register_method->invoke( null, $signup_values );
assert_signup_test( is_wp_error( $result ) && 'kenan_validation_error' === $result->get_error_code(), 'Kenan registration validation errors propagate without creating a user' );
assert_signup_test( 1 === $kenan_user_register_called, 'failed validation prevents the user_register hook' );

fwrite( STDOUT, "Signup integration mock tests passed.\n" );
