<?php
/**
 * Hook registration, constants and the runtime disable filter.
 *
 * @package WP_Fort_Knox
 */

namespace WP_Fort_Knox\Tests;

use Brain\Monkey\Filters;
use WP_Fort_Knox;

/**
 * Covers the constructor and is_runtime_disabled().
 */
class LifecycleTest extends TestCase {

	/**
	 * Clears the hooks the set-up instance registered and builds a new instance.
	 *
	 * @return WP_Fort_Knox
	 */
	private function restart() {
		\Brain\Monkey\tearDown();
		\Brain\Monkey\setUp();

		return $this->fresh_instance();
	}

	/**
	 * Every layer is registered with the documented priority.
	 *
	 * @return void
	 */
	public function test_hooks_registered() {
		$plugin = $this->plugin;

		$this->assertSame( WP_Fort_Knox::instance(), $plugin );
		$this->assertTrue( has_filter( 'automatic_updater_disabled', array( $plugin, 'filter_automatic_updater_disabled' ), PHP_INT_MAX ) );
		$this->assertTrue( has_filter( 'file_mod_allowed', array( $plugin, 'filter_file_mod_allowed' ), PHP_INT_MAX ) );
		$this->assertTrue( has_filter( 'user_has_cap', array( $plugin, 'filter_capabilities' ), 999 ) );
		$this->assertTrue( has_filter( 'editable_roles', array( $plugin, 'filter_editable_roles' ), PHP_INT_MAX ) );
		$this->assertTrue( has_filter( 'add_user_metadata', array( $plugin, 'filter_add_user_metadata' ), PHP_INT_MAX ) );
		$this->assertTrue( has_filter( 'update_user_metadata', array( $plugin, 'filter_update_user_metadata' ), PHP_INT_MAX ) );
		$this->assertTrue( has_filter( 'pre_update_option_default_role', array( $plugin, 'filter_default_role' ), PHP_INT_MAX ) );
		$this->assertTrue( has_filter( 'map_meta_cap', array( $plugin, 'filter_map_meta_cap' ), PHP_INT_MAX ) );
		$this->assertTrue( has_action( 'granted_super_admin', array( $plugin, 'block_super_admin_grant' ), 10 ) );
		$this->assertTrue( has_action( 'admin_notices', array( $plugin, 'render_admin_notices' ), 10 ) );
	}

	/**
	 * WP_FORT_KNOX_DISABLED registers nothing.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_hard_disable_registers_nothing() {
		define( 'WP_FORT_KNOX_DISABLED', true );
		$plugin = $this->restart();

		$this->assertFalse( has_filter( 'automatic_updater_disabled' ) );
		$this->assertFalse( has_filter( 'file_mod_allowed' ) );
		$this->assertFalse( has_filter( 'user_has_cap' ) );
		$this->assertFalse( has_filter( 'update_user_metadata', array( $plugin, 'filter_update_user_metadata' ) ) );
	}

	/**
	 * Under WP-CLI only the automatic updater block is registered.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_wp_cli_registers_only_updater_block() {
		define( 'WP_CLI', true );
		$plugin = $this->restart();

		$this->assertTrue( has_filter( 'automatic_updater_disabled', array( $plugin, 'filter_automatic_updater_disabled' ), PHP_INT_MAX ) );
		$this->assertFalse( has_filter( 'file_mod_allowed' ) );
		$this->assertFalse( has_filter( 'user_has_cap' ) );
		$this->assertFalse( has_filter( 'update_user_metadata' ) );
		$this->assertFalse( has_filter( 'editable_roles' ) );
	}

	/**
	 * WP_FORT_KNOX_ALLOW_AUTO_UPDATES drops only the updater block.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_allow_auto_updates_drops_only_updater_block() {
		define( 'WP_FORT_KNOX_ALLOW_AUTO_UPDATES', true );
		$plugin = $this->restart();

		$this->assertFalse( has_filter( 'automatic_updater_disabled' ) );
		$this->assertTrue( has_filter( 'file_mod_allowed', array( $plugin, 'filter_file_mod_allowed' ), PHP_INT_MAX ) );
	}

	/**
	 * Before init the disable filter is evaluated on every call, so late
	 * callbacks (theme functions.php) still count.
	 *
	 * @return void
	 */
	public function test_runtime_disable_evaluated_every_call_before_init() {
		Filters\expectApplied( 'wp_fort_knox_disabled' )->twice()->andReturn( false, true );

		$this->assertFalse( $this->plugin->filter_file_mod_allowed( true ) );
		$this->assertTrue( $this->plugin->filter_file_mod_allowed( true ) );
	}

	/**
	 * From init on the result is computed once per request.
	 *
	 * @return void
	 */
	public function test_runtime_disable_cached_after_init() {
		do_action( 'init' );
		Filters\expectApplied( 'wp_fort_knox_disabled' )->once()->andReturn( true );

		$this->assertTrue( $this->plugin->filter_file_mod_allowed( true ) );
		$this->assertTrue( $this->plugin->filter_file_mod_allowed( true ) );
		$this->assertFalse( $this->plugin->filter_automatic_updater_disabled( false ) );
	}

	/**
	 * A disable callback that checks a capability does not recurse; the nested
	 * check stays protected and the lock lifts once the callback returns true.
	 *
	 * @return void
	 */
	public function test_runtime_disable_callback_with_capability_check() {
		$test   = $this;
		$nested = null;
		Filters\expectApplied( 'wp_fort_knox_disabled' )->once()->andReturnUsing(
			function () use ( $test, &$nested ) {
				$nested = $test->plugin->filter_capabilities( array( 'install_plugins' => true ) );
				return true;
			}
		);
		do_action( 'init' );

		$this->assertSame( array( 'install_plugins' => true ), $this->plugin->filter_capabilities( array( 'install_plugins' => true ) ) );
		$this->assertSame( array( 'install_plugins' => false ), $nested );
	}
}
