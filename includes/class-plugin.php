<?php
/**
 * Bootstrap.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin after WooCommerce has had a chance to load.
 */
class WPP_EE_Plugin {

	/**
	 * Start the plugin.
	 */
	public static function init() {
		WPP_EE_I18n::init();
		WPP_EE_Install::maybe_upgrade();
		WPP_EE_Admin::init();
		WPP_EE_REST::init();

		if ( class_exists( 'WooCommerce' ) ) {
			WPP_EE_Mailer::init();
		}
	}

	/**
	 * Whether the current user may design emails.
	 *
	 * @return bool
	 */
	public static function user_can() {
		$cap = apply_filters( 'wpp_ee_capability', class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options' );
		return current_user_can( $cap );
	}
}
