<?php
/*
Plugin Name: WP-Table Reloaded
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: This plugin allows you to create and manage tables in the admin-area of WordPress. You can then show them in your posts, on your pages or in text widgets by using a shortcode. The plugin is a completely rewritten and extended version of Alex Rabe's "wp-Table" and uses the state-of-the-art WordPress techniques which makes it faster and lighter than the original plugin.
Version: 1.1
Author: Tobias B&auml;thge
Author URI: http://tobias.baethge.com/
*/

/*  Copyright 2009 Tobias B&auml;thge (email: wordpress@tobias.baethge.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; version 2 of the License (GPL v2) only.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// folder definitions as constants
if ( !defined( 'WP_CONTENT_DIR' ) )
    define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
if ( !defined( 'WP_CONTENT_URL' ) )
    define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content' );
if ( !defined( 'WP_PLUGIN_URL' ) )
	define( 'WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins' );
if ( !defined( 'WP_PLUGIN_DIR' ) )
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
if ( !defined( 'WP_TABLE_RELOADED_ABSPATH' ) )
    define( 'WP_TABLE_RELOADED_ABSPATH', WP_PLUGIN_DIR . '/' . basename( dirname ( __FILE__ ) ) . '/' );
if ( !defined( 'WP_TABLE_RELOADED_URL' ) )
    define( 'WP_TABLE_RELOADED_URL', WP_PLUGIN_URL . '/' . basename( dirname ( __FILE__ ) ) . '/' );
if ( !defined( 'WP_TABLE_RELOADED_BASENAME' ) )
    define( 'WP_TABLE_RELOADED_BASENAME', plugin_basename( __FILE__ ) );


// decide whether admin or frontend
if ( is_admin() ) {
    // we are in admin mode
    if ( !class_exists( 'WP_Table_Reloaded_Admin' ) ) {
        include_once ( WP_TABLE_RELOADED_ABSPATH . 'wp-table-reloaded-admin.php' );
        if ( class_exists( 'WP_Table_Reloaded_Admin' ) )  {
            $WP_Table_Reloaded_Admin = new WP_Table_Reloaded_Admin();
            register_activation_hook( __FILE__, array( &$WP_Table_Reloaded_Admin, 'plugin_activation_hook' ));
            register_deactivation_hook( __FILE__, array( &$WP_Table_Reloaded_Admin, 'plugin_deactivation_hook' ));
        }
    }
} else {
    // we are in frontend mode
    if ( !class_exists( 'WP_Table_Reloaded_Frontend' ) ) {
        include_once ( WP_TABLE_RELOADED_ABSPATH . 'wp-table-reloaded-frontend.php' );
        if ( class_exists( 'WP_Table_Reloaded_Frontend' ) )
            $WP_Table_Reloaded_Frontend = new WP_Table_Reloaded_Frontend();
    }
}

?>