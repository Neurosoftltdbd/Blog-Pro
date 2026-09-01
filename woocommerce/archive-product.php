<?php
/**
 * Shop / Category / Tag Archive — full sidebar layout.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

/**
 * woocommerce_before_main_content hook (theme wrapper + WC breadcrumb).
 */
do_action( 'woocommerce_before_main_content' );

/* ------------------------------------------------------------------
 * 0. Query state — single source of truth for pills, sidebar, links.
 * ---------------------------------------------------------------- */
$bp_min      = isset( $_GET['min_price'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_GET['min_price'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bp_max      = isset( $_GET['max_price'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_GET['max_price'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bp_on_sale  = ! empty( $_GET['filter_on_sale'] );   // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bp_in_stock = ! empty( $_GET['filter_in_stock'] );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bp_search   = is_search() ? get_search_query() : '';

/** Price bounds for the slider — from the shop's actual catalogue. */
$bp_price_bounds = function () {
	static $bounds = null;
	if ( null === $bounds ) {
		$min = (float) get_transient( 'blogpro_price_min' );
		$max = (float) get_transient( 'blogpro_price_max' );
		if ( ! $min || ! $max ) {
			global $wpdb;
			$row = $wpdb->get_row(
				"SELECT MIN(CAST(meta_value AS DECIMAL(10,2))) AS pmin, MAX(CAST(meta_value AS DECIMAL(10,2))) AS pmax
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_price' AND p.post_status = 'publish' AND p.post_type = 'product'"
			); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$min = $row && $row->pmin ? floor( (float) $row->pmin ) : 0;
			$max = $row && $row->pmax ? ceil( (float) $row->pmax ) : 100;
			set_transient( 'blogpro_price_min', $min, HOUR_IN_SECONDS );
			set_transient( 'blogpro_price_max', $max, HOUR_IN_SECONDS );
		}
		$bounds = array( max( 0, (float) $min ), max( 1, (float) $max ) );
	}
	return $bounds;
};
list( $bp_pmin, $bp_pmax ) = $bp_price_bounds();
$bp_range_min = '' !== $bp_min ? max( $bp_pmin, (float) $bp_min ) : $bp_pmin;
$bp_range_max = '' !== $bp_max ? min( $bp_pmax, (float) $bp_max ) : $bp_pmax;

/**
 * Helper: current archive URL with selected params removed.
 */
$bp_url_without = function ( $params ) {
	$url = ( is_search() || ! is_paged() ) ? get_pagenum_link( 1 ) : strtok( esc_url( add_query_arg( array() ) ), '#' );
	$url = remove_query_arg( $params );
	if ( is_product_taxonomy() && false === strpos( (string) $url, home_url() ) ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$url = get_term_link( $term );
			if ( is_wp_error( $url ) ) {
				$url = wc_get_page_permalink( 'shop' );
			}
		}
	}
	return $url;
};

/**
 * Helper: URL for the current archive with one filter param set to a
 * value (used by attribute links). Always resets paging.
 */
$bp_url_with = function ( $param, $value ) {
	$base = is_product_taxonomy() && is_tax( 'product_cat' )
		? get_term_link( get_queried_object() )
		: ( is_product_taxonomy() ? get_term_link( get_queried_object() ) : wc_get_page_permalink( 'shop' ) );
	if ( is_wp_error( $base ) ) {
		$base = wc_get_page_permalink( 'shop' );
	}
	$url = add_query_arg( array( $param => $value, 'paged' => null ), $base );
	return remove_query_arg( 'paged', $url );
};

/**
 * Chosen attribute terms (WC layered-nav convention: filter_pa_x=a,b).
 */
$bp_chosen_attr = function ( $taxonomy ) {
	$key = 'filter_' . str_replace( 'pa_', '', $taxonomy );
	if ( empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array();
	}
	$raw = sanitize_text_field( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return array_filter( array_map( 'sanitize_title', explode( ',', $raw ) ) );
};

/* ------------------------------------------------------------------
 * 1. Hero — title, description, search.
 * ---------------------------------------------------------------- */
$archive_title       = function_exists( 'woocommerce_page_title' ) ? woocommerce_page_title( false ) : single_term_title( '', false );
$archive_description = '';
if ( is_product_taxonomy() ) {
	$term = get_queried_object();
	if ( $term && ! empty( $term->description ) ) {
		$archive_description = wp_kses_post( wpautop( $term->description ) );
	}
} elseif ( is_search() ) {
	$archive_description = sprintf(
		'<p class="text-gray-500 mt-1">%s</p>',
		esc_html( sprintf( __( 'Search results for: "%s"', 'blog-pro' ), get_search_query() ) )
	);
}
?>
<header class="mb-6">
	<div class="page-title flex items-center justify-center">
		<h1 class="text-2xl md:text-4xl font-bold tracking-tight text-gray-900"><?php echo esc_html( $archive_title ); ?></h1>
			<?php if ( $archive_description ) : ?>
				<div class="text-gray-500 mt-2 max-w-2xl text-sm [&_a]:text-indigo-600 [&_a]:underline">
					<?php echo $archive_description; // kses'd above. ?>
				</div>
			<?php endif; ?>
	</div>
	<div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
		<div class="min-w-0">
			<p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold mb-1">
				<?php
				if ( is_product_taxonomy() ) {
					esc_html_e( 'Browse by category', 'blog-pro' );
				} elseif ( is_search() ) {
					esc_html_e( 'Search', 'blog-pro' );
				} 
				else {
					esc_html_e( 'Shop', 'blog-pro' );
				}
				?>
			</p>
			
		</div>

		<?php if ( ! is_search() ) : ?>
			<?php // Product search must target the site root — WP only honours ?s= there. ?>
			<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="relative w-full md:w-72 shrink-0">
				<label class="sr-only" for="shop-search"><?php esc_html_e( 'Search products', 'blog-pro' ); ?></label>
				<input type="search" id="shop-search" name="s" value="<?php echo esc_attr( $bp_search ); ?>"
				       placeholder="<?php esc_attr_e( 'Search products…', 'blog-pro' ); ?>"
				       class="w-full rounded-full bg-white border border-gray-200 text-gray-900 placeholder:text-gray-400 px-4 py-2.5 pr-11 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none transition-colors shadow-sm" />
				<input type="hidden" name="post_type" value="product" />
				<button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-indigo-50 text-indigo-600 hover:bg-indigo-100 flex items-center justify-center transition-colors" aria-label="<?php esc_attr_e( 'Search', 'blog-pro' ); ?>">
					<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
				</button>
			</form>
		<?php endif; ?>
	</div>
</header>

<?php
/* ------------------------------------------------------------------
 * 2. Sub-category pills — children of the current term (or top-level
 *    cats on /shop/). Horizontally scrollable.
 * ---------------------------------------------------------------- */
$bp_current_cat = ( is_product_taxonomy() && is_tax( 'product_cat' ) ) ? get_queried_object_id() : 0;
$bp_pill_parent = $bp_current_cat ? $bp_current_cat : 0;
$bp_cats = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'parent'     => $bp_pill_parent,
	'orderby'    => 'count',
	'order'      => 'DESC',
) );

if ( ! is_wp_error( $bp_cats ) && ! empty( $bp_cats ) ) :
	$chip_base = 'px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-colors no-underline shrink-0';
	$chip_off  = 'bg-white text-gray-700 border border-gray-200 hover:border-indigo-300 hover:text-indigo-600';
	$chip_on   = 'bg-indigo-600 text-white border border-indigo-600 shadow-sm';
	?>
	<nav class="blogpro-cat-chips flex gap-2 mb-6 overflow-x-auto pb-1 -mx-1 px-1" aria-label="<?php esc_attr_e( 'Product categories', 'blog-pro' ); ?>">
		<?php if ( ! $bp_current_cat ) : ?>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="<?php echo esc_attr( $chip_base . ' ' . $chip_on ); ?>">
				<?php esc_html_e( 'All', 'blog-pro' ); ?>
			</a>
		<?php else : ?>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="<?php echo esc_attr( $chip_base . ' ' . $chip_off ); ?>">
				<?php esc_html_e( 'All', 'blog-pro' ); ?>
			</a>
		<?php endif; ?>
		<?php foreach ( $bp_cats as $cat ) : ?>
			<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"
			   class="<?php echo esc_attr( $chip_base . ' ' . ( $bp_pill_parent === (int) $cat->term_id ? $chip_on : $chip_off ) ); ?>">
				<?php echo esc_html( $cat->name ); ?>
				<span class="ml-1 text-xs opacity-70"><?php echo (int) $cat->count; ?></span>
			</a>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>

<?php if ( woocommerce_product_loop() ) : ?>

	<?php
	/* --------------------------------------------------------------
	 * 3. Active filter pills (removable).
	 * ------------------------------------------------------------ */
	$bp_pills = array();
	if ( '' !== $bp_min || '' !== $bp_max ) {
		$bp_pills[] = array(
			sprintf(
				/* translators: 1: min price, 2: max price */
				__( 'Price: %1$s – %2$s', 'blog-pro' ),
				'' !== $bp_min ? wc_price( $bp_min ) : '…',
				'' !== $bp_max ? wc_price( $bp_max ) : '…'
			),
			$bp_url_without( array( 'min_price', 'max_price' ) ),
		);
	}
	if ( $bp_on_sale ) {
		$bp_pills[] = array( __( 'On sale', 'blog-pro' ), $bp_url_without( array( 'filter_on_sale' ) ) );
	}
	if ( $bp_in_stock ) {
		$bp_pills[] = array( __( 'In stock', 'blog-pro' ), $bp_url_without( array( 'filter_in_stock' ) ) );
	}
	if ( $bp_search ) {
		$bp_pills[] = array(
			/* translators: %s: search query */
			sprintf( __( '“%s”', 'blog-pro' ), $bp_search ),
			$bp_url_without( array( 's', 'post_type' ) ),
		);
	}
	// Attribute pills.
	foreach ( wc_get_attribute_taxonomies() as $bp_attr ) {
		$bp_tax   = 'pa_' . $bp_attr->attribute_name;
		$bp_get_k = 'filter_' . $bp_attr->attribute_name;
		if ( empty( $_GET[ $bp_get_k ] ) ) continue; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		foreach ( $bp_chosen_attr( $bp_tax ) as $bp_slug ) {
			$bp_t = get_term_by( 'slug', $bp_slug, $bp_tax );
			if ( ! $bp_t ) continue;
			$remaining        = array_values( array_diff( $bp_chosen_attr( $bp_tax ), array( $bp_slug ) ) );
			$bp_pills[]       = array(
				esc_html( $bp_t->name ),
				$remaining ? $bp_url_with( $bp_get_k, implode( ',', $remaining ) ) : $bp_url_without( array( $bp_get_k ) ),
			);
		}
	}
	?>

	<!-- ================= LAYOUT: sidebar + main ================= -->
	<div class="flex flex-col md:flex-row gap-6 md:gap-8 items-start">

		<!-- Overlay for the mobile drawer (click = close). -->
		<div id="blogpro-drawer-overlay" class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 md:hidden" aria-hidden="true"></div>

		<?php
		/* ------------------------------------------------------------
		 * 4. SIDEBAR — one markup, two presentations:
		 *    desktop: sticky column · mobile: slide-in drawer.
		 * ---------------------------------------------------------- */
		?>
		<aside id="shop-filters"
		       class="blogpro-filters fixed md:sticky top-0 left-0 z-50 md:z-auto h-full md:h-auto w-80 max-w-[85vw] md:w-1/4 shrink-0
		              overflow-y-auto md:overflow-visible bg-white md:bg-transparent
		              -translate-x-full md:translate-x-0 transition-transform duration-300 ease-out
		              p-4 md:top-24 md:max-h-[calc(100vh-7rem)] md:rounded-2xl md:border md:border-gray-100 md:shadow-sm"
		       aria-label="<?php esc_attr_e( 'Product filters', 'blog-pro' ); ?>">

			<!-- Drawer close button (mobile only) -->
			<div class="flex items-center justify-between mb-4 md:hidden">
				<h2 class="text-base font-semibold text-gray-900"><?php esc_html_e( 'Filters', 'blog-pro' ); ?></h2>
				<button type="button" class="blogpro-drawer-close w-9 h-9 rounded-lg text-gray-500 hover:bg-gray-100 flex items-center justify-center" aria-label="<?php esc_attr_e( 'Close filters', 'blog-pro' ); ?>">
					<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
				</button>
			</div>

			<?php
			// Form action: term archives post to the term URL; product
			// search posts to the site root; everything else to shop.
			if ( is_product_taxonomy() ) {
				$bp_filter_action = get_term_link( get_queried_object() );
				if ( is_wp_error( $bp_filter_action ) ) {
					$bp_filter_action = wc_get_page_permalink( 'shop' );
				}
			} elseif ( is_search() ) {
				$bp_filter_action = home_url( '/' );
			} else {
				$bp_filter_action = wc_get_page_permalink( 'shop' );
			}
			?>
			<form method="get" action="<?php echo esc_url( $bp_filter_action ); ?>" id="blogpro-filter-form" class="space-y-1">
				<?php if ( is_search() ) : ?>
					<input type="hidden" name="s" value="<?php echo esc_attr( $bp_search ); ?>" />
					<input type="hidden" name="post_type" value="product" />
				<?php endif; ?>
				<?php
				// Preserve current sort.
				$bp_cur_orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( $bp_cur_orderby ) :
					?>
					<input type="hidden" name="orderby" value="<?php echo esc_attr( $bp_cur_orderby ); ?>" />
				<?php endif; ?>

				<!-- ---------- Categories accordion ---------- -->
				<details open class="border-b border-gray-100 py-3">
					<summary class="flex items-center justify-between cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
						<span class="text-sm font-semibold text-gray-900"><?php esc_html_e( 'Categories', 'blog-pro' ); ?></span>
						<svg class="w-4 h-4 text-gray-400 transition-transform duration-200 details-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
					</summary>
					<ul class="mt-2 space-y-0.5 text-sm" role="list">
						<li>
							<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"
							   class="flex items-center justify-between rounded-lg px-2.5 py-1.5 no-underline transition-colors <?php echo 0 === $bp_current_cat ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-indigo-600'; ?>">
								<?php esc_html_e( 'All products', 'blog-pro' ); ?>
							</a>
						</li>
						<?php
						$bp_top_cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0 ) );
						if ( ! is_wp_error( $bp_top_cats ) ) {
							$bp_render_cat = function ( $cats, $depth = 0 ) use ( &$bp_render_cat, $bp_current_cat ) {
								foreach ( $cats as $cat ) {
									$active = $bp_current_cat === (int) $cat->term_id;
									printf(
										'<li style="padding-left:%1$drem"><a href="%2$s" class="flex items-center justify-between rounded-lg px-2.5 py-1.5 no-underline transition-colors %3$s">%4$s<span class="text-xs %5$s">%6$d</span></a></li>',
										esc_attr( $depth ),
										esc_url( get_term_link( $cat ) ),
										$active ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-indigo-600',
										esc_html( $cat->name ),
										$active ? 'text-indigo-400' : 'text-gray-400',
										(int) $cat->count
									);
									$children = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => $cat->term_id ) );
									if ( ! is_wp_error( $children ) && $children ) {
										echo '<ul class="ml-1" role="list">';
										$bp_render_cat( $children, $depth + 0.75 );
										echo '</ul>';
									}
								}
							};
							$bp_render_cat( $bp_top_cats );
						}
						?>
					</ul>
				</details>

				<!-- ---------- Price ---------- -->
				<details open class="border-b border-gray-100 py-3">
					<summary class="flex items-center justify-between cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
						<span class="text-sm font-semibold text-gray-900"><?php esc_html_e( 'Price', 'blog-pro' ); ?></span>
						<svg class="w-4 h-4 text-gray-400 transition-transform duration-200 details-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
					</summary>
					<div class="mt-3">
						<!-- Dual-thumb range (two stacked inputs, CSS in wcom-support).
						     The number inputs below carry the form names — the
						     ranges are visual and JS-synced both ways. -->
						<div class="blogpro-price-slider relative h-6 <?php echo $bp_pmax > $bp_pmin ? '' : 'hidden'; ?>">
							<input type="range" id="bp-range-min"
							       class="blogpro-range blogpro-range-min"
							       min="<?php echo esc_attr( $bp_pmin ); ?>" max="<?php echo esc_attr( $bp_pmax ); ?>" step="1"
							       value="<?php echo esc_attr( $bp_range_min ); ?>"
							       aria-label="<?php esc_attr_e( 'Minimum price', 'blog-pro' ); ?>" />
							<input type="range" id="bp-range-max"
							       class="blogpro-range blogpro-range-max"
							       min="<?php echo esc_attr( $bp_pmin ); ?>" max="<?php echo esc_attr( $bp_pmax ); ?>" step="1"
							       value="<?php echo esc_attr( $bp_range_max ); ?>"
							       aria-label="<?php esc_attr_e( 'Maximum price', 'blog-pro' ); ?>" />
						</div>
						<div class="flex items-center gap-2 mt-3">
							<label class="sr-only" for="bp-min-input"><?php esc_html_e( 'Minimum price', 'blog-pro' ); ?></label>
							<input type="number" id="bp-min-input" name="min_price" value="<?php echo esc_attr( $bp_min ); ?>" min="<?php echo esc_attr( $bp_pmin ); ?>" max="<?php echo esc_attr( $bp_pmax ); ?>"
							       placeholder="<?php echo esc_attr( wp_strip_all_tags( wc_price( $bp_pmin ) ) ); ?>"
							       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none" />
							<span class="text-gray-300">—</span>
							<label class="sr-only" for="bp-max-input"><?php esc_html_e( 'Maximum price', 'blog-pro' ); ?></label>
							<input type="number" id="bp-max-input" name="max_price" value="<?php echo esc_attr( $bp_max ); ?>" min="<?php echo esc_attr( $bp_pmin ); ?>" max="<?php echo esc_attr( $bp_pmax ); ?>"
							       placeholder="<?php echo esc_attr( wp_strip_all_tags( wc_price( $bp_pmax ) ) ); ?>"
							       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none" />
						</div>
					</div>
				</details>

				<?php
				/* ---------- Attribute filters (colour / size / brand …) ---------- */
				$bp_attrs = wc_get_attribute_taxonomies();
				foreach ( $bp_attrs as $bp_attr ) :
					$bp_tax      = 'pa_' . $bp_attr->attribute_name;
					// Terms that actually have products (WP term cache —
					// no extra query per page beyond the taxonomy listing).
					$bp_terms    = get_terms( array( 'taxonomy' => $bp_tax, 'hide_empty' => true ) );
					$bp_get_key  = 'filter_' . $bp_attr->attribute_name;
					$bp_chosen   = $bp_chosen_attr( $bp_tax );
					$bp_style    = in_array( $bp_attr->attribute_name, array( 'colour', 'color' ), true ) ? 'swatch'
						: ( in_array( $bp_attr->attribute_name, array( 'size', 'sizes' ), true ) ? 'button' : 'checkbox' );
					if ( is_wp_error( $bp_terms ) || empty( $bp_terms ) ) continue;
					?>
					<details <?php echo $bp_chosen ? 'open' : ''; ?> class="border-b border-gray-100 py-3">
						<summary class="flex items-center justify-between cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
							<span class="text-sm font-semibold text-gray-900"><?php echo esc_html( $bp_attr->attribute_label ); ?></span>
							<svg class="w-4 h-4 text-gray-400 transition-transform duration-200 details-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
						</summary>
						<?php
						// Build toggle URL: add/remove one term slug in the list.
						$bp_toggle_url = function ( $slug ) use ( $bp_chosen, $bp_get_key, $bp_url_with, $bp_url_without ) {
							if ( in_array( $slug, $bp_chosen, true ) ) {
								$remaining = array_values( array_diff( $bp_chosen, array( $slug ) ) );
								return $remaining ? $bp_url_with( $bp_get_key, implode( ',', $remaining ) ) : $bp_url_without( array( $bp_get_key ) );
							}
							return $bp_url_with( $bp_get_key, implode( ',', array_merge( $bp_chosen, array( $slug ) ) ) );
						};
						?>
						<?php if ( 'swatch' === $bp_style ) : ?>
							<div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="<?php echo esc_attr( $bp_attr->attribute_label ); ?>">
								<?php foreach ( $bp_terms as $bp_tid ) :
									$bp_t = get_term( $bp_tid, $bp_tax );
									if ( ! $bp_t ) continue;
									$on = in_array( $bp_t->slug, $bp_chosen, true );
									// Swatch colour: term description (hex) or name-as-CSS-colour fallback.
									$hex = trim( wp_strip_all_tags( (string) $bp_t->description ) );
									$bg  = preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex ) ? $hex : $bp_t->slug;
									?>
									<a href="<?php echo esc_url( $bp_toggle_url( $bp_t->slug ) ); ?>"
									   title="<?php echo esc_attr( $bp_t->name ); ?>"
									   aria-label="<?php echo esc_attr( $bp_t->name ); ?>"
									   aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"
									   class="relative w-7 h-7 rounded-full border border-gray-200 transition-transform hover:scale-110 <?php echo $on ? 'ring-2 ring-indigo-500 ring-offset-2' : ''; ?>"
									   style="background-color: <?php echo esc_attr( $bg ); ?>;">
										<?php if ( $on ) : ?>
											<svg class="absolute inset-0 m-auto w-4 h-4 text-white mix-blend-difference" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
										<?php endif; ?>
									</a>
								<?php endforeach; ?>
							</div>
						<?php elseif ( 'button' === $bp_style ) : ?>
							<div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="<?php echo esc_attr( $bp_attr->attribute_label ); ?>">
								<?php foreach ( $bp_terms as $bp_tid ) :
									$bp_t = get_term( $bp_tid, $bp_tax );
									if ( ! $bp_t ) continue;
									$on = in_array( $bp_t->slug, $bp_chosen, true );
									?>
									<a href="<?php echo esc_url( $bp_toggle_url( $bp_t->slug ) ); ?>"
									   aria-pressed="<?php echo $on ? 'true' : 'false'; ?>"
									   class="min-w-10 inline-flex items-center justify-center rounded-lg border px-3 py-1.5 text-sm font-medium no-underline transition-colors <?php echo $on ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm' : 'bg-white border-gray-200 text-gray-700 hover:border-indigo-300 hover:text-indigo-600'; ?>">
										<?php echo esc_html( $bp_t->name ); ?>
									</a>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<ul class="mt-2 space-y-1" role="list">
								<?php foreach ( $bp_terms as $bp_tid ) :
									$bp_t = get_term( $bp_tid, $bp_tax );
									if ( ! $bp_t ) continue;
									$on = in_array( $bp_t->slug, $bp_chosen, true );
									?>
									<li>
										<a href="<?php echo esc_url( $bp_toggle_url( $bp_t->slug ) ); ?>"
										   class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm no-underline transition-colors <?php echo $on ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-indigo-600'; ?>"
										   aria-pressed="<?php echo $on ? 'true' : 'false'; ?>">
											<span class="w-4 h-4 rounded border flex items-center justify-center <?php echo $on ? 'bg-indigo-600 border-indigo-600' : 'border-gray-300 bg-white'; ?>" aria-hidden="true">
												<?php if ( $on ) : ?>
													<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
												<?php endif; ?>
											</span>
											<?php echo esc_html( $bp_t->name ); ?>
											<span class="ml-auto text-xs text-gray-400"><?php echo (int) $bp_t->count; ?></span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</details>
				<?php endforeach; ?>

				<!-- ---------- Availability ---------- -->
				<details open class="border-b border-gray-100 py-3">
					<summary class="flex items-center justify-between cursor-pointer select-none list-none [&::-webkit-details-marker]:hidden">
						<span class="text-sm font-semibold text-gray-900"><?php esc_html_e( 'Availability', 'blog-pro' ); ?></span>
						<svg class="w-4 h-4 text-gray-400 transition-transform duration-200 details-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
					</summary>
					<div class="mt-3 space-y-2.5">
						<label class="flex items-center justify-between cursor-pointer select-none text-sm text-gray-700">
							<span><?php esc_html_e( 'In stock only', 'blog-pro' ); ?></span>
							<span class="relative inline-flex">
								<input type="checkbox" name="filter_in_stock" value="1" <?php checked( $bp_in_stock ); ?>
								       class="peer sr-only blogpro-switch-input" />
								<span class="w-9 h-5 rounded-full bg-gray-200 peer-checked:bg-indigo-600 transition-colors" aria-hidden="true"></span>
								<span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4 pointer-events-none" aria-hidden="true"></span>
							</span>
						</label>
						<label class="flex items-center justify-between cursor-pointer select-none text-sm text-gray-700">
							<span><?php esc_html_e( 'On sale only', 'blog-pro' ); ?></span>
							<span class="relative inline-flex">
								<input type="checkbox" name="filter_on_sale" value="1" <?php checked( $bp_on_sale ); ?>
								       class="peer sr-only blogpro-switch-input" />
								<span class="w-9 h-5 rounded-full bg-gray-200 peer-checked:bg-indigo-600 transition-colors" aria-hidden="true"></span>
								<span class="absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform peer-checked:translate-x-4 pointer-events-none" aria-hidden="true"></span>
							</span>
						</label>
					</div>
				</details>

				<!-- ---------- Actions ---------- -->
				<div class="flex items-center gap-2 pt-4 pb-2">
					<button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
						<?php esc_html_e( 'Apply filters', 'blog-pro' ); ?>
					</button>
					<a href="<?php echo esc_url( $bp_url_without( array( 'min_price', 'max_price', 'filter_on_sale', 'filter_in_stock', 'orderby' ) ) ); ?>"
					   class="blogpro-clear-all inline-flex items-center justify-center rounded-lg border border-gray-200 px-4 py-2.5 text-sm font-medium text-gray-600 hover:border-red-200 hover:text-red-600 no-underline transition-colors <?php echo empty( $bp_pills ) ? 'hidden' : ''; ?>">
						<?php esc_html_e( 'Clear all', 'blog-pro' ); ?>
					</a>
				</div>
				<?php
				// Attribute filter links are direct <a>s (GET), so they must
				// not be inside the form's submit scope — they are, but as
				// links that's fine; only inputs submit.
				?>
			</form>
		</aside>

		<!-- ================= MAIN COLUMN ================= -->
		<div class="w-full md:w-3/4 min-w-0">

			<?php
			/* ----------------------------------------------------------
			 * 5. Control bar — result count + sort (via WC hooks so
			 *    extensions still render here) + mobile filter button
			 *    + view switcher.
			 * --------------------------------------------------------- */
			?>
			<div class="flex flex-col md:flex-row items-center justify-between gap-4 mb-6 shop-toolbar">
				<div class="flex items-center justify-center gap-2 w-full md:w-auto">
					<?php
					// do_action( 'woocommerce_before_shop_loop' );
					woocommerce_result_count();
					woocommerce_catalog_ordering();
					?>
				</div>

				<div class="flex items-center gap-2 shrink-0">
					<!-- View switcher (desktop only; mobile is always grid) -->
					<div class="flex rounded-lg border border-gray-200 bg-white p-0.5" role="group" aria-label="<?php esc_attr_e( 'View mode', 'blog-pro' ); ?>">
						<button type="button" data-blogpro-view="grid" class="blogpro-view-btn inline-flex items-center justify-center w-8 h-8 rounded-md transition-colors" aria-label="<?php esc_attr_e( 'Grid view', 'blog-pro' ); ?>" aria-pressed="true">
							<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M1 2.5A1.5 1.5 0 012.5 1h3A1.5 1.5 0 017 2.5v3A1.5 1.5 0 015.5 7h-3A1.5 1.5 0 011 5.5v-3zm8 0A1.5 1.5 0 0110.5 1h3A1.5 1.5 0 0115 2.5v3A1.5 1.5 0 0113.5 7h-3A1.5 1.5 0 019 5.5v-3zm-8 8A1.5 1.5 0 012.5 9h3A1.5 1.5 0 017 10.5v3A1.5 1.5 0 015.5 15h-3A1.5 1.5 0 011 13.5v-3zm8 0A1.5 1.5 0 0110.5 9h3a1.5 1.5 0 011.5 1.5v3a1.5 1.5 0 01-1.5 1.5h-3A1.5 1.5 0 019 13.5v-3z"/></svg>
						</button>
						<button type="button" data-blogpro-view="list" class="blogpro-view-btn inline-flex items-center justify-center w-8 h-8 rounded-md transition-colors" aria-label="<?php esc_attr_e( 'List view', 'blog-pro' ); ?>" aria-pressed="false">
							<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M2.5 12a.5.5 0 01.5-.5h10a.5.5 0 010 1H3a.5.5 0 01-.5-.5zm0-4a.5.5 0 01.5-.5h10a.5.5 0 010 1H3a.5.5 0 01-.5-.5zm0-4a.5.5 0 01.5-.5h10a.5.5 0 010 1H3a.5.5 0 01-.5-.5z"/></svg>
						</button>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $bp_pills ) ) : ?>
				<div class="flex flex-wrap items-center gap-2 mb-5" aria-label="<?php esc_attr_e( 'Active filters', 'blog-pro' ); ?>">
					<span class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?php esc_html_e( 'Active:', 'blog-pro' ); ?></span>
					<?php foreach ( $bp_pills as $pill ) : ?>
						<a href="<?php echo esc_url( $pill[1] ); ?>" class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 border border-indigo-200 px-3 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 no-underline transition-colors">
							<?php echo wp_kses_post( $pill[0] ); ?>
							<svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
						</a>
					<?php endforeach; ?>
					<a href="<?php echo esc_url( $bp_url_without( array( 'min_price', 'max_price', 'filter_on_sale', 'filter_in_stock', 'orderby' ) ) ); ?>" class="text-xs font-medium text-gray-500 hover:text-red-600 underline underline-offset-2 transition-colors">
						<?php esc_html_e( 'Reset all', 'blog-pro' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<?php
			/* ----------------------------------------------------------
			 * 6. Products grid.
			 * --------------------------------------------------------- */
			echo '<ul id="blogpro-products" class="products grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" role="list">';

			if ( wc_get_loop_prop( 'total' ) ) {
				while ( have_posts() ) {
					the_post();
					wc_get_template_part( 'content', 'product' );
				}
			}

			echo '</ul>';

			/**
			 * woocommerce_after_shop_loop hook (pagination).
			 */
			do_action( 'woocommerce_after_shop_loop' );
			?>

			<p class="sr-only" role="status" aria-live="polite" id="blogpro-live-count"></p>
		</div>
	</div>

<?php else : ?>

	<?php do_action( 'woocommerce_before_shop_loop' ); ?>

	<div class="text-center py-16 px-4 bg-white rounded-2xl border border-gray-100">
		<svg class="mx-auto h-16 w-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
			      d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
		</svg>
		<h2 class="text-xl font-semibold text-gray-700 mb-2"><?php esc_html_e( 'No products found', 'blog-pro' ); ?></h2>
		<p class="text-gray-500 max-w-md mx-auto mb-6"><?php esc_html_e( 'Try a different category, clear filters, or browse our full catalogue.', 'blog-pro' ); ?></p>
		<div class="flex flex-wrap items-center justify-center gap-3">
			<?php if ( ! empty( $bp_pills ) ) : ?>
				<a href="<?php echo esc_url( $bp_url_without( array( 'min_price', 'max_price', 'filter_on_sale', 'filter_in_stock', 'orderby' ) ) ); ?>"
				   class="inline-flex items-center px-5 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:border-indigo-300 hover:text-indigo-600 no-underline transition-colors">
					<?php esc_html_e( 'Clear filters', 'blog-pro' ); ?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"
			   class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 no-underline transition-colors">
				<?php esc_html_e( 'Browse all products', 'blog-pro' ); ?>
			</a>
		</div>
		<?php do_action( 'woocommerce_no_products_found' ); ?>
	</div>

<?php endif; ?>

<?php
/**
 * woocommerce_after_main_content hook (closes the theme wrapper).
 */
do_action( 'woocommerce_after_main_content' );

get_footer();
