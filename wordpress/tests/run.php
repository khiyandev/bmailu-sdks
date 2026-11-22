<?php
// Plugin tests against the shared local Bmailu API (sdks/test-server.js).
// Usage: BMAILU_TEST_API=http://127.0.0.1:port/api/v1 BMAILU_TEST_KEY=bmk_... php tests/run.php

require __DIR__ . '/wp-stub.php';
require __DIR__ . '/../bmailu/bmailu.php';
require __DIR__ . '/../bmailu/includes/class-bmailu-admin.php';

$api = getenv( 'BMAILU_TEST_API' );
$key = getenv( 'BMAILU_TEST_KEY' );
if ( ! $api || ! $key ) {
	fwrite( STDERR, "BMAILU_TEST_API and BMAILU_TEST_KEY are required\n" );
	exit( 2 );
}
add_filter( 'bmailu_api_base_url', function () use ( $api ) { return $api . '/'; } );

$passed = 0;
$failed = 0;
function check( $name, $ok, $detail = '' ) {
	global $passed, $failed;
	if ( $ok ) {
		$passed++;
		echo "ok   $name\n";
	} else {
		$failed++;
		echo "FAIL $name" . ( $detail ? " -- $detail" : '' ) . "\n";
	}
}
function last_payload() {
	$r = end( $GLOBALS['stub_requests'] );
	return $r ? json_decode( $r['args']['body'], true ) : null;
}
function last_request() { return end( $GLOBALS['stub_requests'] ); }
function fetch_email( $id ) {
	global $api, $key;
	$r = wp_remote_get( "$api/emails/$id", array( 'headers' => array( 'Authorization' => "Bearer $key" ) ) );
	return json_decode( wp_remote_retrieve_body( $r ), true );
}
$failures = array();
add_action( 'wp_mail_failed', function ( $e ) use ( &$failures ) { $failures[] = $e; } );
function settings( $extra = array() ) {
	global $key;
	update_option( 'bmailu_settings', array_merge( array( 'api_key' => $key, 'from_email' => 'hello@shop.test', 'from_name' => 'Shop', 'fallback' => 0 ), $extra ) );
}

// 0. No API key: the plugin stays out of the way.
update_option( 'bmailu_settings', array() );
check( 'no api key -> WordPress sends normally', 'CORE' === wp_mail( 'a@example.com', 'x', 'y' ) && ! $GLOBALS['stub_requests'] );

// 1. Plain text.
settings();
$ok = wp_mail( 'Ann <delivered@simulator.bmailu.com>', 'Plain hello', "Line one\nLine two" );
$p  = last_payload();
$r  = last_request();
check( 'plain: wp_mail returns true', true === $ok );
check( 'plain: POST {base}/emails', $r['url'] === "$api/emails" && 'Bearer ' . $key === $r['args']['headers']['Authorization'] );
check( 'plain: from from settings', 'Shop <hello@shop.test>' === $p['from'], $p['from'] ?? '' );
check( 'plain: text body, no html', "Line one\nLine two" === $p['text'] && ! isset( $p['html'] ) );
check( 'plain: to kept with name', array( 'Ann <delivered@simulator.bmailu.com>' ) === $p['to'] );

// 2. HTML with Cc, Reply-To and a custom header (string headers, like most plugins pass them).
$headers = "Content-Type: text/html; charset=UTF-8\r\nCc: Carl <cc1@example.com>, cc2@example.com\r\nReply-To: \"Help, Desk\" <help@shop.test>\r\nX-Order-Id: 1234\r\nMIME-Version: 1.0";
$ok      = wp_mail( array( 'to1@example.com', 'to2@example.com' ), 'HTML hello', '<p>Hi <b>there</b></p>', $headers );
$p       = last_payload();
check( 'html: wp_mail returns true', true === $ok );
check( 'html: html body', '<p>Hi <b>there</b></p>' === ( $p['html'] ?? null ) && ! isset( $p['text'] ) );
check( 'html: cc split', array( 'Carl <cc1@example.com>', 'cc2@example.com' ) === $p['cc'], json_encode( $p['cc'] ?? null ) );
check( 'html: reply_to keeps quoted comma', array( '"Help, Desk" <help@shop.test>' ) === $p['reply_to'], json_encode( $p['reply_to'] ?? null ) );
check( 'html: custom header, MIME-Version dropped', array( 'X-Order-Id' => '1234' ) === $p['headers'], json_encode( $p['headers'] ?? null ) );
$sent = fetch_email( Bmailu_Mailer::send( array( 'to' => 'to1@example.com', 'subject' => 'HTML hello', 'message' => '<p>x</p>', 'headers' => $headers ) ) );
check( 'html: API stored cc + reply_to + html', array( 'cc1@example.com', 'cc2@example.com' ) === $sent['cc'] && 1 === count( $sent['reply_to'] ) && '<p>x</p>' === $sent['html'], json_encode( $sent ) );

// 2b. HTML via the wp_mail_content_type filter, From header, wp_mail_from_name filter.
$ct = function () { return 'text/html'; };
add_filter( 'wp_mail_content_type', $ct );
add_filter( 'wp_mail_from_name', function ( $n ) { return 'Filtered ' . $n; } );
wp_mail( 'a@example.com', 'Filter', '<i>f</i>', array( 'From: Orders <orders@shop.test>' ) );
$p = last_payload();
check( 'filter: content type filter -> html', '<i>f</i>' === ( $p['html'] ?? null ) );
check( 'filter: From header + from_name filter', 'Filtered Orders <orders@shop.test>' === $p['from'], $p['from'] );
remove_all_filters( 'wp_mail_content_type' );
remove_all_filters( 'wp_mail_from_name' );

// 3. One attachment (and a WordPress 6.2 style named one).
$file = tempnam( sys_get_temp_dir(), 'bm' );
file_put_contents( $file, "invoice,total\n1,9.99\n" );
$ok = wp_mail( 'a@example.com', 'With file', 'See attached', '', array( 'invoice.csv' => $file ) );
$p  = last_payload();
check( 'attachment: wp_mail returns true', true === $ok, json_encode( $failures ? $failures[0]->get_error_message() : '' ) );
check( 'attachment: filename + base64 content', array( array( 'filename' => 'invoice.csv', 'content' => base64_encode( "invoice,total\n1,9.99\n" ) ) ) === $p['attachments'] );
$ok = wp_mail( 'a@example.com', 'With file', 'See attached', '', array( $file ) );
check( 'attachment: plain path uses basename', basename( $file ) === last_payload()['attachments'][0]['filename'] && true === $ok );
$n  = count( $failures );
$ok = wp_mail( 'a@example.com', 'Missing file', 'x', '', array( '/no/such/file.pdf' ) );
check( 'attachment: missing file -> false + wp_mail_failed', false === $ok && count( $failures ) === $n + 1 && 'attachment_not_readable' === $failures[ $n ]->get_error_data()['bmailu_error_code'] );
unlink( $file );

// 4. Failure: From on an unverified domain.
$failures = array();
$ok       = wp_mail( 'a@example.com', 'Bad from', 'x', 'From: someone@unverified.test' );
$e        = $failures[0] ?? null;
check( 'failure: wp_mail returns false', false === $ok );
check( 'failure: wp_mail_failed fired with WP_Error', $e instanceof WP_Error && 'wp_mail_failed' === $e->get_error_code() );
check( 'failure: API message passed on', $e && false !== strpos( $e->get_error_message(), 'not a verified sending domain' ), $e ? $e->get_error_message() : '' );
check( 'failure: API code + mail data kept', $e && 'domain_not_verified' === $e->get_error_data()['bmailu_error_code'] && 'Bad from' === $e->get_error_data()['subject'] );

// 5. Fallback on: WordPress sends it instead, no wp_mail_failed.
settings( array( 'fallback' => 1 ) );
$failures = array();
check( 'fallback: API error -> WordPress mail', 'CORE' === wp_mail( 'a@example.com', 'Bad from', 'x', 'From: someone@unverified.test' ) && ! $failures );
settings();

// 6. Too many recipients: refused before any request.
$before = count( $GLOBALS['stub_requests'] );
$to     = array();
for ( $i = 0; $i < 40; $i++ ) {
	$to[] = "u$i@example.com";
}
$ok = wp_mail( $to, 'Many', 'x', array( 'Bcc: ' . implode( ',', array_slice( $to, 0, 11 ) ) ) );
check( 'limit: 51 recipients -> false, no request', false === $ok && count( $GLOBALS['stub_requests'] ) === $before );
check( 'limit: wp_mail_failed says why', 'too_many_recipients' === end( $failures )->get_error_data()['bmailu_error_code'] );

// 7. Bad API key -> the API's message.
settings( array( 'api_key' => 'bmk_wrong' ) );
$failures = array();
check( 'bad key: false + wp_mail_failed', false === wp_mail( 'a@example.com', 'k', 'x' ) && 1 === count( $failures ), $failures ? $failures[0]->get_error_message() : '' );
settings();

// 8. Settings sanitize: a blank key keeps the saved one; clear_key deletes it; bad email refused.
$s = Bmailu_Admin::sanitize( array( 'api_key' => '', 'from_email' => ' hi@shop.test ', 'from_name' => "<b>Shop</b>\n", 'fallback' => '1' ) );
check( 'sanitize: blank key kept', $key === $s['api_key'] && 'hi@shop.test' === $s['from_email'] && 'Shop' === $s['from_name'] && 1 === $s['fallback'], json_encode( $s ) );
$s = Bmailu_Admin::sanitize( array( 'api_key' => '', 'clear_key' => '1', 'from_email' => 'nope' ) );
check( 'sanitize: clear key, invalid email emptied', '' === $s['api_key'] && '' === $s['from_email'] && 0 === $s['fallback'] && ! empty( $GLOBALS['stub_settings_errors'] ) );

// 9. The BMAILU_API_KEY constant wins over the saved key.
settings( array( 'api_key' => 'bmk_saved' ) );
define( 'BMAILU_API_KEY', $key );
check( 'constant: wins over option', $key === Bmailu_Mailer::api_key() && true === wp_mail( 'a@example.com', 'Const', 'x' ) );

// 10. uninstall.php removes the option.
define( 'WP_UNINSTALL_PLUGIN', 'bmailu/bmailu.php' );
require __DIR__ . '/../bmailu/uninstall.php';
check( 'uninstall: option deleted', false === get_option( 'bmailu_settings' ) );

echo "\n$passed passed, $failed failed\n";
exit( $failed ? 1 : 0 );
