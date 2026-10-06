<?php
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
/** Analysis-only values defined by WordPress bootstrap at runtime. */
define( 'ABSPATH', __DIR__ . '/analysis-wordpress/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
