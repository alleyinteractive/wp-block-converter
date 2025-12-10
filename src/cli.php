<?php
/**
 * WP-CLI command registration file
 *
 * @package wp-block-converter
 */

namespace Alley\WP\Block_Converter;

// Register WP-CLI commands if WP-CLI is available.
if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( \WP_CLI::class ) ) {
	\WP_CLI::add_command( 'block-converter', Convert_To_Blocks_Command::class );
}
