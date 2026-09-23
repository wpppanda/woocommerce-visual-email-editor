<?php
/**
 * Email HTML renderer.
 *
 * @package WPP_Email_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a design document into email-client HTML.
 * Styles are inlined. A small head style only handles the mobile stack.
 */
class WPP_EE_Renderer {

	/**
	 * Full HTML document.
	 *
	 * @param array          $design  Sanitized design.
	 * @param WPP_EE_Context $context Data.
	 * @return string
	 */
	public static function html( array $design, WPP_EE_Context $context ) {
		$design   = apply_filters( 'wpp_ee_design', $design, $context->email_id, $context );
		$settings = self::settings( $design );
		$blocks   = isset( $design['blocks'] ) && is_array( $design['blocks'] ) ? $design['blocks'] : array();
		$rows     = '';
		$total    = count( $blocks );

		foreach ( $blocks as $index => $block ) {
			$rows .= self::block( $block, $context, $settings, 0 === $index, ( $total - 1 ) === $index );
		}

		$width   = (int) $settings['width'];
		$radius  = (int) $settings['radius'];
		$font    = $settings['font'];
		$bg      = $settings['background'];
		$card    = $settings['contentBackground'];
		$subject = $context->replace_plain( isset( $design['subject'] ) ? $design['subject'] : '' );
		$pre     = $context->replace_plain( isset( $design['preheader'] ) ? $design['preheader'] : '' );
		$title   = $subject ? $subject : $context->site_title();
		$lang    = str_replace( '_', '-', get_locale() );

		$preheader = '';
		if ( $pre ) {
			$pad       = str_repeat( '&nbsp;&zwnj;', 40 );
			$preheader = '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;color:' . esc_attr( $bg ) . ';">' . esc_html( $pre ) . $pad . '</div>';
		}

		$html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
			. '<html xmlns="http://www.w3.org/1999/xhtml" lang="' . esc_attr( $lang ) . '">'
			. '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'
			. '<meta name="viewport" content="width=device-width, initial-scale=1.0" />'
			. '<meta name="color-scheme" content="light" /><meta name="supported-color-schemes" content="light" />'
			. '<title>' . esc_html( $title ) . '</title>'
			. '<style type="text/css">body,table,td{font-family:' . esc_attr( $font ) . ';}img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic;}a{color:' . esc_attr( $settings['linkColor'] ) . ';}@media only screen and (max-width:620px){.wpp-ee-wrap{width:100%!important;} .wpp-ee-col{display:block!important;width:100%!important;max-width:100%!important;padding-left:0!important;padding-right:0!important;}}</style>'
			. '</head><body style="margin:0;padding:0;background:' . esc_attr( $bg ) . ';color:' . esc_attr( $settings['textColor'] ) . ';">'
			. $preheader
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . esc_attr( $bg ) . ';margin:0;padding:0;">'
			. '<tr><td align="center" style="padding:28px 12px;">'
			. '<!--[if mso]><table role="presentation" width="' . $width . '" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->'
			. '<table role="presentation" class="wpp-ee-wrap" width="' . $width . '" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:' . $width . 'px;background:' . esc_attr( $card ) . ';border-radius:' . $radius . 'px;overflow:hidden;">'
			. $rows
			. '</table>'
			. '<!--[if mso]></td></tr></table><![endif]-->'
			. '</td></tr></table></body></html>';

		return apply_filters( 'wpp_ee_rendered_html', $html, $context->email_id, $context, $design );
	}

	/**
	 * Plain-text cousin, used when WooCommerce is set to send plain email.
	 *
	 * @param array          $design  Design.
	 * @param WPP_EE_Context $context Data.
	 * @return string
	 */
	public static function plain( array $design, WPP_EE_Context $context ) {
		$lines = array();
		foreach ( $design['blocks'] as $block ) {
			$props = $block['props'];
			switch ( $block['type'] ) {
				case 'heading':
				case 'button':
					$lines[] = $context->replace_plain( $props['text'] );
					break;
				case 'text':
				case 'footer':
				case 'html':
					$lines[] = $context->replace_plain( wp_strip_all_tags( str_replace( array( '<br>', '<br/>', '</p>' ), "\n", $props['html'] ) ) );
					break;
				case 'order_table':
					foreach ( $context->items( false, false ) as $item ) {
						$lines[] = $item['name'] . ' × ' . $item['qty'] . ' — ' . wp_strip_all_tags( $item['total'] );
					}
					foreach ( $context->totals() as $total ) {
						$lines[] = $total['label'] . ' ' . wp_strip_all_tags( $total['value'] );
					}
					break;
				case 'order_meta':
					$lines[] = '№ ' . $context->replace_plain( '{order_number}' ) . ' · ' . $context->replace_plain( '{order_date}' );
					break;
				case 'addresses':
					$lines[] = wp_strip_all_tags( str_replace( '<br>', "\n", $context->billing_html() ) );
					$lines[] = wp_strip_all_tags( str_replace( '<br>', "\n", $context->shipping_html() ) );
					break;
				case 'note':
					$note = $context->note();
					if ( $note ) {
						$lines[] = $note;
					}
					break;
				case 'coupon':
					$lines[] = $props['code'];
					break;
				case 'account':
					$lines[] = $context->replace_plain( $props['intro'] );
					$lines[] = $context->account()['url'];
					break;
				case 'stock':
					$lines[] = $context->replace_plain( $props['intro'] );
					break;
			}
		}
		$lines = array_filter( array_map( 'trim', $lines ) );
		return implode( "\n\n", $lines );
	}

	/**
	 * Email settings with schema defaults filled in.
	 *
	 * @param array $design Design.
	 * @return array
	 */
	private static function settings( array $design ) {
		$in = isset( $design['settings'] ) && is_array( $design['settings'] ) ? $design['settings'] : array();
		return WPP_EE_Sanitizer::props( WPP_EE_Schema::email_settings(), $in, true );
	}

	/**
	 * One block, as a table row.
	 *
	 * @param array          $block    Block.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Email settings.
	 * @param bool           $first    First block, for the top radius.
	 * @param bool           $last     Last block, for the bottom radius.
	 * @return string
	 */
	private static function block( array $block, WPP_EE_Context $context, array $settings, $first, $last ) {
		$props = isset( $block['props'] ) && is_array( $block['props'] ) ? $block['props'] : array();
		$def = WPP_EE_Schema::block( $block['type'] );
		if ( $def ) {
			$props = WPP_EE_Sanitizer::props( $def['props'], $props, true );
		}
		$html  = '';
		switch ( $block['type'] ) {
			case 'header':
				$html = self::header( $props, $context, $settings, $first );
				break;
			case 'heading':
				$html = self::heading( $props, $context, $settings );
				break;
			case 'text':
				$html = self::text( $props, $context, $settings );
				break;
			case 'image':
				$html = self::image( $props, $context, $settings );
				break;
			case 'button':
				$html = self::button( $props, $context, $settings );
				break;
			case 'divider':
				$html = self::divider( $props, $settings );
				break;
			case 'spacer':
				$html = self::spacer( $props );
				break;
			case 'columns':
				$html = self::columns( $props, $context, $settings );
				break;
			case 'order_meta':
				$html = self::order_meta( $props, $context, $settings );
				break;
			case 'order_table':
				$html = self::order_table( $props, $context, $settings );
				break;
			case 'addresses':
				$html = self::addresses( $props, $context, $settings );
				break;
			case 'downloads':
				$html = self::downloads( $props, $context, $settings );
				break;
			case 'note':
				$html = self::note( $props, $context, $settings );
				break;
			case 'account':
				$html = self::account( $props, $context, $settings );
				break;
			case 'stock':
				$html = self::stock( $props, $context, $settings );
				break;
			case 'products':
				$html = self::products( $props, $context, $settings );
				break;
			case 'coupon':
				$html = self::coupon( $props, $context, $settings );
				break;
			case 'social':
				$html = self::social( $props, $settings );
				break;
			case 'html':
				$html = self::raw_html( $props, $context, $settings );
				break;
			case 'additional':
				$html = self::additional( $props, $context, $settings );
				break;
			case 'footer':
				$html = self::footer( $props, $context, $settings, $last );
				break;
		}

		return apply_filters( 'wpp_ee_block_html', $html, $block, $context, $settings );
	}

	/**
	 * Header.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @param bool           $first    First row.
	 * @return string
	 */
	private static function header( array $p, WPP_EE_Context $context, array $settings, $first ) {
		$name  = esc_html( $context->site_title() );
		$inner = '';
		if ( ! empty( $p['logoUrl'] ) ) {
			$inner .= '<img src="' . esc_url( $p['logoUrl'] ) . '" width="' . (int) $p['logoWidth'] . '" alt="' . esc_attr( $context->site_title() ) . '" style="display:inline-block;border:0;max-width:100%;height:auto;" />';
		}
		if ( ! empty( $p['showName'] ) || empty( $p['logoUrl'] ) ) {
			$gap    = empty( $p['logoUrl'] ) ? '0' : '12px';
			$inner .= '<div style="margin-top:' . $gap . ';font-size:18px;line-height:1.3;font-weight:700;letter-spacing:-0.02em;color:' . esc_attr( $p['color'] ) . ';">' . $name . '</div>';
		}
		$radius = $first ? (int) $settings['radius'] . 'px ' . (int) $settings['radius'] . 'px 0 0' : '0';
		return self::cell(
			$inner,
			array(
				'align'  => $p['align'],
				'bg'     => $p['background'],
				'color'  => $p['color'],
				'pt'     => (int) $p['paddingY'],
				'pb'     => (int) $p['paddingY'],
				'px'     => (int) $p['paddingX'],
				'font'   => $settings['font'],
				'radius' => $radius,
			)
		);
	}

	/**
	 * Heading.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function heading( array $p, WPP_EE_Context $context, array $settings ) {
		$text  = $context->replace_html( esc_html( $p['text'] ) );
		$inner = '<div style="margin:0;font-size:' . (int) $p['size'] . 'px;line-height:1.25;font-weight:700;letter-spacing:-0.03em;color:' . esc_attr( $p['color'] ) . ';">' . $text . '</div>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingTop'], (int) $p['paddingBottom'] ) );
	}

	/**
	 * Rich text.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function text( array $p, WPP_EE_Context $context, array $settings ) {
		$html = self::paint_links( $context->replace_html( $p['html'] ), $settings['linkColor'] );
		$inner = '<div style="margin:0;font-size:' . (int) $p['size'] . 'px;line-height:1.65;color:' . esc_attr( $p['color'] ) . ';">' . $html . '</div>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Image.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function image( array $p, WPP_EE_Context $context, array $settings ) {
		if ( empty( $p['url'] ) ) {
			return '';
		}
		$img = '<img src="' . esc_url( $p['url'] ) . '" width="' . (int) $p['width'] . '" alt="' . esc_attr( $context->replace_plain( $p['alt'] ) ) . '" style="display:inline-block;border:0;max-width:100%;height:auto;border-radius:' . (int) $p['radius'] . 'px;" />';
		if ( ! empty( $p['href'] ) ) {
			$img = '<a href="' . esc_url( $context->replace_plain( $p['href'] ) ) . '" style="text-decoration:none;">' . $img . '</a>';
		}
		return self::cell( $img, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Button, as a nested table so Outlook keeps the padding.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function button( array $p, WPP_EE_Context $context, array $settings ) {
		$label = $context->replace_html( $p['text'] );
		$url   = esc_url( $context->replace_plain( $p['url'] ) );
		if ( ! $url ) {
			$url = '#';
		}
		$align = self::mso_align( $p['align'] );
		$inner = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="' . esc_attr( $align ) . '"><tr><td align="center" bgcolor="' . esc_attr( $p['background'] ) . '" style="border-radius:' . (int) $p['radius'] . 'px;background:' . esc_attr( $p['background'] ) . ';">'
			. '<a href="' . $url . '" style="display:inline-block;padding:12px 22px;font-size:15px;line-height:1.2;font-weight:700;color:' . esc_attr( $p['color'] ) . ';text-decoration:none;border-radius:' . (int) $p['radius'] . 'px;">' . $label . '</a>'
			. '</td></tr></table>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingTop'], (int) $p['paddingBottom'] ) );
	}

	/**
	 * Divider.
	 *
	 * @param array $p        Props.
	 * @param array $settings Settings.
	 * @return string
	 */
	private static function divider( array $p, array $settings ) {
		$inner = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="border-top:' . (int) $p['thickness'] . 'px solid ' . esc_attr( $p['color'] ) . ';font-size:0;line-height:0;">&nbsp;</td></tr></table>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Spacer.
	 *
	 * @param array $p Props.
	 * @return string
	 */
	private static function spacer( array $p ) {
		$height = (int) $p['height'];
		return '<tr><td height="' . $height . '" style="height:' . $height . 'px;font-size:0;line-height:0;">&nbsp;</td></tr>';
	}

	/**
	 * Two columns.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function columns( array $p, WPP_EE_Context $context, array $settings ) {
		$left  = self::column_card( $p, $context, $settings, 'left' );
		$right = self::column_card( $p, $context, $settings, 'right' );
		$gap   = (int) $p['gap'];
		$inner = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
			. '<td class="wpp-ee-col" width="50%" valign="top" style="width:50%;padding-right:' . (int) floor( $gap / 2 ) . 'px;">' . $left . '</td>'
			. '<td class="wpp-ee-col" width="50%" valign="top" style="width:50%;padding-left:' . (int) ceil( $gap / 2 ) . 'px;">' . $right . '</td>'
			. '</tr></table>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * One side of a columns block.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @param string         $side     left|right.
	 * @return string
	 */
	private static function column_card( array $p, WPP_EE_Context $context, array $settings, $side ) {
		$title = 'left' === $side ? $p['leftTitle'] : $p['rightTitle'];
		$text  = 'left' === $side ? $p['leftText'] : $p['rightText'];
		$image = 'left' === $side ? $p['leftImage'] : $p['rightImage'];
		$btn   = 'left' === $side ? $p['leftButtonText'] : $p['rightButtonText'];
		$url   = 'left' === $side ? $p['leftButtonUrl'] : $p['rightButtonUrl'];
		$html  = '';
		if ( $image ) {
			$html .= '<img src="' . esc_url( $image ) . '" width="240" alt="" style="display:block;border:0;max-width:100%;height:auto;border-radius:8px;margin:0 0 12px;" />';
		}
		if ( $title ) {
			$html .= '<div style="font-size:15px;line-height:1.35;font-weight:700;color:' . esc_attr( $p['titleColor'] ) . ';margin:0 0 6px;">' . $context->replace_html( $title ) . '</div>';
		}
		if ( $text ) {
			$html .= '<div style="font-size:14px;line-height:1.6;color:' . esc_attr( $p['textColor'] ) . ';margin:0;">' . nl2br( $context->replace_html( $text ) ) . '</div>';
		}
		if ( $btn && $url ) {
			$html .= '<div style="margin-top:12px;"><a href="' . esc_url( $context->replace_plain( $url ) ) . '" style="color:' . esc_attr( $settings['linkColor'] ) . ';font-weight:700;text-decoration:none;">' . $context->replace_html( $btn ) . ' →</a></div>';
		}
		return $html;
	}

	/**
	 * Order number, date, payment.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function order_meta( array $p, WPP_EE_Context $context, array $settings ) {
		$bits = array();
		if ( ! empty( $p['showNumber'] ) ) {
			$bits[] = array( __( 'Order', 'wpp-email-editor' ), $context->replace_plain( '{order_number}' ) );
		}
		if ( ! empty( $p['showDate'] ) ) {
			$bits[] = array( __( 'Date', 'wpp-email-editor' ), $context->replace_plain( '{order_date}' ) );
		}
		if ( ! empty( $p['showPayment'] ) ) {
			$bits[] = array( __( 'Payment', 'wpp-email-editor' ), $context->replace_plain( '{payment_method}' ) );
		}
		if ( ! empty( $p['showShipping'] ) ) {
			$bits[] = array( __( 'Shipping', 'wpp-email-editor' ), $context->replace_plain( '{shipping_method}' ) );
		}
		if ( ! empty( $p['showEmail'] ) ) {
			$bits[] = array( __( 'Email', 'wpp-email-editor' ), $context->replace_plain( '{customer_email}' ) );
		}
		if ( ! $bits ) {
			return '';
		}
		$cells = '';
		$width = (int) floor( 100 / count( $bits ) );
		foreach ( $bits as $bit ) {
			$cells .= '<td class="wpp-ee-col" width="' . $width . '%" valign="top" style="width:' . $width . '%;padding:12px 12px;">'
				. '<div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:' . esc_attr( $p['labelColor'] ) . ';">' . esc_html( $bit[0] ) . '</div>'
				. '<div style="margin-top:4px;font-size:14px;font-weight:700;color:' . esc_attr( $p['color'] ) . ';">' . esc_html( $bit[1] ) . '</div>'
				. '</td>';
		}
		$inner = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . esc_attr( $p['background'] ) . ';border-radius:10px;"><tr>' . $cells . '</tr></table>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Order items and totals. Fires the usual WooCommerce hooks around the table.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function order_table( array $p, WPP_EE_Context $context, array $settings ) {
		$items = $context->items( ! empty( $p['showMeta'] ), ! empty( $p['showSku'] ) );
		if ( ! $items && ! $context->is_preview ) {
			return '';
		}
		$rows = '';
		foreach ( $items as $item ) {
			$thumb = '';
			if ( ! empty( $p['showImages'] ) ) {
				if ( ! empty( $item['image'] ) ) {
					$thumb = '<img src="' . esc_url( $item['image'] ) . '" width="48" height="48" alt="" style="display:block;border:0;border-radius:6px;width:48px;height:48px;object-fit:cover;" />';
				} else {
					$letter = esc_html( strtoupper( substr( wp_strip_all_tags( $item['name'] ), 0, 1 ) ) );
					$thumb  = '<div style="width:48px;height:48px;border-radius:6px;background:#f3f1ec;color:' . esc_attr( $p['mutedColor'] ) . ';font-weight:700;text-align:center;line-height:48px;">' . $letter . '</div>';
				}
				$thumb = '<td width="60" valign="top" style="padding:12px 12px 12px 0;border-bottom:1px solid ' . esc_attr( $p['lineColor'] ) . ';">' . $thumb . '</td>';
			}
			$meta = '';
			if ( ! empty( $item['sku'] ) ) {
				$meta .= '<div style="color:' . esc_attr( $p['mutedColor'] ) . ';font-size:12px;">SKU ' . esc_html( $item['sku'] ) . '</div>';
			}
			if ( ! empty( $item['meta'] ) ) {
				$meta .= '<div style="color:' . esc_attr( $p['mutedColor'] ) . ';font-size:12px;line-height:1.45;">' . wp_kses_post( $item['meta'] ) . '</div>';
			}
			$rows .= '<tr>' . $thumb
				. '<td valign="top" style="padding:12px 8px;border-bottom:1px solid ' . esc_attr( $p['lineColor'] ) . ';font-family:' . esc_attr( $settings['font'] ) . ';">'
				. '<div style="font-size:14px;font-weight:700;color:' . esc_attr( $p['color'] ) . ';">' . esc_html( $item['name'] ) . '</div>'
				. '<div style="margin-top:2px;font-size:12px;color:' . esc_attr( $p['mutedColor'] ) . ';">' . esc_html( sprintf( /* translators: %s: quantity */ __( 'Qty %s', 'wpp-email-editor' ), $item['qty'] ) ) . '</div>'
				. $meta
				. '</td>'
				. '<td valign="top" align="right" style="padding:12px 0;border-bottom:1px solid ' . esc_attr( $p['lineColor'] ) . ';font-size:14px;font-weight:700;color:' . esc_attr( $p['color'] ) . ';white-space:nowrap;">' . wp_kses_post( $item['total'] ) . '</td>'
				. '</tr>';
		}

		$totals = '';
		foreach ( $context->totals() as $total ) {
			$color  = $total['grand'] ? $p['totalColor'] : $p['color'];
			$weight = $total['grand'] ? '700' : '400';
			$size   = $total['grand'] ? '16px' : '13px';
			$totals .= '<tr><td colspan="2" align="right" style="padding:6px 8px 6px 0;font-size:' . $size . ';color:' . esc_attr( $p['mutedColor'] ) . ';">' . esc_html( $total['label'] ) . '</td>'
				. '<td align="right" style="padding:6px 0;font-size:' . $size . ';font-weight:' . $weight . ';color:' . esc_attr( $color ) . ';white-space:nowrap;">' . wp_kses_post( $total['value'] ) . '</td></tr>';
		}

		$hooks = self::hooks_html( 'woocommerce_email_before_order_table', $context )
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows . $totals . '</table>'
			. self::hooks_html( 'woocommerce_email_after_order_table', $context )
			. self::hooks_html( 'woocommerce_email_order_meta', $context );

		return self::cell( $hooks, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Billing and shipping.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function addresses( array $p, WPP_EE_Context $context, array $settings ) {
		$cols = array();
		if ( ! empty( $p['showBilling'] ) ) {
			$cols[] = array( $p['headingBilling'], $context->billing_html() );
		}
		if ( ! empty( $p['showShipping'] ) ) {
			$shipping = $context->shipping_html();
			if ( $shipping || $context->is_preview ) {
				$cols[] = array( $p['headingShipping'], $shipping );
			}
		}
		$cols = array_filter(
			$cols,
			static function ( $col ) {
				return '' !== trim( wp_strip_all_tags( $col[1] ) );
			}
		);
		if ( ! $cols ) {
			return '';
		}
		$cells = '';
		$width = (int) floor( 100 / count( $cols ) );
		foreach ( $cols as $col ) {
			$cells .= '<td class="wpp-ee-col" width="' . $width . '%" valign="top" style="width:' . $width . '%;padding-right:16px;">'
				. '<div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:' . esc_attr( $p['mutedColor'] ) . ';">' . esc_html( $col[0] ) . '</div>'
				. '<div style="margin-top:6px;font-size:14px;line-height:1.55;color:' . esc_attr( $p['color'] ) . ';">' . wp_kses_post( $col[1] ) . '</div>'
				. '</td>';
		}
		return self::cell( '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . $cells . '</tr></table>', self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Downloads.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function downloads( array $p, WPP_EE_Context $context, array $settings ) {
		$rows = $context->downloads();
		if ( ! $rows ) {
			return ! empty( $p['hideIfEmpty'] ) && ! $context->is_preview ? '' : '';
		}
		if ( ! $rows && ! empty( $p['hideIfEmpty'] ) ) {
			return '';
		}
		$list = '';
		foreach ( $rows as $row ) {
			$list .= '<tr><td style="padding:8px 0;border-bottom:1px solid #ece7e0;font-size:14px;color:' . esc_attr( $p['color'] ) . ';">' . esc_html( $row['name'] ) . '</td>'
				. '<td align="right" style="padding:8px 0;border-bottom:1px solid #ece7e0;"><a href="' . esc_url( $row['url'] ) . '" style="color:' . esc_attr( $settings['linkColor'] ) . ';font-weight:700;text-decoration:none;">' . esc_html( $p['buttonText'] ) . '</a></td></tr>';
		}
		$inner = '<div style="font-size:13px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:' . esc_attr( $p['color'] ) . ';margin-bottom:6px;">' . esc_html( $p['heading'] ) . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $list . '</table>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Customer note.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function note( array $p, WPP_EE_Context $context, array $settings ) {
		$note = trim( $context->note() );
		if ( '' === $note ) {
			return ( ! empty( $p['hideIfEmpty'] ) ) ? '' : '';
		}
		$inner = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="background:' . esc_attr( $p['background'] ) . ';border-radius:10px;padding:14px 16px;">'
			. '<div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#8a837a;">' . esc_html( $p['heading'] ) . '</div>'
			. '<div style="margin-top:6px;font-size:14px;line-height:1.55;color:' . esc_attr( $p['color'] ) . ';">' . nl2br( esc_html( $note ) ) . '</div>'
			. '</td></tr></table>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Account action.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function account( array $p, WPP_EE_Context $context, array $settings ) {
		$account = $context->account();
		$intro   = '';
		if ( ! empty( $p['showUsername'] ) ) {
			$intro = '<div style="font-size:15px;line-height:1.55;color:' . esc_attr( $p['color'] ) . ';">' . nl2br( $context->replace_html( $p['intro'] ) ) . '</div>';
		}
		$button = '';
		if ( $account['url'] && $p['buttonText'] ) {
			$button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:14px;"><tr><td bgcolor="' . esc_attr( $p['buttonBackground'] ) . '" style="border-radius:8px;background:' . esc_attr( $p['buttonBackground'] ) . ';">'
				. '<a href="' . esc_url( $account['url'] ) . '" style="display:inline-block;padding:12px 22px;font-weight:700;color:' . esc_attr( $p['buttonColor'] ) . ';text-decoration:none;">' . esc_html( $p['buttonText'] ) . '</a>'
				. '</td></tr></table>';
		}
		$inner = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="background:' . esc_attr( $p['background'] ) . ';border-radius:10px;padding:16px 18px;">' . $intro . $button . '</td></tr></table>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Stock notice.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function stock( array $p, WPP_EE_Context $context, array $settings ) {
		$stock = $context->stock();
		$inner = '<div style="font-size:18px;font-weight:700;color:' . esc_attr( $p['color'] ) . ';">' . esc_html( $stock['name'] ) . '</div>'
			. '<div style="margin-top:6px;font-size:14px;line-height:1.55;color:' . esc_attr( $p['color'] ) . ';">' . nl2br( $context->replace_html( $p['intro'] ) ) . '</div>';
		if ( $stock['sku'] ) {
			$inner .= '<div style="margin-top:6px;font-size:12px;color:#8a837a;">SKU ' . esc_html( $stock['sku'] ) . '</div>';
		}
		if ( $stock['url'] && $p['buttonText'] ) {
			$inner .= '<div style="margin-top:12px;"><a href="' . esc_url( $stock['url'] ) . '" style="color:' . esc_attr( $settings['linkColor'] ) . ';font-weight:700;text-decoration:none;">' . esc_html( $p['buttonText'] ) . '</a></div>';
		}
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Product row.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function products( array $p, WPP_EE_Context $context, array $settings ) {
		$products = $context->products( $p['source'], $p['ids'], (int) $p['count'] );
		if ( ! $products ) {
			return '';
		}
		$width = (int) floor( 100 / count( $products ) );
		$cells = '';
		foreach ( $products as $product ) {
			$img = '';
			if ( ! empty( $product['image'] ) ) {
				$img = '<img src="' . esc_url( $product['image'] ) . '" width="160" alt="" style="display:block;border:0;width:100%;max-width:160px;height:auto;border-radius:8px;margin:0 0 8px;" />';
			}
			$price = ! empty( $p['showPrice'] ) ? '<div style="margin-top:4px;font-size:13px;color:#8a837a;">' . wp_kses_post( $product['price'] ) . '</div>' : '';
			$cells .= '<td class="wpp-ee-col" width="' . $width . '%" valign="top" style="width:' . $width . '%;padding-right:10px;">'
				. $img
				. '<div style="font-size:14px;font-weight:700;color:' . esc_attr( $p['color'] ) . ';">' . esc_html( $product['name'] ) . '</div>'
				. $price
				. '<div style="margin-top:8px;"><a href="' . esc_url( $product['url'] ) . '" style="color:' . esc_attr( $settings['linkColor'] ) . ';font-size:13px;font-weight:700;text-decoration:none;">' . esc_html( $p['buttonText'] ) . '</a></div>'
				. '</td>';
		}
		$heading = $p['heading'] ? '<div style="font-size:16px;font-weight:700;color:' . esc_attr( $p['color'] ) . ';margin:0 0 12px;">' . esc_html( $p['heading'] ) . '</div>' : '';
		return self::cell( $heading . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . $cells . '</tr></table>', self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Coupon.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function coupon( array $p, WPP_EE_Context $context, array $settings ) {
		unset( $context );
		$inner = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="' . esc_attr( self::mso_align( $p['align'] ) ) . '" style="background:' . esc_attr( $p['background'] ) . ';border-radius:12px;padding:16px 18px;text-align:' . esc_attr( $p['align'] ) . ';">'
			. '<div style="font-size:11px;letter-spacing:0.12em;text-transform:uppercase;color:' . esc_attr( $p['color'] ) . ';">' . esc_html( $p['kicker'] ) . '</div>'
			. '<div style="margin-top:8px;display:inline-block;border:1px dashed ' . esc_attr( $p['color'] ) . ';border-radius:8px;padding:8px 14px;font-size:18px;font-weight:700;letter-spacing:0.14em;color:' . esc_attr( $p['color'] ) . ';">' . esc_html( $p['code'] ) . '</div>'
			. '<div style="margin-top:8px;font-size:14px;line-height:1.5;color:' . esc_attr( $p['color'] ) . ';">' . nl2br( esc_html( $p['text'] ) ) . '</div>'
			. '</td></tr></table>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Social text links. Images are avoided so nothing breaks if a CDN is blocked.
	 *
	 * @param array $p        Props.
	 * @param array $settings Settings.
	 * @return string
	 */
	private static function social( array $p, array $settings ) {
		$labels = array(
			'instagram' => 'Instagram',
			'telegram'  => 'Telegram',
			'vk'        => 'VK',
			'facebook'  => 'Facebook',
			'youtube'   => 'YouTube',
			'x'         => 'X',
			'tiktok'    => 'TikTok',
			'pinterest' => 'Pinterest',
			'whatsapp'  => 'WhatsApp',
			'linkedin'  => 'LinkedIn',
		);
		$links = array();
		foreach ( $labels as $key => $label ) {
			if ( empty( $p[ $key ] ) ) {
				continue;
			}
			$links[] = '<a href="' . esc_url( $p[ $key ] ) . '" style="display:inline-block;margin:4px;padding:6px 10px;border:1px solid #e4dfd6;border-radius:999px;color:' . esc_attr( $p['color'] ) . ';font-size:12px;text-decoration:none;">' . esc_html( $label ) . '</a>';
		}
		if ( ! $links ) {
			return '';
		}
		return self::cell( implode( '', $links ), self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Custom HTML.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function raw_html( array $p, WPP_EE_Context $context, array $settings ) {
		$html = self::paint_links( $context->replace_html( $p['html'] ), $settings['linkColor'] );
		return self::cell( $html, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * WooCommerce "additional content" field.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @return string
	 */
	private static function additional( array $p, WPP_EE_Context $context, array $settings ) {
		$content = '';
		if ( $context->email && method_exists( $context->email, 'get_additional_content' ) ) {
			$content = (string) $context->email->get_additional_content();
		}
		$content = trim( $content );
		if ( '' === $content ) {
			if ( ! $context->is_preview ) {
				return '';
			}
			$content = __( 'Additional content from WooCommerce → Settings → Emails will appear here.', 'wpp-email-editor' );
		}
		$html = wpautop( $context->replace_html( esc_html( $content ) ) );
		// esc_html ran before replace, so tags in the additional content stay text. That is what we want.
		$inner = '<div style="font-size:' . (int) $p['size'] . 'px;line-height:1.65;color:' . esc_attr( $p['color'] ) . ';">' . wp_kses_post( $html ) . '</div>';
		return self::cell( $inner, self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] ) );
	}

	/**
	 * Footer.
	 *
	 * @param array          $p        Props.
	 * @param WPP_EE_Context $context  Data.
	 * @param array          $settings Settings.
	 * @param bool           $last     Last row.
	 * @return string
	 */
	private static function footer( array $p, WPP_EE_Context $context, array $settings, $last ) {
		$html   = self::paint_links( $context->replace_html( $p['html'] ), $settings['linkColor'] );
		$inner  = '<div style="font-size:' . (int) $p['size'] . 'px;line-height:1.6;color:' . esc_attr( $p['color'] ) . ';">' . $html . '</div>';
		$radius = $last ? '0 0 ' . (int) $settings['radius'] . 'px ' . (int) $settings['radius'] . 'px' : '0';
		$opts   = self::pad( $p, $settings, (int) $p['paddingY'], (int) $p['paddingY'] );
		$opts['bg']     = $p['background'];
		$opts['radius'] = $radius;
		$opts['align']  = $p['align'];
		return self::cell( $inner, $opts );
	}

	/**
	 * Capture WooCommerce email hooks so other plugins can still add a line.
	 *
	 * @param string         $hook    Hook name.
	 * @param WPP_EE_Context $context Data.
	 * @return string
	 */
	private static function hooks_html( $hook, WPP_EE_Context $context ) {
		if ( ! $context->order instanceof WC_Order || $context->is_preview || ! $context->email ) {
			return '';
		}
		ob_start();
		if ( 'woocommerce_email_order_meta' === $hook ) {
			do_action( $hook, $context->order, $context->sent_to_admin, false, $context->email );
		} else {
			do_action( $hook, $context->order, $context->sent_to_admin, false, $context->email );
		}
		$html = ob_get_clean();
		return $html ? '<div style="font-size:14px;line-height:1.5;">' . wp_kses_post( $html ) . '</div>' : '';
	}

	/**
	 * Give bare links the brand color. Existing style attributes are left alone.
	 *
	 * @param string $html  HTML.
	 * @param string $color Link color.
	 * @return string
	 */
	private static function paint_links( $html, $color ) {
		return (string) preg_replace( '/<a(?![^>]*\sstyle=)/i', '<a style="color:' . esc_attr( $color ) . ';text-decoration:underline;"', $html );
	}

	/**
	 * Padding options shared by most blocks.
	 *
	 * @param array $p        Props.
	 * @param array $settings Settings.
	 * @param int   $top      Top padding.
	 * @param int   $bottom   Bottom padding.
	 * @return array
	 */
	private static function pad( array $p, array $settings, $top, $bottom ) {
		return array(
			'align' => isset( $p['align'] ) ? $p['align'] : 'left',
			'bg'    => '',
			'color' => $settings['textColor'],
			'pt'    => $top,
			'pb'    => $bottom,
			'px'    => isset( $p['paddingX'] ) ? (int) $p['paddingX'] : 36,
			'font'  => $settings['font'],
		);
	}

	/**
	 * A presentation row.
	 *
	 * @param string $html Inner HTML, already escaped by the caller.
	 * @param array  $opts align, bg, color, pt, pb, px, font, radius.
	 * @return string
	 */
	private static function cell( $html, array $opts ) {
		$align  = isset( $opts['align'] ) ? $opts['align'] : 'left';
		$bg     = isset( $opts['bg'] ) ? $opts['bg'] : '';
		$color  = isset( $opts['color'] ) ? $opts['color'] : '#3f3a36';
		$pt     = isset( $opts['pt'] ) ? (int) $opts['pt'] : 8;
		$pb     = isset( $opts['pb'] ) ? (int) $opts['pb'] : 8;
		$px     = isset( $opts['px'] ) ? (int) $opts['px'] : 36;
		$font   = isset( $opts['font'] ) ? $opts['font'] : 'Arial, Helvetica, sans-serif';
		$radius = isset( $opts['radius'] ) ? $opts['radius'] : '';
		$style  = 'padding:' . $pt . 'px ' . $px . 'px ' . $pb . 'px;color:' . $color . ';font-family:' . $font . ';text-align:' . $align . ';';
		if ( $bg ) {
			$style .= 'background:' . $bg . ';';
		}
		if ( $radius ) {
			$style .= 'border-radius:' . $radius . ';';
		}
		$bg_attr = $bg ? ' bgcolor="' . esc_attr( $bg ) . '"' : '';
		return '<tr><td align="' . esc_attr( self::mso_align( $align ) ) . '"' . $bg_attr . ' style="' . esc_attr( $style ) . '">' . $html . '</td></tr>';
	}

	/**
	 * Outlook wants left/center/right, not start.
	 *
	 * @param string $align Align.
	 * @return string
	 */
	private static function mso_align( $align ) {
		return in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'left';
	}
}
