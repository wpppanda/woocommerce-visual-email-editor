<?php
/**
 * Data available while an email is rendered.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps a real order, a product, or sample data behind one API.
 */
class WPP_EE_Context {

	/**
	 * WooCommerce email, when we are inside a real send.
	 *
	 * @var WC_Email|null
	 */
	public $email = null;

	/**
	 * Email id.
	 *
	 * @var string
	 */
	public $email_id = '';

	/**
	 * Order, when the email has one.
	 *
	 * @var WC_Order|null
	 */
	public $order = null;

	/**
	 * Product, for stock emails.
	 *
	 * @var WC_Product|null
	 */
	public $product = null;

	/**
	 * User, for account emails.
	 *
	 * @var WP_User|null
	 */
	public $user = null;

	/**
	 * Preview and sample renders show placeholders instead of hiding empty blocks.
	 *
	 * @var bool
	 */
	public $is_preview = false;

	/**
	 * True when the numbers are invented.
	 *
	 * @var bool
	 */
	public $is_sample = false;

	/**
	 * Sent to the shop, not the customer.
	 *
	 * @var bool
	 */
	public $sent_to_admin = false;

	/**
	 * Context from a WooCommerce email that is about to be sent.
	 *
	 * @param WC_Email $email Email.
	 * @return self
	 */
	public static function from_email( WC_Email $email ) {
		$ctx                 = new self();
		$ctx->email          = $email;
		$ctx->email_id       = WPP_EE_Sanitizer::email_id( $email->id );
		$ctx->sent_to_admin  = ! $email->is_customer_email();
		$object              = isset( $email->object ) ? $email->object : null;

		if ( $object instanceof WC_Order ) {
			$ctx->order = $object;
		} elseif ( $object instanceof WC_Product ) {
			$ctx->product = $object;
		} elseif ( $object instanceof WP_User ) {
			$ctx->user = $object;
		}

		return $ctx;
	}

	/**
	 * Context for the admin preview. A real order is used when one is chosen.
	 *
	 * @param string $email_id Email id.
	 * @param int    $order_id Optional order id.
	 * @return self
	 */
	public static function preview( $email_id, $order_id = 0 ) {
		$email_id = WPP_EE_Sanitizer::email_id( $email_id );
		$kind     = WPP_EE_Blueprints::kind( $email_id );

		if ( 'order' === $kind && $order_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$ctx                = new self();
				$ctx->email_id      = $email_id;
				$ctx->order         = $order;
				$ctx->is_preview    = true;
				$ctx->sent_to_admin = in_array( $email_id, array( 'new_order', 'cancelled_order', 'failed_order' ), true );
				$ctx->email         = WPP_EE_Registry::instance( $email_id );
				return $ctx;
			}
		}

		$ctx = self::sample( $email_id );
		if ( 'stock' === $kind && function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products(
				array(
					'status' => 'publish',
					'limit'  => 1,
				)
			);
			if ( ! empty( $products[0] ) && $products[0] instanceof WC_Product ) {
				$ctx->product   = $products[0];
				$ctx->is_sample = false;
			}
		}
		return $ctx;
	}

	/**
	 * Invented but plausible data, so an empty shop can still be designed.
	 *
	 * @param string $email_id Email id.
	 * @return self
	 */
	public static function sample( $email_id ) {
		$ctx               = new self();
		$ctx->email_id     = WPP_EE_Sanitizer::email_id( $email_id );
		$ctx->is_preview   = true;
		$ctx->is_sample    = true;
		$ctx->sent_to_admin = in_array( $ctx->email_id, array( 'new_order', 'cancelled_order', 'failed_order', 'low_stock', 'no_stock', 'backorder' ), true );
		return $ctx;
	}

	/**
	 * Sample display values for the visual editor canvas.
	 *
	 * @return array
	 */
	public static function canvas_sample() {
		$ctx = self::sample( 'customer_processing_order' );
		return array(
			'siteTitle'     => $ctx->site_title(),
			'orderNumber'   => '1024',
			'orderDate'     => $ctx->format_date( null ),
			'firstName'     => __( 'Anna', 'wpp-email-editor' ),
			'lastName'      => __( 'Sokolova', 'wpp-email-editor' ),
			'fullName'      => __( 'Anna Sokolova', 'wpp-email-editor' ),
			'email'         => 'anna@example.com',
			'phone'         => '+7 900 000-00-00',
			'payment'       => __( 'Card', 'wpp-email-editor' ),
			'shipping'      => __( 'Courier', 'wpp-email-editor' ),
			'total'         => $ctx->money( 6000 ),
			'subtotal'      => $ctx->money( 5400 ),
			'shippingTotal' => $ctx->money( 600 ),
			'items'         => array(
				array(
					'name'  => __( 'Ceramic kettle', 'wpp-email-editor' ),
					'qty'   => 1,
					'total' => $ctx->money( 4200 ),
					'meta'  => __( 'Color: sand', 'wpp-email-editor' ),
				),
				array(
					'name'  => __( 'Linen towel', 'wpp-email-editor' ),
					'qty'   => 2,
					'total' => $ctx->money( 1800 ),
					'meta'  => '',
				),
			),
			'billing'       => __( 'Anna Sokolova', 'wpp-email-editor' ) . '<br>' . __( 'Tverskaya 1', 'wpp-email-editor' ) . '<br>' . __( 'Moscow', 'wpp-email-editor' ),
			'shippingAddr'  => __( 'Anna Sokolova', 'wpp-email-editor' ) . '<br>' . __( 'Tverskaya 1', 'wpp-email-editor' ) . '<br>' . __( 'Moscow', 'wpp-email-editor' ),
			'note'          => __( 'Please leave the parcel with the front desk.', 'wpp-email-editor' ),
			'username'      => 'anna',
			'productName'   => __( 'Ceramic kettle', 'wpp-email-editor' ),
			'stock'         => '2',
			'refund'        => $ctx->money( 1800 ),
			'products'      => array(
				array(
					'name'  => __( 'Ceramic kettle', 'wpp-email-editor' ),
					'price' => $ctx->money( 4200 ),
				),
				array(
					'name'  => __( 'Linen towel', 'wpp-email-editor' ),
					'price' => $ctx->money( 900 ),
				),
				array(
					'name'  => __( 'Matcha bowl', 'wpp-email-editor' ),
					'price' => $ctx->money( 2400 ),
				),
			),
		);
	}

	/**
	 * Store name.
	 *
	 * @return string
	 */
	public function site_title() {
		if ( function_exists( 'get_bloginfo' ) ) {
			$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			if ( $name ) {
				return $name;
			}
		}
		return 'WP Panda';
	}

	/**
	 * Replace merge tags in HTML. Values are escaped, except address HTML.
	 *
	 * @param string $html HTML that may contain {tags}.
	 * @return string
	 */
	public function replace_html( $html ) {
		$html = (string) $html;
		if ( '' === $html || false === strpos( $html, '{' ) ) {
			return $html;
		}
		$map = $this->token_map( true );
		$map = apply_filters( 'wpp_ee_placeholder_map', $map, $this );
		return $this->format_remaining( strtr( $html, $map ) );
	}

	/**
	 * Replace merge tags in a plain subject or label.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	public function replace_plain( $text ) {
		$text = (string) $text;
		if ( '' === $text ) {
			return '';
		}
		$map  = $this->token_map( false );
		$map  = apply_filters( 'wpp_ee_placeholder_map', $map, $this );
		$text = $this->format_remaining( strtr( $text, $map ) );
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
	}

	/**
	 * Let WooCommerce fill placeholders we did not replace, and only when some remain.
	 *
	 * @param string $text Text that may still contain {tags}.
	 * @return string
	 */
	private function format_remaining( $text ) {
		if ( false === strpos( $text, '{' ) ) {
			return $text;
		}
		if ( $this->email instanceof WC_Email && method_exists( $this->email, 'format_string' ) ) {
			return $this->email->format_string( $text );
		}
		return $text;
	}

	/**
	 * Order line items shaped for the table block.
	 *
	 * @param bool $with_meta Include item meta HTML.
	 * @param bool $with_sku  Include SKU.
	 * @return array
	 */
	public function items( $with_meta = true, $with_sku = false ) {
		if ( $this->order instanceof WC_Order ) {
			$rows = array();
			foreach ( $this->order->get_items() as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}
				$product = $item->get_product();
				$image   = '';
				if ( $product instanceof WC_Product ) {
					$image_id = $product->get_image_id();
					$image    = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
				}
				$meta = '';
				if ( $with_meta && function_exists( 'wc_display_item_meta' ) ) {
					$meta = wc_display_item_meta(
						$item,
						array(
							'before'    => '',
							'after'     => '',
							'separator' => '<br>',
							'echo'      => false,
							'autop'     => false,
						)
					);
					$meta = wp_kses_post( $meta );
				}
				$rows[] = array(
					'name'  => $item->get_name(),
					'qty'   => $item->get_quantity(),
					'total' => $this->order->get_formatted_line_subtotal( $item ),
					'meta'  => $meta,
					'sku'   => ( $with_sku && $product ) ? $product->get_sku() : '',
					'image' => $image,
				);
			}
			return $rows;
		}

		if ( ! $this->is_preview ) {
			return array();
		}

		$sample = self::canvas_sample();
		$rows   = array();
		foreach ( $sample['items'] as $item ) {
			$rows[] = array(
				'name'  => $item['name'],
				'qty'   => $item['qty'],
				'total' => $item['total'],
				'meta'  => $with_meta ? esc_html( $item['meta'] ) : '',
				'sku'   => $with_sku ? 'SKU-1024' : '',
				'image' => '',
			);
		}
		return $rows;
	}

	/**
	 * Totals rows. The last grand-total row is flagged.
	 *
	 * @return array
	 */
	public function totals() {
		if ( $this->order instanceof WC_Order ) {
			$rows = array();
			foreach ( $this->order->get_order_item_totals() as $key => $total ) {
				$rows[] = array(
					'label' => wp_strip_all_tags( $total['label'] ),
					'value' => wp_kses_post( $total['value'] ),
					'grand' => 'order_total' === $key,
				);
			}
			return $rows;
		}
		if ( ! $this->is_preview ) {
			return array();
		}
		$sample = self::canvas_sample();
		return array(
			array(
				'label' => __( 'Subtotal', 'wpp-email-editor' ),
				'value' => esc_html( $sample['subtotal'] ),
				'grand' => false,
			),
			array(
				'label' => __( 'Shipping', 'wpp-email-editor' ),
				'value' => esc_html( $sample['shippingTotal'] ),
				'grand' => false,
			),
			array(
				'label' => __( 'Total', 'wpp-email-editor' ),
				'value' => esc_html( $sample['total'] ),
				'grand' => true,
			),
		);
	}

	/**
	 * Billing address HTML.
	 *
	 * @return string
	 */
	public function billing_html() {
		if ( $this->order instanceof WC_Order ) {
			return wp_kses_post( $this->order->get_formatted_billing_address() );
		}
		return $this->is_preview ? self::canvas_sample()['billing'] : '';
	}

	/**
	 * Shipping address HTML.
	 *
	 * @return string
	 */
	public function shipping_html() {
		if ( $this->order instanceof WC_Order ) {
			if ( ! $this->order->needs_shipping_address() ) {
				return '';
			}
			return wp_kses_post( $this->order->get_formatted_shipping_address() );
		}
		return $this->is_preview ? self::canvas_sample()['shippingAddr'] : '';
	}

	/**
	 * Customer or store note.
	 *
	 * @return string
	 */
	public function note() {
		if ( $this->email && ! empty( $this->email->customer_note ) ) {
			return (string) $this->email->customer_note;
		}
		if ( $this->order instanceof WC_Order ) {
			return (string) $this->order->get_customer_note();
		}
		return $this->is_preview ? self::canvas_sample()['note'] : '';
	}

	/**
	 * Downloadable files.
	 *
	 * @return array
	 */
	public function downloads() {
		if ( $this->order instanceof WC_Order ) {
			$rows = array();
			foreach ( $this->order->get_downloadable_items() as $download ) {
				$rows[] = array(
					'name' => isset( $download['download_name'] ) ? $download['download_name'] : $download['product_name'],
					'url'  => $download['download_url'],
				);
			}
			return $rows;
		}
		if ( ! $this->is_preview ) {
			return array();
		}
		return array(
			array(
				'name' => __( 'Lookbook (PDF)', 'wpp-email-editor' ),
				'url'  => '#',
			),
		);
	}

	/**
	 * Account action details.
	 *
	 * @return array
	 */
	public function account() {
		$username = '';
		$url      = '';

		if ( $this->email ) {
			if ( ! empty( $this->email->user_login ) ) {
				$username = (string) $this->email->user_login;
			}
			if ( ! empty( $this->email->set_password_url ) ) {
				$url = (string) $this->email->set_password_url;
			}
			if ( '' === $url && ! empty( $this->email->reset_key ) ) {
				$url = $this->reset_url( (string) $this->email->reset_key, $username );
			}
		}
		if ( '' === $username && $this->user instanceof WP_User ) {
			$username = $this->user->user_login;
		}
		if ( '' === $url && $this->is_preview ) {
			$url      = $this->page_url( 'myaccount' );
			$username = $username ? $username : 'anna';
		}
		if ( '' === $url ) {
			$url = $this->page_url( 'myaccount' );
		}

		return array(
			'username' => $username,
			'url'      => $url,
		);
	}

	/**
	 * Stock notice details.
	 *
	 * @return array
	 */
	public function stock() {
		if ( $this->product instanceof WC_Product ) {
			$qty = $this->product->get_stock_quantity();
			return array(
				'name' => $this->product->get_name(),
				'sku'  => $this->product->get_sku(),
				'qty'  => null === $qty ? '0' : (string) $qty,
				'url'  => get_edit_post_link( $this->product->get_id(), 'raw' ) ? get_edit_post_link( $this->product->get_id(), 'raw' ) : admin_url( 'post.php?post=' . $this->product->get_id() . '&action=edit' ),
			);
		}
		$sample = self::canvas_sample();
		return array(
			'name' => $sample['productName'],
			'sku'  => 'KETTLE-01',
			'qty'  => $sample['stock'],
			'url'  => $this->is_preview ? '#' : '',
		);
	}

	/**
	 * Products for the products block.
	 *
	 * @param string $source latest|featured|ids.
	 * @param string $ids    Comma-separated ids.
	 * @param int    $count  How many.
	 * @return array
	 */
	public function products( $source, $ids, $count ) {
		$count = max( 1, min( 4, (int) $count ) );
		$rows  = array();

		if ( function_exists( 'wc_get_products' ) && ! $this->is_sample ) {
			$args = array(
				'status'  => 'publish',
				'limit'   => $count,
				'orderby' => 'date',
				'order'   => 'DESC',
			);
			if ( 'featured' === $source ) {
				$args['featured'] = true;
			}
			if ( 'ids' === $source ) {
				$parsed = array_filter( array_map( 'absint', explode( ',', (string) $ids ) ) );
				if ( $parsed ) {
					$args['include'] = $parsed;
					$args['limit']   = count( $parsed );
				}
			}
			foreach ( wc_get_products( $args ) as $product ) {
				if ( ! $product instanceof WC_Product ) {
					continue;
				}
				$image_id = $product->get_image_id();
				$rows[]   = array(
					'name'  => $product->get_name(),
					'price' => $product->get_price_html(),
					'url'   => $product->get_permalink(),
					'image' => $image_id ? (string) wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '',
				);
			}
		}

		if ( ! $rows && $this->is_preview ) {
			foreach ( array_slice( self::canvas_sample()['products'], 0, $count ) as $product ) {
				$rows[] = array(
					'name'  => $product['name'],
					'price' => $product['price'],
					'url'   => $this->page_url( 'shop' ),
					'image' => '',
				);
			}
		}

		return $rows;
	}

	/**
	 * Raw token values. HTML mode escapes them.
	 *
	 * @param bool $escape Escape for HTML insertion.
	 * @return array
	 */
	private function token_map( $escape ) {
		$billing  = $this->billing_html();
		$shipping = $this->shipping_html();
		$account  = $this->account();
		$stock    = $this->stock();
		$order    = $this->order;

		$raw = array(
			'{site_title}'         => $this->site_title(),
			'{site_url}'           => wp_parse_url( home_url(), PHP_URL_HOST ),
			'{site_address}'       => wp_parse_url( home_url(), PHP_URL_HOST ),
			'{store_email}'        => (string) get_option( 'woocommerce_email_from_address', get_option( 'admin_email' ) ),
			'{store_address}'      => $this->store_address(),
			'{order_number}'       => $order instanceof WC_Order ? $order->get_order_number() : '1024',
			'{order_date}'         => ( $order instanceof WC_Order && $order->get_date_created() ) ? wc_format_datetime( $order->get_date_created() ) : $this->format_date( null ),
			'{order_total}'        => $order instanceof WC_Order ? wp_strip_all_tags( $order->get_formatted_order_total() ) : self::canvas_sample()['total'],
			'{order_url}'          => $this->order_url(),
			'{payment_method}'     => $order instanceof WC_Order ? $order->get_payment_method_title() : __( 'Card', 'wpp-email-editor' ),
			'{shipping_method}'    => $order instanceof WC_Order ? $order->get_shipping_method() : __( 'Courier', 'wpp-email-editor' ),
			'{customer_first_name}' => $this->first_name(),
			'{customer_last_name}' => $this->last_name(),
			'{customer_full_name}' => trim( $this->first_name() . ' ' . $this->last_name() ),
			'{customer_email}'     => $this->customer_email(),
			'{customer_phone}'     => $order instanceof WC_Order ? $order->get_billing_phone() : '+7 900 000-00-00',
			'{user_login}'         => $account['username'],
			'{username}'           => $account['username'],
			'{password_reset_url}' => $account['url'],
			'{set_password_url}'   => $account['url'],
			'{my_account_url}'     => $this->page_url( 'myaccount' ),
			'{shop_url}'           => $this->page_url( 'shop' ),
			'{product_name}'       => $stock['name'],
			'{stock_quantity}'     => $stock['qty'],
			'{refund_amount}'      => $this->refund_amount(),
			'{billing_address}'    => $escape ? $billing : wp_strip_all_tags( str_replace( '<br>', "\n", $billing ) ),
			'{shipping_address}'   => $escape ? $shipping : wp_strip_all_tags( str_replace( '<br>', "\n", $shipping ) ),
		);

		$map = array();
		foreach ( $raw as $tag => $value ) {
			$value = (string) $value;
			if ( $escape && ! in_array( $tag, array( '{billing_address}', '{shipping_address}' ), true ) ) {
				$value = esc_html( $value );
			}
			$map[ $tag ] = $value;
		}
		return $map;
	}

	/**
	 * Customer first name.
	 *
	 * @return string
	 */
	private function first_name() {
		if ( $this->order instanceof WC_Order ) {
			$name = $this->order->get_billing_first_name();
			if ( $name ) {
				return $name;
			}
		}
		if ( $this->user instanceof WP_User ) {
			return $this->user->first_name ? $this->user->first_name : $this->user->display_name;
		}
		return $this->is_preview ? __( 'Anna', 'wpp-email-editor' ) : '';
	}

	/**
	 * Customer last name.
	 *
	 * @return string
	 */
	private function last_name() {
		if ( $this->order instanceof WC_Order && $this->order->get_billing_last_name() ) {
			return $this->order->get_billing_last_name();
		}
		if ( $this->user instanceof WP_User ) {
			return (string) $this->user->last_name;
		}
		return $this->is_preview ? __( 'Sokolova', 'wpp-email-editor' ) : '';
	}

	/**
	 * Customer email.
	 *
	 * @return string
	 */
	private function customer_email() {
		if ( $this->order instanceof WC_Order ) {
			return $this->order->get_billing_email();
		}
		if ( $this->user instanceof WP_User ) {
			return $this->user->user_email;
		}
		if ( $this->email && ! empty( $this->email->user_email ) ) {
			return (string) $this->email->user_email;
		}
		return $this->is_preview ? 'anna@example.com' : '';
	}

	/**
	 * Where the order button should go.
	 *
	 * @return string
	 */
	private function order_url() {
		if ( 'stock' === WPP_EE_Blueprints::kind( $this->email_id ) ) {
			return $this->stock()['url'];
		}
		if ( $this->order instanceof WC_Order ) {
			if ( $this->sent_to_admin && method_exists( $this->order, 'get_edit_order_url' ) ) {
				return $this->order->get_edit_order_url();
			}
			return $this->order->get_view_order_url();
		}
		return $this->is_preview ? $this->page_url( 'myaccount' ) : '';
	}

	/**
	 * Refund amount, plain.
	 *
	 * @return string
	 */
	private function refund_amount() {
		if ( $this->email && isset( $this->email->refund ) && $this->email->refund instanceof WC_Order_Refund ) {
			$currency = $this->order instanceof WC_Order ? array( 'currency' => $this->order->get_currency() ) : array();
			return wp_strip_all_tags( wc_price( $this->email->refund->get_amount(), $currency ) );
		}
		if ( $this->order instanceof WC_Order && $this->order->get_total_refunded() > 0 ) {
			return wp_strip_all_tags( wc_price( $this->order->get_total_refunded(), array( 'currency' => $this->order->get_currency() ) ) );
		}
		return $this->is_preview ? self::canvas_sample()['refund'] : '';
	}

	/**
	 * Store address on one line.
	 *
	 * @return string
	 */
	private function store_address() {
		if ( ! function_exists( 'WC' ) ) {
			return '';
		}
		$parts = array(
			get_option( 'woocommerce_store_address' ),
			get_option( 'woocommerce_store_address_2' ),
			get_option( 'woocommerce_store_city' ),
			get_option( 'woocommerce_store_postcode' ),
		);
		$parts = array_filter( array_map( 'trim', $parts ) );
		if ( ! $parts ) {
			return wp_parse_url( home_url(), PHP_URL_HOST );
		}
		return implode( ', ', $parts );
	}

	/**
	 * A WooCommerce page URL.
	 *
	 * @param string $page myaccount|shop.
	 * @return string
	 */
	private function page_url( $page ) {
		if ( 'shop' === $page && function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'shop' );
			return $url ? $url : home_url( '/' );
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );
			return $url ? $url : home_url( '/' );
		}
		return home_url( '/' );
	}

	/**
	 * Password reset URL matching WooCommerce's own template.
	 *
	 * @param string $key      Reset key.
	 * @param string $login    User login.
	 * @return string
	 */
	private function reset_url( $key, $login ) {
		$user_id = $this->user instanceof WP_User ? $this->user->ID : 0;
		if ( ! $user_id && $login ) {
			$user = get_user_by( 'login', $login );
			$user_id = $user ? $user->ID : 0;
		}
		$base = function_exists( 'wc_get_endpoint_url' )
			? wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
			: home_url( '/' );
		return add_query_arg(
			array(
				'key'   => $key,
				'id'    => $user_id,
				'login' => rawurlencode( $login ),
			),
			$base
		);
	}

	/**
	 * Format a price for sample data.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private function money( $amount ) {
		if ( function_exists( 'wc_price' ) ) {
			return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
		}
		return (string) $amount;
	}

	/**
	 * Today's date in the site format, used for samples.
	 *
	 * @param mixed $unused Unused.
	 * @return string
	 */
	private function format_date( $unused ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		unset( $unused );
		return function_exists( 'wp_date' ) ? wp_date( get_option( 'date_format' ) ) : gmdate( 'Y-m-d' );
	}
}
