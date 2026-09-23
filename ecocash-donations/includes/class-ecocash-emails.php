<?php
/**
 * Email notifications: donor receipt + admin notification.
 *
 * Emails are sent only when a donation transitions to completed, guarded
 * by a meta flag so repeated status polls never double-send.
 *
 * @package EcoCash_Donations
 */

defined( 'ABSPATH' ) || exit;

class Ecocash_Emails {

	/**
	 * Constructor: hook completion event.
	 */
	public function __construct() {
		add_action( 'ecocash_donation_completed', array( $this, 'send_receipts' ), 10, 2 );
	}

	/**
	 * Send donor receipt + admin notification exactly once per donation.
	 *
	 * @param int   $donation_id Donation post ID.
	 * @param array $lookup      Raw EcoCash lookup response.
	 */
	public function send_receipts( $donation_id, $lookup = array() ) {
		$already = get_post_meta( $donation_id, '_ecocash_email_sent', true );

		if ( '1' === (string) $already ) {
			return; // Race-safe: poll hits may fire this hook more than once.
		}

		update_post_meta( $donation_id, '_ecocash_email_sent', '1' );

		$notify_admin = 'yes' === get_option( 'ecocash_notify_admin', 'yes' );

		if ( $notify_admin ) {
			$this->send_admin_notification( $donation_id );
		}

		$this->send_donor_receipt( $donation_id );
	}

	/**
	 * Send the thank-you receipt to the donor.
	 *
	 * @param int $donation_id Donation post ID.
	 */
	private function send_donor_receipt( $donation_id ) {
		$email = get_post_meta( $donation_id, '_ecocash_email', true );

		if ( ! is_email( $email ) ) {
			return; // No valid donor email — nothing to send.
		}

		$first_name = get_post_meta( $donation_id, '_ecocash_first_name', true );
		$amount     = get_post_meta( $donation_id, '_ecocash_amount', true );
		$currency   = get_post_meta( $donation_id, '_ecocash_currency', true );
		$reference  = get_post_meta( $donation_id, '_ecocash_reference', true );
		$ecocash_ref = get_post_meta( $donation_id, '_ecocash_ecocash_ref', true );
		$message    = get_post_meta( $donation_id, '_ecocash_donor_message', true );

		$subject = sprintf(
			/* translators: 1: site name, 2: amount, 3: currency */
			__( 'Thank you for your donation to %1$s — %2$s %3$s', 'ecocash-donations' ),
			get_bloginfo( 'name' ),
			number_format( (float) $amount, 2 ),
			$currency
		);

		$body = $this->render_receipt( $donation_id, $email );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $email, $subject, $body, $headers );
	}

	/**
	 * Send the notification to the site admin.
	 *
	 * @param int $donation_id Donation post ID.
	 */
	private function send_admin_notification( $donation_id ) {
		$admin_email = get_option( 'ecocash_admin_email', get_option( 'admin_email' ) );

		if ( ! is_email( $admin_email ) ) {
			return;
		}

		$amount   = get_post_meta( $donation_id, '_ecocash_amount', true );
		$currency = get_post_meta( $donation_id, '_ecocash_currency', true );
		$name     = trim( get_post_meta( $donation_id, '_ecocash_first_name', true ) . ' ' . get_post_meta( $donation_id, '_ecocash_last_name', true ) );
		$phone    = get_post_meta( $donation_id, '_ecocash_phone', true );

		$subject = sprintf(
			/* translators: 1: amount, 2: currency */
			__( '💰 New donation received: %1$s %2$s', 'ecocash-donations' ),
			number_format( (float) $amount, 2 ),
			$currency
		);

		$body = $this->render_admin( $donation_id );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $admin_email, $subject, $body, $headers );
	}

	/**
	 * Render the donor receipt HTML.
	 *
	 * @param int    $donation_id Donation post ID.
	 * @param string $email       Donor email (for the from display only).
	 * @return string
	 */
	private function render_receipt( $donation_id, $email ) {
		$amount      = get_post_meta( $donation_id, '_ecocash_amount', true );
		$currency    = get_post_meta( $donation_id, '_ecocash_currency', true );
		$reference   = get_post_meta( $donation_id, '_ecocash_reference', true );
		$ecocash_ref = get_post_meta( $donation_id, '_ecocash_ecocash_ref', true );
		$completed   = get_post_meta( $donation_id, '_ecocash_completed', true );
		$first_name  = get_post_meta( $donation_id, '_ecocash_first_name', true );
		$message     = get_post_meta( $donation_id, '_ecocash_donor_message', true );

		$greeting = $first_name
			/* translators: %s: donor first name */
			? sprintf( __( 'Dear %s,', 'ecocash-donations' ), esc_html( $first_name ) )
			: __( 'Dear donor,', 'ecocash-donations' );

		$rows = array(
			array( __( 'Amount', 'ecocash-donations' ), esc_html( $currency . ' ' . number_format( (float) $amount, 2 ) ) ),
			array( __( 'Your reference', 'ecocash-donations' ), esc_html( $reference ) ),
		);

		if ( $ecocash_ref ) {
			$rows[] = array( __( 'EcoCash reference', 'ecocash-donations' ), esc_html( $ecocash_ref ) );
		}

		if ( $completed ) {
			$rows[] = array( __( 'Date', 'ecocash-donations' ), esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $completed ) ) );
		}

		$rows_html = '';
		foreach ( $rows as $row ) {
			$rows_html .= sprintf(
				'<tr><td style="padding:8px 12px;border-bottom:1px solid #eee;color:#666;">%s</td><td style="padding:8px 12px;border-bottom:1px solid #eee;font-weight:600;">%s</td></tr>',
				$row[0],
				$row[1]
			);
		}

		$message_html = '';
		if ( $message ) {
			$message_html = sprintf(
				'<div style="margin:16px 0;padding:12px 16px;background:#f6f7f7;border-left:3px solid #4c3392;font-style:italic;">%s</div>',
				esc_html( $message )
			);
		}

		return '
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;">
		<tr>
			<td align="center">
				<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
					<tr>
						<td style="background:#4c3392;padding:24px 32px;color:#ffffff;">
							<h1 style="margin:0;font-size:20px;">' . esc_html( get_bloginfo( 'name' ) ) . '</h1>
							<p style="margin:4px 0 0;font-size:14px;opacity:0.9;">' . esc_html__( 'Donation receipt', 'ecocash-donations' ) . '</p>
						</td>
					</tr>
					<tr>
						<td style="padding:32px;">
							<p style="margin:0 0 16px;font-size:15px;">' . $greeting . '</p>
							<p style="margin:0 0 16px;font-size:15px;">' . esc_html__( 'Thank you for your generous donation! Your payment was received successfully via EcoCash.', 'ecocash-donations' ) . '</p>
							' . $message_html . '
							<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;border-radius:6px;margin:16px 0;">
								' . $rows_html . '
							</table>
							<p style="margin:16px 0 0;font-size:13px;color:#777;">
								' . esc_html__( 'This email serves as your receipt. No action is needed.', 'ecocash-donations' ) . '<br/>
								' . sprintf(
									/* translators: %s: site name */
									esc_html__( 'With gratitude, the %s team', 'ecocash-donations' ),
									esc_html( get_bloginfo( 'name' ) )
								) . '
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';
	}

	/**
	 * Render the admin notification HTML.
	 *
	 * @param int $donation_id Donation post ID.
	 * @return string
	 */
	private function render_admin( $donation_id ) {
		$amount   = get_post_meta( $donation_id, '_ecocash_amount', true );
		$currency = get_post_meta( $donation_id, '_ecocash_currency', true );
		$first    = get_post_meta( $donation_id, '_ecocash_first_name', true );
		$last     = get_post_meta( $donation_id, '_ecocash_last_name', true );
		$email    = get_post_meta( $donation_id, '_ecocash_email', true );
		$phone    = get_post_meta( $donation_id, '_ecocash_phone', true );
		$ref      = get_post_meta( $donation_id, '_ecocash_reference', true );
		$eco_ref  = get_post_meta( $donation_id, '_ecocash_ecocash_ref', true );
		$message  = get_post_meta( $donation_id, '_ecocash_donor_message', true );

		$lines = array(
			array( __( 'Donor', 'ecocash-donations' ), esc_html( trim( $first . ' ' . $last ) ) ),
			array( __( 'Email', 'ecocash-donations' ), esc_html( $email ) ),
			array( __( 'Phone', 'ecocash-donations' ), esc_html( $phone ) ),
			array( __( 'Amount', 'ecocash-donations' ), esc_html( $currency . ' ' . number_format( (float) $amount, 2 ) ) ),
			array( __( 'Reference', 'ecocash-donations' ), esc_html( $ref ) ),
		);

		if ( $eco_ref ) {
			$lines[] = array( __( 'EcoCash reference', 'ecocash-donations' ), esc_html( $eco_ref ) );
		}

		$rows = '';
		foreach ( $lines as $line ) {
			$rows .= sprintf(
				'<tr><td style="padding:6px 12px;border-bottom:1px solid #eee;color:#666;">%s</td><td style="padding:6px 12px;border-bottom:1px solid #eee;">%s</td></tr>',
				$line[0],
				$line[1]
			);
		}

		$message_html = '';
		if ( $message ) {
			$rows .= sprintf(
				'<tr><td style="padding:6px 12px;color:#666;vertical-align:top;">%s</td><td style="padding:6px 12px;">%s</td></tr>',
				esc_html__( 'Message', 'ecocash-donations' ),
				esc_html( $message )
			);
		}

		$admin_url = admin_url( 'post.php?post=' . absint( $donation_id ) . '&action=edit' );

		return '
<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;">
	<h2 style="margin:0 0 12px;">' . esc_html__( 'New EcoCash donation received', 'ecocash-donations' ) . '</h2>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;border-radius:6px;">
		' . $rows . '
	</table>
	<p style="margin:16px 0 0;">
		<a href="' . esc_url( $admin_url ) . '">' . esc_html__( 'View donation in admin', 'ecocash-donations' ) . '</a>
	</p>
</div>';
	}
}

new Ecocash_Emails();
