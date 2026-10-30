<?php
/**
 * Check if a <script> tag is included in header.php or footer.php
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Check if:
 * A <script> tag is included in header.php or footer.php
 */
class Script_Tag_Check implements themecheck {
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
		foreach ( $php_files as $file_path => $file_content ) {
			checkcount();

			// This check is limited to header.php and footer.php.
			$filename = tc_filename( $file_path );
			if ( ! in_array( $filename, array( 'header.php', 'footer.php' ), true ) ) {
				continue;
			}

			if ( false !== stripos( $file_content, '<script' ) ) {
				$grep          = tc_preg( '/<script/i', $file_path );
				$this->error[] = sprintf(
					'<span class="tc-lead tc-recommended">%s</span>: %s %s',
					__( 'RECOMMENDED', 'theme-check' ),
					sprintf(
						/* translators: %s: file name */
						__( 'Found a script tag in %s. Scripts and styles need to be enqueued or added via a hook, otherwise it is more difficult to remove or replace them with plugins or child themes.', 'theme-check' ),
						'<strong>' . $filename . '</strong>'
					),
					$grep
				) . tc_finding_suffix( 'script-tag' );
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

$themechecks[] = new Script_Tag_Check();
