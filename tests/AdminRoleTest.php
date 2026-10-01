<?php
/**
 * Administrator role protection: editable_roles, the capabilities meta
 * sanitizer and the default_role option.
 *
 * @package WP_Fort_Knox
 */

namespace WP_Fort_Knox\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

/**
 * Covers the three administrator role layers.
 */
class AdminRoleTest extends TestCase {

	/**
	 * Editable roles as core builds them.
	 *
	 * @var array
	 */
	const ROLES = array(
		'administrator' => array( 'name' => 'Administrator' ),
		'editor'        => array( 'name' => 'Editor' ),
		'subscriber'    => array( 'name' => 'Subscriber' ),
	);

	/**
	 * Administrator is removed from the dropdown roles.
	 *
	 * @return void
	 */
	public function test_editable_roles_strips_administrator() {
		$roles = $this->plugin->filter_editable_roles( self::ROLES );

		$this->assertArrayNotHasKey( 'administrator', $roles );
		$this->assertArrayHasKey( 'editor', $roles );
	}

	/**
	 * On user-edit.php for an existing administrator the role stays, so saving
	 * the form does not demote them.
	 *
	 * @return void
	 */
	public function test_editable_roles_keeps_administrator_for_existing_admin_on_user_edit() {
		$GLOBALS['pagenow']  = 'user-edit.php';
		$_REQUEST['user_id'] = '5';
		Functions\expect( 'get_userdata' )->once()->with( 5 )->andReturn( $this->make_user( 5, 'boss', array( 'administrator' ) ) );

		$this->assertSame( self::ROLES, $this->plugin->filter_editable_roles( self::ROLES ) );
	}

	/**
	 * On user-edit.php for a non-administrator the role is stripped, so posting
	 * role=administrator makes edit_user() die with 403.
	 *
	 * @return void
	 */
	public function test_editable_roles_strips_administrator_for_non_admin_on_user_edit() {
		$GLOBALS['pagenow']  = 'user-edit.php';
		$_REQUEST['user_id'] = '6';
		Functions\expect( 'get_userdata' )->once()->with( 6 )->andReturn( $this->make_user( 6, 'sub', array( 'subscriber' ) ) );

		$this->assertArrayNotHasKey( 'administrator', $this->plugin->filter_editable_roles( self::ROLES ) );
	}

	/**
	 * The exception is limited to user-edit.php.
	 *
	 * @return void
	 */
	public function test_editable_roles_exception_only_on_user_edit() {
		$GLOBALS['pagenow']  = 'users.php';
		$_REQUEST['user_id'] = '5';
		Functions\expect( 'get_userdata' )->never();

		$this->assertArrayNotHasKey( 'administrator', $this->plugin->filter_editable_roles( self::ROLES ) );
	}

	/**
	 * The runtime disable filter lifts the editable_roles layer.
	 *
	 * @return void
	 */
	public function test_editable_roles_untouched_when_runtime_disabled() {
		Filters\expectApplied( 'wp_fort_knox_disabled' )->andReturn( true );

		$this->assertSame( self::ROLES, $this->plugin->filter_editable_roles( self::ROLES ) );
	}

	/**
	 * WP_User::set_role( 'administrator' ) on an editor: set_role() drops the
	 * editor role first, so the sanitizer puts it back.
	 *
	 * @return void
	 */
	public function test_set_role_administrator_on_editor_keeps_editor() {
		$this->meta[3]['wp_capabilities'] = array( 'editor' => true );
		Actions\expectDone( 'wp_fort_knox_blocked' )->once()->with( 'admin_role_blocked', \Mockery::type( 'array' ) );

		$result = $this->plugin->filter_update_user_metadata( null, 3, 'wp_capabilities', array( 'administrator' => true ), '' );

		$this->assertTrue( $result );
		$this->assertSame( array( array( 'update', 3, 'wp_capabilities', array( 'editor' => true ) ) ), $this->writes );
	}

	/**
	 * A value that keeps another role only loses administrator.
	 *
	 * @return void
	 */
	public function test_new_admin_stripped_other_role_kept() {
		$this->meta[3]['wp_capabilities'] = array( 'editor' => true );

		$this->plugin->filter_update_user_metadata(
			null,
			3,
			'wp_capabilities',
			array(
				'editor'        => true,
				'administrator' => true,
			),
			''
		);

		$this->assertSame( array( 'editor' => true ), $this->meta[3]['wp_capabilities'] );
	}

	/**
	 * An existing administrator is left alone (no demotion, no write by us).
	 *
	 * @return void
	 */
	public function test_existing_admin_untouched() {
		$this->meta[2]['wp_capabilities'] = array( 'administrator' => true );

		$result = $this->plugin->filter_update_user_metadata( null, 2, 'wp_capabilities', array( 'administrator' => true ), '' );

		$this->assertNull( $result );
		$this->assertSame( array(), $this->writes );
	}

	/**
	 * WP_User::add_role( 'administrator' ) on a role-less user gets the default role.
	 *
	 * @return void
	 */
	public function test_add_role_on_roleless_user_gets_default_role() {
		$this->options['default_role']    = 'author';
		$this->meta[4]['wp_capabilities'] = array();

		$this->plugin->filter_update_user_metadata( null, 4, 'wp_capabilities', array( 'administrator' => true ), '' );

		$this->assertSame( array( 'author' => true ), $this->meta[4]['wp_capabilities'] );
		$this->assertStringContainsString( 'assigned_role=author', implode( "\n", $this->log_lines() ) );
	}

	/**
	 * A tampered default_role of administrator falls back to subscriber
	 * (register_new_user() / wp_insert_user() with no role).
	 *
	 * @return void
	 */
	public function test_default_role_administrator_falls_back_to_subscriber() {
		$this->options['default_role'] = 'administrator';

		$this->plugin->filter_update_user_metadata( null, 9, 'wp_capabilities', array( 'administrator' => true ), '' );

		$this->assertSame( array( 'subscriber' => true ), $this->meta[9]['wp_capabilities'] );
	}

	/**
	 * A brand new user written through add_user_meta(): no stored caps yet, the
	 * write still happens, with the default role.
	 *
	 * @return void
	 */
	public function test_add_user_metadata_for_brand_new_user() {
		$result = $this->plugin->filter_add_user_metadata( null, 10, 'wp_capabilities', array( 'administrator' => true ), true );

		$this->assertSame( 42, $result );
		$this->assertSame( array( array( 'add', 10, 'wp_capabilities', array( 'subscriber' => true ) ) ), $this->writes );
	}

	/**
	 * Other keys and non-array values pass through untouched.
	 *
	 * @return void
	 */
	public function test_unrelated_writes_untouched() {
		$this->assertNull( $this->plugin->filter_update_user_metadata( null, 3, 'nickname', array( 'administrator' => true ), '' ) );
		$this->assertNull( $this->plugin->filter_update_user_metadata( null, 3, 'wp_2_capabilities', array( 'administrator' => true ), '' ) );
		$this->assertNull( $this->plugin->filter_update_user_metadata( null, 3, 'wp_capabilities', 'administrator', '' ) );
		$this->assertNull( $this->plugin->filter_update_user_metadata( null, 3, 'wp_capabilities', array( 'administrator' => false ), '' ) );
		$this->assertNull( $this->plugin->filter_add_user_metadata( null, 3, 'wp_user_level', 10, false ) );
		$this->assertSame( array(), $this->writes );
	}

	/**
	 * A short-circuit from an earlier filter is respected.
	 *
	 * @return void
	 */
	public function test_earlier_short_circuit_respected() {
		$this->assertFalse( $this->plugin->filter_update_user_metadata( false, 3, 'wp_capabilities', array( 'administrator' => true ), '' ) );
		$this->assertSame( array(), $this->writes );
	}

	/**
	 * The sanitized write does not re-enter the sanitizer.
	 *
	 * @return void
	 */
	public function test_recursion_guard() {
		$test   = $this;
		$nested = 'not called';
		Functions\when( 'update_user_meta' )->alias(
			function ( $user_id, $key, $value ) use ( $test, &$nested ) {
				$nested = $test->plugin->filter_update_user_metadata( null, $user_id, $key, $value + array( 'administrator' => true ), '' );
				return true;
			}
		);

		$this->assertTrue( $this->plugin->filter_update_user_metadata( null, 3, 'wp_capabilities', array( 'administrator' => true ), '' ) );
		$this->assertNull( $nested );
	}

	/**
	 * The runtime disable filter lifts the sanitizer.
	 *
	 * @return void
	 */
	public function test_sanitizer_untouched_when_runtime_disabled() {
		Filters\expectApplied( 'wp_fort_knox_disabled' )->andReturn( true );

		$this->assertNull( $this->plugin->filter_update_user_metadata( null, 3, 'wp_capabilities', array( 'administrator' => true ), '' ) );
		$this->assertNull( $this->plugin->filter_add_user_metadata( null, 3, 'wp_capabilities', array( 'administrator' => true ), true ) );
	}

	/**
	 * Multisite: the key follows switch_to_blog(), and other sites' keys are covered.
	 *
	 * @return void
	 */
	public function test_multisite_capabilities_keys() {
		Functions\when( 'is_multisite' )->justReturn( true );
		$GLOBALS['wpdb'] = $this->make_wpdb( 3, true );

		$this->plugin->filter_update_user_metadata( null, 3, 'wp_3_capabilities', array( 'administrator' => true ), '' );
		$this->plugin->filter_update_user_metadata( null, 3, 'wp_7_capabilities', array( 'administrator' => true ), '' );
		$this->plugin->filter_update_user_metadata( null, 3, 'wp_capabilities', array( 'administrator' => true ), '' );

		$this->assertSame( array( 'subscriber' => true ), $this->meta[3]['wp_3_capabilities'] );
		$this->assertSame( array( 'subscriber' => true ), $this->meta[3]['wp_7_capabilities'] );
		$this->assertSame( array( 'subscriber' => true ), $this->meta[3]['wp_capabilities'] );
		$this->assertNull( $this->plugin->filter_update_user_metadata( null, 3, 'wp_x_capabilities', array( 'administrator' => true ), '' ) );
	}

	/**
	 * Multisite: a new site's owner becomes its administrator.
	 *
	 * @return void
	 */
	public function test_multisite_new_site_owner_keeps_administrator() {
		Functions\when( 'is_multisite' )->justReturn( true );
		$GLOBALS['wpdb'] = $this->make_wpdb( 4, true );
		$test            = $this;
		$result          = 'not called';

		Actions\expectDone( 'wp_initialize_site' )->whenHappen(
			function () use ( $test, &$result ) {
				$result = $test->plugin->filter_update_user_metadata( null, 3, 'wp_4_capabilities', array( 'administrator' => true ), '' );
			}
		);
		do_action( 'wp_initialize_site' );

		$this->assertNull( $result );
		$this->assertSame( array(), $this->writes );
	}

	/**
	 * Administrator is refused as default role; the old value is kept.
	 *
	 * @return void
	 */
	public function test_default_role_option_refuses_administrator() {
		Actions\expectDone( 'wp_fort_knox_blocked' )->once()->with( 'default_role_blocked', \Mockery::type( 'array' ) );

		$this->assertSame( 'editor', $this->plugin->filter_default_role( 'administrator', 'editor', 'default_role' ) );
	}

	/**
	 * If the old value already is administrator, subscriber is used.
	 *
	 * @return void
	 */
	public function test_default_role_option_old_administrator_becomes_subscriber() {
		$this->assertSame( 'subscriber', $this->plugin->filter_default_role( 'administrator', 'administrator', 'default_role' ) );
	}

	/**
	 * Other values, and runtime-disabled requests, pass through.
	 *
	 * @return void
	 */
	public function test_default_role_option_passthrough() {
		$this->assertSame( 'author', $this->plugin->filter_default_role( 'author', 'editor', 'default_role' ) );

		Filters\expectApplied( 'wp_fort_knox_disabled' )->andReturn( true );
		$this->assertSame( 'administrator', $this->plugin->filter_default_role( 'administrator', 'editor', 'default_role' ) );
	}

	/**
	 * In an admin request the actor gets a one-time notice.
	 *
	 * @return void
	 */
	public function test_actor_notice_flagged_in_admin() {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\expect( 'set_transient' )->once()->with( 'wp_fort_knox_notice_7', 'default_role_blocked', 60 );

		$this->plugin->filter_default_role( 'administrator', 'editor', 'default_role' );
	}

	/**
	 * The blocked notice is shown to the actor whose transient it is, without a
	 * manage_options check; the screen notices still require manage_options.
	 *
	 * @return void
	 */
	public function test_actor_notice_rendered_without_manage_options() {
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'wp_kses' )->returnArg();
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
		Functions\expect( 'get_transient' )->once()->with( 'wp_fort_knox_notice_7' )->andReturn( 'admin_role_blocked' );
		Functions\expect( 'delete_transient' )->once()->with( 'wp_fort_knox_notice_7' );
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'get_current_screen' )->never();

		ob_start();
		$this->plugin->render_admin_notices();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'The Administrator role was not granted.', $html );
	}
}
