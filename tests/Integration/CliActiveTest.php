<?php
/**
 * ThemeCheckCLI::active() must run end-to-end against the active theme and emit the JSON report.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,WordPress.Files.FileName

namespace TC\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class CliActiveTest extends TestCase {

	public function test_active_runs_without_undefined_function_and_emits_json(): void {
		require_once dirname( __DIR__ ) . '/Support/WpCliShim.php';
		if ( ! class_exists( 'ThemeCheckCLI', false ) ) {
			require_once dirname( __DIR__, 2 ) . '/theme-check-cli.php';
		}

		$cli = new \ThemeCheckCLI();

		ob_start();
		try {
			$cli->active( array(), array( 'format' => true ) );
		} catch ( \Error $e ) {
			ob_end_clean();
			$this->fail( 'active() threw: ' . $e->getMessage() );
		}
		$out = (string) ob_get_clean();

		$json = json_decode( $out, true );
		$this->assertIsArray( $json, 'active() must output JSON, got: ' . substr( $out, 0, 200 ) );
		foreach ( array( 'result', 'required', 'recommended', 'warnings', 'errors' ) as $key ) {
			$this->assertArrayHasKey( $key, $json, "JSON missing '$key' for " . wp_get_theme()->get_stylesheet() );
		}
	}
}
