<?php
declare(strict_types=1);

$fixture_count  = (int) get_comments_number();
$fixture_author = get_the_author();
?>
<p><?php printf( esc_html( _n( '%s comment', '%s comments', $fixture_count, 'fixture' ) ), esc_html( number_format_i18n( $fixture_count ) ) ); ?></p>
<p><?php echo esc_html( sprintf( __( 'By %s', 'fixture' ), $fixture_author ) ); ?></p>
<p><?php esc_html_e( 'Untranslated domain-less string' ); ?></p>
