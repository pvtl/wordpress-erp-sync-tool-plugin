<?php
/**
 * Autoload package classes.
 *
 * @package ERP_Sync_Tool
 */

spl_autoload_register(
	static function ( $class ) {
		$file = str_replace(
			array( 'App\\Plugins\\Pvtl\\', '\\', '_', 'Classes' . DIRECTORY_SEPARATOR ),
			array( '', DIRECTORY_SEPARATOR, '-', 'classes' . DIRECTORY_SEPARATOR . 'class-' ),
			$class
		);
		$file = ERP_PATH . '/' . strtolower( $file ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}

		return false;
	}
);