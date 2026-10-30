<?php
/**
 * Check if the screenshot size is correct
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Check if the screenshot size is correct.
 */
class Screenshot_Check implements themecheck {
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

		$ret       = true;
		$filenames = array();

		foreach ( $other_files as $other_key => $otherfile ) {
			array_push( $filenames, strtolower( basename( $other_key ) ) );
		}

		checkcount();

		if (
			in_array( 'screenshot.png', $filenames, true ) ||
			in_array( 'screenshot.jpg', $filenames, true )
		) {

			foreach ( $other_files as $other_key => $otherfile ) {

				if (
					(
						basename( $other_key ) === 'screenshot.png' ||
						basename( $other_key ) === 'screenshot.jpg'
					) &&
					preg_match( '/.*themes\/[^\/]*\/screenshot\.(png|jpg)/', $other_key )
				) {
					// we have our screenshot!
					$image = getimagesize( $other_key );
					if ( $image[0] > 1200 || $image[1] > 900 ) {
						$this->error[] = sprintf(
							'<span class="tc-lead tc-required">%s</span> %s',
							__( 'REQUIRED', 'theme-check' ),
							sprintf(
								/* translators: %s: detected screenshot size, e.g. 1200x900 */
								__( 'Screenshot is wrong size! Detected: %s. Maximum allowed size is 1200x900px.', 'theme-check' ),
								'<strong>' . $image[0] . 'x' . $image[1] . '</strong>'
							)
						);
						$ret = false;
					}
					if ( 0.75 != $image[1] / $image[0] ) {
						$this->error[] = sprintf(
							'<span class="tc-lead tc-required">%s</span> %s',
							__( 'REQUIRED', 'theme-check' ),
							__( 'Screenshot dimensions are wrong! Ratio of width to height should be 4:3.', 'theme-check' )
						);
						$ret           = false;
					}
					if ( 1200 != $image[0] || 900 != $image[1] ) {
						$this->error[] = sprintf(
							'<span class="tc-lead tc-recommended">%s</span> %s',
							__( 'RECOMMENDED', 'theme-check' ),
							__( 'Screenshot size should be 1200x900, to account for HiDPI displays. Any 4:3 image size is acceptable, but 1200x900 is preferred.', 'theme-check' )
						);
					}
				}
			}
		} else {
			$this->error[] = sprintf(
				'<span class="tc-lead tc-required">%s</span> %s',
				__( 'REQUIRED', 'theme-check' ),
				__( 'No screenshot detected! Please include a screenshot.png or screenshot.jpg.', 'theme-check' )
			);
			$ret           = false;
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

$themechecks[] = new Screenshot_Check();
