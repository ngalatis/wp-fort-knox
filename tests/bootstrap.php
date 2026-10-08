<?php
/**
 * PHPUnit bootstrap for WP Fort Knox.
 *
 * @package WP_Fort_Knox
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.AlternativeFunctions

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// The plugin bails out unless ABSPATH is defined; point it at a temp dir.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', rtrim( sys_get_temp_dir(), '/\\' ) . '/wp-fort-knox-abspath/' );
}

// WP-CLI must NOT look active, otherwise the plugin registers only the updater block.
if ( defined( 'WP_CLI' ) ) {
	fwrite( STDERR, "WP_CLI must not be defined when running the test suite.\n" );
	exit( 1 );
}

/*
 * The plugin calls WP_Fort_Knox::instance() at the bottom of the file, which
 * registers hooks through add_filter()/add_action() at include time. Brain
 * Monkey defines those functions (and loads Patchwork) in Brain\Monkey\setUp(),
 * so run one set-up/tear-down cycle around the include. Do NOT define WordPress
 * function stubs here: a function defined before Patchwork loads cannot be
 * redefined later with Brain\Monkey\Functions\when() or expect().
 *
 * The plugin file is required exactly once. Its singleton then holds hooks and
 * caches from the include, so TestCase::setUp() resets the private static
 * $instance via reflection and calls WP_Fort_Knox::instance() again inside the
 * test's own Brain Monkey cycle. Tests that need a configuration constant
 * (WP_FORT_KNOX_STRICT, WP_CLI, ...) run in a separate process, because a
 * constant cannot be undefined once set.
 */
Brain\Monkey\setUp();
require_once dirname( __DIR__ ) . '/wp-fort-knox.php';
Brain\Monkey\tearDown();

require_once dirname( __DIR__ ) . '/tests/TestCase.php';
