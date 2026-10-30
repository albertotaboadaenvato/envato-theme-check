<?php
declare(strict_types=1);

$fixture_label = __( 'Read more', 'fixture' );
register_nav_menus( array( 'primary' => __( 'Primary menu', 'fixture' ) ) );
$fixture_count = 3;
$fixture_items = _n( '%s item', '%s items', $fixture_count, 'fixture' );
$fixture_ctx   = _x( 'Post', 'noun', 'fixture' );
?>
<a class="more" href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( $fixture_label ); ?></a>
<p><?php echo esc_html( sprintf( $fixture_items, number_format_i18n( $fixture_count ) ) ); ?></p>
<p><?php echo wp_kses_post( __( 'Powered by <strong>WordPress</strong>', 'fixture' ) ); ?></p>
<span title="<?php esc_attr_e( 'Close', 'fixture' ); ?>"><?php esc_html_e( 'Close', 'fixture' ); ?></span>
<em><?php echo esc_html( $fixture_ctx ); ?></em>
