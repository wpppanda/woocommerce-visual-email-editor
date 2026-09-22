<?php
/**
 * Translations.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the Russian catalog from JSON so the editor does not depend on a compiled .mo.
 * A .mo is still shipped for the plugins list, which translates before this class boots.
 */
class WPP_EE_I18n {

	/**
	 * Cached map.
	 *
	 * @var array|null
	 */
	private static $ru = null;

	/**
	 * Hook gettext.
	 */
	public static function init() {
		add_filter( 'gettext', array( __CLASS__, 'gettext' ), 10, 3 );
		add_filter( 'load_textdomain_mofile', array( __CLASS__, 'mofile' ), 10, 2 );
		load_plugin_textdomain( 'wpp-email-editor', false, dirname( plugin_basename( WPP_EE_FILE ) ) . '/languages' );
	}

	/**
	 * Point WordPress at our compiled catalog.
	 *
	 * @param string $mofile Requested file.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public static function mofile( $mofile, $domain ) {
		if ( 'wpp-email-editor' !== $domain ) {
			return $mofile;
		}
		$locale = determine_locale();
		if ( 0 !== strpos( $locale, 'ru' ) ) {
			return $mofile;
		}
		$local = WPP_EE_DIR . 'languages/wpp-email-editor-ru_RU.mo';
		return file_exists( $local ) ? $local : $mofile;
	}

	/**
	 * Translate plugin strings when the active editor locale is Russian.
	 *
	 * @param string $translated Already translated text.
	 * @param string $text       Original text.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public static function gettext( $translated, $text, $domain ) {
		if ( 'wpp-email-editor' !== $domain ) {
			return $translated;
		}
		if ( ! self::use_russian() ) {
			return $translated;
		}
		$map = self::russian_map();
		return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
	}

	/**
	 * Whether the editor should speak Russian.
	 *
	 * @return bool
	 */
	public static function use_russian() {
		$choice = 'site';
		$stored = get_option( 'wpp_ee_settings', array() );
		if ( is_array( $stored ) && ! empty( $stored['uiLocale'] ) ) {
			$choice = $stored['uiLocale'];
		}
		if ( 'ru' === $choice ) {
			return true;
		}
		if ( 'en' === $choice ) {
			return false;
		}
		return 0 === strpos( determine_locale(), 'ru' );
	}

	/**
	 * English => Russian map.
	 *
	 * @return array
	 */
	public static function russian_map() {
		if ( null !== self::$ru ) {
			return self::$ru;
		}
		$path = WPP_EE_DIR . 'assets/i18n/ru.json';
		$raw  = file_exists( $path ) ? file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$map  = json_decode( (string) $raw, true );
		self::$ru = is_array( $map ) ? $map : array();
		return self::$ru;
	}

	/**
	 * Strings the editor script needs, already translated.
	 *
	 * @return array
	 */
	public static function script_catalog() {
		$map = self::russian_map();
		$out = array();
		foreach ( $map as $en => $ru ) {
			$out[ $en ] = self::use_russian() ? $ru : $en;
		}
		return $out;
	}
}
