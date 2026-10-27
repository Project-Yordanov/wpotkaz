<?php
/**
 * Activation, database schema and upgrades.
 *
 * @package PY\Otkaz
 */

namespace PY\Otkaz;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class Install {

	const DB_VERSION        = '1.1.0';
	const DB_VERSION_OPTION = 'py_otkaz_db_version';
	const CRON_HOOK         = 'py_otkaz_daily_cleanup';
	const PAGE_SLUG         = 'otkaz-ot-dogovor';
	const CONFLICT_OPTION   = 'py_otkaz_page_conflict';
	const BACKUP_OPTION     = 'otkazpro_settings_backup'; // Outside the py_otkaz_ prefix on purpose.

	/**
	 * Earlier editions of the plugin (same data, different folder / name).
	 */
	const OLD_EDITIONS = array( 'wpotkaz/wpotkaz.php', 'wpotkaz-main/wpotkaz.php', 'otkazpro-main/otkazpro.php', 'py-otkaz/py-otkaz.php' );

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'restore_settings_if_wiped' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_take_over' ), 2 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'backup_settings' ) );
	}

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::take_over_old_editions();
		self::restore_settings_if_wiped();
		self::upgrade_schema();
		self::maybe_create_page();
		self::ensure_cron();
		self::backup_settings();
		update_option( 'py_otkaz_version', PY_OTKAZ_VERSION, false );
		set_transient( 'py_otkaz_activated', 1, MINUTE_IN_SECONDS * 5 );
		Cache_Check::schedule_soon();
	}

	/**
	 * Deactivate earlier editions (WPOtkaz from GitHub) – OtkazPro uses the same settings and data.
	 */
	public static function maybe_take_over() {
		if ( current_user_can( 'activate_plugins' ) ) {
			self::take_over_old_editions();
		}
	}

	/**
	 * Deactivate earlier editions that are still active.
	 */
	private static function take_over_old_editions() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$taken = array();
		foreach ( self::OLD_EDITIONS as $old ) {
			if ( $old !== PY_OTKAZ_BASENAME && is_plugin_active( $old ) ) {
				deactivate_plugins( $old, true );
				$taken[] = $old;
			}
		}
		if ( $taken ) {
			set_transient( 'py_otkaz_took_over', $taken, DAY_IN_SECONDS );
			self::backup_settings();
		}
	}

	/**
	 * Keep a copy of all settings under a different option name.
	 */
	public static function backup_settings() {
		global $wpdb;
		// Never overwrite a good backup with wiped settings.
		if ( null === get_option( Settings::PREFIX . 'page_id', null ) ) {
			return;
		}
		$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'py\\_otkaz\\_%'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$data = array();
		foreach ( (array) $rows as $row ) {
			$data[ $row['option_name'] ] = maybe_unserialize( $row['option_value'] );
		}
		if ( $data ) {
			update_option( self::BACKUP_OPTION, $data, false );
		}
	}

	/**
	 * Uninstalling an older edition (before 1.3.1) deletes the shared py_otkaz_ settings –
	 * restore them from the backup.
	 */
	public static function restore_settings_if_wiped() {
		if ( null !== get_option( Settings::PREFIX . 'page_id', null ) ) {
			return;
		}
		$backup = get_option( self::BACKUP_OPTION );
		if ( ! is_array( $backup ) || ! $backup ) {
			return;
		}
		foreach ( $backup as $name => $value ) {
			if ( 0 === strpos( $name, Settings::PREFIX ) && null === get_option( $name, null ) ) {
				add_option( $name, $value, '', false );
			}
		}
		set_transient( 'py_otkaz_settings_restored', 1, DAY_IN_SECONDS );
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_clear_scheduled_hook( Cache_Check::ONCE_HOOK );
	}

	/**
	 * Run schema upgrades after plugin updates (activation hook does not run on update).
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::upgrade_schema();
		}
		// After a plugin update: re-run the cache check once.
		if ( get_option( 'py_otkaz_version' ) !== PY_OTKAZ_VERSION ) {
			update_option( 'py_otkaz_version', PY_OTKAZ_VERSION, false );
			Cache_Check::schedule_soon();
			self::backup_settings();
		}
	}

	/**
	 * Create / update the table and migrate existing records, then store the DB version.
	 */
	private static function upgrade_schema() {
		$from = (string) get_option( self::DB_VERSION_OPTION );
		self::create_tables();
		if ( '' !== $from && version_compare( $from, '1.1.0', '<' ) ) {
			Refunds::migrate_existing();
		}
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Make sure the retention cleanup is scheduled.
	 */
	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Submissions table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'py_otkaz_submissions';
	}

	/**
	 * Create / update the submissions table.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  number varchar(64) NOT NULL,
  order_id bigint(20) unsigned NOT NULL,
  order_number varchar(64) NOT NULL DEFAULT '',
  customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
  name varchar(255) NOT NULL DEFAULT '',
  email varchar(200) NOT NULL DEFAULT '',
  items longtext NOT NULL,
  statement longtext NOT NULL,
  reason text NULL,
  has_excluded tinyint(1) NOT NULL DEFAULT 0,
  within_deadline tinyint(1) NULL DEFAULT NULL,
  deadline_gmt datetime NULL DEFAULT NULL,
  source varchar(20) NOT NULL DEFAULT '',
  language varchar(20) NOT NULL DEFAULT '',
  ip_address varchar(100) NOT NULL DEFAULT '',
  user_agent varchar(500) NOT NULL DEFAULT '',
  email_status varchar(20) NOT NULL DEFAULT 'pending',
  email_sent_gmt datetime NULL DEFAULT NULL,
  email_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  admin_email_status varchar(20) NOT NULL DEFAULT 'pending',
  processed_gmt datetime NULL DEFAULT NULL,
  processed_by varchar(20) NOT NULL DEFAULT '',
  reminder_sent_gmt datetime NULL DEFAULT NULL,
  created_local datetime NOT NULL,
  created_gmt datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY number (number),
  KEY order_id (order_id),
  KEY email (email(100)),
  KEY created_gmt (created_gmt),
  KEY email_status (email_status),
  KEY processed_gmt (processed_gmt)
) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Create the "Withdrawal from contract" page with the shortcode on first activation.
	 */
	public static function maybe_create_page() {
		$page_id = (int) get_option( Settings::PREFIX . 'page_id' );
		if ( $page_id && 'trash' !== get_post_status( $page_id ) && get_post( $page_id ) ) {
			return;
		}

		// Reuse an existing page (e.g. after uninstall + reinstall) instead of creating a duplicate.
		$existing = self::find_existing_page();
		if ( $existing ) {
			update_option( Settings::PREFIX . 'page_id', $existing );
			return;
		}

		// A page with our slug already exists (e.g. an old returns form): never overwrite or
		// duplicate it silently – let the merchant decide in the admin.
		$slug_page = get_page_by_path( self::PAGE_SLUG, OBJECT, 'page' );
		if ( $slug_page && 'trash' !== $slug_page->post_status ) {
			update_option( self::CONFLICT_OPTION, (int) $slug_page->ID, false );
			return;
		}

		self::create_page();
	}

	/**
	 * Create a new form page and select it in the settings.
	 *
	 * @return int Page ID or 0.
	 */
	public static function create_page() {
		$page_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => __( 'Withdrawal from contract', 'otkazpro' ),
				'post_name'      => self::PAGE_SLUG,
				'post_content'   => self::shortcode_block(),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);

		if ( ! $page_id || is_wp_error( $page_id ) ) {
			return 0;
		}
		update_option( Settings::PREFIX . 'page_id', (int) $page_id );
		delete_option( self::CONFLICT_OPTION );
		return (int) $page_id;
	}

	/**
	 * Add the form at the end of an existing page (content is kept) and select it.
	 *
	 * @param int $page_id Page ID.
	 * @return bool
	 */
	public static function use_existing_page( $page_id ) {
		$post = get_post( $page_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return false;
		}
		if ( ! Form::has_form_shortcode( $post->post_content ) ) {
			$result = wp_update_post(
				array(
					'ID'           => $post->ID,
					'post_content' => rtrim( $post->post_content ) . "

" . self::shortcode_block(),
				),
				true
			);
			if ( is_wp_error( $result ) ) {
				return false;
			}
		}
		update_option( Settings::PREFIX . 'page_id', (int) $post->ID );
		delete_option( self::CONFLICT_OPTION );
		return true;
	}

	/**
	 * Shortcode block markup.
	 *
	 * @return string
	 */
	private static function shortcode_block() {
		return '<!-- wp:shortcode -->[' . Form::SHORTCODE . ']<!-- /wp:shortcode -->';
	}

	/**
	 * The oldest non-trashed page that already contains the form shortcode (new or legacy).
	 *
	 * @return int Page ID or 0.
	 */
	private static function find_existing_page() {
		$found = array();
		foreach ( Form::shortcodes() as $shortcode ) {
			$ids = get_posts(
				array(
					'post_type'      => 'page',
					'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
					's'              => '[' . $shortcode . ']',
					'fields'         => 'ids',
					'posts_per_page' => 5,
					'orderby'        => 'ID',
					'order'          => 'ASC',
				)
			);
			foreach ( $ids as $id ) {
				if ( has_shortcode( (string) get_post_field( 'post_content', $id ), $shortcode ) ) {
					$found[] = (int) $id;
				}
			}
		}
		return $found ? min( $found ) : 0;
	}
}
