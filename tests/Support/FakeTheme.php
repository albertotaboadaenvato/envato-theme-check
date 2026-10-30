<?php
/**
 * Minimal stand-in for WP_Theme: get() reads style.css headers from a fixture directory.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,WordPress.Files.FileName,WordPress.WP.AlternativeFunctions -- test harness (PSR-4 class files)

namespace TC\Tests\Support;

/** @implements \ArrayAccess<string, string|array<string>> */
final class FakeTheme implements \ArrayAccess {

	/** @var array<string,string|array<string>> Headers keyed by lower-case name without spaces, e.g. "requiresphp". */
	private array $headers = array();

	private string $dir;

	public function __construct( string $dir ) {
		$this->dir = rtrim( $dir, '/' );
		$css       = rtrim( $dir, '/' ) . '/style.css';
		if ( ! is_file( $css ) ) {
			return;
		}
		$head = (string) file_get_contents( $css, false, null, 0, 8192 );
		foreach ( array( 'Theme Name', 'Requires PHP', 'Tested up to', 'Text Domain', 'Template', 'Author URI', 'Theme URI', 'Author', 'Version', 'Tags' ) as $name ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $name, '/' ) . ':(.*)$/mi', $head, $m ) ) {
				$this->headers[ self::key( $name ) ] = trim( $m[1] );
			}
		}
	}

	public function get_stylesheet_directory(): string {
		return $this->dir;
	}

	/**
	 * Relative path => absolute path, as WP_Theme::get_files() returns.
	 *
	 * @return array<string,string>
	 */
	public function get_files(): array {
		$out = array();
		$it  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->dir, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$out[ substr( $file->getPathname(), strlen( $this->dir ) + 1 ) ] = $file->getPathname();
		}
		return $out;
	}

	/**
	 * Accepts "Requires PHP", "RequiresPHP", "Name" (alias of Theme Name), etc.
	 *
	 * @return string|array<string>
	 */
	public function get( string $header ): string|array {
		$key = self::key( $header );
		if ( 'name' === $key ) {
			$key = 'themename';
		}
		if ( 'tags' === $key ) {
			return array_values( array_filter( array_map( 'trim', explode( ',', $this->headers[ $key ] ?? '' ) ) ) );
		}
		return $this->headers[ $key ] ?? '';
	}

	public function offsetExists( mixed $offset ): bool {
		return '' !== $this->get( (string) $offset );
	}

	/** @return string|array<string> */
	public function offsetGet( mixed $offset ): string|array {
		return $this->get( (string) $offset );
	}

	public function offsetSet( mixed $offset, mixed $value ): void {
		throw new \LogicException( 'Fixture theme headers are read-only.' );
	}

	public function offsetUnset( mixed $offset ): void {
		throw new \LogicException( 'Fixture theme headers are read-only.' );
	}

	private static function key( string $name ): string {
		return strtolower( str_replace( ' ', '', $name ) );
	}
}
