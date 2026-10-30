<?php
declare(strict_types=1);

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head><meta charset="utf-8"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php
wp_body_open();
add_theme_support( 'automatic-feed-links' );
post_class();
wp_link_pages();
wp_footer();
