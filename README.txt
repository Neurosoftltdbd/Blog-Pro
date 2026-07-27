BLOG PRO — a plugin-free, speed-first WordPress blogging theme
================================================================

INSTALLATION
1. Zip the "blog-pro" folder (or use the .zip you already have) and upload
   via WordPress Admin → Appearance → Themes → Add New → Upload Theme.
2. Activate "Blog Pro". Activation automatically registers the /sitemap.xml
   rewrite rule.
3. Go to Settings → Permalinks and click "Save" once (forces WordPress to
   flush rewrite rules so /sitemap.xml and clean post URLs work).
4. Go to Settings → General and fill in Site Title + Tagline — these feed
   directly into the homepage hero, meta description fallback, and JSON-LD.

CREATING THE CORE PAGES
- Home: Settings → Reading → set "Your homepage displays" to a static page,
  OR simply leave it on "Your latest posts" — front-page.php only activates
  if you assign a static front page. For the full hero+featured+recent
  layout shown in this theme, create a page (any title) and set it as the
  static front page.
- Blog: create a page (e.g. titled "Blog"), and set it as the "Posts page"
  in Settings → Reading. index.php renders it.
- About: create a page, then in the page editor's Page Attributes panel
  set Template = "About Page".
- Contact: create a page, set Template = "Contact Page". The contact form
  works immediately — no plugin, no API key — via wp_mail().

OPTIMIZING IMAGES UPLOADED BEFORE THIS THEME
WebP conversion and the theme's own thumbnail sizes (blogpro-card,
blogpro-hero) only apply automatically to images uploaded AFTER the
theme is active — that's how WordPress's upload hooks work. For
anything already in your Media Library from before, go to
Media → Optimize Images and click "Start Optimizing". It processes
your library in small batches (safe for shared hosting timeouts),
regenerating thumbnail sizes and creating .webp copies. It's safe to
re-run anytime — already-optimized images are detected and skipped,
so re-running after adding a batch of old images only processes the
new ones.

FEATURED POSTS
Tag any post "featured" (the built-in WordPress tag field) and it will
appear in the homepage's Featured Posts section and the
/wp-json/blogpro/v1/posts/featured REST endpoint.

SEO — WHAT'S BUILT IN, NO PLUGIN NEEDED
- Dynamic <title>, meta description, canonical URL, Open Graph, Twitter
  Card tags on every template (inc/seo-meta.php).
- JSON-LD schema.org structured data: WebSite, Organization, BreadcrumbList,
  and BlogPosting on every post (inc/schema.php) — this also helps AI
  answer engines (ChatGPT, Perplexity, Google AI Overviews) cite your
  content accurately (GEO).
- XML sitemap at yourdomain.com/sitemap.xml (inc/sitemap.php).
- robots.txt served automatically at yourdomain.com/robots.txt, pointing
  crawlers at the sitemap (inc/robots.php).
- Per-post SEO override: set post meta `_blogpro_meta_title` and
  `_blogpro_meta_description` if you ever want manual control on a
  specific post (optional — good defaults are computed automatically).

CUSTOM REST API (inc/rest-api.php)
  GET /wp-json/blogpro/v1/posts?page=1&per_page=10&category=news&search=x
  GET /wp-json/blogpro/v1/posts/{id}
  GET /wp-json/blogpro/v1/posts/featured
  GET /wp-json/blogpro/v1/categories
This returns a slimmer payload than WordPress core's /wp/v2/posts —
purpose-built for a fast blog front end or a future headless/mobile app.

SPEED — HOW SUB-1-SECOND LOADS ARE ACHIEVED
1. No plugins, no page builder, no jQuery dependency — theme JS is one
   small vanilla file, loaded with `defer`.
2. Unused core assets are dequeued (block-library CSS, emoji script,
   embed script, oEmbed discovery, RSD/wlwmanifest links, XML-RPC).
3. Images: WebP auto-generated on upload (falls back to JPEG/PNG
   automatically if the server's GD build lacks WebP support), responsive
   srcset with layout-accurate `sizes`, lazy-loading + async decode on
   every image except the single post's featured image, which loads
   eagerly with `fetchpriority="high"` so it becomes the fast LCP element.
4. Video: self-hosted <video> uses `preload="metadata"`; embedded iframes
   (YouTube etc.) are lazy-loaded.
5. Upload cap: images over 1600px are auto-downsized on upload so nobody
   accidentally ships a 6000px camera photo.
6. Static assets: gzip compression, far-future browser caching, and a
   couple of security headers are written into your site's root
   .htaccess AUTOMATICALLY when the theme is activated (inc/htaccess.php),
   using WordPress's own insert_with_markers() — the same safe mechanism
   WP uses for its own rewrite rules. It lives inside clearly marked
   "# BEGIN Blog Pro Performance" / "# END Blog Pro Performance" comments,
   so it won't touch anything else in the file, and it's removed cleanly
   if you switch to a different theme. On Nginx (no .htaccess support)
   this step is skipped automatically — use the Nginx block in
   `.htaccess-blogpro` instead, added to your server{} config by hand.
   Note: this requires the web server user to have write permission on
   your site's root .htaccess file — most standard hosts allow this,
   but some locked-down hosts don't; if the file isn't writable, nothing
   happens and you can still add the rules manually from
   `.htaccess-blogpro`.
7. Fewer, smaller HTTP requests: no external font/icon/CSS framework
   requests — the theme ships one compact stylesheet.

RECOMMENDED HOSTING-LEVEL ADDITIONS (outside theme scope)
- Use a host with HTTP/2 or HTTP/3 and server-side page caching
  (e.g. object cache / Redis, or a reverse-proxy cache) for logged-out
  traffic — this theme is built to work with or without one.
- Serve the whole site over a CDN if your audience is geographically
  spread out.
- Use modern image formats and compress uploads before adding them to
  the Media Library — the built-in WebP conversion helps, but starting
  with a well-compressed source file still matters most.

FILE MAP
  functions.php            theme bootstrap — loads everything below
  inc/performance.php       head cleanup, dequeues, resource hints
  inc/seo-meta.php          meta tags
  inc/schema.php             JSON-LD
  inc/sitemap.php            /sitemap.xml
  inc/robots.php             /robots.txt
  inc/rest-api.php           /wp-json/blogpro/v1/*
  inc/media-optimize.php     image/video optimization
  inc/admin-tools.php         bulk-optimize existing images (Media → Optimize Images)
  inc/template-tags.php      reading time, related posts, breadcrumbs
  inc/contact-form.php       wp_mail()-based contact handler
  header.php / footer.php    global chrome
  front-page.php             home page
  index.php                  blog listing / archives / search results
  single.php / comments.php  single post + comment form
  page.php                   generic page
  page-about.php              About template
  page-contact.php            Contact template
  404.php                     not found
  style.css                   all theme CSS (theme header lives here too)
  js/main.js                  mobile nav toggle + lazy-load fallback
  js/admin-optimize-images.js bulk image optimizer progress bar
  .htaccess-blogpro           optional Apache/Nginx caching rules
