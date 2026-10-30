<?php
/**
 * Check for files that are not allowed and files that are required.
 *
 * @package Theme Check
 */

declare(strict_types=1);

/**
 * Check for files that are not allowed and files that are required.
 */
class File_Check implements themecheck {
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

		foreach ( $php_files as $php_key => $phpfile ) {
			array_push( $filenames, strtolower( basename( $php_key ) ) );
		}
		foreach ( $other_files as $php_key => $phpfile ) {
			array_push( $filenames, strtolower( basename( $php_key ) ) );
		}
		foreach ( $css_files as $php_key => $phpfile ) {
			array_push( $filenames, strtolower( basename( $php_key ) ) );
		}

		$allowlist = array(
			'wpml-config.xml',
			'loco.xml',
			'phpcs.xml',
		);

		$blocklist = array(
			'thumbs\.db'          => __( 'Windows thumbnail store', 'theme-check' ),
			'desktop\.ini'        => __( 'windows system file', 'theme-check' ),
			'project\.properties' => __( 'NetBeans Project File', 'theme-check' ),
			'project\.xml'        => __( 'NetBeans Project File', 'theme-check' ),
			'\.kpf'               => __( 'Komodo Project File', 'theme-check' ),
			'^\.+[a-zA-Z0-9]'     => __( 'Hidden Files or Folders', 'theme-check' ),
			'php\.ini'            => __( 'PHP server settings file', 'theme-check' ),
			'dwsync\.xml'         => __( 'Dreamweaver project file', 'theme-check' ),
			'error_log'           => __( 'PHP error log', 'theme-check' ),
			'web\.config'         => __( 'Server settings file', 'theme-check' ),
			'\.sql'               => __( 'SQL dump file', 'theme-check' ),
			'__MACOSX'            => __( 'OSX system file', 'theme-check' ),
			'\.lubith'            => __( 'Lubith theme generator file', 'theme-check' ),
			'\.sh'                => __( 'Shell script file', 'theme-check' ),
			'favicon\.ico'        => __( 'Favicon', 'theme-check' ),
		);

		$musthave = array( 'index.php', 'style.css', 'readme.txt' );

		$fse_find = array_filter(
			array_keys( $other_files ),
			function ( $file_name ) {
				if ( false !== stripos( $file_name, 'templates/index.html' ) || false !== stripos( $file_name, 'block-templates/index.html' ) ) {
					return true;
				}

				return false;
			}
		);

		if ( ! empty( $fse_find ) ) {
			$fse_not_needed = array_search( 'index.php', $musthave, true );
			unset( $musthave[ $fse_not_needed ] );
		}

		checkcount();

		foreach ( $blocklist as $file => $reason ) {
			$filename = preg_grep( '/' . $file . '/', $filenames );
			if ( $filename ) {
				$commons = array_intersect( $filename, $allowlist );
				foreach ( $commons as $common ) {
					$allowed_key = array_search( $common, $filename );
					if ( false !== $allowed_key ) {
						unset( $filename[ $allowed_key ] );
					}
				}
				if ( empty( $filename ) ) {
					continue;
				}
				$error         = implode( ' ', array_unique( $filename ) );
				$this->error[] = sprintf(
					'<span class="tc-lead tc-required">%s</span>: %s',
					__( 'REQUIRED', 'theme-check' ),
					sprintf(
						/* translators: 1: file name, 2: reason the file is not allowed */
						__( '%1$s %2$s found. This file must not be in the production version of the theme.', 'theme-check' ),
						'<strong>' . $error . '</strong>',
						$reason
					)
				);
				$ret = false;
			}
		}

		foreach ( $musthave as $file ) {
			if ( ! in_array( $file, $filenames, true ) ) {
				$this->error[] = sprintf(
					'<span class="tc-lead tc-required">%s</span>: %s',
					__( 'REQUIRED', 'theme-check' ),
					sprintf(
						/* translators: %s: file name */
						__( 'Could not find the file %s in the theme.', 'theme-check' ),
						'<strong>' . $file . '</strong>'
					)
				);
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

$themechecks[] = new File_Check();
