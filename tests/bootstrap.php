<?php
/**
 * WP Block Converter Test Bootstrap
 */

/**
 * Visit {@see https://mantle.alley.co/testing/test-framework.html} to learn more.
 */
\Mantle\Testing\manager()
	->with_sqlite()
	->maybe_rsync_plugin()
	->install();
