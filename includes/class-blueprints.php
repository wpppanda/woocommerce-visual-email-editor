<?php
/**
 * Starter designs.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a finished-looking starter for each WooCommerce email.
 * Copy is translated. Colors and the logo come from the brand kit.
 */
class WPP_EE_Blueprints {

	/**
	 * What kind of data this email carries.
	 *
	 * @param string $email_id Email id.
	 * @return string order|account|stock
	 */
	public static function kind( $email_id ) {
		if ( in_array( $email_id, array( 'low_stock', 'no_stock', 'backorder' ), true ) || false !== strpos( $email_id, 'stock' ) ) {
			return 'stock';
		}
		if ( in_array( $email_id, array( 'customer_new_account', 'customer_reset_password' ), true ) ) {
			return 'account';
		}
		return 'order';
	}

	/**
	 * A design document that has not been saved yet.
	 *
	 * @param string $email_id Email id.
	 * @return array
	 */
	public static function make( $email_id ) {
		$email_id = WPP_EE_Sanitizer::email_id( $email_id );
		$brand    = WPP_EE_Settings::get();
		$copy     = self::copy( $email_id );
		$kind     = self::kind( $email_id );

		$settings = array(
			'width'             => $brand['width'],
			'background'        => $brand['background'],
			'contentBackground' => $brand['contentBackground'],
			'textColor'         => $brand['text'],
			'linkColor'         => $brand['primary'],
			'font'              => $brand['font'],
			'radius'            => $brand['radius'],
		);

		$blocks = array(
			self::block(
				'header',
				array(
					'logoUrl'    => $brand['logoUrl'],
					'logoWidth'  => $brand['logoWidth'],
					'background' => $brand['headerBackground'],
					'color'      => $brand['headerColor'],
				)
			),
			self::block( 'heading', array( 'text' => $copy['heading'], 'color' => '#17191d' ) ),
			self::block( 'text', array( 'html' => $copy['html'], 'color' => $brand['text'] ) ),
		);

		if ( 'order' === $kind ) {
			$blocks[] = self::block( 'order_meta', array() );
			$blocks[] = self::block( 'order_table', array( 'totalColor' => $brand['primary'] ) );
			if ( 'customer_note' === $email_id ) {
				$blocks[] = self::block( 'note', array( 'heading' => __( 'Note from the store', 'wpp-email-editor' ) ) );
			} else {
				$blocks[] = self::block( 'note', array() );
			}
			$blocks[] = self::block( 'addresses', array() );
			$blocks[] = self::block( 'downloads', array() );
			if ( false !== strpos( $email_id, 'refund' ) ) {
				$blocks[] = self::block(
					'text',
					array(
						'html' => '<p>' . esc_html__( 'Refund amount: {refund_amount}. It can take a few days to appear on the original payment method.', 'wpp-email-editor' ) . '</p>',
					)
				);
			}
			$blocks[] = self::block(
				'button',
				array(
					'text'       => $copy['button'],
					'url'        => '{order_url}',
					'background' => $brand['primary'],
				)
			);
			$blocks[] = self::block( 'additional', array( 'color' => $brand['text'] ) );
		} elseif ( 'account' === $kind ) {
			$blocks[] = self::block(
				'account',
				array(
					'intro'            => 'customer_reset_password' === $email_id
						? __( 'Confirm it is you, and we will let you choose a new password.', 'wpp-email-editor' )
						: __( 'Your username is {user_login}.', 'wpp-email-editor' ),
					'buttonText'       => 'customer_reset_password' === $email_id
						? __( 'Reset password', 'wpp-email-editor' )
						: __( 'Set your password', 'wpp-email-editor' ),
					'buttonBackground' => $brand['primary'],
				)
			);
		} else {
			$blocks[] = self::block( 'stock', array( 'color' => '#17191d' ) );
			$blocks[] = self::block(
				'button',
				array(
					'text'       => __( 'Edit product', 'wpp-email-editor' ),
					'url'        => '{order_url}',
					'background' => $brand['primary'],
				)
			);
		}

		$blocks[] = self::block(
			'footer',
			array(
				'html'  => $brand['footerText'],
				'color' => $brand['muted'],
			)
		);

		$design = array(
			'enabled'   => false,
			'subject'   => $copy['subject'],
			'preheader' => $copy['preheader'],
			'settings'  => $settings,
			'blocks'    => $blocks,
		);

		return apply_filters( 'wpp_ee_blueprint', WPP_EE_Sanitizer::design( $design ), $email_id, $brand );
	}

	/**
	 * A block with schema defaults, plus overrides.
	 *
	 * @param string $type      Block type.
	 * @param array  $overrides Prop overrides.
	 * @return array
	 */
	private static function block( $type, array $overrides ) {
		$def   = WPP_EE_Schema::block( $type );
		$props = $def ? WPP_EE_Schema::defaults_from( $def['props'] ) : array();
		$props = array_merge( $props, $overrides );
		return array(
			'id'    => 'b_' . substr( md5( $type . wp_json_encode( $overrides ) . wp_rand() ), 0, 8 ),
			'type'  => $type,
			'props' => $props,
		);
	}

	/**
	 * Subject, heading, and intro for a known email. Unknown emails get a calm generic.
	 *
	 * @param string $email_id Email id.
	 * @return array
	 */
	private static function copy( $email_id ) {
		$catalog = array(
			'customer_processing_order' => array(
				'subject'   => __( 'Your {site_title} order #{order_number} is confirmed', 'wpp-email-editor' ),
				'heading'   => __( 'We have your order', 'wpp-email-editor' ),
				'preheader' => __( 'Order #{order_number} is confirmed.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'Thank you. We have received order #{order_number} and we are getting it ready. This email is the receipt — the next one arrives when it ships.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'View your order', 'wpp-email-editor' ),
			),
			'customer_completed_order'  => array(
				'subject'   => __( 'Order #{order_number} is complete', 'wpp-email-editor' ),
				'heading'   => __( 'It is on the way', 'wpp-email-editor' ),
				'preheader' => __( 'Order #{order_number} is complete.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'Order #{order_number} is complete. We hope it is exactly what you wanted — and if it is not, reply to this email.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'View your order', 'wpp-email-editor' ),
			),
			'customer_on_hold_order'    => array(
				'subject'   => __( 'Order #{order_number} is on hold', 'wpp-email-editor' ),
				'heading'   => __( 'We need a moment', 'wpp-email-editor' ),
				'preheader' => __( 'Order #{order_number} is on hold.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'Order #{order_number} is on hold until payment is confirmed. We will email you as soon as it starts moving.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'View your order', 'wpp-email-editor' ),
			),
			'customer_refunded_order'   => array(
				'subject'   => __( 'Refund for order #{order_number}', 'wpp-email-editor' ),
				'heading'   => __( 'Your refund is on its way', 'wpp-email-editor' ),
				'preheader' => __( 'A refund was issued for order #{order_number}.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'We have refunded order #{order_number}. The amount below is what left our side.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'View the order', 'wpp-email-editor' ),
			),
			'customer_invoice'          => array(
				'subject'   => __( 'Invoice for order #{order_number}', 'wpp-email-editor' ),
				'heading'   => __( 'Your invoice', 'wpp-email-editor' ),
				'preheader' => __( 'Invoice for order #{order_number}.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'Here is the invoice for order #{order_number}. The details below match what we have on file.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'Pay or view the order', 'wpp-email-editor' ),
			),
			'customer_note'             => array(
				'subject'   => __( 'A note about order #{order_number}', 'wpp-email-editor' ),
				'heading'   => __( 'A note from us', 'wpp-email-editor' ),
				'preheader' => __( 'We added a note to order #{order_number}.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'We added a note to order #{order_number}. It is quoted below, followed by the order itself.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'View your order', 'wpp-email-editor' ),
			),
			'customer_failed_order'     => array(
				'subject'   => __( 'Payment failed for order #{order_number}', 'wpp-email-editor' ),
				'heading'   => __( 'The payment did not go through', 'wpp-email-editor' ),
				'preheader' => __( 'Order #{order_number} still needs payment.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'The payment for order #{order_number} failed. Nothing has shipped. You can try again from the order page.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'Try the payment again', 'wpp-email-editor' ),
			),
			'customer_cancelled_order'  => array(
				'subject'   => __( 'Order #{order_number} was cancelled', 'wpp-email-editor' ),
				'heading'   => __( 'This order was cancelled', 'wpp-email-editor' ),
				'preheader' => __( 'Order #{order_number} was cancelled.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'Order #{order_number} has been cancelled. If that was a surprise, reply to this email and we will look it up.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'View the order', 'wpp-email-editor' ),
			),
			'new_order'                 => array(
				'subject'   => __( 'New order #{order_number}', 'wpp-email-editor' ),
				'heading'   => __( 'New order', 'wpp-email-editor' ),
				'preheader' => __( '{customer_full_name} placed order #{order_number}.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'You have a new order from {customer_full_name} — #{order_number}, placed on {order_date}.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'Open in admin', 'wpp-email-editor' ),
			),
			'cancelled_order'           => array(
				'subject'   => __( 'Order #{order_number} was cancelled', 'wpp-email-editor' ),
				'heading'   => __( 'Order cancelled', 'wpp-email-editor' ),
				'preheader' => __( 'Order #{order_number} was cancelled.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Order #{order_number} from {customer_full_name} was cancelled.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'Open in admin', 'wpp-email-editor' ),
			),
			'failed_order'              => array(
				'subject'   => __( 'Order #{order_number} failed', 'wpp-email-editor' ),
				'heading'   => __( 'Payment failed', 'wpp-email-editor' ),
				'preheader' => __( 'Order #{order_number} failed.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Order #{order_number} from {customer_full_name} failed at payment. It has not been paid.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'Open in admin', 'wpp-email-editor' ),
			),
			'customer_new_account'      => array(
				'subject'   => __( 'Your {site_title} account', 'wpp-email-editor' ),
				'heading'   => __( 'Your account is ready', 'wpp-email-editor' ),
				'preheader' => __( 'An account was created for you at {site_title}.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'An account has been created for you at {site_title}. Set a password and you can see your orders any time.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'Set your password', 'wpp-email-editor' ),
			),
			'customer_reset_password'   => array(
				'subject'   => __( 'Reset your {site_title} password', 'wpp-email-editor' ),
				'heading'   => __( 'Reset your password', 'wpp-email-editor' ),
				'preheader' => __( 'A password reset was requested for your account.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'Someone asked to reset the password for {user_login}. If that was you, use the button. If it was not, you can ignore this email.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'Reset password', 'wpp-email-editor' ),
			),
			'low_stock'                 => array(
				'subject'   => __( 'Low stock: {product_name}', 'wpp-email-editor' ),
				'heading'   => __( 'Stock is running low', 'wpp-email-editor' ),
				'preheader' => __( '{product_name} is down to {stock_quantity}.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( '{product_name} is almost gone. There are {stock_quantity} left.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'Edit product', 'wpp-email-editor' ),
			),
			'no_stock'                  => array(
				'subject'   => __( 'Out of stock: {product_name}', 'wpp-email-editor' ),
				'heading'   => __( 'This product is out of stock', 'wpp-email-editor' ),
				'preheader' => __( '{product_name} is out of stock.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( '{product_name} has no stock left. Customers cannot buy it until you restock.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'Edit product', 'wpp-email-editor' ),
			),
			'backorder'                 => array(
				'subject'   => __( 'Backorder: {product_name}', 'wpp-email-editor' ),
				'heading'   => __( 'A product was backordered', 'wpp-email-editor' ),
				'preheader' => __( '{product_name} was backordered.', 'wpp-email-editor' ),
				'html'      => '<p>' . __( '{product_name} was just ordered on backorder. Current stock: {stock_quantity}.', 'wpp-email-editor' ) . '</p>',
				'button'    => __( 'Edit product', 'wpp-email-editor' ),
			),
		);

		if ( isset( $catalog[ $email_id ] ) ) {
			return $catalog[ $email_id ];
		}

		return array(
			'subject'   => __( '{site_title}: {order_number}', 'wpp-email-editor' ),
			'heading'   => __( 'An update from {site_title}', 'wpp-email-editor' ),
			'preheader' => __( 'A message about your order.', 'wpp-email-editor' ),
			'html'      => '<p>' . __( 'Hi {customer_first_name},', 'wpp-email-editor' ) . '</p><p>' . __( 'Here is an update from {site_title}. The details we have are below.', 'wpp-email-editor' ) . '</p>',
			'button'    => __( 'View your order', 'wpp-email-editor' ),
		);
	}
}
