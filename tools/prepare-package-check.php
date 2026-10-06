<?php
/** Prepare the trusted, locally built archive as a separate inactive test plugin. */
if ( PHP_SAPI !== 'cli' || getenv( 'UWCMP_INTEGRATION_TEST' ) !== '1' ) {
	exit( 1 );
}
$uwcmp_zip = new ZipArchive();
if ( $uwcmp_zip->open( '/tmp/uwcmp-development.zip' ) !== true ) {
	throw new RuntimeException( 'Unable to open development archive.' );
}
$uwcmp_target = '/var/www/html/wp-content/plugins/uwcmp-package-check';
for ( $uwcmp_index = 0; $uwcmp_index < $uwcmp_zip->numFiles; ++$uwcmp_index ) {
	$uwcmp_name = $uwcmp_zip->getNameIndex( $uwcmp_index );
	if ( ! is_string( $uwcmp_name ) || ! str_starts_with( $uwcmp_name, 'universal-content-moderation/' ) || str_contains( $uwcmp_name, '..' ) ) {
		throw new RuntimeException( 'Invalid archive entry.' );
	}
	$uwcmp_file = $uwcmp_target . '/' . substr( $uwcmp_name, strlen( 'universal-content-moderation/' ) );
	if ( ! is_dir( dirname( $uwcmp_file ) ) ) {
		mkdir( dirname( $uwcmp_file ), 0755, true );
	}
	file_put_contents( $uwcmp_file, $uwcmp_zip->getFromIndex( $uwcmp_index ) );
}
$uwcmp_zip->close();
echo "Development archive prepared for checks.\n";
