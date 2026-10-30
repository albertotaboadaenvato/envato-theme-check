<?php
/**
 * PHP version compatibility of theme code, via PHPCompatibilityWP sniffs run in-process.
 *
 * Engine decision: PHPCS/PHPCompatibilityWP sniffs run in-process (ADR 0001, kept with the project planning docs).
 *
 * @package Theme Check
 */

declare(strict_types=1);

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\DummyFile;
use PHP_CodeSniffer\Ruleset;

/**
 * Flags PHP constructs removed, deprecated or unavailable between the theme's
 * declared "Requires PHP" (min 7.4) and PHP 8.4.
 */
final class PHP_Compatibility_Check implements themecheck {

	private const MIN_FLOOR = '7.4';
	private const CEILING   = '8.4';

	/**
	 * Error messages, warnings and info notices.
	 *
	 * @var list<string>
	 */
	private array $error = array();

	/**
	 * Lowest PHP version the theme must run on (Major.Minor).
	 *
	 * @var string
	 */
	private string $floor = self::MIN_FLOOR;

	/**
	 * Shared across instances: building the ruleset loads ~180 sniffs.
	 *
	 * @var Ruleset|null
	 */
	private static ?Ruleset $ruleset = null;

	/**
	 * Config the cached ruleset was built with.
	 *
	 * @var Config|null
	 */
	private static ?Config $config = null;

	/**
	 * Reads the theme's "Requires PHP" header.
	 *
	 * @param array<string,mixed> $context Context from run_themechecks(); 'theme' is a WP_Theme (or test double).
	 */
	public function set_context( array $context ): void {
		$this->floor = self::MIN_FLOOR;
		$theme       = $context['theme'] ?? null;
		if ( ! is_object( $theme ) || ! method_exists( $theme, 'get' ) ) {
			return;
		}
		$declared = $theme->get( 'RequiresPHP' );
		if ( is_string( $declared ) && preg_match( '/^\s*(\d+\.\d+)/', $declared, $m ) && version_compare( $m[1], self::MIN_FLOOR, '>' ) ) {
			$this->floor = $m[1];
		}
	}

	/**
	 * Runs PHPCompatibilityWP over every theme PHP file.
	 *
	 * @param array<string,string> $php_files   File paths => comment-stripped content (only the keys are used).
	 * @param array<string,string> $css_files   File paths and content for CSS files.
	 * @param array<string,string> $other_files Folder names, file paths and content for other files.
	 */
	public function check( $php_files, $css_files, $other_files ): bool {
		$this->error = array(
			sprintf(
				'<span class="tc-lead tc-info">%s</span>: %s',
				__( 'INFO', 'theme-check' ),
				/* translators: 1: lowest PHP version, 2: highest PHP version. */
				sprintf( __( 'PHP compatibility evaluated for %1$s–%2$s.', 'theme-check' ), $this->floor, self::CEILING )
			),
		);

		$ruleset = self::ruleset();
		if ( null === $ruleset || null === self::$config ) {
			$this->error[] = sprintf(
				'<span class="tc-lead tc-warning">%s</span>: %s',
				__( 'WARNING', 'theme-check' ),
				__( 'PHPCompatibility not installed; PHP version compatibility was not checked.', 'theme-check' )
			);
			return true;
		}

		self::use_test_version( $this->floor . '-' . self::CEILING );

		$trigger = tc_finding_suffix( 'php-compatibility' );
		$pass    = true;

		foreach ( array_keys( $php_files ) as $path ) {
			checkcount();
			// The values are tc_strip_comments() output; sniff the original so lines match.
			$content = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
			if ( false === $content ) {
				continue;
			}
			$file = new DummyFile( $content, $ruleset, self::$config );
			try {
				$file->process();
			} catch ( \Throwable $e ) {
				continue;
			}
			$filename = tc_filename( $path );
			$found    = array(
				'tc-required' => $file->getErrors(),
				'tc-warning'  => $file->getWarnings(),
			);
			foreach ( $found as $class => $by_line ) {
				foreach ( $by_line as $line => $by_column ) {
					foreach ( $by_column as $messages ) {
						foreach ( $messages as $message ) {
							if ( str_starts_with( (string) $message['source'], 'Internal.' ) ) {
								continue;
							}
							$pass          = $pass && 'tc-required' !== $class;
							$this->error[] = sprintf(
								'<span class="tc-lead %1$s">%2$s</span>: %3$s %4$s%5$s',
								$class,
								'tc-required' === $class ? __( 'REQUIRED', 'theme-check' ) : __( 'WARNING', 'theme-check' ),
								esc_html( $this->reword( (string) $message['message'] ) ),
								sprintf(
									/* translators: 1: file name, 2: line number. */
									__( 'Found in %1$s on line %2$d.', 'theme-check' ),
									'<strong>' . esc_html( $filename ) . '</strong>',
									(int) $line
								),
								$trigger
							);
						}
					}
				}
			}
			$file->cleanUp();
		}

		return $pass;
	}

	/**
	 * Error messages, warnings and info notices.
	 *
	 * @return list<string>
	 */
	public function getError(): array {
		return $this->error;
	}

	/**
	 * PHPCompatibility phrases unavailable features as "is not present in PHP version 8.0 or earlier";
	 * name the first version that has the feature and the theme's floor instead. Always ends with a period.
	 *
	 * @param string $message Sniff message.
	 */
	private function reword( string $message ): string {
		$message = (string) preg_replace_callback(
			'/is not (?:present|available|supported) in PHP version (\d+)\.(\d+) or earlier/i',
			function ( array $m ): string {
				// Last minor of each major jumps to the next major (5.6 -> 7.0, 7.4 -> 8.0).
				$next  = array(
					'5.6' => '7.0',
					'7.4' => '8.0',
				);
				$since = $next[ $m[1] . '.' . $m[2] ] ?? $m[1] . '.' . ( (int) $m[2] + 1 );
				/* translators: 1: PHP version that introduced the feature, 2: theme's declared minimum PHP version. */
				return sprintf( __( 'requires PHP %1$s and is unavailable at the declared minimum of PHP %2$s', 'theme-check' ), $since, $this->floor );
			},
			trim( $message )
		);
		return rtrim( $message, '.' ) . '.';
	}

	/**
	 * Builds (once) the PHPCompatibilityWP ruleset; null if PHPCS is not installed.
	 */
	private static function ruleset(): ?Ruleset {
		if ( null !== self::$ruleset ) {
			return self::$ruleset;
		}
		$vendor = dirname( __DIR__ ) . '/vendor';
		if ( ! class_exists( Config::class ) ) {
			foreach ( array( $vendor . '/autoload.php', $vendor . '/squizlabs/php_codesniffer/autoload.php' ) as $autoload ) {
				if ( is_readable( $autoload ) ) {
					require_once $autoload;
				}
			}
		}
		if ( ! class_exists( Config::class ) || ! is_dir( $vendor . '/phpcompatibility/phpcompatibility-wp' ) ) {
			return null;
		}
		if ( ! defined( 'PHP_CODESNIFFER_VERBOSITY' ) ) {
			define( 'PHP_CODESNIFFER_VERBOSITY', 0 );
		}
		if ( ! defined( 'PHP_CODESNIFFER_CBF' ) ) {
			define( 'PHP_CODESNIFFER_CBF', false );
		}
		// Loading Tokens defines the PHPCS T_* constants sniffs rely on.
		class_exists( \PHP_CodeSniffer\Util\Tokens::class );

		// Explicit paths: the dev-only composer installer that writes CodeSniffer.conf is absent in the shipped zip.
		$paths = array();
		foreach ( array( 'phpcompatibility/php-compatibility', 'phpcompatibility/phpcompatibility-paragonie', 'phpcompatibility/phpcompatibility-wp', 'phpcsstandards/phpcsutils' ) as $package ) {
			$paths[] = $vendor . '/' . $package;
		}

		try {
			// ponytail: PHPCS 3 Config peeks at a non-TTY STDIN (200 ms) once per process; harmless for admin, noted for piped WP-CLI.
			self::$config  = new Config(
				array(
					'--runtime-set',
					'installed_paths',
					implode( ',', $paths ),
					'--standard=PHPCompatibilityWP',
					'--extensions=php',
					'-q',
				),
				false
			);
			self::$ruleset = new Ruleset( self::$config );
		} catch ( \Throwable $e ) {
			self::$config  = null;
			self::$ruleset = null;
		}
		return self::$ruleset;
	}

	/**
	 * Sets testVersion for the next files; PHPCompatibility caches it statically per process,
	 * so clear that cache when themes with different floors are checked in one request.
	 *
	 * @param string $range testVersion range, e.g. "7.4-8.4".
	 */
	private static function use_test_version( string $range ): void {
		Config::setConfigData( 'testVersion', $range, true );
		if ( ! class_exists( \PHPCompatibility\Helpers\ScannedCode::class ) ) {
			return;
		}
		foreach ( array( 'testVersion', 'testVersions' ) as $name ) {
			( new \ReflectionProperty( \PHPCompatibility\Helpers\ScannedCode::class, $name ) )->setValue( null, null );
		}
	}
}

$themechecks[] = new PHP_Compatibility_Check();
