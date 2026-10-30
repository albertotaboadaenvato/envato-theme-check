<?php
/**
 * Integration bootstrap: loads the sibling Local WordPress install (read-only).
 *
 * @package Theme Check
 */

declare(strict_types=1);

$tc_wp_root = getenv( 'WP_ROOT' );
if ( false === $tc_wp_root || '' === $tc_wp_root ) {
	$tc_wp_root = __DIR__ . '/../../../..';
}

// Point mysqli/PDO at Local's MySQL when the default socket is missing (e.g. Homebrew mysqld).
$tc_sock = (string) ini_get( 'mysqli.default_socket' );
if ( '' === $tc_sock || ! file_exists( $tc_sock ) ) {
	$tc_sock = (string) getenv( 'TC_MYSQL_SOCKET' );
	if ( '' === $tc_sock ) {
		$tc_cands = glob( getenv( 'HOME' ) . '/Library/Application Support/Local/run/*/mysql/mysqld.sock' );
		if ( ! empty( $tc_cands ) ) {
			usort( $tc_cands, static fn( string $a, string $b ): int => filemtime( $b ) <=> filemtime( $a ) );
			$tc_sock = $tc_cands[0];
		}
	}
	if ( '' !== $tc_sock ) {
		ini_set( 'mysqli.default_socket', $tc_sock );
		ini_set( 'pdo_mysql.default_socket', $tc_sock );
		if ( getenv( 'TC_DEBUG' ) ) {
			fwrite( STDERR, "TC mysql socket: {$tc_sock}\n" );
		}
	}
}

require_once $tc_wp_root . '/wp-load.php';
require_once dirname( __DIR__ ) . '/vendor/autoload.php';
