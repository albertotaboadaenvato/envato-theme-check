<?php
/**
 * Turns a fixture theme directory into the [php, css, other] arrays checks receive.
 *
 * Mirrors run_themechecks_against_theme() in checkbase.php.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,Generic.CodeAnalysis.UnusedFunctionParameter,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName,WordPress.Security.EscapeOutput,WordPress.WP.AlternativeFunctions -- test harness (PSR-4 class files, passthrough WP stubs)

namespace TC\Tests\Support;

final class FixtureTheme {

	/**
	 * @return array{php: array<string,string>, css: array<string,string>, other: array<string,string>}
	 */
	public static function fromDir( string $dir ): array {
		$root = realpath( $dir );
		if ( false === $root || ! is_dir( $root ) ) {
			throw new \InvalidArgumentException( "Fixture directory not found: {$dir}" );
		}

		$php   = array();
		$css   = array();
		$other = array();

		foreach ( self::files( $root ) as $filename ) {
			if ( str_contains( $filename, 'tgm-plugin-activation' ) || str_contains( $filename, 'class-merlin' ) ) {
				continue;
			}
			$content = (string) file_get_contents( $filename );
			if ( str_ends_with( $filename, '.php' ) ) {
				$php[ $filename ] = tc_strip_comments( $content );
			} elseif ( str_ends_with( $filename, '.css' ) ) {
				$css[ $filename ] = $content;
			} else {
				if ( apply_filters( 'tc_skip_development_directories', false ) && tc_is_other_file_in_dev_directory( $filename ) ) {
					continue;
				}
				$other[ $filename ] = $content;
			}
		}

		return array(
			'php'   => $php,
			'css'   => $css,
			'other' => $other,
		);
	}

	/**
	 * All files under $root, sorted, including hidden files and directories.
	 *
	 * @return list<string>
	 */
	private static function files( string $root ): array {
		$found    = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			$found[] = $file->getPathname();
		}
		sort( $found );
		return $found;
	}
}
