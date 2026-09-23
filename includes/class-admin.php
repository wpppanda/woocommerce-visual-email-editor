<?php
/**
 * Admin screen.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Menu, assets, and the small links inside WooCommerce email settings.
 */
class WPP_EE_Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'in_admin_header', array( __CLASS__, 'silence_notices' ), 100 );
		add_filter( 'plugin_action_links_' . plugin_basename( WPP_EE_FILE ), array( __CLASS__, 'links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'missing_woocommerce' ) );

		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_sections_email', array( __CLASS__, 'emails_banner' ) );
			add_action( 'woocommerce_email_settings_after', array( __CLASS__, 'email_link' ) );
		}
	}

	/**
	 * Submenu under WooCommerce, or a top-level page when WooCommerce is missing.
	 */
	public static function menu() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_submenu_page(
				'woocommerce',
				__( 'Email Editor', 'wpp-email-editor' ),
				__( 'Email Editor', 'wpp-email-editor' ),
				'manage_woocommerce',
				'wpp-email-editor',
				array( __CLASS__, 'render' )
			);
			return;
		}

		add_menu_page(
			__( 'Email Editor', 'wpp-email-editor' ),
			__( 'Email Editor', 'wpp-email-editor' ),
			'manage_options',
			'wpp-email-editor',
			array( __CLASS__, 'render' ),
			'dashicons-email-alt',
			58
		);
	}

	/**
	 * Editor mount point. The script builds the rest.
	 */
	public static function render() {
		if ( ! WPP_EE_Plugin::user_can() ) {
			wp_die( esc_html__( 'You do not have permission to design emails.', 'wpp-email-editor' ) );
		}
		echo '<div id="wpp-ee-app" class="wpp-ee-app" data-email="' . esc_attr( self::requested_email() ) . '"></div>';
	}

	/**
	 * Styles and the editor script, only on our screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		if ( ! self::is_screen( $hook ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wpp-ee-admin', WPP_EE_URL . 'assets/css/admin.css', array(), WPP_EE_VERSION );
		wp_enqueue_script( 'wpp-ee-editor', WPP_EE_URL . 'assets/js/editor.js', array(), WPP_EE_VERSION, true );

		$settings = WPP_EE_Settings::get();
		$config   = array(
			'demo'       => false,
			'restUrl'    => esc_url_raw( rest_url( WPP_EE_REST::NS . '/' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'version'    => WPP_EE_VERSION,
			'email'      => self::requested_email(),
			'adminEmail' => (string) get_option( 'admin_email' ),
			'hasWoo'     => class_exists( 'WooCommerce' ),
			'emails'     => class_exists( 'WooCommerce' ) ? WPP_EE_Registry::all() : array(),
			'settings'   => $settings,
			'schema'     => WPP_EE_Schema::all(),
			'sample'     => class_exists( 'WooCommerce' ) ? WPP_EE_Context::canvas_sample() : array(),
			'orders'     => class_exists( 'WooCommerce' ) ? WPP_EE_REST::recent_orders() : array(),
			'i18n'       => WPP_EE_I18n::script_catalog(),
			'urls'       => array(
				'emails'  => admin_url( 'admin.php?page=wc-settings&tab=email' ),
				'plugins' => admin_url( 'plugins.php' ),
			),
		);

		wp_add_inline_script( 'wpp-ee-editor', 'window.WPPEmailEditor = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Mark the screen so the stylesheet can take the content area.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( self::is_screen() ) {
			$classes .= ' wpp-ee-screen';
		}
		return $classes;
	}

	/**
	 * Other plugins' notices wreck a visual editor. Drop them on our screen only.
	 */
	public static function silence_notices() {
		if ( ! self::is_screen() ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	/**
	 * Plugins list shortcut.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function links( $links ) {
		$url     = admin_url( 'admin.php?page=wpp-email-editor' );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open editor', 'wpp-email-editor' ) . '</a>';
		return $links;
	}

	/**
	 * Ask for WooCommerce when it is not active. Hidden on our own screen, which has its own empty state.
	 */
	public static function missing_woocommerce() {
		if ( class_exists( 'WooCommerce' ) || self::is_screen() ) {
			return;
		}
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'WPP Email Editor needs WooCommerce to design and send emails.', 'wpp-email-editor' ) . '</p></div>';
	}

	/**
	 * Banner on WooCommerce → Settings → Emails.
	 */
	public static function emails_banner() {
		if ( ! WPP_EE_Plugin::user_can() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['section'] ) && '' !== $_GET['section'] ) {
			return;
		}
		$url = admin_url( 'admin.php?page=wpp-email-editor' );
		echo '<div class="notice notice-info" style="margin:12px 0;padding:8px 12px;"><p style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">'
			. esc_html__( 'Design these emails visually — logo, copy, order table, and all — without copying templates into the theme.', 'wpp-email-editor' )
			. ' <a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Open editor', 'wpp-email-editor' ) . '</a></p></div>';
	}

	/**
	 * Link on an individual WooCommerce email settings screen.
	 *
	 * @param WC_Email $email Email being edited.
	 */
	public static function email_link( $email ) {
		if ( ! $email instanceof WC_Email || ! WPP_EE_Plugin::user_can() ) {
			return;
		}
		$url = admin_url( 'admin.php?page=wpp-email-editor&email=' . rawurlencode( $email->id ) );
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Design this email', 'wpp-email-editor' ) . '</a></p>';
	}

	/**
	 * Email id passed in the query string.
	 *
	 * @return string
	 */
	private static function requested_email() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET['email'] ) ? wp_unslash( $_GET['email'] ) : '';
		return WPP_EE_Sanitizer::email_id( $raw );
	}

	/**
	 * Whether the current admin page is ours.
	 *
	 * @param string $hook Optional hook from admin_enqueue_scripts.
	 * @return bool
	 */
	private static function is_screen( $hook = '' ) {
		if ( $hook ) {
			return in_array( $hook, array( 'woocommerce_page_wpp-email-editor', 'toplevel_page_wpp-email-editor' ), true );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) && 'wpp-email-editor' === $_GET['page'];
	}
}
