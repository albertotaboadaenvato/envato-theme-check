<?php
/**
 * Checks that gravatars are supported in comments.
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Checks that gravatars are supported in comments.
 */
class Gravatar_Check implements themecheck {
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

		$php = implode( ' ', $php_files );

		checkcount();

		if (
			strpos( $php, 'get_avatar' ) === false &&
			strpos( $php, 'wp_list_comments' ) === false
		) {
			$this->error[] = sprintf(
				'<span class="tc-lead tc-recommended">%s</span>: %s',
				__( 'RECOMMENDED', 'theme-check' ),
				__( "This theme doesn't seem to support the standard avatar functions. Use <strong>get_avatar</strong> or <strong>wp_list_comments</strong> to add this support.", 'theme-check' )
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

$themechecks[] = new Gravatar_Check();
