<?php
/**
 * Spec §Extensibility (docs/SPEC.md): findings pass through the themecheck_finding_suffix filter.
 *
 * @package EnvatoThemeCheck
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName -- test harness (PSR-4 class files, snake_case test names)

namespace TC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TC\Tests\Support\CheckRunner;
use TC\Tests\Support\FakeTheme;
use TC\Tests\Support\FixtureTheme;

final class FindingSuffixTest extends TestCase {

	private const THEMES = __DIR__ . '/../fixtures/themes';

	protected function tearDown(): void {
		unset( $GLOBALS['tc_test_filters']['themecheck_finding_suffix'] );
	}

	/**
	 * @param class-string $check_class
	 * @return list<string>
	 */
	private static function run_fixture( string $check_class, string $fixture ): array {
		$dir   = self::THEMES . '/' . $fixture;
		$theme = new FakeTheme( $dir );
		global $theme_check_current_theme;
		$theme_check_current_theme = $theme;
		try {
			return CheckRunner::run(
				$check_class,
				FixtureTheme::fromDir( $dir ),
				array(
					'theme' => $theme,
					'slug'  => $fixture,
				)
			);
		} finally {
			$theme_check_current_theme = false;
		}
	}

	/**
	 * @return class-string|null Null when the check file registers no class in the unit run (CheckFixtureTest skips those too).
	 */
	private static function class_for( string $id ): ?string {
		global $themechecks;
		foreach ( (array) $themechecks as $check ) {
			$file = basename( (string) ( new \ReflectionClass( $check ) )->getFileName() );
			if ( in_array( $file, array( "class-{$id}.php", "class-{$id}-check.php" ), true ) ) {
				return get_class( $check );
			}
		}
		return null;
	}

	public function test_suffix_is_empty_without_a_callback(): void {
		$this->assertSame( '', tc_finding_suffix( 'basic/wp_head' ) );
	}

	public function test_callback_return_value_ends_the_message(): void {
		add_filter( 'themecheck_finding_suffix', static fn( string $suffix, string $key ): string => ' X' . $key, 10, 2 );

		$wp_head = array_values( array_filter( self::run_fixture( (string) self::class_for( 'basic' ), 'basic-fail' ), static fn( string $l ): bool => str_contains( $l, 'Could not find wp_head' ) ) );
		$this->assertCount( 1, $wp_head, 'basic-fail must report the missing wp_head' );
		$this->assertStringEndsWith( ' Xbasic/wp_head', $wp_head[0] );

		$territory = self::run_fixture( (string) self::class_for( 'plugin-territory' ), 'plugin-territory-fail' );
		$this->assertNotEmpty( $territory );
		$this->assertStringEndsWith( ' Xplugin-territory', $territory[0] );
	}

	public function test_no_fail_fixture_message_carries_a_trigger_without_a_callback(): void {
		$dirs = (array) glob( self::THEMES . '/*-fail', GLOB_ONLYDIR );
		$this->assertNotEmpty( $dirs );
		foreach ( $dirs as $dir ) {
			$fixture = basename( (string) $dir );
			$class   = self::class_for( substr( $fixture, 0, -5 ) );
			if ( null === $class ) {
				continue;
			}
			foreach ( self::run_fixture( $class, $fixture ) as $line ) {
				$this->assertStringNotContainsString( 'Trigger:', $line, "$fixture: $line" );
			}
		}
	}
}
