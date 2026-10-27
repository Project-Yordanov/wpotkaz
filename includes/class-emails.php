<?php
/**
 * Email registration, sending and the link in WooCommerce order emails.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Emails module.
 */
class Emails {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register' ) );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'order_email_link' ), 20, 4 );
		// Runs after email customizers (VillaTheme uses 999) have built the final message.
		add_filter( 'woocommerce_mail_callback_params', array( __CLASS__, 'ensure_link_in_message' ), PHP_INT_MAX - 10, 2 );
	}

	/**
	 * Register WC_Email classes.
	 *
	 * @param array $emails Emails.
	 * @return array
	 */
	public static function register( $emails ) {
		$emails['PY_Otkaz_Customer_Confirmation'] = new Emails\Customer_Confirmation();
		$emails['PY_Otkaz_Admin_Notification']    = new Emails\Admin_Notification();
		return $emails;
	}

	/**
	 * Get one of our email objects.
	 *
	 * @param string $key Class key.
	 * @return \WC_Email|null
	 */
	private static function email( $key ) {
		$emails = WC()->mailer()->get_emails();
		return isset( $emails[ $key ] ) ? $emails[ $key ] : null;
	}

	/**
	 * Send both emails right after a submission.
	 *
	 * @param Submission $submission Submission.
	 * @param \WC_Order  $order      Order.
	 */
	public static function send_for_submission( $submission, $order ) {
		self::send_customer( $submission, $order );

		$admin = self::email( 'PY_Otkaz_Admin_Notification' );
		if ( ! $admin || ! $admin->is_enabled() || ! $admin->get_recipient() ) {
			$submission->update_email_status( '', 'disabled' );
			return;
		}
		$sent = (bool) $admin->trigger( $submission, $order );
		$submission->update_email_status( '', $sent ? 'sent' : 'failed' );
		if ( ! $sent ) {
			wc_get_logger()->error( sprintf( 'Store notification for %s could not be sent.', $submission->number ), array( 'source' => 'otkazpro' ) );
		}
	}

	/**
	 * Send (or resend) the customer confirmation and record the result.
	 *
	 * @param Submission     $submission Submission.
	 * @param \WC_Order|null $order      Order.
	 * @return bool
	 */
	public static function send_customer( $submission, $order = null ) {
		$order = $order ? $order : Verifier::get_order( $submission->order_id );
		$email = self::email( 'PY_Otkaz_Customer_Confirmation' );
		$sent  = false;

		if ( $email && $order ) {
			$state = I18n::switch_to_order_language( $order, $submission->language );
			try {
				$sent = (bool) $email->trigger( $submission, $order );
			} finally {
				I18n::restore( $state );
			}
		}

		$submission->update_email_status( $sent ? 'sent' : 'failed' );

		if ( ! $sent ) {
			wc_get_logger()->error(
				sprintf( 'Withdrawal confirmation %s could not be sent to %s (wp_mail returned false).', $submission->number, $submission->email ),
				array( 'source' => 'otkazpro' )
			);
			/**
			 * Fires when the customer confirmation email fails.
			 *
			 * @param Submission $submission Submission.
			 */
			do_action( 'py_otkaz_mail_failed', $submission );
		}
		return $sent;
	}

	/**
	 * Link block in WooCommerce customer order emails (standard template hook).
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Admin email.
	 * @param bool      $plain_text    Plain text.
	 * @param \WC_Email $email         Email.
	 */
	public static function order_email_link( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		if ( $sent_to_admin ) {
			return;
		}
		echo wp_kses_post( self::link_block( $order, $email, (bool) $plain_text ) );
	}

	/**
	 * Fallback for email customizers (VillaTheme, YayMail, Kadence…) that replace the
	 * WooCommerce templates without firing woocommerce_email_after_order_table:
	 * append the block to the final message if it is not there yet.
	 *
	 * @param array     $params [to, subject, message, headers, attachments].
	 * @param \WC_Email $email  Email.
	 * @return array
	 */
	public static function ensure_link_in_message( $params, $email = null ) {
		if ( ! $email instanceof \WC_Email || ! isset( $params[2] ) || ! is_string( $params[2] ) ) {
			return $params;
		}
		if ( ! apply_filters( 'py_otkaz_email_link_fallback', true, $email ) ) {
			return $params;
		}
		$order = $email->object;
		if ( ! $order instanceof \WC_Order ) {
			return $params;
		}
		$plain   = 'plain' === $email->get_email_type();
		$message = $params[2];
		$url     = Form::order_url( $order, I18n::order_language( $order ) );
		if ( preg_match( '/(otkazpro|wpotkaz|py-otkaz)-email-link/', $message ) || false !== strpos( $message, esc_url( $url ) ) || false !== strpos( $message, $url ) ) {
			return $params;
		}
		$block = self::link_block( $order, $email, $plain );
		if ( '' === $block ) {
			return $params;
		}
		if ( $plain ) {
			// The message is already final here, so undo esc_url()'s &#038; as WooCommerce does for plain emails.
			$params[2] = rtrim( $message ) . "

" . str_replace( '&#038;', '&', trim( $block ) ) . "
";
			return $params;
		}
		$block = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;"><tr><td align="center" style="padding:16px 12px;"><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;"><tr><td style="font-family:Helvetica,Arial,sans-serif;font-size:14px;line-height:1.5;color:#333333;">' . $block . '</td></tr></table></td></tr></table>';
		$pos   = strripos( $message, '</body>' );
		$params[2] = false === $pos ? $message . $block : substr( $message, 0, $pos ) . $block . substr( $message, $pos );
		return $params;
	}

	/**
	 * The link block markup (or '' when not applicable).
	 *
	 * @param \WC_Order      $order Order.
	 * @param \WC_Email|null $email Email.
	 * @param bool           $plain Plain text.
	 * @return string
	 */
	public static function link_block( $order, $email, $plain = false ) {
		if ( ! $order instanceof \WC_Order || ! $email instanceof \WC_Email ) {
			return '';
		}
		if ( ! in_array( $email->id, (array) Settings::get( 'email_link_emails' ), true ) ) {
			return '';
		}
		if ( ! Order_Data::has_selectable_items( $order ) ) {
			return '';
		}

		$url   = Form::order_url( $order, I18n::order_language( $order ) );
		$label = Settings::text( 'text_start_button' );
		$intro = __( 'You can withdraw from the contract within 14 days of receiving the goods, without giving a reason:', 'otkazpro' );

		if ( $plain ) {
			return "
" . esc_html( wp_strip_all_tags( $intro ) ) . "
" . esc_html( $label ) . ': ' . esc_url( $url ) . "

";
		}

		return sprintf(
			'<div class="otkazpro-email-link" style="margin:0 0 24px;padding:12px 16px;border:1px solid #e5e5e5;border-radius:4px;"><p style="margin:0 0 6px;">%1$s</p><p style="margin:0;"><a href="%2$s" style="font-weight:600;text-decoration:underline;">%3$s</a></p></div>',
			esc_html( $intro ),
			esc_url( $url ),
			esc_html( $label )
		);
	}
}
