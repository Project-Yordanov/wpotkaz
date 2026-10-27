<?php
/**
 * Withdrawals list table.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for WooCommerce → Withdrawals.
 */
class Admin_List_Table extends \WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'py_otkaz',
				'plural'   => 'py_otkazi',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Filters from the request (shared with CSV export).
	 *
	 * @return array
	 */
	public static function filters_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$get = static function ( $key ) {
			return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
		};
		$args = array(
			'search'       => $get( 's' ),
			'deadline'     => $get( 'deadline' ),
			'email_status' => $get( 'email_status' ),
			'refund'       => $get( 'refund' ),
			'date_from'    => $get( 'date_from' ),
			'date_to'      => $get( 'date_to' ),
			'orderby'      => $get( 'orderby' ) ? $get( 'orderby' ) : 'created_gmt',
			'order'        => $get( 'order' ) ? $get( 'order' ) : 'DESC',
		);
		// phpcs:enable
		return $args;
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'number'   => __( 'Number', 'otkazpro' ),
			'date'     => __( 'Date', 'otkazpro' ),
			'order'    => __( 'Order', 'otkazpro' ),
			'customer' => __( 'Customer', 'otkazpro' ),
			'items'    => __( 'Products', 'otkazpro' ),
			'deadline' => __( 'Deadline', 'otkazpro' ),
			'refund'   => __( 'Refund', 'otkazpro' ),
			'email'    => __( 'Confirmation', 'otkazpro' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'number'   => array( 'number', false ),
			'date'     => array( 'created_gmt', true ),
			'order'    => array( 'order_id', false ),
			'customer' => array( 'name', false ),
		);
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'py_otkaz_per_page', 20 );
		$args     = self::filters_from_request();

		$args['per_page'] = $per_page;
		$args['page']     = $this->get_pagenum();

		$result      = Submission::query( $args );
		$this->items = $result['items'];

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'number' );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / max( 1, $per_page ) ),
			)
		);
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No withdrawals yet.', 'otkazpro' );
	}

	/**
	 * Filters + export.
	 *
	 * @param string $which top|bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$f = self::filters_from_request();
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="otkazpro-deadline"><?php esc_html_e( 'Deadline', 'otkazpro' ); ?></label>
			<select name="deadline" id="otkazpro-deadline">
				<option value=""><?php esc_html_e( 'Any deadline', 'otkazpro' ); ?></option>
				<option value="in" <?php selected( $f['deadline'], 'in' ); ?>><?php esc_html_e( 'In time', 'otkazpro' ); ?></option>
				<option value="late" <?php selected( $f['deadline'], 'late' ); ?>><?php esc_html_e( 'After the deadline', 'otkazpro' ); ?></option>
				<option value="open" <?php selected( $f['deadline'], 'open' ); ?>><?php esc_html_e( 'Not started', 'otkazpro' ); ?></option>
			</select>
			<label class="screen-reader-text" for="otkazpro-email-status"><?php esc_html_e( 'Confirmation', 'otkazpro' ); ?></label>
			<select name="email_status" id="otkazpro-email-status">
				<option value=""><?php esc_html_e( 'Any email status', 'otkazpro' ); ?></option>
				<option value="sent" <?php selected( $f['email_status'], 'sent' ); ?>><?php esc_html_e( 'Sent', 'otkazpro' ); ?></option>
				<option value="failed" <?php selected( $f['email_status'], 'failed' ); ?>><?php esc_html_e( 'Email not sent', 'otkazpro' ); ?></option>
			</select>
			<label class="screen-reader-text" for="otkazpro-refund"><?php esc_html_e( 'Refund', 'otkazpro' ); ?></label>
			<select name="refund" id="otkazpro-refund">
				<option value=""><?php esc_html_e( 'Any refund status', 'otkazpro' ); ?></option>
				<option value="open" <?php selected( $f['refund'], 'open' ); ?>><?php esc_html_e( 'Not processed', 'otkazpro' ); ?></option>
				<option value="overdue" <?php selected( $f['refund'], 'overdue' ); ?>><?php esc_html_e( 'Overdue', 'otkazpro' ); ?></option>
				<option value="processed" <?php selected( $f['refund'], 'processed' ); ?>><?php esc_html_e( 'Processed', 'otkazpro' ); ?></option>
			</select>
			<label for="otkazpro-from"><?php esc_html_e( 'From', 'otkazpro' ); ?></label>
			<input type="date" id="otkazpro-from" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" />
			<label for="otkazpro-to"><?php esc_html_e( 'To', 'otkazpro' ); ?></label>
			<input type="date" id="otkazpro-to" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" />
			<?php submit_button( __( 'Filter', 'otkazpro' ), '', 'filter_action', false ); ?>
			<?php
			$export = wp_nonce_url(
				add_query_arg(
					array_merge(
						array( 'action' => 'otkazpro_export' ),
						array_filter(
							array(
								's'            => $f['search'],
								'deadline'     => $f['deadline'],
								'email_status' => $f['email_status'],
								'refund'       => $f['refund'],
								'date_from'    => $f['date_from'],
								'date_to'      => $f['date_to'],
							)
						)
					),
					admin_url( 'admin-post.php' )
				),
				'otkazpro_export'
			);
			?>
			<a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export CSV', 'otkazpro' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Number column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_number( $s ) {
		$out = '<strong><a href="' . esc_url( Admin::url( array( 'view' => $s->id ) ) ) . '">' . esc_html( $s->number ) . '</a></strong>';
		if ( $s->has_excluded ) {
			$out .= '<br /><mark class="otkazpro-badge otkazpro-badge--warn">' . esc_html__( 'Excluded product', 'otkazpro' ) . '</mark>';
		}
		return $out;
	}

	/**
	 * Date column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_date( $s ) {
		return esc_html( Util::format_gmt( $s->created_gmt, 'datetime' ) );
	}

	/**
	 * Order column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_order( $s ) {
		$order = Verifier::get_order( $s->order_id );
		if ( ! $order ) {
			return esc_html( '#' . $s->order_number );
		}
		return '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $s->order_number ) . '</a><br /><span class="description">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span>';
	}

	/**
	 * Customer column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_customer( $s ) {
		return esc_html( $s->name ) . '<br /><a href="mailto:' . esc_attr( $s->email ) . '">' . esc_html( $s->email ) . '</a>';
	}

	/**
	 * Items column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_items( $s ) {
		return Admin::items_html( $s );
	}

	/**
	 * Deadline column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_deadline( $s ) {
		return Admin::deadline_badge( $s );
	}

	/**
	 * Refund column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_refund( $s ) {
		return Refunds::badge( $s ) . '<br />' . ( $s->processed_gmt ? '' : Refunds::prepare_button( $s ) . ' ' ) . Refunds::processed_button( $s );
	}

	/**
	 * Email column.
	 *
	 * @param Submission $s Item.
	 * @return string
	 */
	protected function column_email( $s ) {
		$out = Admin::email_badge( $s );
		if ( 'sent' !== $s->email_status ) {
			$out .= '<br />' . Admin::resend_button( $s );
		}
		return $out;
	}
}
