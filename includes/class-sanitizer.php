<?php
/**
 * Input sanitizing.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema-driven sanitizer. Unknown blocks and props are dropped.
 */
class WPP_EE_Sanitizer {

	/**
	 * Clean an email id so it can be a key and a URL segment.
	 *
	 * @param string $email_id Raw id.
	 * @return string
	 */
	public static function email_id( $email_id ) {
		$email_id = strtolower( (string) $email_id );
		$email_id = preg_replace( '/[^a-z0-9_-]/', '', $email_id );
		return substr( (string) $email_id, 0, 80 );
	}

	/**
	 * Sanitize a full design document.
	 *
	 * @param array $input Raw design.
	 * @return array
	 */
	public static function design( array $input ) {
		$settings_schema = WPP_EE_Schema::email_settings();
		$settings_in     = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

		return array(
			'enabled'   => ! empty( $input['enabled'] ),
			'subject'   => self::plain( isset( $input['subject'] ) ? $input['subject'] : '', 180 ),
			'preheader' => self::plain( isset( $input['preheader'] ) ? $input['preheader'] : '', 180 ),
			'settings'  => self::props( $settings_schema, $settings_in, true ),
			'blocks'    => self::blocks( isset( $input['blocks'] ) && is_array( $input['blocks'] ) ? $input['blocks'] : array() ),
		);
	}

	/**
	 * Sanitize the brand kit.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function settings( array $input ) {
		return self::props( WPP_EE_Schema::global_settings(), $input, true );
	}

	/**
	 * Sanitize a block list.
	 *
	 * @param array $blocks Raw blocks.
	 * @return array
	 */
	public static function blocks( array $blocks ) {
		$clean = array();
		$seen  = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || empty( $block['type'] ) ) {
				continue;
			}
			$type = sanitize_key( $block['type'] );
			$def  = WPP_EE_Schema::block( $type );
			if ( ! $def ) {
				continue;
			}
			$id = isset( $block['id'] ) ? preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $block['id'] ) : '';
			$id = substr( (string) $id, 0, 32 );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				$id = 'b_' . wp_generate_password( 8, false, false );
			}
			$seen[ $id ] = true;
			$props_in    = isset( $block['props'] ) && is_array( $block['props'] ) ? $block['props'] : array();
			$clean[]     = array(
				'id'    => $id,
				'type'  => $type,
				'props' => self::props( $def['props'], $props_in, true ),
			);
			if ( count( $clean ) >= 80 ) {
				break;
			}
		}
		return $clean;
	}

	/**
	 * Sanitize a prop map against a schema.
	 *
	 * @param array $schema  Prop schema.
	 * @param array $input   Raw values.
	 * @param bool  $fill    Fill missing keys with defaults.
	 * @return array
	 */
	public static function props( array $schema, array $input, $fill = true ) {
		$out = array();
		foreach ( $schema as $key => $rule ) {
			$value = array_key_exists( $key, $input ) ? $input[ $key ] : ( $fill && isset( $rule['default'] ) ? $rule['default'] : null );
			if ( null === $value && ! $fill ) {
				continue;
			}
			$out[ $key ] = self::prop( $rule, $value );
		}
		return $out;
	}

	/**
	 * Sanitize one value.
	 *
	 * @param array $rule  Schema rule.
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public static function prop( array $rule, $value ) {
		$type = isset( $rule['type'] ) ? $rule['type'] : 'text';
		switch ( $type ) {
			case 'bool':
				return (bool) $value;
			case 'int':
				$number = (int) $value;
				if ( isset( $rule['min'] ) ) {
					$number = max( (int) $rule['min'], $number );
				}
				if ( isset( $rule['max'] ) ) {
					$number = min( (int) $rule['max'], $number );
				}
				return $number;
			case 'color':
				return self::color( $value, isset( $rule['default'] ) ? $rule['default'] : '#000000' );
			case 'enum':
				$values = isset( $rule['values'] ) && is_array( $rule['values'] ) ? $rule['values'] : array();
				$value  = (string) $value;
				return in_array( $value, $values, true ) ? $value : ( isset( $rule['default'] ) ? $rule['default'] : '' );
			case 'font':
				$fonts = WPP_EE_Schema::fonts();
				return in_array( (string) $value, $fonts, true ) ? (string) $value : ( isset( $rule['default'] ) ? $rule['default'] : 'Arial, Helvetica, sans-serif' );
			case 'url':
				return self::url( $value );
			case 'html':
				return self::html( $value, false );
			case 'html_rich':
				return self::html( $value, true );
			case 'textarea':
				return self::plain( $value, 2000 );
			case 'social':
				return self::social( $value );
			case 'text':
			default:
				return self::plain( $value, 300 );
		}
	}

	/**
	 * Single-line plain text that may contain merge tags.
	 *
	 * @param mixed $value  Raw value.
	 * @param int   $length Max length.
	 * @return string
	 */
	public static function plain( $value, $length = 300 ) {
		$value = sanitize_textarea_field( (string) $value );
		$value = str_replace( array( "\r", "\n" ), ' ', $value );
		return trim( substr( $value, 0, $length ) );
	}

	/**
	 * Hex color, or the fallback.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Fallback hex.
	 * @return string
	 */
	public static function color( $value, $fallback ) {
		$value = strtolower( trim( (string) $value ) );
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ) {
			return $value;
		}
		return self::color( $fallback, '#000000' );
	}

	/**
	 * URL, or a merge tag that will become a URL at send time.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function url( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^\{[a-z0-9_]+\}$/', $value ) ) {
			return $value;
		}
		$url = esc_url_raw( $value );
		if ( $url && preg_match( '#^(https?:|mailto:|tel:)#i', $url ) ) {
			return $url;
		}
		return '';
	}

	/**
	 * Limited HTML safe to place in an email body.
	 *
	 * @param mixed $value Raw HTML.
	 * @param bool  $rich  Allow tables and layout tags.
	 * @return string
	 */
	public static function html( $value, $rich = false ) {
		$value = (string) $value;
		$value = preg_replace( '#<(script|iframe|object|embed|form|link|meta)\b[^>]*>.*?</\1>#is', '', $value );
		$value = preg_replace( '#<(script|iframe|object|embed|form|link|meta)\b[^>]*/?>#is', '', (string) $value );
		$html  = wp_kses( $value, self::allowed_html( $rich ) );
		return self::strip_dangerous_styles( $html );
	}

	/**
	 * Social URL map.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function social( $value ) {
		$out = array();
		if ( ! is_array( $value ) ) {
			return $out;
		}
		foreach ( WPP_EE_Schema::social_networks() as $network ) {
			$out[ $network ] = isset( $value[ $network ] ) ? self::url( $value[ $network ] ) : '';
		}
		return $out;
	}

	/**
	 * KSES allow-list for email HTML.
	 *
	 * @param bool $rich Include table markup.
	 * @return array
	 */
	public static function allowed_html( $rich = false ) {
		$style = array( 'style' => true );
		$tags  = array(
			'a'      => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
				'style'  => true,
			),
			'p'      => $style,
			'br'     => array(),
			'strong' => $style,
			'b'      => $style,
			'em'     => $style,
			'i'      => $style,
			'u'      => $style,
			'span'   => $style,
			'div'    => $style,
			'ul'     => $style,
			'ol'     => $style,
			'li'     => $style,
			'h1'     => $style,
			'h2'     => $style,
			'h3'     => $style,
			'h4'     => $style,
			'img'    => array(
				'src'    => true,
				'alt'    => true,
				'width'  => true,
				'height' => true,
				'style'  => true,
			),
			'small'  => $style,
		);
		if ( $rich ) {
			$tags['table'] = array(
				'role'        => true,
				'width'       => true,
				'cellpadding' => true,
				'cellspacing' => true,
				'border'      => true,
				'align'       => true,
				'style'       => true,
			);
			$tags['thead'] = $style;
			$tags['tbody'] = $style;
			$tags['tr']    = array( 'style' => true, 'align' => true );
			$tags['td']    = array(
				'style'   => true,
				'align'   => true,
				'valign'  => true,
				'width'   => true,
				'colspan' => true,
				'bgcolor' => true,
			);
			$tags['th']    = $tags['td'];
		}
		return $tags;
	}

	/**
	 * Drop CSS expressions and javascript URLs hiding in style attributes.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function strip_dangerous_styles( $html ) {
		return (string) preg_replace_callback(
			'/style=("|\')(.*?)\1/i',
			static function ( $matches ) {
				$css = $matches[2];
				if ( preg_match( '/expression|javascript:|behavior\s*:|-moz-binding|@import/i', $css ) ) {
					return '';
				}
				return 'style=' . $matches[1] . $css . $matches[1];
			},
			$html
		);
	}
}
