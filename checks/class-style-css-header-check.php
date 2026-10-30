<?php
/**
 * Checks if the style.css file header includes the required items.
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Checks if the style.css file header includes the required items.
 */
class Style_CSS_Header_Check implements themecheck {
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

		$css = implode( ' ', $css_files );
		$ret = true;

		$checks = array(
			'[ \t\/*#]*Theme Name:'   => __( '<strong>Theme name:</strong> is missing from your style.css header.', 'theme-check' ),
			'[ \t\/*#]*Description:'  => __( '<strong>Description:</strong> is missing from your style.css header.', 'theme-check' ),
			'[ \t\/*#]*Author:'       => __( '<strong>Author:</strong> is missing from your style.css header.', 'theme-check' ),
			'[ \t\/*#]*Version'       => __( '<strong>Version:</strong> is missing from your style.css header.', 'theme-check' ),
			'[ \t\/*#]*License:'      => __( '<strong>License:</strong> is missing from your style.css header.', 'theme-check' ),
			'[ \t\/*#]*License URI:'  => __( '<strong>License URI:</strong> is missing from your style.css header.', 'theme-check' ),
			'[ \t\/*#]*Text Domain:'  => __( '<strong>Text Domain:</strong> is missing from your style.css header.', 'theme-check' ),
			'[ \t\/*#]*Tested up to:' => __( '<strong>Tested up to:</strong> is missing from your style.css header. Also, this should be numbers only, so <em>5.0</em> and not <em>WP 5.0</em>', 'theme-check' ),
			'[ \t\/*#]*Requires PHP:' => __( '<strong>Requires PHP:</strong> is missing from your style.css header.', 'theme-check' ),
			'[ \t\/*#]*Update URI:'   => __( '<strong>Update URI:</strong> is found from your style.css header. This feature is only for themes that are distributed outside the theme directory. Remove from your style.css file.', 'theme-check' ),
		);

		foreach ( $checks as $key => $check ) {
			checkcount();

			if ( '[ \t\/*#]*Update URI:' === $key ) {
				if ( preg_match( '/' . $key . '/i', $css, $matches ) ) {
					$this->error[] = sprintf(
						'<span class="tc-lead tc-required">%s</span> %s',
						__( 'REQUIRED', 'theme-check' ),
						$check
					);
					$ret           = false;
				}
			} elseif ( ! preg_match( '/' . $key . '/i', $css, $matches ) ) {
					$this->error[] = sprintf(
						'<span class="tc-lead tc-required">%s</span> %s',
						__( 'REQUIRED', 'theme-check' ),
						$check
					);
					$ret           = false;
			}
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

$themechecks[] = new Style_CSS_Header_Check();
