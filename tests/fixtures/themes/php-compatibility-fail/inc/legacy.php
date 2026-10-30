<?php
// Fixture: one instance of each construct removed or deprecated in PHP 8.0–8.4.

$fixture_callback = create_function( '$a', 'return $a;' );

function fixture_legacy_nullable( Foo $x = null ) {
	return $x;
}

$fixture_name = 'world';
echo "Hello ${fixture_name}";
