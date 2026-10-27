<?php
/**
 * Plugin bootstrap.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Wires all modules together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether init() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Get the instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks for all modules.
	 */
	public function init() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		Install::init();
		Settings::init();
		I18n::init();
		Order_Status::init();
		Exclusions::init();
		Form::init();
		Emails::init();
		Refunds::init();
		Account::init();
		Privacy::init();
		Cache_Check::init();

		if ( is_admin() ) {
			Admin::init();
		}

		add_filter( 'plugin_action_links_' . PY_OTKAZ_BASENAME, array( $this, 'action_links' ) );

		/**
		 * Fires when OtkazPro is fully loaded.
		 */
		do_action( 'py_otkaz_loaded' );
	}

	/**
	 * Add "Settings" and "Withdrawals" links on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( Settings::url() ), esc_html__( 'Settings', 'otkazpro' ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( Admin::url() ), esc_html__( 'Withdrawals', 'otkazpro' ) )
		);
		return $links;
	}
}
