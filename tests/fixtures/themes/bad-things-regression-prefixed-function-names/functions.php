<?php
declare(strict_types=1);

function fixture_font_system( string $stack ): string {
	return sanitize_text_field( $stack );
}

function fixture_exec( string $task ): string {
	return sanitize_key( $task );
}

function fixture_eval( int $score ): bool {
	return $score > 3;
}

$fixture_stack = fixture_font_system( 'system-ui' );
$fixture_task  = fixture_exec( 'refresh' );
$fixture_ok    = fixture_eval( 5 );
$fixture_curl  = function_exists( 'curl_exec' );
