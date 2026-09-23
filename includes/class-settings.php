<?php
/**
 * Brand kit.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global brand settings stored in a single option.
 */
class WPP_EE_Settings {

	const OPTION = 'wpp_ee_settings';

	/**
	 * Schema defaults, including empty social URLs.
	 *
	 * @return array
	 */
	public static function defaults() {
		$defaults = WPP_EE_Schema::defaults_from( WPP_EE_Schema::global_settings() );
		$social   = array();
		foreach ( WPP_EE_Schema::social_networks() as $network ) {
			$social[ $network ] = '';
		}
		$defaults['social'] = $social;
		return $defaults;
	}

	/**
	 * Saved settings merged over defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return WPP_EE_Sanitizer::settings( array_merge( self::defaults(), $stored ) );
	}

	/**
	 * Persist a sanitized brand kit.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	public static function update( array $settings ) {
		$clean = WPP_EE_Sanitizer::settings( $settings );
		update_option( self::OPTION, $clean, false );
		return $clean;
	}
}
