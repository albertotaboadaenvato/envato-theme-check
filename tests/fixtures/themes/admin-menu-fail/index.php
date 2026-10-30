<?php
declare(strict_types=1);

current_user_can( 'level_1' );
add_options_page( 'Fixture', 'Fixture', 'manage_options', 'fixture' );
