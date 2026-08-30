<?php
/**
 * Shop / Category / Archive Product Template — redesigned.
 *
 * Structure:
 *   ┌ hero (title + description + product search) ┐
 *   ├ category chips (horizontal, scrollable)     ┤
 *   ├ toolbar: result count · filter pills ·      ┤
 *   │          filter toggle · sort · view switch │
 *   ├ collapsible filter panel (price, sale,      │
 *   │  stock, per-page) — pure GET, no JS needed  │
 *   ├ product grid (or list via view switch)      │
 *   └ pagination                                  │
 *
 * Robustness notes:
 *  - All filtering is server-side via GET params + the
 *    blogpro_wcom_product_query filter (wcom-support.php), so the
 *    page works with JS disabled and survives AJAX failures.
 *  - Result count / sort dropdown come from the loop/result-count.php
 *    + loop/orderby.php template overrides fired by
 *    woocommerce_before_shop_loop — plugins hooking that action still
 *    render.
 *  - Empty state keeps the woocommerce_no_products_found hook.
 *
 * The page wrapper is emitted by wcom-support.php via
 * blogpro_wcom_wrapper_before/after — do not duplicate it here.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

/**
 * woocommerce_before_main_content hook (theme wrapper open + breadcrumb).
 */
do_action( 'woocommerce_before_main_content' );

/* ------------------------------------------------------------------
 * 0. Current query state (single source of truth for chips + form).
 * ---------------------------------------------------------------- */
$bp_min       = isset( $_GET['min_price'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_GET['min_price'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bp_max       = isset( $_GET['max_price'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_GET['max_price'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bp_on_sale   = ! empty( $_GET['filter_on_sale'] );  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bp_in_stock  = ! empty( $_GET['filter_in_stock'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bp_per_page  = isset( $_GET['per_page'] ) ? absint( wp_unslash( $_GET['per_page'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bp_orderby   = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$bp_search    = is_search() ? get_search_query() : '';

/**
 * Helper: current archive URL with selected params removed — used by
 * the removable filter pills and the "Reset" button.
 */
$bp_url_without = function ( $params ) {
	$url = ( is_search() || ! is_paged() ) ? get_pagenum_link( 1 ) : strtok( esc_url( add_query_arg( array() ) ), '#' );
	$url = remove_query_arg( $params );
	// On taxonomy archives add_query_arg keeps the term base; make sure we
	// return to the plain term URL when paging params were stripped.
	if ( is_product_taxonomy() && false === strpos( $url, home_url() ) ) {
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

/* ------------------------------------------------------------------
 * 1. Hero — compact gradient panel with title, description, search.
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
		'<p class="text-indigo-100/90 mt-1">%s</p>',
		esc_html( sprintf( __( 'Search results for: "%s"', 'blog-pro' ), get_search_query() ) )
	);
}
?>
<header class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-purple-700 text-white p-6 md:p-8 mb-6 shadow-sm">
	<div class="absolute inset-0 opacity-20 pointer-events-none" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" class="w-full h-full">
			<defs>
				<pattern id="shop-dots" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
					<circle cx="2" cy="2" r="1" fill="currentColor"/>
				</pattern>
			</defs>
			<rect width="200" height="200" fill="url(#shop-dots)"/>
		</svg>
	</div>

	<div class="relative flex flex-col md:flex-row md:items-end md:justify-between gap-5">
		<div class="min-w-0">
			<p class="text-xs uppercase tracking-widest text-indigo-200 font-semibold mb-1.5">
				<?php
				if ( is_product_taxonomy() ) {
					esc_html_e( 'Browse by category', 'blog-pro' );
				} elseif ( is_search() ) {
					esc_html_e( 'Search', 'blog-pro' );
				} else {
					esc_html_e( 'Shop', 'blog-pro' );
				}
				?>
			</p>
			<h1 class="text-2xl md:text-4xl font-bold tracking-tight text-white"><?php echo esc_html( $archive_title ); ?></h1>
			<?php if ( $archive_description ) : ?>
				<div class="text-indigo-100/90 mt-2 max-w-2xl text-sm [&_a]:underline [&_a]:text-white">
					<?php echo $archive_description; // already kses'd ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! is_search() ) : ?>
			<form role="search" method="get" action="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="relative w-full md:w-72 shrink-0">
				<label class="sr-only" for="shop-search"><?php esc_html_e( 'Search products', 'blog-pro' ); ?></label>
				<input type="search" id="shop-search" name="s" value="<?php echo esc_attr( $bp_search ); ?>"
				       placeholder="<?php esc_attr_e( 'Search products…', 'blog-pro' ); ?>"
				       class="w-full rounded-full bg-white/15 backdrop-blur-sm border border-white/25 text-white placeholder:text-indigo-200 px-4 py-2.5 pr-11 text-sm focus:bg-white focus:text-gray-900 focus:placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-white/60 transition-colors" />
				<input type="hidden" name="post_type" value="product" />
				<button type="submit" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/20 hover:bg-white/35 flex items-center justify-center transition-colors" aria-label="<?php esc_attr_e( 'Search', 'blog-pro' ); ?>">
					<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
				</button>
			</form>
		<?php endif; ?>
	</div>
</header>

<?php
/* ------------------------------------------------------------------
 * 2. Category chips — on shop + any product_cat archive. Horizontally
 *    scrollable on small screens (no wrap → predictable height).
 * ---------------------------------------------------------------- */
$bp_cats = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'parent'     => 0,
	'orderby'    => 'count',
	'order'      => 'DESC',
) );
$bp_current_cat = ( is_product_taxonomy() && is_tax( 'product_cat' ) ) ? get_queried_object_id() : 0;

if ( ! is_wp_error( $bp_cats ) && ! empty( $bp_cats ) ) :
	$chip_base  = 'px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition-colors no-underline shrink-0';
	$chip_off   = 'bg-white text-gray-700 border border-gray-200 hover:border-indigo-300 hover:text-indigo-600';
	$chip_on    = 'bg-indigo-600 text-white border border-indigo-600 shadow-sm';
	?>
	<nav class="blogpro-cat-chips flex gap-2 mb-5 overflow-x-auto pb-1 -mx-1 px-1" aria-label="<?php esc_attr_e( 'Product categories', 'blog-pro' ); ?>">
		<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"
		   class="<?php echo esc_attr( $chip_base . ' ' . ( 0 === $bp_current_cat ? $chip_on : $chip_off ) ); ?>">
			<?php esc_html_e( 'All', 'blog-pro' ); ?>
		</a>
		<?php foreach ( $bp_cats as $cat ) : ?>
			<a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"
			   class="<?php echo esc_attr( $chip_base . ' ' . ( $bp_current_cat === (int) $cat->term_id ? $chip_on : $chip_off ) ); ?>">
				<?php echo esc_html( $cat->name ); ?>
				<span class="ml-1 text-xs opacity-70"><?php echo (int) $cat->count; ?></span>
			</a>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>

<?php if ( woocommerce_product_loop() ) : ?>

	<?php
	/* --------------------------------------------------------------
	 * 3. Active filter pills — removable chips for every GET filter
	 *    currently applied. Pure links, no JS.
	 * ------------------------------------------------------------ */
	$bp_pills = array();
	if ( '' !== $bp_min || '' !== $bp_max ) {
		$label = sprintf(
			/* translators: 1: min price, 2: max price */
			__( 'Price: %1$s – %2$s', 'blog-pro' ),
			'' !== $bp_min ? wc_price( $bp_min ) : '…',
			'' !== $bp_max ? wc_price( $bp_max ) : '…'
		);
		$bp_pills[] = array( $label, $bp_url_without( array( 'min_price', 'max_price' ) ) );
	}
	if ( $bp_on_sale ) {
		$bp_pills[] = array( __( 'On sale', 'blog-pro' ), $bp_url_without( array( 'filter_on_sale' ) ) );
	}
	if ( $bp_in_stock ) {
		$bp_pills[] = array( __( 'In stock', 'blog-pro' ), $bp_url_without( array( 'filter_in_stock' ) ) );
	}
	if ( $bp_per_page ) {
		$bp_pills[] = array(
			/* translators: %d: products per page */
			sprintf( __( '%d / page', 'blog-pro' ), $bp_per_page ),
			$bp_url_without( array( 'per_page' ) ),
		);
	}
	if ( $bp_search ) {
		$bp_pills[] = array(
			/* translators: %s: search query */
			sprintf( __( '“%s”', 'blog-pro' ), $bp_search ),
			$bp_url_without( array( 's', 'post_type' ) ),
		);
	}
	?>

	<?php
	/* --------------------------------------------------------------
	 * 4. Toolbar — result count + sort (via WC hooks, so plugins can
	 *    still append UI) + filter toggle + view switcher.
	 * ------------------------------------------------------------ */
	?>
	<div class="flex flex-wrap items-center justify-between gap-3 mb-5 shop-toolbar">
		<div class="flex items-center gap-3 min-w-0">
			<?php
			/**
			 * woocommerce_before_shop_loop hook
			 * Emits result count (p.20) + sort dropdown (p.30) — both
			 * Tailwind-styled via loop/ template overrides.
			 */
			do_action( 'woocommerce_before_shop_loop' );
			?>
		</div>

		<div class="flex items-center gap-2 shrink-0">
			<button type="button"
			        class="blogpro-filter-toggle inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 hover:border-indigo-300 hover:text-indigo-600 transition-colors"
			        aria-expanded="false" aria-controls="shop-filters">
				<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h1.4a1 1 0 01.8.4l3.6 4.8a1 1 0 010 1.2l-3.6 4.8a1 1 0 01-.8.4H4a1 1 0 01-1-1V4zm0 10a1 1 0 011-1h1.4a1 1 0 01.8.4l3.6 4.8a1 1 0 010 1.2l-3.6 4.8a1 1 0 01-.8.4H4a1 1 0 01-1-1v-6zM21 4h-9m9 8h-5m5 8h-9"/></svg>
				<?php esc_html_e( 'Filters', 'blog-pro' ); ?>
				<?php if ( ! empty( $bp_pills ) ) : ?>
					<span class="ml-0.5 inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-white text-xs font-semibold"><?php echo count( $bp_pills ); ?></span>
				<?php endif; ?>
			</button>

			<!-- View switcher (desktop only; grid is the mobile default) -->
			<div class="hidden sm:flex rounded-lg border border-gray-200 bg-white p-0.5" role="group" aria-label="<?php esc_attr_e( 'View mode', 'blog-pro' ); ?>">
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
			<a href="<?php echo esc_url( $bp_url_without( array( 'min_price', 'max_price', 'filter_on_sale', 'filter_in_stock', 'per_page', 'orderby' ) ) ); ?>" class="text-xs font-medium text-gray-500 hover:text-red-600 underline underline-offset-2 transition-colors">
				<?php esc_html_e( 'Reset all', 'blog-pro' ); ?>
			</a>
		</div>
	<?php endif; ?>

	<?php
	/* --------------------------------------------------------------
	 * 5. Filter panel — plain GET form. Works without JS (always
	 *    visible until the toggle button hides it).
	 * ------------------------------------------------------------ */
	?>
	<div id="shop-filters" class="mb-6">
		<form method="get" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 md:p-5">
			<?php
			// Preserve search + taxonomy context: on a term archive the
			// form posts to the term URL, on shop to the shop URL.
			$action = is_product_taxonomy() && is_tax( 'product_cat' ) ? get_term_link( get_queried_object() ) : wc_get_page_permalink( 'shop' );
			if ( is_wp_error( $action ) ) {
				$action = wc_get_page_permalink( 'shop' );
			}
			?>
			<div class="flex flex-wrap items-end gap-4">
				<fieldset class="flex flex-col gap-1.5">
					<legend class="text-xs font-semibold uppercase tracking-wide text-gray-500"><?php esc_html_e( 'Price range', 'blog-pro' ); ?></legend>
					<div class="flex items-center gap-2">
						<label class="sr-only" for="bp-min-price"><?php esc_html_e( 'Minimum price', 'blog-pro' ); ?></label>
						<input type="number" id="bp-min-price" name="min_price" value="<?php echo esc_attr( $bp_min ); ?>" min="0" step="1"
						       placeholder="<?php esc_attr_e( 'Min', 'blog-pro' ); ?>"
						       class="w-24 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none" />
						<span class="text-gray-300">—</span>
						<label class="sr-only" for="bp-max-price"><?php esc_html_e( 'Maximum price', 'blog-pro' ); ?></label>
						<input type="number" id="bp-max-price" name="max_price" value="<?php echo esc_attr( $bp_max ); ?>" min="0" step="1"
						       placeholder="<?php esc_attr_e( 'Max', 'blog-pro' ); ?>"
						       class="w-24 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none" />
					</div>
				</fieldset>

				<fieldset class="flex flex-col gap-1.5">
					<legend class="text-xs font-semibold uppercase tracking-wide text-gray-500"><?php esc_html_e( 'Availability', 'blog-pro' ); ?></legend>
					<div class="flex items-center gap-4 pt-1">
						<label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
							<input type="checkbox" name="filter_on_sale" value="1" <?php checked( $bp_on_sale ); ?>
							       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
							<?php esc_html_e( 'On sale', 'blog-pro' ); ?>
						</label>
						<label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
							<input type="checkbox" name="filter_in_stock" value="1" <?php checked( $bp_in_stock ); ?>
							       class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
							<?php esc_html_e( 'In stock', 'blog-pro' ); ?>
						</label>
					</div>
				</fieldset>

				<fieldset class="flex flex-col gap-1.5">
					<legend class="text-xs font-semibold uppercase tracking-wide text-gray-500"><?php esc_html_e( 'Per page', 'blog-pro' ); ?></legend>
					<label class="sr-only" for="bp-per-page"><?php esc_html_e( 'Products per page', 'blog-pro' ); ?></label>
					<select id="bp-per-page" name="per_page" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none">
						<option value=""><?php esc_html_e( 'Default', 'blog-pro' ); ?></option>
						<?php foreach ( array( 12, 24, 36, 48 ) as $n ) : ?>
							<option value="<?php echo esc_attr( $n ); ?>" <?php selected( $bp_per_page, $n ); ?>><?php echo esc_html( $n ); ?></option>
						<?php endforeach; ?>
					</select>
				</fieldset>

				<?php if ( $bp_search ) : ?>
					<input type="hidden" name="s" value="<?php echo esc_attr( $bp_search ); ?>" />
					<input type="hidden" name="post_type" value="product" />
				<?php endif; ?>
				<?php if ( $bp_orderby ) : ?>
					<input type="hidden" name="orderby" value="<?php echo esc_attr( $bp_orderby ); ?>" />
				<?php endif; ?>

				<div class="flex items-center gap-2 ml-auto">
					<?php if ( $bp_on_sale || $bp_in_stock || '' !== $bp_min || '' !== $bp_max || $bp_per_page ) : ?>
						<a href="<?php echo esc_url( $bp_url_without( array( 'min_price', 'max_price', 'filter_on_sale', 'filter_in_stock', 'per_page' ) ) ); ?>"
						   class="rounded-lg px-3.5 py-2 text-sm font-medium text-gray-500 hover:text-red-600 no-underline transition-colors"><?php esc_html_e( 'Clear', 'blog-pro' ); ?></a>
					<?php endif; ?>
					<button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">
						<?php esc_html_e( 'Apply', 'blog-pro' ); ?>
					</button>
				</div>
			</div>
		</form>
	</div>

	<?php
	/* --------------------------------------------------------------
	 * 6. Products grid.
	 *    The <ul> is opened manually (not via woocommerce_product_loop_start)
	 *    so the grid can carry the list-view modifier class.
	 * ------------------------------------------------------------ */
	echo '<ul id="blogpro-products" class="products grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5" role="list">';

	if ( wc_get_loop_prop( 'total' ) ) {
		while ( have_posts() ) {
			the_post();
			wc_get_template_part( 'content', 'product' );
		}
	}

	echo '</ul>';

	/**
	 * woocommerce_after_shop_loop hook (pagination + loop end).
	 */
	do_action( 'woocommerce_after_shop_loop' );
	?>

	<?php if ( wc_get_loop_prop( 'total' ) ) : ?>
		<p class="sr-only" role="status" aria-live="polite" id="blogpro-live-count"></p>
	<?php endif; ?>

<?php else : ?>

	<?php
	/* --------------------------------------------------------------
	 * 7. Empty state — with a way back and suggested categories.
	 * ------------------------------------------------------------ */
	?>
	<div class="text-center py-16 px-4 bg-white rounded-2xl border border-gray-100">
		<svg class="mx-auto h-16 w-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
			<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
			      d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
		</svg>
		<h2 class="text-xl font-semibold text-gray-700 mb-2"><?php esc_html_e( 'No products found', 'blog-pro' ); ?></h2>
		<p class="text-gray-500 max-w-md mx-auto mb-6"><?php esc_html_e( 'Try a different category, clear filters, or browse our full catalogue.', 'blog-pro' ); ?></p>
		<div class="flex flex-wrap items-center justify-center gap-3">
			<?php if ( ! empty( $bp_pills ) ) : ?>
				<a href="<?php echo esc_url( $bp_url_without( array( 'min_price', 'max_price', 'filter_on_sale', 'filter_in_stock', 'per_page', 'orderby' ) ) ); ?>"
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
