<?php
/**
 * False-positive sweep: runs every check on each committed reference theme and fails on any
 * finding that is not in <slug>.allowlist.json (and on any allowlist entry nothing matches).
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName,WordPress.WP.AlternativeFunctions,WordPress.Security.EscapeOutput,WordPress.PHP.DevelopmentFunctions -- test harness

namespace TC\Tests\Sweep;

use Brain\Monkey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TC\Tests\Support\FixtureTheme;

final class ReferenceThemeSweepTest extends TestCase {

	private const ROOT = __DIR__ . '/../fixtures/reference';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public static function slugs(): array {
		$cases = array();
		foreach ( (array) glob( self::ROOT . '/*', GLOB_ONLYDIR ) as $dir ) {
			$cases[ basename( $dir ) ] = array( basename( $dir ) );
		}
		return $cases;
	}

	#[DataProvider( 'slugs' )]
	public function test_reference_theme_findings_match_allowlist( string $slug ): void {
		$findings          = $this->sweep( self::ROOT . '/' . $slug, $slug );
		[ $entries, $bad ] = $this->load_allowlist( self::ROOT . '/' . $slug . '.allowlist.json' );

		$this->assertSame( array(), $bad, "Invalid allowlist entries in {$slug}.allowlist.json:\n" . implode( "\n", $bad ) );

		$allowed = array();
		foreach ( $entries as $entry ) {
			$allowed[ self::key( $entry['check'], $entry['file'], $entry['line'] ) ] = $entry;
		}
		$found = array();
		foreach ( $findings as $finding ) {
			$found[ self::key( $finding['check'], $finding['file'], $finding['line'] ) ] = true;
		}

		$unlisted = array();
		foreach ( $findings as $finding ) {
			if ( ! isset( $allowed[ self::key( $finding['check'], $finding['file'], $finding['line'] ) ] ) ) {
				$unlisted[] = self::format( $finding );
			}
		}
		$unmatched = array();
		foreach ( $entries as $entry ) {
			if ( ! isset( $found[ self::key( $entry['check'], $entry['file'], $entry['line'] ) ] ) ) {
				$unmatched[] = sprintf( '%s  %s:%d  allowlist entry matches no finding (%s)', $entry['check'], $entry['file'], $entry['line'], $entry['reason'] );
			}
		}

		$this->assertSame( array(), $unlisted, sprintf( "%d finding(s) on %s are not in the allowlist:\n%s", count( $unlisted ), $slug, implode( "\n", $unlisted ) ) );
		$this->assertSame( array(), $unmatched, sprintf( "%d allowlist entr(ies) for %s match no finding:\n%s", count( $unmatched ), $slug, implode( "\n", $unmatched ) ) );
	}

	/**
	 * Report-only run over proprietary themes: TC_EXTRA_REFERENCE_DIR=../../themes
	 */
	public function test_extra_reference_dir_report(): void {
		$extra = getenv( 'TC_EXTRA_REFERENCE_DIR' );
		if ( false === $extra || '' === $extra ) {
			$this->markTestSkipped( 'TC_EXTRA_REFERENCE_DIR is not set.' );
		}
		$this->expectNotToPerformAssertions();

		foreach ( (array) glob( rtrim( $extra, '/' ) . '/*', GLOB_ONLYDIR ) as $dir ) {
			$slug = basename( $dir );
			fwrite( STDERR, "\n== {$slug} ==\n" );
			try {
				$lines = array_map( array( self::class, 'format' ), $this->sweep( $dir, $slug ) );
				fwrite( STDERR, ( $lines ? implode( "\n", $lines ) : '(no findings)' ) . "\n" );
			} catch ( \Throwable $e ) {
				fwrite( STDERR, 'sweep failed: ' . $e->getMessage() . "\n" );
			}
		}
	}

	/**
	 * Runs every check (minus those pruned for FSE themes) as run_themechecks() would, with the
	 * same context (theme + slug) and current-theme global, on fresh check instances.
	 *
	 * @return list<array{check:string,file:string,line:int,severity:string,message:string}>
	 */
	private function sweep( string $dir, string $slug ): array {
		global $themechecks, $theme_check_current_theme;

		$files = FixtureTheme::fromDir( $dir );
		$theme = self::theme( (string) realpath( $dir ), array_merge( $files['php'], $files['css'], $files['other'] ) );

		$saved_checks = $themechecks;
		$classes      = array_map( 'get_class', $saved_checks );
		$themechecks  = array_map( static fn( string $check_class ) => new $check_class(), $classes );
		$context      = array(
			'theme' => $theme,
			'slug'  => $slug,
		);

		$theme_check_current_theme = $theme;
		$findings                  = array();
		try {
			tc_adapt_checks_for_fse_themes( $files['php'], $files['css'], $files['other'] );
			foreach ( $themechecks as $check ) {
				$id = self::check_id( $check );
				try {
					if ( is_callable( array( $check, 'set_context' ) ) ) {
						$check->set_context( $context );
					}
					$check->check( $files['php'], $files['css'], $files['other'] );
					foreach ( (array) $check->getError() as $html ) {
						foreach ( self::parse( $id, (string) $html, $theme ) as $finding ) {
							$findings[] = $finding;
						}
					}
				} catch ( \Throwable $e ) {
					$findings[] = array(
						'check'    => $id,
						'file'     => '',
						'line'     => 0,
						'severity' => 'ERROR',
						'message'  => 'check threw ' . get_class( $e ) . ': ' . $e->getMessage(),
					);
				}
			}
		} finally {
			$themechecks               = $saved_checks;
			$theme_check_current_theme = false;
		}
		return $findings;
	}

	/**
	 * checks/class-escaping-check.php => "escaping".
	 */
	private static function check_id( object $check ): string {
		$base = basename( (string) ( new \ReflectionClass( $check ) )->getFileName(), '.php' );
		return (string) preg_replace( '/^class-|-check$/', '', $base );
	}

	/**
	 * One finding per "Line N" snippet (tc_grep/tc_preg output); one line-0 finding when there is none.
	 * The file is the first <strong> value that is a path in the theme, else "".
	 *
	 * @return list<array{check:string,file:string,line:int,severity:string,message:string}>
	 */
	private static function parse( string $id, string $html, object $theme ): array {
		$file = '';
		if ( preg_match_all( '#<strong>(.*?)</strong>#s', $html, $m ) ) {
			$paths = $theme->get_files();
			foreach ( $m[1] as $candidate ) {
				$candidate = trim( html_entity_decode( $candidate ) );
				if ( isset( $paths[ $candidate ] ) ) {
					$file = $candidate;
					break;
				}
			}
		}

		$severity = preg_match( '#tc-lead tc-([a-z]+)#', $html, $m ) ? strtoupper( $m[1] ) : 'UNKNOWN';

		$snippets = array();
		if ( preg_match_all( "#<pre class='tc-grep'>Line (\d+): (.*?)</pre>#s", $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $hit ) {
				$snippets[ (int) $hit[1] ] = self::text( $hit[2] );
			}
		}
		$message = self::text( (string) preg_replace( '#<pre\b.*?</pre>#s', ' ', $html ) );
		$message = trim( (string) preg_replace( '#^(REQUIRED|WARNING|RECOMMENDED|INFO)\s*:?\s*#', '', $message ) );

		if ( ! $snippets ) {
			return array(
				array(
					'check'    => $id,
					'file'     => $file,
					'line'     => 0,
					'severity' => $severity,
					'message'  => $message,
				),
			);
		}
		$out = array();
		foreach ( $snippets as $line => $snippet ) {
			$out[] = array(
				'check'    => $id,
				'file'     => $file,
				'line'     => $line,
				'severity' => $severity,
				'message'  => $message . ' | ' . $snippet,
			);
		}
		return $out;
	}

	private static function text( string $html ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES ) ) );
	}

	private static function key( string $check, string $file, int $line ): string {
		return "{$check}\0{$file}\0{$line}";
	}

	/**
	 * @param array{check:string,file:string,line:int,severity:string,message:string} $f
	 */
	private static function format( array $f ): string {
		return sprintf( '%s  %s:%d  %s %s', $f['check'], $f['file'], $f['line'], $f['severity'], $f['message'] );
	}

	/**
	 * @return array{0: list<array{check:string,file:string,line:int,reason:string,confirmed_by:string}>, 1: list<string>} entries, validation errors.
	 */
	private function load_allowlist( string $path ): array {
		if ( ! is_file( $path ) ) {
			return array( array(), array( "{$path}: allowlist file missing" ) );
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) {
			return array( array(), array( "{$path}: not a JSON array" ) );
		}
		$entries = array();
		$bad     = array();
		foreach ( $data as $i => $entry ) {
			$ok = is_array( $entry ) && is_int( $entry['line'] ?? null ) && $entry['line'] >= 0 && is_string( $entry['file'] ?? null );
			foreach ( array( 'check', 'reason', 'confirmed_by' ) as $field ) {
				$ok = $ok && is_string( $entry[ $field ] ?? null ) && '' !== $entry[ $field ];
			}
			if ( $ok ) {
				$entries[] = $entry;
			} else {
				$bad[] = "entry #{$i}: needs string file, non-empty check, reason, confirmed_by and integer line >= 0";
			}
		}
		return array( $entries, $bad );
	}

	/**
	 * Minimal WP_Theme stand-in: get() reads style.css headers, ArrayAccess like WP_Theme, get_files()
	 * returns relative => absolute paths (what tc_filename() uses to relativise).
	 *
	 * @param array<string,string> $files Absolute path => content.
	 */
	private static function theme( string $root, array $files ): object {
		$headers = array();
		$css     = $root . '/style.css';
		$head    = is_file( $css ) ? (string) file_get_contents( $css, false, null, 0, 8192 ) : '';
		$labels  = array(
			'Name'        => 'Theme Name',
			'ThemeURI'    => 'Theme URI',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'Description' => 'Description',
			'Version'     => 'Version',
			'Template'    => 'Template',
			'Status'      => 'Status',
			'Tags'        => 'Tags',
			'TextDomain'  => 'Text Domain',
			'RequiresWP'  => 'Requires at least',
			'TestedUpTo'  => 'Tested up to',
			'RequiresPHP' => 'Requires PHP',
		);
		foreach ( $labels as $key => $label ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $label, '/' ) . ':(.*)$/mi', $head, $m ) ) {
				$headers[ $key ] = trim( $m[1] );
			}
		}
		// WP_Theme exposes Tags as an array (empty when absent).
		$headers['Tags'] = array_values( array_filter( array_map( 'trim', explode( ',', $headers['Tags'] ?? '' ) ) ) );
		$relative        = array();
		foreach ( array_keys( $files ) as $abs ) {
			$relative[ substr( $abs, strlen( $root ) + 1 ) ] = $abs;
		}

		return new class( $headers, $root, $relative ) implements \ArrayAccess {
			/**
			 * @param array<string, string|array<string>> $headers
			 * @param array<string, string>               $relative
			 */
			public function __construct( private array $headers, private string $root, private array $relative ) {}

			/** @return string|array<string>|false */
			public function get( string $header ): string|array|false {
				return $this->headers[ $header ] ?? false;
			}

			public function get_stylesheet_directory(): string {
				return $this->root;
			}

			/** @return array<string, string> */
			public function get_files(): array {
				return $this->relative;
			}

			public function offsetExists( mixed $offset ): bool {
				return isset( $this->headers[ $offset ] );
			}

			public function offsetGet( mixed $offset ): mixed {
				return $this->get( (string) $offset );
			}

			public function offsetSet( mixed $offset, mixed $value ): void {}

			public function offsetUnset( mixed $offset ): void {}
		};
	}
}
