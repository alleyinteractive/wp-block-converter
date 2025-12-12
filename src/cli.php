<?php
/**
 * WP-CLI command registration file
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter;

// Register WP-CLI commands if WP-CLI is available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	// Handle an edge case where the class might not be loaded because of
	// autoloading issues.
	if ( ! class_exists( Convert_To_Blocks_Command::class ) ) {
		require_once __DIR__ . '/class-convert-to-blocks-command.php';
	}

	\WP_CLI::add_command( 'block-converter', Convert_To_Blocks_Command::class );
}
