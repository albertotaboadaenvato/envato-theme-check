<?php
add_action( 'widgets_init', 'fx_widgets' );
function fx_widgets() {
	register_sidebar( array( 'name' => 'Side' ) );
}
dynamic_sidebar( 'sidebar-1' );
