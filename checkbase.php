<?php
/**
 * Runs checks against themes and displays the results
 *
 * Runs checks against themes and displays the results. Includes helper functions
 * for performing checks.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// main global to hold our checks.
global $themechecks;
$themechecks = array();

// counter for the checks.
global $checkcount;
$checkcount = 0;

// current WP_Theme being tested. Internal use only.
global $theme_check_current_theme;
$theme_check_current_theme = false;

// interface that all checks should implement.
interface themecheck {

	/**
	 * Run the check. Parameter types stay undeclared so existing implementations remain compatible.
	 *
	 * @param array<string, string> $php_files   PHP file contents keyed by path.
	 * @param array<string, string> $css_files   CSS file contents keyed by path.
	 * @param array<string, string> $other_files Other file contents keyed by path.
	 * @return bool True for good/okay/acceptable, false for bad/not-okay/unacceptable.
	 */
	public function check( $php_files, $css_files, $other_files ): bool;

	/**
	 * Problems found by the last check() run.
	 *
	 * @return array<int|string, string> Messages explaining any problems found.
	 */
	public function getError(): array; // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- interface implemented by every check.
}

// load all the checks in the checks directory.
foreach ( glob( __DIR__ . '/checks/*.php' ) as $file ) {
	include $file;
}

do_action( 'themecheck_checks_loaded' );

/**
 * Suffix appended to a finding message; extensions add one via the `themecheck_finding_suffix` filter.
 *
 * @param string $key Check id (class file name minus `class-`/`-check`), or `<check-id>/<finding_key>`.
 * @return string '' unless a callback returns a suffix.
 */
function tc_finding_suffix( string $key ): string {
	return (string) apply_filters( 'themecheck_finding_suffix', '', $key );
}

/**
 * Collect and prepare a theme's files for the checks. Shared by the admin and WP-CLI runners.
 *
 * Hidden folders need no filter here: WP_Theme::get_files() (scandir) already skips
 * dot-prefixed entries, so Directories_Check can only fire on hidden names surfaced another way.
 *
 * @param WP_Theme $theme A WP_Theme instance.
 * @return array{php: array<string, string>, css: array<string, string>, other: array<string, string>} Contents keyed by absolute path.
 */
function tc_collect_theme_files( WP_Theme $theme ): array {
	wp_raise_memory_limit( 'admin' );

	$files = $theme->get_files(
		null, /* all file types */
		-1, /* infinite recursion */
		true /* include parent theme files */
	);
	unset( $files[0] ); // Work around https://core.trac.wordpress.org/ticket/53599.

	$collected = array(
		'php'   => array(),
		'css'   => array(),
		'other' => array(),
	);

	foreach ( $files as $filename ) {
		if ( false !== strpos( $filename, 'tgm-plugin-activation' ) || false !== strpos( $filename, 'class-merlin' ) || is_dir( $filename ) ) {
			continue;
		}
		if ( '.php' === substr( $filename, -4 ) ) {
			$collected['php'][ $filename ] = tc_strip_comments( (string) file_get_contents( $filename ) );
		} elseif ( '.css' === substr( $filename, -4 ) ) {
			$collected['css'][ $filename ] = (string) file_get_contents( $filename );
		} else {
			// In local development it might be useful to skip other files
			// (non .php or .css files) in dev directories.
			if ( apply_filters( 'tc_skip_development_directories', false ) && tc_is_other_file_in_dev_directory( $filename ) ) {
				continue;
			}
			$collected['other'][ $filename ] = (string) file_get_contents( $filename );
		}
	}

	return $collected;
}

/**
 * Run Theme Check against a given theme.
 *
 * @param WP_Theme $theme      A WP_Theme instance.
 * @param string   $theme_slug The slug of the given theme.
 * @return bool
 */
function run_themechecks_against_theme( WP_Theme $theme, string $theme_slug ): bool {
	$files = tc_collect_theme_files( $theme );

	// Run the checks.
	return run_themechecks(
		$files['php'],
		$files['css'],
		$files['other'],
		array(
			'theme' => $theme,
			'slug'  => $theme_slug,
		)
	);
}

/**
 * Run the Theme Checks against a set of files.
 *
 * @param array<string, string> $php     The PHP files.
 * @param array<string, string> $css     The CSS files.
 * @param array<string, string> $other   Any non-php/css files.
 * @param array<string, mixed>  $context Any context for the Theme Checks.
 *
 * @return bool
 */
function run_themechecks( array $php, array $css, array $other, array $context = array() ): bool {
	global $themechecks, $theme_check_current_theme;

	// Provide context to some functions that need to know the current theme, but aren't passed the object.
	$theme_check_current_theme = isset( $context['theme'] ) ? $context['theme'] : false;

	$pass = true;

	tc_adapt_checks_for_fse_themes( $php, $css, $other );

	foreach ( $themechecks as $check ) {
		if ( $check instanceof themecheck ) {
			if ( $context && is_callable( array( $check, 'set_context' ) ) ) {
				$check->set_context( $context );
			}

			$pass = $pass & $check->check( $php, $css, $other );
		}
	}

	$theme_check_current_theme = false;

	return (bool) $pass;
}

/**
 * Collect every check's errors as the HTML (or Trac) result list.
 *
 * @return string
 */
function display_themechecks(): string {
	$results = '';
	global $themechecks;
	$errors = array();
	foreach ( $themechecks as $check ) {
		if ( $check instanceof themecheck ) {
			$error = $check->getError();
			$error = (array) $error;
			if ( ! empty( $error ) ) {
				$errors = array_unique( array_merge( $error, $errors ) );
			}
		}
	}
	if ( ! empty( $errors ) ) {
		// Only reached from check_main(), after themecheck_do_page() has run check_admin_referer( 'themecheck-nonce' ).
		$suppress_info = isset( $_POST['s_info'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		rsort( $errors );
		foreach ( $errors as $e ) {

			if ( defined( 'TC_TRAC' ) ) {
				$results .= ( $suppress_info && preg_match( '/INFO/', (string) $e ) ) ? '' : '* ' . tc_trac( (string) $e ) . "\r\n";
			} else {
				$results .= ( $suppress_info && preg_match( '/INFO/', (string) $e ) ) ? '' : '<li>' . tc_trac( (string) $e ) . '</li>';
			}
		}
	}

	if ( defined( 'TC_TRAC' ) ) {

		if ( defined( 'TC_PRE' ) ) {
			$results = TC_PRE . $results;
		}
		$results = '<textarea cols=140 rows=20>' . wp_strip_all_tags( $results );
		if ( defined( 'TC_POST' ) ) {
			$results = $results . TC_POST;
		}
		$results .= '</textarea>';
	}
	return $results;
}

/**
 * Increment the global check counter.
 */
function checkcount(): void {
	global $checkcount;
	++$checkcount;
}

/**
 * Lines of a file containing a literal string, as `<pre>` snippets with the match highlighted.
 *
 * @param string $error Literal to search for (case-insensitive).
 * @param string $file  Absolute path.
 * @return string
 */
function tc_grep( string $error, string $file ): string {
	if ( ! file_exists( $file ) ) {
		return '';
	}
	$lines      = file( $file, FILE_IGNORE_NEW_LINES ); // Read the theme file into an array.
	$line_index = 0;
	$bad_lines  = '';
	foreach ( $lines as $this_line ) {
		if ( stristr( $this_line, $error ) ) {
			$error      = str_replace( '"', "'", $error );
			$this_line  = str_replace( '"', "'", $this_line );
			$error      = ltrim( $error );
			$pos        = strpos( $this_line, $error );
			$pre        = ( false !== $pos ? substr( $this_line, 0, $pos ) : false );
			$pre        = ltrim( htmlspecialchars( (string) $pre ) );
			$bad_lines .= "<pre class='tc-grep'>" . __( 'Line ', 'theme-check' ) . ( $line_index + 1 ) . ': ' . $pre . htmlspecialchars( substr( (string) stristr( $this_line, $error ), 0, 75 ) ) . '</pre>';
		}
		++$line_index;
	}
	return str_replace( $error, '<span class="tc-grep">' . $error . '</span>', $bad_lines );
}

/**
 * Lines of a file matching a regex, as `<pre>` snippets with the match highlighted.
 *
 * @param string $preg Regex.
 * @param string $file Absolute path.
 * @return string
 */
function tc_preg( string $preg, string $file ): string {
	if ( ! file_exists( $file ) ) {
		return '';
	}
	$lines      = file( $file, FILE_IGNORE_NEW_LINES ); // Read the theme file into an array.
	$line_index = 0;
	$bad_lines  = '';
	$error      = '';
	foreach ( $lines as $this_line ) {
		if ( preg_match( $preg, $this_line, $matches ) ) {
			$error     = $matches[0];
			$this_line = str_replace( '"', "'", $this_line );
			$error     = ltrim( $error );
			$pre       = '';
			if ( ! empty( $error ) ) {
				$pos = strpos( $this_line, $error );
				$pre = ( false !== $pos ? substr( $this_line, 0, $pos ) : false );
			}
			$pre        = ltrim( htmlspecialchars( (string) $pre ) );
			$bad_lines .= "<pre class='tc-grep'>" . __( 'Line ', 'theme-check' ) . ( $line_index + 1 ) . ': ' . $pre . htmlspecialchars( substr( (string) stristr( $this_line, $error ), 0, 75 ) ) . '</pre>';
		}
		++$line_index;
	}
	return str_replace( $error, '<span class="tc-grep">' . $error . '</span>', $bad_lines );
}

/**
 * A theme file path relative to the theme root (best effort).
 *
 * @param string $file Absolute path.
 * @return string
 */
function tc_filename( string $file ): string {
	// If we know the WP_Theme object, we can get the exact path.
	$filename = _get_filename_from_current_theme( $file );
	if ( $filename ) {
		return $filename;
	}

	// If the $file exists within a theme-like folder, use that.
	// Does not support themes nested in directories such as wp-content/themes/pub/wporg-themes/index.php.
	if ( preg_match( '!/themes/[^/]+/(.*)$!i', $file, $m ) ) {
		return $m[1];
	}

	// If still nothing, use the basename.
	return basename( $file );
}

/**
 * Get a filename relative to the current theme.
 *
 * @param string $file the file to get a relative filename for.
 * @return false|string The filename, or false on failure.
 * @access private
 */
function _get_filename_from_current_theme( string $file ): string|false {
	global $theme_check_current_theme;
	static $theme_files = array();
	static $theme_path  = '';

	if ( empty( $theme_check_current_theme ) ) {
		return false;
	}

	// Fetch the files for the theme, once per theme.
	if ( $theme_path !== $theme_check_current_theme->get_stylesheet_directory() ) {
		$theme_path = $theme_check_current_theme->get_stylesheet_directory();

		$theme_files = $theme_check_current_theme->get_files(
			null, /* all file types */
			-1, /* infinite recursion */
			true /* include parent theme files */
		);
	}

	$relative = array_search( $file, $theme_files, true );

	// Numeric file names come back as int keys; cast so the declared type holds.
	return false === $relative ? false : (string) $relative;
}

/**
 * Convert a result's HTML to Trac markup when TC_TRAC is defined.
 *
 * @param string $e Result HTML.
 * @return string
 */
function tc_trac( string $e ): string {
	$trac_left  = array( '<strong>', '</strong>' );
	$trac_right = array( "'''", "'''" );
	$html_link  = '/<a\s?href\s?=\s?[\'|"]([^"|\']*)[\'|"]>([^<]*)<\/a>/i';
	$html_new   = '[$1 $2]';
	if ( defined( 'TC_TRAC' ) ) {
		$e = (string) preg_replace( $html_link, $html_new, $e );
		$e = str_replace( $trac_left, $trac_right, $e );
		$e = (string) preg_replace( '/<pre.*?>/', "\r\n{{{\r\n", $e );
		$e = str_replace( '</pre>', "\r\n}}}\r\n", $e );
	}
	return $e;
}

/**
 * Strip comments from a PHP file in a way that will not change the underlying structure of the file.
 *
 * @param string $code PHP source.
 * @return string
 */
function tc_strip_comments( string $code ): string {
	$strip    = array(
		T_COMMENT     => true,
		T_DOC_COMMENT => true,
	);
	$newlines = array(
		"\n" => true,
		"\r" => true,
	);
	$tokens   = token_get_all( $code );
	reset( $tokens );
	$return = '';
	$token  = current( $tokens );
	while ( $token ) {
		if ( ! is_array( $token ) ) {
			$return .= $token;
		} elseif ( ! isset( $strip[ $token[0] ] ) ) {
			$return .= $token[1];
		} else {
			for ( $i = 0, $token_length = strlen( $token[1] ); $i < $token_length; ++$i ) {
				if ( isset( $newlines[ $token[1][ $i ] ] ) ) {
					$return .= $token[1][ $i ];
				}
			}
		}
		$token = next( $tokens );
	}
	return $return;
}

/**
 * Used to allow some directories to be skipped during development.
 *
 * @param  string $filename a filename/path.
 * @return boolean
 */
function tc_is_other_file_in_dev_directory( string $filename ): bool {
	$skip = false;
	// Filterable List of dirs that you may want to skip other files in during
	// development.
	$dev_dirs = apply_filters(
		'tc_common_dev_directories',
		array(
			'node_modules',
			'vendor',
		)
	);
	foreach ( $dev_dirs as $dev_dir ) {
		if ( strpos( $filename, (string) $dev_dir ) ) {
			$skip = true;
			break;
		}
	}
	return $skip;
}

/**
 * Adapt the Theme Checks if the theme is an experiment Full-Site Editing theme.
 *
 * @param array<string, string> $php_files   The theme's PHP files.
 * @param array<string, string> $css_files   The theme's CSS files.
 * @param array<string, string> $other_files Any other theme files.
 *
 * @return bool Whether the theme checks were adapted for FSE or not.
 */
function tc_adapt_checks_for_fse_themes( array $php_files, array $css_files, array $other_files ): bool {
	global $themechecks;

	// Get a list of all non PHP and CSS file paths, relative to the theme root.
	$other_filenames = array();
	foreach ( array_keys( $other_files ) as $path ) {
		$other_filenames[] = tc_filename( (string) $path );
	}

	// Check whether this is a FSE theme by searching for an index.html block template.
	if ( ! in_array( 'block-templates/index.html', $other_filenames, true ) && ! in_array( 'templates/index.html', $other_filenames, true ) ) {
		return false;
	}

	// Remove theme checks that do not apply to FSE themes.
	foreach ( $themechecks as $key => $check ) {
		if ( $check instanceof Tag_Check
			|| $check instanceof Suggested_Styles_Check
			|| $check instanceof Widgets_Check
			|| $check instanceof Gravatar_Check
			|| $check instanceof Post_Pagination_Check
			|| $check instanceof Basic_Check
			|| $check instanceof Comments_Check
			|| $check instanceof Comment_Pagination_Check
			|| $check instanceof Comment_Reply_Check
			|| $check instanceof Nav_Menu_Check
			|| $check instanceof Post_Thumbnail_Check
			|| $check instanceof Theme_Support_Check
			|| $check instanceof Editor_Style_Check
			|| $check instanceof Underscores_Check
			|| $check instanceof Constants_Check
			|| $check instanceof Customizer_Check
			|| $check instanceof Post_Format_Check
			|| $check instanceof Search_Form_Check
			|| $check instanceof Theme_Support_Title_Tag_Check
			|| $check instanceof Screen_Reader_Text_Check
			|| $check instanceof Include_Check
		) {
			unset( $themechecks[ $key ] );
		}
	}

	// Add FSE specific checks.
	$themechecks[] = new FSE_Required_Files_Check();

	return true;
}
