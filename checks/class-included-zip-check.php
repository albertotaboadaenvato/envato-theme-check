<?php
/**
 * Check if zip files are included
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Check if zip files are included.
 */
class Included_Zip_Check implements themecheck {
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

		foreach ( $other_files as $other_key => $otherfile ) {
			array_push( $filenames, strtolower( basename( $other_key ) ) );
		}

		$blocklist = array(
			'\.zip' => __( 'Zipped Plugin', 'theme-check' ),
		);

		checkcount();

		foreach ( $blocklist as $file => $reason ) {
			$filename = preg_grep( '/' . $file . '/', $filenames );
			if ( $filename ) {
				$error         = implode( ' ', array_unique( $filename ) );
				$this->error[] = sprintf(
					'<span class="tc-lead tc-warning">%s</span>: %s',
					__( 'REQUIRED', 'theme-check' ),
					sprintf(
						/* translators: %s: zip file name */
						__( '<strong>Zip file found.</strong> Plugins are not allowed in themes unless they are being included with TGMPA. The zip file found was <strong>%s</strong>.', 'theme-check' ),
						$error
					)
				) . tc_finding_suffix( 'included-zip' );
				$ret = false;
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

$themechecks[] = new Included_Zip_Check();
