<?php
/**
 * REST API.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor endpoints. All of them require the email-editor capability.
 */
class WPP_EE_REST {

	const NS = 'wpp-email-editor/v1';

	/**
	 * Register routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Route table.
	 */
	public static function routes() {
		$id = '(?P<id>[a-z0-9_-]+)';

		self::route( '/emails', 'GET', 'emails' );
		self::route( '/designs/' . $id, 'GET', 'get_design' );
		self::route( '/designs/' . $id, 'PUT', 'save_design' );
		self::route( '/designs/' . $id, 'DELETE', 'delete_design' );
		self::route( '/designs/' . $id . '/revisions', 'GET', 'revisions' );
		self::route( '/designs/' . $id . '/restore', 'POST', 'restore' );
		self::route( '/preview', 'POST', 'preview' );
		self::route( '/test', 'POST', 'test_email' );
		self::route( '/settings', 'GET', 'get_settings' );
		self::route( '/settings', 'PUT', 'save_settings' );
		self::route( '/orders', 'GET', 'orders' );
		self::route( '/coupons', 'GET', 'coupons' );
		self::route( '/products', 'GET', 'products' );
		self::route( '/export', 'GET', 'export' );
		self::route( '/import', 'POST', 'import' );
	}

	/**
	 * Register one route.
	 *
	 * @param string $path     Path.
	 * @param string $methods  Methods.
	 * @param string $callback Method on this class.
	 */
	private static function route( $path, $methods, $callback ) {
		register_rest_route(
			self::NS,
			$path,
			array(
				'methods'             => $methods,
				'callback'            => array( __CLASS__, $callback ),
				'permission_callback' => array( __CLASS__, 'can' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public static function can() {
		return WPP_EE_Plugin::user_can();
	}

	/**
	 * Email library.
	 *
	 * @return array
	 */
	public static function emails() {
		return array( 'emails' => WPP_EE_Registry::all() );
	}

	/**
	 * Saved design, or a starter when nothing has been saved.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function get_design( WP_REST_Request $request ) {
		$id = self::require_email( $request );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$design = WPP_EE_Repository::get( $id );
		if ( ! $design ) {
			$design           = WPP_EE_Blueprints::make( $id );
			$design['emailId'] = $id;
			$design['saved']  = false;
			return $design;
		}
		$design['saved'] = true;
		return $design;
	}

	/**
	 * Save. Pass revision=0 to autosave without a history entry.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function save_design( WP_REST_Request $request ) {
		$id = self::require_email( $request );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$params   = $request->get_json_params();
		$params   = is_array( $params ) ? $params : array();
		$design   = WPP_EE_Sanitizer::design( $params );
		$revision = ! isset( $params['revision'] ) || false !== $params['revision'];
		$saved    = WPP_EE_Repository::save( $id, $design, get_current_user_id(), $revision );
		$saved['saved'] = true;

		do_action( 'wpp_ee_after_save', $id, $saved );

		return $saved;
	}

	/**
	 * Forget a design. WooCommerce defaults take over.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function delete_design( WP_REST_Request $request ) {
		$id = self::require_email( $request );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		WPP_EE_Repository::delete( $id );
		$design            = WPP_EE_Blueprints::make( $id );
		$design['emailId'] = $id;
		$design['saved']   = false;
		return $design;
	}

	/**
	 * Revision list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function revisions( WP_REST_Request $request ) {
		$id = self::require_email( $request );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array( 'revisions' => WPP_EE_Repository::revisions( $id ) );
	}

	/**
	 * Restore a revision.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function restore( WP_REST_Request $request ) {
		$id = self::require_email( $request );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$rev  = absint( $request->get_param( 'revisionId' ) );
		$saved = WPP_EE_Repository::restore( $id, $rev, get_current_user_id() );
		if ( ! $saved ) {
			return new WP_Error( 'wpp_ee_revision', __( 'That revision could not be restored.', 'wpp-email-editor' ), array( 'status' => 404 ) );
		}
		$saved['saved'] = true;
		return $saved;
	}

	/**
	 * Render HTML for the preview iframe. Does not send anything.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function preview( WP_REST_Request $request ) {
		$params   = $request->get_json_params();
		$params   = is_array( $params ) ? $params : array();
		$email_id = isset( $params['emailId'] ) ? WPP_EE_Sanitizer::email_id( $params['emailId'] ) : '';
		if ( ! WPP_EE_Registry::exists( $email_id ) ) {
			return new WP_Error( 'wpp_ee_email', __( 'Unknown email.', 'wpp-email-editor' ), array( 'status' => 404 ) );
		}
		$design  = WPP_EE_Sanitizer::design( $params );
		$context = WPP_EE_Context::preview( $email_id, isset( $params['orderId'] ) ? absint( $params['orderId'] ) : 0 );
		return array(
			'html'    => WPP_EE_Renderer::html( $design, $context ),
			'subject' => $context->replace_plain( $design['subject'] ),
		);
	}

	/**
	 * Send the current design to one address.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function test_email( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$to     = isset( $params['to'] ) ? sanitize_email( $params['to'] ) : '';
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'wpp_ee_to', __( 'Enter a valid email address.', 'wpp-email-editor' ), array( 'status' => 400 ) );
		}

		$key   = 'wpp_ee_tests_' . get_current_user_id();
		$count = (int) get_transient( $key );
		if ( $count >= 20 ) {
			return new WP_Error( 'wpp_ee_limit', __( 'Test email limit reached. Try again in an hour.', 'wpp-email-editor' ), array( 'status' => 429 ) );
		}

		$email_id = isset( $params['emailId'] ) ? WPP_EE_Sanitizer::email_id( $params['emailId'] ) : '';
		$design   = WPP_EE_Sanitizer::design( $params );
		$context  = WPP_EE_Context::preview( $email_id, isset( $params['orderId'] ) ? absint( $params['orderId'] ) : 0 );
		$html     = WPP_EE_Renderer::html( $design, $context );
		$subject  = '[Test] ' . $context->replace_plain( $design['subject'] );
		$sent     = wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		if ( ! $sent ) {
			return new WP_Error( 'wpp_ee_mail', __( 'WordPress could not send the email. Check the site mail setup.', 'wpp-email-editor' ), array( 'status' => 500 ) );
		}
		return array( 'sent' => true );
	}

	/**
	 * Brand kit.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return array( 'settings' => WPP_EE_Settings::get() );
	}

	/**
	 * Save the brand kit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function save_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		return array( 'settings' => WPP_EE_Settings::update( $params ) );
	}

	/**
	 * Recent orders for the preview picker.
	 *
	 * @return array
	 */
	public static function orders() {
		return array( 'orders' => self::recent_orders() );
	}

	/**
	 * Published coupons, for the coupon block.
	 *
	 * @return array
	 */
	public static function coupons() {
		$posts = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$out = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'code'   => $post->post_title,
				'amount' => get_post_meta( $post->ID, 'coupon_amount', true ),
				'type'   => get_post_meta( $post->ID, 'discount_type', true ),
			);
		}
		return array( 'coupons' => $out );
	}

	/**
	 * Product search for the products block.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function products( WP_REST_Request $request ) {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$ids    = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				's'              => $search,
				'posts_per_page' => 12,
				'fields'         => 'ids',
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( ! $product ) {
				continue;
			}
			$image_id = $product->get_image_id();
			$out[]    = array(
				'id'    => $product->get_id(),
				'name'  => $product->get_name(),
				'price' => wp_strip_all_tags( $product->get_price_html() ),
				'image' => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '',
			);
		}
		return array( 'products' => $out );
	}

	/**
	 * Export designs and the brand kit.
	 *
	 * @return array
	 */
	public static function export() {
		return array(
			'plugin'     => 'wpp-email-editor',
			'version'    => WPP_EE_VERSION,
			'exportedAt' => gmdate( 'c' ),
			'settings'   => WPP_EE_Settings::get(),
			'designs'    => WPP_EE_Repository::all(),
		);
	}

	/**
	 * Import a previously exported file.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function import( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'wpp_ee_import', __( 'That file is not a WPP Email Editor export.', 'wpp-email-editor' ), array( 'status' => 400 ) );
		}
		if ( isset( $params['settings'] ) && is_array( $params['settings'] ) ) {
			WPP_EE_Settings::update( $params['settings'] );
		}
		$imported = 0;
		if ( isset( $params['designs'] ) && is_array( $params['designs'] ) ) {
			foreach ( $params['designs'] as $id => $design ) {
				$email_id = WPP_EE_Sanitizer::email_id( is_string( $id ) ? $id : '' );
				if ( '' === $email_id || ! is_array( $design ) || ! WPP_EE_Registry::exists( $email_id ) ) {
					continue;
				}
				WPP_EE_Repository::save( $email_id, WPP_EE_Sanitizer::design( $design ), get_current_user_id(), true );
				++$imported;
			}
		}
		return array(
			'imported' => $imported,
			'settings' => WPP_EE_Settings::get(),
			'emails'   => WPP_EE_Registry::all(),
		);
	}

	/**
	 * Validate the email id in the route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string|WP_Error
	 */
	private static function require_email( WP_REST_Request $request ) {
		$id = WPP_EE_Sanitizer::email_id( $request['id'] );
		if ( '' === $id || ! WPP_EE_Registry::exists( $id ) ) {
			return new WP_Error( 'wpp_ee_email', __( 'Unknown email.', 'wpp-email-editor' ), array( 'status' => 404 ) );
		}
		return $id;
	}

	/**
	 * Recent orders.
	 *
	 * @return array
	 */
	public static function recent_orders() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		$orders = wc_get_orders(
			array(
				'limit'   => 15,
				'orderby' => 'date',
				'order'   => 'DESC',
			)
		);
		$out = array();
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$out[] = array(
				'id'     => $order->get_id(),
				'number' => $order->get_order_number(),
				'name'   => $order->get_formatted_billing_full_name(),
				'total'  => wp_strip_all_tags( $order->get_formatted_order_total() ),
				'date'   => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
			);
		}
		return $out;
	}
}
