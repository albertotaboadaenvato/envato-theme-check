<?php
/**
 * Check classic themes for add_theme_support( 'title-tag' ).
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Check classic themes for add_theme_support( 'title-tag' ).
 */
class Theme_Support_Title_Tag_Check implements themecheck {
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
		$ret = true;
		$php = implode( ' ', $php_files );

		// Look for add_theme_support( 'title-tag' ).
		if ( ! preg_match( '#add_theme_support\s?\(\s?[\'|"]title-tag#', $php ) ) {
			$ret           = false;
			$this->error[] = sprintf(
				'<span class="tc-lead tc-required">%s</span>: %s',
				__( 'REQUIRED', 'theme-check' ),
				__( 'No reference to <strong>add_theme_support( "title-tag" )</strong> was found in the theme.', 'theme-check' )
			);
		}

		return $ret;
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

$themechecks[] = new Theme_Support_Title_Tag_Check();
