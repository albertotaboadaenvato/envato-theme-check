<?php
/**
 * US2 / FR-006–FR-007: PHP 8.x compatibility verdicts on fixture themes.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName -- test harness

namespace TC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TC\Tests\Support\CheckRunner;
use TC\Tests\Support\FakeTheme;
use TC\Tests\Support\FixtureTheme;

final class PhpCompatibilityCheckTest extends TestCase {

	private const ROOT  = __DIR__ . '/../fixtures/themes/';
	private const CHECK = 'PHP_Compatibility_Check';

	/**
	 * @return list<string>
	 */
	private function run_fixture( string $name ): array {
		$this->assertTrue( class_exists( self::CHECK ), 'class ' . self::CHECK . ' does not exist (checks/class-php-compatibility-check.php)' );
		$dir = self::ROOT . $name;
		return CheckRunner::run(
			self::CHECK,
			FixtureTheme::fromDir( $dir ),
			array(
				'theme' => new FakeTheme( $dir ),
				'slug'  => $name,
			)
		);
	}

	/**
	 * Lines with the given severity whose text contains every keyword (case-insensitive).
	 *
	 * @param list<string> $lines
	 * @param list<string> $keywords
	 * @return list<string>
	 */
	private static function matching( array $lines, string $severity, array $keywords ): array {
		return array_values(
			array_filter(
				$lines,
				static function ( string $line ) use ( $severity, $keywords ): bool {
					if ( ! str_starts_with( $line, $severity . ':' ) ) {
						return false;
					}
					foreach ( $keywords as $keyword ) {
						if ( false === stripos( $line, $keyword ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * @param list<string> $lines
	 * @param list<string> $keywords
	 */
	private function assertFinding( array $lines, string $severity, array $keywords, string $file ): void {
		$found = self::matching( $lines, $severity, $keywords );
		$this->assertCount( 1, $found, "expected one {$severity} line with [" . implode( ', ', $keywords ) . "]:\n" . implode( "\n", $lines ) );
		$this->assertStringContainsString( $file, $found[0], 'finding must name the file' );
		$this->assertMatchesRegularExpression( '/\bline\s*\d+|:\d+\b/i', $found[0], 'finding must give a line number' );
	}

	/**
	 * @param list<string> $lines
	 */
	private function assertRangeInfo( array $lines, string $floor ): void {
		$info = self::matching( $lines, 'INFO', array( $floor, '8.4' ) );
		$this->assertCount( 1, $info, "expected exactly one INFO line with the evaluated range {$floor}–8.4:\n" . implode( "\n", $lines ) );
		$this->assertCount( 1, self::matching( $lines, 'INFO', array() ), 'only one INFO line expected' );
	}

	public function test_fail_fixture_flags_each_construct_with_file_line_and_version(): void {
		$lines = $this->run_fixture( 'php-compatibility-fail' );
		$file  = 'legacy.php';

		$this->assertFinding( $lines, 'REQUIRED', array( 'create_function', '8.0' ), $file );
		$this->assertFinding( $lines, 'WARNING', array( 'nullable', '8.4' ), $file );
		$this->assertFinding( $lines, 'WARNING', array( '${', '8.2' ), $file );
		$this->assertRangeInfo( $lines, '7.4' );
	}

	public function test_pass_fixture_emits_only_the_range_info_line(): void {
		$lines = $this->run_fixture( 'php-compatibility-pass' );

		$this->assertRangeInfo( $lines, '8.2' );
		$this->assertCount( 1, $lines, "modern theme (enum, readonly, create_function only in string/comment) must produce no findings:\n" . implode( "\n", $lines ) );
	}

	public function test_floor_fixture_flags_syntax_unavailable_at_declared_floor(): void {
		$lines = $this->run_fixture( 'php-compatibility-floor' );

		$this->assertFinding( $lines, 'REQUIRED', array( 'enum', '8.1' ), 'enum.php' );
		$this->assertRangeInfo( $lines, '7.4' );
	}
}
