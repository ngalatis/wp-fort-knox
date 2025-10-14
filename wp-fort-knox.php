<?php

/**
 * Plugin Name: WP Fort Knox
 * Description: Enhanced WordPress security plugin that disables file modifications, removes plugin management capabilities, and prevents admin user creation from wp-admin while preserving WP-CLI functionality.
 * Version: 1.0.0
 * Author: WEFIXIT
 * Network: true
 * 
 * Security Features:
 * - Defines DISALLOW_FILE_MODS constant to block file changes from wp-admin
 * - Removes plugin installation, upload, update, and deletion capabilities from all user roles
 * - Blocks creation of administrator users through wp-admin interface
 * - Prevents role elevation to administrator outside of WP-CLI
 * - Removes administrator role from user role dropdown in wp-admin
 * - Monitors and logs admin user creation attempts
 * - Preserves WP-CLI functionality for all operations
 * 
 * WP-CLI Commands for Administrative Tasks:
 * 
 * Create admin user:
 * wp user create admin admin@example.com --role=administrator --user_pass=secure_password
 * 
 * Update user role to admin:
 * wp user set-role username administrator
 * 
 * Install/update plugins:
 * wp plugin install plugin-name --activate
 * wp plugin update --all
 * 
 * Install/update themes:
 * wp theme install theme-name --activate
 * wp theme update --all
 * 
 * Update WordPress core:
 * wp core update
 * 
 * List all users with roles:
 * wp user list --fields=ID,user_login,roles
 * 
 * @package WPFortKnox
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Block file modifications from wp-admin, except for WP-CLI
add_action( 'init', function () {
    if ( ! defined( 'WP_CLI' ) && ! defined( 'DISALLOW_FILE_MODS' ) ) {
        define( 'DISALLOW_FILE_MODS', true );
    }
    foreach ( [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ] as $role_slug ) {
        $role = get_role( $role_slug );
        if ( $role ) {
            $role->remove_cap( 'install_plugins' );
            $role->remove_cap( 'upload_plugins' );   // WP 6.3+
            $role->remove_cap( 'update_plugins' );
            $role->remove_cap( 'delete_plugins' );
        }
    }
}, 1 );

// Prevent admin interface from showing Administrator role in user role dropdown, except for WP-CLI
add_filter('editable_roles', function($roles) {
    if (!defined('WP_CLI') || !WP_CLI) {
        unset($roles['administrator']);
    }
    return $roles;
});

// Block user creation with admin role
add_filter('pre_insert_user_data', function($data, $update, $user_id) {
    // Skip check if updating existing user or if in WP-CLI
    if ($update || (defined('WP_CLI') && WP_CLI)) {
        return $data;
    }
    
    // Block if trying to create admin
    if (isset($data['role']) && $data['role'] === 'administrator') {
        wp_die('Admin user creation is disabled');
    }
    
    return $data;
}, 10, 3);

// Block role changes to administrator
add_filter('wp_update_user', function($user_id, $old_user_data) {
    if (defined('WP_CLI') && WP_CLI) {
        return;
    }
    
    $user = get_userdata($user_id);
    if ($user && in_array('administrator', $user->roles) && 
        !in_array('administrator', $old_user_data->roles)) {
        // Remove admin role if it was just added outside WP-CLI
        $user->remove_role('administrator');
        $user->add_role($old_user_data->roles[0] ?? 'subscriber');
    }
}, 10, 2);

// Monitor capability changes
add_filter('user_has_cap', function($allcaps, $caps, $args, $user) {
    if (defined('WP_CLI') && WP_CLI) {
        return $allcaps;
    }
    
    // Get user's actual roles from database
    $user_obj = get_userdata($user->ID);
    if (!$user_obj) return $allcaps;
    
    // If user shouldn't be admin but has admin caps, remove them
    if (!in_array('administrator', $user_obj->roles) && 
        isset($allcaps['manage_options']) && $allcaps['manage_options']) {
        
        // Remove all admin capabilities
        foreach ($allcaps as $cap => $grant) {
            if ($grant && in_array($cap, ['manage_options', 'update_core', 
                'delete_users', 'create_users', 'edit_users'])) {
                $allcaps[$cap] = false;
            }
        }
    }
    
    return $allcaps;
}, 999, 4);

// Log all attempts to create/modify admin users
add_action('user_register', function($user_id) {
    $user = get_userdata($user_id);
    if (in_array('administrator', $user->roles)) {
        error_log(sprintf(
            '[SECURITY] Admin user created: %s (ID: %d) from IP: %s',
            $user->user_login,
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ));
    }
});