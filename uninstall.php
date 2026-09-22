<?php
/**
 * Uninstall WPP Email Editor.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'wpp_ee_designs',
	$wpdb->prefix . 'wpp_ee_revisions',
);

foreach ( $tables as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'wpp_ee_settings' );
delete_option( 'wpp_ee_db_version' );

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpp_ee_%' OR option_name LIKE '_transient_timeout_wpp_ee_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
