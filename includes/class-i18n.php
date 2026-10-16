<?php
/**
 * WPML / Polylang integration.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Multilingual helpers.
 */
class I18n {

	const CONTEXT = 'otkazpro';

	/**
	 * Hooks.
	 */
	public static function init() {
		// Polylang requires registration on every admin request.
		add_action( 'admin_init', array( __CLASS__, 'register_polylang_strings' ) );
	}

	/**
	 * Setting keys translatable via String Translation.
	 *
	 * @return string[]
	 */
	public static function translatable_keys() {
		return array( 'text_intro', 'text_start_button', 'text_confirm_button', 'text_statement', 'text_return_instructions' );
	}

	/**
	 * Register stored strings with WPML (called on settings save).
	 */
	public static function register_strings() {
		foreach ( self::translatable_keys() as $key ) {
			if ( Settings::is_stored( $key ) ) {
				do_action( 'wpml_register_single_string', self::CONTEXT, $key, (string) Settings::get( $key ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party / core hook.
			}
		}
	}

	/**
	 * Register strings with Polylang.
	 */
	public static function register_polylang_strings() {
		if ( ! function_exists( 'pll_register_string' ) ) {
			return;
		}
		foreach ( self::translatable_keys() as $key ) {
			if ( Settings::is_stored( $key ) ) {
				pll_register_string( $key, (string) Settings::get( $key ), self::CONTEXT, 0 === strpos( $key, 'text_' ) && 'text_start_button' !== $key && 'text_confirm_button' !== $key );
			}
		}
	}

	/**
	 * Translate a stored setting string.
	 *
	 * @param string      $key   Setting key.
	 * @param string      $value Original value.
	 * @param string|null $lang  Language code.
	 * @return string
	 */
	public static function translate( $key, $value, $lang = null ) {
		if ( ! self::is_multilingual() ) {
			return $value;
		}
		if ( has_filter( 'wpml_translate_single_string' ) ) {
			return (string) apply_filters( 'wpml_translate_single_string', $value, self::CONTEXT, $key, $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party / core hook.
		}
		if ( $lang && function_exists( 'pll_translate_string' ) ) {
			return (string) pll_translate_string( $value, $lang );
		}
		if ( function_exists( 'pll__' ) ) {
			return (string) pll__( $value );
		}
		return $value;
	}

	/**
	 * Current front-end language code ('' when no multilingual plugin).
	 *
	 * @return string
	 */
	public static function current_language() {
		// Other plugins sometimes answer WPML's filters without WPML being installed.
		if ( ! self::is_multilingual() ) {
			return '';
		}
		$lang = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party / core hook.
		if ( ! $lang && function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language();
		}
		return $lang ? (string) $lang : '';
	}

	/**
	 * Is WPML or Polylang actually active?
	 *
	 * @return bool
	 */
	public static function is_multilingual() {
		return defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'pll_current_language' );
	}

	/**
	 * Language the order was placed in.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	public static function order_language( $order ) {
		$lang = (string) $order->get_meta( 'wpml_language' );
		if ( ! $lang && function_exists( 'pll_get_post_language' ) ) {
			$pll = pll_get_post_language( $order->get_id() );
			$lang = $pll ? (string) $pll : '';
		}
		return (string) apply_filters( 'py_otkaz_order_language', $lang, $order );
	}

	/**
	 * Locale for a language code.
	 *
	 * @param string $lang Language code.
	 * @return string
	 */
	public static function locale_for( $lang ) {
		if ( ! $lang || ! self::is_multilingual() ) {
			return '';
		}
		$languages = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party / core hook.
		if ( is_array( $languages ) && isset( $languages[ $lang ]['default_locale'] ) ) {
			return (string) $languages[ $lang ]['default_locale'];
		}
		if ( function_exists( 'pll_languages_list' ) ) {
			$slugs   = pll_languages_list( array( 'fields' => 'slug' ) );
			$locales = pll_languages_list( array( 'fields' => 'locale' ) );
			$index   = array_search( $lang, (array) $slugs, true );
			if ( false !== $index && isset( $locales[ $index ] ) ) {
				return (string) $locales[ $index ];
			}
		}
		return '';
	}

	/**
	 * Locale of the site's default language. Multilingual plugins filter get_locale()
	 * to the visitor's language, but emails to the store must use the site language.
	 *
	 * @return string
	 */
	public static function site_locale() {
		$locale = '';
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$locale = self::locale_for( (string) apply_filters( 'wpml_default_language', null ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party hook.
		} elseif ( function_exists( 'pll_default_language' ) ) {
			$locale = (string) pll_default_language( 'locale' );
		} elseif ( class_exists( 'TRP_Translate_Press' ) ) {
			$trp    = get_option( 'trp_settings' );
			$locale = is_array( $trp ) && ! empty( $trp['default-language'] ) ? (string) $trp['default-language'] : '';
		}
		return $locale ? $locale : get_locale();
	}

	/**
	 * Switch WPML language + WP locale to the order language (fallback: site locale).
	 *
	 * @param \WC_Order $order    Order.
	 * @param string    $fallback Fallback language code.
	 * @return array State for restore().
	 */
	public static function switch_to_order_language( $order, $fallback = '' ) {
		$lang  = self::order_language( $order );
		$lang  = $lang ? $lang : $fallback;
		$state = array(
			'wpml_prev' => '',
			'switched'  => false,
		);

		if ( $lang && self::is_multilingual() && has_action( 'wpml_switch_language' ) ) {
			$state['wpml_prev'] = self::current_language();
			do_action( 'wpml_switch_language', $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party / core hook.
		}

		$locale = self::locale_for( $lang );
		if ( ! $locale ) {
			$locale = get_locale();
		}
		$state['switched'] = switch_to_locale( $locale );

		return $state;
	}

	/**
	 * Switch WPML language + WP locale to the site's default language (emails to the store).
	 *
	 * @return array State for restore().
	 */
	public static function switch_to_site_language() {
		$state = array(
			'wpml_prev' => '',
			'switched'  => false,
		);
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && has_action( 'wpml_switch_language' ) ) {
			$default = (string) apply_filters( 'wpml_default_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party hook.
			if ( $default ) {
				$state['wpml_prev'] = self::current_language();
				do_action( 'wpml_switch_language', $default ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party hook.
			}
		}
		$state['switched'] = switch_to_locale( self::site_locale() );
		return $state;
	}

	/**
	 * Restore after switch_to_order_language() / switch_to_site_language().
	 *
	 * @param array $state State.
	 */
	public static function restore( $state ) {
		if ( ! empty( $state['switched'] ) ) {
			restore_previous_locale();
		}
		if ( ! empty( $state['wpml_prev'] ) ) {
			do_action( 'wpml_switch_language', $state['wpml_prev'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party / core hook.
		}
	}
}
