<?php
declare(strict_types=1);

function fixture_contact_methods( array $methods ): array {
	$methods['twitter'] = 'Twitter';
	return $methods;
}
add_filter( 'user_contactmethods', 'fixture_contact_methods' );
