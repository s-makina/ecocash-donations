<?php
/**
 * Admin: settings page + donations list table columns + details metabox.
 *
 * @package EcoCash_Donations
 */

defined( 'ABSPATH' ) || exit;

class Ecocash_Admin {

	const NONCE = 'ecocash_admin_settings';

	/**
	 * Constructor: hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ) );
		add_filter( 'manage_ecocash_donation_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_ecocash_donation_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
	}

	/**
	 * Add the settings page under the donations CPT menu.
	 */
	public function menu() {
		add_submenu_page(
			'edit.php?post_type=ecocash_donation',
			__( 'EcoCash Settings', 'ecocash-donations' ),
			__( 'Settings', 'ecocash-donations' ),
			'manage_options',
			'ecocash-donations-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Render the settings screen.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api_key      = get_option( 'ecocash_api_key', '' );
		$environment  = get_option( 'ecocash_environment', 'sandbox' );
		$currency     = get_option( 'ecocash_currency', 'USD' );
		$test_number  = get_option( 'ecocash_test_number', '' );
		$notify_admin = get_option( 'ecocash_notify_admin', 'yes' );
		$admin_email  = get_option( 'ecocash_admin_email', get_option( 'admin_email' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'EcoCash Donations — Settings', 'ecocash-donations' ); ?></h1>

			<?php if ( 'sandbox' === $environment ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Sandbox mode active.', 'ecocash-donations' ); ?></strong>
						<?php esc_html_e( 'Payments will not be real. Switch to Live once your tests pass and your live API key is enabled.', 'ecocash-donations' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE, 'ecocash_admin_nonce' ); ?>

				<h2 class="title"><?php esc_html_e( 'API', 'ecocash-donations' ); ?></h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ecocash_api_key"><?php esc_html_e( 'API Key', 'ecocash-donations' ); ?></label></th>
						<td>
							<input
								type="password"
								id="ecocash_api_key"
								name="ecocash_api_key"
								class="regular-text"
								autocomplete="off"
								value="<?php echo esc_attr( $api_key ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'From developers.ecocash.co.zw after merchant approval. Kept private — never shown to donors.', 'ecocash-donations' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Environment', 'ecocash-donations' ); ?></th>
						<td>
							<label>
								<input type="radio" name="ecocash_environment" value="sandbox" <?php checked( $environment, 'sandbox' ); ?> />
								<?php esc_html_e( 'Sandbox (testing)', 'ecocash-donations' ); ?>
							</label>
							&nbsp;&nbsp;
							<label>
								<input type="radio" name="ecocash_environment" value="live" <?php checked( $environment, 'live' ); ?> />
								<?php esc_html_e( 'Live (real money)', 'ecocash-donations' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Currency', 'ecocash-donations' ); ?></th>
						<td>
							<select name="ecocash_currency">
								<option value="USD" <?php selected( $currency, 'USD' ); ?>>USD</option>
								<option value="ZWL" <?php selected( $currency, 'ZWL' ); ?>>ZWL</option>
								<option value="ZIG" <?php selected( $currency, 'ZIG' ); ?>>ZiG</option>
							</select>
							<p class="description"><?php esc_html_e( 'Must match the currency enabled on your EcoCash merchant account.', 'ecocash-donations' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="ecocash_test_number"><?php esc_html_e( 'Test phone number', 'ecocash-donations' ); ?></label></th>
						<td>
							<input
								type="text"
								id="ecocash_test_number"
								name="ecocash_test_number"
								class="regular-text"
								placeholder="0771234567"
								value="<?php echo esc_attr( $test_number ); ?>"
							/>
							<p class="description"><?php esc_html_e( 'Optional. Your own EcoCash number for doing a small live test donation.', 'ecocash-donations' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Email notifications', 'ecocash-donations' ); ?></h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Donor receipts', 'ecocash-donations' ); ?></th>
						<td>
							<p class="description">
								<?php esc_html_e( 'Always on. Donors receive a thank-you receipt at the email address they enter on the donation form, sent when their payment is confirmed.', 'ecocash-donations' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Notify admin', 'ecocash-donations' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="ecocash_notify_admin" value="yes" <?php checked( $notify_admin, 'yes' ); ?> />
								<?php esc_html_e( 'Email me when a new donation is completed', 'ecocash-donations' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="ecocash_admin_email"><?php esc_html_e( 'Notification email', 'ecocash-donations' ); ?></label></th>
						<td>
							<input
								type="email"
								id="ecocash_admin_email"
								name="ecocash_admin_email"
								class="regular-text"
								value="<?php echo esc_attr( $admin_email ); ?>"
							/>
							<p class="description"><?php esc_html_e( 'Where admin donation notifications are sent. Defaults to the site admin email.', 'ecocash-donations' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'How to display the donation form', 'ecocash-donations' ); ?></h2>
			<p>
				<code>[ecocash_donate]</code> —
				<?php esc_html_e( 'options:', 'ecocash-donations' ); ?>
				<code>amounts="5,10,25,50"</code>, <code>currency="USD"</code>,
				<code>title="Support us"</code>, <code>desc="Your message"</code>,
				<code>show_message="false"</code> (<?php esc_html_e( 'hides the optional donor message field', 'ecocash-donations' ); ?>)
			</p>
		</div>
		<?php
	}

	/**
	 * Save settings submitted from the settings screen.
	 */
	public function save_settings() {
		if ( ! isset( $_POST['ecocash_admin_nonce'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( self::NONCE, 'ecocash_admin_nonce' );

		$api_key      = isset( $_POST['ecocash_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['ecocash_api_key'] ) ) : '';
		$env          = isset( $_POST['ecocash_environment'] ) ? sanitize_key( wp_unslash( $_POST['ecocash_environment'] ) ) : 'sandbox';
		$cur          = isset( $_POST['ecocash_currency'] ) ? sanitize_text_field( wp_unslash( $_POST['ecocash_currency'] ) ) : 'USD';
		$test         = isset( $_POST['ecocash_test_number'] ) ? sanitize_text_field( wp_unslash( $_POST['ecocash_test_number'] ) ) : '';
		$notify_admin = isset( $_POST['ecocash_notify_admin'] ) ? 'yes' : 'no';
		$admin_email  = isset( $_POST['ecocash_admin_email'] ) ? sanitize_email( wp_unslash( $_POST['ecocash_admin_email'] ) ) : '';

		update_option( 'ecocash_api_key', $api_key );
		update_option( 'ecocash_environment', in_array( $env, array( 'sandbox', 'live' ), true ) ? $env : 'sandbox' );
		update_option( 'ecocash_currency', in_array( $cur, array( 'USD', 'ZWL', 'ZIG' ), true ) ? $cur : 'USD' );
		update_option( 'ecocash_test_number', $test );
		update_option( 'ecocash_notify_admin', $notify_admin );
		update_option( 'ecocash_admin_email', is_email( $admin_email ) ? $admin_email : get_option( 'admin_email' ) );

		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'ecocash-donations' ) . '</p></div>';
			}
		);
	}

	/**
	 * Register the donation details metabox.
	 */
	public function register_metabox() {
		add_meta_box(
			'ecocash_donation_details',
			__( 'Donation Details', 'ecocash-donations' ),
			array( $this, 'render_metabox' ),
			'ecocash_donation',
			'normal',
			'high'
		);
	}

	/**
	 * Render the donation details metabox.
	 *
	 * @param WP_Post $post Donation post.
	 */
	public function render_metabox( $post ) {
		$donation_id = $post->ID;
		$fields      = array(
			__( 'Status', 'ecocash-donations' )      => get_post_meta( $donation_id, '_ecocash_status', true ),
			__( 'Amount', 'ecocash-donations' )      => get_post_meta( $donation_id, '_ecocash_currency', true ) . ' ' . number_format( (float) get_post_meta( $donation_id, '_ecocash_amount', true ), 2 ),
			__( 'Donor name', 'ecocash-donations' )  => trim( get_post_meta( $donation_id, '_ecocash_first_name', true ) . ' ' . get_post_meta( $donation_id, '_ecocash_last_name', true ) ),
			__( 'Donor email', 'ecocash-donations' ) => get_post_meta( $donation_id, '_ecocash_email', true ),
			__( 'EcoCash number', 'ecocash-donations' ) => get_post_meta( $donation_id, '_ecocash_phone', true ),
			__( 'Reference', 'ecocash-donations' )   => get_post_meta( $donation_id, '_ecocash_reference', true ),
			__( 'EcoCash ref', 'ecocash-donations' ) => get_post_meta( $donation_id, '_ecocash_ecocash_ref', true ),
			__( 'Environment', 'ecocash-donations' ) => get_post_meta( $donation_id, '_ecocash_environment', true ),
			__( 'Completed', 'ecocash-donations' )   => get_post_meta( $donation_id, '_ecocash_completed', true ),
			__( 'Receipt emailed', 'ecocash-donations' ) => ( '1' === (string) get_post_meta( $donation_id, '_ecocash_email_sent', true ) ) ? __( 'Yes', 'ecocash-donations' ) : __( 'No', 'ecocash-donations' ),
		);

		$message = get_post_meta( $donation_id, '_ecocash_donor_message', true );
		$error   = get_post_meta( $donation_id, '_ecocash_last_error', true );

		echo '<table class="widefat striped" style="max-width:640px;">';
		foreach ( $fields as $label => $value ) {
			echo '<tr><td style="width:160px;font-weight:600;">' . esc_html( $label ) . '</td><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</table>';

		if ( $message ) {
			echo '<p style="margin-top:12px;"><strong>' . esc_html__( 'Donor message:', 'ecocash-donations' ) . '</strong></p>';
			echo '<blockquote style="margin:4px 0;padding:8px 16px;border-left:3px solid #4c3392;background:#f6f7f7;">' . esc_html( $message ) . '</blockquote>';
		}

		if ( $error ) {
			echo '<p style="margin-top:12px;color:#d63638;"><strong>' . esc_html__( 'Last API error:', 'ecocash-donations' ) . '</strong> ' . esc_html( $error ) . '</p>';
		}
	}

	/**
	 * Custom columns for the donations list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$new = array(
			'cb'                   => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'title'                => __( 'Donation', 'ecocash-donations' ),
			'donation_status'      => __( 'Status', 'ecocash-donations' ),
			'donation_amount'      => __( 'Amount', 'ecocash-donations' ),
			'donation_donor'       => __( 'Donor', 'ecocash-donations' ),
			'donation_phone'       => __( 'Phone', 'ecocash-donations' ),
			'donation_reference'   => __( 'Reference', 'ecocash-donations' ),
			'donation_ecocash_ref' => __( 'EcoCash Ref', 'ecocash-donations' ),
			'date'                 => __( 'Date', 'ecocash-donations' ),
		);

		return $new;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Donation ID.
	 */
	public function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'donation_status':
				$status = get_post_meta( $post_id, '_ecocash_status', true );
				$colors = array(
					'completed' => '#00a32a',
					'failed'    => '#d63638',
					'pending'   => '#dba617',
				);
				$color = isset( $colors[ $status ] ) ? $colors[ $status ] : '#8c8f94';
				printf(
					'<span style="color:%1$s;font-weight:600;">%2$s</span>',
					esc_attr( $color ),
					esc_html( ucfirst( (string) $status ) )
				);
				break;

			case 'donation_amount':
				$amount   = get_post_meta( $post_id, '_ecocash_amount', true );
				$currency = get_post_meta( $post_id, '_ecocash_currency', true );
				echo esc_html( $currency . ' ' . number_format( (float) $amount, 2 ) );
				break;

			case 'donation_donor':
				$name  = trim( get_post_meta( $post_id, '_ecocash_first_name', true ) . ' ' . get_post_meta( $post_id, '_ecocash_last_name', true ) );
				$email = get_post_meta( $post_id, '_ecocash_email', true );
				echo esc_html( $name );
				if ( $email ) {
					echo '<br /><span style="color:#666;">' . esc_html( $email ) . '</span>';
				}
				break;

			case 'donation_phone':
				echo esc_html( get_post_meta( $post_id, '_ecocash_phone', true ) );
				break;

			case 'donation_reference':
				echo esc_html( get_post_meta( $post_id, '_ecocash_reference', true ) );
				break;

			case 'donation_ecocash_ref':
				echo esc_html( get_post_meta( $post_id, '_ecocash_ecocash_ref', true ) );
				break;
		}
	}
}

new Ecocash_Admin();
