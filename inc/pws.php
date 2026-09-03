<?php
/**
 * Progressive Web App (PWA) integration – manifest, service worker,
 * offline support, and install prompt. No external libraries.
 *
 * Serves /manifest.json and /sw.js via rewrite rules, enqueues
 * registration script, and provides theme-color meta.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register rewrite rules for PWA assets.
 */
function blogpro_pwa_rewrite_rules() {
	add_rewrite_rule( '^manifest\.json$', 'index.php?blogpro_pwa_manifest=1', 'top' );
	add_rewrite_rule( '^sw\.js$', 'index.php?blogpro_pwa_sw=1', 'top' );
}
add_action( 'init', 'blogpro_pwa_rewrite_rules' );

/**
 * Add query vars for PWA handlers.
 */
add_filter( 'query_vars', function( $vars ) {
	$vars[] = 'blogpro_pwa_manifest';
	$vars[] = 'blogpro_pwa_sw';
	return $vars;
} );

/**
 * Serve manifest.json dynamically.
 */
function blogpro_pwa_serve_manifest() {
	if ( ! get_query_var( 'blogpro_pwa_manifest' ) ) return;

	header( 'Content-Type: application/json; charset=UTF-8' );
	header( 'Cache-Control: public, max-age=86400' );

	$site_name   = get_bloginfo( 'name' );
	$description = get_bloginfo( 'description' );
	$theme_color = get_theme_mod( 'pwa_theme_color', '#1a1a2e' );
	$bg_color    = get_theme_mod( 'pwa_background_color', '#ffffff' );
	$start_url   = home_url( '/' );
	$icon_url    = get_site_icon_url( 512 );
	$icon_192    = get_site_icon_url( 192 );
	$icon_512    = get_site_icon_url( 512 );

	// Fallback icons if no site icon.
	if ( ! $icon_192 ) {
		$icon_192 = get_template_directory_uri() . '/assets/images/icon.png';
		$icon_512 = get_template_directory_uri() . '/assets/images/icon.png';
	}

	$manifest = array(
		'name'             => $site_name,
		'short_name'       => substr( $site_name, 0, 12 ),
		'description'      => $description,
		'start_url'        => $start_url,
		'display'          => 'standalone',
		'orientation'      => 'portrait',
		'theme_color'      => $theme_color,
		'background_color' => $bg_color,
		'icons'            => array(
			array(
				'src'   => $icon_192,
				'sizes' => '192x192',
				'type'  => 'image/png',
			),
			array(
				'src'   => $icon_512,
				'sizes' => '512x512',
				'type'  => 'image/png',
			),
		),
	);

	// Allow filtering.
	$manifest = apply_filters( 'blogpro_pwa_manifest', $manifest );

	echo wp_json_encode( $manifest );
	exit;
}
add_action( 'template_redirect', 'blogpro_pwa_serve_manifest', 1 );

/**
 * Serve service worker (sw.js) dynamically.
 */
function blogpro_pwa_serve_sw() {
	if ( ! get_query_var( 'blogpro_pwa_sw' ) ) return;

	header( 'Content-Type: application/javascript; charset=UTF-8' );
	header( 'Cache-Control: public, max-age=0, must-revalidate' );
	header( 'Service-Worker-Allowed: /' );

	$cache_name    = 'blogpro-pwa-v1';
	$assets        = array(
		get_stylesheet_uri(),
		get_template_directory_uri() . '/assets/css/tailwind.css',
		get_template_directory_uri() . '/assets/js/main.js',
	);
	$offline_fallback = get_theme_mod( 'pwa_offline_page', '' );
	if ( ! $offline_fallback ) {
		$offline_fallback = home_url( '/?pwa_offline=1' );
	}

	// Filterable list of cache assets and offline URL.
	$assets = apply_filters( 'blogpro_pwa_cache_assets', $assets );
	$offline_fallback = apply_filters( 'blogpro_pwa_offline_url', $offline_fallback );

	$js = <<<JS
// Service Worker for Blog Pro PWA
const CACHE_NAME = '{$cache_name}';
const ASSETS = ' . wp_json_encode( $assets ) . ';
const OFFLINE_URL = ' . wp_json_encode( $offline_fallback ) . ';

self.addEventListener('install', (event) => {
	event.waitUntil(
		caches.open(CACHE_NAME).then((cache) => {
			// Pre-cache critical assets.
			return cache.addAll(ASSETS).catch((err) => {
				console.warn('Pre-cache failed:', err);
			});
		}).then(() => self.skipWaiting())
	);
});

self.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys().then((keys) => {
			return Promise.all(
				keys.filter((key) => key !== CACHE_NAME)
					.map((key) => caches.delete(key))
			);
		}).then(() => self.clients.claim())
	);
});

self.addEventListener('fetch', (event) => {
	// Skip non-GET requests and third-party URLs.
	if (event.request.method !== 'GET') return;
	const url = new URL(event.request.url);
	if (url.origin !== self.location.origin) return;

	// HTML navigation requests: network first, fallback to offline page.
	if (event.request.mode === 'navigate') {
		event.respondWith(
			fetch(event.request).catch(() => {
				return caches.match(OFFLINE_URL);
			})
		);
		return;
	}

	// Assets: cache first, then network.
	event.respondWith(
		caches.match(event.request).then((response) => {
			return response || fetch(event.request).then((fetchRes) => {
				return caches.open(CACHE_NAME).then((cache) => {
					cache.put(event.request, fetchRes.clone());
					return fetchRes;
				});
			});
		}).catch(() => {
			// Fallback to offline page for images etc.
			if (event.request.destination === 'image') {
				return caches.match(OFFLINE_URL);
			}
			return new Response('Offline', { status: 503 });
		})
	);
});
JS;

	echo $js;
	exit;
}
add_action( 'template_redirect', 'blogpro_pwa_serve_sw', 1 );

/**
 * Output PWA meta tags in <head>.
 */
function blogpro_pwa_head_meta() {
	$theme_color = get_theme_mod( 'pwa_theme_color', '#1a1a2e' );
	echo '<meta name="theme-color" content="' . esc_attr( $theme_color ) . '">' . "\n";
	echo '<link rel="manifest" href="' . esc_url( home_url( '/manifest.json' ) ) . '">' . "\n";

	// Apple touch icon (fallback).
	$icon = get_site_icon_url( 192 );
	if ( ! $icon ) {
		$icon = get_template_directory_uri() . '/assets/images/icon-192.png';
	}
	echo '<link rel="apple-touch-icon" href="' . esc_url( $icon ) . '">' . "\n";
}
add_action( 'wp_head', 'blogpro_pwa_head_meta', 2 );

/**
 * Enqueue service worker registration script.
 */
function blogpro_pwa_register_script() {
	// Only on frontend.
	if ( is_admin() ) return;

	// Inline registration and install prompt handler.
	$script = <<<JS
// Detect if already installed (standalone mode)
if (window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches) {
	document.body.classList.add('pwa-installed');
}

let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
	e.preventDefault();
	deferredPrompt = e;
	console.log('PWA install prompt available');
	document.body.classList.add('pwa-installable');
	window.dispatchEvent(new CustomEvent('pwa-ready', { detail: { deferredPrompt: e } }));
});

// Service worker registration
if ('serviceWorker' in navigator) {
	navigator.serviceWorker.register('/sw.js', { scope: '/' })
		.then(reg => console.log('SW registered:', reg))
		.catch(err => console.warn('SW registration failed:', err));
}

// Function to trigger install prompt or show fallback instructions.
window.installPWA = function() {
	if (deferredPrompt) {
		deferredPrompt.prompt();
		deferredPrompt.userChoice.then((choiceResult) => {
			if (choiceResult.outcome === 'accepted') {
				console.log('User accepted the install prompt');
			} else {
				console.log('User dismissed the install prompt');
			}
			deferredPrompt = null;
		});
	} else {
		// Fallback for iOS or browsers that don't support beforeinstallprompt.
		alert('To install this app, tap the Share button and select "Add to Home Screen".');
	}
};
JS;
	wp_add_inline_script( 'jquery', $script );

	// CSS: always show the button, hide if already installed.
	$css = <<<CSS
.pwa-install-btn {
	position: fixed;
	bottom: 20px;
	right: 20px;
	z-index: 9999;
	background: #1a1a2e;
	color: #fff;
	border: none;
	padding: 12px 24px;
	border-radius: 30px;
	box-shadow: 0 4px 12px rgba(0,0,0,0.3);
	font-weight: bold;
	cursor: pointer;
	display: block;
}
body.pwa-installed .pwa-install-btn {
	display: none;
}
CSS;
	wp_add_inline_style( 'blogpro-style', $css );
	// Also output a button in footer.
	add_action( 'wp_footer', function() {
		echo '<button id="pwa-install-btn" class="pwa-install-btn" onclick="installPWA()">📱 Install App</button>';
	}, 100 );
}
add_action( 'wp_enqueue_scripts', 'blogpro_pwa_register_script' );

/**
 * Flush rewrite rules on theme activation.
 */
function blogpro_pwa_activate() {
	blogpro_pwa_rewrite_rules();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'blogpro_pwa_activate' );

/**
 * Cleanup on theme deactivation (optional).
 */
function blogpro_pwa_deactivate() {
	flush_rewrite_rules();
}
add_action( 'switch_theme', 'blogpro_pwa_deactivate' );