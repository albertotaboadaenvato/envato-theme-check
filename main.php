<?php
/**
 * Functions for displaying theme information, test results, and the form
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Present theme information and test results.
 *
 * @param string $theme_slug theme slug of the theme to be tested.
 */
function check_main( string $theme_slug ): void {
	global $checkcount;

	/**
	 * Get theme data. Return early if the theme is not found.
	 *
	 * @link https://developer.wordpress.org/reference/functions/wp_get_theme/
	 */
	$theme = wp_get_theme( $theme_slug );
	if ( ! $theme->exists() ) {
		return;
	}

	// Run the checks.
	$success = run_themechecks_against_theme( $theme, $theme_slug );

	// Display theme info.
	echo '<h2>' . esc_html__( 'Theme Info', 'theme-check' ) . ': </h2>';
	echo '<div class="theme-info">';

	$screenshot = $theme->get_screenshot( 'relative' );
	if ( $screenshot ) {
		$screenshot_file = $theme->get_stylesheet_directory() . '/' . $screenshot;
		$image_size      = getimagesize( $screenshot_file );
		$image_filesize  = filesize( $screenshot_file );

		echo '<div style="float:right" class="theme-info"><img style="max-height:180px;" src="' . esc_url( $theme->get_screenshot() ) . '" />';
		echo '<br /><div style="text-align:center">' . intval( $image_size[0] ) . 'x' . intval( $image_size[1] ) . ' ' . esc_html( (string) round( $image_filesize / 1024 ) ) . 'k</div></div>';
	}

	echo ( ! empty( $theme['Title'] ) ) ? '<p><label>' . esc_html__( 'Title', 'theme-check' ) . '</label><span class="info">' . esc_html( $theme['Title'] ) . '</span></p>' : '';
	echo ( ! empty( $theme['Version'] ) ) ? '<p><label>' . esc_html__( 'Version', 'theme-check' ) . '</label><span class="info">' . esc_html( $theme['Version'] ) . '</span></p>' : '';
	echo ( ! empty( $theme->get( 'Author' ) ) ) ? '<p><label>' . esc_html__( 'Author', 'theme-check' ) . '</label><span class="info">' . esc_html( $theme->get( 'Author' ) ) . '</span></p>' : '';
	echo ( ! empty( $theme->get( 'AuthorURI' ) ) ) ? '<p><label>' . esc_html__( 'Author URI', 'theme-check' ) . '</label><span class="info"><a href="' . esc_url( $theme->get( 'AuthorURI' ) ) . '">' . esc_html( $theme->get( 'AuthorURI' ) ) . '</a></span></p>' : '';
	echo ( ! empty( $theme->get( 'ThemeURI' ) ) ) ? '<p><label>' . esc_html__( 'Theme URI', 'theme-check' ) . '</label><span class="info"><a href="' . esc_url( $theme->get( 'ThemeURI' ) ) . '">' . esc_html( $theme->get( 'ThemeURI' ) ) . '</a></span></p>' : '';
	/**
	 * License headers come from the extra_theme_headers filter; the stubs only know core headers.
	 *
	 * @var string|false $license
	 */
	$license = $theme->get( 'License' );
	/**
	 * See $license.
	 *
	 * @var string|false $license_uri
	 */
	$license_uri = $theme->get( 'License URI' );
	echo ( ! empty( $license ) ) ? '<p><label>' . esc_html__( 'License', 'theme-check' ) . '</label><span class="info">' . esc_html( $license ) . '</span></p>' : '';
	echo ( ! empty( $license_uri ) ) ? '<p><label>' . esc_html__( 'License URI', 'theme-check' ) . '</label><span class="info">' . esc_html( $license_uri ) . '</span></p>' : '';
	echo ( ! empty( $theme['Tags'] ) ) ? '<p><label>' . esc_html__( 'Tags', 'theme-check' ) . '</label><span class="info">' . esc_html( implode( ', ', $theme['Tags'] ) ) . '</span></p>' : '';
	echo ( ! empty( $theme['Description'] ) ) ? '<p><label>' . esc_html__( 'Description', 'theme-check' ) . '</label><span class="info">' . esc_html( $theme['Description'] ) . '</span></p>' : '';

	if ( $theme->parent() ) {
		echo '<p>';
		printf(
			/* translators: %s: Name of the parent theme. */
			esc_html__( 'This is a child theme. The parent theme is: %s. These files have been included automatically!', 'theme-check' ),
			'<strong>' . esc_html( $theme['Template'] ) . '</strong>'
		);
		echo '</p>';
	}

	echo '</div><!-- .theme-info-->';
	echo '<p>' . sprintf(
		/* translators: 1: number of checks run, 2: theme name */
		esc_html__( 'Running %1$s tests against %2$s.', 'theme-check' ),
		'<strong>' . esc_html( $checkcount ) . '</strong>',
		'<strong>' . esc_html( $theme['Title'] ) . '</strong>'
	) . '</p>';

	$results = display_themechecks();

	if ( ! $success ) {
		/* translators: %s: theme name */
		echo '<h2>' . sprintf( esc_html__( 'One or more errors were found for %1$s.', 'theme-check' ), esc_html( $theme['Title'] ) ) . '</h2>';
	} else {
		/* translators: %s: theme name */
		echo '<h2>' . sprintf( esc_html__( '%1$s passed the tests', 'theme-check' ), esc_html( $theme['Title'] ) ) . '</h2>';
		tc_success();
	}

	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
		echo '<div class="updated">';
		echo '<span class="tc-fail">';
		echo esc_html__( 'WARNING', 'theme-check' );
		echo '</span> ';
		echo '<strong>';
		echo esc_html__( 'WP_DEBUG is not enabled!', 'theme-check' );
		echo '</strong>';
		printf(
			/* translators: %1$s is an opening anchor tag. %2$s is the closing part of the tag. */
			esc_html__( 'Please test your theme with %1$sdebug enabled%2$s before you upload!', 'theme-check' ),
			'<a href="https://wordpress.org/support/article/editing-wp-config-php/">',
			'</a>'
		);
		echo '</div>';
	}

	echo '<div class="tc-box">';
	echo '<ul class="tc-result">';
	echo wp_kses(
		$results,
		array(
			'li'     => array(),
			'span'   => array(
				'class' => array(),
			),
			'strong' => array(),
			'code'   => array(),
			'pre'    => array(),
			'a'      => array(
				'href' => array(),
			),
		)
	);
	echo '</ul></div>';

	do_action( 'themecheck_after_results', $theme_slug );
}

/**
 * Print the introduction shown before a theme is checked.
 */
function tc_intro(): void {
	?>
	<h2><?php esc_html_e( 'About', 'theme-check' ); ?></h2>
	<p><?php esc_html_e( "The Envato Theme Check plugin is an easy way to test your theme and make sure it's up to date with the latest Themeforest review standards. With it, you can run all the same automated testing tools on your theme that Themeforest Reviewers use for theme submissions.", 'theme-check' ); ?></p>
	<?php
}

/**
 * Present information about submitting themes.
 */
function tc_success(): void {
	?>
	<div class="tc-success"><p><?php esc_html_e( 'Now that your theme has passed the basic tests you need to check it properly using the test data before you upload it to the WordPress Themes Directory.', 'theme-check' ); ?></p>
	<p>
		<?php
		printf(
			/* translators: %1$s is an opening anchor tag. %2$s is the closing part of the tag. */
			esc_html__( 'Make sure to review the guidelines at %1$sTheme Review%2$s before uploading a Theme.', 'theme-check' ),
			'<a href="https://make.wordpress.org/themes/handbook/review/required/">',
			'</a>'
		);
		?>
	</p>
	<h3><?php esc_html_e( 'Useful Links', 'theme-check' ); ?></h3>
	<ul>
	<li><a href="https://developer.wordpress.org/themes/"><?php esc_html_e( 'Theme Handbook', 'theme-check' ); ?></a></li>
	<li><a href="https://wordpress.org/support/forum/wp-advanced/"><?php esc_html_e( 'Developing with WordPress Forum', 'theme-check' ); ?></a></li>
	<li><a href="https://github.com/WPTRT/theme-unit-test"><?php esc_html_e( 'Theme Unit Tests', 'theme-check' ); ?></a></li>
	</ul></div>
	<?php
}

/**
 * Print the form for selecting the theme to test.
 */
function tc_form(): void {
	echo '<form action="themes.php?page=themecheck" method="post">';
	echo '<select name="themename">';

	// Only pre-selects the dropdown; themecheck_do_page() runs check_admin_referer( 'themecheck-nonce' ) before any check runs.
	$selected_theme = isset( $_POST['themename'] ) ? sanitize_text_field( wp_unslash( $_POST['themename'] ) ) : get_stylesheet(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	foreach ( wp_get_themes() as $theme ) {
		printf(
			'<option %s value="%s">%s</option>',
			selected( $selected_theme, $theme['Stylesheet'], false ),
			esc_attr( $theme['Stylesheet'] ),
			esc_html( $theme['Name'] )
		);
	}

	echo '</select>';

	echo '<input class="button" type="submit" value="' . esc_attr__( 'Check it!', 'theme-check' ) . '" />';
	if ( defined( 'TC_PRE' ) || defined( 'TC_POST' ) ) {
		echo ' <input name="trac" type="checkbox" /> ' . esc_html__( 'Output in Trac format.', 'theme-check' );
	}
	echo '<input name="s_info" type="checkbox" /> ' . esc_html__( 'Suppress INFO.', 'theme-check' );
	wp_nonce_field( 'themecheck-nonce' );
	echo '</form>';
}

