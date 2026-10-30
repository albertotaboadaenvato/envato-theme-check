<?php
/**
 * US5 red test: every PHP file in the repo (outside vendor/ and tests/fixtures/) must declare
 * strict types within its first 2 KB, and theme-check.php must advertise Requires PHP: 8.1.
 *
 * @package EnvatoThemeCheck
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName,WordPress.WP.AlternativeFunctions,WordPress.PHP.DiscouragedPHPFunctions -- test harness (PSR-4 class files, snake_case test names)

namespace TC\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class StrictTypesTest extends TestCase {

	private const EXCLUDED_SEGMENTS = array( 'vendor', 'vendor-bin', 'node_modules', '.git' );
	private const EXCLUDED_PREFIX   = 'tests/fixtures/';
	private const HEAD_BYTES        = 2048;
	private const STRICT_TYPES      = 'declare(strict_types=1);';

	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	private static function is_excluded( string $relative ): bool {
		if ( str_starts_with( $relative, self::EXCLUDED_PREFIX ) ) {
			return true;
		}

		return array() !== array_intersect( explode( '/', $relative ), self::EXCLUDED_SEGMENTS );
	}

	/**
	 * Tracked PHP files plus untracked PHP files under tests/, minus excluded paths.
	 *
	 * @return array<string, array{string}>
	 */
	public static function phpFiles(): array {
		$root  = self::root();
		$found = array();

		$tracked = shell_exec( 'git -C ' . escapeshellarg( $root ) . " ls-files -- '*.php' 2>/dev/null" );
		foreach ( explode( "\n", (string) $tracked ) as $relative ) {
			// Deleted-but-unstaged paths are still listed by ls-files; skip what is not on disk.
			if ( '' !== $relative && ! self::is_excluded( $relative ) && is_file( $root . '/' . $relative ) ) {
				$found[ $relative ] = true;
			}
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $root . '/tests', FilesystemIterator::SKIP_DOTS ),
				static fn ( \SplFileInfo $file ): bool => $file->isDir() || 'php' === $file->getExtension()
			)
		);
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$relative = substr( $file->getPathname(), strlen( $root ) + 1 );
			if ( ! self::is_excluded( $relative ) ) {
				$found[ $relative ] = true;
			}
		}

		ksort( $found );

		$cases = array();
		foreach ( array_keys( $found ) as $relative ) {
			$cases[ $relative ] = array( $relative );
		}

		return $cases;
	}

	#[DataProvider( 'phpFiles' )]
	public function test_php_file_starts_with_php_tag_and_declares_strict_types( string $relative_path ): void {
		$contents = (string) file_get_contents( self::root() . '/' . $relative_path );

		$this->assertStringStartsWith( '<?php', $contents, "{$relative_path}: must start with <?php" );
		$this->assertStringContainsString(
			self::STRICT_TYPES,
			substr( $contents, 0, self::HEAD_BYTES ),
			"{$relative_path}: missing `" . self::STRICT_TYPES . '` within the first 2 KB'
		);
	}

	public function test_theme_check_header_requires_php_81(): void {
		$contents = (string) file_get_contents( self::root() . '/theme-check.php' );

		$this->assertMatchesRegularExpression(
			'/^[ \t*]*Requires PHP:[ \t]*8\.1[ \t]*\r?$/m',
			$contents,
			'theme-check.php: plugin header must contain the line `Requires PHP: 8.1`'
		);
	}
}
