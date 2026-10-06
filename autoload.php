<?php
/** Runtime PSR-4 loader; production does not require Composer. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'UWCMP\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		if ( ! preg_match( '/^[A-Za-z0-9_\\\\]+$/D', $relative ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_file( $file ) ) {
			require_once $file;
		}
	}
);
