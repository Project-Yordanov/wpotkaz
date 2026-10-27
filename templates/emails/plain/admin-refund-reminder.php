<?php
/**
 * Store reminder – refund deadline (plain text).
 *
 * Override: yourtheme/woocommerce/otkazpro/emails/plain/admin-refund-reminder.php
 *
 * @package PY\Otkaz
 *
 * @var string                 $email_heading      Heading.
 * @var string                 $additional_content Additional content.
 * @var \PY\Otkaz\Submission[] $submissions        Statements.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template: variables are local to the including function (wc_get_template).

echo "=================================================================\n";
echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n";
echo "=================================================================\n\n";

echo esc_html__( 'These withdrawals are not marked as processed yet and their 14-day refund deadline is near or has passed:', 'otkazpro' ) . "\n\n";

foreach ( $submissions as $submission ) {
	echo '* ' . esc_html( $submission->number . ' – #' . $submission->order_number . ' – ' . $submission->name ) . "\n";
	echo '  ' . esc_html( $submission->items_summary() ) . "\n";
	echo '  ' . esc_html__( 'Refund due by', 'otkazpro' ) . ': ' . esc_html( wp_date( get_option( 'date_format' ), $submission->refund_due() ) );
	if ( 'overdue' === $submission->refund_state() ) {
		echo ' (' . esc_html__( 'Overdue', 'otkazpro' ) . ')';
	}
	echo "\n  " . esc_url( \PY\Otkaz\Admin::url( array( 'view' => $submission->id ) ) ) . "\n\n";
}

if ( $additional_content ) {
	echo "----------------------------------------\n\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}

echo "\n=================================================================\n\n";
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party / core hook.
