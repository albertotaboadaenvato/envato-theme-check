<?php
/**
 * Static checks must make zero outbound HTTP requests.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,WordPress.Files.FileName,WordPress.Security.EscapeOutput -- test harness

namespace TC\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class StaticNoHttpTest extends TestCase {

	private int $http_calls = 0;

	/** @var callable */
	private $counter;

	protected function setUp(): void {
		$this->http_calls = 0;
		$this->counter    = function () {
			++$this->http_calls;
			return new \WP_Error( 'tc_test_blocked', 'HTTP blocked in StaticNoHttpTest' );
		};
		add_filter( 'pre_http_request', $this->counter, 1 );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_http_request', $this->counter, 1 );
	}

	public function test_static_run_makes_no_http_requests(): void {
		if ( ! function_exists( 'run_themechecks_against_theme' ) ) {
			require_once dirname( __DIR__, 2 ) . '/checkbase.php';
		}
		register_theme_directory( dirname( __DIR__ ) . '/fixtures/themes' );
		wp_clean_themes_cache();

		$theme = wp_get_theme( 'bad-things-fail', dirname( __DIR__ ) . '/fixtures/themes' );
		$this->assertTrue( $theme->exists(), 'fixture theme must resolve' );

		run_themechecks_against_theme( $theme, 'bad-things-fail' );

		$this->assertSame( 0, $this->http_calls );
	}
}
