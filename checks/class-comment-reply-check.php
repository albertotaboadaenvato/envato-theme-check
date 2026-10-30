<?php
/**
 * Checks if the comment reply script is included in classic themes
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Checks if the comment reply script is included in classic themes.
 *
 * Checks if the comment reply script is included. If not, recommend it.
 */
class Comment_Reply_Check implements themecheck {
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

		if ( ! preg_match( '/wp_enqueue_script\(\s?("|\')comment-reply("|\')/i', $php ) ) {
			if ( ! preg_match( '/comment-reply/', $php ) ) {
				$this->error[] = sprintf(
					'<span class="tc-lead tc-recommended">%s</span>: %s',
					__( 'RECOMMENDED', 'theme-check' ),
					__( 'Could not find the <strong>comment-reply</strong> script enqueued.', 'theme-check' )
				);
			} else {
				$this->error[] = sprintf(
					'<span class="tc-lead tc-info">%s</span>: %s',
					__( 'INFO', 'theme-check' ),
					__( 'Could not find the <strong>comment-reply</strong> script enqueued, however a reference to \'comment-reply\' was found. Make sure that the comment-reply script is being enqueued properly on singular pages.', 'theme-check' )
				);
			}
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

$themechecks[] = new Comment_Reply_Check();
