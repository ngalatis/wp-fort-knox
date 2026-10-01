<?php
/**
 * Protected users (map_meta_cap).
 *
 * @package WP_Fort_Knox
 */

namespace WP_Fort_Knox\Tests;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

/**
 * Covers WP_FORT_KNOX_PROTECTED_USERS and the wp_fort_knox_protected_users filter.
 */
class ProtectedUsersTest extends TestCase {

	/**
	 * Protects user 5 through the filter.
	 *
	 * @return void
	 */
	private function protect_user_5() {
		Filters\expectApplied( 'wp_fort_knox_protected_users' )->andReturn( array( 5 ) );
	}

	/**
	 * Data provider: the user-management meta caps that are denied.
	 *
	 * @return array[]
	 */
	public function provide_denied_caps() {
		return array(
			'edit_user'    => array( 'edit_user' ),
			'delete_user'  => array( 'delete_user' ),
			'remove_user'  => array( 'remove_user' ),
			'promote_user' => array( 'promote_user' ),
		);
	}

	/**
	 * Another user gets do_not_allow for a protected target.
	 *
	 * @dataProvider provide_denied_caps
	 *
	 * @param string $cap Meta capability.
	 * @return void
	 */
	public function test_other_user_denied( $cap ) {
		$this->protect_user_5();

		$caps = $this->plugin->filter_map_meta_cap( array( 'edit_users' ), $cap, 2, array( 5 ) );

		$this->assertSame( array( 'edit_users', 'do_not_allow' ), $caps );
	}

	/**
	 * A protected user can still edit themselves.
	 *
	 * @return void
	 */
	public function test_self_allowed() {
		$this->protect_user_5();

		$this->assertSame( array( 'edit_users' ), $this->plugin->filter_map_meta_cap( array( 'edit_users' ), 'edit_user', 5, array( '5' ) ) );
	}

	/**
	 * Unprotected targets and unlisted caps are left alone.
	 *
	 * @return void
	 */
	public function test_unprotected_target_and_unlisted_caps_allowed() {
		$this->protect_user_5();

		$this->assertSame( array( 'edit_users' ), $this->plugin->filter_map_meta_cap( array( 'edit_users' ), 'edit_user', 2, array( 6 ) ) );
		$this->assertSame( array( 'list_users' ), $this->plugin->filter_map_meta_cap( array( 'list_users' ), 'list_users', 2, array( 5 ) ) );
		$this->assertSame( array( 'edit_posts' ), $this->plugin->filter_map_meta_cap( array( 'edit_posts' ), 'edit_post', 2, array( 5 ) ) );
	}

	/**
	 * Nobody is protected by default.
	 *
	 * @return void
	 */
	public function test_empty_by_default() {
		$this->assertSame( array( 'edit_users' ), $this->plugin->filter_map_meta_cap( array( 'edit_users' ), 'edit_user', 2, array( 1 ) ) );
	}

	/**
	 * The runtime disable filter lifts the protection.
	 *
	 * @return void
	 */
	public function test_untouched_when_runtime_disabled() {
		Filters\expectApplied( 'wp_fort_knox_disabled' )->andReturn( true );
		Filters\expectApplied( 'wp_fort_knox_protected_users' )->never();

		$this->assertSame( array( 'edit_users' ), $this->plugin->filter_map_meta_cap( array( 'edit_users' ), 'delete_user', 2, array( 5 ) ) );
	}

	/**
	 * The constant accepts IDs and logins, comma separated with spaces; logins
	 * are resolved to IDs and unknown logins are ignored.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_constant_parsing_with_login_resolution() {
		define( 'WP_FORT_KNOX_PROTECTED_USERS', '1, agency_admin, , ghost' );
		$test = $this;
		Functions\when( 'get_user_by' )->alias(
			function ( $field, $value ) use ( $test ) {
				return ( 'login' === $field && 'agency_admin' === $value ) ? $test->make_user( 12, 'agency_admin' ) : false;
			}
		);

		$this->assertSame( array( 1, 12 ), $this->call_private( 'get_protected_user_ids' ) );
		$this->assertContains( 'do_not_allow', $this->plugin->filter_map_meta_cap( array(), 'promote_user', 2, array( 12 ) ) );
		$this->assertContains( 'do_not_allow', $this->plugin->filter_map_meta_cap( array(), 'delete_user', 2, array( 1 ) ) );
	}

	/**
	 * A capability check inside a wp_fort_knox_protected_users callback does not recurse.
	 *
	 * @return void
	 */
	public function test_filter_callback_cap_check_does_not_recurse() {
		$test   = $this;
		$nested = null;
		Filters\expectApplied( 'wp_fort_knox_protected_users' )->once()->andReturnUsing(
			function () use ( $test, &$nested ) {
				$nested = $test->plugin->filter_map_meta_cap( array( 'edit_users' ), 'edit_user', 2, array( 5 ) );
				return array( 5 );
			}
		);

		$this->assertContains( 'do_not_allow', $this->plugin->filter_map_meta_cap( array( 'edit_users' ), 'edit_user', 2, array( 5 ) ) );
		$this->assertSame( array( 'edit_users' ), $nested );
	}
}
