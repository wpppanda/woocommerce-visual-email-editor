<?php
/**
 * Install and upgrade.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the design tables.
 */
class WPP_EE_Install {

	/**
	 * Activation hook.
	 */
	public static function activate() {
		self::create_tables();
		if ( false === get_option( 'wpp_ee_settings', false ) ) {
			add_option( 'wpp_ee_settings', WPP_EE_Settings::defaults(), '', false );
		}
		update_option( 'wpp_ee_db_version', WPP_EE_DB_VERSION, false );
	}

	/**
	 * Run dbDelta if the stored version is behind.
	 */
	public static function maybe_upgrade() {
		$installed = get_option( 'wpp_ee_db_version', '' );
		if ( WPP_EE_DB_VERSION === $installed ) {
			return;
		}
		self::create_tables();
		if ( false === get_option( 'wpp_ee_settings', false ) ) {
			add_option( 'wpp_ee_settings', WPP_EE_Settings::defaults(), '', false );
		}
		update_option( 'wpp_ee_db_version', WPP_EE_DB_VERSION, false );
	}

	/**
	 * Create or update tables. dbDelta is particular about formatting.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset    = $wpdb->get_charset_collate();
		$designs    = $wpdb->prefix . 'wpp_ee_designs';
		$revisions  = $wpdb->prefix . 'wpp_ee_revisions';

		$designs_sql = "CREATE TABLE {$designs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email_id varchar(191) NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 0,
			subject text NULL,
			preheader varchar(255) NULL,
			settings longtext NULL,
			blocks longtext NOT NULL,
			updated_at datetime NOT NULL,
			updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY email_id (email_id)
		) {$charset};";

		$revisions_sql = "CREATE TABLE {$revisions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email_id varchar(191) NOT NULL,
			snapshot longtext NOT NULL,
			created_at datetime NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY email_id (email_id)
		) {$charset};";

		dbDelta( $designs_sql );
		dbDelta( $revisions_sql );
	}
}
