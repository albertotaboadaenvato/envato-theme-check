<?php
/**
 * Runs each check against tests/fixtures/themes/<id>-pass|fail|regression-* and asserts the outcome.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName,WordPress.WP.AlternativeFunctions,WordPress.Security.EscapeOutput -- test harness

namespace TC\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TC\Tests\Support\CheckRunner;
use TC\Tests\Support\FakeTheme;
use TC\Tests\Support\FixtureTheme;

final class CheckFixtureTest extends TestCase {

	private const ROOT = __DIR__ . '/../fixtures/themes';

	private static function root(): string {
		$env = getenv( 'TC_FIXTURE_ROOT' );
		return false === $env || '' === $env ? self::ROOT : $env;
	}

	/**
	 * @return list<string>
	 */
	private static function fixtureDirs(): array {
		$dirs = glob( self::root() . '/*', GLOB_ONLYDIR );
		return false === $dirs ? array() : $dirs;
	}

	/**
	 * @return array<string,array{0:string,1:string,2:string}> name => [dir, id, kind]
	 */
	public static function fixtures(): array {
		$cases = array();
		foreach ( self::fixtureDirs() as $dir ) {
			$name = basename( $dir );
			if ( preg_match( '/^(.+)-(pass|fail)$/', $name, $m ) || preg_match( '/^(.+?)-(regression)-.+$/', $name, $m ) ) {
				$cases[ $name ] = array( $dir, $m[1], $m[2] );
			}
		}
		return $cases;
	}

	public function test_fixtures_exist(): void {
		$this->assertNotEmpty( self::fixtures(), 'no fixtures under tests/fixtures/themes' );
	}

	#[DataProvider( 'fixtures' )]
	public function test_check_against_fixture( string $dir, string $id, string $kind ): void {
		$class = self::checkClass( $id );
		if ( null === $class ) {
			$this->markTestSkipped( "No registered check class for id '{$id}'" );
		}

		if ( 'regression' === $kind ) {
			$kind = self::expectation( $dir );
		}

		$theme = new FakeTheme( $dir );
		global $theme_check_current_theme;
		$theme_check_current_theme = $theme;
		try {
			$lines = CheckRunner::run(
				$class,
				FixtureTheme::fromDir( $dir ),
				array(
					'theme' => $theme,
					'slug'  => basename( $dir ),
				)
			);
		} finally {
			$theme_check_current_theme = false;
		}

		if ( 'pass' === $kind ) {
			$findings = array_values( array_filter( $lines, static fn( string $line ): bool => ! preg_match( '/^INFO\b/', $line ) ) );
			$this->assertSame( array(), $findings, "{$id} must emit no finding above INFO on " . basename( $dir ) );
			return;
		}

		$this->assertNotEmpty( $lines, "{$id} must flag " . basename( $dir ) );
		$allowed = self::specSeverities( $id );
		if ( $allowed ) {
			$seen = array_map(
				static function ( string $line ): string {
					preg_match( '/^(REQUIRED|WARNING|RECOMMENDED|INFO)\b/', $line, $match );
					return $match[1] ?? '';
				},
				$lines
			);
			$this->assertNotEmpty( array_intersect( $seen, $allowed ), "{$id} severities [" . implode( ',', $seen ) . '] not in SPEC [' . implode( ',', $allowed ) . ']' );
		}
	}

	/**
	 * @return class-string|null
	 */
	private static function checkClass( string $id ): ?string {
		global $themechecks;
		foreach ( (array) $themechecks as $check ) {
			$file = ( new \ReflectionClass( $check ) )->getFileName();
			if ( in_array( basename( (string) $file ), array( "class-{$id}.php", "class-{$id}-check.php" ), true ) ) {
				return get_class( $check );
			}
		}
		return null;
	}

	private static function expectation( string $dir ): string {
		$readme = $dir . '/README.md';
		$text   = is_file( $readme ) ? (string) file_get_contents( $readme ) : '';
		if ( preg_match( '/\A---\R(.*?)\R---/s', $text, $fm ) && preg_match( '/^expect:\s*(pass|fail)\s*$/m', $fm[1], $m ) ) {
			return $m[1];
		}
		throw new \RuntimeException( basename( $dir ) . '/README.md needs front-matter "expect: pass|fail"' );
	}

	/**
	 * @return list<string> Upper-case severities from the SPEC catalogue row for $id (empty if unset).
	 */
	private static function specSeverities( string $id ): array {
		$spec = (string) file_get_contents( __DIR__ . '/../../docs/SPEC.md' );
		if ( preg_match( '/^\|\s*' . preg_quote( $id, '/' ) . '\s*\|[^|]*\|[^|]*\|([^|]*)\|/m', $spec, $m ) ) {
			return array_values( array_filter( (array) preg_split( '/[^A-Za-z]+/', strtoupper( $m[1] ) ) ) );
		}
		return array();
	}
}
