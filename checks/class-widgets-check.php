<?php
/**
 * Check if widgets are supported in classic themes
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Check if widgets are supported in classic themes.
 */
class Widgets_Check implements themecheck {
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

		// Combine all the php files into one string to make it easier to search.
		$php = implode( ' ', $php_files );
		checkcount();

		// No widgets registered or used.
		if (
			strpos( $php, 'register_sidebar' ) === false &&
			strpos( $php, 'dynamic_sidebar' ) === false
		) {
			$this->error[] = sprintf(
				'<span class="tc-lead tc-recommended">%s</span> %s',
				__( 'RECOMMENDED', 'theme-check' ),
				__( "This theme contains no sidebars/widget areas. See <a href='https://codex.wordpress.org/Widgets_API'>Widgets API</a>", 'theme-check' )
			);
			$ret           = true;
		}

		// Widget area is registered but not used.
		if (
			strpos( $php, 'register_sidebar' ) !== false &&
			strpos( $php, 'dynamic_sidebar' ) === false
		) {
			$this->error[] = sprintf(
				'<span class="tc-lead tc-required">%s</span> %s',
				__( 'REQUIRED', 'theme-check' ),
				__( "The theme appears to use <strong>register_sidebar()</strong> but no <strong>dynamic_sidebar()</strong> was found. See: <a href='https://developer.wordpress.org/reference/functions/dynamic_sidebar/'>dynamic_sidebar</a><pre> &lt;?php dynamic_sidebar( \$index ); ?&gt;</pre>", 'theme-check' )
			);
			$ret           = false;
		}

		// Widget area is used but not registered.
		if (
			strpos( $php, 'register_sidebar' ) === false &&
			strpos( $php, 'dynamic_sidebar' ) !== false
		) {
			$this->error[] = sprintf(
				'<span class="tc-lead tc-required">%s</span> %s',
				__( 'REQUIRED', 'theme-check' ),
				__( "The theme appears to use <strong>dynamic_sidebars()</strong> but no <strong>register_sidebar()</strong> was found. See: <a href='https://developer.wordpress.org/reference/functions/register_sidebar/'>register_sidebar</a><pre> &lt;?php register_sidebar( \$args ); ?&gt;</pre>", 'theme-check' )
			);
			$ret           = false;
		}

		/**
		 * There are widgets registered, is the widgets_init action present?
		 */
		if (
			strpos( $php, 'register_sidebar' ) !== false &&
			preg_match( '/add_action\s*\(\s*("|\')widgets_init("|\')\s*,/', $php ) == false
		) {
			$this->error[] = sprintf(
				'<span class="tc-lead tc-required">%s</span> %s',
				__( 'REQUIRED', 'theme-check' ),
				sprintf(
					/* translators: %s: link to the register_sidebar() documentation */
					__( 'Sidebars need to be registered in a custom function hooked to the <strong>widgets_init</strong> action. See: %s.', 'theme-check' ),
					'<a href="https://developer.wordpress.org/reference/functions/register_sidebar/">register_sidebar()</a>'
				)
			);
			$ret = false;
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

$themechecks[] = new Widgets_Check();
