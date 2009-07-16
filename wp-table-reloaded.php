<?php
/*
Plugin Name: WP-Table Reloaded
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: This plugin allows you to create and easily manage tables in the admin-area of WordPress. A comfortable backend allows an easy manipulation of table data. You can then include the tables into your posts, on your pages or in text widgets by using a shortcode or a template tag function. Tables can be imported and exported from/to CSV, XML and HTML.
Version: 1.4-beta1
Author: Tobias B&auml;thge
Author URI: http://tobias.baethge.com/
Donate URI: http://tobias.baethge.com/donate/
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
if ( !defined( 'WP_TABLE_RELOADED_ABSPATH' ) )
    define( 'WP_TABLE_RELOADED_ABSPATH', WP_PLUGIN_DIR . '/' . basename( dirname ( __FILE__ ) ) . '/' );
if ( !defined( 'WP_TABLE_RELOADED_URL' ) )
    define( 'WP_TABLE_RELOADED_URL', WP_PLUGIN_URL . '/' . basename( dirname ( __FILE__ ) ) . '/' );
if ( !defined( 'WP_TABLE_RELOADED_BASENAME' ) )
    define( 'WP_TABLE_RELOADED_BASENAME', plugin_basename( __FILE__ ) );

// decide whether admin or frontend
if ( is_admin() ) {
    // we are in admin mode, load admin class
    include_once( WP_TABLE_RELOADED_ABSPATH . 'wp-table-reloaded-admin.php' );
    $WP_Table_Reloaded_Admin = new WP_Table_Reloaded_Admin();

    // actions to in admin, outside class
    register_activation_hook( __FILE__, array( &$WP_Table_Reloaded_Admin, 'plugin_activation_hook' ) );
    register_deactivation_hook( __FILE__, array( &$WP_Table_Reloaded_Admin, 'plugin_deactivation_hook' ) );
} else {
    // we are in frontend mode, load frontend class
    include_once ( WP_TABLE_RELOADED_ABSPATH . 'wp-table-reloaded-frontend.php' );
    $WP_Table_Reloaded_Frontend = new WP_Table_Reloaded_Frontend();

    // add template tag function for "table" shortcode to be used anywhere in the template
    function wp_table_reloaded_print_table( $table_query ) {
        global $WP_Table_Reloaded_Frontend;
        parse_str( $table_query, $atts );
        echo $WP_Table_Reloaded_Frontend->handle_content_shortcode_table( $atts );
    }
    // add template tag function for "table-info" shortcode to be used anywhere in the template
    function wp_table_reloaded_print_table_info( $table_query ) {
        global $WP_Table_Reloaded_Frontend;
        parse_str( $table_query, $atts );
        echo $WP_Table_Reloaded_Frontend->handle_content_shortcode_table_info( $atts );
    }

}

?>