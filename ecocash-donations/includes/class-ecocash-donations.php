<?php
/**
 * Core donations logic: CPT, shortcode, AJAX handlers, status polling.
 *
 * @package EcoCash_Donations
 */

defined( 'ABSPATH' ) || exit;

class Ecocash_Donations {

	/** Donation post type slug. */
	const CPT = 'ecocash_donation';

	/** Nonce action for the donation form. */
	const NONCE = 'ecocash_donate';

	/** Donation statuses stored in post meta. */
	const ST_PENDING   = 'pending';
	const ST_COMPLETED = 'completed';
	const ST_FAILED    = 'failed';

	/**
	 * Constructor: hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_shortcode( 'ecocash_donate', array( $this, 'shortcode' ) );

		// Frontend AJAX (donor-facing, logged-in and logged-out).
		add_action( 'wp_ajax_nopriv_ecocash_donate', array( $this, 'ajax_donate' ) );
		add_action( 'wp_ajax_ecocash_donate', array( $this, 'ajax_donate' ) );
		add_action( 'wp_ajax_nopriv_ecocash_status', array( $this, 'ajax_status' ) );
		add_action( 'wp_ajax_ecocash_status', array( $this, 'ajax_status' ) );
	}

	/**
	 * Register the donation custom post type.
	 */
	public function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => __( 'EcoCash Donations', 'ecocash-donations' ),
					'singular_name' => __( 'Donation', 'ecocash-donations' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-money-alt',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Activation: register CPT then flush rewrite rules.
	 */
	public static function activate() {
		register_post_type(
			self::CPT,
			array(
				'labels'   => array(
					'name'          => __( 'EcoCash Donations', 'ecocash-donations' ),
					'singular_name' => __( 'Donation', 'ecocash-donations' ),
				),
				'public'   => false,
				'show_ui'  => true,
				'supports' => array( 'title' ),
			)
		);
		flush_rewrite_rules();
	}

	/**
	 * Register frontend assets (registered only; enqueued by shortcode).
	 */
	public function register_assets() {
		wp_register_style(
			'ecocash-donations',
			ECOCASH_DONATIONS_URL . 'assets/css/ecocash-donations.css',
			array(),
			ECOCASH_DONATIONS_VERSION
		);
		wp_register_script(
			'ecocash-donations',
			ECOCASH_DONATIONS_URL . 'assets/js/ecocash-donations.js',
			array(),
			ECOCASH_DONATIONS_VERSION,
			true
		);
		wp_localize_script(
			'ecocash-donations',
			'ecocashDonations',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Render the donation form via shortcode.
	 *
	 * Usage:
	 * [ecocash_donate currency="USD" amounts="5,10,25,50" title="Support our cause"]
	 *
	 * Optional attributes:
	 * - show_message="false"  hide the donor message field
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML.
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'currency'    => get_option( 'ecocash_currency', 'USD' ),
				'amounts'     => '5,10,25,50',
				'title'       => __( 'Make a Donation', 'ecocash-donations' ),
				'desc'        => __( 'Donate securely with EcoCash. You will receive a prompt on your phone.', 'ecocash-donations' ),
				'show_message' => 'true',
			),
			$atts,
			'ecocash_donate'
		);

		wp_enqueue_style( 'ecocash-donations' );
		wp_enqueue_script( 'ecocash-donations' );

		$amounts      = array_filter( array_map( 'floatval', explode( ',', $atts['amounts'] ) ) );
		$show_message = 'false' !== strtolower( (string) $atts['show_message'] );

		ob_start();
		?>
		<div class="ecocash-donate-wrap">
			<h3 class="ecocash-donate-title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<p class="ecocash-donate-desc"><?php echo esc_html( $atts['desc'] ); ?></p>

			<form class="ecocash-donate-form" method="post">
				<?php wp_nonce_field( self::NONCE, 'ecocash_nonce' ); ?>

				<div class="ecocash-amounts" role="group" aria-label="<?php esc_attr_e( 'Donation amount', 'ecocash-donations' ); ?>">
					<?php foreach ( $amounts as $amt ) : ?>
						<label class="ecocash-amount-chip">
							<input type="radio" name="ecocash_amount" value="<?php echo esc_attr( $amt ); ?>" />
							<span><?php echo esc_html( $atts['currency'] . ' ' . number_format( $amt, 0 ) ); ?></span>
						</label>
					<?php endforeach; ?>
					<label class="ecocash-amount-chip ecocash-amount-custom">
						<input type="radio" name="ecocash_amount" value="custom" />
						<span class="ecocash-custom-label">
							<?php echo esc_html( $atts['currency'] ); ?>&nbsp;
							<input
								type="number"
								class="ecocash-custom-amount"
								name="ecocash_custom_amount"
								min="1"
								step="0.01"
								placeholder="<?php esc_attr_e( 'Other', 'ecocash-donations' ); ?>"
							/>
						</span>
					</label>
				</div>

				<div class="ecocash-field-row">
					<div class="ecocash-field">
						<label>
							<?php esc_html_e( 'Your name', 'ecocash-donations' ); ?> <span class="ecocash-required">*</span>
						</label>
						<input type="text" class="ecocash-name" name="ecocash_name" required />
					</div>
					<div class="ecocash-field">
						<label>
							<?php esc_html_e( 'Email (for your receipt)', 'ecocash-donations' ); ?> <span class="ecocash-required">*</span>
						</label>
						<input type="email" class="ecocash-email" name="ecocash_email" required />
					</div>
				</div>

				<input
					type="tel"
					class="ecocash-phone"
					name="ecocash_phone"
					placeholder="<?php esc_attr_e( 'EcoCash number e.g. 0771234567', 'ecocash-donations' ); ?>"
					required
				/>

				<?php if ( $show_message ) : ?>
					<textarea
						class="ecocash-message"
						name="ecocash_message"
						rows="3"
						maxlength="500"
						placeholder="<?php esc_attr_e( 'Leave an optional message of support…', 'ecocash-donations' ); ?>"
					></textarea>
				<?php endif; ?>

				<button type="submit" class="ecocash-donate-btn">
					<?php esc_html_e( 'Donate with EcoCash', 'ecocash-donations' ); ?>
				</button>
			</form>

			<div class="ecocash-status" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: create donation + trigger the EcoCash STK push.
	 */
	public function ajax_donate() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$chosen  = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '';
		$custom  = isset( $_POST['custom'] ) ? (float) $_POST['custom'] : 0;
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		$amount = ( 'custom' === $chosen ) ? $custom : (float) $chosen;

		if ( $amount <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a donation amount.', 'ecocash-donations' ) ) );
		}

		if ( $amount > 100000 ) {
			wp_send_json_error( array( 'message' => __( 'Amount exceeds the maximum allowed.', 'ecocash-donations' ) ) );
		}

		if ( '' === $name || mb_strlen( $name ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name.', 'ecocash-donations' ) ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address for your receipt.', 'ecocash-donations' ) ) );
		}

		$phone_check = Ecocash_API::normalize_phone( $phone );
		if ( is_wp_error( $phone_check ) ) {
			wp_send_json_error( array( 'message' => $phone_check->get_error_message() ) );
		}

		// Split "Name Surname" into first + last for the receipt greeting.
		$name_parts = preg_split( '/\s+/', trim( $name ), 2 );
		$first_name = $name_parts[0];
		$last_name  = isset( $name_parts[1] ) ? $name_parts[1] : '';

		$currency  = get_option( 'ecocash_currency', 'USD' );
		$reference = 'DON-' . strtoupper( wp_generate_password( 12, false, false ) );

		$donation_id = wp_insert_post(
			array(
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => sprintf(
					/* translators: 1: amount, 2: currency, 3: donor name */
					__( 'Donation %1$s %2$s — %3$s', 'ecocash-donations' ),
					number_format( $amount, 2 ),
					$currency,
					$name
				),
				'meta_input'  => array(
					'_ecocash_amount'       => $amount,
					'_ecocash_currency'     => $currency,
					'_ecocash_phone'        => $phone_check,
					'_ecocash_reference'    => $reference,
					'_ecocash_status'       => self::ST_PENDING,
					'_ecocash_environment'  => get_option( 'ecocash_environment', 'sandbox' ),
					'_ecocash_first_name'   => $first_name,
					'_ecocash_last_name'    => $last_name,
					'_ecocash_email'        => $email,
					'_ecocash_donor_message' => $message,
				),
			)
		);

		if ( is_wp_error( $donation_id ) || ! $donation_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not record the donation.', 'ecocash-donations' ) ) );
		}

		$response = Ecocash_API::request_payment(
			$phone_check,
			$amount,
			sprintf(
				/* translators: %s: site name */
				__( 'Donation to %s', 'ecocash-donations' ),
				get_bloginfo( 'name' )
			),
			$reference,
			$currency
		);

		if ( is_wp_error( $response ) ) {
			update_post_meta( $donation_id, '_ecocash_status', self::ST_FAILED );
			update_post_meta( $donation_id, '_ecocash_last_error', $response->get_error_message() );

			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		update_post_meta( $donation_id, '_ecocash_api_response', $response );

		wp_send_json_success(
			array(
				'donationId' => $donation_id,
				'reference'  => $reference,
				'message'    => __(
					'Check your phone and approve the EcoCash payment prompt. A receipt will be emailed to you once the payment is confirmed.',
					'ecocash-donations'
				),
			)
		);
	}

	/**
	 * AJAX: poll donation status via EcoCash transaction lookup.
	 */
	public function ajax_status() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$donation_id = isset( $_POST['donationId'] ) ? absint( $_POST['donationId'] ) : 0;

		if ( ! $donation_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid donation.', 'ecocash-donations' ) ) );
		}

		$reference = get_post_meta( $donation_id, '_ecocash_reference', true );
		$phone     = get_post_meta( $donation_id, '_ecocash_phone', true );
		$status    = get_post_meta( $donation_id, '_ecocash_status', true );

		if ( self::ST_PENDING !== $status ) {
			wp_send_json_success(
				array(
					'status'  => $status,
					'message' => $this->status_message( $status ),
				)
			);
		}

		$lookup = Ecocash_API::lookup( $phone, $reference );
		if ( ! is_wp_error( $lookup ) ) {
			$api_status = Ecocash_API::extract_status( $lookup );

			if ( 'SUCCESS' === $api_status ) {
				update_post_meta( $donation_id, '_ecocash_status', self::ST_COMPLETED );
				update_post_meta( $donation_id, '_ecocash_ecocash_ref', isset( $lookup['ecocashReference'] ) ? $lookup['ecocashReference'] : '' );
				update_post_meta( $donation_id, '_ecocash_completed', current_time( 'mysql' ) );

				/**
				 * Fires when a donation is confirmed completed.
				 * Receipt emails hook into this.
				 *
				 * @param int   $donation_id Donation post ID.
				 * @param array $lookup      Raw EcoCash lookup response.
				 */
				do_action( 'ecocash_donation_completed', $donation_id, $lookup );

				wp_send_json_success(
					array(
						'status'  => self::ST_COMPLETED,
						'message' => $this->status_message( self::ST_COMPLETED ),
					)
				);
			}

			if ( 'FAILED' === $api_status ) {
				update_post_meta( $donation_id, '_ecocash_status', self::ST_FAILED );

				do_action( 'ecocash_donation_failed', $donation_id, $lookup );

				wp_send_json_success(
					array(
						'status'  => self::ST_FAILED,
						'message' => $this->status_message( self::ST_FAILED ),
					)
				);
			}
		}

		// Still pending.
		wp_send_json_success(
			array(
				'status'  => self::ST_PENDING,
				'message' => $this->status_message( self::ST_PENDING ),
			)
		);
	}

	/**
	 * Human-friendly status message.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	private function status_message( $status ) {
		switch ( $status ) {
			case self::ST_COMPLETED:
				return __( 'Thank you! Your donation was received — a receipt is on its way to your email. 🎉', 'ecocash-donations' );
			case self::ST_FAILED:
				return __( 'The payment was not completed. Please try again.', 'ecocash-donations' );
			default:
				return __( 'Waiting for approval on your phone…', 'ecocash-donations' );
		}
	}
}

new Ecocash_Donations();
