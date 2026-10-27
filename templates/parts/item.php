<?php
/**
 * One order item row in the withdrawal form.
 *
 * Override: yourtheme/woocommerce/otkazpro/parts/item.php
 *
 * @package PY\Otkaz
 *
 * @var array  $item   Prepared item (see Order_Data::get_items) + checked, qty.
 * @var string $group  selectable|excluded|done.
 * @var bool   $strict Strict exclusion mode.
 * @var array  $fields Field errors.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

$otkazpro_id       = (int) $item['id'];
$otkazpro_cb_id    = 'otkazpro_item_' . $otkazpro_id;
$otkazpro_qty_id   = 'otkazpro_qty_' . $otkazpro_id;
$otkazpro_error    = isset( $fields[ $otkazpro_cb_id ] ) ? $fields[ $otkazpro_cb_id ] : '';
$otkazpro_can      = 'done' !== $group && $item['selectable'];
$otkazpro_describe = array( 'otkazpro_item_info_' . $otkazpro_id );
if ( $otkazpro_error ) {
	$otkazpro_describe[] = 'otkazpro_item_error_' . $otkazpro_id;
}
$otkazpro_classes = array( 'otkazpro-item', 'otkazpro-item--' . $group );
if ( ! $otkazpro_can ) {
	$otkazpro_classes[] = 'is-disabled';
}
if ( $otkazpro_error ) {
	$otkazpro_classes[] = 'has-error';
}
?>
<li class="<?php echo esc_attr( implode( ' ', $otkazpro_classes ) ); ?>" data-otkazpro-item>
	<div class="otkazpro-item__check">
		<?php if ( $otkazpro_can ) : ?>
			<input type="checkbox" id="<?php echo esc_attr( $otkazpro_cb_id ); ?>" name="otkazpro_items[<?php echo esc_attr( (string) $otkazpro_id ); ?>][selected]" value="1" <?php checked( ! empty( $item['checked'] ) ); ?>
				data-otkazpro-check aria-describedby="<?php echo esc_attr( implode( ' ', $otkazpro_describe ) ); ?>"<?php echo $otkazpro_error ? ' aria-invalid="true"' : ''; ?> />
		<?php else : ?>
			<input type="checkbox" id="<?php echo esc_attr( $otkazpro_cb_id ); ?>" disabled aria-describedby="<?php echo esc_attr( implode( ' ', $otkazpro_describe ) ); ?>" />
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $item['image'] ) ) : ?>
		<div class="otkazpro-item__img" aria-hidden="true"><?php
			echo wp_kses(
				$item['image'],
				array(
					'img' => array_fill_keys( array( 'src', 'srcset', 'sizes', 'width', 'height', 'class', 'alt', 'loading', 'decoding' ), true ),
				)
			);
			?></div>
	<?php endif; ?>

	<div class="otkazpro-item__body">
		<label class="otkazpro-item__name" for="<?php echo esc_attr( $otkazpro_cb_id ); ?>"><?php echo esc_html( $item['name'] ); ?></label>
		<?php if ( ! empty( $item['meta_html'] ) ) : ?>
			<div class="otkazpro-item__meta"><?php echo wp_kses_post( $item['meta_html'] ); ?></div>
		<?php endif; ?>

		<div class="otkazpro-item__info" id="otkazpro_item_info_<?php echo esc_attr( (string) $otkazpro_id ); ?>">
			<span>
				<?php
				/* translators: %d: purchased quantity */
				echo esc_html( sprintf( _n( 'Purchased: %d pc.', 'Purchased: %d pcs.', $item['ordered'], 'otkazpro' ), $item['ordered'] ) );
				?>
			</span>
			<?php foreach ( $item['requests'] as $otkazpro_req ) : ?>
				<span class="otkazpro-item__done">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: quantity, 2: date, 3: statement number */
							__( 'Withdrawal submitted on %2$s for %1$d pcs. (%3$s)', 'otkazpro' ),
							$otkazpro_req['qty'],
							\PY\Otkaz\Util::format_gmt( $otkazpro_req['date_gmt'], 'date' ),
							$otkazpro_req['number']
						)
					);
					?>
				</span>
			<?php endforeach; ?>
			<?php if ( ! empty( $item['refunded'] ) ) : ?>
				<span>
					<?php
					/* translators: %d: refunded quantity */
					echo esc_html( sprintf( __( 'Already refunded: %d pcs.', 'otkazpro' ), $item['refunded'] ) );
					?>
				</span>
			<?php endif; ?>
			<?php if ( $item['excluded'] && 'done' !== $group ) : ?>
				<span class="otkazpro-item__warn">
					<?php if ( $strict ) : ?>
						<?php
						/* translators: %s: reason */
						echo esc_html( sprintf( __( 'No right of withdrawal: %s.', 'otkazpro' ), $item['excluded']['label'] ) );
						?>
					<?php else : ?>
						<?php
						/* translators: %s: reason */
						echo esc_html( sprintf( __( 'This product is marked as having no right of withdrawal (%s). You can still select it – the store will review your request.', 'otkazpro' ), $item['excluded']['label'] ) );
						?>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( $otkazpro_error ) : ?>
			<p class="otkazpro__error" id="otkazpro_item_error_<?php echo esc_attr( (string) $otkazpro_id ); ?>"><?php echo esc_html( $otkazpro_error ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $otkazpro_can ) : ?>
		<div class="otkazpro-item__qty">
			<?php if ( $item['remaining'] > 1 ) : ?>
				<label for="<?php echo esc_attr( $otkazpro_qty_id ); ?>"><?php esc_html_e( 'Qty', 'otkazpro' ); ?></label>
				<input type="number" id="<?php echo esc_attr( $otkazpro_qty_id ); ?>" name="otkazpro_items[<?php echo esc_attr( (string) $otkazpro_id ); ?>][qty]" value="<?php echo esc_attr( (string) $item['qty'] ); ?>" min="1" max="<?php echo esc_attr( (string) $item['remaining'] ); ?>" step="1" inputmode="numeric" data-otkazpro-qty />
				<span class="otkazpro-item__max">
					<?php
					/* translators: %d: max quantity */
					echo esc_html( sprintf( __( 'of %d', 'otkazpro' ), $item['remaining'] ) );
					?>
				</span>
			<?php else : ?>
				<input type="hidden" name="otkazpro_items[<?php echo esc_attr( (string) $otkazpro_id ); ?>][qty]" value="1" data-otkazpro-qty />
				<span class="otkazpro-item__max"><?php esc_html_e( '1 pc.', 'otkazpro' ); ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</li>
