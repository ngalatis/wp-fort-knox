<?php
/**
 * Logging and the wp_fort_knox_blocked action.
 *
 * @package WP_Fort_Knox
 */

namespace WP_Fort_Knox\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

/**
 * Covers WP_Fort_Knox::log().
 */
class LoggingTest extends TestCase {

	/**
	 * One line with event, actor, IP, URI and the context pairs.
	 *
	 * @return void
	 */
	public function test_line_format() {
		$test = $this;
		Functions\when( 'wp_get_current_user' )->alias(
			function () use ( $test ) {
				return $test->make_user( 7, 'shop_manager' );
			}
		);
		$this->meta[3]['wp_capabilities'] = array( 'editor' => true );

		$this->plugin->filter_update_user_metadata( null, 3, 'wp_capabilities', array( 'administrator' => true ), '' );

		$this->assertSame(
			array( '[WP Fort Knox] admin_role_blocked | actor=7:shop_manager | ip=203.0.113.7 | uri=/wp-admin/user-edit.php?user_id=5 | user_id=3 meta_key=wp_capabilities assigned_role=editor' ),
			$this->log_lines()
		);
	}

	/**
	 * Logged out, no server variables, booleans and non-scalars.
	 *
	 * @return void
	 */
	public function test_logged_out_actor_and_value_types() {
		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI'] );

		$this->call_private(
			'log',
			'super_admin_blocked',
			array(
				'user_id' => 4,
				'revoked' => true,
				'list'    => array( 1 ),
			)
		);

		$this->assertSame(
			array( '[WP Fort Knox] super_admin_blocked | actor=0:none | ip=unknown | uri=unknown | user_id=4 revoked=true' ),
			$this->log_lines()
		);
	}

	/**
	 * Keys containing "pass" are never written.
	 *
	 * @return void
	 */
	public function test_password_keys_skipped() {
		$this->call_private(
			'log',
			'admin_role_blocked',
			array(
				'user_id'   => 3,
				'user_pass' => 'hunter2',
				'password'  => 'hunter2',
			)
		);

		$lines = $this->log_lines();
		$this->assertCount( 1, $lines );
		$this->assertStringNotContainsString( 'hunter2', $lines[0] );
		$this->assertStringEndsWith( '| user_id=3', $lines[0] );
	}

	/**
	 * Returning false from wp_fort_knox_log suppresses the line, not the action.
	 *
	 * @return void
	 */
	public function test_log_filter_suppresses_line() {
		Filters\expectApplied( 'wp_fort_knox_log' )->once()->with( true, 'default_role_blocked', \Mockery::type( 'array' ) )->andReturn( false );
		Actions\expectDone( 'wp_fort_knox_blocked' )->once();

		$this->plugin->filter_default_role( 'administrator', 'editor', 'default_role' );

		$this->assertSame( array(), $this->log_lines() );
	}

	/**
	 * The action receives the event and the context.
	 *
	 * @return void
	 */
	public function test_blocked_action_fires_with_context() {
		Actions\expectDone( 'wp_fort_knox_blocked' )->once()->with(
			'default_role_blocked',
			array(
				'option'   => 'default_role',
				'restored' => 'editor',
			)
		);

		$this->plugin->filter_default_role( 'administrator', 'editor', 'default_role' );
	}

	/**
	 * Super Admin grants are revoked and logged.
	 *
	 * @return void
	 */
	public function test_super_admin_grant_revoked() {
		Functions\expect( 'revoke_super_admin' )->once()->with( 8 )->andReturn( true );
		Actions\expectDone( 'wp_fort_knox_blocked' )->once()->with(
			'super_admin_blocked',
			array(
				'user_id' => 8,
				'revoked' => true,
			)
		);

		$this->plugin->block_super_admin_grant( 8 );
	}
}
