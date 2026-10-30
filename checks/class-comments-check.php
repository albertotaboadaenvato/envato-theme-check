<?php
/**
 * Checks if comment templates are included in classic themes
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Checks if comment templates are included in classic themes.
 *
 * Checks if comment templates are included. If not, recommend them.
 */
class Comments_Check implements themecheck {
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

		$checks = array(
			'comments_template\s*\(' => __( 'See: <a href="https://developer.wordpress.org/reference/functions/comments_template/">comments_template</a><pre> &lt;?php comments_template( $file, $separate_comments ); ?&gt;</pre>', 'theme-check' ),
			'wp_list_comments\s*\('  => __( 'See: <a href="https://developer.wordpress.org/reference/functions/wp_list_comments/">wp_list_comments</a><pre> &lt;?php wp_list_comments( $args ); ?&gt;</pre>', 'theme-check' ),
			'comment_form\s*\('      => __( 'See: <a href="https://developer.wordpress.org/reference/functions/comment_form/">comment_form</a><pre> &lt;?php comment_form(); ?&gt;</pre>', 'theme-check' ),
		);

		foreach ( $checks as $key => $check ) {
			checkcount();
			if ( ! preg_match( '/' . $key . '/i', $php ) ) {
				$key           = str_replace( '\s*\(', '', $key );
				$this->error[] = sprintf(
					'<span class="tc-lead tc-recommended">%s</span>: %s',
					__( 'RECOMMENDED', 'theme-check' ),
					sprintf(
						/* translators: 1: missing function or file, 2: explanation */
						__( 'Could not find %1$s. %2$s', 'theme-check' ),
						'<strong>' . $key . '</strong>',
						$check
					)
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

$themechecks[] = new Comments_Check();
