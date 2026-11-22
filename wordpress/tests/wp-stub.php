<?php
// A minimal WordPress stand-in so the plugin can be tested without a WordPress install.
// Only what the plugin uses. wp_remote_post() is real HTTP over PHP streams.

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['stub_filters']  = array();
$GLOBALS['stub_options']  = array();
$GLOBALS['stub_requests'] = array();

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['stub_filters'][ $hook ][ $priority ][] = array( $cb, $args );
	ksort( $GLOBALS['stub_filters'][ $hook ] );
	return true;
}
function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
function remove_all_filters( $hook ) { unset( $GLOBALS['stub_filters'][ $hook ] ); }
function apply_filters( $hook, $value, ...$rest ) {
	foreach ( $GLOBALS['stub_filters'][ $hook ] ?? array() as $list ) {
		foreach ( $list as list( $cb, $n ) ) {
			$value = call_user_func_array( $cb, array_slice( array_merge( array( $value ), $rest ), 0, $n ) );
		}
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['stub_filters'][ $hook ] ?? array() as $list ) {
		foreach ( $list as list( $cb, $n ) ) {
			call_user_func_array( $cb, array_slice( $args, 0, $n ) );
		}
	}
}

function get_option( $name, $default = false ) { return $GLOBALS['stub_options'][ $name ] ?? $default; }
function update_option( $name, $value ) { $GLOBALS['stub_options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['stub_options'][ $name ] ); return true; }

function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_basename( $path ) { return basename( $path ); }
function network_home_url() { return 'https://www.example.org'; }
function is_admin() { return false; }
function plugin_basename( $file ) { return 'bmailu/' . basename( $file ); }
function __( $s, $domain = 'default' ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function sanitize_email( $s ) { $s = trim( (string) $s ); return filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : ''; }
function is_email( $s ) { return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ); }
function add_settings_error( ...$args ) { $GLOBALS['stub_settings_errors'][] = $args; }

// HTTP with PHP streams; every request is kept in $GLOBALS['stub_requests'] so tests can look at the JSON sent.
function stub_http( $method, $url, $args ) {
	$GLOBALS['stub_requests'][] = array( 'method' => $method, 'url' => $url, 'args' => $args );
	$headers = array();
	foreach ( $args['headers'] ?? array() as $k => $v ) {
		$headers[] = "$k: $v";
	}
	if ( isset( $args['user-agent'] ) ) {
		$headers[] = 'User-Agent: ' . $args['user-agent'];
	}
	$ctx  = stream_context_create(
		array(
			'http' => array(
				'method'        => $method,
				'header'        => implode( "\r\n", $headers ),
				'content'       => $args['body'] ?? '',
				'timeout'       => $args['timeout'] ?? 5,
				'ignore_errors' => true,
			),
		)
	);
	$body = @file_get_contents( $url, false, $ctx );
	if ( false === $body ) {
		return new WP_Error( 'http_request_failed', 'Could not connect to ' . $url );
	}
	$code = 0;
	foreach ( $http_response_header as $line ) {
		if ( preg_match( '#^HTTP/\S+ (\d{3})#', $line, $m ) ) {
			$code = (int) $m[1];
		}
	}
	return array( 'body' => $body, 'response' => array( 'code' => $code ) );
}
function wp_remote_post( $url, $args = array() ) { return stub_http( 'POST', $url, $args ); }
function wp_remote_get( $url, $args = array() ) { return stub_http( 'GET', $url, $args ); }
function wp_remote_retrieve_body( $r ) { return is_wp_error( $r ) ? '' : $r['body']; }
function wp_remote_retrieve_response_code( $r ) { return is_wp_error( $r ) ? '' : $r['response']['code']; }

// The start of core's wp_mail(): the wp_mail filter, then pre_wp_mail. 'CORE' stands for core's own mailer.
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	$atts = apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) );
	$pre  = apply_filters( 'pre_wp_mail', null, $atts );
	if ( null !== $pre ) {
		return $pre;
	}
	return 'CORE';
}
