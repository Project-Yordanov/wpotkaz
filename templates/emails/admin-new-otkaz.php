<?php
/**
 * Store notification – new withdrawal (HTML).
 *
 * Override: yourtheme/woocommerce/otkazpro/emails/admin-new-otkaz.php
 *
 * @package PY\Otkaz
 *
 * @var string               $email_heading      Heading.
 * @var string               $additional_content Additional content.
 * @var \PY\Otkaz\Submission $submission         Submission.
 * @var \WC_Order            $order              Order.
 * @var \WC_Email            $email              Email.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook. ?>

<p>
	<?php
	/* translators: 1: customer name, 2: order number */
	echo esc_html( sprintf( __( '%1$s submitted a withdrawal from the contract for order #%2$s.', 'otkazpro' ), $submission->name, $submission->order_number ) );
	?>
</p>

<?php if ( $submission->has_excluded ) : ?>
	<p style="padding:10px 14px;border:1px solid #f0c36d;background:#fff8e5;">
		<?php esc_html_e( 'The statement includes products marked as having no right of withdrawal. Review the request and reply to the customer.', 'otkazpro' ); ?>
	</p>
<?php endif; ?>

<?php
wc_get_template(
	'emails/parts/otkaz-details.php',
	array(
		'submission'    => $submission,
		'order'         => $order,
		'sent_to_admin' => true,
	),
	'woocommerce/otkazpro/',
	PY_OTKAZ_DIR . 'templates/'
);
?>

<p>
	<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php esc_html_e( 'Open the order', 'otkazpro' ); ?></a>
	&nbsp;·&nbsp;
	<a href="<?php echo esc_url( \PY\Otkaz\Admin::url( array( 'view' => $submission->id ) ) ); ?>"><?php esc_html_e( 'View the statement', 'otkazpro' ); ?></a>
</p>

<?php if ( 'sent' !== $submission->email_status ) : ?>
	<p style="color:#b32d2e;"><strong><?php esc_html_e( 'The confirmation email to the customer could not be sent. Use “Resend” in WooCommerce → Withdrawals.', 'otkazpro' ); ?></strong></p>
<?php endif; ?>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
