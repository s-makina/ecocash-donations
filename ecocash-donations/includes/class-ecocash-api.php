<?php
/**
 * EcoCash Open API client.
 *
 * Endpoints and payload shapes verified against the official EcoCash
 * developer portal API (developers.ecocash.co.zw) as consumed by the
 * published community SDKs:
 *
 *   POST /api/v2/payment/instant/c2b/{environment}
 *        { customerMsisdn, amount, reason, currency, sourceReference }
 *
 *   POST /api/v1/transaction/c2b/status/{environment}
 *        { sourceMobileNumber, sourceReference }
 *
 *   POST /api/v2/refund/instant/c2b/{environment}
 *        { originalEcocashTransactionReference, refundCorrelator,
 *          sourceMobileNumber, amount, clientName, currency,
 *          reasonForRefund }
 *
 * Authentication: X-API-KEY header with the key from the developer portal.
 *
 * @package EcoCash_Donations
 */

defined( 'ABSPATH' ) || exit;

class Ecocash_API {

	const ENV_SANDBOX = 'sandbox';
	const ENV_LIVE    = 'live';

	/**
	 * Normalize a Zimbabwean mobile number to 263XXXXXXXXX.
	 *
	 * Accepts 0771234567 / 263771234567 / +263771234567 / 771234567.
	 * EcoCash prefixes: 071, 073, 077, 078.
	 *
	 * @param string $raw Raw phone input.
	 * @return string|WP_Error Normalized 263XXXXXXXXX or error.
	 */
	public static function normalize_phone( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw );

		if ( '' === $digits ) {
			return new WP_Error(
				'ecocash_invalid_phone',
				__( 'Please enter your EcoCash mobile number.', 'ecocash-donations' )
			);
		}

		if ( 0 === strpos( $digits, '0' ) && 10 === strlen( $digits ) ) {
			$digits = '263' . substr( $digits, 1 );
		} elseif ( 0 === strpos( $digits, '263' ) && 12 === strlen( $digits ) ) {
			// Already international format.
		} elseif ( 9 === strlen( $digits ) ) {
			$digits = '263' . $digits;
		}

		if ( ! preg_match( '/^263(71|73|77|78)\d{7}$/', $digits ) ) {
			return new WP_Error(
				'ecocash_invalid_phone',
				__(
					'That does not look like a valid EcoCash number. Use e.g. 0771234567.',
					'ecocash-donations'
				)
			);
		}

		return $digits;
	}

	/**
	 * Build request headers.
	 *
	 * @return array Headers incl. X-API-KEY.
	 */
	private static function headers() {
		$key = get_option( 'ecocash_api_key', '' );

		return array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'X-API-KEY'    => $key,
		);
	}

	/**
	 * Resolve base URL + path for the current environment.
	 *
	 * @param string $path API path e.g. /api/v2/payment/instant/c2b.
	 * @return string Full URL.
	 */
	private static function url( $path ) {
		$env = self::ENV_LIVE === get_option( 'ecocash_environment', self::ENV_SANDBOX )
			? self::ENV_LIVE
			: self::ENV_SANDBOX;

		return untrailingslashit( ECOCASH_DONATIONS_API_BASE ) . $path . '/' . $env;
	}

	/**
	 * POST JSON to the API.
	 *
	 * @param string $path    API path (environment appended).
	 * @param array  $payload JSON body.
	 * @return array|WP_Error Decoded response or error.
	 */
	private static function post( $path, $payload ) {
		$key = get_option( 'ecocash_api_key', '' );

		if ( '' === $key ) {
			return new WP_Error(
				'ecocash_no_key',
				__(
					'EcoCash API key is not configured. Set it in EcoCash Donations → Settings.',
					'ecocash-donations'
				)
			);
		}

		$response = wp_remote_post(
			self::url( $path ),
			array(
				'timeout' => 45,
				'headers' => self::headers(),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = '';
			if ( is_array( $body ) ) {
				$message = isset( $body['message'] ) ? $body['message']
					: ( isset( $body['error'] ) ? $body['error'] : '' );
			}
			if ( '' === $message ) {
				/* translators: %d: HTTP status code */
				$message = sprintf(
					__( 'EcoCash API error (HTTP %d).', 'ecocash-donations' ),
					$code
				);
			}

			return new WP_Error( 'ecocash_api_error', $message );
		}

		if ( null === $body ) {
			return new WP_Error(
				'ecocash_bad_response',
				__( 'Unexpected response from EcoCash.', 'ecocash-donations' )
			);
		}

		return $body;
	}

	/**
	 * Initiate a C2B instant payment (STK push to the donor's phone).
	 *
	 * @param string $msisdn     Donor phone (any common ZW format).
	 * @param float  $amount     Donation amount.
	 * @param string $reason     Payment description shown to donor.
	 * @param string $reference  Unique sourceReference for this donation.
	 * @param string $currency   USD, ZWL or ZIG.
	 * @return array|WP_Error
	 */
	public static function request_payment( $msisdn, $amount, $reason, $reference, $currency = 'USD' ) {
		$phone = self::normalize_phone( $msisdn );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}

		$amount = (float) $amount;
		if ( $amount <= 0 ) {
			return new WP_Error(
				'ecocash_invalid_amount',
				__( 'Donation amount must be greater than zero.', 'ecocash-donations' )
			);
		}

		return self::post(
			'/api/v2/payment/instant/c2b',
			array(
				'customerMsisdn'  => $phone,
				'amount'          => round( $amount, 2 ),
				'reason'          => mb_substr( (string) $reason, 0, 100 ),
				'currency'        => strtoupper( $currency ),
				'sourceReference' => $reference,
			)
		);
	}

	/**
	 * Look up the status of a C2B transaction.
	 *
	 * @param string $msisdn    Donor phone (any common ZW format).
	 * @param string $reference The sourceReference used on request_payment().
	 * @return array|WP_Error Response with status, ecocashReference, etc.
	 */
	public static function lookup( $msisdn, $reference ) {
		$phone = self::normalize_phone( $msisdn );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}

		return self::post(
			'/api/v1/transaction/c2b/status',
			array(
				'sourceMobileNumber' => $phone,
				'sourceReference'    => $reference,
			)
		);
	}

	/**
	 * Refund a completed transaction.
	 *
	 * @param string $ecocash_ref  Original EcoCash transaction reference.
	 * @param string $msisdn       Donor phone.
	 * @param float  $amount       Refund amount.
	 * @param string $reason       Reason for refund.
	 * @param string $currency     Currency code.
	 * @return array|WP_Error
	 */
	public static function refund( $ecocash_ref, $msisdn, $amount, $reason, $currency = 'USD' ) {
		$key = get_option( 'ecocash_api_key', '' );
		if ( '' === $key ) {
			return new WP_Error( 'ecocash_no_key', __( 'EcoCash API key is not configured.', 'ecocash-donations' ) );
		}

		$phone = self::normalize_phone( $msisdn );
		if ( is_wp_error( $phone ) ) {
			return $phone;
		}

		return self::post(
			'/api/v2/refund/instant/c2b',
			array(
				'originalEcocashTransactionReference' => $ecocash_ref,
				'refundCorrelator'                    => 'RFD-' . wp_generate_password( 12, false, false ),
				'clientName'                          => get_bloginfo( 'name' ),
				'amount'                              => round( (float) $amount, 2 ),
				'currency'                            => strtoupper( $currency ),
				'reasonForRefund'                     => mb_substr( (string) $reason, 0, 100 ),
				'sourceMobileNumber'                  => $phone,
			)
		);
	}

	/**
	 * Extract the status string from a lookup response.
	 *
	 * @param array $body Decoded lookup response.
	 * @return string SUCCESS | FAILED | PENDING | '' when unknown.
	 */
	public static function extract_status( $body ) {
		if ( ! is_array( $body ) ) {
			return '';
		}

		$status = isset( $body['status'] ) ? strtoupper( (string) $body['status'] ) : '';

		// Some responses nest under data.
		if ( '' === $status && isset( $body['data']['status'] ) ) {
			$suffix = strtoupper( (string) $body['data']['status'] );
			$status = $suffix;
		}

		return $status;
	}
}
