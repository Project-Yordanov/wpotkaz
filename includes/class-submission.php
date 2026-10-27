<?php
/**
 * Withdrawal statement – entity, persistence and creation.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * A submitted withdrawal statement.
 */
class Submission {

	/** @var int */
	public $id = 0;
	/** @var string */
	public $number = '';
	/** @var int */
	public $order_id = 0;
	/** @var string */
	public $order_number = '';
	/** @var int */
	public $customer_id = 0;
	/** @var string */
	public $name = '';
	/** @var string */
	public $email = '';
	/** @var array[] */
	public $items = array();
	/** @var string */
	public $statement = '';
	/** @var string */
	public $reason = '';
	/** @var bool */
	public $has_excluded = false;
	/** @var bool|null */
	public $within_deadline = null;
	/** @var string|null */
	public $deadline_gmt = null;
	/** @var string */
	public $source = '';
	/** @var string */
	public $language = '';
	/** @var string */
	public $ip_address = '';
	/** @var string */
	public $user_agent = '';
	/** @var string pending|sent|failed */
	public $email_status = 'pending';
	/** @var string|null */
	public $email_sent_gmt = null;
	/** @var int */
	public $email_attempts = 0;
	/** @var string */
	public $admin_email_status = 'pending';
	/** @var string|null */
	public $processed_gmt = null;
	/** @var string refund|manual */
	public $processed_by = '';
	/** @var string|null */
	public $reminder_sent_gmt = null;
	/** @var string */
	public $created_local = '';
	/** @var string */
	public $created_gmt = '';

	/**
	 * Build from a DB row.
	 *
	 * @param object|array $row Row.
	 * @return Submission
	 */
	public static function from_row( $row ) {
		$row = (array) $row;
		$s   = new self();
		foreach ( $row as $key => $value ) {
			if ( property_exists( $s, $key ) ) {
				$s->$key = $value;
			}
		}
		$s->id              = (int) $s->id;
		$s->order_id        = (int) $s->order_id;
		$s->customer_id     = (int) $s->customer_id;
		$s->email_attempts  = (int) $s->email_attempts;
		$s->has_excluded    = (bool) (int) $s->has_excluded;
		$s->within_deadline = null === $row['within_deadline'] ? null : (bool) (int) $row['within_deadline'];
		$items              = is_string( $s->items ) ? json_decode( $s->items, true ) : $s->items;
		$s->items           = is_array( $items ) ? $items : array();
		$s->reason          = (string) $s->reason;
		return $s;
	}

	/* ---------------------------------------------------------------------
	 * Queries
	 * ------------------------------------------------------------------ */

	/**
	 * Get by ID.
	 *
	 * @param int $id ID.
	 * @return Submission|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Install::table();
		$row   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ? self::from_row( $row ) : null;
	}

	/**
	 * All submissions for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return Submission[]
	 */
	public static function for_order( $order_id ) {
		$result = self::query(
			array(
				'order_id' => $order_id,
				'per_page' => 100,
				'orderby'  => 'created_gmt',
				'order'    => 'ASC',
			)
		);
		return $result['items'];
	}

	/**
	 * Query submissions.
	 *
	 * @param array $args search, order_id, email, deadline (in|late|open), email_status, refund (open|overdue|processed), date_from, date_to (Y-m-d), per_page, page, orderby, order.
	 * @return array ['items' => Submission[], 'total' => int]
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = Install::table();
		$args  = wp_parse_args(
			$args,
			array(
				'search'       => '',
				'order_id'     => 0,
				'email'        => '',
				'deadline'     => '',
				'email_status' => '',
				'refund'       => '',
				'date_from'    => '',
				'date_to'      => '',
				'per_page'     => 20,
				'page'         => 1,
				'orderby'      => 'created_gmt',
				'order'        => 'DESC',
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['order_id'] ) {
			$where[]  = 'order_id = %d';
			$params[] = (int) $args['order_id'];
		}
		if ( $args['email'] ) {
			$where[]  = 'email = %s';
			$params[] = $args['email'];
		}
		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(number LIKE %s OR order_number LIKE %s OR name LIKE %s OR email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( 'in' === $args['deadline'] ) {
			$where[] = 'within_deadline = 1';
		} elseif ( 'late' === $args['deadline'] ) {
			$where[] = 'within_deadline = 0';
		} elseif ( 'open' === $args['deadline'] ) {
			$where[] = 'within_deadline IS NULL';
		}
		if ( in_array( $args['email_status'], array( 'sent', 'failed', 'pending' ), true ) ) {
			$where[]  = 'email_status = %s';
			$params[] = $args['email_status'];
		}
		if ( 'processed' === $args['refund'] ) {
			$where[] = 'processed_gmt IS NOT NULL';
		} elseif ( 'open' === $args['refund'] ) {
			$where[] = 'processed_gmt IS NULL';
		} elseif ( 'overdue' === $args['refund'] ) {
			$where[]  = 'processed_gmt IS NULL AND created_gmt < %s';
			$params[] = Refunds::overdue_cutoff();
		}
		if ( $args['date_from'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_from'] ) ) {
			$where[]  = 'created_local >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( $args['date_to'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_to'] ) ) {
			$where[]  = 'created_local <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}

		$orderby = in_array( $args['orderby'], array( 'created_gmt', 'number', 'order_id', 'name', 'email' ), true ) ? $args['orderby'] : 'created_gmt';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$sql_where = implode( ' AND ', $where );

		$per_page = (int) $args['per_page'];
		$offset   = max( 0, ( (int) $args['page'] - 1 ) * max( 1, $per_page ) );
		$base     = array_merge( array( $table ), $params );

		// $sql_where contains only placeholders; $orderby / $order are whitelisted above.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$sql_where}", $base ) );
		if ( $per_page > 0 ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$sql_where} ORDER BY %i {$order}, id {$order} LIMIT %d OFFSET %d", array_merge( $base, array( $orderby, $per_page, $offset ) ) ) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE {$sql_where} ORDER BY %i {$order}, id {$order}", array_merge( $base, array( $orderby ) ) ) );
		}
		// phpcs:enable

		return array(
			'items' => array_map( array( __CLASS__, 'from_row' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Count failed confirmation emails (for the admin menu bubble).
	 *
	 * @return int
	 */
	public static function count_failed() {
		global $wpdb;
		$table = Install::table();
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE email_status = %s', $table, 'failed' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Mark as processed (refund made / handled) or reopen.
	 *
	 * @param string $by refund|manual, or '' to reopen.
	 */
	public function set_processed( $by ) {
		global $wpdb;
		$this->processed_gmt = $by ? current_time( 'mysql', true ) : null;
		$this->processed_by  = (string) $by;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Install::table(),
			array(
				'processed_gmt' => $this->processed_gmt,
				'processed_by'  => $this->processed_by,
			),
			array( 'id' => $this->id )
		);
	}

	/**
	 * Update email status columns.
	 *
	 * @param string $customer_status Customer email status or '' to keep.
	 * @param string $admin_status    Admin email status or '' to keep.
	 */
	public function update_email_status( $customer_status, $admin_status = '' ) {
		global $wpdb;
		$data = array();
		if ( $customer_status ) {
			$this->email_status = $customer_status;
			++$this->email_attempts;
			$data['email_status']   = $customer_status;
			$data['email_attempts'] = $this->email_attempts;
			if ( 'sent' === $customer_status ) {
				$this->email_sent_gmt   = current_time( 'mysql', true );
				$data['email_sent_gmt'] = $this->email_sent_gmt;
			}
		}
		if ( $admin_status ) {
			$this->admin_email_status   = $admin_status;
			$data['admin_email_status'] = $admin_status;
		}
		if ( $data ) {
			$wpdb->update( Install::table(), $data, array( 'id' => $this->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/* ---------------------------------------------------------------------
	 * Presentation helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Local date of submission.
	 *
	 * @return string
	 */
	public function date() {
		return Util::format_gmt( $this->created_gmt, 'date' );
	}

	/**
	 * Local time of submission.
	 *
	 * @return string
	 */
	public function time() {
		return Util::format_gmt( $this->created_gmt, 'time' );
	}

	/**
	 * "Product A × 2, Product B × 1".
	 *
	 * @param string $sep Separator.
	 * @return string
	 */
	public function items_summary( $sep = ', ' ) {
		$parts = array();
		foreach ( $this->items as $item ) {
			/* translators: 1: product name, 2: quantity */
			$parts[] = sprintf( __( '%1$s × %2$d', 'otkazpro' ), $item['name'], (int) $item['qty'] );
		}
		return implode( $sep, $parts );
	}

	/**
	 * Total quantity.
	 *
	 * @return int
	 */
	public function total_qty() {
		return (int) array_sum( wp_list_pluck( $this->items, 'qty' ) );
	}

	/**
	 * Refund deadline (GMT timestamp): the shop must refund within 14 days of receiving the statement.
	 *
	 * @return int
	 */
	public function refund_due() {
		return (int) strtotime( $this->created_gmt . ' UTC' ) + Refunds::days() * DAY_IN_SECONDS;
	}

	/**
	 * Whole calendar days (site timezone) until the refund deadline; negative when overdue.
	 *
	 * @param int|null $now Timestamp.
	 * @return int
	 */
	public function refund_days_left( $now = null ) {
		$tz    = wp_timezone();
		$due   = ( new \DateTimeImmutable( '@' . $this->refund_due() ) )->setTimezone( $tz )->setTime( 0, 0 );
		$today = ( new \DateTimeImmutable( '@' . ( null === $now ? time() : (int) $now ) ) )->setTimezone( $tz )->setTime( 0, 0 );
		return (int) round( ( $due->getTimestamp() - $today->getTimestamp() ) / DAY_IN_SECONDS );
	}

	/**
	 * Refund state: processed, overdue, soon (reminder window) or open.
	 *
	 * @return string
	 */
	public function refund_state() {
		if ( $this->processed_gmt ) {
			return 'processed';
		}
		if ( time() > $this->refund_due() ) {
			return 'overdue';
		}
		return $this->refund_days_left() <= Refunds::reminder_days() ? 'soon' : 'open';
	}

	/**
	 * Render the statement template.
	 *
	 * @param string    $template Template.
	 * @param array     $vars     name, email, items (array), order (WC_Order), created_gmt.
	 * @return string
	 */
	public static function render_statement( $template, $vars ) {
		$items = array();
		foreach ( $vars['items'] as $item ) {
			/* translators: 1: product name, 2: quantity */
			$items[] = sprintf( __( '%1$s (%2$d pcs.)', 'otkazpro' ), $item['name'], (int) $item['qty'] );
		}
		/** @var \WC_Order $order */
		$order        = $vars['order'];
		$order_date   = $order->get_date_created();
		$replacements = array(
			'{items}'        => implode( '; ', $items ),
			'{order_number}' => $order->get_order_number(),
			'{order_date}'   => $order_date ? wc_format_datetime( $order_date ) : '',
			'{date}'         => Util::format_gmt( $vars['created_gmt'], 'date' ),
			'{time}'         => Util::format_gmt( $vars['created_gmt'], 'time' ),
			'{name}'         => $vars['name'],
			'{email}'        => $vars['email'],
			'{site_title}'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		return trim( strtr( wp_strip_all_tags( $template ), $replacements ) );
	}

	/* ---------------------------------------------------------------------
	 * Creation
	 * ------------------------------------------------------------------ */

	/**
	 * Validate and create a submission. Sends the emails.
	 *
	 * @param \WC_Order $order Order (access already verified).
	 * @param array     $data  name, email, items [item_id => qty], reason, source.
	 * @return Submission|\WP_Error WP_Error data may contain 'fields' => [field => message].
	 */
	public static function create( $order, $data ) {
		global $wpdb;

		$lock = 'py_otkaz_' . $order->get_id();
		$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		try {
			$result = self::create_locked( $order->get_id(), $data );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		list( $submission, $order ) = $result;

		Emails::send_for_submission( $submission, $order );

		/**
		 * Fires after a withdrawal statement is stored and emails are sent.
		 *
		 * @param Submission $submission Submission.
		 * @param \WC_Order  $order      Order.
		 */
		do_action( 'py_otkaz_after_submission', $submission, $order );

		return $submission;
	}

	/**
	 * Body of create() running under the per-order lock.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Data.
	 * @return array|\WP_Error [Submission, WC_Order]
	 */
	private static function create_locked( $order_id, $data ) {
		global $wpdb;

		// Fresh copy – history may have changed while waiting for the lock.
		if ( class_exists( '\WC_Cache_Helper' ) ) {
			wp_cache_delete( $order_id, 'orders' );
		}
		$order = Verifier::get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'py_otkaz_no_order', __( 'The order could not be found.', 'otkazpro' ) );
		}
		if ( ! Order_Data::is_eligible_status( $order ) ) {
			return new \WP_Error( 'py_otkaz_status', __( 'A withdrawal cannot be submitted online for this order in its current status. Please contact us.', 'otkazpro' ) );
		}

		$available = Order_Data::get_items( $order );
		$fields    = array();
		$selected  = array();

		foreach ( (array) $data['items'] as $item_id => $qty ) {
			$item_id = absint( $item_id );
			$qty     = (int) $qty;
			$field   = 'otkazpro_item_' . $item_id;

			if ( ! isset( $available[ $item_id ] ) ) {
				$fields['otkazpro_items'] = __( 'One of the selected products is not part of this order.', 'otkazpro' );
				continue;
			}
			$item = $available[ $item_id ];

			if ( ! $item['selectable'] ) {
				$fields[ $field ] = $item['excluded'] && $item['remaining'] > 0
					/* translators: %s: product name */
					? sprintf( __( '“%s” is not eligible for withdrawal.', 'otkazpro' ), $item['name'] )
					/* translators: %s: product name */
					: sprintf( __( 'A withdrawal for “%s” has already been submitted.', 'otkazpro' ), $item['name'] );
				continue;
			}
			if ( $qty < 1 || $qty > $item['remaining'] ) {
				$fields[ $field ] = sprintf(
					/* translators: 1: product name, 2: maximum quantity */
					__( 'Enter a quantity between 1 and %2$d for “%1$s”.', 'otkazpro' ),
					$item['name'],
					$item['remaining']
				);
				continue;
			}

			$selected[ $item_id ] = array(
				'item_id'         => $item_id,
				'product_id'      => $item['product_id'],
				'variation_id'    => $item['variation_id'],
				'name'            => $item['name'],
				'sku'             => $item['sku'],
				'qty'             => $qty,
				'ordered_qty'     => $item['ordered'],
				'excluded'        => (bool) $item['excluded'],
				'excluded_reason' => $item['excluded'] ? $item['excluded']['label'] : '',
			);
		}

		if ( ! $selected && ! $fields ) {
			$fields['otkazpro_items'] = __( 'Select at least one product.', 'otkazpro' );
		}
		if ( $fields ) {
			$error = new \WP_Error( 'py_otkaz_validation', __( 'Please correct the highlighted fields.', 'otkazpro' ) );
			$error->add_data( array( 'fields' => $fields ) );
			return $error;
		}

		$created_gmt   = current_time( 'mysql', true );
		$created_local = wp_date( 'Y-m-d H:i:s', strtotime( $created_gmt . ' UTC' ) );
		$deadline      = Order_Data::deadline( $order );
		$within        = Order_Data::is_within_deadline( $order );
		$items         = array_values( $selected );

		$statement = self::render_statement(
			Settings::text( 'text_statement' ),
			array(
				'name'        => $data['name'],
				'email'       => $data['email'],
				'items'       => $items,
				'order'       => $order,
				'created_gmt' => $created_gmt,
			)
		);

		$row = array(
			'order_id'           => $order->get_id(),
			'order_number'       => (string) $order->get_order_number(),
			'customer_id'        => (int) $order->get_customer_id(),
			'name'               => $data['name'],
			'email'              => $data['email'],
			'items'              => wp_json_encode( $items ),
			'statement'          => $statement,
			'reason'             => isset( $data['reason'] ) ? $data['reason'] : '',
			'has_excluded'       => (int) (bool) array_filter( wp_list_pluck( $items, 'excluded' ) ),
			'within_deadline'    => null === $within ? null : (int) $within,
			'deadline_gmt'       => $deadline ? gmdate( 'Y-m-d H:i:s', $deadline->getTimestamp() ) : null,
			'source'             => isset( $data['source'] ) ? $data['source'] : '',
			'language'           => I18n::current_language(),
			'ip_address'         => Util::client_ip(),
			'user_agent'         => Util::user_agent(),
			'email_status'       => 'pending',
			'admin_email_status' => 'pending',
			'created_local'      => $created_local,
			'created_gmt'        => $created_gmt,
		);

		$existing = count( Order_Data::history( $order ) );
		$inserted = false;
		for ( $n = $existing + 1; $n <= $existing + 20; $n++ ) {
			$number        = (string) apply_filters( 'py_otkaz_submission_number', 'OTK-' . $order->get_id() . '-' . $n, $order, $n );
			$row['number'] = $number;
			$suppress      = $wpdb->suppress_errors( true );
			$inserted      = $wpdb->insert( Install::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->suppress_errors( $suppress );
			if ( $inserted ) {
				break;
			}
		}
		if ( ! $inserted ) {
			wc_get_logger()->error( 'Could not store withdrawal: ' . $wpdb->last_error, array( 'source' => 'otkazpro' ) );
			return new \WP_Error( 'py_otkaz_db', __( 'Your withdrawal could not be saved due to a technical error. Please try again or contact us by email.', 'otkazpro' ) );
		}

		$row['id']  = (int) $wpdb->insert_id;
		$submission = self::from_row( $row );

		Order_Data::add_history(
			$order,
			array(
				'number'   => $submission->number,
				'id'       => $submission->id,
				'date_gmt' => $created_gmt,
				'items'    => wp_list_pluck( $items, 'qty', 'item_id' ),
			)
		);

		$note = sprintf(
			/* translators: 1: statement number, 2: items */
			__( 'Withdrawal %1$s submitted for: %2$s.', 'otkazpro' ),
			$submission->number,
			$submission->items_summary()
		);
		if ( $submission->has_excluded ) {
			$note .= ' ' . __( 'Includes products marked as excluded from withdrawal.', 'otkazpro' );
		}
		if ( false === $within ) {
			$note .= ' ' . __( 'Submitted after the indicative deadline.', 'otkazpro' );
		}

		if ( Settings::enabled( 'custom_status' ) && Order_Status::STATUS !== $order->get_status() ) {
			$order->set_status( Order_Status::STATUS, $note );
		} else {
			$order->add_order_note( $note );
		}
		$order->save();

		return array( $submission, $order );
	}

	/**
	 * Delete records older than the retention period.
	 */
	public static function cleanup() {
		global $wpdb;
		$years = (int) Settings::get( 'retention_years' );
		if ( $years <= 0 ) {
			return;
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $years . ' years' ) );
		$table  = Install::table();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_gmt < %s LIMIT 1000', $table, $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
