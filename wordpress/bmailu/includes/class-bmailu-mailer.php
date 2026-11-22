<?php
/**
 * Turns a wp_mail() call into one request to the Bmailu Email API (POST {base}/emails).
 *
 * @package Bmailu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Bmailu mailer.
 */
class Bmailu_Mailer {

	/** The API allows at most this many To + Cc + Bcc recipients per email. */
	const MAX_RECIPIENTS = 50;

	/** The API allows at most this many Reply-To addresses. */
	const MAX_REPLY_TO = 10;

	/** The API allows at most this many attachments. */
	const MAX_ATTACHMENTS = 20;

	/** Headers the API builds itself; they are dropped instead of being sent as custom headers. */
	const SKIPPED_HEADERS = array( 'to', 'subject', 'date', 'sender', 'return-path', 'received', 'mime-version', 'content-transfer-encoding', 'content-disposition', 'dkim-signature', 'x-bmailu-email-id' );

	/**
	 * Plugin settings merged with defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$saved = get_option( BMAILU_OPTION, array() );
		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				'api_key'    => '',
				'from_email' => '',
				'from_name'  => '',
				'fallback'   => 0,
			)
		);
	}

	/**
	 * The API key: the BMAILU_API_KEY constant (wp-config.php) wins over the saved setting.
	 *
	 * @return string
	 */
	public static function api_key() {
		if ( defined( 'BMAILU_API_KEY' ) && '' !== (string) constant( 'BMAILU_API_KEY' ) ) {
			return (string) constant( 'BMAILU_API_KEY' );
		}
		$settings = self::settings();
		return (string) $settings['api_key'];
	}

	/**
	 * The API base URL, without a trailing slash.
	 *
	 * @return string
	 */
	public static function base_url() {
		return untrailingslashit( (string) apply_filters( 'bmailu_api_base_url', 'https://app.bmailu.com/api/v1' ) );
	}

	/**
	 * The pre_wp_mail filter (WordPress 5.7+). Returning null lets WordPress send the mail itself.
	 *
	 * @param null|bool $short_circuit Null, or the result from another plugin.
	 * @param array     $atts          The wp_mail() arguments: to, subject, message, headers, attachments.
	 * @return null|bool
	 */
	public static function pre_wp_mail( $short_circuit, $atts ) {
		if ( null !== $short_circuit || '' === self::api_key() ) {
			return $short_circuit;
		}
		$result = self::send( $atts );
		if ( ! is_wp_error( $result ) ) {
			return true;
		}
		$settings = self::settings();
		if ( ! empty( $settings['fallback'] ) ) {
			return null; // Let WordPress send it with its normal mailer.
		}
		do_action( 'wp_mail_failed', $result );
		return false;
	}

	/**
	 * Sends one email through the API.
	 *
	 * @param array $atts The wp_mail() arguments.
	 * @return string|WP_Error The Bmailu email id, or the error.
	 */
	public static function send( $atts ) {
		$atts      = wp_parse_args(
			(array) $atts,
			array(
				'to'          => array(),
				'subject'     => '',
				'message'     => '',
				'headers'     => array(),
				'attachments' => array(),
			)
		);
		$mail_data = $atts;
		$payload   = self::build_payload( $atts );
		if ( is_wp_error( $payload ) ) {
			return new WP_Error( 'wp_mail_failed', $payload->get_error_message(), $mail_data + array( 'bmailu_error_code' => $payload->get_error_code() ) );
		}

		$response = wp_remote_post(
			self::base_url() . '/emails',
			array(
				'timeout'    => 15,
				'user-agent' => 'bmailu-wordpress/' . BMAILU_VERSION,
				'headers'    => array(
					'Authorization' => 'Bearer ' . self::api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'       => wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'wp_mail_failed', $response->get_error_message(), $mail_data + array( 'bmailu_error_code' => 'http_request_failed' ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status >= 200 && $status < 300 && is_array( $body ) && ! empty( $body['id'] ) ) {
			return (string) $body['id'];
		}
		$code    = isset( $body['error']['code'] ) ? (string) $body['error']['code'] : 'http_' . $status;
		$message = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : sprintf(
			/* translators: %d: HTTP status code. */
			__( 'The Bmailu API answered with HTTP status %d.', 'bmailu' ),
			$status
		);
		return new WP_Error(
			'wp_mail_failed',
			$message,
			$mail_data + array(
				'bmailu_error_code' => $code,
				'http_status'       => $status,
			)
		);
	}

	/**
	 * Maps the wp_mail() arguments to the JSON body of POST /emails.
	 *
	 * @param array $atts The wp_mail() arguments.
	 * @return array|WP_Error
	 */
	public static function build_payload( $atts ) {
		$settings     = self::settings();
		$from_email   = (string) $settings['from_email'];
		$from_name    = (string) $settings['from_name'];
		$content_type = 'text/plain';
		$cc           = array();
		$bcc          = array();
		$reply_to     = array();
		$custom       = array();

		$headers = $atts['headers'];
		if ( ! is_array( $headers ) ) {
			$headers = preg_split( "/\r\n|\n|\r/", (string) $headers );
		}
		foreach ( $headers as $line ) {
			if ( false === strpos( (string) $line, ':' ) ) {
				continue;
			}
			list( $name, $value ) = explode( ':', (string) $line, 2 );
			$name  = trim( $name );
			$value = trim( $value );
			if ( '' === $name || '' === $value ) {
				continue;
			}
			switch ( strtolower( $name ) ) {
				case 'from':
					$from = self::split_address( $value );
					if ( '' !== $from['email'] ) {
						$from_email = $from['email'];
						$from_name  = $from['name'];
					}
					break;
				case 'content-type':
					$content_type = trim( strtok( $value, ';' ) );
					break;
				case 'cc':
					$cc = array_merge( $cc, self::split_list( $value ) );
					break;
				case 'bcc':
					$bcc = array_merge( $bcc, self::split_list( $value ) );
					break;
				case 'reply-to':
					$reply_to = array_merge( $reply_to, self::split_list( $value ) );
					break;
				default:
					if ( ! in_array( strtolower( $name ), self::SKIPPED_HEADERS, true ) ) {
						$custom[ $name ] = $value;
					}
			}
		}

		if ( '' === $from_email ) {
			$from_email = 'wordpress@' . self::site_domain(); // Same default as WordPress core.
		}
		if ( '' === $from_name ) {
			$from_name = 'WordPress';
		}
		$from_email   = (string) apply_filters( 'wp_mail_from', $from_email );
		$from_name    = (string) apply_filters( 'wp_mail_from_name', $from_name );
		$content_type = (string) apply_filters( 'wp_mail_content_type', $content_type );

		$to = is_array( $atts['to'] ) ? $atts['to'] : self::split_list( (string) $atts['to'] );
		$to = array_values( array_filter( array_map( 'trim', array_map( 'strval', $to ) ) ) );
		if ( ! $to ) {
			return new WP_Error( 'missing_required_field', __( 'The email has no recipient.', 'bmailu' ) );
		}
		if ( count( $to ) + count( $cc ) + count( $bcc ) > self::MAX_RECIPIENTS ) {
			return new WP_Error(
				'too_many_recipients',
				/* translators: %d: maximum number of recipients. */
				sprintf( __( 'Bmailu sends to at most %d recipients (To + Cc + Bcc) per email.', 'bmailu' ), self::MAX_RECIPIENTS )
			);
		}
		if ( count( $reply_to ) > self::MAX_REPLY_TO ) {
			return new WP_Error(
				'too_many_recipients',
				/* translators: %d: maximum number of Reply-To addresses. */
				sprintf( __( 'Bmailu allows at most %d Reply-To addresses.', 'bmailu' ), self::MAX_REPLY_TO )
			);
		}

		$from_name = trim( str_replace( array( '"', '<', '>' ), '', $from_name ) );
		$payload   = array(
			'from'    => '' !== $from_name ? $from_name . ' <' . trim( $from_email ) . '>' : trim( $from_email ),
			'to'      => $to,
			'subject' => (string) $atts['subject'],
		);
		if ( false !== stripos( $content_type, 'text/html' ) ) {
			$payload['html'] = (string) $atts['message'];
		} else {
			$payload['text'] = (string) $atts['message'];
		}
		if ( $cc ) {
			$payload['cc'] = $cc;
		}
		if ( $bcc ) {
			$payload['bcc'] = $bcc;
		}
		if ( $reply_to ) {
			$payload['reply_to'] = $reply_to;
		}
		if ( $custom ) {
			$payload['headers'] = $custom;
		}

		$attachments = self::attachments( $atts['attachments'] );
		if ( is_wp_error( $attachments ) ) {
			return $attachments;
		}
		if ( $attachments ) {
			$payload['attachments'] = $attachments;
		}
		return $payload;
	}

	/**
	 * Reads the attachment files into base64 [{filename, content}].
	 * Like core, a string key is used as the file name (WordPress 6.2+).
	 *
	 * @param string|array $attachments File paths.
	 * @return array|WP_Error
	 */
	private static function attachments( $attachments ) {
		if ( ! is_array( $attachments ) ) {
			$attachments = explode( "\n", str_replace( "\r\n", "\n", (string) $attachments ) );
		}
		$out = array();
		foreach ( $attachments as $key => $path ) {
			$path = trim( (string) $path );
			if ( '' === $path ) {
				continue;
			}
			if ( ! is_file( $path ) || ! is_readable( $path ) ) {
				/* translators: %s: file name. */
				return new WP_Error( 'attachment_not_readable', sprintf( __( 'Could not read the attachment %s.', 'bmailu' ), wp_basename( $path ) ) );
			}
			$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file given to wp_mail().
			if ( false === $content || '' === $content ) {
				/* translators: %s: file name. */
				return new WP_Error( 'attachment_not_readable', sprintf( __( 'Could not read the attachment %s.', 'bmailu' ), wp_basename( $path ) ) );
			}
			$filename = is_string( $key ) && '' !== trim( $key ) ? trim( $key ) : wp_basename( $path );
			$out[]    = array(
				'filename' => str_replace( array( '"', '\\', '/' ), '', $filename ),
				'content'  => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the API takes attachments as base64.
			);
		}
		if ( count( $out ) > self::MAX_ATTACHMENTS ) {
			/* translators: %d: maximum number of attachments. */
			return new WP_Error( 'too_many_attachments', sprintf( __( 'Bmailu allows at most %d attachments per email.', 'bmailu' ), self::MAX_ATTACHMENTS ) );
		}
		return $out;
	}

	/**
	 * Splits "a@x.com, Name <b@x.com>" on commas that are not inside quotes.
	 *
	 * @param string $value A list of addresses.
	 * @return array
	 */
	private static function split_list( $value ) {
		$parts = preg_split( '/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', (string) $value );
		return array_values( array_filter( array_map( 'trim', $parts ) ) );
	}

	/**
	 * Splits "Name <a@x.com>" into name and email.
	 *
	 * @param string $value One address.
	 * @return array {name, email}
	 */
	private static function split_address( $value ) {
		if ( preg_match( '/^(.*)<([^<>]+)>\s*$/', $value, $m ) ) {
			return array(
				'name'  => trim( str_replace( '"', '', $m[1] ) ),
				'email' => trim( $m[2] ),
			);
		}
		return array(
			'name'  => '',
			'email' => trim( $value ),
		);
	}

	/**
	 * The site's host name without "www.", as wp_mail() uses it for the default From address.
	 *
	 * @return string
	 */
	private static function site_domain() {
		$host = strtolower( (string) wp_parse_url( network_home_url(), PHP_URL_HOST ) );
		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}
}
