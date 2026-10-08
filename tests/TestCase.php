<?php
/**
 * Base test case for WP Fort Knox.
 *
 * @package WP_Fort_Knox
 */

namespace WP_Fort_Knox\Tests;

// phpcs:disable WordPress.PHP.IniSet, WordPress.WP.AlternativeFunctions -- Tests capture error_log() output in a temp file.

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use ReflectionClass;
use ReflectionMethod;
use WP_Fort_Knox;

/**
 * Sets up and tears down Brain Monkey around every test and hands each test a
 * fresh plugin instance plus a few WordPress stand-ins.
 */
abstract class TestCase extends PHPUnitTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Fresh plugin instance for the current test.
	 *
	 * @var WP_Fort_Knox
	 */
	protected $plugin;

	/**
	 * In-memory user meta: user ID => meta key => value.
	 *
	 * @var array
	 */
	protected $meta = array();

	/**
	 * Writes the plugin performed through add_user_meta()/update_user_meta().
	 *
	 * @var array[]
	 */
	protected $writes = array();

	/**
	 * In-memory options.
	 *
	 * @var array
	 */
	protected $options = array( 'default_role' => 'subscriber' );

	/**
	 * Temporary file that receives error_log() output.
	 *
	 * @var string
	 */
	protected $log_file;

	/**
	 * Previous error_log ini value.
	 *
	 * @var string|false
	 */
	private $previous_error_log;

	/**
	 * Set up Brain Monkey, the WordPress stand-ins and a fresh plugin instance.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->log_file           = tempnam( sys_get_temp_dir(), 'wpfk' );
		$this->previous_error_log = ini_set( 'error_log', $this->log_file );

		$GLOBALS['wpdb']        = $this->make_wpdb( 1, false );
		$GLOBALS['pagenow']     = 'index.php';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$_SERVER['REQUEST_URI'] = '/wp-admin/user-edit.php?user_id=5';
		unset( $_REQUEST['user_id'] );

		$this->stub_wordpress();
		$this->plugin = $this->fresh_instance();
	}

	/**
	 * Tear down Brain Monkey and restore globals.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( false !== $this->previous_error_log ) {
			ini_set( 'error_log', $this->previous_error_log );
		}
		if ( is_file( $this->log_file ) ) {
			unlink( $this->log_file );
		}
		unset( $GLOBALS['wpdb'], $GLOBALS['pagenow'], $_REQUEST['user_id'] );

		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Resets the private static singleton and builds a new instance, so the
	 * constructor registers its hooks with this test's Brain Monkey container
	 * and no cached state leaks between tests.
	 *
	 * @return WP_Fort_Knox
	 */
	protected function fresh_instance() {
		$property = ( new ReflectionClass( WP_Fort_Knox::class ) )->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );

		return WP_Fort_Knox::instance();
	}

	/**
	 * Calls a private method of the plugin.
	 *
	 * @param string $method Method name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	protected function call_private( $method, ...$args ) {
		$reflection = new ReflectionMethod( WP_Fort_Knox::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->plugin, ...$args );
	}

	/**
	 * Builds a minimal $wpdb stand-in.
	 *
	 * @param int  $blog_id   Current site ID.
	 * @param bool $multisite Whether site IDs above 1 get their own prefix.
	 * @return object
	 */
	protected function make_wpdb( $blog_id, $multisite ) {
		return new class( $blog_id, $multisite ) {

			/**
			 * Current site ID.
			 *
			 * @var int
			 */
			public $blogid;

			/**
			 * Base table prefix.
			 *
			 * @var string
			 */
			public $base_prefix = 'wp_';

			/**
			 * Whether this is a multisite install.
			 *
			 * @var bool
			 */
			private $multisite;

			/**
			 * Constructor.
			 *
			 * @param int  $blog_id   Current site ID.
			 * @param bool $multisite Multisite flag.
			 */
			public function __construct( $blog_id, $multisite ) {
				$this->blogid    = $blog_id;
				$this->multisite = $multisite;
			}

			/**
			 * Mirrors wpdb::get_blog_prefix() for the current site.
			 *
			 * @return string
			 */
			public function get_blog_prefix() {
				if ( $this->multisite && $this->blogid > 1 ) {
					return $this->base_prefix . $this->blogid . '_';
				}

				return $this->base_prefix;
			}
		};
	}

	/**
	 * Builds a user object like the parts of WP_User the plugin reads.
	 *
	 * @param int      $id    User ID (0 for logged out).
	 * @param string   $login User login.
	 * @param string[] $roles Roles on the current site.
	 * @return object
	 */
	protected function make_user( $id, $login = '', array $roles = array() ) {
		return new class( $id, $login, $roles ) {

			/**
			 * User ID.
			 *
			 * @var int
			 */
			public $ID;

			/**
			 * User login.
			 *
			 * @var string
			 */
			public $user_login;

			/**
			 * Roles.
			 *
			 * @var string[]
			 */
			public $roles;

			/**
			 * Constructor.
			 *
			 * @param int      $id    User ID.
			 * @param string   $login User login.
			 * @param string[] $roles Roles.
			 */
			public function __construct( $id, $login, $roles ) {
				$this->ID         = $id;
				$this->user_login = $login;
				$this->roles      = $roles;
			}

			/**
			 * Mirrors WP_User::exists().
			 *
			 * @return bool
			 */
			public function exists() {
				return $this->ID > 0;
			}
		};
	}

	/**
	 * Lines written to the error log during this test (timestamps stripped).
	 *
	 * @return string[]
	 */
	protected function log_lines() {
		$contents = (string) file_get_contents( $this->log_file );
		$lines    = array();
		foreach ( preg_split( '/\R/', $contents ) as $line ) {
			if ( '' !== $line ) {
				$lines[] = preg_replace( '/^\[[^\]]+\] (?=\[WP Fort Knox\])/', '', $line );
			}
		}

		return $lines;
	}

	/**
	 * Stubs the WordPress functions the plugin calls.
	 *
	 * @return void
	 */
	private function stub_wordpress() {
		$test = $this;

		Functions\stubs(
			array(
				'is_multisite'        => false,
				'is_admin'            => false,
				'get_current_user_id' => 0,
				'wp_unslash'          => null,
				'get_user_by'         => false,
				'sanitize_text_field' => function ( $text ) {
					return trim( strip_tags( (string) $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Test stub.
				},
				'sanitize_key'        => function ( $key ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
				},
				'wp_get_current_user' => function () use ( $test ) {
					return $test->make_user( 0 );
				},
				'get_option'          => function ( $name ) use ( $test ) {
					return isset( $test->options[ $name ] ) ? $test->options[ $name ] : false;
				},
				'get_user_meta'       => function ( $user_id, $key ) use ( $test ) {
					return isset( $test->meta[ $user_id ][ $key ] ) ? $test->meta[ $user_id ][ $key ] : '';
				},
				'update_user_meta'    => function ( $user_id, $key, $value ) use ( $test ) {
					$test->writes[]               = array( 'update', $user_id, $key, $value );
					$test->meta[ $user_id ][ $key ] = $value;
					return true;
				},
				'add_user_meta'       => function ( $user_id, $key, $value ) use ( $test ) {
					$test->writes[]               = array( 'add', $user_id, $key, $value );
					$test->meta[ $user_id ][ $key ] = $value;
					return 42;
				},
				'wp_roles'            => function () {
					return new class() {

						/**
						 * Mirrors WP_Roles::is_role() for the core roles.
						 *
						 * @param string $role Role name.
						 * @return bool
						 */
						public function is_role( $role ) {
							return in_array( $role, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), true );
						}
					};
				},
			)
		);
	}
}
