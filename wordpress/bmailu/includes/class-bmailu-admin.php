<?php
/**
 * Settings → Bmailu: API key, From address, fallback and a test email.
 *
 * @package Bmailu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The settings page.
 */
class Bmailu_Admin {

	/**
	 * Registers the hooks.
	 *
	 * @param string $basename The plugin basename, for the "Settings" link.
	 */
	public static function init( $basename ) {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_bmailu_test', array( __CLASS__, 'send_test' ) );
		add_filter( 'plugin_action_links_' . $basename, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adds Settings → Bmailu.
	 */
	public static function menu() {
		add_options_page( __( 'Bmailu', 'bmailu' ), __( 'Bmailu', 'bmailu' ), 'manage_options', 'bmailu', array( __CLASS__, 'render' ) );
	}

	/**
	 * Adds a "Settings" link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=bmailu' ) ) . '">' . esc_html__( 'Settings', 'bmailu' ) . '</a>';
		return $links;
	}

	/**
	 * Registers the option with its sanitize callback.
	 */
	public static function register() {
		register_setting(
			'bmailu',
			BMAILU_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Cleans the submitted settings. A blank API key keeps the saved one.
	 *
	 * @param mixed $input The submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$old   = Bmailu_Mailer::settings();
		$key   = isset( $input['api_key'] ) ? sanitize_text_field( $input['api_key'] ) : '';
		if ( '' === $key && empty( $input['clear_key'] ) ) {
			$key = $old['api_key'];
		}
		$from_email = isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '';
		if ( isset( $input['from_email'] ) && '' !== trim( $input['from_email'] ) && '' === $from_email ) {
			add_settings_error( BMAILU_OPTION, 'bmailu_from_email', __( 'The From email is not a valid email address.', 'bmailu' ) );
		}
		return array(
			'api_key'    => $key,
			'from_email' => $from_email,
			'from_name'  => isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '',
			'fallback'   => empty( $input['fallback'] ) ? 0 : 1,
		);
	}

	/**
	 * Sends a test email (admin-post.php?action=bmailu_test) and shows the API result on the settings page.
	 */
	public static function send_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'bmailu' ), 403 );
		}
		check_admin_referer( 'bmailu_test' );

		$to = isset( $_POST['bmailu_test_to'] ) ? sanitize_email( wp_unslash( $_POST['bmailu_test_to'] ) ) : '';
		if ( ! is_email( $to ) ) {
			$notice = array( 'error', __( 'Enter a valid email address for the test.', 'bmailu' ) );
		} elseif ( '' === Bmailu_Mailer::api_key() ) {
			$notice = array( 'error', __( 'Add your Bmailu API key first.', 'bmailu' ) );
		} else {
			$result = Bmailu_Mailer::send(
				array(
					'to'      => $to,
					'subject' => __( 'Bmailu test email', 'bmailu' ),
					'message' => __( 'This is a test email sent by the Bmailu plugin. If you can read it, WordPress email works.', 'bmailu' ),
				)
			);
			if ( is_wp_error( $result ) ) {
				/* translators: %s: error message from the Bmailu API. */
				$notice = array( 'error', sprintf( __( 'The test email failed: %s', 'bmailu' ), $result->get_error_message() ) );
			} else {
				/* translators: %s: Bmailu email id. */
				$notice = array( 'success', sprintf( __( 'The test email was accepted. Email id: %s', 'bmailu' ), $result ) );
			}
		}
		set_transient( 'bmailu_test_' . get_current_user_id(), $notice, 120 );
		wp_safe_redirect( admin_url( 'options-general.php?page=bmailu' ) );
		exit;
	}

	/**
	 * Prints the settings page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings     = Bmailu_Mailer::settings();
		$key_constant = defined( 'BMAILU_API_KEY' ) && '' !== (string) constant( 'BMAILU_API_KEY' );
		$notice       = get_transient( 'bmailu_test_' . get_current_user_id() );
		if ( $notice ) {
			delete_transient( 'bmailu_test_' . get_current_user_id() );
		}
		$name = BMAILU_OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bmailu', 'bmailu' ); ?></h1>
			<?php if ( is_array( $notice ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( 'success' === $notice[0] ? 'success' : 'error' ); ?> is-dismissible"><p><?php echo esc_html( $notice[1] ); ?></p></div>
			<?php endif; ?>
			<p>
				<?php esc_html_e( 'Bmailu sends all WordPress email through the Bmailu Email API. Create an API key on the Developers page of your Bmailu account. The From address must be on a sending domain you verified in Bmailu.', 'bmailu' ); ?>
				<?php if ( '' === Bmailu_Mailer::api_key() ) : ?>
					<strong><?php esc_html_e( 'No API key yet, so WordPress still sends email the normal way.', 'bmailu' ); ?></strong>
				<?php endif; ?>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'bmailu' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bmailu_api_key"><?php esc_html_e( 'API key', 'bmailu' ); ?></label></th>
						<td>
							<?php if ( $key_constant ) : ?>
								<p><?php esc_html_e( 'Set by the BMAILU_API_KEY constant in wp-config.php.', 'bmailu' ); ?></p>
							<?php else : ?>
								<input type="password" class="regular-text" id="bmailu_api_key" name="<?php echo esc_attr( $name ); ?>[api_key]" value="" autocomplete="off" placeholder="<?php echo esc_attr( '' !== $settings['api_key'] ? __( 'Saved. Leave blank to keep it.', 'bmailu' ) : 'bmk_...' ); ?>">
								<?php if ( '' !== $settings['api_key'] ) : ?>
									<p><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[clear_key]" value="1"> <?php esc_html_e( 'Delete the saved key', 'bmailu' ); ?></label></p>
								<?php endif; ?>
								<p class="description"><?php esc_html_e( 'Tip: define BMAILU_API_KEY in wp-config.php to keep the key out of the database.', 'bmailu' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bmailu_from_email"><?php esc_html_e( 'From email', 'bmailu' ); ?></label></th>
						<td><input type="email" class="regular-text" id="bmailu_from_email" name="<?php echo esc_attr( $name ); ?>[from_email]" value="<?php echo esc_attr( $settings['from_email'] ); ?>" placeholder="hello@example.com"></td>
					</tr>
					<tr>
						<th scope="row"><label for="bmailu_from_name"><?php esc_html_e( 'From name', 'bmailu' ); ?></label></th>
						<td><input type="text" class="regular-text" id="bmailu_from_name" name="<?php echo esc_attr( $name ); ?>[from_name]" value="<?php echo esc_attr( $settings['from_name'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fallback', 'bmailu' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[fallback]" value="1" <?php checked( 1, (int) $settings['fallback'] ); ?>> <?php esc_html_e( 'Fall back to the normal WordPress mail when the Bmailu API fails', 'bmailu' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Send a test email', 'bmailu' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bmailu_test">
				<?php wp_nonce_field( 'bmailu_test' ); ?>
				<p>
					<label for="bmailu_test_to"><?php esc_html_e( 'Send to', 'bmailu' ); ?></label>
					<input type="email" class="regular-text" id="bmailu_test_to" name="bmailu_test_to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required>
				</p>
				<?php submit_button( __( 'Send test email', 'bmailu' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
