<?php
/**
 * Checks if tags are supported
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Checks if tags are supported.
 */
class Tag_Check implements themecheck {
	/**
	 * Lookup data.
	 *
	 * @var array<int, string>
	 */
	protected array $error = array();

	/**
	 * Run the check.
	 *
	 * @param array<string, string> $php_files   File paths and content for PHP files.
	 * @param array<string, string> $css_files   File paths and content for CSS files.
	 * @param array<string, string> $other_files Folder names, file paths and content for other files.
	 */
	public function check( $php_files, $css_files, $other_files ): bool {

		// Combine all the php files into one string to make it easier to search.
		$php = implode( ' ', $php_files );
		checkcount();

		if (
			strpos( $php, 'the_tags' ) === false &&
			strpos( $php, 'the_taxonomies' ) === false &&
			strpos( $php, 'get_the_tag_list' ) === false &&
			strpos( $php, 'get_the_term_list' ) === false
		) {
			$this->error[] = sprintf(
				'<span class="tc-lead tc-recommended">%s</span> %s',
				__( 'RECOMMENDED', 'theme-check' ),
				__( "This theme doesn't seem to display tags. Modify it to display tags in appropriate locations.", 'theme-check' )
			);
		}

		return true;
	}

	/**
	 * Get error messages.
	 *
	 * @return array<int, string>
	 */
	public function getError(): array {
		return $this->error;
	}
}

$themechecks[] = new Tag_Check();
