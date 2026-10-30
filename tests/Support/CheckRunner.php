<?php
/**
 * Runs one check class against fixture arrays and returns plain "SEVERITY: message" strings.
 *
 * @package Theme Check
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting,Generic.Commenting,Generic.CodeAnalysis.UnusedFunctionParameter,WordPress.Files.FileName,WordPress.NamingConventions.ValidFunctionName,WordPress.Security.EscapeOutput,WordPress.WP.AlternativeFunctions -- test harness (PSR-4 class files, passthrough WP stubs)

namespace TC\Tests\Support;

final class CheckRunner {

	private const SEVERITY = array(
		'tc-required'    => 'REQUIRED',
		'tc-warning'     => 'WARNING',
		'tc-recommended' => 'RECOMMENDED',
		'tc-info'        => 'INFO',
	);

	/**
	 * @param class-string                                                                    $check_class
	 * @param array{php?: array<string,string>, css?: array<string,string>, other?: array<string,string>} $files   As returned by FixtureTheme::fromDir().
	 * @param array<string,mixed>                                                             $context Optional context passed to set_context(), as run_themechecks() does.
	 * @return list<string>
	 */
	public static function run( string $check_class, array $files, array $context = array() ): array {
		$check = new $check_class();
		if ( $context && is_callable( array( $check, 'set_context' ) ) ) {
			$check->set_context( $context );
		}
		$check->check( $files['php'] ?? array(), $files['css'] ?? array(), $files['other'] ?? array() );

		$errors = array();
		foreach ( (array) $check->getError() as $error ) {
			$errors[] = self::normalise( (string) $error );
		}
		return $errors;
	}

	private static function normalise( string $html ): string {
		foreach ( self::SEVERITY as $class => $label ) {
			$html = (string) preg_replace( '#<span class="tc-lead ' . $class . '">.*?</span>#s', $label, $html );
		}
		$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES );
		return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
	}
}
