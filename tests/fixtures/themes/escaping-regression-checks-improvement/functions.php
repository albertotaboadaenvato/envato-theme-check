<?php
declare(strict_types=1);

function fixture_echo( string $value ): void {
	echo esc_html( $value );
}

function fixture_print( string $value ): void {
	print esc_html( $value );
}

function fixture_get_title(): string {
	return esc_html( get_the_title() );
}

function fixture_latest_ids(): array {
	global $wpdb;
	$static   = $wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' LIMIT 5" );
	$prepared = $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_author = %d", get_current_user_id() )
	);
	return array( $static, $prepared );
}

function fixture_request_path(): string {
	return isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
}
