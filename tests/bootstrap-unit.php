<?php
/**
 * Unit/sweep bootstrap: no WordPress, plain function stubs, then load checkbase.php.
 *
 * Brain\Monkey::setUp() only works inside a test, so load-time stubs are plain functions.
 * Tests that need different behaviour use Brain\Monkey\Functions\when() on top of these
 * only where the function is not already defined; keep stubs passthrough.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,Generic.CodeAnalysis.UnusedFunctionParameter,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName,WordPress.Security.EscapeOutput,WordPress.WP.AlternativeFunctions -- test harness (PSR-4 class files, passthrough WP stubs)

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/tc-unit-abspath/' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}
if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return $data;
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
// Minimal hook registry so tests can add a filter callback; with none registered apply_filters() is passthrough.
$GLOBALS['tc_test_filters'] = array();
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value = null, ...$args ) {
		$hooks = $GLOBALS['tc_test_filters'][ $hook_name ] ?? array();
		ksort( $hooks );
		foreach ( $hooks as $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$value = $cb[0]( ...array_slice( array_merge( array( $value ), $args ), 0, $cb[1] ) );
			}
		}
		return $value;
	}
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook_name, ...$args ): void {}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ): bool {
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ): bool {
		$GLOBALS['tc_test_filters'][ $hook_name ][ $priority ][] = array( $callback, $accepted_args );
		return true;
	}
}
if ( ! function_exists( 'has_action' ) ) {
	function has_action( $hook_name, $callback = false ) {
		return false;
	}
}
if ( ! function_exists( 'wp_raise_memory_limit' ) ) {
	function wp_raise_memory_limit( $context = 'admin' ) {
		return false;
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
}

if ( ! defined( 'KB_IN_BYTES' ) ) {
	define( 'KB_IN_BYTES', 1024 );
}

if ( ! function_exists( '_x' ) ) {
	function _x( string $text, string $context, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
		return 1 === $number ? $single : $plural;
	}
}
if ( ! function_exists( '_nx' ) ) {
	function _nx( string $single, string $plural, int $number, string $context, string $domain = 'default' ): string {
		return 1 === $number ? $single : $plural;
	}
}
if ( ! function_exists( 'esc_html_x' ) ) {
	function esc_html_x( string $text, string $context, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_attr_x' ) ) {
	function esc_attr_x( string $text, string $context, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( string $text, string $domain = 'default' ): void {
		echo $text;
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo $text;
	}
}
if ( ! function_exists( 'sanitize_title_with_dashes' ) ) {
	function sanitize_title_with_dashes( string $title, string $raw_title = '', string $context = 'display' ): string {
		return strtolower( str_replace( ' ', '-', $title ) );
	}
}
if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
	function wp_check_filetype_and_ext( string $file, string $filename, ?array $mimes = null ): array {
		return array(
			'ext'             => pathinfo( $filename, PATHINFO_EXTENSION ),
			'type'            => mime_content_type( $file ),
			'proper_filename' => false,
		);
	}
}
if ( ! function_exists( 'size_format' ) ) {
	function size_format( int|float $bytes, int $decimals = 0 ): string {
		return number_format( $bytes, $decimals ) . ' B';
	}
}
if ( ! function_exists( 'get_file_data' ) ) {
	function get_file_data( string $file, array $default_headers, string $context = '' ): array {
		$content = (string) file_get_contents( $file, false, null, 0, 8192 );
		$headers = array();
		foreach ( $default_headers as $key => $name ) {
			preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $content, $match );
			$headers[ $key ] = trim( $match[1] ?? '' );
		}
		return $headers;
	}
}

require_once dirname( __DIR__ ) . '/checkbase.php';
