<?php
/**
 * Store reminder: refund deadline of withdrawals is near or past.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz\Emails;

use PY\Otkaz\I18n;
use PY\Otkaz\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * "Refund reminder" – daily digest to the store email(s) from the plugin settings.
 */
class Admin_Refund_Reminder extends Base {

	/**
	 * Statements in this reminder.
	 *
	 * @var \PY\Otkaz\Submission[]
	 */
	public $submissions = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'py_otkaz_admin_refund_reminder';
		$this->customer_email = false;
		$this->title          = __( 'Withdrawal refund reminder', 'otkazpro' );
		$this->description    = __( 'Sent to the store when the 14-day refund deadline of a withdrawal is near and the withdrawal is not marked as processed. Each withdrawal is included once. Recipients are set in WooCommerce → Settings → Withdrawal.', 'otkazpro' );
		$this->template_html  = 'emails/admin-refund-reminder.php';
		$this->template_plain = 'emails/plain/admin-refund-reminder.php';
		$this->setup_base();
		$this->placeholders['{count}'] = '';

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '[{site_title}] Refund deadline: {count} withdrawal(s) to process', 'otkazpro' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Withdrawals waiting for a refund', 'otkazpro' );
	}

	/**
	 * Default additional content.
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return __( 'Refunds are marked as processed automatically when you create a refund in the order. If a withdrawal needs no refund, use “Mark as processed” in WooCommerce → Withdrawals.', 'otkazpro' );
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
	 * Template args.
	 *
	 * @param bool $plain Plain text.
	 * @return array
	 */
	protected function template_args( $plain ) {
		return array_merge( parent::template_args( $plain ), array( 'submissions' => $this->submissions ) );
	}

	/**
	 * Send.
	 *
	 * @param \PY\Otkaz\Submission[] $submissions Statements.
	 * @return bool
	 */
	public function trigger( $submissions ) {
		if ( ! $submissions ) {
			return false;
		}
		$state = I18n::switch_to_site_language();
		$sent  = false;
		try {
			$this->submissions             = $submissions;
			$this->object                  = null;
			$this->placeholders['{count}'] = (string) count( $submissions );
			if ( $this->is_enabled() && $this->get_recipient() ) {
				$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		} finally {
			I18n::restore( $state );
		}
		return $sent;
	}
}
