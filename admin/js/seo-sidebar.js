/**
 * Blog Pro SEO — Gutenberg editor sidebar panel.
 *
 * Shows the live SEO audit score + findings for the post being edited,
 * without leaving the block editor. Fetches from the theme REST route
 * /blogpro/v1/seo-check/<id> on every post change (debounced).
 *
 * Deps: core packages only (wp.plugins, wp.element, …), no build step.
 */
(function (wp) {
	'use strict';

	var el = wp.element.createElement;
	var registerPlugin = wp.plugins.registerPlugin;
	// PluginSidebar moved from wp.editor to wp.editPost in WP 6.x; both
	// still registered on the dependencies, read from whichever exists.
	var PluginSidebar = (wp.editPost && wp.editPost.PluginSidebar) || wp.editor.PluginSidebar;
	if (!PluginSidebar) {
		return; // old WP — bail silently.
	}
	var Spinner = wp.components.Spinner || function () { return null; };
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;
	var useSelect = (wp.data && wp.data.useSelect) ? wp.data.useSelect : null;

	// Fallback: no hooks — plain class-based approach via useState/useEffect.
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;

	function SeoPanel() {
		var postId = null;
		if (useSelect) {
			postId = useSelect(function (select) {
				var editor = select('core/editor');
				if (!editor) { return null; }
				return editor.getCurrentPostId();
			}, []);
		}

		var state = useState({
			loading: true,
			error: null,
			score: null,
			keyword: '',
			findings: [],
		});
		var result = state[0];
		var setResult = state[1];

		var settings = window.blogproSeoSidebar || {};
		var root = settings.root || '';

		useEffect(function () {
			if (!postId) { return; }
			var t = setTimeout(function () {
				setResult({ loading: true, error: null, score: null, keyword: '', findings: [] });
				apiFetch({
					path: '/blogpro/v1/seo-check/' + postId,
					headers: { 'X-WP-Nonce': settings.nonce },
				}).then(function (data) {
					setResult({ loading: false, error: null, score: data.score, keyword: data.keyword, findings: data.findings || [] });
				}).catch(function (err) {
					setResult({ loading: false, error: (err && err.message) ? err.message : __('Failed to load SEO report.', 'blog-pro'), score: null, keyword: '', findings: [] });
				});
			}, 500); // debounce typing / saves
			return function () { clearTimeout(t); };
		}, [postId]);

		var cls = 'bpseo-sidebar';
		var body = [];
		if (result.loading) {
			body.push(el('p', { key: 'loading' }, el(Spinner)));
		} else if (result.error) {
			body.push(el('p', { key: 'err', style: { color: '#d63638' } }, result.error));
		} else {
			var score = result.score;
			var scls = score >= 80 ? 'ok' : (score >= 60 ? 'mid' : 'bad');
			body.push(
				el('div', { key: 'score', className: cls + '-score ' + scls,
					style: { fontSize: '30px', fontWeight: 700, marginBottom: '4px' } },
					score + '/100')
			);
			body.push(
				el('p', { key: 'kw', style: { margin: '0 0 10px', fontSize: '12px', color: '#646970' } },
					__('Keyword: ', 'blog-pro') + (result.keyword || __('(auto)', 'blog-pro')))
			);
			var items = result.findings || [];
			if (!items.length) {
				body.push(el('p', { key: 'ok', style: { color: '#008a20' } }, __('No issues found — well optimised.', 'blog-pro')));
			} else {
				var list = items.slice(0, 8).map(function (f, i) {
					var color = f.severity === 'error' ? '#d63638' : (f.severity === 'warning' ? '#c77700' : '#2271b1');
					return el('li', { key: i, style: { margin: '6px 0', color: '#3c434a' } },
						el('span', { style: { color: color, fontWeight: 600, textTransform: 'uppercase', fontSize: '10px' } },
							f.severity + ' — '),
						f.message
					);
				});
				body.push(el('ul', { key: 'findings', style: { margin: 0, paddingLeft: '16px', fontSize: '12px', lineHeight: 1.5 } }, list));
				if (items.length > 8) {
					body.push(el('p', { key: 'more', style: { fontSize: '12px', color: '#646970' } }, '+' + (items.length - 8) + ' more'));
				}
			}
			body.push(
				el('p', { key: 'link', style: { marginTop: '12px' } },
					el('a', { href: adminUrl('admin.php?page=blogpro-seo-checker&post_id=' + postId), className: 'button button-small' },
						__('Full report', 'blog-pro')))
			);
		}

		return el('div', { className: cls }, body);
	}

	function adminUrl(path) {
		var base = window.ajaxurl ? window.ajaxurl.replace('admin-ajax.php', '') : '/wp-admin/';
		return base + path;
	}

	registerPlugin('blogpro-seo-sidebar', {
		icon: 'search',
		render: function () {
			return el(
				PluginSidebar,
				{
					name: 'blogpro-seo-sidebar',
					title: __('SEO Check', 'blog-pro'),
					icon: 'search',
				},
				el(SeoPanel)
			);
		},
	});
})(window.wp);
