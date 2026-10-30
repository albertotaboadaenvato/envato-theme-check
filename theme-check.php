<?php
/**
 * Plugin Name: Envato Theme Check
 * Plugin URI: https://github.com/envato/Envato-Theme-Check
 * Description: Envato Theme Check is a modified fork of the original Theme Check by Otto42 with additional Themeforest specific WordPress checks.
 * Author: Scott Parry
 * Author URI: https://envato.com
 * Version: 2.1.0
 * Requires PHP: 8.1
 * Text Domain: theme-check
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Theme Check
 */

declare(strict_types=1);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include 'theme-check-cli.php';
}

/**
 * Admin page bootstrap: menu entry, styles and the Theme Check screen.
 */
class EnvatoThemeCheck {
	/**
	 * Hook the admin page.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'tc_i18n' ) );
		add_action( 'admin_menu', array( $this, 'themecheck_add_page' ) );
	}

	/**
	 * Load translations.
	 */
	public function tc_i18n(): void {
		load_plugin_textdomain( 'theme-check', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}

	/**
	 * Enqueue the Theme Check screen stylesheet.
	 */
	public function load_styles(): void {
		wp_enqueue_style( 'style', plugins_url( 'assets/style.css', __FILE__ ), array(), '1', 'screen' );
	}

	/**
	 * Register Appearance > Theme Check.
	 */
	public function themecheck_add_page(): void {
		$page = add_theme_page( 'Theme Check', 'Theme Check', 'manage_options', 'themecheck', array( $this, 'themecheck_do_page' ) );
		add_action( 'admin_print_styles-' . $page, array( $this, 'load_styles' ) );
	}

	/**
	 * Extra style.css headers read by the checks (`extra_theme_headers` filter).
	 *
	 * @param mixed $extra_headers Headers from other filters (replaced, as before).
	 * @return list<string>
	 */
	public function tc_add_headers( mixed $extra_headers ): array {
		$extra_headers = array( 'License', 'License URI', 'Template Version' );
		return $extra_headers;
	}

	/**
	 * Render the Theme Check screen and, on submit, run the checks.
	 */
	public function themecheck_do_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'theme-check' ) );
		}

		add_filter( 'extra_theme_headers', array( $this, 'tc_add_headers' ) );

		include 'checkbase.php';
		include 'main.php';

		?>
		<div id="theme-check" class="wrap">
		<h1><?php echo esc_html_x( 'Theme Check', 'title of the main page', 'theme-check' ); ?></h1>
		<div class="theme-check">
		<?php
		tc_form();
		if ( ! isset( $_POST['themename'] ) ) {
			tc_intro();
		}

		if ( isset( $_POST['themename'] ) ) {
			check_admin_referer( 'themecheck-nonce' );

			if ( isset( $_POST['trac'] ) ) {
				define( 'TC_TRAC', true );
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- theme directory name; check_main() validates it via wp_get_theme()->exists().
			check_main( (string) wp_unslash( $_POST['themename'] ) );
		}
		?>
		</div> <!-- .theme-check-->
		</div>
		<?php
	}
}
new EnvatoThemeCheck();
