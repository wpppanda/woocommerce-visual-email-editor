<?php
/**
 * WooCommerce send integration.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces a WooCommerce email at the last moment before wp_mail(),
 * so block-email and classic templates both yield to an enabled design.
 */
class WPP_EE_Mailer {

	/**
	 * Email captured from the subject filter or header action.
	 *
	 * @var WC_Email|null
	 */
	private static $current = null;

	/**
	 * Plain alternative for multipart sends.
	 *
	 * @var string
	 */
	private static $plain = '';

	/**
	 * True once this request has already swapped the message.
	 *
	 * @var bool
	 */
	private static $replaced = false;

	/**
	 * Hook the mailer.
	 */
	public static function init() {
		add_action( 'woocommerce_email', array( __CLASS__, 'register_subjects' ) );
		add_action( 'woocommerce_email_header', array( __CLASS__, 'capture_header' ), 1, 2 );
		add_filter( 'woocommerce_mail_callback_params', array( __CLASS__, 'replace_params' ), 20, 2 );
		add_filter( 'wp_mail', array( __CLASS__, 'replace_wp_mail' ), 20 );
		add_action( 'phpmailer_init', array( __CLASS__, 'alt_body' ) );
		add_action( 'woocommerce_email_sent', array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * Subject filters also tell us which email is about to render.
	 *
	 * @param WC_Emails $mailer Mailer.
	 */
	public static function register_subjects( $mailer ) {
		if ( ! is_object( $mailer ) || ! method_exists( $mailer, 'get_emails' ) ) {
			return;
		}
		foreach ( $mailer->get_emails() as $email ) {
			if ( ! $email instanceof WC_Email || empty( $email->id ) ) {
				continue;
			}
			add_filter( 'woocommerce_email_subject_' . $email->id, array( __CLASS__, 'filter_subject' ), 20, 3 );
		}
	}

	/**
	 * Remember the email from the header template, for older send paths.
	 *
	 * @param string        $heading Heading.
	 * @param WC_Email|null $email   Email.
	 */
	public static function capture_header( $heading, $email = null ) {
		unset( $heading );
		if ( $email instanceof WC_Email ) {
			self::$current = $email;
		}
	}

	/**
	 * Swap the subject when the design supplies one.
	 *
	 * @param string        $subject Default subject, already formatted by WooCommerce.
	 * @param mixed         $object  Email object.
	 * @param WC_Email|null $email   Email.
	 * @return string
	 */
	public static function filter_subject( $subject, $object = null, $email = null ) {
		unset( $object );
		if ( ! $email instanceof WC_Email ) {
			return $subject;
		}
		self::$current = $email;
		$design        = WPP_EE_Repository::enabled( $email->id );
		if ( ! $design || '' === $design['subject'] ) {
			return $subject;
		}
		return WPP_EE_Context::from_email( $email )->replace_plain( $design['subject'] );
	}

	/**
	 * Replace the message argument passed to wp_mail().
	 *
	 * @param array         $params To, subject, message, headers, attachments.
	 * @param WC_Email|null $email  Email.
	 * @return array
	 */
	public static function replace_params( $params, $email = null ) {
		if ( self::$replaced ) {
			return $params;
		}
		if ( ! $email instanceof WC_Email ) {
			$email = self::$current;
		}
		if ( ! $email instanceof WC_Email || ! is_array( $params ) || ! isset( $params[2] ) ) {
			return $params;
		}
		$rendered = self::render_for( $email );
		if ( null === $rendered ) {
			return $params;
		}
		$params[2]      = $rendered;
		self::$replaced = true;
		return $params;
	}

	/**
	 * Fallback for WooCommerce versions that send through wp_mail()
	 * without the callback-params filter.
	 *
	 * @param array $args wp_mail arguments.
	 * @return array
	 */
	public static function replace_wp_mail( $args ) {
		if ( self::$replaced || ! self::$current instanceof WC_Email || ! is_array( $args ) ) {
			return $args;
		}
		$rendered = self::render_for( self::$current );
		if ( null === $rendered ) {
			return $args;
		}
		$args['message'] = $rendered;
		self::$replaced  = true;
		return $args;
	}

	/**
	 * Render an enabled design, or null to keep WooCommerce's own template.
	 *
	 * @param WC_Email $email Email.
	 * @return string|null
	 */
	private static function render_for( WC_Email $email ) {
		$design = WPP_EE_Repository::enabled( $email->id );
		if ( ! $design ) {
			return null;
		}
		try {
			$context     = WPP_EE_Context::from_email( $email );
			$html        = WPP_EE_Renderer::html( $design, $context );
			self::$plain = WPP_EE_Renderer::plain( $design, $context );
			$type        = method_exists( $email, 'get_email_type' ) ? $email->get_email_type() : 'html';
			if ( 'plain' === $type ) {
				$plain       = self::$plain;
				self::$plain = '';
				return $plain;
			}
			return $html;
		} catch ( \Throwable $e ) {
			self::log( $e->getMessage() );
			return null;
		}
	}

	/**
	 * Keep the plain alternative in step with the HTML we just injected.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer.
	 */
	public static function alt_body( $phpmailer ) {
		if ( '' === self::$plain || ! is_object( $phpmailer ) ) {
			return;
		}
		$content_type = isset( $phpmailer->ContentType ) ? $phpmailer->ContentType : '';
		if ( 'text/plain' === $content_type ) {
			return;
		}
		$phpmailer->AltBody = self::$plain; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Do not leak the alternative body into the next email.
	 */
	public static function cleanup() {
		self::$plain    = '';
		self::$current  = null;
		self::$replaced = false;
	}

	/**
	 * Log a render failure without taking down the original email.
	 *
	 * @param string $message Error.
	 */
	private static function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'wpp-email-editor' ) );
		}
	}
}
