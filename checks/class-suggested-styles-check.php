<?php
/**
 * Checks if style.css includes theme and author URI
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Checks if style.css includes theme and author URI
 */
class Suggested_Styles_Check implements themecheck {
	/**
	 * Error messages, warnings and info notices.
	 *
	 * @var array<int, string> $error
	 */
	protected array $error = array();

	/**
	 * Check that return true for good/okay/acceptable, false for bad/not-okay/unacceptable.
	 *
	 * @param array<string, string> $php_files File paths and content for PHP files.
	 * @param array<string, string> $css_files File paths and content for CSS files.
	 * @param array<string, string> $other_files Folder names, file paths and content for other files.
	 */
	public function check( $php_files, $css_files, $other_files ): bool {

		// Combine all the css files into one string to make it easier to search.
		$css = implode( ' ', $css_files );

		checkcount();

		$checks = array(
			'[ \t\/*#]*Theme URI:'  => 'Theme URI:',
			'[ \t\/*#]*Author URI:' => 'Author URI:',
		);

		foreach ( $checks as $key => $check ) {
			if ( ! preg_match( '/' . $key . '/i', $css, $matches ) ) {
				/* translators: %s: style.css header name */
				$this->error[] = sprintf( '<span class="tc-lead tc-recommended">' . __( 'RECOMMENDED', 'theme-check' ) . '</span>: ' . __( '%s is missing from your style.css header.', 'theme-check' ), '<strong>' . $check . '</strong>' ) . tc_finding_suffix( 'suggested-styles' );
			}
		}

		$css_class_checks = array(
			'\.sticky'          => __( '<strong>.sticky</strong> css class is recommended in your theme css.', 'theme-check' ),
			'\.bypostauthor'    => __( '<strong>.bypostauthor</strong> css class is recommended in your theme css.', 'theme-check' ),
			'\.alignleft'       => __( '<strong>.alignleft</strong> css class is recommended in your theme css.', 'theme-check' ),
			'\.alignright'      => __( '<strong>.alignright</strong> css class is recommended in your theme css.', 'theme-check' ),
			'\.aligncenter'     => __( '<strong>.aligncenter</strong> css class is recommended in your theme css.', 'theme-check' ),
			'\.wp-caption'      => __( '<strong>.wp-caption</strong> css class is recommended in your theme css.', 'theme-check' ),
			'\.wp-caption-text' => __( '<strong>.wp-caption-text</strong> css class is recommended in your theme css.', 'theme-check' ),
			'\.gallery-caption' => __( '<strong>.gallery-caption</strong> css class is recommended in your theme css.', 'theme-check' ),
		);

		foreach ( $css_class_checks as $key => $check ) {
			if ( ! preg_match( '/' . $key . '/i', $css, $matches ) ) {
				$this->error[] = sprintf(
					'<span class="tc-lead tc-recommended">%s</span> %s',
					__( 'RECOMMENDED', 'theme-check' ),
					$check
				) . tc_finding_suffix( 'suggested-styles' );
			}
		}

		return true;
	}

	/**
	 * Get error messages from the checks.
	 *
	 * @return array Error message.
	 */
	/**
	 * Get error messages.
	 *
	 * @return array<int, string>
	 */
	public function getError(): array {
		return $this->error;
	}
}

$themechecks[] = new Suggested_Styles_Check();
