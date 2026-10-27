<?php
/**
 * Plugin Name:       OtkazPro – Withdrawal Button for WooCommerce
 * Plugin URI:        https://github.com/Project-Yordanov/otkazpro
 * Description:       Adds the online withdrawal function required by EU Directive 2023/2673 (Bulgaria: Art. 52a of the Consumer Protection Act) to WooCommerce: a “Withdraw from the contract here” button, a two-step form, partial withdrawal, an instant confirmation email and a register of statements.
 * Version:           2.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Project Yordanov
 * Author URI:        https://projectyordanov.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       otkazpro
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 *
 * @package PY\Otkaz
 */

defined( 'ABSPATH' ) || exit;

// Another edition of this plugin (WPOtkaz from GitHub) is already loaded: do not load twice,
// but on activation deactivate that edition so OtkazPro takes over on the next request.
if ( defined( 'PY_OTKAZ_VERSION' ) ) {
	register_activation_hook(
		__FILE__,
		static function () {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			$taken = array();
			foreach ( array( 'wpotkaz/wpotkaz.php', 'wpotkaz-main/wpotkaz.php', 'otkazpro-main/otkazpro.php', 'py-otkaz/py-otkaz.php' ) as $otkazpro_old ) {
				if ( plugin_basename( __FILE__ ) !== $otkazpro_old && is_plugin_active( $otkazpro_old ) ) {
					deactivate_plugins( $otkazpro_old, true );
					$taken[] = $otkazpro_old;
				}
			}
			if ( $taken ) {
				set_transient( 'py_otkaz_took_over', $taken, DAY_IN_SECONDS );
			}
		}
	);
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'OtkazPro: another edition of the plugin (WPOtkaz) is active. Deactivate it – OtkazPro takes over its settings and data.', 'otkazpro' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'PY_OTKAZ_VERSION', '2.1.0' );
define( 'PY_OTKAZ_FILE', __FILE__ );
define( 'PY_OTKAZ_DIR', plugin_dir_path( __FILE__ ) );
define( 'PY_OTKAZ_URL', plugin_dir_url( __FILE__ ) );
define( 'PY_OTKAZ_BASENAME', plugin_basename( __FILE__ ) );
define( 'PY_OTKAZ_MIN_WC', '8.0' );

/**
 * PSR-4-like autoloader: PY\Otkaz\Foo_Bar => includes/class-foo-bar.php,
 * PY\Otkaz\Emails\Foo => includes/emails/class-foo.php.
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'PY\\Otkaz\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$name  = array_pop( $parts );
		$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$file  = PY_OTKAZ_DIR . 'includes/' . $dir . 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

// HPOS + Cart/Checkout blocks compatibility.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook( __FILE__, array( 'PY\\Otkaz\\Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PY\\Otkaz\\Install', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, PY_OTKAZ_MIN_WC, '<' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %s: minimum WooCommerce version */
								__( 'OtkazPro requires WooCommerce %s or newer to be installed and active.', 'otkazpro' ),
								PY_OTKAZ_MIN_WC
							)
						)
					);
				}
			);
			return;
		}
		\PY\Otkaz\Plugin::instance()->init();
	}
);
