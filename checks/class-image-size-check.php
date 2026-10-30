<?php
/**
 * Check if the image sizes are larger than necessary
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Check if the image sizes are larger than necessary
 */
class Image_Size_Check implements themecheck {
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

		checkcount();

		foreach ( $other_files as $other_key => $otherfile ) {

			$file = wp_check_filetype_and_ext( $other_key, basename( $other_key ) );
			if ( 'image/' !== substr( (string) $file['type'], 0, 6 ) ) {
				continue;
			}

			// Check if the file is larger than 500 KB.
			$image_size = filesize( $other_key );
			if ( $image_size < 500 * KB_IN_BYTES ) {
				continue;
			}

			$this->error[] = sprintf(
				'<span class="tc-lead tc-warning">%s</span>: %s',
				__( 'WARNING', 'theme-check' ),
				sprintf(
					/* translators: %1$s file name. %2$s file size. */
					__( '%1$s is %2$s in size. Large file sizes have a negative impact on website performance and loading time. Compress images before using them.', 'theme-check' ),
					'<strong>' . tc_filename( $other_key ) . '</strong>',
					size_format( $image_size, 1 )
				)
			);
		}

		return true;
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

$themechecks[] = new Image_Size_Check();
