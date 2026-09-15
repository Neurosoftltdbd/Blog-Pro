<?php
/**
 * Template Name: Service (Tally / Web / Business)
 *
 * Robust single template for all service pages. Reads a `service` page
 * template setting (or page slug) to render the right hero, features,
 * deliverables, FAQ, and CTA. Falls back to a generic services layout.
 *
 * Expected usage:
 *   - Create a Page, set Template = "Service (Tally / Web / Business)".
 *   - In the page editor, set Custom Field `service_id` to one of:
 *       tally-prime, web-development, business-solutions, mobile-apps,
 *       cloud-devops, digital-marketing
 *   - Edit service definitions in `blogpro_service_definitions()` below.
 *
 * @package Blog_Pro
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

/**
 * Service definitions: keyed by service id (slug). Each service declares
 * its own icon, color, hero copy, feature list, deliverables, pricing tiers,
 * and FAQ. Add new services by appending a new key — no template changes
 * required.
 *
 * @return array<string, array>
 */
function blogpro_service_definitions() {
    return apply_filters( 'blogpro_service_definitions', array(
        'tally-prime' => array(
            'label'        => 'Tally Prime',
            'icon'         => '🧾',
            'tagline'      => 'Authorized Tally Partner — sales, implementation & support',
            'description'  => 'Get the most out of Tally Prime with expert setup, data migration, training, and ongoing support from certified partners.',
            'bullets'      => array(
                'Tally Prime licensing & activation',
                'Data migration from Tally.ERP9, Excel, or other tools',
                'Customization: invoices, GST, payroll, MIS reports',
                'On-site and remote training for your team',
                'Annual maintenance & priority support',
            ),
            'deliverables' => array(
                'License procurement with proper invoicing',
                'Company creation, voucher types & ledgers setup',
                'GST configuration (HSN, tax rates, returns)',
                'Bank reconciliation & payment gateway integration',
                'Backup policy & security hardening',
            ),
            'pricing'      => array(
                array( 'name' => 'Silver',  'price' => '₹4,999',  'desc' => 'License + 2 hours training',           'items' => array( 'Tally Prime single user', '2 hr training', 'Email support' ) ),
                array( 'name' => 'Gold',    'price' => '₹14,999', 'desc' => 'Multi-user + full setup',                'items' => array( 'Multi-user license', 'Full data migration', 'GST setup', '4 hr training', 'Phone support' ) ),
                array( 'name' => 'Platinum','price' => 'Custom',  'desc' => 'Enterprise & multi-branch',             'items' => array( 'Unlimited users', 'Multi-branch sync', 'Custom reports', 'Dedicated AM' ) ),
            ),
            'faqs'         => array(
                array( 'q' => 'Do you sell genuine Tally Prime licenses?',  'a' => 'Yes. We are an authorized partner; licenses come with proper GST invoicing and direct manufacturer support.' ),
                array( 'q' => 'Can you migrate data from Tally.ERP9?',     'a' => 'Yes. We migrate companies, masters, vouchers, and reconciliation data. Zero data loss with verification reports.' ),
                array( 'q' => 'Do you provide on-site training?',          'a' => 'Yes. Remote and on-site options are available across major cities. Sessions are recorded for your team.' ),
                array( 'q' => 'Is annual support included?',                'a' => 'Gold and Platinum plans include 12 months of priority support. Silver users can add it for ₹2,999/year.' ),
            ),
        ),
        'web-development' => array(
            'label'        => 'Web Development',
            'icon'         => '💻',
            'tagline'      => 'Modern websites, web apps & e-commerce — built for performance',
            'description'  => 'Custom websites and web applications using WordPress, Next.js, React, and Laravel. SEO-friendly, fast, and maintainable.',
            'bullets'      => array(
                'WordPress themes & custom plugins',
                'Headless WordPress with Next.js / React',
                'Laravel & custom PHP applications',
                'WooCommerce & custom e-commerce',
                'Performance, Core Web Vitals & SEO',
            ),
            'deliverables' => array(
                'Information architecture & UX wireframes',
                'Pixel-perfect responsive implementation',
                'CMS setup with editor-friendly blocks',
                'SEO foundation: schema, sitemap, redirects',
                'Lighthouse 90+ across all four metrics',
            ),
            'pricing'      => array(
                array( 'name' => 'Starter',    'price' => '$499',   'desc' => 'Landing page or 5-page site',     'items' => array( 'Up to 5 pages', 'Mobile responsive', 'Contact form', 'Basic SEO' ) ),
                array( 'name' => 'Business',   'price' => '$1,499', 'desc' => 'WordPress site with blog & CMS',  'items' => array( 'Up to 15 pages', 'Custom blocks', 'Blog + categories', 'Performance tuned' ) ),
                array( 'name' => 'Enterprise', 'price' => 'Custom', 'desc' => 'Headless, e-commerce, or web app', 'items' => array( 'Custom architecture', 'API integrations', 'SLA & support', 'Dedicated team' ) ),
            ),
            'faqs'         => array(
                array( 'q' => 'Which tech stack do you recommend?',  'a' => 'WordPress for content sites, Next.js for headless/marketing, Laravel for custom business apps. We pick based on your goals and team capabilities.' ),
                array( 'q' => 'Do you provide hosting?',             'a' => 'We can deploy on your hosting or recommend managed providers (Cloudways, Kinsta, AWS). We do not resell hosting.' ),
                array( 'q' => 'How long does a typical project take?', 'a' => 'Landing page: 1-2 weeks. Business site: 4-6 weeks. Custom app: 8-16 weeks. We provide a clear timeline after discovery.' ),
                array( 'q' => 'Do you offer post-launch support?',   'a' => 'Yes. Monthly retainers start at $199/mo covering updates, backups, security, and small changes.' ),
            ),
        ),
        'business-solutions' => array(
            'label'        => 'Business Solutions',
            'icon'         => '📊',
            'tagline'      => 'Process automation, MIS, and digital transformation for SMEs',
            'description'  => 'Streamline operations with custom MIS dashboards, workflow automation, CRM/ERP integrations, and digital transformation consulting.',
            'bullets'      => array(
                'MIS dashboards & reporting automation',
                'CRM, ERP, and accounting integrations',
                'Workflow & approval automation',
                'Digital transformation consulting',
                'Internal tools & admin panels',
            ),
            'deliverables' => array(
                'Current-state audit & process mapping',
                'Target architecture & tool selection',
                'Implementation in sprints with demos',
                'User training & documentation',
                '30/60/90-day success metrics',
            ),
            'pricing'      => array(
                array( 'name' => 'Audit',      'price' => '$999',   'desc' => '2-week process audit',          'items' => array( 'Process mapping', 'Tool recommendations', 'Cost-benefit report' ) ),
                array( 'name' => 'Implement',  'price' => 'Custom', 'desc' => 'Build & deploy',                 'items' => array( 'Sprint-based delivery', 'Integrations', 'Training', '90-day support' ) ),
                array( 'name' => 'Retainer',   'price' => '$2,499/mo','desc' => 'Ongoing improvement',         'items' => array( 'Dedicated consultant', 'Monthly reviews', 'Change requests', 'Priority SLA' ) ),
            ),
            'faqs'         => array(
                array( 'q' => 'Which industries do you serve?',     'a' => 'Retail, manufacturing, services, healthcare, and education. We focus on SMEs with 10-500 employees.' ),
                array( 'q' => 'Do you build custom internal tools?', 'a' => 'Yes. We build admin panels, dashboards, and internal apps tailored to your workflows using Laravel or Next.js.' ),
                array( 'q' => 'Can you integrate with our existing software?', 'a' => 'Yes. We have experience with Tally, Zoho, HubSpot, Salesforce, SAP Business One, and custom APIs.' ),
                array( 'q' => 'How do you measure success?',         'a' => 'We define 3-5 KPIs upfront (time saved, error reduction, revenue impact) and report monthly against them.' ),
            ),
        ),
        'mobile-apps' => array(
            'label'        => 'Mobile Apps',
            'icon'         => '📱',
            'tagline'      => 'Native and cross-platform iOS & Android apps',
            'description'  => 'Customer apps, internal tools, and PWAs built with React Native or Flutter. App Store submission included.',
            'bullets'      => array(
                'React Native & Flutter development',
                'REST & GraphQL API integration',
                'Push notifications, auth & analytics',
                'App Store & Play Store submission',
                'PWA alternative for fast launch',
            ),
            'deliverables' => array(
                'UX flow & screen designs',
                'Native builds for iOS & Android',
                'Backend API integration',
                'TestFlight & internal testing',
                'Store submission & launch support',
            ),
            'pricing'      => array(
                array( 'name' => 'MVP',       'price' => '$4,999',  'desc' => '4-6 week MVP',                'items' => array( 'Up to 8 screens', 'Single platform', 'Basic API', 'TestFlight build' ) ),
                array( 'name' => 'Production','price' => '$14,999', 'desc' => 'iOS + Android + backend',     'items' => array( 'Both platforms', 'Push + analytics', 'Store submission', '30-day support' ) ),
                array( 'name' => 'Enterprise','price' => 'Custom',  'desc' => 'Complex apps & integrations', 'items' => array( 'Custom architecture', 'SLA', 'Dedicated team', 'Long-term support' ) ),
            ),
            'faqs'         => array(
                array( 'q' => 'React Native or Flutter?',     'a' => 'Both are excellent. React Native is best for JS teams and web parity; Flutter for complex UI and native performance.' ),
                array( 'q' => 'How much does a typical app cost?', 'a' => 'MVPs start at $4,999. Production apps with backend typically $14,999-$49,999. We quote after discovery.' ),
                array( 'q' => 'Do you handle App Store submission?', 'a' => 'Yes. We handle certificates, profiles, review compliance, and post-launch updates.' ),
            ),
        ),
        'cloud-devops' => array(
            'label'        => 'Cloud & DevOps',
            'icon'         => '☁️',
            'tagline'      => 'AWS, GCP, Azure — infrastructure as code & CI/CD',
            'description'  => 'Cloud architecture, migrations, Kubernetes, Terraform, and CI/CD pipelines. Reliable, observable, and cost-optimized.',
            'bullets'      => array(
                'AWS / GCP / Azure architecture',
                'Terraform & infrastructure as code',
                'Docker & Kubernetes',
                'CI/CD pipelines (GitHub Actions, GitLab)',
                'Monitoring, logging & cost optimization',
            ),
            'deliverables' => array(
                'Architecture diagram & cost estimate',
                'Terraform modules & environments',
                'CI/CD with automated tests',
                'Monitoring & alerting setup',
                'Runbook & handover documentation',
            ),
            'pricing'      => array(
                array( 'name' => 'Setup',     'price' => '$2,999',  'desc' => 'Single project setup',     'items' => array( 'Architecture', 'IaC', 'CI/CD', 'Docs' ) ),
                array( 'name' => 'Migration', 'price' => 'Custom',  'desc' => 'On-prem to cloud',          'items' => array( 'Discovery', 'Migration plan', 'Cutover', 'Hypercare' ) ),
                array( 'name' => 'Retainer',  'price' => '$1,999/mo','desc' => 'Ongoing ops',             'items' => array( '24/7 monitoring', 'Patching', 'Cost review', 'SLA' ) ),
            ),
            'faqs'         => array(
                array( 'q' => 'Which cloud do you recommend?',     'a' => 'It depends on existing stack, team skills, and cost. We are cloud-agnostic and pick the best fit for your workload.' ),
                array( 'q' => 'Do you offer 24/7 support?',         'a' => 'Yes, via retainer. We define response times per severity (P1: 15 min, P2: 1 hour, P3: next business day).' ),
                array( 'q' => 'Can you take over an existing setup?', 'a' => 'Yes. We perform a takeover audit, document the current state, and propose improvements in a 30/60/90 plan.' ),
            ),
        ),
        'digital-marketing' => array(
            'label'        => 'Digital Marketing',
            'icon'         => '📣',
            'tagline'      => 'SEO, content, and paid acquisition for measurable growth',
            'description'  => 'Search-first digital marketing: technical SEO, content strategy, paid campaigns, and analytics. Transparent reporting.',
            'bullets'      => array(
                'Technical & on-page SEO',
                'Content strategy & production',
                'Google Ads & Meta Ads',
                'Analytics & conversion tracking',
                'Monthly performance reporting',
            ),
            'deliverables' => array(
                'SEO audit & keyword research',
                'Content calendar (90 days)',
                'On-page optimization',
                'Ad campaign setup & creatives',
                'Monthly performance report',
            ),
            'pricing'      => array(
                array( 'name' => 'SEO Starter',  'price' => '$499/mo',  'desc' => 'Local SEO',          'items' => array( 'Audit', '4 articles', 'Local citations', 'Monthly report' ) ),
                array( 'name' => 'Growth',       'price' => '$1,499/mo','desc' => 'Full-funnel SEO',     'items' => array( 'Audit', '8 articles', 'Link building', 'Schema & CRO' ) ),
                array( 'name' => 'Performance',  'price' => 'Custom',   'desc' => 'SEO + paid + CRO',    'items' => array( 'Full stack', 'Dedicated AM', 'Quarterly reviews' ) ),
            ),
            'faqs'         => array(
                array( 'q' => 'How long until I see results?', 'a' => 'SEO: 3-6 months for meaningful gains. Paid: results within days. We set realistic expectations upfront.' ),
                array( 'q' => 'Do you guarantee rankings?',     'a' => 'No ethical agency can guarantee rankings. We guarantee process quality, transparency, and compounding results.' ),
                array( 'q' => 'Do you write content?',         'a' => 'Yes. Our writers are subject-matter aware and SEO-trained. We include content in Growth and Performance plans.' ),
            ),
        ),
    ) );
}

/**
 * Resolve the current service id from a custom field, page slug, or fallback.
 */
function blogpro_resolve_service_id() {
    $post_id = get_the_ID();
    $explicit = get_post_meta( $post_id, 'service_id', true );
    if ( $explicit ) {
        return sanitize_title( $explicit );
    }
    $slug = get_post_field( 'post_name', $post_id );
    return $slug ? $slug : 'generic';
}

$service_id = blogpro_resolve_service_id();
$services   = blogpro_service_definitions();
$service    = isset( $services[ $service_id ] ) ? $services[ $service_id ] : null;

// Generic fallback if the slug doesn't match a known service.
$generic = array(
    'label'        => get_the_title(),
    'icon'         => '✨',
    'tagline'      => get_the_excerpt() ?: __( 'Professional services tailored to your goals.', 'blog-pro' ),
    'description'  => get_the_content(),
    'bullets'      => array(),
    'deliverables' => array(),
    'pricing'      => array(),
    'faqs'         => array(),
);

if ( ! $service ) {
    // Strip HTML from description for fallback.
    $generic['description'] = wp_strip_all_tags( $generic['description'] );
    $service = $generic;
    $service['generic'] = true;
} else {
    $service['generic'] = false;
}

$status = function_exists( 'blogpro_contact_form_status' ) ? blogpro_contact_form_status() : '';
?>

<div class="bg-white text-slate-800 antialiased">
  <!-- Hero -->
  <section class="relative overflow-hidden">
    <div class="absolute inset-0 -z-10">
      <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-indigo-100 blur-3xl"></div>
      <div class="absolute top-16 -right-20 h-72 w-72 rounded-full bg-indigo-200 blur-3xl"></div>
    </div>

    <div class="mx-auto max-w-7xl px-4 py-14 sm:py-18">
      <div class="grid lg:grid-cols-12 gap-10 items-center">
        <div class="lg:col-span-7">
          <p class="inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-sm font-semibold text-indigo-700">
            <span class="h-2 w-2 rounded-full bg-indigo-600"></span>
            <?php echo esc_html( $service['label'] ); ?>
          </p>

          <h1 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-slate-900">
            <?php the_title(); ?>
          </h1>

          <p class="mt-5 text-base sm:text-lg text-slate-600 max-w-xl">
            <?php echo esc_html( $service['tagline'] ); ?>
          </p>

          <?php if ( ! empty( $service['bullets'] ) ) : ?>
            <ul class="mt-7 grid sm:grid-cols-2 gap-3 text-sm text-slate-700">
              <?php foreach ( $service['bullets'] as $b ) : ?>
                <li class="flex gap-2">
                  <span class="mt-1 h-2 w-2 rounded-full bg-indigo-600"></span>
                  <?php echo esc_html( $b ); ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <div class="mt-7 flex flex-col sm:flex-row gap-3">
            <a href="#pricing" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm sm:text-base font-semibold text-white hover:bg-indigo-700">
              <?php esc_html_e( 'View pricing', 'blog-pro' ); ?>
            </a>
            <a href="#contact" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3 text-sm sm:text-base font-semibold text-slate-800 hover:bg-slate-50">
              <?php esc_html_e( 'Talk to an expert', 'blog-pro' ); ?>
            </a>
          </div>
        </div>

        <div class="lg:col-span-5">
          <div class="rounded-3xl border border-slate-100 bg-white/80 shadow-sm p-6 sm:p-7">
            <h2 class="text-lg sm:text-xl font-bold text-slate-900">
              <?php printf( esc_html__( 'Get a %s quote', 'blog-pro' ), esc_html( $service['label'] ) ); ?>
            </h2>
            <p class="mt-2 text-sm text-slate-600">
              <?php esc_html_e( 'Tell us what you need and we will respond with next steps.', 'blog-pro' ); ?>
            </p>

            <form class="mt-5 grid gap-3" action="#contact" method="post">
              <div class="grid sm:grid-cols-2 gap-3">
                <label class="block">
                  <span class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'First name', 'blog-pro' ); ?></span>
                  <input type="text" name="first_name" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600" placeholder="<?php esc_attr_e( 'John', 'blog-pro' ); ?>" />
                </label>
                <label class="block">
                  <span class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'Last name', 'blog-pro' ); ?></span>
                  <input type="text" name="last_name" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600" placeholder="<?php esc_attr_e( 'Doe', 'blog-pro' ); ?>" />
                </label>
              </div>

              <label class="block">
                <span class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'Email', 'blog-pro' ); ?></span>
                <input type="email" name="email" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600" placeholder="<?php esc_attr_e( 'you@example.com', 'blog-pro' ); ?>" />
              </label>

              <label class="block">
                <span class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'What do you need?', 'blog-pro' ); ?></span>
                <select name="service" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600">
                  <?php foreach ( array_keys( $services ) as $sid ) : ?>
                    <option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $sid, $service_id ); ?>>
                      <?php echo esc_html( $services[ $sid ]['label'] ); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label class="block">
                <span class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'Message', 'blog-pro' ); ?></span>
                <textarea name="message" rows="3" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600" placeholder="<?php esc_attr_e( 'Briefly describe your project...', 'blog-pro' ); ?>"></textarea>
              </label>

              <button type="submit" class="mt-1 inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                <?php esc_html_e( 'Send request', 'blog-pro' ); ?>
              </button>

              <p class="text-xs text-slate-500">
                <?php esc_html_e( 'By submitting, you agree to be contacted about your request.', 'blog-pro' ); ?>
              </p>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Description / editor content -->
  <section class="mx-auto max-w-7xl px-4 py-10 sm:py-14">
    <div class="grid lg:grid-cols-12 gap-8 items-start">
      <div class="lg:col-span-7">
        <p class="text-sm font-semibold text-indigo-700"><?php esc_html_e( 'Overview', 'blog-pro' ); ?></p>
        <h2 class="mt-2 text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
          <?php printf( esc_html__( 'About our %s service', 'blog-pro' ), esc_html( $service['label'] ) ); ?>
        </h2>
        <div class="prose prose-slate mt-5 max-w-none text-slate-700">
          <?php
          if ( $service['generic'] ) {
              echo '<p>' . esc_html( $service['description'] ) . '</p>';
          } else {
              echo '<p>' . esc_html( $service['description'] ) . '</p>';
              while ( have_posts() ) {
                  the_post();
                  the_content();
              }
          }
          ?>
        </div>
      </div>

      <?php if ( ! empty( $service['deliverables'] ) ) : ?>
        <div class="lg:col-span-5">
          <div class="rounded-3xl border border-slate-100 bg-white p-6 sm:p-7">
            <h3 class="text-lg font-bold text-slate-900"><?php esc_html_e( 'Deliverables', 'blog-pro' ); ?></h3>
            <ul class="mt-4 space-y-3 text-sm text-slate-700">
              <?php foreach ( $service['deliverables'] as $d ) : ?>
                <li class="flex gap-3">
                  <span class="mt-1 h-5 w-5 rounded-full bg-indigo-600 text-white grid place-items-center text-xs font-bold flex-shrink-0">✓</span>
                  <?php echo esc_html( $d ); ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Pricing -->
  <?php if ( ! empty( $service['pricing'] ) ) : ?>
  <section id="pricing" class="mx-auto max-w-7xl px-4 py-10 sm:py-14">
    <div class="text-center max-w-2xl mx-auto">
      <p class="text-sm font-semibold text-indigo-700"><?php esc_html_e( 'Pricing', 'blog-pro' ); ?></p>
      <h2 class="mt-2 text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
        <?php esc_html_e( 'Transparent packages', 'blog-pro' ); ?>
      </h2>
      <p class="mt-3 text-slate-600">
        <?php esc_html_e( 'Pick a plan or request a custom quote.', 'blog-pro' ); ?>
      </p>
    </div>

    <div class="mt-10 grid md:grid-cols-3 gap-5">
      <?php $i = 0; foreach ( $service['pricing'] as $tier ) : $featured = ( 1 === $i ); $i++; ?>
        <div class="rounded-3xl border <?php echo $featured ? 'border-indigo-300 bg-indigo-50 ring-2 ring-indigo-200' : 'border-slate-200 bg-white'; ?> p-6 flex flex-col">
          <div class="text-sm font-semibold text-indigo-700"><?php echo esc_html( $tier['name'] ); ?></div>
          <div class="mt-2 text-3xl font-black text-slate-900"><?php echo esc_html( $tier['price'] ); ?></div>
          <div class="mt-1 text-sm text-slate-600"><?php echo esc_html( $tier['desc'] ); ?></div>
          <ul class="mt-5 space-y-2 text-sm text-slate-700 flex-1">
            <?php foreach ( $tier['items'] as $it ) : ?>
              <li class="flex gap-2">
                <span class="mt-1 h-2 w-2 rounded-full bg-indigo-600 flex-shrink-0"></span>
                <?php echo esc_html( $it ); ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <a href="#contact" class="mt-6 inline-flex w-full justify-center rounded-2xl <?php echo $featured ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-white border border-slate-200 hover:bg-slate-50'; ?> px-4 py-2.5 text-sm font-bold">
            <?php printf( esc_html__( 'Choose %s', 'blog-pro' ), esc_html( $tier['name'] ) ); ?>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- FAQ -->
  <?php if ( ! empty( $service['faqs'] ) ) : ?>
  <section class="mx-auto max-w-4xl px-4 py-10 sm:py-14">
    <div class="text-center max-w-2xl mx-auto">
      <p class="text-sm font-semibold text-indigo-700"><?php esc_html_e( 'FAQ', 'blog-pro' ); ?></p>
      <h2 class="mt-2 text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
        <?php printf( esc_html__( '%s questions', 'blog-pro' ), esc_html( $service['label'] ) ); ?>
      </h2>
    </div>

    <div class="mt-8 space-y-3">
      <?php foreach ( $service['faqs'] as $idx => $faq ) : ?>
        <details class="group rounded-2xl border border-slate-100 bg-white p-5">
          <summary class="cursor-pointer flex items-center justify-between gap-4 font-semibold text-slate-900">
            <span><?php echo esc_html( $faq['q'] ); ?></span>
            <span class="text-indigo-600 transition-transform group-open:rotate-45" aria-hidden="true">+</span>
          </summary>
          <p class="mt-3 text-sm text-slate-600"><?php echo esc_html( $faq['a'] ); ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Contact -->
  <section id="contact" class="mx-auto max-w-7xl px-4 pb-14 sm:pb-20">
    <div class="rounded-3xl border border-slate-100 bg-white shadow-sm p-6 sm:p-10">
      <div class="grid lg:grid-cols-12 gap-8 items-center">
        <div class="lg:col-span-7">
          <p class="text-sm font-semibold text-indigo-700"><?php esc_html_e( 'Contact', 'blog-pro' ); ?></p>
          <h2 class="mt-2 text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
            <?php esc_html_e( 'Ready to get started?', 'blog-pro' ); ?>
          </h2>
          <p class="mt-4 text-slate-600">
            <?php esc_html_e( 'Send your request and we will reply with proposed next steps.', 'blog-pro' ); ?>
          </p>

          <?php if ( 'success' === $status ) : ?>
            <div class="mt-6 rounded-2xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
              <?php esc_html_e( 'Thank you! Your message has been sent.', 'blog-pro' ); ?>
            </div>
          <?php elseif ( 'error' === $status ) : ?>
            <div class="mt-6 rounded-2xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">
              <?php esc_html_e( 'Please fill in all fields correctly.', 'blog-pro' ); ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="lg:col-span-5">
          <form class="grid gap-3" method="POST" action="">
            <?php wp_nonce_field( 'blogpro_contact', 'blogpro_contact_nonce' ); ?>
            <input type="hidden" name="blogpro_contact_submit" value="1">
            <div style="display:none;"><input type="text" name="website_url" tabindex="-1" autocomplete="off"></div>

            <div class="grid sm:grid-cols-2 gap-3">
              <label class="block">
                <span class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'Name', 'blog-pro' ); ?></span>
                <input type="text" name="contact_name" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600" />
              </label>
              <label class="block">
                <span class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'Email', 'blog-pro' ); ?></span>
                <input type="email" name="contact_email" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600" />
              </label>
            </div>

            <label class="block">
              <span class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'Service', 'blog-pro' ); ?></span>
              <input type="text" name="contact_message" value="<?php echo esc_attr( $service['label'] ); ?>" readonly class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-slate-50" />
            </label>

            <label class="block">
              <span class="text-sm font-semibold text-slate-700"><?php esc_html_e( 'Message', 'blog-pro' ); ?></span>
              <textarea name="contact_message" rows="4" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600" placeholder="<?php esc_attr_e( 'Tell us about your goals...', 'blog-pro' ); ?>"></textarea>
            </label>

            <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white hover:bg-indigo-700">
              <?php esc_html_e( 'Submit request', 'blog-pro' ); ?>
            </button>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>

<?php
// Schema.org Service + FAQPage JSON-LD.
$schema = array(
    '@context'    => 'https://schema.org',
    '@type'       => 'Service',
    'name'        => $service['label'],
    'description' => $service['tagline'],
    'provider'    => array(
        '@type' => 'Organization',
        'name'  => get_bloginfo( 'name' ),
        'url'   => home_url( '/' ),
    ),
    'areaServed'  => get_bloginfo( 'name' ),
    'url'         => get_permalink(),
);
echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";

if ( ! empty( $service['faqs'] ) ) {
    $faq_schema = array(
        '@context'    => 'https://schema.org',
        '@type'       => 'FAQPage',
        'mainEntity'  => array_map( function ( $f ) {
            return array(
                '@type'          => 'Question',
                'name'           => $f['q'],
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $f['a'],
                ),
            );
        }, $service['faqs'] ),
    );
    echo '<script type="application/ld+json">' . wp_json_encode( $faq_schema ) . '</script>' . "\n";
}

get_footer();
