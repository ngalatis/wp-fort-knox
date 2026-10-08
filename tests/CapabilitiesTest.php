<?php
/**
 * Managed capabilities, file modifications and the automatic updater.
 *
 * @package WP_Fort_Knox
 */

namespace WP_Fort_Knox\Tests;

use Brain\Monkey\Filters;

/**
 * Covers the user_has_cap, file_mod_allowed and automatic_updater_disabled layers.
 */
class CapabilitiesTest extends TestCase {

	/**
	 * The default managed capability list.
	 *
	 * @var string[]
	 */
	const DEFAULT_CAPS = array(
		'install_plugins',
		'update_plugins',
		'delete_plugins',
		'install_themes',
		'update_themes',
		'delete_themes',
		'update_core',
		'edit_plugins',
		'edit_themes',
		'edit_files',
		'install_languages',
		'update_languages',
	);

	/**
	 * The default list has exactly the file-related primitive capabilities.
	 *
	 * @return void
	 */
	public function test_default_managed_capabilities() {
		$this->assertSame( self::DEFAULT_CAPS, $this->call_private( 'get_managed_capabilities' ) );
	}

	/**
	 * Strict mode adds activate_plugins and switch_themes.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_strict_mode_adds_activation_and_theme_switching() {
		define( 'WP_FORT_KNOX_STRICT', true );

		$caps = $this->call_private( 'get_managed_capabilities' );

		$this->assertSame( array_merge( self::DEFAULT_CAPS, array( 'activate_plugins', 'switch_themes' ) ), $caps );
	}

	/**
	 * The list is filterable; non-strings and duplicates are dropped.
	 *
	 * @return void
	 */
	public function test_managed_capabilities_filter() {
		Filters\expectApplied( 'wp_fort_knox_managed_capabilities' )
			->once()
			->with( self::DEFAULT_CAPS )
			->andReturn( array( 'update_core', 'export', 'export', 7, null ) );

		$this->assertSame( array( 'update_core', 'export' ), $this->call_private( 'get_managed_capabilities' ) );
	}

	/**
	 * Present managed caps become false, absent ones are not added, others untouched.
	 *
	 * @return void
	 */
	public function test_user_has_cap_sets_present_caps_false() {
		$allcaps = array(
			'install_plugins' => true,
			'update_core'     => true,
			'edit_posts'      => true,
			'manage_options'  => true,
		);

		$result = $this->plugin->filter_capabilities( $allcaps );

		$this->assertSame(
			array(
				'install_plugins' => false,
				'update_core'     => false,
				'edit_posts'      => true,
				'manage_options'  => true,
			),
			$result
		);
		$this->assertArrayNotHasKey( 'delete_plugins', $result );
	}

	/**
	 * The runtime disable filter lifts the capability layer.
	 *
	 * @return void
	 */
	public function test_user_has_cap_untouched_when_runtime_disabled() {
		Filters\expectApplied( 'wp_fort_knox_disabled' )->andReturn( true );

		$allcaps = array( 'install_plugins' => true );

		$this->assertSame( $allcaps, $this->plugin->filter_capabilities( $allcaps ) );
	}

	/**
	 * File modifications are refused.
	 *
	 * @return void
	 */
	public function test_file_mod_allowed_false() {
		$this->assertFalse( $this->plugin->filter_file_mod_allowed( true ) );
	}

	/**
	 * The runtime disable filter lifts the file_mod_allowed layer.
	 *
	 * @return void
	 */
	public function test_file_mod_allowed_untouched_when_runtime_disabled() {
		Filters\expectApplied( 'wp_fort_knox_disabled' )->andReturn( true );

		$this->assertTrue( $this->plugin->filter_file_mod_allowed( true ) );
		$this->assertFalse( $this->plugin->filter_file_mod_allowed( false ) );
	}

	/**
	 * The automatic updater is disabled.
	 *
	 * @return void
	 */
	public function test_automatic_updater_disabled() {
		$this->assertTrue( $this->plugin->filter_automatic_updater_disabled( false ) );
	}

	/**
	 * The runtime disable filter lifts the automatic updater block too.
	 *
	 * @return void
	 */
	public function test_automatic_updater_untouched_when_runtime_disabled() {
		Filters\expectApplied( 'wp_fort_knox_disabled' )->andReturn( true );

		$this->assertFalse( $this->plugin->filter_automatic_updater_disabled( false ) );
		$this->assertTrue( $this->plugin->filter_automatic_updater_disabled( true ) );
	}
}
