<?php
/**
 * WooCommerce email catalog.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists every email WooCommerce (and other plugins) have registered.
 */
class WPP_EE_Registry {

	/**
	 * Emails for the editor library.
	 *
	 * @return array
	 */
	public static function all() {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return array();
		}

		$saved = WPP_EE_Repository::all();
		$out   = array();

		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( ! $email instanceof WC_Email || empty( $email->id ) ) {
				continue;
			}
			$id     = WPP_EE_Sanitizer::email_id( $email->id );
			$design = isset( $saved[ $id ] ) ? $saved[ $id ] : null;
			$out[]  = array(
				'id'          => $id,
				'title'       => wp_strip_all_tags( $email->get_title() ),
				'description' => wp_strip_all_tags( $email->get_description() ),
				'audience'    => $email->is_customer_email() ? 'customer' : 'admin',
				'group'       => self::group( $email ),
				'kind'        => WPP_EE_Blueprints::kind( $id ),
				'manual'      => (bool) $email->is_manual(),
				'wcEnabled'   => (bool) $email->is_enabled(),
				'customized'  => (bool) $design,
				'live'        => $design && ! empty( $design['enabled'] ),
				'updatedAt'   => $design ? $design['updatedAt'] : '',
			);
		}

		return apply_filters( 'wpp_ee_emails', $out );
	}

	/**
	 * Whether an id belongs to a registered email.
	 *
	 * @param string $email_id Email id.
	 * @return bool
	 */
	public static function exists( $email_id ) {
		$email_id = WPP_EE_Sanitizer::email_id( $email_id );
		foreach ( self::all() as $email ) {
			if ( $email['id'] === $email_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Find a live WC_Email instance.
	 *
	 * @param string $email_id Email id.
	 * @return WC_Email|null
	 */
	public static function instance( $email_id ) {
		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return null;
		}
		$email_id = WPP_EE_Sanitizer::email_id( $email_id );
		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( $email instanceof WC_Email && WPP_EE_Sanitizer::email_id( $email->id ) === $email_id ) {
				return $email;
			}
		}
		return null;
	}

	/**
	 * Library group.
	 *
	 * @param WC_Email $email Email.
	 * @return string
	 */
	private static function group( WC_Email $email ) {
		$id = $email->id;
		if ( in_array( $id, array( 'low_stock', 'no_stock', 'backorder' ), true ) || false !== strpos( $id, 'stock' ) ) {
			return 'stock';
		}
		if ( in_array( $id, array( 'customer_new_account', 'customer_reset_password' ), true ) ) {
			return 'accounts';
		}
		if ( $email->is_customer_email() || in_array( $id, array( 'new_order', 'cancelled_order', 'failed_order' ), true ) ) {
			return 'orders';
		}
		return 'other';
	}
}
