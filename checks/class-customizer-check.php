<?php
/**
 * Checks if Customizer settings have sanitization callbacks
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Check whether every Customizer setting has a sanitization callback set.
 *
 * Check whether every Customizer setting has a sanitization callback set and that it is not empty.
 */
class Customizer_Check implements themecheck {
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

		checkcount();

		foreach ( $php_files as $file_path => $file_content ) {
			// Get the arguments passed to the add_setting method.
			if ( preg_match_all( '/\$wp_customize->add_setting\(([^;]+)/', $file_content, $matches ) ) {
				// The full match is in [0], the match group in [1].
				foreach ( $matches[1] as $match ) {
					// Check if we have sanitize_callback or sanitize_js_callback.
					if (
						false === strpos( $match, 'sanitize_callback' ) &&
						false === strpos( $match, 'sanitize_js_callback' )
					) {
						/*
						 * Clean up our match to be able to present the results better.
						 *
						 * Note: The delimiter in the below regex using $match MUST be a special regex character per preg_quote().
						 */
						$ret           = false;
						$match         = preg_split( '/,/', $match );
						$grep          = tc_preg( '!' . preg_quote( $match[0], '!' ) . '!', $file_path );
						$grep          = preg_split( '/,/', $grep );
						$this->error[] = sprintf(
							'<span class="tc-lead tc-required">%s</span>: %s %s',
							__( 'REQUIRED', 'theme-check' ),
							sprintf(
								/* translators: 1: setting name, 2: file name */
								__( 'Found a Customizer setting called %1$s in %2$s that did not have a sanitization callback function. ', 'theme-check' ) . __( 'Every call to the <strong>add_setting()</strong> method needs to have a sanitization callback function passed.', 'theme-check' ),
								'<strong>' . $match[0] . '</strong>',
								'<strong>' . tc_filename( $file_path ) . '</strong>'
							),
							$grep[0]
						) . tc_finding_suffix( 'customizer' );
					} elseif ( preg_match( '/[\'"](?:sanitize_callback|sanitize_js_callback)[\'"]\s*=>\s*[\'"]\s*[\'"]/', $match ) ) {
						// There's a callback, check that no empty parameter is passed.
						$ret           = false;
						$match         = preg_split( '/,/', $match );
						$grep          = tc_preg( '/[\'"](?:sanitize_callback|sanitize_js_callback)[\'"]\s*=>\s*[\'"]\s*[\'"]/', $file_path );
						$grep          = preg_split( '/,/', $grep );
						$this->error[] = sprintf(
							'<span class="tc-lead tc-required">%s</span>: %s %s',
							__( 'REQUIRED', 'theme-check' ),
							sprintf(
								/* translators: 1: setting name, 2: file name */
								__( 'Found a Customizer setting called %1$s in %2$s that had an empty value passed as sanitization callback. You need to pass a function name as sanitization callback.', 'theme-check' ),
								'<strong>' . $match[0] . '</strong>',
								'<strong>' . tc_filename( $file_path ) . '</strong>'
							),
							$grep[0]
						) . tc_finding_suffix( 'customizer' );
					}
				}
			}
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

$themechecks[] = new Customizer_Check();
