<?php
/**
 * Plugin Name:       WPP Email Editor
 * Plugin URI:        https://github.com/wpppanda/woocommerce-visual-email-editor
 * Description:       Visual email editor for WooCommerce. Design transactional emails without editing templates.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            WP Panda
 * Author URI:        https://github.com/wpppanda
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpp-email-editor
 * Domain Path:       /languages
 * WC requires at least: 7.1
 * WC tested up to:   10.2
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPP_EE_VERSION', '1.0.0' );
define( 'WPP_EE_FILE', __FILE__ );
define( 'WPP_EE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPP_EE_URL', plugin_dir_url( __FILE__ ) );
define( 'WPP_EE_DB_VERSION', '1.0.0' );

require_once WPP_EE_DIR . 'includes/class-schema.php';
require_once WPP_EE_DIR . 'includes/class-i18n.php';
require_once WPP_EE_DIR . 'includes/class-install.php';
require_once WPP_EE_DIR . 'includes/class-settings.php';
require_once WPP_EE_DIR . 'includes/class-repository.php';
require_once WPP_EE_DIR . 'includes/class-sanitizer.php';
require_once WPP_EE_DIR . 'includes/class-registry.php';
require_once WPP_EE_DIR . 'includes/class-context.php';
require_once WPP_EE_DIR . 'includes/class-blueprints.php';
require_once WPP_EE_DIR . 'includes/class-renderer.php';
require_once WPP_EE_DIR . 'includes/class-mailer.php';
require_once WPP_EE_DIR . 'includes/class-rest.php';
require_once WPP_EE_DIR . 'includes/class-admin.php';
require_once WPP_EE_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'WPP_EE_Install', 'activate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPP_EE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WPP_EE_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'WPP_EE_Plugin', 'init' ), 20 );
