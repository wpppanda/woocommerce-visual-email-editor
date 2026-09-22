<?php
/**
 * Shared block schema.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads assets/schema.json once and exposes it to PHP and the editor.
 */
class WPP_EE_Schema {

	/**
	 * Parsed schema.
	 *
	 * @var array|null
	 */
	private static $data = null;

	/**
	 * Full schema.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$data ) {
			return self::$data;
		}

		$path = WPP_EE_DIR . 'assets/schema.json';
		$raw  = file_exists( $path ) ? file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = json_decode( (string) $raw, true );

		self::$data = is_array( $data ) ? $data : array();
		return self::$data;
	}

	/**
	 * Block definitions keyed by type.
	 *
	 * @return array
	 */
	public static function blocks() {
		$all = self::all();
		return isset( $all['blocks'] ) && is_array( $all['blocks'] ) ? $all['blocks'] : array();
	}

	/**
	 * One block definition.
	 *
	 * @param string $type Block type.
	 * @return array|null
	 */
	public static function block( $type ) {
		$blocks = self::blocks();
		return isset( $blocks[ $type ] ) ? $blocks[ $type ] : null;
	}

	/**
	 * Email-level style settings schema.
	 *
	 * @return array
	 */
	public static function email_settings() {
		$all = self::all();
		return isset( $all['emailSettings'] ) && is_array( $all['emailSettings'] ) ? $all['emailSettings'] : array();
	}

	/**
	 * Brand kit schema.
	 *
	 * @return array
	 */
	public static function global_settings() {
		$all = self::all();
		return isset( $all['globalSettings'] ) && is_array( $all['globalSettings'] ) ? $all['globalSettings'] : array();
	}

	/**
	 * Allowed font stacks.
	 *
	 * @return string[]
	 */
	public static function fonts() {
		$all   = self::all();
		$fonts = isset( $all['fonts'] ) && is_array( $all['fonts'] ) ? $all['fonts'] : array();
		$ids   = array();
		foreach ( $fonts as $font ) {
			if ( isset( $font['id'] ) ) {
				$ids[] = (string) $font['id'];
			}
		}
		return $ids;
	}

	/**
	 * Social network keys.
	 *
	 * @return string[]
	 */
	public static function social_networks() {
		$all = self::all();
		$list = isset( $all['socialNetworks'] ) && is_array( $all['socialNetworks'] ) ? $all['socialNetworks'] : array();
		return array_map( 'strval', $list );
	}

	/**
	 * Default value map for a prop schema.
	 *
	 * @param array $props Prop schema.
	 * @return array
	 */
	public static function defaults_from( array $props ) {
		$defaults = array();
		foreach ( $props as $key => $rule ) {
			if ( isset( $rule['type'] ) && 'social' === $rule['type'] ) {
				$defaults[ $key ] = array();
				continue;
			}
			$defaults[ $key ] = isset( $rule['default'] ) ? $rule['default'] : '';
		}
		return $defaults;
	}
}
