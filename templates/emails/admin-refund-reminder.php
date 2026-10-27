<?php
/**
 * Store reminder – refund deadline (HTML).
 *
 * Override: yourtheme/woocommerce/otkazpro/emails/admin-refund-reminder.php
 *
 * @package PY\Otkaz
 *
 * @var string                 $email_heading      Heading.
 * @var string                 $additional_content Additional content.
 * @var \PY\Otkaz\Submission[] $submissions        Statements.
 * @var \WC_Email              $email              Email.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook. ?>

<p><?php esc_html_e( 'These withdrawals are not marked as processed yet and their 14-day refund deadline is near or has passed:', 'otkazpro' ); ?></p>

<table class="td" cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;margin:0 0 24px;">
	<thead>
		<tr>
			<th class="td" scope="col" style="text-align:left;"><?php esc_html_e( 'Withdrawal', 'otkazpro' ); ?></th>
			<th class="td" scope="col" style="text-align:left;"><?php esc_html_e( 'Customer', 'otkazpro' ); ?></th>
			<th class="td" scope="col" style="text-align:left;"><?php esc_html_e( 'Refund due by', 'otkazpro' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $submissions as $submission ) : ?>
			<?php $order = \PY\Otkaz\Verifier::get_order( $submission->order_id ); ?>
			<tr>
				<td class="td" style="text-align:left;">
					<a href="<?php echo esc_url( \PY\Otkaz\Admin::url( array( 'view' => $submission->id ) ) ); ?>"><?php echo esc_html( $submission->number ); ?></a><br />
					<?php if ( $order ) : ?>
						<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php echo esc_html( '#' . $submission->order_number ); ?></a>
					<?php else : ?>
						<?php echo esc_html( '#' . $submission->order_number ); ?>
					<?php endif; ?>
				</td>
				<td class="td" style="text-align:left;"><?php echo esc_html( $submission->name ); ?><br /><?php echo esc_html( $submission->items_summary() ); ?></td>
				<td class="td" style="text-align:left;">
					<?php echo esc_html( wp_date( get_option( 'date_format' ), $submission->refund_due() ) ); ?>
					<?php if ( 'overdue' === $submission->refund_state() ) : ?>
						<br /><strong style="color:#b32d2e;"><?php esc_html_e( 'Overdue', 'otkazpro' ); ?></strong>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<p><a href="<?php echo esc_url( \PY\Otkaz\Admin::url( array( 'refund' => 'open' ) ) ); ?>"><?php esc_html_e( 'Open withdrawals in the register', 'otkazpro' ); ?></a></p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
