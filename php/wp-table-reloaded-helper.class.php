<?php
/*
File Name: WP-Table Reloaded - Helper Class (see main file wp-table-reloaded.php)
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: This plugin allows you to create and manage tables in the admin-area of WordPress. You can then show them in your posts or on your pages by using a shortcode. The plugin is greatly influenced by the plugin "wp-Table" by Alex Rabe, but was completely rewritten and uses the state-of-the-art WordPress techniques which makes it faster and lighter than the original plugin.
Version: 1.5
Author: Tobias B&auml;thge
Author URI: http://tobias.baethge.com/
*/

// should be included by WP_Table_Reloaded_Admin!
class WP_Table_Reloaded_Helper {

    // ###################################################################################################################
    var $helper_class_version = '1.5';

    // ###################################################################################################################
    // constructor class
    function WP_Table_Reloaded_Helper() {
        // nothing to init here
    }

    // ###################################################################################################################
    function print_header_message( $text ) {
        echo "<div id='message' class='updated fade'><p style='line-height:1.2;'><strong>{$text}</strong></p></div>";
    }

    // ###################################################################################################################
    function print_page_header( $text = 'WP-Table Reloaded' ) {
        echo <<<TEXT
<div class="wrap">
<h2>{$text}</h2>
<div id="poststuff">
TEXT;
    }

    // ###################################################################################################################
    function print_page_footer() {
        echo "</div></div>";
    }

    // ###################################################################################################################
    // for each $action an approriate message will be shown
    function get_contextual_help_string( $action ) {
        // certain $actions need different help string, because different screen is actually shown

        // problem: delete -> edit/list     'table' == $_GET['item'] -> list
        if ( 'delete' == $action && 'table' == $_GET['item'] )
            $action = 'list';
        // problem: edit+save -> list       'table' == $_GET['item'] -> list
        if ( 'edit' == $action && isset( $_POST['submit']['save_back'] ) )
            $action = 'list';
        // problem: import -> edit          $_REQUEST['import_format'] -> edit
        if ( 'import' == $action && isset( $_REQUEST['import_format'] ) )
            $action = 'edit';
        // problem: add -> edit             $_POST['table'] ) -> edit
        if ( 'add' == $action && isset( $_POST['table'] ) )
            $action = 'edit';
        // a few problems remain: import fails will show edit

        switch( $action ) {
            case 'copy':
            case 'bulk_edit':
            case 'hide_donate_nag':
            case 'list':
                $help = sprintf( __( 'This is the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'List Tables', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                break;
            case 'edit':
                $help = sprintf( __( 'This is the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Edit Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                break;
            case 'add':
                $help = sprintf( __( 'This is the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Add new Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                break;
            case 'import':
                $help = sprintf( __( 'This is the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Import a Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                break;
            case 'export':
                $help = sprintf( __( 'This is the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Export a Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                break;
            case 'options':
                $help = sprintf( __( 'This is the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                break;
            case 'info':
                $help = sprintf( __( 'This is the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'About the plugin', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                break;
            case 'uninstall':
                $help = __( 'Plugin deactivated successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' ' . __( 'All tables, data and options were deleted. You may now remove the plugin\'s subfolder from your WordPress plugin folder.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            // case 'ajax_list': // not needed, no contextual_help here
            // case 'ajax_preview': // not needed, no contextual_help here
            default:
                $help = '';
                break;
        }

        $help .= '<br/><br/>' . sprintf( __( 'More information about WP-Table Reloaded can be found on the <a href="%s">plugin\'s website</a> or on its page in the <a href="%s">WordPress Plugin Directory</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/website/', 'http://wordpress.org/extend/plugins/wp-table-reloaded/' );
        $help .= ' ' . sprintf( __( 'For technical information, see the <a href="%s">documentation</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/documentation/' );
        $help .= '<br/>' . sprintf( __( '<a href="%s">Support</a> is provided through the <a href="%s">WordPress Support Forums</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/support/', 'http://www.wordpress.org/support/' );
        $help .= ' ' . sprintf( __( 'Before asking for support, please carefully read the <a href="%s">Frequently Asked Questions</a> where you will find answered to the most common questions, and search through the forums.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/faq/' );
        $help .= '<br/>' . sprintf( __( 'If you like the plugin, <a href="%s"><strong>a donation</strong></a> is recommended.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/donate/' );

        return $help;
    }
    
    // ###################################################################################################################
    function safe_output( $string ) {
        $string = stripslashes( $string ); // because $string is add_slashed before WP stores it in DB
        return wp_specialchars( $string, ENT_QUOTES, false, true );
    }
    
    // ###################################################################################################################
    // create new two-dimensional array with $num_rows rows and $num_cols columns, each cell filled with $default_cell_content
    function create_empty_table( $num_rows = 1, $num_cols = 1, $default_cell_content = '' ) {
        return array_fill( 0, $num_rows, array_fill( 0, $num_cols, $default_cell_content ) );
    }

    // ###################################################################################################################
    // need to clean this up and find out what's really necessary
    function prepare_download( $filename, $filesize, $filetype ) {
        @ob_end_clean();
        //header( 'Content-Description: File Transfer' );
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"');
        header( 'Content-Length: ' . $filesize );
        //header( 'Content-type: ' . $filetype. '; charset=' . get_option('blog_charset') );
    }

    // ###################################################################################################################
    function format_datetime( $last_modified ) {
        return mysql2date( get_option('date_format'), $last_modified ) . ' ' . mysql2date( get_option('time_format'), $last_modified );
    }

    // ###################################################################################################################
    function get_last_editor( $last_editor_id ) {
        $user = get_userdata( $last_editor_id );
        $nickname = ( isset( $user->nickname ) ) ? $user->nickname : '';
        return $nickname;
    }

    // ###################################################################################################################
    // add admin footer text
    function add_admin_footer_text( $content ) {
        $content .= ' | ' . __( 'Thank you for using <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/">WP-Table Reloaded</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' ' . sprintf( __( 'Support the plugin with your <a href="%s">donation</a>!', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/donate-message/' );
        return $content;
    }

    // ###################################################################################################################
    // state of the postbox, can be filtered
    function postbox_closed( $postbox_name, $postbox_closed ) {
        $postbox_closed = apply_filters( 'wp_table_reloaded_admin_postbox_closed', $postbox_closed, $postbox_name );
        $output = ( $postbox_closed ) ? ' closed' : '';
        return $output;
    }

    // ###################################################################################################################
    // retrieve the update message from the development server to notify user of what changes there are in this update, in his language
    function retrieve_plugin_update_message( $current_version, $new_version ) {
        $message = '';
        $wp_locale = get_locale();
        $update_message = wp_remote_fopen( "http://dev.tobias.baethge.com/plugin/update/wp-table-reloaded/{$current_version}/{$new_version}/{$wp_locale}/" );
        if ( false !== $update_message ) {
            if ( 1 == preg_match( '/<info>(.*?)<\/info>/is', $update_message, $matches ) )
                $message = $matches[1];
        }
        return $message;
    }

    // ###################################################################################################################
    // this function is equivalent to WP's plugins_url, but we need it for WP 2.7, as that has a different output
    function plugins_url( $path, $plugin ) {
        if ( version_compare( $GLOBALS['wp_version'], '2.8', '>=') ) {
            return plugins_url( $path, $plugin );
        } else {
            $folder = dirname( plugin_basename( $plugin ) );
            if ( '.' != $folder )
                $folder = '/' . ltrim( $folder, '/' );
            if ( '' != $path )
                $path = '/' . ltrim( $path, '/' );
            $path = $folder . $path;
            return plugins_url( $path );
        }
    }
    
} // class WP_Table_Reloaded_Helper

?>