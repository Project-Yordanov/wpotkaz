<?php
/**
 * Refund tracking: 14-day refund deadline, processed state, store reminders and the
 * "Prepare refund" shortcut that pre-fills the WooCommerce refund form.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Refunds module.
 */
class Refunds {

	const REMINDER_LIMIT = 100;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'on_refund' ), 10, 2 );
		add_action( Install::CRON_HOOK, array( __CLASS__, 'send_reminders' ) );

		if ( is_admin() ) {
			add_action( 'admin_post_otkazpro_processed', array( __CLASS__, 'handle_processed' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'order_screen_script' ) );
		}
	}

	/**
	 * Days the shop has to refund after receiving the statement.
	 *
	 * @return int
	 */
	public static function days() {
		return max( 1, (int) apply_filters( 'py_otkaz_refund_days', 14 ) );
	}

	/**
	 * Days before the refund deadline when the store is reminded.
	 *
	 * @return int
	 */
	public static function reminder_days() {
		return max( 0, (int) apply_filters( 'py_otkaz_refund_reminder_days', 3 ) );
	}

	/**
	 * Statements created before this GMT datetime are past the refund deadline.
	 *
	 * @return string
	 */
	public static function overdue_cutoff() {
		return gmdate( 'Y-m-d H:i:s', time() - self::days() * DAY_IN_SECONDS );
	}

	/**
	 * Open statements past the refund deadline (admin menu bubble).
	 *
	 * @return int
	 */
	public static function count_overdue() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE processed_gmt IS NULL AND created_gmt < %s', Install::table(), self::overdue_cutoff() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * A refund was created in WooCommerce: open statements of the order count as processed.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $refund_id Refund ID.
	 */
	public static function on_refund( $order_id, $refund_id ) {
		$order = Verifier::get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( Submission::for_order( $order_id ) as $s ) {
			if ( $s->processed_gmt ) {
				continue;
			}
			$s->set_processed( 'refund' );
			/* translators: 1: statement number, 2: refund ID */
			$order->add_order_note( sprintf( __( 'Withdrawal %1$s marked as processed (refund #%2$d).', 'otkazpro' ), $s->number, (int) $refund_id ) );
		}
	}

	/**
	 * Daily: one email to the store with open statements whose refund deadline is near or past.
	 * Every statement is included at most once.
	 */
	public static function send_reminders() {
		global $wpdb;
		// Refund due by the end of the day (site timezone) that is reminder_days() from today.
		$until  = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->setTime( 23, 59, 59 )->modify( '+' . self::reminder_days() . ' days' );
		$cutoff = gmdate( 'Y-m-d H:i:s', $until->getTimestamp() - self::days() * DAY_IN_SECONDS );
		$rows   =$wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE processed_gmt IS NULL AND reminder_sent_gmt IS NULL AND created_gmt <= %s ORDER BY created_gmt ASC LIMIT %d', Install::table(), $cutoff, self::REMINDER_LIMIT ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $rows ) {
			return;
		}
		$submissions = array_map( array( Submission::class, 'from_row' ), $rows );

		$emails = WC()->mailer()->get_emails();
		$email  = isset( $emails['PY_Otkaz_Admin_Refund_Reminder'] ) ? $emails['PY_Otkaz_Admin_Refund_Reminder'] : null;
		if ( ! $email || ! $email->is_enabled() || ! $email->get_recipient() ) {
			return;
		}
		if ( ! $email->trigger( $submissions ) ) {
			wc_get_logger()->error( 'Refund reminder could not be sent.', array( 'source' => 'otkazpro' ) );
			return;
		}
		$ids = array_map( 'intval', wp_list_pluck( $submissions, 'id' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET reminder_sent_gmt = %s WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', array_merge( array( Install::table(), current_time( 'mysql', true ) ), $ids ) ) );
		// phpcs:enable
	}

	/**
	 * Upgrade from DB 1.0.0: statements of orders that were already refunded after the
	 * statement count as processed; no reminder for statements already past the deadline.
	 */
	public static function migrate_existing() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE processed_gmt IS NULL', Install::table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $rows as $row ) {
			$s     = Submission::from_row( $row );
			$order = Verifier::get_order( $s->order_id );
			if ( $order && self::refunded_since( $order, strtotime( $s->created_gmt . ' UTC' ) ) ) {
				$s->set_processed( 'refund' );
				continue;
			}
			if ( time() > $s->refund_due() ) {
				$wpdb->update( Install::table(), array( 'reminder_sent_gmt' => current_time( 'mysql', true ) ), array( 'id' => $s->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
	}

	/**
	 * Does the order have a refund created at or after the timestamp?
	 *
	 * @param \WC_Order $order Order.
	 * @param int       $since Timestamp.
	 * @return bool
	 */
	private static function refunded_since( $order, $since ) {
		foreach ( $order->get_refunds() as $refund ) {
			$date = $refund->get_date_created();
			if ( $date && $date->getTimestamp() >= $since ) {
				return true;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------ */

	/**
	 * Refund state badge: "Refunded", "Processed", "N days left" or "Overdue by N days".
	 *
	 * @param Submission $s Submission.
	 * @return string
	 */
	public static function badge( $s ) {
		$state = $s->refund_state();
		if ( 'processed' === $state ) {
			$label = 'refund' === $s->processed_by ? __( 'Refunded', 'otkazpro' ) : __( 'Processed', 'otkazpro' );
			return '<mark class="otkazpro-badge otkazpro-badge--ok" title="' . esc_attr( Util::format_gmt( $s->processed_gmt, 'datetime' ) ) . '">' . esc_html( $label ) . '</mark>';
		}
		$days = $s->refund_days_left();
		$due  = wp_date( get_option( 'date_format' ), $s->refund_due() );
		if ( 'overdue' === $state ) {
			$days = max( 1, -$days );
			/* translators: %d: number of days */
			$label = sprintf( _n( 'Overdue by %d day', 'Overdue by %d days', $days, 'otkazpro' ), $days );
			$class = 'error';
		} else {
			$label = 0 === $days
				? __( 'Refund due today', 'otkazpro' )
				/* translators: %d: number of days */
				: sprintf( _n( '%d day left', '%d days left', $days, 'otkazpro' ), $days );
			$class = 'soon' === $state ? 'warn' : '';
		}
		/* translators: %s: date */
		$title = sprintf( __( 'Refund due by %s', 'otkazpro' ), $due );
		return '<mark class="otkazpro-badge' . ( $class ? ' otkazpro-badge--' . $class : '' ) . '" title="' . esc_attr( $title ) . '">' . esc_html( $label ) . '</mark>';
	}

	/**
	 * "Mark as processed" / "Reopen" button.
	 *
	 * @param Submission $s Submission.
	 * @return string
	 */
	public static function processed_button( $s ) {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'otkazpro_processed',
					'id'     => $s->id,
					'state'  => $s->processed_gmt ? 0 : 1,
				),
				admin_url( 'admin-post.php' )
			),
			'py_otkaz_processed_' . $s->id
		);
		$label = $s->processed_gmt ? __( 'Reopen', 'otkazpro' ) : __( 'Mark as processed', 'otkazpro' );
		return '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * "Prepare refund" button: opens the order with the WooCommerce refund form pre-filled
	 * with the products and quantities of the statement. The refund itself stays manual.
	 *
	 * @param Submission     $s     Submission.
	 * @param \WC_Order|null $order Order.
	 * @return string '' when the order cannot be refunded.
	 */
	public static function prepare_button( $s, $order = null ) {
		$order = $order ? $order : Verifier::get_order( $s->order_id );
		if ( ! $order || $order->get_remaining_refund_amount() <= 0 ) {
			return '';
		}
		$items = array();
		foreach ( $s->items as $item ) {
			if ( ! empty( $item['item_id'] ) && $order->get_item( (int) $item['item_id'] ) ) {
				$items[ (int) $item['item_id'] ] = (int) $item['qty'];
			}
		}
		if ( ! $items ) {
			return '';
		}
		$url = add_query_arg( 'otkazpro_refund', $s->id, $order->get_edit_order_url() ) . '#woocommerce-order-items';
		return sprintf(
			'<a class="button button-small otkazpro-prepare-refund" href="%1$s" data-otkazpro-refund="%2$d" data-items="%3$s" data-reason="%4$s">%5$s</a>',
			esc_url( $url ),
			(int) $s->id,
			esc_attr( (string) wp_json_encode( (object) $items ) ),
			/* translators: %s: statement number */
			esc_attr( sprintf( __( 'Withdrawal %s', 'otkazpro' ), $s->number ) ),
			esc_html__( 'Prepare refund', 'otkazpro' )
		);
	}

	/**
	 * Handle "Mark as processed" / "Reopen".
	 */
	public static function handle_processed() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'py_otkaz_processed_' . $id );
		if ( ! current_user_can( Admin::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'otkazpro' ) );
		}
		$s = Submission::get( $id );
		if ( $s ) {
			$done = ! empty( $_GET['state'] );
			$s->set_processed( $done ? 'manual' : '' );
			$order = Verifier::get_order( $s->order_id );
			if ( $order ) {
				$order->add_order_note(
					$done
						/* translators: %s: statement number */
						? sprintf( __( 'Withdrawal %s marked as processed.', 'otkazpro' ), $s->number )
						/* translators: %s: statement number */
						: sprintf( __( 'Withdrawal %s reopened.', 'otkazpro' ), $s->number ),
					false,
					true
				);
			}
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : Admin::url() );
		exit;
	}

	/**
	 * Script that pre-fills the refund form, on the order edit screen only.
	 */
	public static function order_screen_script() {
		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		if ( ! $screen || ! in_array( $screen->id, $screens, true ) ) {
			return;
		}
		wp_enqueue_script( 'otkazpro-admin-refund', PY_OTKAZ_URL . 'assets/js/admin-refund.js', array( 'jquery', 'wc-admin-order-meta-boxes' ), PY_OTKAZ_VERSION, true );
	}
}
