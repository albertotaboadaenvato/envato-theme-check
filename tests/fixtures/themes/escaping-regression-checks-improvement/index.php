<?php
declare(strict_types=1);

$fixture_contact = 'support@example.com';
fixture_echo( get_bloginfo( 'name' ) );
fixture_print( $fixture_contact );
?>
<style>@media (min-width: 600px) { .site { display: flex; } }</style>
<h1><?php echo fixture_get_title(); ?></h1>
<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_theme_mod( 'fixture_brand', 'Fixture' ) ); ?></a>
<img src="<?php echo esc_url( get_template_directory_uri() . '/logo.png' ); ?>" alt="<?php echo esc_attr( fixture_request_path() ); ?>" />
<div><?php echo force_balance_tags( wp_kses_post( get_the_excerpt() ) ); ?></div>
