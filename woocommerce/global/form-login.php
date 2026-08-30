<?php
/**
 * Login form (checkout returning-customer toggle + my-account page) —
 * Tailwind styling.
 *
 * The .woocommerce-form-login class and #username/#password ids are kept —
 * WC's checkout JS slides this form open from the "Returning customer?"
 * notice, and the WP login handler reads those field names.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( is_user_logged_in() ) return;

$input = 'block w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none transition-colors';
?>
<form class="woocommerce-form woocommerce-form-login login bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-6 mb-5" method="post" <?php echo ( $hidden ) ? 'style="display:none;"' : ''; ?>>

	<?php do_action( 'woocommerce_login_form_start' ); ?>

	<?php if ( $message ) : ?>
		<div class="text-sm text-gray-600 mb-4"><?php echo wpautop( wptexturize( $message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	<?php endif; ?>

	<p class="form-row form-row-first mb-4">
		<label for="username" class="block text-sm font-medium text-gray-700 mb-1.5"><?php esc_html_e( 'Username or email', 'woocommerce' ); ?>&nbsp;<span class="required text-red-500" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
		<input type="text" class="input-text <?php echo esc_attr( $input ); ?>" name="username" id="username" autocomplete="username" required aria-required="true" />
	</p>
	<p class="form-row form-row-last mb-4">
		<label for="password" class="block text-sm font-medium text-gray-700 mb-1.5"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required text-red-500" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
		<input class="input-text woocommerce-Input <?php echo esc_attr( $input ); ?>" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
	</p>
	<div class="clear"></div>

	<?php do_action( 'woocommerce_login_form' ); ?>

	<p class="form-row flex flex-wrap items-center gap-3">
		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
			<input class="woocommerce-form__input woocommerce-form__input-checkbox mt-0.5 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
		</label>
		<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
		<input type="hidden" name="redirect" value="<?php echo esc_url( $redirect ); ?>" />
		<button type="submit" class="woocommerce-button button woocommerce-form-login__submit inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" name="login" value="<?php esc_attr_e( 'Login', 'woocommerce' ); ?>"><?php esc_html_e( 'Login', 'woocommerce' ); ?></button>
	</p>
	<p class="lost_password mt-3">
		<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="text-sm text-indigo-600 hover:text-indigo-800"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
	</p>

	<div class="clear"></div>

	<?php do_action( 'woocommerce_login_form_end' ); ?>

</form>
