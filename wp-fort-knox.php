<?php
/**
 * Plugin Name:       WP Fort Knox
 * Plugin URI:        https://github.com/ngalatis/wp-fort-knox
 * Description:       Locks down wp-admin: no plugin/theme/core installs, updates, deletions or file editing, no automatic updates, and no new administrators. WP-CLI keeps working.
 * Version:           2.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.0
 * Author:            WEFIXIT
 * License:           WTFPL
 * License URI:       http://www.wtfpl.net/
 * Text Domain:       wp-fort-knox
 *
 * What it does (outside WP-CLI):
 * - Returns false from the `file_mod_allowed` filter, so every core check that goes
 *   through wp_is_file_mod_allowed() refuses file modifications (plugin, theme, core and
 *   language installs/updates/deletions, the file editors and the automatic updater).
 * - Strips the managed capabilities at runtime through `user_has_cap` (non-destructive:
 *   nothing is written to the database).
 * - Disables the automatic updater through `automatic_updater_disabled`. This one also
 *   applies under WP-CLI, so `wp cron event run --due-now` cannot auto-update.
 * - Hides the Administrator role from role dropdowns (except on the edit screen of a
 *   user who already is an administrator, so saving that form does not demote them).
 * - Strips the administrator role from any capabilities meta write that would newly
 *   grant it (set_role, add_role, REST, registration, add_user_to_blog, direct writes).
 * - Refuses `administrator` as the default role for new users.
 * - On multisite, revokes Super Admin as soon as it is granted.
 * - Optionally protects named accounts from being edited, deleted, removed or promoted
 *   by anyone but themselves.
 * - Logs every blocked attempt (actor, IP, URI) with error_log().
 *
 * Under WP-CLI everything above is bypassed except the automatic updater block.
 *
 * This is a must-use plugin: place it directly in /wp-content/mu-plugins/.
 *
 * Configuration (wp-config.php):
 * define( 'WP_FORT_KNOX_DISABLED', true );           // Turn everything off.
 * define( 'WP_FORT_KNOX_STRICT', true );             // Also block activate_plugins and switch_themes.
 * define( 'WP_FORT_KNOX_ALLOW_AUTO_UPDATES', true ); // Do not block the automatic updater under WP-CLI.
 * define( 'WP_FORT_KNOX_PROTECTED_USERS', '1, agency_admin' ); // IDs or logins.
 *
 * Runtime disable (plugin or theme, evaluated per request):
 * add_filter( 'wp_fort_knox_disabled', '__return_true' );
 *
 * WP-CLI Commands for Administrative Tasks:
 *
 * User Management:
 * wp user create admin admin@example.com --role=administrator --prompt=user_pass
 * wp user set-role username administrator
 * wp user list --fields=ID,user_login,roles
 * wp super-admin add username   (multisite)
 *
 * Plugin Management:
 * wp plugin install plugin-name --activate
 * wp plugin update plugin-name
 * wp plugin update --all
 * wp plugin list
 * wp plugin deactivate plugin-name
 * wp plugin delete plugin-name
 *
 * Theme Management:
 * wp theme install theme-name --activate
 * wp theme update theme-name
 * wp theme update --all
 * wp theme list
 *
 * Core and Translation Updates:
 * wp core check-update
 * wp core update
 * wp core update --version=6.4.1
 * wp language core update
 *
 * @package WPFortKnox
 * @since   1.0.0
 * @version 2.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Fort_Knox' ) ) {

	/**
	 * WP Fort Knox main class.
	 */
	class WP_Fort_Knox {

		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '2.1.0';

		/**
		 * Transient prefix for the per-user "you were blocked" notice.
		 *
		 * @var string
		 */
		const NOTICE_TRANSIENT_PREFIX = 'wp_fort_knox_notice_';

		/**
		 * Singleton instance.
		 *
		 * @var WP_Fort_Knox|null
		 */
		private static $instance = null;

		/**
		 * Cached result of the `wp_fort_knox_disabled` filter (only after `init`).
		 *
		 * @var bool|null
		 */
		private $runtime_disabled = null;

		/**
		 * True while the `wp_fort_knox_disabled` filter is being evaluated.
		 *
		 * Prevents infinite recursion when a callback of that filter performs a
		 * capability check, which runs through filter_capabilities() again.
		 *
		 * @var bool
		 */
		private $evaluating_disabled = false;

		/**
		 * Cached managed capability list (only after `init`).
		 *
		 * @var string[]|null
		 */
		private $managed_capabilities = null;

		/**
		 * Cached protected user IDs (only after `init`).
		 *
		 * @var int[]|null
		 */
		private $protected_user_ids = null;

		/**
		 * True while the protected users list is being resolved (recursion guard).
		 *
		 * @var bool
		 */
		private $resolving_protected_users = false;

		/**
		 * True while this class writes sanitized capabilities meta (recursion guard).
		 *
		 * @var bool
		 */
		private $writing_capabilities = false;

		/**
		 * Returns the singleton instance, creating it on first call.
		 *
		 * @return WP_Fort_Knox
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Registers the hooks. Runs once, at mu-plugin include time.
		 *
		 * @return void
		 */
		private function __construct() {
			// Hard disable: register nothing at all.
			if ( defined( 'WP_FORT_KNOX_DISABLED' ) && WP_FORT_KNOX_DISABLED ) {
				return;
			}

			$allow_auto_updates = defined( 'WP_FORT_KNOX_ALLOW_AUTO_UPDATES' ) && WP_FORT_KNOX_ALLOW_AUTO_UPDATES;

			// The automatic updater block is the only layer that also applies under WP-CLI.
			if ( ! $allow_auto_updates ) {
				add_filter( 'automatic_updater_disabled', array( $this, 'filter_automatic_updater_disabled' ), PHP_INT_MAX, 1 );
			}

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return;
			}

			add_filter( 'file_mod_allowed', array( $this, 'filter_file_mod_allowed' ), PHP_INT_MAX, 1 );
			add_filter( 'user_has_cap', array( $this, 'filter_capabilities' ), 999, 1 );
			add_filter( 'editable_roles', array( $this, 'filter_editable_roles' ), PHP_INT_MAX, 1 );
			add_filter( 'add_user_metadata', array( $this, 'filter_add_user_metadata' ), PHP_INT_MAX, 5 );
			add_filter( 'update_user_metadata', array( $this, 'filter_update_user_metadata' ), PHP_INT_MAX, 5 );
			add_filter( 'pre_update_option_default_role', array( $this, 'filter_default_role' ), PHP_INT_MAX, 3 );
			add_action( 'granted_super_admin', array( $this, 'block_super_admin_grant' ), 10, 1 );
			add_filter( 'map_meta_cap', array( $this, 'filter_map_meta_cap' ), PHP_INT_MAX, 4 );
			add_action( 'admin_notices', array( $this, 'render_admin_notices' ), 10, 0 );
		}

		/**
		 * Whether the `wp_fort_knox_disabled` filter lifts the protection for this request.
		 *
		 * Before `init` the filter is applied on every call, so callbacks added by plugins
		 * and the theme count as soon as they are registered. From `init` on, the result is
		 * computed once and cached for the rest of the request.
		 *
		 * @return bool
		 */
		private function is_runtime_disabled() {
			if ( null !== $this->runtime_disabled ) {
				return $this->runtime_disabled;
			}

			// A capability check inside a filter callback lands here again: stay protected.
			if ( $this->evaluating_disabled ) {
				return false;
			}

			$this->evaluating_disabled = true;
			try {
				$disabled = (bool) apply_filters( 'wp_fort_knox_disabled', false );
			} finally {
				$this->evaluating_disabled = false;
			}

			if ( did_action( 'init' ) ) {
				$this->runtime_disabled = $disabled;
			}

			return $disabled;
		}

		/**
		 * Blocks file modifications in every context core checks.
		 *
		 * Hooked to `file_mod_allowed`.
		 *
		 * @param bool $allowed Whether file modifications are allowed.
		 * @return bool
		 */
		public function filter_file_mod_allowed( $allowed ) {
			if ( $this->is_runtime_disabled() ) {
				return $allowed;
			}

			return false;
		}

		/**
		 * Disables the automatic (background) updater.
		 *
		 * Hooked to `automatic_updater_disabled`, also under WP-CLI.
		 *
		 * @param bool $disabled Whether the automatic updater is disabled.
		 * @return bool
		 */
		public function filter_automatic_updater_disabled( $disabled ) {
			if ( $this->is_runtime_disabled() ) {
				return $disabled;
			}

			return true;
		}

		/**
		 * Returns the capabilities removed at runtime.
		 *
		 * @return string[]
		 */
		private function get_managed_capabilities() {
			if ( null !== $this->managed_capabilities ) {
				return $this->managed_capabilities;
			}

			$caps = array(
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

			if ( defined( 'WP_FORT_KNOX_STRICT' ) && WP_FORT_KNOX_STRICT ) {
				$caps[] = 'activate_plugins';
				$caps[] = 'switch_themes';
			}

			/**
			 * Filters the capabilities WP Fort Knox removes at runtime.
			 *
			 * @since 2.1.0
			 *
			 * @param string[] $caps Capability names.
			 */
			$caps = apply_filters( 'wp_fort_knox_managed_capabilities', $caps );
			$caps = array_values( array_unique( array_filter( (array) $caps, 'is_string' ) ) );

			if ( did_action( 'init' ) ) {
				$this->managed_capabilities = $caps;
			}

			return $caps;
		}

		/**
		 * Removes the managed capabilities at runtime (non-destructive).
		 *
		 * Hooked to `user_has_cap`. Note: on multisite, WP_User::has_cap() returns early
		 * for super admins and never applies `user_has_cap`, so for them only the
		 * `file_mod_allowed` layer applies. That layer covers every file-related
		 * capability (install/update/delete plugins, themes, core and languages, and the
		 * file editors) through map_meta_cap().
		 *
		 * @param bool[] $allcaps All capabilities of the user, keyed by name.
		 * @return bool[]
		 */
		public function filter_capabilities( $allcaps ) {
			if ( $this->is_runtime_disabled() ) {
				return $allcaps;
			}

			foreach ( $this->get_managed_capabilities() as $cap ) {
				if ( isset( $allcaps[ $cap ] ) ) {
					$allcaps[ $cap ] = false;
				}
			}

			return $allcaps;
		}

		/**
		 * Hides the Administrator role from role dropdowns and role validation.
		 *
		 * Exception: on user-edit.php for a user who already is an administrator. Without
		 * it the dropdown would preselect "No role for this site" and saving the form would
		 * demote that administrator. No elevation is possible: the user already has the role.
		 *
		 * Hooked to `editable_roles`.
		 *
		 * @param array $roles Editable roles, keyed by role name.
		 * @return array
		 */
		public function filter_editable_roles( $roles ) {
			if ( $this->is_runtime_disabled() ) {
				return $roles;
			}

			if ( isset( $GLOBALS['pagenow'] ) && 'user-edit.php' === $GLOBALS['pagenow'] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to KEEP a role in the list, never grants anything.
				$user_id = isset( $_REQUEST['user_id'] ) ? absint( wp_unslash( $_REQUEST['user_id'] ) ) : 0;
				$target  = $user_id > 0 ? get_userdata( $user_id ) : false;

				if ( $target && in_array( 'administrator', (array) $target->roles, true ) ) {
					return $roles;
				}
			}

			unset( $roles['administrator'] );

			return $roles;
		}

		/**
		 * Sanitizes capability meta additions.
		 *
		 * Hooked to `add_user_metadata`.
		 *
		 * @param null|int|false $check      Short-circuit value (null to continue).
		 * @param int            $object_id  User ID.
		 * @param string         $meta_key   Meta key.
		 * @param mixed          $meta_value Meta value (unslashed).
		 * @param bool           $unique     Whether the key should be unique.
		 * @return null|int|false
		 */
		public function filter_add_user_metadata( $check, $object_id, $meta_key, $meta_value, $unique ) {
			if ( $this->writing_capabilities || $this->is_runtime_disabled() ) {
				return $check;
			}

			$sanitized = $this->sanitize_capabilities_value( $check, $object_id, $meta_key, $meta_value );
			if ( null === $sanitized ) {
				return $check;
			}

			$this->writing_capabilities = true;
			try {
				$result = add_user_meta( $object_id, $meta_key, wp_slash( $sanitized['value'] ), $unique );
			} finally {
				$this->writing_capabilities = false;
			}

			$this->report_admin_role_blocked( $object_id, $meta_key, $sanitized['fallback'] );

			return $result;
		}

		/**
		 * Sanitizes capability meta updates.
		 *
		 * Hooked to `update_user_metadata`.
		 *
		 * @param null|bool $check      Short-circuit value (null to continue).
		 * @param int       $object_id  User ID.
		 * @param string    $meta_key   Meta key.
		 * @param mixed     $meta_value Meta value (unslashed).
		 * @param mixed     $prev_value Previous value to match, if any.
		 * @return null|bool
		 */
		public function filter_update_user_metadata( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
			if ( $this->writing_capabilities || $this->is_runtime_disabled() ) {
				return $check;
			}

			$sanitized = $this->sanitize_capabilities_value( $check, $object_id, $meta_key, $meta_value );
			if ( null === $sanitized ) {
				return $check;
			}

			$this->writing_capabilities = true;
			try {
				$result = update_user_meta( $object_id, $meta_key, wp_slash( $sanitized['value'] ), $prev_value );
			} finally {
				$this->writing_capabilities = false;
			}

			$this->report_admin_role_blocked( $object_id, $meta_key, $sanitized['fallback'] );

			return $result;
		}

		/**
		 * Computes the sanitized capabilities value, or null when nothing must change.
		 *
		 * Something must change when the key is the current site's capabilities key, the
		 * new value grants `administrator`, and the stored value does not already grant it.
		 *
		 * @param mixed  $check      Short-circuit value from earlier filters.
		 * @param int    $object_id  User ID.
		 * @param string $meta_key   Meta key.
		 * @param mixed  $meta_value Meta value.
		 * @return array|null Array with 'value' (sanitized caps) and 'fallback' (comma-separated roles
		 *                    put back or assigned, or '' when another role remained), or null.
		 */
		private function sanitize_capabilities_value( $check, $object_id, $meta_key, $meta_value ) {
			// Another filter already short-circuited the write.
			if ( null !== $check ) {
				return null;
			}

			if ( ! is_array( $meta_value ) || empty( $meta_value['administrator'] ) ) {
				return null;
			}

			if ( ! $this->is_capabilities_key( $meta_key ) ) {
				return null;
			}

			/*
			 * Multisite: a new site's owner is made its administrator while the site is
			 * initialized (Network Admin > Add New Site, or a blog signup). That grants
			 * nothing on any existing site, and blocking it would leave the new site
			 * without an administrator.
			 */
			if ( function_exists( 'doing_action' ) && doing_action( 'wp_initialize_site' ) ) {
				return null;
			}

			// get_user_meta() reads through get_metadata(), not the add/update filters.
			$existing = get_user_meta( $object_id, $meta_key, true );
			if ( is_array( $existing ) && ! empty( $existing['administrator'] ) ) {
				return null;
			}

			unset( $meta_value['administrator'] );

			$wp_roles = wp_roles();
			$assigned = array();
			if ( ! $this->grants_role( $meta_value, $wp_roles ) ) {
				// WP_User::set_role() drops the previous roles before adding the new one: put them back.
				if ( is_array( $existing ) ) {
					foreach ( $existing as $name => $granted ) {
						if ( $granted && 'administrator' !== $name && $wp_roles->is_role( $name ) ) {
							$meta_value[ $name ] = true;
							$assigned[]          = $name;
						}
					}
				}

				// No previous role either: fall back to the default role, never administrator.
				if ( ! $assigned ) {
					$fallback = (string) get_option( 'default_role' );
					if ( 'administrator' === $fallback || ! $wp_roles->is_role( $fallback ) ) {
						$fallback = 'subscriber';
					}
					$meta_value[ $fallback ] = true;
					$assigned[]              = $fallback;
				}
			}

			return array(
				'value'    => $meta_value,
				'fallback' => implode( ',', $assigned ),
			);
		}

		/**
		 * Whether a capabilities array grants at least one existing role.
		 *
		 * @param array    $caps     Capabilities meta value, keyed by role or capability name.
		 * @param WP_Roles $wp_roles Role registry.
		 * @return bool
		 */
		private function grants_role( $caps, $wp_roles ) {
			foreach ( $caps as $name => $granted ) {
				if ( $granted && $wp_roles->is_role( $name ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Whether a user meta key holds the roles of a site.
		 *
		 * Matches the current site's key, which follows switch_to_blog(). On multisite it
		 * also matches every other site's key ({base_prefix}{site_id}_capabilities), so a
		 * WP_User loaded for another site cannot be granted administrator there either.
		 *
		 * @param string $meta_key Meta key.
		 * @return bool
		 */
		private function is_capabilities_key( $meta_key ) {
			global $wpdb;

			if ( ! is_string( $meta_key ) ) {
				return false;
			}

			if ( $wpdb->get_blog_prefix() . 'capabilities' === $meta_key ) {
				return true;
			}

			if ( ! is_multisite() || ! isset( $wpdb->base_prefix ) ) {
				return false;
			}

			return 1 === preg_match( '/^' . preg_quote( $wpdb->base_prefix, '/' ) . '(?:[0-9]+_)?capabilities$/', $meta_key );
		}

		/**
		 * Logs a blocked administrator grant and flags the actor notice.
		 *
		 * @param int    $user_id  Target user ID.
		 * @param string $meta_key Capabilities meta key.
		 * @param string $fallback Roles put back or assigned, or '' when another role remained.
		 * @return void
		 */
		private function report_admin_role_blocked( $user_id, $meta_key, $fallback ) {
			$context = array(
				'user_id'  => (int) $user_id,
				'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Log context, not a query.
			);
			if ( '' !== $fallback ) {
				$context['assigned_role'] = $fallback;
			}

			$this->log( 'admin_role_blocked', $context );
			$this->flag_actor_notice( 'admin_role_blocked' );
		}

		/**
		 * Refuses `administrator` as the default role for new users.
		 *
		 * Hooked to `pre_update_option_default_role`.
		 *
		 * @param mixed  $value     New option value.
		 * @param mixed  $old_value Old option value.
		 * @param string $option    Option name.
		 * @return mixed
		 */
		public function filter_default_role( $value, $old_value, $option ) {
			if ( $this->is_runtime_disabled() ) {
				return $value;
			}

			if ( 'administrator' !== $value ) {
				return $value;
			}

			$restored = ( 'administrator' === $old_value ) ? 'subscriber' : $old_value;

			$this->log(
				'default_role_blocked',
				array(
					'option'   => $option,
					'restored' => is_scalar( $restored ) ? (string) $restored : '',
				)
			);
			$this->flag_actor_notice( 'default_role_blocked' );

			return $restored;
		}

		/**
		 * Revokes Super Admin right after it is granted (multisite).
		 *
		 * Hooked to `granted_super_admin`. Core does not fire that action when the
		 * `$super_admins` global is defined, which is the recommended hard lock.
		 *
		 * @param int $user_id ID of the user who was granted Super Admin.
		 * @return void
		 */
		public function block_super_admin_grant( $user_id ) {
			if ( $this->is_runtime_disabled() ) {
				return;
			}

			$revoked = function_exists( 'revoke_super_admin' ) ? revoke_super_admin( $user_id ) : false;

			$this->log(
				'super_admin_blocked',
				array(
					'user_id' => (int) $user_id,
					'revoked' => (bool) $revoked,
				)
			);
			$this->flag_actor_notice( 'super_admin_blocked' );
		}

		/**
		 * Returns the protected user IDs (constant plus filter, logins resolved to IDs).
		 *
		 * @return int[]
		 */
		private function get_protected_user_ids() {
			if ( null !== $this->protected_user_ids ) {
				return $this->protected_user_ids;
			}

			$entries = array();
			if ( defined( 'WP_FORT_KNOX_PROTECTED_USERS' ) && is_scalar( WP_FORT_KNOX_PROTECTED_USERS ) ) {
				$entries = explode( ',', (string) WP_FORT_KNOX_PROTECTED_USERS );
			}

			/**
			 * Filters the users protected from edits, deletion, removal and promotion by others.
			 *
			 * @since 2.1.0
			 *
			 * @param array $entries User IDs (int) or logins (string).
			 */
			$entries = (array) apply_filters( 'wp_fort_knox_protected_users', $entries );

			$ids = array();
			foreach ( $entries as $entry ) {
				if ( is_int( $entry ) ) {
					$id = $entry;
				} elseif ( is_string( $entry ) ) {
					$entry = trim( $entry );
					if ( '' === $entry ) {
						continue;
					}
					if ( ctype_digit( $entry ) ) {
						$id = (int) $entry;
					} else {
						$user = function_exists( 'get_user_by' ) ? get_user_by( 'login', $entry ) : false;
						$id   = $user ? (int) $user->ID : 0;
					}
				} else {
					continue;
				}

				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}

			$ids = array_values( array_unique( $ids ) );

			if ( did_action( 'init' ) ) {
				$this->protected_user_ids = $ids;
			}

			return $ids;
		}

		/**
		 * Denies edit/delete/remove/promote on protected users to everyone but themselves.
		 *
		 * Hooked to `map_meta_cap`. `do_not_allow` also binds multisite super admins.
		 *
		 * @param string[] $caps    Primitive capabilities required.
		 * @param string   $cap     Capability being checked.
		 * @param int      $user_id ID of the user being checked.
		 * @param array    $args    Additional arguments (target user ID first).
		 * @return string[]
		 */
		public function filter_map_meta_cap( $caps, $cap, $user_id, $args ) {
			if ( ! in_array( $cap, array( 'edit_user', 'delete_user', 'remove_user', 'promote_user' ), true ) ) {
				return $caps;
			}

			if ( $this->is_runtime_disabled() || $this->resolving_protected_users ) {
				return $caps;
			}

			if ( ! isset( $args[0] ) || ! is_numeric( $args[0] ) ) {
				return $caps;
			}

			$target_id = (int) $args[0];
			if ( (int) $user_id === $target_id ) {
				return $caps;
			}

			$this->resolving_protected_users = true;
			try {
				$protected = $this->get_protected_user_ids();
			} finally {
				$this->resolving_protected_users = false;
			}

			if ( in_array( $target_id, $protected, true ) ) {
				$caps[] = 'do_not_allow';
			}

			return $caps;
		}

		/**
		 * Renders the actor feedback notice and the per-screen information notices.
		 *
		 * Hooked to `admin_notices`.
		 *
		 * @return void
		 */
		public function render_admin_notices() {
			if ( $this->is_runtime_disabled() ) {
				return;
			}

			$this->render_blocked_notice();

			if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'get_current_screen' ) ) {
				return;
			}

			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			if ( in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
				$message = __( 'Installing, updating, deleting and editing plugins and themes, and core updates, are disabled in wp-admin. Use WP-CLI for these operations.', 'wp-fort-knox' );
				if ( defined( 'WP_FORT_KNOX_STRICT' ) && WP_FORT_KNOX_STRICT ) {
					$message .= ' ' . __( 'Strict mode is on: activating plugins and switching themes are disabled too.', 'wp-fort-knox' );
				}
				$this->print_notice( 'notice-info', $message );
			}

			if ( in_array( $screen->id, array( 'user', 'user-network' ), true ) && 'add' === $screen->action ) {
				$this->print_notice(
					'notice-warning',
					__( 'The Administrator role cannot be assigned from wp-admin. Use WP-CLI to create administrators or change roles.', 'wp-fort-knox' )
				);
			}
		}

		/**
		 * Prints the one-time "your action was blocked" notice for the current user.
		 *
		 * @return void
		 */
		private function render_blocked_notice() {
			$user_id = get_current_user_id();
			if ( $user_id < 1 ) {
				return;
			}

			$key   = self::NOTICE_TRANSIENT_PREFIX . $user_id;
			$event = get_transient( $key );
			if ( false === $event ) {
				return;
			}
			delete_transient( $key );

			switch ( $event ) {
				case 'admin_role_blocked':
					$message = __( 'The Administrator role was not granted. It was removed from the change you just made. Use WP-CLI to grant it.', 'wp-fort-knox' );
					break;
				case 'default_role_blocked':
					$message = __( 'The default role for new users cannot be Administrator. The previous default role was kept.', 'wp-fort-knox' );
					break;
				case 'super_admin_blocked':
					$message = __( 'Super Admin privileges were not granted. Use WP-CLI to grant them.', 'wp-fort-knox' );
					break;
				default:
					return;
			}

			$this->print_notice( 'notice-error', $message );
		}

		/**
		 * Prints an admin notice with the plugin name and the wp-config.php hint.
		 *
		 * @param string $type    Notice class: notice-info, notice-warning or notice-error.
		 * @param string $message Translated plain-text message.
		 * @return void
		 */
		private function print_notice( $type, $message ) {
			printf(
				'<div class="notice %1$s"><p><strong>%2$s</strong> %3$s %4$s</p></div>',
				esc_attr( $type ),
				esc_html__( 'WP Fort Knox:', 'wp-fort-knox' ),
				esc_html( $message ),
				wp_kses(
					sprintf(
						/* translators: %s: constant name wrapped in a code tag. */
						esc_html__( 'The site owner or developer can lift this restriction by defining %s in wp-config.php.', 'wp-fort-knox' ),
						'<code>WP_FORT_KNOX_DISABLED</code>'
					),
					array( 'code' => array() )
				)
			);
		}

		/**
		 * Stores a one-time notice for the acting user (admin requests only).
		 *
		 * @param string $event Event name.
		 * @return void
		 */
		private function flag_actor_notice( $event ) {
			if ( ! is_admin() || ! function_exists( 'get_current_user_id' ) ) {
				return;
			}

			$user_id = get_current_user_id();
			if ( $user_id < 1 ) {
				return;
			}

			set_transient( self::NOTICE_TRANSIENT_PREFIX . $user_id, $event, 60 );
		}

		/**
		 * Logs a blocked event and fires the `wp_fort_knox_blocked` action.
		 *
		 * Line format:
		 * [WP Fort Knox] <event> | actor=<id>:<login|none> | ip=<ip|cli> | uri=<uri|cli> | key=value ...
		 *
		 * @param string $event   Event name.
		 * @param array  $context Event details (scalars). Never pass secrets.
		 * @return void
		 */
		private function log( $event, array $context = array() ) {
			/**
			 * Filters whether WP Fort Knox writes the event to the PHP error log.
			 *
			 * @since 2.1.0
			 *
			 * @param bool   $log     Whether to log. Default true.
			 * @param string $event   Event name.
			 * @param array  $context Event details.
			 */
			if ( apply_filters( 'wp_fort_knox_log', true, $event, $context ) ) {
				$is_cli = defined( 'WP_CLI' ) && WP_CLI;

				$actor = '0:none';
				if ( function_exists( 'wp_get_current_user' ) ) {
					$current = wp_get_current_user();
					if ( $current && $current->exists() ) {
						$actor = absint( $current->ID ) . ':' . sanitize_text_field( $current->user_login );
					}
				}

				$ip  = 'cli';
				$uri = 'cli';
				if ( ! $is_cli ) {
					$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
					$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'unknown';
				}

				$pairs = array();
				foreach ( $context as $key => $value ) {
					$key = sanitize_key( (string) $key );
					if ( '' === $key || false !== strpos( $key, 'pass' ) ) {
						continue;
					}

					if ( is_bool( $value ) ) {
						$value = $value ? 'true' : 'false';
					} elseif ( is_int( $value ) ) {
						$value = (string) absint( $value );
					} elseif ( is_scalar( $value ) ) {
						$value = sanitize_text_field( (string) $value );
					} else {
						continue;
					}

					$pairs[] = $key . '=' . $value;
				}

				$line = sprintf(
					'[WP Fort Knox] %s | actor=%s | ip=%s | uri=%s',
					sanitize_key( $event ),
					$actor,
					$ip,
					$uri
				);
				if ( $pairs ) {
					$line .= ' | ' . implode( ' ', $pairs );
				}

				error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Security audit log.
			}

			/**
			 * Fires after WP Fort Knox blocked something, for audit log plugins.
			 *
			 * @since 2.1.0
			 *
			 * @param string $event   Event name: admin_role_blocked, default_role_blocked or super_admin_blocked.
			 * @param array  $context Event details.
			 */
			do_action( 'wp_fort_knox_blocked', $event, $context );
		}
	}
}

WP_Fort_Knox::instance();
