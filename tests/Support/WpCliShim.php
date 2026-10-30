<?php
/**
 * Minimal stand-in for the WP-CLI classes theme-check-cli.php touches, so the integration suite
 * can load ThemeCheckCLI without the wp-cli phar. Everything the command prints is captured in
 * WP_CLI::$output (echo output still goes to stdout; wrap calls in ob_start()).
 *
 * Only declared when real WP-CLI is not loaded.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,Generic.CodeAnalysis.UnusedFunctionParameter,Generic.Files.OneObjectStructurePerFile,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName,WordPress.Security.EscapeOutput,Universal.Namespaces.DisallowCurlyBraceSyntax,Universal.Namespaces.OneDeclarationPerFile,Universal.Namespaces.DisallowDeclarationWithoutName -- test harness

namespace WP_CLI\Fetchers {

	if ( ! class_exists( Theme::class, false ) ) {
		class Theme {
			/** Mirrors WP-CLI's fetcher: resolve by slug through the registered theme roots. */
			public function get_check( string $name ): \WP_Theme {
				$theme = wp_get_theme( $name );
				if ( ! $theme->exists() ) {
					throw new \RuntimeException( "WP_CLI shim: theme '{$name}' not found" );
				}
				return $theme;
			}
		}
	}
}

namespace WP_CLI\Loggers {

	if ( ! class_exists( Regular::class, false ) ) {
		class Regular {
			protected bool $in_color;

			public function __construct( bool $in_color ) {
				$this->in_color = $in_color;
			}

			/** @param mixed $handle */
			protected function write( $handle, string $str ): void {
				\WP_CLI::$output .= $str;
			}
		}
	}
}

namespace {

	if ( ! class_exists( 'WP_CLI', false ) ) {
		class WP_CLI {
			public static string $output = '';

			public static function set_logger( object $logger ): void {}

			public static function add_command( string $name, string $class_name ): void {}

			public static function colorize( string $text ): string {
				return (string) preg_replace( '/%[a-zA-Z]/', '', $text );
			}

			public static function line( string $message = '' ): void {
				self::$output .= $message . "\n";
			}

			public static function warning( string $message ): void {
				self::$output .= 'Warning: ' . self::colorize( $message ) . "\n";
			}

			public static function success( string $message ): void {
				self::$output .= 'Success: ' . $message . "\n";
			}

			public static function error( string $message ): void {
				throw new \RuntimeException( 'WP_CLI::error: ' . $message );
			}
		}
	}

	if ( ! class_exists( 'WP_CLI_Command', false ) ) {
		class WP_CLI_Command {
			public function __construct() {}
		}
	}
}
