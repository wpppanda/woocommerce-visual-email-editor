<?php
/**
 * Design storage.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One row per WooCommerce email, plus a short revision history.
 */
class WPP_EE_Repository {

	/**
	 * Request cache.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Designs table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wpp_ee_designs';
	}

	/**
	 * Revisions table.
	 *
	 * @return string
	 */
	public static function revisions_table() {
		global $wpdb;
		return $wpdb->prefix . 'wpp_ee_revisions';
	}

	/**
	 * All saved designs keyed by email id.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );
		$out  = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$design = self::row_to_design( $row );
				if ( $design ) {
					$out[ $design['emailId'] ] = $design;
				}
			}
		}

		self::$cache = $out;
		return $out;
	}

	/**
	 * One design, or null.
	 *
	 * @param string $email_id WooCommerce email id.
	 * @return array|null
	 */
	public static function get( $email_id ) {
		$email_id = WPP_EE_Sanitizer::email_id( $email_id );
		if ( '' === $email_id ) {
			return null;
		}
		$all = self::all();
		return isset( $all[ $email_id ] ) ? $all[ $email_id ] : null;
	}

	/**
	 * Enabled design, or null when the store should keep the WooCommerce default.
	 *
	 * @param string $email_id WooCommerce email id.
	 * @return array|null
	 */
	public static function enabled( $email_id ) {
		$design = self::get( $email_id );
		if ( ! $design || empty( $design['enabled'] ) || empty( $design['blocks'] ) ) {
			return null;
		}
		return $design;
	}

	/**
	 * Insert or update a design.
	 *
	 * @param string $email_id  Email id.
	 * @param array  $design    Sanitized design.
	 * @param int    $user_id   Author.
	 * @param bool   $revision  Whether to keep a revision.
	 * @return array
	 */
	public static function save( $email_id, array $design, $user_id, $revision = true ) {
		global $wpdb;

		$email_id = WPP_EE_Sanitizer::email_id( $email_id );
		$now      = current_time( 'mysql' );
		$row      = array(
			'email_id'   => $email_id,
			'enabled'    => empty( $design['enabled'] ) ? 0 : 1,
			'subject'    => $design['subject'],
			'preheader'  => $design['preheader'],
			'settings'   => wp_json_encode( $design['settings'] ),
			'blocks'     => wp_json_encode( $design['blocks'] ),
			'updated_at' => $now,
			'updated_by' => (int) $user_id,
		);

		$existing = self::get( $email_id );
		$table    = self::table();

		if ( $existing ) {
			$wpdb->update( $table, $row, array( 'email_id' => $email_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		self::$cache = null;

		if ( $revision ) {
			self::add_revision( $email_id, $design, $user_id );
		}

		$saved = self::get( $email_id );
		return $saved ? $saved : $design;
	}

	/**
	 * Delete a design and its revisions.
	 *
	 * @param string $email_id Email id.
	 */
	public static function delete( $email_id ) {
		global $wpdb;
		$email_id = WPP_EE_Sanitizer::email_id( $email_id );
		$wpdb->delete( self::table(), array( 'email_id' => $email_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::revisions_table(), array( 'email_id' => $email_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::$cache = null;
	}

	/**
	 * Recent revisions, newest first.
	 *
	 * @param string $email_id Email id.
	 * @return array
	 */
	public static function revisions( $email_id ) {
		global $wpdb;
		$email_id = WPP_EE_Sanitizer::email_id( $email_id );
		$table    = self::revisions_table();
		$rows     = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, email_id, created_at, created_by FROM `{$table}` WHERE email_id = %s ORDER BY id DESC LIMIT 25", $email_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$out = array();
		foreach ( (array) $rows as $row ) {
			$user = get_userdata( (int) $row['created_by'] );
			$out[] = array(
				'id'        => (int) $row['id'],
				'createdAt' => $row['created_at'],
				'author'    => $user ? $user->display_name : __( 'Unknown', 'wpp-email-editor' ),
			);
		}
		return $out;
	}

	/**
	 * Restore a revision over the current design.
	 *
	 * @param string $email_id    Email id.
	 * @param int    $revision_id Revision id.
	 * @param int    $user_id     Who restored it.
	 * @return array|null
	 */
	public static function restore( $email_id, $revision_id, $user_id ) {
		global $wpdb;
		$email_id = WPP_EE_Sanitizer::email_id( $email_id );
		$table    = self::revisions_table();
		$snapshot = $wpdb->get_var(
			$wpdb->prepare( "SELECT snapshot FROM `{$table}` WHERE id = %d AND email_id = %s", (int) $revision_id, $email_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( ! $snapshot ) {
			return null;
		}
		$decoded = json_decode( $snapshot, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}
		$clean = WPP_EE_Sanitizer::design( $decoded );
		return self::save( $email_id, $clean, $user_id, true );
	}

	/**
	 * Store a snapshot and prune old ones.
	 *
	 * @param string $email_id Email id.
	 * @param array  $design   Design.
	 * @param int    $user_id  Author.
	 */
	private static function add_revision( $email_id, array $design, $user_id ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::revisions_table(),
			array(
				'email_id'   => $email_id,
				'snapshot'   => wp_json_encode( $design ),
				'created_at' => current_time( 'mysql' ),
				'created_by' => (int) $user_id,
			)
		);

		$table = self::revisions_table();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE email_id = %s ORDER BY id DESC LIMIT 25, 100", $email_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $ids ) {
			$in = implode( ',', array_map( 'absint', $ids ) );
			$wpdb->query( "DELETE FROM `{$table}` WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Map a database row to the editor document.
	 *
	 * @param array $row Database row.
	 * @return array|null
	 */
	private static function row_to_design( array $row ) {
		$settings = json_decode( (string) $row['settings'], true );
		$blocks   = json_decode( (string) $row['blocks'], true );
		if ( ! is_array( $blocks ) ) {
			return null;
		}
		return array(
			'emailId'   => (string) $row['email_id'],
			'enabled'   => (bool) $row['enabled'],
			'subject'   => (string) $row['subject'],
			'preheader' => (string) $row['preheader'],
			'settings'  => is_array( $settings ) ? $settings : array(),
			'blocks'    => $blocks,
			'updatedAt' => (string) $row['updated_at'],
			'updatedBy' => (int) $row['updated_by'],
		);
	}
}
