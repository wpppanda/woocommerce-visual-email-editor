/* WPP Email Editor — visual canvas. No build step. */
(function () {
	'use strict';

	const cfg = window.WPPEmailEditor || {};
	const schema = cfg.schema || { blocks: {}, groups: [], emailSettings: {}, globalSettings: {}, mergeTags: [], fonts: [] };
	let booted = false;
	let drag = null;
	let saveTimer = null;
	let burst = false;
	let burstTimer = null;
	let saving = false;
	let saveAgain = false;

	const state = {
		view: 'library',
		emails: cfg.emails || [],
		settings: cfg.settings || {},
		emailId: cfg.email || '',
		design: null,
		selected: null,
		device: 'desktop',
		query: '',
		dirty: false,
		past: [],
		future: [],
		focus: true,
		menu: false,
		modal: null,
		previewHtml: '',
		previewSubject: '',
		orderId: 0,
		testTo: cfg.adminEmail || '',
		revisions: [],
		copyFrom: '',
		productHits: [],
		loading: false,
	};

	function t(s) {
		if (!s) {
			return '';
		}
		const map = cfg.i18n || {};
		return Object.prototype.hasOwnProperty.call(map, s) ? map[s] : s;
	}

	function esc(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function uid() {
		return 'b_' + Math.random().toString(36).slice(2, 10);
	}

	function clone(value) {
		return JSON.parse(JSON.stringify(value));
	}

	function sample() {
		return Object.assign({
			siteTitle: 'WP Panda',
			orderNumber: '1024',
			orderDate: '',
			firstName: 'Anna',
			lastName: 'Sokolova',
			fullName: 'Anna Sokolova',
			email: 'anna@example.com',
			phone: '+7 900 000-00-00',
			payment: 'Card',
			shipping: 'Courier',
			total: '6 000',
			subtotal: '5 400',
			shippingTotal: '600',
			items: [],
			billing: 'Anna Sokolova<br>Moscow',
			shippingAddr: 'Anna Sokolova<br>Moscow',
			note: 'Please leave the parcel with the front desk.',
			username: 'anna',
			productName: 'Ceramic kettle',
			stock: '2',
			refund: '1 800',
			products: [],
		}, cfg.sample || {});
	}

	function token(tag) {
		const s = sample();
		const map = {
			'{site_title}': s.siteTitle,
			'{site_url}': 'shop.example',
			'{site_address}': 'shop.example',
			'{store_email}': cfg.adminEmail || 'shop@example.com',
			'{store_address}': s.billing,
			'{order_number}': s.orderNumber,
			'{order_date}': s.orderDate,
			'{order_total}': s.total,
			'{order_url}': '#',
			'{payment_method}': s.payment,
			'{shipping_method}': s.shipping,
			'{customer_first_name}': s.firstName,
			'{customer_last_name}': s.lastName,
			'{customer_full_name}': s.fullName,
			'{customer_email}': s.email,
			'{customer_phone}': s.phone,
			'{user_login}': s.username,
			'{username}': s.username,
			'{password_reset_url}': '#',
			'{set_password_url}': '#',
			'{my_account_url}': '#',
			'{shop_url}': '#',
			'{product_name}': s.productName,
			'{stock_quantity}': s.stock,
			'{refund_amount}': s.refund,
		};
		return Object.prototype.hasOwnProperty.call(map, tag) ? String(map[tag]) : tag;
	}

	function fillText(value) {
		return esc(String(value || '').replace(/\{[a-z0-9_]+\}/g, token));
	}

	function fillHtml(value) {
		return String(value || '').replace(/\{[a-z0-9_]+\}/g, function (tag) {
			return esc(token(tag));
		});
	}

	function app() {
		return document.getElementById('wpp-ee-app');
	}

	function toast(message) {
		let node = document.querySelector('.wpp-ee-toast');
		if (!node) {
			node = document.createElement('div');
			node.className = 'wpp-ee-toast';
			document.body.appendChild(node);
		}
		node.textContent = message;
		clearTimeout(node._t);
		node._t = setTimeout(function () {
			node.remove();
		}, 2800);
	}

	function emailSettings() {
		const defaults = {};
		Object.keys(schema.emailSettings || {}).forEach(function (key) {
			defaults[key] = schema.emailSettings[key].default;
		});
		return Object.assign(defaults, (state.design && state.design.settings) || {});
	}

	function currentEmail() {
		return state.emails.find(function (email) {
			return email.id === state.emailId;
		}) || null;
	}

	function getBlock(id) {
		if (!state.design) {
			return null;
		}
		return state.design.blocks.find(function (block) {
			return block.id === id;
		}) || null;
	}

	function blockLabel(type) {
		return schema.blocks[type] ? schema.blocks[type].label : type;
	}

	function defaultsFor(type) {
		const def = schema.blocks[type];
		const props = {};
		if (!def) {
			return props;
		}
		Object.keys(def.props).forEach(function (key) {
			const rule = def.props[key];
			let value = rule.default;
			if (typeof value === 'string' && (rule.type === 'text' || rule.type === 'textarea' || rule.type === 'html' || rule.type === 'html_rich')) {
				value = t(value);
			}
			props[key] = value;
		});
		const brand = state.settings || {};
		if (type === 'header') {
			if (brand.logoUrl) {
				props.logoUrl = brand.logoUrl;
			}
			if (brand.logoWidth) {
				props.logoWidth = brand.logoWidth;
			}
			if (brand.headerBackground) {
				props.background = brand.headerBackground;
			}
			if (brand.headerColor) {
				props.color = brand.headerColor;
			}
		}
		if (type === 'button' && brand.primary) {
			props.background = brand.primary;
		}
		if (type === 'account' && brand.primary) {
			props.buttonBackground = brand.primary;
		}
		if (type === 'footer' && brand.footerText) {
			props.html = brand.footerText;
		}
		if (type === 'social' && brand.social) {
			Object.keys(brand.social).forEach(function (key) {
				if (brand.social[key]) {
					props[key] = brand.social[key];
				}
			});
		}
		if (type === 'order_table' && brand.primary) {
			props.totalColor = brand.primary;
		}
		return props;
	}

	function createBlock(type) {
		return { id: uid(), type: type, props: defaultsFor(type) };
	}

	function beforeChange(structural) {
		if (!state.design) {
			return;
		}
		if (structural || !burst) {
			state.past.push(clone(state.design));
			if (state.past.length > 60) {
				state.past.shift();
			}
			state.future = [];
			burst = !structural;
			clearTimeout(burstTimer);
			burstTimer = setTimeout(function () {
				burst = false;
			}, 700);
		}
		state.dirty = true;
	}

	function undo() {
		if (!state.past.length) {
			return;
		}
		state.future.push(clone(state.design));
		state.design = state.past.pop();
		state.dirty = true;
		state.selected = null;
		render();
		scheduleSave();
	}

	function redo() {
		if (!state.future.length) {
			return;
		}
		state.past.push(clone(state.design));
		state.design = state.future.pop();
		state.dirty = true;
		render();
		scheduleSave();
	}

	function scheduleSave() {
		clearTimeout(saveTimer);
		saveTimer = setTimeout(function () {
			saveDesign(cfg.demo);
		}, cfg.demo ? 400 : 3500);
	}

	function markEmail() {
		const email = currentEmail();
		if (!email || !state.design) {
			return;
		}
		email.customized = !!state.design.saved || state.dirty;
		email.live = !!state.design.enabled;
	}

	async function api(method, path, body) {
		const res = await fetch(cfg.restUrl + path.replace(/^\//, ''), {
			method: method,
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': cfg.nonce || '',
			},
			body: body ? JSON.stringify(body) : undefined,
		});
		const data = await res.json().catch(function () {
			return {};
		});
		if (!res.ok) {
			throw new Error(data.message || t('Could not save'));
		}
		return data;
	}

	function demoDesigns() {
		try {
			return JSON.parse(localStorage.getItem('wpp-ee-demo') || '{}');
		} catch (err) {
			return {};
		}
	}

	function writeDemoDesigns(all) {
		localStorage.setItem('wpp-ee-demo', JSON.stringify(all));
	}

	function htmlFrom(copy) {
		if (Array.isArray(copy.html)) {
			return copy.html.map(function (part) {
				return '<p>' + t(part) + '</p>';
			}).join('');
		}
		return t(copy.html);
	}

	function starter(id) {
		const brand = state.settings || {};
		const copy = STARTERS[id] || STARTERS.generic;
		const kind = (state.emails.find(function (email) { return email.id === id; }) || {}).kind || 'order';
		const settings = {
			width: brand.width || 600,
			background: brand.background || '#f3f1ec',
			contentBackground: brand.contentBackground || '#fffcf8',
			textColor: brand.text || '#3f3a36',
			linkColor: brand.primary || '#1c7a46',
			font: brand.font || 'Arial, Helvetica, sans-serif',
			radius: brand.radius || 16,
		};
		const blocks = [
			createBlock('header'),
			Object.assign(createBlock('heading'), { props: Object.assign(defaultsFor('heading'), { text: t(copy.heading) }) }),
			Object.assign(createBlock('text'), { props: Object.assign(defaultsFor('text'), { html: htmlFrom(copy) }) }),
		];
		if (kind === 'account') {
			const account = createBlock('account');
			account.props.intro = t(copy.intro || account.props.intro);
			account.props.buttonText = t(copy.button || account.props.buttonText);
			blocks.push(account);
		} else if (kind === 'stock') {
			blocks.push(createBlock('stock'));
		} else {
			blocks.push(createBlock('order_meta'));
			blocks.push(createBlock('order_table'));
			blocks.push(createBlock('note'));
			blocks.push(createBlock('addresses'));
			blocks.push(createBlock('button'));
			blocks[blocks.length - 1].props.text = t(copy.button || blocks[blocks.length - 1].props.text);
			blocks.push(createBlock('additional'));
		}
		const footer = createBlock('footer');
		if (brand.footerText) {
			footer.props.html = brand.footerText;
		}
		blocks.push(footer);
		return {
			emailId: id,
			enabled: false,
			subject: t(copy.subject),
			preheader: t(copy.preheader),
			settings: settings,
			blocks: blocks,
			saved: false,
		};
	}

	const STARTERS = {
		customer_processing_order: {
			subject: 'Your {site_title} order #{order_number} is confirmed',
			heading: 'We have your order',
			preheader: 'Order #{order_number} is confirmed.',
			html: '<p>Hi {customer_first_name},</p><p>Thank you. We have received order #{order_number} and we are getting it ready. This email is the receipt — the next one arrives when it ships.</p>',
			button: 'View your order',
		},
		customer_completed_order: {
			subject: 'Order #{order_number} is complete',
			heading: 'It is on the way',
			preheader: 'Order #{order_number} is complete.',
			html: '<p>Hi {customer_first_name},</p><p>Order #{order_number} is complete. We hope it is exactly what you wanted — and if it is not, reply to this email.</p>',
			button: 'View your order',
		},
		customer_new_account: {
			subject: 'Your {site_title} account',
			heading: 'Your account is ready',
			preheader: 'An account was created for you at {site_title}.',
			html: '<p>Hi {customer_first_name},</p><p>An account has been created for you at {site_title}. Set a password and you can see your orders any time.</p>',
			button: 'Set your password',
			intro: 'Your username is {user_login}.',
		},
		customer_reset_password: {
			subject: 'Reset your {site_title} password',
			heading: 'Reset your password',
			preheader: 'A password reset was requested for your account.',
			html: '<p>Hi {customer_first_name},</p><p>Someone asked to reset the password for {user_login}. If that was you, use the button. If it was not, you can ignore this email.</p>',
			button: 'Reset password',
			intro: 'Confirm it is you, and we will let you choose a new password.',
		},
		new_order: {
			subject: 'New order #{order_number}',
			heading: 'New order',
			preheader: '{customer_full_name} placed order #{order_number}.',
			html: '<p>You have a new order from {customer_full_name} — #{order_number}, placed on {order_date}.</p>',
			button: 'Open in admin',
		},
		generic: {
			subject: '{site_title}: {order_number}',
			heading: 'An update from {site_title}',
			preheader: 'A message about your order.',
			html: '<p>Hi {customer_first_name},</p><p>Here is an update from {site_title}. The details we have are below.</p>',
			button: 'View your order',
		},
	};

	async function openEmail(id) {
		state.view = 'editor';
		state.emailId = id;
		state.selected = null;
		state.past = [];
		state.future = [];
		state.menu = false;
		state.loading = true;
		state.design = null;
		render();
		try {
			if (cfg.demo) {
				const saved = demoDesigns()[id];
				state.design = saved ? clone(saved) : starter(id);
			} else {
				state.design = await api('GET', 'designs/' + id);
			}
			state.dirty = false;
		} catch (err) {
			toast(err.message);
			state.view = 'library';
		}
		state.loading = false;
		render();
	}

	async function saveDesign(revision) {
		if (!state.design || !state.emailId) {
			return;
		}
		if (saving) {
			saveAgain = true;
			return;
		}
		saving = true;
		setStatus(t('Saving…'));
		try {
			if (cfg.demo) {
				const all = demoDesigns();
				state.design.saved = true;
				all[state.emailId] = state.design;
				writeDemoDesigns(all);
				if (state.settings) {
					localStorage.setItem('wpp-ee-demo-settings', JSON.stringify(state.settings));
				}
			} else {
				const payload = clone(state.design);
				payload.revision = revision !== false;
				const saved = await api('PUT', 'designs/' + state.emailId, payload);
				state.design.updatedAt = saved.updatedAt;
				state.design.saved = true;
			}
			state.dirty = false;
			markEmail();
			setStatus(t('Saved'));
		} catch (err) {
			toast(err.message || t('Could not save'));
			setStatus(t('Could not save'));
		}
		saving = false;
		if (saveAgain) {
			saveAgain = false;
			saveDesign(false);
		}
	}

	function setStatus(text) {
		const node = document.querySelector('[data-status]');
		if (node) {
			node.textContent = text;
		}
	}

	function icon(name) {
		const paths = {
			undo: '<path d="M7 8H3V4"/><path d="M3.5 8A6 6 0 1 1 4 12"/>',
			redo: '<path d="M9 8h4V4"/><path d="M12.5 8A6 6 0 1 0 12 12"/>',
			desktop: '<rect x="2" y="3" width="12" height="8" rx="1"/><path d="M6 14h4M8 11v3"/>',
			mobile: '<rect x="5" y="2" width="6" height="12" rx="1"/><path d="M8 12.5h.01"/>',
			eye: '<path d="M1 8s2.5-4.5 7-4.5S15 8 15 8s-2.5 4.5-7 4.5S1 8 1 8z"/><circle cx="8" cy="8" r="2"/>',
			send: '<path d="M2 8l12-5-5 12-2-5-5-2z"/>',
			plus: '<path d="M8 3v10M3 8h10"/>',
			image: '<rect x="2" y="3" width="12" height="10" rx="1"/><path d="M2 11l3.5-3.5L9 11l2-2 3 3"/>',
			type: '<path d="M3 4h10M8 4v9M5 13h6"/>',
			layout: '<rect x="2" y="3" width="12" height="10" rx="1"/><path d="M2 6h12"/>',
			button: '<rect x="2" y="5" width="12" height="6" rx="2"/>',
			minus: '<path d="M3 8h10"/>',
			space: '<path d="M8 3v10M5 5l3-3 3 3M5 11l3 3 3-3"/>',
			cols: '<rect x="2" y="3" width="5" height="10" rx="1"/><rect x="9" y="3" width="5" height="10" rx="1"/>',
			hash: '<path d="M6 2v12M10 2v12M2 6h12M2 10h12"/>',
			list: '<path d="M5 4h9M5 8h9M5 12h9"/><path d="M2.5 4h.01M2.5 8h.01M2.5 12h.01"/>',
			map: '<path d="M8 14s5-4.2 5-7a5 5 0 1 0-10 0c0 2.8 5 7 5 7z"/><circle cx="8" cy="7" r="1.5"/>',
			down: '<path d="M8 2v8M5 7l3 3 3-3"/><path d="M3 13h10"/>',
			note: '<path d="M4 2h6l3 3v9H4z"/><path d="M10 2v3h3"/>',
			user: '<circle cx="8" cy="6" r="2.4"/><path d="M3.5 13c.6-2.2 2.3-3.2 4.5-3.2s3.9 1 4.5 3.2"/>',
			box: '<path d="M2 5l6-3 6 3-6 3-6-3z"/><path d="M2 5v6l6 3 6-3V5"/>',
			grid: '<rect x="2" y="2" width="5" height="5" rx="1"/><rect x="9" y="2" width="5" height="5" rx="1"/><rect x="2" y="9" width="5" height="5" rx="1"/><rect x="9" y="9" width="5" height="5" rx="1"/>',
			ticket: '<path d="M2 6a2 2 0 0 0 0 4v2h12v-2a2 2 0 0 0 0-4V4H2z"/><path d="M8 5v6"/>',
			share: '<circle cx="4" cy="8" r="1.6"/><circle cx="12" cy="4" r="1.6"/><circle cx="12" cy="12" r="1.6"/><path d="M5.5 7.2L10.4 4.8M5.5 8.8l4.9 2.4"/>',
			code: '<path d="M6 4L2 8l4 4M10 4l4 4-4 4"/>',
			footer: '<path d="M2 4h12v8H2z"/><path d="M2 9h12"/>',
		};
		const path = paths[name] || paths.layout;
		return '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + path + '</svg>';
	}

	const BLOCK_ICONS = {
		header: 'layout',
		heading: 'type',
		text: 'type',
		image: 'image',
		button: 'button',
		divider: 'minus',
		spacer: 'space',
		columns: 'cols',
		order_meta: 'hash',
		order_table: 'list',
		addresses: 'map',
		downloads: 'down',
		note: 'note',
		account: 'user',
		stock: 'box',
		products: 'grid',
		coupon: 'ticket',
		social: 'share',
		html: 'code',
		additional: 'plus',
		footer: 'footer',
	};

	function mark() {
		return '<svg class="wpp-ee-mark" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#1c7a46"/><path d="M8.5 22.5V10.5L16 17l7.5-6.5v12" fill="none" stroke="#f7f4ef" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/></svg>';
	}

	function visual(block, preview) {
		const p = block.props || {};
		const text = preview ? fillText : esc;
		const html = preview ? fillHtml : function (value) { return value || ''; };
		switch (block.type) {
			case 'header':
				return '<div style="padding:' + p.paddingY + 'px ' + p.paddingX + 'px;background:' + p.background + ';color:' + p.color + ';text-align:' + p.align + ';">' +
					(p.logoUrl ? '<img src="' + esc(p.logoUrl) + '" alt="" style="width:' + p.logoWidth + 'px;max-width:100%;height:auto;border:0;">' : '') +
					(p.showName || !p.logoUrl ? '<div style="margin-top:' + (p.logoUrl ? 12 : 0) + 'px;font-size:18px;font-weight:700;letter-spacing:-0.02em;">' + esc(sample().siteTitle) + '</div>' : '') +
					'</div>';
			case 'heading':
				return '<div style="padding:' + p.paddingTop + 'px ' + p.paddingX + 'px ' + p.paddingBottom + 'px;text-align:' + p.align + ';"><div class="wpp-ee-ce" ' + (preview ? '' : 'contenteditable="true"') + ' data-ce="text" style="font-size:' + p.size + 'px;line-height:1.25;font-weight:700;letter-spacing:-0.03em;color:' + p.color + ';">' + (preview ? fillText(p.text) : esc(p.text)) + '</div></div>';
			case 'text':
				return '<div style="padding:' + p.paddingY + 'px ' + p.paddingX + 'px;text-align:' + p.align + ';color:' + p.color + ';font-size:' + p.size + 'px;line-height:1.65;"><div class="wpp-ee-ce" ' + (preview ? '' : 'contenteditable="true"') + ' data-ce="html">' + html(p.html) + '</div></div>';
			case 'image':
				if (!p.url) {
					return pad(p, '<div class="wpp-ee-ph">' + esc(t('No image yet')) + '</div>');
				}
				return '<div style="padding:' + p.paddingY + 'px ' + p.paddingX + 'px;text-align:' + p.align + ';"><img src="' + esc(p.url) + '" alt="' + esc(p.alt) + '" style="width:' + Math.min(p.width, 560) + 'px;max-width:100%;height:auto;border-radius:' + p.radius + 'px;border:0;"></div>';
			case 'button':
				return '<div style="padding:' + p.paddingTop + 'px ' + p.paddingX + 'px ' + p.paddingBottom + 'px;text-align:' + p.align + ';"><span style="display:inline-block;background:' + p.background + ';color:' + p.color + ';border-radius:' + p.radius + 'px;padding:12px 22px;font-weight:700;">' + text(p.text) + '</span></div>';
			case 'divider':
				return '<div style="padding:' + p.paddingY + 'px ' + p.paddingX + 'px;"><div style="border-top:' + p.thickness + 'px solid ' + p.color + ';"></div></div>';
			case 'spacer':
				return '<div style="height:' + p.height + 'px;"></div>';
			case 'columns':
				return '<div style="padding:' + p.paddingY + 'px ' + p.paddingX + 'px;display:flex;gap:' + p.gap + 'px;">' + columnCard(p, 'left', preview) + columnCard(p, 'right', preview) + '</div>';
			case 'order_meta':
				return visualMeta(p);
			case 'order_table':
				return visualTable(p);
			case 'addresses':
				return visualAddresses(p);
			case 'downloads':
				return pad(p, '<div style="font-size:12px;letter-spacing:0.08em;text-transform:uppercase;font-weight:700;margin-bottom:8px;">' + esc(p.heading) + '</div><div style="display:flex;justify-content:space-between;border-bottom:1px solid #ece7e0;padding:8px 0;"><span>' + esc(t('Lookbook (PDF)')) + '</span><strong style="color:#1c7a46;">' + esc(p.buttonText) + '</strong></div>');
			case 'note':
				return pad(p, '<div style="background:' + p.background + ';border-radius:10px;padding:14px 16px;"><div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:#8a837a;">' + esc(p.heading) + '</div><div style="margin-top:6px;color:' + p.color + ';">' + esc(sample().note) + '</div></div>');
			case 'account':
				return pad(p, '<div style="background:' + p.background + ';border-radius:10px;padding:16px 18px;color:' + p.color + ';"><div>' + (preview ? fillHtml(p.intro).replace(/\n/g, '<br>') : esc(p.intro)) + '</div><div style="margin-top:12px;"><span style="display:inline-block;background:' + p.buttonBackground + ';color:' + p.buttonColor + ';border-radius:8px;padding:12px 22px;font-weight:700;">' + esc(p.buttonText) + '</span></div></div>');
			case 'stock':
				return pad(p, '<div style="font-size:18px;font-weight:700;color:' + p.color + ';">' + esc(sample().productName) + '</div><div style="margin-top:6px;">' + (preview ? fillText(p.intro) : esc(p.intro)) + '</div><div style="margin-top:8px;color:#8a837a;font-size:12px;">SKU KETTLE-01</div>');
			case 'products':
				return visualProducts(p);
			case 'coupon':
				return pad(p, '<div style="background:' + p.background + ';color:' + p.color + ';border-radius:12px;padding:16px 18px;text-align:' + p.align + ';"><div style="font-size:11px;letter-spacing:0.12em;text-transform:uppercase;">' + esc(p.kicker) + '</div><div style="margin-top:8px;display:inline-block;border:1px dashed ' + p.color + ';border-radius:8px;padding:8px 14px;font-weight:700;letter-spacing:0.14em;">' + esc(p.code) + '</div><div style="margin-top:8px;">' + esc(p.text) + '</div></div>');
			case 'social':
				return visualSocial(p);
			case 'html':
				return pad(p, preview ? (p.html || '') : '<div class="wpp-ee-ph">' + esc(t('Custom HTML is edited on the right. Open Preview to see it rendered.')) + '</div>');
			case 'additional':
				return pad(p, '<div style="color:' + p.color + ';font-size:' + p.size + 'px;line-height:1.65;">' + esc(t('Additional content from WooCommerce → Settings → Emails will appear here.')) + '</div>');
			case 'footer':
				return '<div style="padding:' + p.paddingY + 'px ' + p.paddingX + 'px;background:' + p.background + ';color:' + p.color + ';text-align:' + p.align + ';font-size:' + p.size + 'px;line-height:1.6;">' + html(p.html) + '</div>';
			default:
				return pad(p, esc(block.type));
		}
	}

	function pad(p, inner) {
		const y = p.paddingY || 8;
		const x = p.paddingX || 36;
		return '<div style="padding:' + y + 'px ' + x + 'px;">' + inner + '</div>';
	}

	function columnCard(p, side) {
		const title = side === 'left' ? p.leftTitle : p.rightTitle;
		const text = side === 'left' ? p.leftText : p.rightText;
		const image = side === 'left' ? p.leftImage : p.rightImage;
		return '<div style="flex:1;min-width:0;">' +
			(image ? '<img src="' + esc(image) + '" alt="" style="width:100%;border-radius:8px;margin-bottom:10px;">' : '') +
			'<div style="font-weight:700;color:' + p.titleColor + ';margin-bottom:4px;">' + esc(title) + '</div>' +
			'<div style="color:' + p.textColor + ';font-size:14px;line-height:1.55;">' + esc(text) + '</div></div>';
	}

	function visualMeta(p) {
		const s = sample();
		const bits = [];
		if (p.showNumber) bits.push([t('Order'), s.orderNumber]);
		if (p.showDate) bits.push([t('Date'), s.orderDate || '—']);
		if (p.showPayment) bits.push([t('Payment'), s.payment]);
		if (p.showShipping) bits.push([t('Shipping'), s.shipping]);
		if (p.showEmail) bits.push([t('Email'), s.email]);
		const cells = bits.map(function (bit) {
			return '<div style="flex:1;padding:12px;"><div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:' + p.labelColor + ';">' + esc(bit[0]) + '</div><div style="margin-top:4px;font-weight:700;color:' + p.color + ';">' + esc(bit[1]) + '</div></div>';
		}).join('');
		return pad(p, '<div style="display:flex;background:' + p.background + ';border-radius:10px;">' + cells + '</div>');
	}

	function visualTable(p) {
		const s = sample();
		const items = s.items.length ? s.items : [{ name: s.productName, qty: 1, total: s.total, meta: '' }];
		const span = p.showImages ? 2 : 1;
		const rows = items.map(function (item) {
			const thumb = p.showImages ? '<td style="padding:12px 12px 12px 0;border-bottom:1px solid ' + p.lineColor + ';width:60px;"><div style="width:48px;height:48px;border-radius:6px;background:#f3f1ec;color:' + p.mutedColor + ';font-weight:700;text-align:center;line-height:48px;">' + esc((item.name || '?').slice(0, 1)) + '</div></td>' : '';
			return '<tr>' + thumb + '<td style="padding:12px 8px;border-bottom:1px solid ' + p.lineColor + ';"><div style="font-weight:700;color:' + p.color + ';">' + esc(item.name) + '</div><div style="color:' + p.mutedColor + ';font-size:12px;">' + esc(t('Qty')) + ' ' + esc(item.qty) + '</div>' + (p.showMeta && item.meta ? '<div style="color:' + p.mutedColor + ';font-size:12px;">' + esc(item.meta) + '</div>' : '') + '</td><td style="padding:12px 0;border-bottom:1px solid ' + p.lineColor + ';text-align:right;font-weight:700;color:' + p.color + ';white-space:nowrap;">' + esc(item.total) + '</td></tr>';
		}).join('');
		const totals = '<tr><td colspan="' + span + '" style="text-align:right;padding:6px 8px;color:' + p.mutedColor + ';font-size:13px;">' + esc(t('Subtotal')) + '</td><td style="text-align:right;font-size:13px;">' + esc(s.subtotal) + '</td></tr>' +
			'<tr><td colspan="' + span + '" style="text-align:right;padding:6px 8px;color:' + p.mutedColor + ';font-size:13px;">' + esc(t('Shipping')) + '</td><td style="text-align:right;font-size:13px;">' + esc(s.shippingTotal) + '</td></tr>' +
			'<tr><td colspan="' + span + '" style="text-align:right;padding:8px 8px;color:' + p.mutedColor + ';">' + esc(t('Total')) + '</td><td style="text-align:right;font-weight:700;color:' + p.totalColor + ';font-size:16px;">' + esc(s.total) + '</td></tr>';
		return pad(p, '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">' + rows + totals + '</table>');
	}

	function visualAddresses(p) {
		const s = sample();
		let html = '<div style="display:flex;gap:16px;">';
		if (p.showBilling) {
			html += '<div style="flex:1;"><div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:' + p.mutedColor + ';">' + esc(p.headingBilling) + '</div><div style="margin-top:6px;color:' + p.color + ';line-height:1.55;">' + s.billing + '</div></div>';
		}
		if (p.showShipping) {
			html += '<div style="flex:1;"><div style="font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:' + p.mutedColor + ';">' + esc(p.headingShipping) + '</div><div style="margin-top:6px;color:' + p.color + ';line-height:1.55;">' + s.shippingAddr + '</div></div>';
		}
		return pad(p, html + '</div>');
	}

	function visualProducts(p) {
		const items = (sample().products || []).slice(0, p.count || 3);
		const cards = items.map(function (item) {
			return '<div style="flex:1;min-width:0;"><div style="height:72px;border-radius:8px;background:#f3f1ec;margin-bottom:8px;"></div><div style="font-weight:700;color:' + p.color + ';font-size:14px;">' + esc(item.name) + '</div>' + (p.showPrice ? '<div style="color:#8a837a;font-size:13px;margin-top:2px;">' + esc(item.price) + '</div>' : '') + '<div style="margin-top:6px;color:#1c7a46;font-weight:700;font-size:13px;">' + esc(p.buttonText) + '</div></div>';
		}).join('');
		return pad(p, (p.heading ? '<div style="font-weight:700;font-size:16px;margin-bottom:12px;color:' + p.color + ';">' + esc(p.heading) + '</div>' : '') + '<div style="display:flex;gap:12px;">' + cards + '</div>');
	}

	function visualSocial(p) {
		const names = { instagram: 'Instagram', telegram: 'Telegram', vk: 'VK', facebook: 'Facebook', youtube: 'YouTube', x: 'X', tiktok: 'TikTok', pinterest: 'Pinterest', whatsapp: 'WhatsApp', linkedin: 'LinkedIn' };
		const links = Object.keys(names).filter(function (key) { return p[key]; }).map(function (key) {
			return '<span style="display:inline-block;margin:4px;padding:6px 10px;border:1px solid #e4dfd6;border-radius:99px;font-size:12px;color:' + p.color + ';">' + names[key] + '</span>';
		});
		return pad(p, '<div style="text-align:' + p.align + ';">' + (links.length ? links.join('') : '<div class="wpp-ee-ph">' + esc(t('No social links yet')) + '</div>') + '</div>');
	}

	function mountBlock(block, preview) {
		const el = document.createElement('div');
		el.className = 'wpp-ee-block' + (!preview && state.selected === block.id ? ' is-selected' : '');
		el.dataset.block = block.id;
		if (!preview) {
			el.insertAdjacentHTML('beforeend', '<div class="wpp-ee-block__bar"><button type="button" class="wpp-ee-drag" draggable="true">' + esc(t(blockLabel(block.type))) + '</button><button type="button" data-act="up" aria-label="' + esc(t('Up')) + '">↑</button><button type="button" data-act="down" aria-label="' + esc(t('Down')) + '">↓</button><button type="button" data-act="dup" aria-label="' + esc(t('Duplicate')) + '">⧉</button><button type="button" class="is-danger" data-act="del" aria-label="' + esc(t('Delete')) + '">✕</button></div>');
		}
		const body = document.createElement('div');
		body.className = 'wpp-ee-block__body';
		body.innerHTML = visual(block, preview);
		el.appendChild(body);
		return el;
	}

	function patchBlock(id) {
		const current = app().querySelector('[data-block="' + id + '"]');
		const block = getBlock(id);
		if (!current || !block) {
			return;
		}
		if (current.contains(document.activeElement) && document.activeElement.isContentEditable) {
			return;
		}
		current.replaceWith(mountBlock(block, false));
	}

	function clientDocument() {
		const s = emailSettings();
		const holder = document.createElement('div');
		(state.design.blocks || []).forEach(function (block) {
			holder.appendChild(mountBlock(block, true));
		});
		holder.querySelectorAll('.wpp-ee-block__bar').forEach(function (node) { node.remove(); });
		const inner = holder.innerHTML;
		return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;background:' + s.background + ';padding:28px 12px;"><div style="max-width:' + s.width + 'px;margin:0 auto;background:' + s.contentBackground + ';border-radius:' + s.radius + 'px;overflow:hidden;font-family:' + s.font + ';color:' + s.textColor + ';">' + inner + '</div></body></html>';
	}

	function shell() {
		return topbar() + (state.view === 'editor' ? editorHtml() : libraryHtml());
	}

	function topbar() {
		const email = currentEmail();
		const title = state.view === 'editor' && email ? '<span>' + esc(t('Email')) + '</span>' + esc(email.title) : esc(t('Email Editor'));
		let actions = '';
		if (state.view === 'editor') {
			actions = '<span class="wpp-ee-status" data-status>' + (state.dirty ? esc(t('Unsaved changes')) : esc(t('Saved'))) + '</span>' +
				'<label class="wpp-ee-switch"><input type="checkbox" data-act="enable" ' + (state.design && state.design.enabled ? 'checked' : '') + '>' + esc(t('Use this design')) + '</label>' +
				'<button type="button" class="wpp-ee-iconbtn" data-act="undo" aria-label="' + esc(t('Undo')) + '">' + icon('undo') + '</button>' +
				'<button type="button" class="wpp-ee-iconbtn" data-act="redo" aria-label="' + esc(t('Redo')) + '">' + icon('redo') + '</button>' +
				'<button type="button" class="wpp-ee-btn" data-act="preview">' + icon('eye') + esc(t('Preview')) + '</button>' +
				'<button type="button" class="wpp-ee-btn" data-act="test">' + icon('send') + esc(t('Test')) + '</button>' +
				'<div class="wpp-ee-menu"><button type="button" class="wpp-ee-btn" data-act="menu">' + esc(t('More')) + '</button>' + (state.menu ? menuHtml() : '') + '</div>' +
				'<button type="button" class="wpp-ee-btn-primary" data-act="save">' + esc(t('Save')) + '</button>';
		} else {
			actions = '<button type="button" class="wpp-ee-btn" data-act="focus">' + esc(state.focus ? t('Exit focus') : t('Focus mode')) + '</button>';
		}
		return '<header class="wpp-ee-top"><button type="button" class="wpp-ee-brand" data-act="library">' + mark() + '<span><small>WP Panda</small><strong>wpp-email-editor</strong></span></button><div class="wpp-ee-top__title">' + title + '</div><div class="wpp-ee-top__actions">' + actions + '</div></header>';
	}

	function menuHtml() {
		return '<div class="wpp-ee-menu__pop"><button type="button" data-act="revisions">' + esc(t('Revisions')) + '</button><button type="button" data-act="copy-from">' + esc(t('Copy design from…')) + '</button><button type="button" data-act="apply-brand">' + esc(t('Apply brand to this email')) + '</button><button type="button" data-act="download-html">' + esc(t('Download HTML')) + '</button><button type="button" data-act="focus">' + esc(state.focus ? t('Exit focus') : t('Focus mode')) + '</button><button type="button" data-act="reset">' + esc(t('Reset to starter')) + '</button></div>';
	}

	function libraryHtml() {
		if (cfg.hasWoo === false) {
			return '<div class="wpp-ee-empty"><p class="wpp-ee-kicker">WP Panda</p><h1>' + esc(t('WooCommerce is required')) + '</h1><p>' + esc(t('WPP Email Editor needs WooCommerce to design and send emails.')) + '</p></div>';
		}
		const q = state.query.trim().toLowerCase();
		const groups = [
			['orders', t('Orders')],
			['accounts', t('Accounts')],
			['stock', t('Stock')],
			['other', t('Other')],
		];
		const live = state.emails.filter(function (email) { return email.live; }).length;
		const drafts = state.emails.filter(function (email) { return email.customized && !email.live; }).length;
		let groupsHtml = '';
		groups.forEach(function (group) {
			const cards = state.emails.filter(function (email) {
				if (email.group !== group[0]) {
					return false;
				}
				if (!q) {
					return true;
				}
				return (email.title + ' ' + email.description + ' ' + email.id).toLowerCase().indexOf(q) !== -1;
			});
			if (!cards.length) {
				return;
			}
			groupsHtml += '<section class="wpp-ee-group"><h2>' + esc(group[1]) + '</h2><div class="wpp-ee-grid">' + cards.map(cardHtml).join('') + '</div></section>';
		});
		if (!groupsHtml) {
			groupsHtml = '<div class="wpp-ee-empty"><h1>' + esc(t('Nothing matches')) + '</h1><p>' + esc(t('No emails match that search.')) + '</p></div>';
		}
		return '<div class="wpp-ee-lib"><section class="wpp-ee-hero"><div><p class="wpp-ee-kicker">WP Panda</p><h1>' + esc(t('Email Editor')) + '</h1><p class="wpp-ee-lead">' + esc(t('Visual editor for WooCommerce. The shop keeps sending its own template until you turn a design on.')) + '</p></div><div class="wpp-ee-stats"><div class="wpp-ee-stat"><b>' + state.emails.length + '</b><span>' + esc(t('Emails')) + '</span></div><div class="wpp-ee-stat"><b>' + live + '</b><span>' + esc(t('Live')) + '</span></div><div class="wpp-ee-stat"><b>' + drafts + '</b><span>' + esc(t('Drafts')) + '</span></div></div></section><div class="wpp-ee-libbar"><input class="wpp-ee-search" data-search value="' + esc(state.query) + '" placeholder="' + esc(t('Search emails')) + '"><button type="button" class="wpp-ee-btn" data-act="brand">' + esc(t('Brand')) + '</button><button type="button" class="wpp-ee-btn" data-act="export">' + esc(t('Export')) + '</button><button type="button" class="wpp-ee-btn" data-act="import">' + esc(t('Import')) + '</button><input type="file" id="wpp-ee-import" accept="application/json" hidden></div>' + groupsHtml + '</div>';
	}

	function cardHtml(email) {
		let pill = '<span class="wpp-ee-pill">' + esc(t('WooCommerce default')) + '</span>';
		if (!email.wcEnabled) {
			pill = '<span class="wpp-ee-pill is-off">' + esc(t('Off in WooCommerce')) + '</span>';
		} else if (email.live) {
			pill = '<span class="wpp-ee-pill is-live">' + esc(t('Live')) + '</span>';
		} else if (email.customized) {
			pill = '<span class="wpp-ee-pill is-draft">' + esc(t('Draft')) + '</span>';
		}
		return '<button type="button" class="wpp-ee-card" data-open="' + esc(email.id) + '"><span class="wpp-ee-card__top"><span class="wpp-ee-audience">' + esc(email.audience === 'admin' ? t('Admin') : t('Customer')) + (email.manual ? ' · ' + esc(t('Manual')) : '') + '</span>' + pill + '</span><strong>' + esc(email.title) + '</strong><span class="wpp-ee-card__desc">' + esc(email.description) + '</span><span class="wpp-ee-card__go">' + esc(t('Design')) + ' →</span></button>';
	}

	function editorHtml() {
		if (state.loading || !state.design) {
			return '<div class="wpp-ee-empty"><p>' + esc(t('Opening the email…')) + '</p></div>';
		}
		const s = emailSettings();
		return '<div class="wpp-ee-edit"><aside class="wpp-ee-palette">' + paletteHtml() + '</aside><main class="wpp-ee-stage"><div class="wpp-ee-stage__bar"><div class="wpp-ee-seg"><button type="button" data-act="device" data-value="desktop" class="' + (state.device === 'desktop' ? 'is-on' : '') + '">' + esc(t('Desktop')) + '</button><button type="button" data-act="device" data-value="mobile" class="' + (state.device === 'mobile' ? 'is-on' : '') + '">' + esc(t('Mobile')) + '</button></div></div><div class="wpp-ee-stage__scroll" style="background:' + esc(s.background) + ';"><div class="wpp-ee-sheet' + (state.device === 'mobile' ? ' is-mobile' : '') + '" data-sheet style="max-width:' + s.width + 'px;background:' + s.contentBackground + ';border-radius:' + s.radius + 'px;font-family:' + esc(s.font) + ';color:' + s.textColor + ';"></div></div></main><aside class="wpp-ee-inspector">' + inspectorHtml() + '</aside></div>';
	}

	function paletteHtml() {
		let html = '';
		(schema.groups || []).forEach(function (group) {
			const chips = Object.keys(schema.blocks || {}).filter(function (type) {
				return schema.blocks[type].group === group.id;
			}).map(function (type) {
				const def = schema.blocks[type];
				return '<button type="button" class="wpp-ee-chip" draggable="true" data-add="' + esc(type) + '" title="' + esc(t(def.description || '')) + '">' + icon(BLOCK_ICONS[type] || 'layout') + '<span>' + esc(t(def.label)) + '</span></button>';
			}).join('');
			html += '<h2>' + esc(t(group.label)) + '</h2><div class="wpp-ee-chips">' + chips + '</div>';
		});
		return html + '<p class="wpp-ee-hint">' + esc(t('Drag a block onto the email, or click to append it.')) + '</p>';
	}

	function inspectorHtml() {
		if (!state.design) {
			return '';
		}
		if (!state.selected) {
			return emailInspector();
		}
		const block = getBlock(state.selected);
		if (!block || !schema.blocks[block.type]) {
			return emailInspector();
		}
		const def = schema.blocks[block.type];
		let fields = Object.keys(def.props).map(function (key) {
			return control(key, def.props[key], block.props[key], 'block');
		}).join('');
		if (block.type === 'text') {
			fields = '<div class="wpp-ee-format"><button type="button" class="wpp-ee-mini" data-act="format" data-value="bold"><b>B</b></button><button type="button" class="wpp-ee-mini" data-act="format" data-value="italic"><i>I</i></button><button type="button" class="wpp-ee-mini" data-act="format" data-value="underline"><u>U</u></button><button type="button" class="wpp-ee-mini" data-act="format" data-value="createLink">' + esc(t('Link')) + '</button></div>' + fields;
		}
		return '<div class="wpp-ee-inspector__head"><strong>' + esc(t(def.label)) + '</strong><button type="button" data-act="deselect">' + esc(t('Email')) + '</button></div>' + fields;
	}

	function emailInspector() {
		const email = currentEmail();
		const s = emailSettings();
		let note = state.design.enabled
			? '<div class="wpp-ee-note">' + esc(t('This design replaces the WooCommerce template when the email sends.')) + '</div>'
			: '<div class="wpp-ee-note is-warn">' + esc(t('This design is off. WooCommerce will keep its own template until you switch it on.')) + '</div>';
		if (email && email.wcEnabled === false) {
			note += '<div class="wpp-ee-note is-warn">' + esc(t('This email is disabled in WooCommerce, so it will not send until you enable it there.')) + '</div>';
		}
		let fields = '<div class="wpp-ee-field"><label>' + esc(t('Subject')) + '</label><input type="text" data-prop="subject" data-scope="email" value="' + esc(state.design.subject) + '">' + tagButtons('subject') + '</div>';
		fields += '<div class="wpp-ee-field"><label>' + esc(t('Preheader')) + '</label><input type="text" data-prop="preheader" data-scope="email" value="' + esc(state.design.preheader) + '"><p class="wpp-ee-hint">' + esc(t('Shown next to the subject in the inbox.')) + '</p></div>';
		fields += Object.keys(schema.emailSettings || {}).map(function (key) {
			return control(key, schema.emailSettings[key], s[key], 'email');
		}).join('');
		const orders = (cfg.orders || []).map(function (order) {
			return '<option value="' + order.id + '"' + (String(state.orderId) === String(order.id) ? ' selected' : '') + '>#' + esc(order.number) + ' · ' + esc(order.name) + '</option>';
		}).join('');
		fields += '<div class="wpp-ee-field"><label>' + esc(t('Preview with order')) + '</label><select data-act="order"><option value="0">' + esc(t('Sample data')) + '</option>' + orders + '</select></div>';
		return '<div class="wpp-ee-inspector__head"><strong>' + esc(email ? email.title : t('Email')) + '</strong></div>' + note + fields;
	}

	function control(key, rule, value, scope) {
		const label = '<span class="wpp-ee-label">' + esc(t(rule.label)) + '</span>';
		const prop = ' data-prop="' + esc(key) + '" data-scope="' + scope + '"';
		if (rule.control === 'toggle') {
			return '<label class="wpp-ee-check"><span>' + esc(t(rule.label)) + '</span><input type="checkbox"' + prop + (value ? ' checked' : '') + '></label>';
		}
		if (rule.control === 'color') {
			return '<div class="wpp-ee-field">' + label + '<div class="wpp-ee-color"><input type="color"' + prop + ' value="' + esc(value || '#000000') + '"><input type="text"' + prop + ' value="' + esc(value || '') + '" maxlength="7"></div></div>';
		}
		if (rule.control === 'align') {
			const buttons = (rule.values || ['left', 'center', 'right']).map(function (align) {
				return '<button type="button" class="' + (value === align ? 'is-on' : '') + '" data-act="align" data-prop="' + esc(key) + '" data-value="' + align + '" data-scope="' + scope + '">' + esc(t(align.charAt(0).toUpperCase() + align.slice(1))) + '</button>';
			}).join('');
			return '<div class="wpp-ee-field">' + label + '<div class="wpp-ee-align">' + buttons + '</div></div>';
		}
		if (rule.control === 'range') {
			return '<div class="wpp-ee-field">' + label + '<div class="wpp-ee-range"><input type="range"' + prop + ' min="' + rule.min + '" max="' + rule.max + '" value="' + esc(value) + '"><b>' + esc(value) + '</b></div></div>';
		}
		if (rule.control === 'font' || rule.control === 'select') {
			const values = rule.values || (schema.fonts || []).map(function (font) { return font.id; });
			const options = values.map(function (item) {
				const id = typeof item === 'string' ? item : item.id;
				const name = typeof item === 'string' ? (VALUE_LABELS[item] || item) : item.label;
				return '<option value="' + esc(id) + '"' + (value === id ? ' selected' : '') + '>' + esc(t(name)) + '</option>';
			}).join('');
			return '<div class="wpp-ee-field">' + label + '<select' + prop + '>' + options + '</select></div>';
		}
		if (rule.control === 'image') {
			return '<div class="wpp-ee-field">' + label + '<div class="wpp-ee-inline"><input type="url"' + prop + ' value="' + esc(value || '') + '" placeholder="https://"><button type="button" class="wpp-ee-btn" data-act="media" data-prop="' + esc(key) + '" data-scope="' + scope + '">' + esc(t('Media library')) + '</button></div></div>';
		}
		if (rule.control === 'textarea' || rule.control === 'html') {
			return '<div class="wpp-ee-field">' + label + '<textarea' + prop + ' rows="4">' + esc(value || '') + '</textarea>' + tagButtons(key) + '</div>';
		}
		if (rule.control === 'url') {
			return '<div class="wpp-ee-field">' + label + '<input type="text"' + prop + ' value="' + esc(value || '') + '" placeholder="https:// or {order_url}"></div>';
		}
		return '<div class="wpp-ee-field">' + label + '<input type="text"' + prop + ' value="' + esc(value || '') + '"></div>';
	}

	const VALUE_LABELS = {
		latest: 'Latest products',
		featured: 'Featured',
		ids: 'Specific IDs',
		site: 'Site default',
		ru: 'Russian',
		en: 'English',
		left: 'Left',
		center: 'Center',
		right: 'Right',
	};

	function tagButtons(key) {
		const tags = (schema.mergeTags || []).slice(0, 8);
		if (!tags.length) {
			return '';
		}
		return '<div class="wpp-ee-tags">' + tags.map(function (tag) {
			return '<button type="button" data-act="tag" data-tag="' + esc(tag.tag) + '" data-prop="' + esc(key) + '">' + esc(tag.tag) + '</button>';
		}).join('') + '</div>';
	}

	function render() {
		const root = app();
		if (!root) {
			return;
		}
		const sc = root.querySelector('.wpp-ee-stage__scroll');
		const top = sc ? sc.scrollTop : 0;
		root.innerHTML = shell();
		document.body.classList.toggle('wpp-ee-focus', !!state.focus);
		const sheet = root.querySelector('[data-sheet]');
		if (sheet && state.design) {
			state.design.blocks.forEach(function (block) {
				sheet.appendChild(mountBlock(block, false));
			});
		}
		const next = root.querySelector('.wpp-ee-stage__scroll');
		if (next) {
			next.scrollTop = top;
		}
	}

	function setProp(key, value, scope) {
		beforeChange(false);
		if (scope === 'email') {
			state.design.settings = state.design.settings || {};
			if (key === 'subject' || key === 'preheader') {
				state.design[key] = value;
			} else {
				state.design.settings[key] = value;
			}
			const sheet = app().querySelector('[data-sheet]');
			const s = emailSettings();
			if (sheet) {
				sheet.style.maxWidth = s.width + 'px';
				sheet.style.background = s.contentBackground;
				sheet.style.borderRadius = s.radius + 'px';
				sheet.style.fontFamily = s.font;
				sheet.style.color = s.textColor;
			}
			const scroll = app().querySelector('.wpp-ee-stage__scroll');
			if (scroll) {
				scroll.style.background = s.background;
			}
		} else if (state.selected) {
			const block = getBlock(state.selected);
			if (block) {
				block.props[key] = value;
				patchBlock(state.selected);
			}
		}
		scheduleSave();
	}

	function addBlock(type, index) {
		if (!schema.blocks[type] || !state.design) {
			return;
		}
		beforeChange(true);
		const block = createBlock(type);
		if (typeof index === 'number' && index >= 0) {
			state.design.blocks.splice(index, 0, block);
		} else {
			state.design.blocks.push(block);
		}
		state.selected = block.id;
		render();
		scheduleSave();
	}

	function moveBlock(id, index) {
		const from = state.design.blocks.findIndex(function (block) { return block.id === id; });
		if (from < 0) {
			return;
		}
		beforeChange(true);
		const block = state.design.blocks.splice(from, 1)[0];
		const target = index > from ? index - 1 : index;
		state.design.blocks.splice(Math.max(0, target), 0, block);
		render();
		scheduleSave();
	}

	function removeBlock(id) {
		beforeChange(true);
		state.design.blocks = state.design.blocks.filter(function (block) { return block.id !== id; });
		if (state.selected === id) {
			state.selected = null;
		}
		render();
		scheduleSave();
	}

	function duplicateBlock(id) {
		const block = getBlock(id);
		if (!block) {
			return;
		}
		beforeChange(true);
		const copy = clone(block);
		copy.id = uid();
		const index = state.design.blocks.findIndex(function (item) { return item.id === id; });
		state.design.blocks.splice(index + 1, 0, copy);
		state.selected = copy.id;
		render();
		scheduleSave();
	}

	function nudge(id, dir) {
		const index = state.design.blocks.findIndex(function (block) { return block.id === id; });
		const next = index + dir;
		if (index < 0 || next < 0 || next >= state.design.blocks.length) {
			return;
		}
		beforeChange(true);
		const block = state.design.blocks.splice(index, 1)[0];
		state.design.blocks.splice(next, 0, block);
		render();
		scheduleSave();
	}

	function pickImage(key, scope) {
		const apply = function (url) {
			setProp(key, url, scope || 'block');
			render();
		};
		if (window.wp && wp.media) {
			const frame = wp.media({ title: t('Choose image'), multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				apply(frame.state().get('selection').first().toJSON().url);
			});
			frame.open();
			return;
		}
		const url = window.prompt(t('Image URL'), '');
		if (url) {
			apply(url);
		}
	}

	function insertTag(tag, key) {
		const field = app().querySelector('[data-prop="' + key + '"]');
		if (field && (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA')) {
			const start = field.selectionStart || field.value.length;
			field.value = field.value.slice(0, start) + tag + field.value.slice(start);
			field.dispatchEvent(new Event('input', { bubbles: true }));
			field.focus();
			return;
		}
		const ce = state.selected && app().querySelector('[data-block="' + state.selected + '"] [data-ce]');
		if (ce) {
			ce.focus();
			document.execCommand('insertText', false, tag);
			const block = getBlock(state.selected);
			if (block) {
				block.props[ce.dataset.ce === 'html' ? 'html' : ce.dataset.ce] = ce.dataset.ce === 'html' ? ce.innerHTML : ce.textContent;
			}
			scheduleSave();
		}
	}

	function format(cmd) {
		const node = app().querySelector('[data-block="' + state.selected + '"] [data-ce="html"]');
		if (!node) {
			return;
		}
		node.focus();
		let value = null;
		if (cmd === 'createLink') {
			value = window.prompt(t('Link'), 'https://');
			if (!value) {
				return;
			}
		}
		document.execCommand(cmd, false, value);
		const block = getBlock(state.selected);
		if (block) {
			beforeChange(false);
			block.props.html = node.innerHTML;
			scheduleSave();
		}
	}

	function applyBrand() {
		if (!state.design) {
			return;
		}
		beforeChange(true);
		const brand = state.settings;
		state.design.settings.background = brand.background;
		state.design.settings.contentBackground = brand.contentBackground;
		state.design.settings.textColor = brand.text;
		state.design.settings.linkColor = brand.primary;
		state.design.settings.font = brand.font;
		state.design.settings.width = brand.width;
		state.design.settings.radius = brand.radius;
		state.design.blocks.forEach(function (block) {
			if (block.type === 'header') {
				block.props.logoUrl = brand.logoUrl || block.props.logoUrl;
				block.props.logoWidth = brand.logoWidth || block.props.logoWidth;
				block.props.background = brand.headerBackground;
				block.props.color = brand.headerColor;
			}
			if (block.type === 'button') {
				block.props.background = brand.primary;
			}
			if (block.type === 'footer' && brand.footerText) {
				block.props.html = brand.footerText;
			}
		});
		state.menu = false;
		render();
		scheduleSave();
		toast(t('Brand applied'));
	}

	async function resetDesign() {
		if (!window.confirm(t('Reset this email to the starter? The saved design will be deleted.'))) {
			return;
		}
		try {
			if (cfg.demo) {
				const all = demoDesigns();
				delete all[state.emailId];
				writeDemoDesigns(all);
				state.design = starter(state.emailId);
			} else {
				state.design = await api('DELETE', 'designs/' + state.emailId);
			}
			state.dirty = false;
			state.selected = null;
			state.menu = false;
			const email = currentEmail();
			if (email) {
				email.customized = false;
				email.live = false;
			}
			render();
		} catch (err) {
			toast(err.message);
		}
	}

	async function openPreview() {
		state.modal = 'preview';
		state.previewHtml = '';
		state.menu = false;
		renderModal();
		try {
			if (cfg.demo) {
				state.previewHtml = clientDocument();
			} else {
				const res = await api('POST', 'preview', Object.assign({ emailId: state.emailId, orderId: state.orderId }, state.design));
				state.previewHtml = res.html;
				state.previewSubject = res.subject || '';
			}
			renderModal();
		} catch (err) {
			toast(err.message);
		}
	}

	async function sendTest() {
		const to = state.testTo;
		if (!to) {
			toast(t('Enter a valid email address.'));
			return;
		}
		try {
			if (cfg.demo) {
				toast(t('Demo mode does not send email. Install the plugin in WordPress to send a test.'));
				return;
			}
			await api('POST', 'test', Object.assign({ to: to, emailId: state.emailId, orderId: state.orderId }, state.design));
			toast(t('Test sent'));
			closeModal();
		} catch (err) {
			toast(err.message);
		}
	}

	async function openRevisions() {
		state.menu = false;
		state.modal = 'revisions';
		state.revisions = [];
		renderModal();
		if (cfg.demo) {
			state.revisions = [];
			renderModal();
			return;
		}
		try {
			const res = await api('GET', 'designs/' + state.emailId + '/revisions');
			state.revisions = res.revisions || [];
			renderModal();
		} catch (err) {
			toast(err.message);
		}
	}

	async function restoreRevision(id) {
		try {
			state.design = await api('POST', 'designs/' + state.emailId + '/restore', { revisionId: id });
			state.dirty = false;
			state.selected = null;
			closeModal();
			render();
			toast(t('Revision restored'));
		} catch (err) {
			toast(err.message);
		}
	}

	function download(filename, text, type) {
		const blob = new Blob([text], { type: type || 'application/json' });
		const link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = filename;
		link.click();
		setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
	}

	async function exportAll() {
		try {
			const data = cfg.demo
				? { plugin: 'wpp-email-editor', version: cfg.version || '1.0.0', settings: state.settings, designs: demoDesigns() }
				: await api('GET', 'export');
			download('wpp-email-editor.json', JSON.stringify(data, null, 2));
		} catch (err) {
			toast(err.message);
		}
	}

	async function importFile(file) {
		try {
			const data = JSON.parse(await file.text());
			if (cfg.demo) {
				if (data.settings) {
					state.settings = data.settings;
					localStorage.setItem('wpp-ee-demo-settings', JSON.stringify(state.settings));
				}
				if (data.designs) {
					writeDemoDesigns(data.designs);
					state.emails.forEach(function (email) {
						const design = data.designs[email.id];
						email.customized = !!design;
						email.live = !!(design && design.enabled);
					});
				}
			} else {
				const res = await api('POST', 'import', data);
				state.settings = res.settings;
				state.emails = res.emails || state.emails;
			}
			toast(t('Import finished'));
			render();
		} catch (err) {
			toast(err.message || t('That file is not a WPP Email Editor export.'));
		}
	}

	function closeModal() {
		state.modal = null;
		const modal = document.querySelector('.wpp-ee-modal');
		if (modal) {
			modal.remove();
		}
	}

	function renderModal() {
		let modal = document.querySelector('.wpp-ee-modal');
		if (!state.modal) {
			if (modal) {
				modal.remove();
			}
			return;
		}
		if (!modal) {
			modal = document.createElement('div');
			modal.className = 'wpp-ee-modal';
			document.body.appendChild(modal);
			modal.addEventListener('click', onModalClick);
			modal.addEventListener('input', onModalInput);
			modal.addEventListener('change', onModalChange);
		}
		modal.innerHTML = modalHtml();
		const frame = modal.querySelector('iframe');
		if (frame) {
			frame.srcdoc = state.previewHtml || '<p style="font-family:sans-serif;padding:24px;color:#8a837a;">' + esc(t('Opening the email…')) + '</p>';
		}
	}

	function modalHtml() {
		if (state.modal === 'preview') {
			return dialog(t('Preview'), '<div class="wpp-ee-preview"><iframe sandbox="allow-same-origin allow-popups" title="' + esc(t('Preview')) + '"></iframe></div><div class="wpp-ee-split"><button type="button" class="wpp-ee-btn" data-act="download-html">' + esc(t('Download HTML')) + '</button><button type="button" class="wpp-ee-btn-primary" data-act="test">' + esc(t('Send a test')) + '</button></div>', true);
		}
		if (state.modal === 'test') {
			return dialog(t('Send a test'), '<p class="wpp-ee-hint" style="margin-bottom:12px;">' + esc(t('Sends the current design, even if it is not switched on.')) + '</p><div class="wpp-ee-field"><label>' + esc(t('Recipient')) + '</label><input type="email" data-test-to value="' + esc(state.testTo) + '"></div><div class="wpp-ee-split"><button type="button" class="wpp-ee-btn-primary" data-act="send-test">' + esc(t('Send')) + '</button></div>');
		}
		if (state.modal === 'brand') {
			return dialog(t('Brand kit'), brandForm());
		}
		if (state.modal === 'revisions') {
			const list = state.revisions.length
				? '<div class="wpp-ee-revs">' + state.revisions.map(function (rev) {
					return '<button type="button" data-act="restore" data-id="' + rev.id + '"><span>' + esc(rev.createdAt) + ' · ' + esc(rev.author) + '</span><strong>' + esc(t('Restore')) + '</strong></button>';
				}).join('') + '</div>'
				: '<p class="wpp-ee-hint">' + esc(t('No revisions yet. They appear each time you press Save.')) + '</p>';
			return dialog(t('Revisions'), list);
		}
		if (state.modal === 'copy') {
			const options = state.emails.filter(function (email) {
				return email.customized && email.id !== state.emailId;
			}).map(function (email) {
				return '<button type="button" data-act="copy-email" data-id="' + esc(email.id) + '">' + esc(email.title) + '</button>';
			}).join('');
			return dialog(t('Copy design from…'), options ? '<div class="wpp-ee-revs">' + options + '</div>' : '<p class="wpp-ee-hint">' + esc(t('No other saved designs yet.')) + '</p>');
		}
		return '';
	}

	function dialog(title, body) {
		return '<div class="wpp-ee-dialog" role="dialog" aria-modal="true"><header><strong>' + esc(title) + '</strong><button type="button" data-act="close">' + esc(t('Close')) + '</button></header>' + body + '</div>';
	}

	function brandForm() {
		const s = state.settings || {};
		let html = '<div class="wpp-ee-brandgrid">';
		Object.keys(schema.globalSettings || {}).forEach(function (key) {
			if (key === 'social') {
				return;
			}
			html += control(key, schema.globalSettings[key], s[key], 'brand');
		});
		html += '</div><h2 style="margin-top:8px;">' + esc(t('Social links')) + '</h2><div class="wpp-ee-brandgrid">';
		(schema.socialNetworks || []).forEach(function (network) {
			const value = (s.social && s.social[network]) || '';
			html += '<div class="wpp-ee-field"><label>' + esc(network) + '</label><input type="url" data-social="' + esc(network) + '" value="' + esc(value) + '" placeholder="https://"></div>';
		});
		html += '</div><div class="wpp-ee-split"><button type="button" class="wpp-ee-btn-primary" data-act="save-brand">' + esc(t('Save brand')) + '</button></div>';
		return html;
	}

	async function saveBrand() {
		try {
			if (cfg.demo) {
				localStorage.setItem('wpp-ee-demo-settings', JSON.stringify(state.settings));
			} else {
				const res = await api('PUT', 'settings', state.settings);
				state.settings = res.settings;
				cfg.i18n = cfg.i18n;
			}
			closeModal();
			toast(t('Brand saved'));
			if (state.settings.uiLocale) {
				window.location.reload();
			}
		} catch (err) {
			toast(err.message);
		}
	}

	async function copyEmail(id) {
		try {
			let design;
			if (cfg.demo) {
				design = demoDesigns()[id];
			} else {
				design = await api('GET', 'designs/' + id);
			}
			if (!design) {
				return;
			}
			beforeChange(true);
			state.design.blocks = clone(design.blocks || []).map(function (block) {
				block.id = uid();
				return block;
			});
			state.design.settings = clone(design.settings || state.design.settings);
			state.selected = null;
			closeModal();
			render();
			scheduleSave();
		} catch (err) {
			toast(err.message);
		}
	}

	function onClick(e) {
		const act = e.target.closest('[data-act]');
		const add = e.target.closest('[data-add]');
		const open = e.target.closest('[data-open]');
		const block = e.target.closest('[data-block]');
		if (act) {
			if (act.type !== 'checkbox') {
				e.preventDefault();
			}
			runAct(act.dataset.act, act, e);
			return;
		}
		if (add) {
			addBlock(add.dataset.add);
			return;
		}
		if (open) {
			openEmail(open.dataset.open);
			return;
		}
		if (block) {
			const id = block.dataset.block;
			if (state.selected !== id) {
				state.selected = id;
				app().querySelectorAll('.wpp-ee-block').forEach(function (node) {
					node.classList.toggle('is-selected', node.dataset.block === id);
				});
				const insp = app().querySelector('.wpp-ee-inspector');
				if (insp) {
					insp.innerHTML = inspectorHtml();
				}
			}
			return;
		}
		if (state.menu && !e.target.closest('.wpp-ee-menu')) {
			state.menu = false;
			render();
		}
	}

	function runAct(name, node) {
		if (name === 'library') {
			state.view = 'library';
			state.menu = false;
			state.selected = null;
			render();
			return;
		}
		if (name === 'save') {
			saveDesign(true);
			return;
		}
		if (name === 'undo') return undo();
		if (name === 'redo') return redo();
		if (name === 'preview') return openPreview();
		if (name === 'test') {
			state.modal = 'test';
			state.menu = false;
			renderModal();
			return;
		}
		if (name === 'menu') {
			state.menu = !state.menu;
			render();
			return;
		}
		if (name === 'focus') {
			state.focus = !state.focus;
			localStorage.setItem('wpp-ee-focus', state.focus ? '1' : '0');
			state.menu = false;
			render();
			return;
		}
		if (name === 'enable') {
			beforeChange(true);
			state.design.enabled = node.checked;
			markEmail();
			scheduleSave();
			const note = app().querySelector('.wpp-ee-note');
			if (note && !state.selected) {
				render();
			}
			return;
		}
		if (name === 'device') {
			state.device = node.dataset.value;
			render();
			return;
		}
		if (name === 'deselect') {
			state.selected = null;
			render();
			return;
		}
		if (name === 'up' || name === 'down' || name === 'dup' || name === 'del') {
			const id = node.closest('[data-block]').dataset.block;
			if (name === 'up') nudge(id, -1);
			if (name === 'down') nudge(id, 1);
			if (name === 'dup') duplicateBlock(id);
			if (name === 'del') removeBlock(id);
			return;
		}
		if (name === 'align') {
			setProp(node.dataset.prop, node.dataset.value, node.dataset.scope || 'block');
			render();
			return;
		}
		if (name === 'tag') {
			insertTag(node.dataset.tag, node.dataset.prop);
			return;
		}
		if (name === 'format') {
			format(node.dataset.value);
			return;
		}
		if (name === 'media') {
			pickImage(node.dataset.prop, node.dataset.scope || 'block');
			return;
		}
		if (name === 'brand') {
			state.modal = 'brand';
			renderModal();
			return;
		}
		if (name === 'export') return exportAll();
		if (name === 'import') {
			const input = document.getElementById('wpp-ee-import');
			if (input) input.click();
			return;
		}
		if (name === 'reset') return resetDesign();
		if (name === 'revisions') return openRevisions();
		if (name === 'copy-from') {
			state.modal = 'copy';
			state.menu = false;
			renderModal();
			return;
		}
		if (name === 'apply-brand') return applyBrand();
		if (name === 'download-html') {
			const html = state.previewHtml || clientDocument();
			download((state.emailId || 'email') + '.html', html, 'text/html');
			return;
		}
		if (name === 'close') return closeModal();
		if (name === 'send-test') return sendTest();
		if (name === 'save-brand') return saveBrand();
		if (name === 'restore') return restoreRevision(node.dataset.id);
		if (name === 'copy-email') return copyEmail(node.dataset.id);
		if (name === 'order') {
			state.orderId = parseInt(node.value, 10) || 0;
		}
	}

	function onInput(e) {
		const ce = e.target.closest('[data-ce]');
		if (ce) {
			const id = ce.closest('[data-block]').dataset.block;
			const block = getBlock(id);
			if (!block) {
				return;
			}
			beforeChange(false);
			if (ce.dataset.ce === 'html') {
				block.props.html = ce.innerHTML;
			} else {
				block.props[ce.dataset.ce] = ce.textContent;
			}
			scheduleSave();
			return;
		}
		if (e.target.dataset.search != null) {
			state.query = e.target.value;
			const lib = app().querySelector('.wpp-ee-lib');
			if (lib) {
				const top = app().querySelector('.wpp-ee-search');
				const caret = top ? top.selectionStart : 0;
				lib.outerHTML = libraryHtml();
				const again = app().querySelector('.wpp-ee-search');
				if (again) {
					again.focus();
					again.selectionStart = again.selectionEnd = caret;
				}
			}
			return;
		}
		const prop = e.target.closest('[data-prop]');
		if (!prop || prop.type === 'checkbox') {
			return;
		}
		let value = prop.value;
		if (prop.type === 'range' || prop.type === 'number') {
			value = Number(value);
			const badge = prop.parentElement.querySelector('b');
			if (badge) {
				badge.textContent = value;
			}
		}
		const color = prop.closest('.wpp-ee-color');
		if (color) {
			color.querySelectorAll('[data-prop]').forEach(function (el) {
				if (el !== prop) {
					el.value = value;
				}
			});
		}
		setProp(prop.dataset.prop, value, prop.dataset.scope || 'block');
	}

	function onChange(e) {
		if (e.target.id === 'wpp-ee-import' && e.target.files[0]) {
			importFile(e.target.files[0]);
			e.target.value = '';
			return;
		}
		if (e.target.dataset.act === 'order') {
			state.orderId = parseInt(e.target.value, 10) || 0;
			return;
		}
		const prop = e.target.closest('[data-prop]');
		if (!prop) {
			return;
		}
		if (prop.type === 'checkbox') {
			setProp(prop.dataset.prop, prop.checked, prop.dataset.scope || 'block');
			return;
		}
		if (prop.tagName === 'SELECT') {
			setProp(prop.dataset.prop, prop.value, prop.dataset.scope || 'block');
		}
	}

	function onDragStart(e) {
		const add = e.target.closest('[data-add]');
		const handle = e.target.closest('.wpp-ee-drag');
		if (add) {
			drag = { type: add.dataset.add };
		} else if (handle) {
			drag = { id: handle.closest('[data-block]').dataset.block };
		} else {
			return;
		}
		e.dataTransfer.effectAllowed = 'move';
		e.dataTransfer.setData('text/plain', drag.type || drag.id);
	}

	function onDragOver(e) {
		if (!drag) {
			return;
		}
		const block = e.target.closest('[data-block]');
		const sheet = e.target.closest('[data-sheet]');
		if (!block && !sheet) {
			return;
		}
		e.preventDefault();
		app().querySelectorAll('.is-drop').forEach(function (node) { node.classList.remove('is-drop'); });
		if (block) {
			block.classList.add('is-drop');
		}
	}

	function onDrop(e) {
		if (!drag) {
			return;
		}
		e.preventDefault();
		const block = e.target.closest('[data-block]');
		const index = block ? state.design.blocks.findIndex(function (item) { return item.id === block.dataset.block; }) : state.design.blocks.length;
		if (drag.type) {
			addBlock(drag.type, index);
		} else if (drag.id) {
			moveBlock(drag.id, index);
		}
		drag = null;
	}

	function onModalClick(e) {
		if (e.target.classList.contains('wpp-ee-modal')) {
			closeModal();
			return;
		}
		const act = e.target.closest('[data-act]');
		if (!act) {
			return;
		}
		e.preventDefault();
		runAct(act.dataset.act, act, e);
	}

	function onModalInput(e) {
		if (e.target.dataset.testTo != null) {
			state.testTo = e.target.value;
			return;
		}
		if (e.target.dataset.social) {
			state.settings.social = state.settings.social || {};
			state.settings.social[e.target.dataset.social] = e.target.value;
			return;
		}
		const prop = e.target.closest('[data-prop]');
		if (prop && prop.dataset.scope === 'brand') {
			state.settings[prop.dataset.prop] = prop.type === 'checkbox' ? prop.checked : prop.value;
		}
	}

	function onModalChange(e) {
		const prop = e.target.closest('[data-prop]');
		if (prop && prop.dataset.scope === 'brand') {
			state.settings[prop.dataset.prop] = prop.type === 'checkbox' ? prop.checked : prop.value;
		}
	}

	function bind() {
		const root = app();
		root.addEventListener('click', onClick);
		root.addEventListener('input', onInput);
		root.addEventListener('change', onChange);
		root.addEventListener('dragstart', onDragStart);
		root.addEventListener('dragover', onDragOver);
		root.addEventListener('drop', onDrop);
		root.addEventListener('dragend', function () {
			drag = null;
			root.querySelectorAll('.is-drop').forEach(function (node) { node.classList.remove('is-drop'); });
		});
		document.addEventListener('keydown', function (e) {
			const meta = e.metaKey || e.ctrlKey;
			if (meta && e.key.toLowerCase() === 's' && state.view === 'editor') {
				e.preventDefault();
				saveDesign(true);
			}
			if (meta && e.key.toLowerCase() === 'z' && state.view === 'editor') {
				e.preventDefault();
				if (e.shiftKey) {
					redo();
				} else {
					undo();
				}
			}
			if ((e.key === 'Delete' || e.key === 'Backspace') && state.selected && state.view === 'editor') {
				const el = document.activeElement;
				if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable)) {
					return;
				}
				e.preventDefault();
				removeBlock(state.selected);
			}
			if (e.key === 'Escape') {
				if (state.modal) {
					closeModal();
				} else if (state.selected) {
					state.selected = null;
					render();
				}
			}
		});
		window.addEventListener('beforeunload', function (e) {
			if (state.dirty && !cfg.demo) {
				e.preventDefault();
				e.returnValue = '';
			}
		});
	}

	function boot() {
		if (booted || !app()) {
			return;
		}
		booted = true;
		app().classList.add('wpp-ee-app');
		state.focus = localStorage.getItem('wpp-ee-focus') !== '0';
		if (cfg.demo) {
			try {
				const stored = JSON.parse(localStorage.getItem('wpp-ee-demo-settings') || 'null');
				if (stored) {
					state.settings = stored;
				}
			} catch (err) {
				/* keep defaults */
			}
		}
		bind();
		if (state.emailId) {
			openEmail(state.emailId);
		} else {
			render();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
