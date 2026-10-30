<?php
/**
 * Enforces the docs/SPEC.md check catalogue against checks/ and fixtures.
 *
 * @package EnvatoThemeCheck
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName,WordPress.WP.AlternativeFunctions -- test harness (PSR-4 class files, snake_case test names)

namespace TC\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CatalogueTest extends TestCase {

	private const ENGINES    = array( 'regex', 'token', 'sniff', 'meta' );
	private const SEVERITIES = array( 'required', 'warning', 'recommended', 'info' );

	private static function root(): string {
		return dirname( __DIR__, 2 );
	}

	/**
	 * Header-aware parse of the catalogue table.
	 *
	 * @return array<string, array<string, string>> rows keyed by id.
	 */
	public static function rows(): array {
		$lines = file( self::root() . '/docs/SPEC.md', FILE_IGNORE_NEW_LINES );
		$rows  = array();
		$head  = null;
		$in    = false;
		foreach ( (array) $lines as $line ) {
			if ( ! $in ) {
				$in = 0 === strpos( $line, '## Check catalogue' );
				continue;
			}
			if ( 0 === strpos( $line, '## ' ) ) {
				break;
			}
			if ( 0 !== strpos( trim( $line ), '|' ) ) {
				continue;
			}
			$cells = array_map( 'trim', explode( '|', trim( trim( $line ), '|' ) ) );
			if ( null === $head ) {
				$head = $cells;
				continue;
			}
			if ( preg_match( '/^:?-+:?$/', $cells[0] ) ) {
				continue;
			}
			$row                = array_combine( $head, array_pad( $cells, count( $head ), '' ) );
			$rows[ $row['id'] ] = $row;
		}
		return $rows;
	}

	/**
	 * @return array<string, string> id => absolute file.
	 */
	private static function check_files(): array {
		$ids = array();
		foreach ( (array) glob( self::root() . '/checks/class-*.php' ) as $file ) {
			$id         = (string) preg_replace( '/^class-|(-check)?\.php$/', '', basename( $file ) );
			$ids[ $id ] = $file;
		}
		return $ids;
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function ids(): array {
		$out = array();
		foreach ( array_keys( self::check_files() + self::rows() ) as $id ) {
			$out[ $id ] = array( $id );
		}
		return $out;
	}

	public function test_ids_match_check_files(): void {
		$files = self::check_files();
		$this->assertCount( 61, $files );
		$this->assertEqualsCanonicalizing( array_keys( $files ), array_keys( self::rows() ) );

		global $themechecks;
		$known = array_map( 'realpath', array_values( $files ) );
		foreach ( (array) $themechecks as $obj ) {
			$file = ( new ReflectionClass( $obj ) )->getFileName();
			$this->assertContains( realpath( (string) $file ), $known, get_class( $obj ) . ' not mapped to a checks/ file' );
		}
	}

	#[DataProvider( 'ids' )]
	public function test_row_is_complete( string $id ): void {
		$row = self::rows()[ $id ] ?? null;
		$this->assertNotNull( $row, "$id: no catalogue row" );
		foreach ( array( 'engine', 'severity', 'detects', 'pass fixture', 'fail fixture' ) as $col ) {
			$this->assertNotSame( '', $row[ $col ], "$id: empty column '$col'" );
		}
		$this->assertContains( $row['engine'], self::ENGINES, "$id: bad engine" );
		$bad = array_diff( array_map( 'trim', explode( ',', $row['severity'] ) ), self::SEVERITIES );
		$this->assertSame( array(), array_values( $bad ), "$id: bad severity" );
		$this->assertSame( "$id-pass", $row['pass fixture'], "$id: pass fixture name" );
		$this->assertSame( "$id-fail", $row['fail fixture'], "$id: fail fixture name" );
	}

	#[DataProvider( 'ids' )]
	public function test_fixture_dirs_exist( string $id ): void {
		foreach ( array( 'pass', 'fail' ) as $kind ) {
			$dir = self::root() . "/tests/fixtures/themes/$id-$kind";
			$this->assertDirectoryExists( $dir, "$id: missing fixture dir $id-$kind" );
			foreach ( array( 'style.css', 'index.php' ) as $f ) {
				$this->assertFileExists( "$dir/$f", "$id: $id-$kind lacks $f" );
			}
		}
	}

	public function test_finding_suffix_keys_name_existing_checks(): void {
		$files = self::check_files();
		$keys  = array();
		foreach ( $files as $file ) {
			preg_match_all( "/tc_finding_suffix\\(\\s*'([a-z0-9-]+)(\\/[a-z0-9_]*)?'/", (string) file_get_contents( $file ), $m );
			foreach ( $m[1] as $i => $id ) {
				$keys[ $id . $m[2][ $i ] ] = $id;
			}
		}
		$this->assertNotEmpty( $keys, 'no tc_finding_suffix() calls found in checks/' );
		foreach ( $keys as $key => $id ) {
			$this->assertArrayHasKey( $id, $files, "$key: tc_finding_suffix() key names an unknown check" );
		}
	}
}
