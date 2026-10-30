<?php
/**
 * Checks if core scripts are deregistered.
 *
 * Themes must use WordPress’ default libraries.
 * WordPress includes a number of libraries such as jQuery.
 * For security and stability reasons themes may not include those libraries in their own code.
 * Instead themes must use the versions of those libraries packaged with WordPress.
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Checks if core scripts are deregistered.
 */
class Deregister_Check implements themecheck {
	/**
	 * Error messages.
	 *
	 * @var array<int, string>
	 */
	protected array $error = array();

	/**
	 * Check for deregistered core scripts.
	 *
	 * @param array<string, string> $php_files   PHP files.
	 * @param array<string, string> $css_files   CSS files.
	 * @param array<string, string> $other_files Other files.
	 */
	public function check( $php_files, $css_files, $other_files ): bool {
		foreach ( $php_files as $file_path => $file_content ) {
			checkcount();

			if ( false !== strpos( $file_content, 'wp_deregister_script' ) ) {
				$grep          = tc_preg( '/wp_deregister_script/', $file_path );
				$this->error[] = sprintf(
					'<span class="tc-lead tc-warning">%s</span>: %s %s',
					__( 'WARNING', 'theme-check' ),
					sprintf(
						/* translators: %s: file name */
						__( 'Found wp_deregister_script in %1$s. Themes must not deregister scripts that are included in WordPress. Deregistering third party scripts that are registered by parent themes is allowed.', 'theme-check' ),
						'<strong>' . tc_filename( $file_path ) . '</strong>'
					),
					$grep
				) . tc_finding_suffix( 'deregister' );
			}
		}

		return true;
	}

	/**
	 * Get errors.
	 *
	 * @return array<int, string>
	 */
	public function getError(): array {
		return $this->error;
	}
}

$themechecks[] = new Deregister_Check();
