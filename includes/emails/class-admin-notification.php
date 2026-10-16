<?php
/**
 * Store notification about a new withdrawal.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz\Emails;

use PY\Otkaz\I18n;
use PY\Otkaz\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * "New withdrawal" – sent to the store email(s) from the plugin settings.
 */
class Admin_Notification extends Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'py_otkaz_admin_new';
		$this->customer_email = false;
		$this->title          = __( 'New withdrawal', 'otkazpro' );
		$this->description    = __( 'Sent to the store when a customer submits a withdrawal statement. Recipients are set in WooCommerce → Settings → Withdrawal.', 'otkazpro' );
		$this->template_html  = 'emails/admin-new-otkaz.php';
		$this->template_plain = 'emails/plain/admin-new-otkaz.php';
		$this->setup_base();

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] New withdrawal {otkaz_number} – order #{order_number}', 'otkazpro' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'New withdrawal from contract', 'otkazpro' );
	}

	/**
	 * Default additional content.
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return __( 'Refund the payments received, including standard delivery costs, without undue delay and not later than 14 days from today. You may withhold the refund until you have received the goods back or proof of sending.', 'otkazpro' );
	}

	/**
	 * Recipients come from the plugin settings.
	 *
	 * @return string
	 */
	public function get_recipient() {
		$this->recipient = (string) Settings::get( 'notify_emails' );
		return parent::get_recipient();
	}

	/**
	 * Hide the recipient field (managed in plugin settings).
	 */
	public function init_form_fields() {
		parent::init_form_fields();
		unset( $this->form_fields['recipient'] );
	}

	/**
	 * Reply-To: the customer.
	 *
	 * @return string
	 */
	public function get_headers() {
		$headers = parent::get_headers();
		if ( $this->submission && is_email( $this->submission->email ) ) {
			// Replace WooCommerce's default store Reply-To with the customer.
			$headers  = (string) preg_replace( '/^Reply-to:.*(\r\n|\n)?/mi', '', (string) $headers );
			$name     = str_replace( array( "\r", "\n", '"' ), '', $this->submission->name );
			$headers .= 'Reply-to: "' . $name . '" <' . $this->submission->email . ">\r\n";
		}
		return $headers;
	}

	/**
	 * Send.
	 *
	 * @param \PY\Otkaz\Submission $submission Submission.
	 * @param \WC_Order            $order      Order.
	 * @return bool
	 */
	public function trigger( $submission, $order = null ) {
		if ( ! $submission || ! $order ) {
			return false;
		}
		// Store emails always in the site language (not the visitor's language on multilingual sites).
		$state = I18n::switch_to_site_language();
		$sent  = false;
		try {
			$this->prepare( $submission, $order );
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		} finally {
			I18n::restore( $state );
		}
		return $sent;
	}
}
