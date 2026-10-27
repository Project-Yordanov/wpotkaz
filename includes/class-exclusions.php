<?php
/**
 * Products without right of withdrawal (Art. 57 CPA).
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Product / variation / category / order-item exclusions.
 */
class Exclusions {

	const META_FLAG   = '_py_otkaz_excluded';     // '' inherit | 'yes' | 'no'.
	const META_REASON = '_py_otkaz_reason';       // personalized | perishable | other.
	const META_TEXT   = '_py_otkaz_reason_text';  // Free text for "other".
	const TERM_FLAG   = 'py_otkaz_excluded';
	const TERM_REASON = 'py_otkaz_reason';
	const TERM_TEXT   = 'py_otkaz_reason_text';

	/**
	 * Product IDs whose storefront label was already printed (classic hook + block fallback).
	 *
	 * @var int[]
	 */
	private static $printed = array();

	/**
	 * Hooks.
	 */
	public static function init() {
		// Product.
		add_action( 'woocommerce_product_options_advanced', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
		// Variation.
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variation_fields' ), 20, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 10, 2 );
		// Category.
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'category_add_fields' ) );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'category_edit_fields' ) );
		add_action( 'created_product_cat', array( __CLASS__, 'save_category' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'save_category' ) );

		if ( Settings::enabled( 'exclusion_label' ) ) {
			add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'single_product_label' ), 35 );
			add_filter( 'render_block_woocommerce/add-to-cart-form', array( __CLASS__, 'block_product_label' ), 10, 2 );
			add_filter( 'woocommerce_available_variation', array( __CLASS__, 'variation_label' ), 10, 3 );
			add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_label' ), 20, 2 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'label_styles' ) );
		}
	}

	/**
	 * Reason options.
	 *
	 * @return array
	 */
	public static function reasons() {
		return array(
			'personalized' => __( 'Made to the consumer’s specifications or personalised', 'otkazpro' ),
			'perishable'   => __( 'Liable to deteriorate or expire rapidly', 'otkazpro' ),
			'other'        => __( 'Other (specify)', 'otkazpro' ),
		);
	}

	/**
	 * Human-readable reason.
	 *
	 * @param string $reason Reason key.
	 * @param string $text   Free text.
	 * @return string
	 */
	public static function reason_label( $reason, $text = '' ) {
		if ( 'other' === $reason ) {
			return $text ? $text : __( 'Excluded from the right of withdrawal', 'otkazpro' );
		}
		$reasons = self::reasons();
		return isset( $reasons[ $reason ] ) ? $reasons[ $reason ] : __( 'Excluded from the right of withdrawal', 'otkazpro' );
	}

	/**
	 * Help text shown next to the fields.
	 *
	 * @return string
	 */
	private static function help_text() {
		return __( 'Do not mark goods that lose the right of withdrawal only once unsealed (hygiene products, cosmetics, sealed audio/video/software) – they remain withdrawable while sealed.', 'otkazpro' );
	}

	/* ---------------------------------------------------------------------
	 * Resolution
	 * ------------------------------------------------------------------ */

	/**
	 * Exclusion for a product (variation → parent → categories).
	 *
	 * @param \WC_Product|int|null $product Product.
	 * @return array|false ['reason' => key, 'label' => text, 'source' => product|variation|category]
	 */
	public static function for_product( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			$product = $product ? wc_get_product( $product ) : null;
		}
		if ( ! $product ) {
			return false;
		}

		$result = false;

		if ( $product->is_type( 'variation' ) ) {
			$own = self::from_meta( $product, 'variation' );
			if ( null !== $own ) {
				return apply_filters( 'py_otkaz_product_exclusion', $own, $product );
			}
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$result = self::resolve_parent( $parent );
			}
		} else {
			$result = self::resolve_parent( $product );
		}

		return apply_filters( 'py_otkaz_product_exclusion', $result, $product );
	}

	/**
	 * Product (non-variation) own meta, then categories.
	 *
	 * @param \WC_Product $product Product.
	 * @return array|false
	 */
	private static function resolve_parent( $product ) {
		$own = self::from_meta( $product, 'product' );
		if ( null !== $own ) {
			return $own;
		}
		foreach ( $product->get_category_ids() as $term_id ) {
			$flag = get_term_meta( $term_id, self::TERM_FLAG, true );
			if ( 'yes' === $flag ) {
				$reason = (string) get_term_meta( $term_id, self::TERM_REASON, true );
				return array(
					'reason' => $reason ? $reason : 'other',
					'label'  => self::reason_label( $reason, (string) get_term_meta( $term_id, self::TERM_TEXT, true ) ),
					'source' => 'category',
				);
			}
		}
		return false;
	}

	/**
	 * Read product/variation meta. Returns null when "inherit".
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $source  Source label.
	 * @return array|false|null
	 */
	private static function from_meta( $product, $source ) {
		$flag = (string) $product->get_meta( self::META_FLAG );
		if ( 'no' === $flag ) {
			return false;
		}
		if ( 'yes' !== $flag ) {
			return null;
		}
		$reason = (string) $product->get_meta( self::META_REASON );
		return array(
			'reason' => $reason ? $reason : 'other',
			'label'  => self::reason_label( $reason, (string) $product->get_meta( self::META_TEXT ) ),
			'source' => $source,
		);
	}

	/**
	 * Exclusion for an order line item (product rules + personalisation meta keys).
	 *
	 * @param \WC_Order_Item_Product $item  Item.
	 * @param \WC_Order              $order Order.
	 * @return array|false
	 */
	public static function for_item( $item, $order ) {
		$result = self::for_product( $item->get_product() );

		if ( ! $result ) {
			$keys = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) Settings::get( 'exclusion_meta_keys' ) ) ) );
			if ( $keys ) {
				$keys = array_map( 'strtolower', $keys );
				foreach ( $item->get_meta_data() as $meta ) {
					$data = $meta->get_data();
					if ( in_array( strtolower( (string) $data['key'] ), $keys, true ) && ! self::is_empty_value( $data['value'] ) ) {
						$result = array(
							'reason' => 'personalized',
							'label'  => self::reason_label( 'personalized' ),
							'source' => 'item_meta',
						);
						break;
					}
				}
			}
		}

		/**
		 * Filter whether an order item is excluded from the right of withdrawal.
		 *
		 * @param array|false            $result ['reason','label','source'] or false.
		 * @param \WC_Order_Item_Product $item   Order item.
		 * @param \WC_Order              $order  Order.
		 */
		return apply_filters( 'py_otkaz_item_is_excluded', $result, $item, $order );
	}

	/**
	 * Empty check for meta values (arrays, whitespace).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_empty_value( $value ) {
		if ( is_array( $value ) ) {
			return ! array_filter( $value );
		}
		return '' === trim( wp_strip_all_tags( (string) $value ) );
	}

	/* ---------------------------------------------------------------------
	 * Admin fields
	 * ------------------------------------------------------------------ */

	/**
	 * Product edit → Advanced tab.
	 */
	public static function product_fields() {
		global $product_object;
		$product = $product_object instanceof \WC_Product ? $product_object : null;

		echo '<div class="options_group otkazpro-product-fields">';
		woocommerce_wp_select(
			array(
				'id'          => self::META_FLAG,
				'label'       => __( 'Right of withdrawal', 'otkazpro' ),
				'value'       => $product ? (string) $product->get_meta( self::META_FLAG ) : '',
				'options'     => array(
					''    => __( 'Inherit from category', 'otkazpro' ),
					'yes' => __( 'No right of withdrawal', 'otkazpro' ),
					'no'  => __( 'Has right of withdrawal (override category)', 'otkazpro' ),
				),
				'desc_tip'    => true,
				'description' => self::help_text(),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => self::META_REASON,
				'label'   => __( 'Reason', 'otkazpro' ),
				'value'   => $product ? (string) $product->get_meta( self::META_REASON ) : '',
				'options' => self::reasons(),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => self::META_TEXT,
				'label'       => __( 'Reason text', 'otkazpro' ),
				'value'       => $product ? (string) $product->get_meta( self::META_TEXT ) : '',
				'description' => __( 'Only for “Other”. Shown to the customer.', 'otkazpro' ),
				'desc_tip'    => true,
			)
		);
		echo '</div>';
	}

	/**
	 * Save product fields.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function save_product( $product ) {
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $product->get_id() ) || ! isset( $_POST[ self::META_FLAG ] ) ) {
			return;
		}
		self::save_to_product(
			$product,
			sanitize_key( wp_unslash( $_POST[ self::META_FLAG ] ) ),
			isset( $_POST[ self::META_REASON ] ) ? sanitize_key( wp_unslash( $_POST[ self::META_REASON ] ) ) : '',
			isset( $_POST[ self::META_TEXT ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_TEXT ] ) ) : ''
		);
	}

	/**
	 * Persist values on a product object (no save()).
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $flag    Flag.
	 * @param string      $reason  Reason.
	 * @param string      $text    Text.
	 */
	private static function save_to_product( $product, $flag, $reason, $text ) {
		$flag   = in_array( $flag, array( 'yes', 'no' ), true ) ? $flag : '';
		$reason = array_key_exists( $reason, self::reasons() ) ? $reason : 'personalized';

		if ( '' === $flag ) {
			$product->delete_meta_data( self::META_FLAG );
			$product->delete_meta_data( self::META_REASON );
			$product->delete_meta_data( self::META_TEXT );
			return;
		}
		$product->update_meta_data( self::META_FLAG, $flag );
		$product->update_meta_data( self::META_REASON, $reason );
		$product->update_meta_data( self::META_TEXT, $text );
	}

	/**
	 * Variation fields.
	 *
	 * @param int      $loop           Index.
	 * @param array    $variation_data Data.
	 * @param \WP_Post $variation      Variation post.
	 */
	public static function variation_fields( $loop, $variation_data, $variation ) {
		$product = wc_get_product( $variation->ID );
		if ( ! $product ) {
			return;
		}
		echo '<div class="otkazpro-variation-fields form-row form-row-full">';
		woocommerce_wp_select(
			array(
				'id'            => self::META_FLAG . '_' . $loop,
				'name'          => self::META_FLAG . '[' . $loop . ']',
				'label'         => __( 'Right of withdrawal', 'otkazpro' ),
				'value'         => (string) $product->get_meta( self::META_FLAG ),
				'options'       => array(
					''    => __( 'Same as parent product', 'otkazpro' ),
					'yes' => __( 'No right of withdrawal', 'otkazpro' ),
					'no'  => __( 'Has right of withdrawal', 'otkazpro' ),
				),
				'wrapper_class' => 'form-row form-row-first',
			)
		);
		woocommerce_wp_select(
			array(
				'id'            => self::META_REASON . '_' . $loop,
				'name'          => self::META_REASON . '[' . $loop . ']',
				'label'         => __( 'Reason', 'otkazpro' ),
				'value'         => (string) $product->get_meta( self::META_REASON ),
				'options'       => self::reasons(),
				'wrapper_class' => 'form-row form-row-last',
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'            => self::META_TEXT . '_' . $loop,
				'name'          => self::META_TEXT . '[' . $loop . ']',
				'label'         => __( 'Reason text (for “Other”)', 'otkazpro' ),
				'value'         => (string) $product->get_meta( self::META_TEXT ),
				'wrapper_class' => 'form-row form-row-full',
			)
		);
		echo '</div>';
	}

	/**
	 * Save variation fields.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Index.
	 */
	public static function save_variation( $variation_id, $loop ) {
		if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['security'] ) ), 'save-variations' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $variation_id ) || ! isset( $_POST[ self::META_FLAG ][ $loop ] ) ) {
			return;
		}
		$variation = wc_get_product( $variation_id );
		if ( ! $variation ) {
			return;
		}
		self::save_to_product(
			$variation,
			sanitize_key( wp_unslash( $_POST[ self::META_FLAG ][ $loop ] ) ),
			isset( $_POST[ self::META_REASON ][ $loop ] ) ? sanitize_key( wp_unslash( $_POST[ self::META_REASON ][ $loop ] ) ) : '',
			isset( $_POST[ self::META_TEXT ][ $loop ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::META_TEXT ][ $loop ] ) ) : ''
		);
		$variation->save();
	}

	/**
	 * Category "add" screen.
	 */
	public static function category_add_fields() {
		wp_nonce_field( 'py_otkaz_term', 'py_otkaz_term_nonce' );
		?>
		<div class="form-field">
			<label for="py_otkaz_excluded"><input type="checkbox" name="py_otkaz_excluded" id="py_otkaz_excluded" value="yes" /> <?php esc_html_e( 'No right of withdrawal for products in this category', 'otkazpro' ); ?></label>
			<p class="description"><?php echo esc_html( self::help_text() ); ?></p>
		</div>
		<div class="form-field">
			<label for="py_otkaz_reason"><?php esc_html_e( 'Reason', 'otkazpro' ); ?></label>
			<select name="py_otkaz_reason" id="py_otkaz_reason">
				<?php foreach ( self::reasons() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="form-field">
			<label for="py_otkaz_reason_text"><?php esc_html_e( 'Reason text (for “Other”)', 'otkazpro' ); ?></label>
			<input type="text" name="py_otkaz_reason_text" id="py_otkaz_reason_text" value="" />
		</div>
		<?php
	}

	/**
	 * Category "edit" screen.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function category_edit_fields( $term ) {
		$flag   = get_term_meta( $term->term_id, self::TERM_FLAG, true );
		$reason = get_term_meta( $term->term_id, self::TERM_REASON, true );
		$text   = get_term_meta( $term->term_id, self::TERM_TEXT, true );
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Right of withdrawal', 'otkazpro' ); ?></th>
			<td>
				<?php wp_nonce_field( 'py_otkaz_term', 'py_otkaz_term_nonce' ); ?>
				<label for="py_otkaz_excluded"><input type="checkbox" name="py_otkaz_excluded" id="py_otkaz_excluded" value="yes" <?php checked( 'yes', $flag ); ?> /> <?php esc_html_e( 'No right of withdrawal for products in this category', 'otkazpro' ); ?></label>
				<p class="description"><?php echo esc_html( self::help_text() ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="py_otkaz_reason"><?php esc_html_e( 'Reason', 'otkazpro' ); ?></label></th>
			<td>
				<select name="py_otkaz_reason" id="py_otkaz_reason">
					<?php foreach ( self::reasons() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $reason, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="py_otkaz_reason_text"><?php esc_html_e( 'Reason text (for “Other”)', 'otkazpro' ); ?></label></th>
			<td><input type="text" name="py_otkaz_reason_text" id="py_otkaz_reason_text" value="<?php echo esc_attr( $text ); ?>" /></td>
		</tr>
		<?php
	}

	/**
	 * Save category meta.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save_category( $term_id ) {
		if ( ! isset( $_POST['py_otkaz_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['py_otkaz_term_nonce'] ) ), 'py_otkaz_term' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}
		if ( isset( $_POST['py_otkaz_excluded'] ) && 'yes' === $_POST['py_otkaz_excluded'] ) {
			$reason = isset( $_POST['py_otkaz_reason'] ) ? sanitize_key( wp_unslash( $_POST['py_otkaz_reason'] ) ) : 'personalized';
			update_term_meta( $term_id, self::TERM_FLAG, 'yes' );
			update_term_meta( $term_id, self::TERM_REASON, array_key_exists( $reason, self::reasons() ) ? $reason : 'personalized' );
			update_term_meta( $term_id, self::TERM_TEXT, isset( $_POST['py_otkaz_reason_text'] ) ? sanitize_text_field( wp_unslash( $_POST['py_otkaz_reason_text'] ) ) : '' );
		} else {
			delete_term_meta( $term_id, self::TERM_FLAG );
			delete_term_meta( $term_id, self::TERM_REASON );
			delete_term_meta( $term_id, self::TERM_TEXT );
		}
	}

	/* ---------------------------------------------------------------------
	 * Storefront label
	 * ------------------------------------------------------------------ */

	/**
	 * Label markup.
	 *
	 * @param array $exclusion Exclusion data.
	 * @return string
	 */
	public static function label_html( $exclusion ) {
		return sprintf(
			'<p class="otkazpro-label"><strong>%1$s</strong> <span>%2$s</span></p>',
			esc_html__( 'No right of withdrawal', 'otkazpro' ),
			esc_html( '(' . $exclusion['label'] . ')' )
		);
	}

	/**
	 * Single product page (parent level).
	 */
	public static function single_product_label() {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		$exclusion = self::for_product( $product );
		if ( $exclusion && ! in_array( $product->get_id(), self::$printed, true ) ) {
			self::$printed[] = $product->get_id();
			echo wp_kses_post( self::label_html( $exclusion ) );
		}
	}

	/**
	 * Block themes: append the label after the Add to cart form block.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Block.
	 * @return string
	 */
	public static function block_product_label( $content, $block ) {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $content;
		}
		$product   = wc_get_product( get_the_ID() );
		$exclusion = $product ? self::for_product( $product ) : false;
		if ( ! $exclusion || in_array( $product->get_id(), self::$printed, true ) ) {
			return $content;
		}
		self::$printed[] = $product->get_id();
		return $content . self::label_html( $exclusion );
	}

	/**
	 * Variation-specific label (when it differs from the parent).
	 *
	 * @param array                 $data      Variation data.
	 * @param \WC_Product           $parent    Parent.
	 * @param \WC_Product_Variation $variation Variation.
	 * @return array
	 */
	public static function variation_label( $data, $parent, $variation ) {
		$own    = self::for_product( $variation );
		$parent_ex = self::for_product( $parent );
		if ( $own && ! $parent_ex ) {
			$data['availability_html'] = ( isset( $data['availability_html'] ) ? $data['availability_html'] : '' ) . self::label_html( $own );
		}
		return $data;
	}

	/**
	 * Cart / checkout item data (classic + blocks via Store API).
	 *
	 * @param array $item_data Item data.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function cart_item_label( $item_data, $cart_item ) {
		$product   = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
		$exclusion = $product ? self::for_product( $product ) : false;
		if ( $exclusion ) {
			$item_data[] = array(
				'key'     => __( 'Right of withdrawal', 'otkazpro' ),
				'value'   => __( 'No right of withdrawal', 'otkazpro' ) . ' (' . $exclusion['label'] . ')',
				'display' => esc_html__( 'No right of withdrawal', 'otkazpro' ) . ' (' . esc_html( $exclusion['label'] ) . ')',
			);
		}
		return $item_data;
	}

	/**
	 * Tiny inline style for the product-page label.
	 */
	public static function label_styles() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		wp_register_style( 'otkazpro-label', false, array(), PY_OTKAZ_VERSION );
		wp_enqueue_style( 'otkazpro-label' );
		wp_add_inline_style( 'otkazpro-label', '.otkazpro-label{margin:.75em 0;padding:.5em .75em;border-left:3px solid currentColor;font-size:.9em;line-height:1.4}' );
	}
}
