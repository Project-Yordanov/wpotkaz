<?php
/**
 * Customer withdrawal confirmation (HTML).
 *
 * Override: yourtheme/woocommerce/otkazpro/emails/customer-otkaz-confirmation.php
 *
 * @package PY\Otkaz
 *
 * @var string               $email_heading       Heading.
 * @var string               $additional_content  Additional content.
 * @var \PY\Otkaz\Submission $submission          Submission.
 * @var \WC_Order            $order               Order.
 * @var bool                 $sent_to_admin       False.
 * @var bool                 $plain_text          False.
 * @var \WC_Email            $email               Email.
 * @var string               $return_instructions Return instructions.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook. ?>

<p>
	<?php
	/* translators: %s: customer name */
	echo esc_html( sprintf( __( 'Hello %s,', 'otkazpro' ), $submission->name ) );
	?>
</p>
<p>
	<?php
	/* translators: 1: date, 2: time */
	echo esc_html( sprintf( __( 'We confirm that we received your statement of withdrawal from the contract on %1$s at %2$s. Its content is below.', 'otkazpro' ), $submission->date(), $submission->time() ) );
	?>
</p>

<?php
wc_get_template(
	'emails/parts/otkaz-details.php',
	array(
		'submission'    => $submission,
		'order'         => $order,
		'sent_to_admin' => false,
	),
	'woocommerce/otkazpro/',
	PY_OTKAZ_DIR . 'templates/'
);
?>

<?php if ( $return_instructions ) : ?>
	<h2><?php esc_html_e( 'How to return the goods', 'otkazpro' ); ?></h2>
	<?php echo wp_kses_post( wpautop( wptexturize( $return_instructions ) ) ); ?>
<?php endif; ?>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
