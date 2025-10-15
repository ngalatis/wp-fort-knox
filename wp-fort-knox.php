<?php

/**
 * Plugin Name: WP Fort Knox
 * Description: Enhanced WordPress security - disables file modifications and plugin management from wp-admin
 * Version: 2.0.0
 * Author: WEFIXIT
 *
 * This is a Must-Use plugin - place directly in /wp-content/mu-plugins/
 * 
 * To disable temporarily: define('WP_FORT_KNOX_DISABLED', true); in wp-config.php
 * To restore capabilities: wp cap add administrator install_plugins update_plugins delete_plugins upload_plugins --allow-root
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_Fort_Knox {
    
    private $managed_capabilities = [
        'install_plugins',
        'upload_plugins',
        'update_plugins', 
        'delete_plugins'
    ];
    
    public function __construct() {
        // Check if disabled
        if ( $this->is_disabled() ) {
            return;
        }
        
        // Apply security measures
        $this->apply_security();
    }
    
    /**
     * Check if plugin should be disabled
     */
    private function is_disabled() {
        // Always allow WP-CLI
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return true;
        }
        
        // Check for disable constant
        if ( defined( 'WP_FORT_KNOX_DISABLED' ) && WP_FORT_KNOX_DISABLED ) {
            return true;
        }
        
        // Allow filter for programmatic control
        if ( apply_filters( 'wp_fort_knox_disabled', false ) ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Apply all security measures
     */
    private function apply_security() {
        // Block file modifications
        if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
            define( 'DISALLOW_FILE_MODS', true );
        }
        
        // Remove plugin capabilities at runtime (non-destructive)
        add_filter( 'user_has_cap', [ $this, 'filter_capabilities' ], 999, 4 );
        
        // Hide administrator role from user creation/edit screens
        add_filter( 'editable_roles', [ $this, 'hide_administrator_role' ] );
        
        // Block admin user creation via wp-admin
        add_filter( 'pre_insert_user_data', [ $this, 'block_admin_creation' ], 10, 3 );
        
        // Prevent role elevation to administrator
        add_action( 'set_user_role', [ $this, 'prevent_admin_elevation' ], 10, 3 );
        
        // Show notice on plugins page
        add_action( 'admin_notices', [ $this, 'show_admin_notice' ] );
    }
    
    /**
     * Filter user capabilities at runtime
     */
    public function filter_capabilities( $allcaps, $caps, $args, $user ) {
        // Only filter for non-CLI requests
        foreach ( $this->managed_capabilities as $cap ) {
            if ( isset( $allcaps[ $cap ] ) ) {
                $allcaps[ $cap ] = false;
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Hide administrator role from dropdowns
     */
    public function hide_administrator_role( $roles ) {
        unset( $roles['administrator'] );
        return $roles;
    }
    
    /**
     * Block admin user creation
     */
    public function block_admin_creation( $data, $update, $user_id ) {
        // Allow updates to existing users
        if ( $update ) {
            return $data;
        }
        
        // Block new admin creation
        if ( isset( $data['role'] ) && $data['role'] === 'administrator' ) {
            wp_die( 
                'Administrator account creation is disabled. Use WP-CLI: wp user create username email@example.com --role=administrator',
                'Security Policy',
                [ 'back_link' => true ]
            );
        }
        
        return $data;
    }
    
    /**
     * Prevent elevation to administrator role
     */
    public function prevent_admin_elevation( $user_id, $role, $old_roles ) {
        // If trying to add administrator role
        if ( $role === 'administrator' && ! in_array( 'administrator', $old_roles ) ) {
            // Revert the change
            $user = get_userdata( $user_id );
            if ( $user ) {
                $user->remove_role( 'administrator' );
                $user->add_role( $old_roles[0] ?? 'subscriber' );
                
                // Log the attempt
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf(
                        '[WP Fort Knox] Blocked administrator elevation for user %s (ID: %d)',
                        $user->user_login,
                        $user_id
                    ) );
                }
            }
        }
    }
    
    /**
     * Show admin notice on relevant pages
     */
    public function show_admin_notice() {
        // Only show to users who would normally have capability
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $screen = get_current_screen();
        
        // Show on plugins page
        if ( $screen && $screen->id === 'plugins' ) {
            ?>
            <div class="notice notice-info">
                <p><strong>WP Fort Knox:</strong> Plugin management is disabled in wp-admin. Use WP-CLI for all plugin operations. To disable temporarily, add <code>define('WP_FORT_KNOX_DISABLED', true);</code> to wp-config.php</p>
            </div>
            <?php
        }
        
        // Show on users page when trying to add new
        if ( $screen && $screen->id === 'user' && $screen->action === 'add' ) {
            ?>
            <div class="notice notice-warning">
                <p><strong>WP Fort Knox:</strong> Administrator role creation is disabled. Use WP-CLI: <code>wp user create username email@example.com --role=administrator</code></p>
            </div>
            <?php
        }
    }
}

// Initialize - no activation hooks needed for mu-plugins
new WP_Fort_Knox();