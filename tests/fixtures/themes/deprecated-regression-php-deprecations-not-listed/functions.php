<?php
declare(strict_types=1);

function fixture_legacy_label( string $label ): string {
	return utf8_encode( $label ) . strftime( '%Y' );
}

function fixture_first_post(): void {
	$query = new WP_Query( array( 'posts_per_page' => 1 ) );
	if ( $query->have_posts() ) {
		$query->next_post();
	}
}
