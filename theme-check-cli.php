<?php
/**
 * Run Envato Theme Check from the command line
 *
 * @package Theme Check
 */

declare(strict_types=1);

require_once __DIR__ . '/checkbase.php';
require_once __DIR__ . '/main.php';

/**
 * Run Envato Theme Check from the command line.
 */
class ThemeCheckCLI extends WP_CLI_Command {

	/**
	 * Theme fetcher instance
	 *
	 * @var \WP_CLI\Fetchers\Theme
	 */
	public \WP_CLI\Fetchers\Theme $fetcher;

	/**
	 * Set up the theme fetcher.
	 */
	public function __construct() {
		parent::__construct();
		$this->fetcher = new \WP_CLI\Fetchers\Theme();
	}
	/**
	 * Show a list of the current themes
	 *
	 * ## OPTIONS
	 *
	 * [--errors=<errors>]
	 * : set true to return only themes with errors, false for only without errors. Default: false
	 *
	 * [--allowed=<allowed>]
	 * : (Multisite) set true to return only themes allowed on this site, false for only those not allowed,
	 * 'site' for only site-allowed, 'network' for only network-allowed.
	 *
	 * [--blog_id=<id>]
	 * : (Multisite) Blog ID, if different than current
	 *
	 * @subcommand list
	 *
	 * @param array<int,string>   $args       Positional args (unused).
	 * @param array<string,mixed> $assoc_args Flags (errors, allowed, blog_id).
	 */
	public function list_themes( array $args = array(), array $assoc_args = array() ): void {
		$defaults       = array(
			'errors'  => false,
			'allowed' => null,
			'blog_id' => 0,
		);
		$args           = wp_parse_args( $assoc_args, $defaults );
		$args['errors'] = 'true' === $args['errors'];

		if ( ( 'true' == $args['allowed'] ) || ( 'false' == $args['allowed'] ) ) {
			$args['allowed'] = 'true' === $args['allowed'];
		}

		$themes = wp_get_themes( $args );

		foreach ( $themes as $slug => $theme ) {
			WP_CLI::line( $slug . ': ' . $theme->get( 'Name' ) );
		}
	}
	/**
	 * Check a theme
	 *
	 * <theme>
	 * : The theme slug to check
	 *
	 * [--format=<format>]
	 * : set to true to format as json. Default: false
	 *
	 * @param array<int,string>   $args       Positional args.
	 * @param array<string,mixed> $assoc_args Flags (format).
	 */
	public function check( array $args = array(), array $assoc_args = array() ): void {
		global $themechecks;

		checkcount();

		// Prevent undefined index errors.
		if ( ! array_key_exists( 'format', $assoc_args ) ) {
			$assoc_args['format'] = '';
		}

		// Empty arrays for the json format.
		$required_json    = array();
		$warnings_json    = array();
		$recommended_json = array();
		$errors_json      = array();
		$result_json      = array();

		// Same collector + context as the admin page (check_main), so both runners see the same files.
		$theme = $this->fetcher->get_check( $args[0] );
		run_themechecks_against_theme( $theme, $theme->get_stylesheet() );

		$errors = array();

		foreach ( $themechecks as $check ) {
			if ( $check instanceof themecheck ) {
				$error = $check->getError();

				if ( ! empty( $error ) ) {
					$errors = array_merge( $error, $errors );
				}
			}
		}

		$errors = array_unique( $errors );
		$errors = array_map( 'strip_tags', $errors );
		rsort( $errors );

		// assume to pass unless we see a required or warning message.
		$pass = true;

		foreach ( $errors as $error ) {
			$parts   = explode( ':', $error, 2 );
			$type    = $parts[0];
			$message = isset( $parts[1] ) ? $parts[1] : '';

			if ( 'REQUIRED' === trim( $type ) ) {
				if ( 'true' == $assoc_args['format'] ) {
					array_push( $required_json, 'REQUIRED: ' . trim( $message ) );
				} else {
					WP_CLI::warning( '%rREQUIRED:%n ' . trim( $message ) );
				}
				$pass = false;
			} elseif ( 'WARNING' === trim( $type ) ) {
				if ( 'true' == $assoc_args['format'] ) {
					array_push( $warnings_json, 'WARNING: ' . trim( $message ) );
				} else {
					WP_CLI::warning( '%yWARNING:%n ' . trim( $message ) );
				}
				$pass = false;

			} elseif ( 'RECOMMENDED' === trim( $type ) ) {
				if ( 'true' == $assoc_args['format'] ) {
					array_push( $recommended_json, 'RECOMMENDED: ' . trim( $message ) );
				} else {
					WP_CLI::warning( '%cRECOMMENDED:%n ' . trim( $message ) );
				}
			} elseif ( 'true' == $assoc_args['format'] ) {
				array_push( $errors_json, 'ERROR: ' . trim( $error ) );
			} else {
				WP_CLI::warning( $error );
			}
		}

		WP_CLI::line();

		if ( empty( $errors ) ) {
			if ( 'true' == $assoc_args['format'] ) {
				array_push( $result_json, 'SUCCESS' );
				array_push( $result_json, 'THEME PASSED REVIEW' );
			} else {
				WP_CLI::success( 'THEME PASSED REVIEW' );
			}
		} elseif ( true === $pass ) {
			if ( 'true' == $assoc_args['format'] ) {
				array_push( $result_json, 'SUCCESS' );
				array_push( $result_json, 'THEME PASSED REVIEW WITH RECOMMENDED CHANGES' );
			} else {
				WP_CLI::success( 'THEME PASSED REVIEW WITH RECOMMENDED CHANGES' );
			}
		} elseif ( 'true' == $assoc_args['format'] ) {
			array_push( $result_json, 'FAIL' );
			array_push( $result_json, 'THEME DID NOT PASS REVIEW' );
		} else {
			WP_CLI::line( WP_CLI::colorize( '%RFAIL:%n THEME DID NOT PASS REVIEW' ) );
		}

		if ( 'true' == $assoc_args['format'] ) {
			$output = array(
				'result'      => $result_json,
				'required'    => $required_json,
				'recommended' => $recommended_json,
				'warnings'    => $warnings_json,
				'errors'      => $errors_json,
			);
			echo htmlspecialchars_decode( (string) wp_json_encode( $output, JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI plain text JSON output, not HTML.
		}
	}
	/**
	 * Check for the active theme
	 *
	 * [--format=<format>]
	 * : set to true to format as json. Default: false
	 *
	 * @param array<int,string>   $args       Positional args.
	 * @param array<string,mixed> $assoc_args Flags (format).
	 */
	public function active( array $args = array(), array $assoc_args = array() ): void {
		$this->check( array( wp_get_theme()->get_stylesheet() ), $assoc_args );
	}
}

/**
 * Logger that prints warnings without the "Warning:" prefix.
 *
 * Params stay `mixed`: the parent WP-CLI logger methods are untyped, so narrower types would be incompatible.
 */
class ThemeCheckCLILogger extends WP_CLI\Loggers\Regular {
	/**
	 * Write a line, optionally prefixed by a coloured label.
	 *
	 * @param mixed $message Text.
	 * @param mixed $label   Label ('' for none).
	 * @param mixed $color   Colour token for the label.
	 * @param mixed $handle  Stream.
	 */
	public function _line( mixed $message, mixed $label, mixed $color, mixed $handle = STDOUT ): void { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- overrides WP-CLI's _line().
		if ( ! empty( $label ) ) {
			$label = \cli\Colors::colorize( "$color$label:%n ", $this->in_color );
		}
		$this->write( $handle, "{$label}{$message}\n" );
	}

	/**
	 * Print a warning as-is (colourised), without the "Warning:" label.
	 *
	 * @param mixed $message Text.
	 */
	public function warning( mixed $message ): void {
		$this->_line( WP_CLI::colorize( $message ), '', '', STDERR );
	}
}
WP_CLI::set_logger( new ThemeCheckCLILogger( true ) );

WP_CLI::add_command( 'theme review', 'ThemeCheckCLI' );
