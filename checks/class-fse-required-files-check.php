<?php
/**
 * Checks that Full-Site Editing themes have the required files.
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Class FSE_Required_Files_Check
 *
 * Checks that Full-Site Editing themes have the required files.
 *
 * This check is not added to the global array of checks, because it doesn't apply to all themes.
 */
class FSE_Required_Files_Check implements themecheck {
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

		$filenames = array();

		foreach ( $other_files as $php_key => $phpfile ) {
			array_push( $filenames, tc_filename( $php_key ) );
		}

		if ( ! in_array( 'theme.json', $filenames, true ) ) {
			$this->error[] = sprintf( '<span class="tc-lead tc-required">' . __( 'REQUIRED', 'theme-check' ) . '</span>: ' . __( 'Could not find the file theme.json in the theme.', 'theme-check' ), '<strong>theme.json</strong>' );
			$ret           = false;
		}

		if ( ! in_array( 'block-templates/index.html', $filenames, true ) && ! in_array( 'templates/index.html', $filenames, true ) ) {
			$this->error[] = sprintf( '<span class="tc-lead tc-required">' . __( 'REQUIRED', 'theme-check' ) . '</span>: ' . __( 'Could not find the index.html template in the theme.', 'theme-check' ), '<strong>index.html</strong>' );
			$ret           = false;
		}

		return $ret;
	}

	/**
	 * Get error messages from the checks.
	 *
	 * @return array<int, string> Error message.
	 */
	public function getError(): array {
		return $this->error;
	}
}
