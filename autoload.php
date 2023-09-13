<?php
/**
 * Autoload package classes.
 *
 * @package ERP_Sync_Tool
 */

spl_autoload_register(
	static function ( $class ) {
        if (strpos($class, 'App\Plugins\Pvtl\Classes') !== 0) {
            // Only autoload classes in this known namespace
            return false;
        }

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
