<?php
/**
 * Admin/CLI parity (docs/SPEC.md "Plugin behaviour", FR-001..FR-003, research R6).
 *
 * For each fixture theme, the admin runner (run_themechecks_against_theme() rendered through
 * display_themechecks(), as check_main() does) and the CLI runner (ThemeCheckCLI::check() with
 * --format=true) must produce the same multiset of findings. A finding is normalised to
 * "SEVERITY: message"; the file is part of the message ("Found in <file>"), so the
 * (severity, message, file) triple of the data model collapses to that string.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName,WordPress.WP.AlternativeFunctions,WordPress.Security.EscapeOutput,WordPress.WP.GlobalVariablesOverride -- test harness

namespace TC\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RunnerParityTest extends TestCase {

	private const PLUGIN    = __DIR__ . '/../..';
	private const THEMES    = __DIR__ . '/../fixtures/themes';
	private const REFERENCE = __DIR__ . '/../fixtures/reference';

	/** @var list<class-string> Check classes as loaded, before any run mutates $themechecks. */
	private static array $check_classes = array();

	/** @var list<string> */
	private array $added_roots = array();

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/../Support/WpCliShim.php';

		if ( ! class_exists( 'ThemeCheckCLI', false ) ) {
			if ( function_exists( 'run_themechecks' ) ) {
				self::fail( 'checkbase.php was loaded before theme-check-cli.php; its plain include() would redeclare functions.' );
			}
			// Loads checkbase.php + main.php exactly as `wp theme review` does.
			require_once self::PLUGIN . '/theme-check-cli.php';
		}

		global $themechecks;
		foreach ( $themechecks as $check ) {
			self::$check_classes[] = get_class( $check );
		}
	}

	protected function setUp(): void {
		global $wp_theme_directories;
		foreach ( array( self::THEMES, self::REFERENCE ) as $dir ) {
			$root = (string) realpath( $dir );
			if ( ! in_array( $root, (array) $wp_theme_directories, true ) ) {
				register_theme_directory( $root );
				$this->added_roots[] = $root;
			}
		}
		search_theme_directories( true );
	}

	protected function tearDown(): void {
		global $wp_theme_directories;
		$wp_theme_directories = array_values( array_diff( (array) $wp_theme_directories, $this->added_roots ) );
		$this->added_roots    = array();
		// search_theme_directories(true) rewrote this cache with the fixture roots; let the site rebuild it.
		delete_site_transient( 'theme_roots' );
		search_theme_directories( true );
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public static function themes(): array {
		$cases = array();
		foreach ( (array) glob( self::THEMES . '/*-fail', GLOB_ONLYDIR ) as $dir ) {
			$cases[ basename( (string) $dir ) ] = array( basename( (string) $dir ) );
		}
		foreach ( array( 'parity-child', 'parity-hidden', 'twentytwentyfive' ) as $slug ) {
			$cases[ $slug ] = array( $slug );
		}
		return $cases;
	}

	#[DataProvider( 'themes' )]
	public function test_admin_and_cli_produce_the_same_findings( string $slug ): void {
		$admin = $this->admin_findings( $slug );
		$cli   = $this->cli_findings( $slug );

		$this->assertSame(
			self::multiset( $admin ),
			self::multiset( $cli ),
			"Admin and CLI findings differ for '{$slug}':\n" . self::diff( $admin, $cli )
		);
	}

	public function test_child_theme_includes_parent_files_on_both_paths(): void {
		$parent_finding = static fn ( string $f ): bool => str_contains( $f, 'eval() is not allowed' );

		$this->assertNotEmpty(
			array_filter( $this->admin_findings( 'parity-child' ), $parent_finding ),
			'Admin run of parity-child is missing the parent theme finding (parity-parent/functions.php eval()).'
		);
		$this->assertNotEmpty(
			array_filter( $this->cli_findings( 'parity-child' ), $parent_finding ),
			'CLI run of parity-child is missing the parent theme finding (parity-parent/functions.php eval()): '
			. 'ThemeCheckCLI::check() calls get_files() without the include-parent flag.'
		);
	}

	public function test_hidden_folder_contents_are_excluded_on_both_paths(): void {
		$hidden_finding = static fn ( string $f ): bool => str_contains( $f, 'eval()' );

		$this->assertSame(
			array(),
			array_values( array_filter( $this->admin_findings( 'parity-hidden' ), $hidden_finding ) ),
			'Hidden-folder bug (admin): parity-hidden/.github/config was scanned; hidden folders must be excluded.'
		);
		$this->assertSame(
			array(),
			array_values( array_filter( $this->cli_findings( 'parity-hidden' ), $hidden_finding ) ),
			'Hidden-folder bug (CLI): parity-hidden/.github/config was scanned; hidden folders must be excluded.'
		);
	}

	/**
	 * @return list<string>
	 */
	private function admin_findings( string $slug ): array {
		$theme = $this->theme( $slug );
		self::reset_checks();
		run_themechecks_against_theme( $theme, $slug );

		preg_match_all( '#<li>(.*?)</li>#s', display_themechecks(), $m );
		return array_map( static fn ( string $li ): string => self::normalise( html_entity_decode( strip_tags( $li ), ENT_QUOTES ) ), $m[1] );
	}

	/**
	 * @return list<string>
	 */
	private function cli_findings( string $slug ): array {
		$this->theme( $slug );
		self::reset_checks();

		// --format=true echoes the JSON; WP_CLI::line() goes to the shim (whichever stub loaded first).
		ob_start();
		try {
			( new \ThemeCheckCLI() )->check( array( $slug ), array( 'format' => 'true' ) );
		} finally {
			$raw = (string) ob_get_clean();
		}

		$json = json_decode( $raw, true );
		$this->assertIsArray( $json, "CLI output for '{$slug}' is not valid JSON:\n{$raw}" );

		$findings = array();
		foreach ( array( 'required', 'warnings', 'recommended', 'errors' ) as $bucket ) {
			foreach ( (array) ( $json[ $bucket ] ?? array() ) as $line ) {
				// The CLI already strip_tags()'d and entity-decoded these; anything it could not
				// classify as REQUIRED/WARNING/RECOMMENDED is emitted as "ERROR: <full message>".
				$line       = 'errors' === $bucket ? (string) preg_replace( '/^ERROR:\s*/', '', (string) $line ) : (string) $line;
				$findings[] = self::normalise( $line );
			}
		}
		return $findings;
	}

	private function theme( string $slug ): \WP_Theme {
		$theme = wp_get_theme( $slug );
		if ( ! $theme->exists() ) {
			$this->markTestSkipped( "'{$slug}' is not a loadable theme (no style.css)." );
		}
		$expected = (string) realpath( ( 'twentytwentyfive' === $slug ? self::REFERENCE : self::THEMES ) . '/' . $slug );
		$this->assertSame( $expected, realpath( $theme->get_stylesheet_directory() ), "'{$slug}' resolved to the wrong theme root." );
		return $theme;
	}

	/** Fresh check instances per run: checks accumulate errors and the FSE adapter mutates the list. */
	private static function reset_checks(): void {
		global $themechecks;
		$themechecks = array();
		foreach ( self::$check_classes as $class ) {
			$themechecks[] = new $class();
		}
	}

	/** Plain text -> "SEVERITY: message" (the label may lack a colon, e.g. "REQUIRED Description: ..."). */
	private static function normalise( string $text ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		if ( preg_match( '/^(REQUIRED|WARNING|RECOMMENDED|INFO)\b\s*:?\s*(.*)$/s', $text, $m ) ) {
			return $m[1] . ': ' . $m[2];
		}
		return $text;
	}

	/**
	 * @param list<string> $findings
	 * @return array<string,int>
	 */
	private static function multiset( array $findings ): array {
		$counts = array_count_values( $findings );
		ksort( $counts );
		return $counts;
	}

	/**
	 * @param list<string> $admin
	 * @param list<string> $cli
	 */
	private static function diff( array $admin, array $cli ): string {
		$a   = self::multiset( $admin );
		$b   = self::multiset( $cli );
		$out = array();
		foreach ( array_unique( array_merge( array_keys( $a ), array_keys( $b ) ) ) as $key ) {
			$delta = ( $a[ $key ] ?? 0 ) - ( $b[ $key ] ?? 0 );
			if ( $delta > 0 ) {
				$out[] = "  admin only (x{$delta}): {$key}";
			} elseif ( $delta < 0 ) {
				$out[] = '  cli only (x' . -$delta . "): {$key}";
			}
		}
		return implode( "\n", $out );
	}
}
