<?php
/*
File Name: WP-Table Reloaded - Admin Class (see main file wp-table-reloaded.php)
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: Description: This plugin allows you to create and easily manage tables in the admin-area of WordPress. A comfortable backend allows an easy manipulation of table data. You can then include the tables into your posts, on your pages or in text widgets by using a shortcode or a template tag function. Tables can be imported and exported from/to CSV, XML and HTML.
Version: 1.2
Author: Tobias B&auml;thge
Author URI: http://tobias.baethge.com/
*/

define( 'WP_TABLE_RELOADED_TEXTDOMAIN', 'wp-table-reloaded' );

class WP_Table_Reloaded_Admin {

    // ###################################################################################################################
    var $plugin_version = '1.2';
    // nonce for security of links/forms, try to prevent "CSRF"
    var $nonce_base = 'wp-table-reloaded-nonce';
    // names for the options which are stored in the WP database
    var $optionname = array(
        'tables' => 'wp_table_reloaded_tables',
        'options' => 'wp_table_reloaded_options',
        'table' => 'wp_table_reloaded_data'
    );
    // allowed actions in this class
    var $allowed_actions = array( 'list', 'add', 'edit', 'bulk_edit', 'copy', 'delete', 'insert', 'import', 'export', 'options', 'uninstall', 'info' ); // 'ajax_list', but handled separatly
    
    // init vars
    var $tables = array();
    var $options = array();

    // default values, could be different in future plugin version
    var $default_options = array(
        'installed_version' => '0',
        'uninstall_upon_deactivation' => false,
        'enable_tablesorter' => true,
        'use_custom_css' => true,
        'custom_css' => '.wp-table-reloaded {width:100%;}',
        'last_id' => 0
    );
    var $default_tables = array();
    var $default_table = array(
        'id' => 0,
        'data' => array( 0 => array( 0 => '' ) ),
        'name' => 'default',
        'description' => 'default',
        'options' => array(
            'alternating_row_colors' => true,
            'first_row_th' => true,
            'print_name' => false,
            'print_description' => false,
            'use_tablesorter' => true
        )
    );
    
    // class instances
    var $export_instance;
    var $import_instance;
    
    // temp variables
    var $hook = '';

    // ###################################################################################################################
    // add admin-page to sidebar navigation, function called by PHP when class is constructed
    function WP_Table_Reloaded_Admin() {
        // init plugin (means: load plugin options and existing tables)
        $this->init_plugin();

        add_action( 'admin_menu', array( &$this, 'add_manage_page' ) );

        // add JS to add button to editor on these pages
        $pages_with_editor_button = array( 'post.php', 'post-new.php', 'page.php', 'page-new.php' );
        foreach ( $pages_with_editor_button as $page )
            add_action( 'load-' . $page, array( &$this, 'add_editor_button' ) );

        // have to check for possible export file download request this early,
        // because otherwise http-headers will be sent by WP before we can send download headers
        if ( isset( $_POST['wp_table_reloaded_download_export_file'] ) ) {
            add_action( 'init', array( &$this, 'do_action_export' ) );
        }

        // have to check for possible call by editor button to show list of tables
        if ( isset( $_GET['action'] ) && 'ajax_list' == $_GET['action'] ) {
            add_action( 'init', array( &$this, 'do_action_ajax_list' ) );
        }
    }

    // ###################################################################################################################
    // add page, and what happens when page is loaded or shown
    function add_manage_page() {
        $min_needed_capability = 'publish_posts'; // user needs at least this capability to show WP-Table Reloaded config page
        $this->hook = add_management_page( 'WP-Table Reloaded', 'WP-Table Reloaded', $min_needed_capability, 'wp_table_reloaded_manage_page', array( &$this, 'show_manage_page' ) );
        add_action( 'load-' . $this->hook, array( &$this, 'load_manage_page' ) );
    }
    
    // ###################################################################################################################
    // only load the scripts, stylesheets and language by hook, if this admin page will be shown
    // all of this will be done before the page is shown by show_manage_page()
    function load_manage_page() {
        // load js and css for admin
        //$this->add_manage_page_js();
        add_action( 'admin_footer', array( &$this, 'add_manage_page_js' ) ); // can be put in footer, jQuery will be loaded anyway
        $this->add_manage_page_css();

        // init language support
        $this->init_language_support();
        
        if ( true == function_exists( 'add_contextual_help' ) ) // then WP version is >= 2.7
            add_contextual_help( $this->hook, $this->get_contextual_help_string() );
    }

    // ###################################################################################################################
    function show_manage_page() {
        // get and check action parameter from passed variables
        $action = ( isset( $_REQUEST['action'] ) && !empty( $_REQUEST['action'] ) ) ? $_REQUEST['action'] : 'list';
        // check if action is in allowed actions and if method is callable, if yes, call it
        if ( in_array( $action, $this->allowed_actions ) && is_callable( array( &$this, 'do_action_' . $action ) ) )
            call_user_func( array( &$this, 'do_action_' . $action ) );
        else
            call_user_func( array( &$this, 'do_action_list' ) );
    }
    
    // ###################################################################################################################
    // ##########################################                   ######################################################
    // ##########################################      ACTIONS      ######################################################
    // ##########################################                   ######################################################
    // ###################################################################################################################

    // ###################################################################################################################
    // list all tables
    function do_action_list()  {
        $this->print_list_tables_form();
    }
    
    // ###################################################################################################################
    function do_action_add() {
        if ( isset( $_POST['submit'] ) && isset( $_POST['table'] ) ) {
            check_admin_referer( $this->get_nonce( 'add' ) );

            $rows = ( 0 < $_POST['table']['rows'] ) ? $_POST['table']['rows'] : 1;
            $cols = ( 0 < $_POST['table']['cols'] ) ? $_POST['table']['cols'] : 1;

            $table = $this->default_table;

            $table['id'] = $this->get_new_table_id();
            $table['data'] = $this->create_empty_table( $rows, $cols );
            $table['name'] = $_POST['table']['name'];
            $table['description'] = $_POST['table']['description'];

            $this->save_table( $table );

            $this->print_success_message( sprintf( __( 'Table "%s" added successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table['name'] ) ) );
            $this->print_edit_table_form( $table['id'] );
        } else {
            $this->print_add_table_form();
        }
    }

    // ###################################################################################################################
    function do_action_edit() {
        if ( isset( $_POST['submit'] ) && isset( $_POST['table'] ) ) {
            check_admin_referer( $this->get_nonce( 'edit' ) );
            
            $subactions = array_keys( $_POST['submit'] );
            $subaction = $subactions[0];
            
            switch( $subaction ) {
            case 'update':
            case 'save_back':
                $table = $_POST['table'];   // careful here to not miss any stuff!!! (options, etc.)
                $table['options']['alternating_row_colors'] = isset( $_POST['table']['options']['alternating_row_colors'] );
                $table['options']['first_row_th'] = isset( $_POST['table']['options']['first_row_th'] );
                $table['options']['print_name'] = isset( $_POST['table']['options']['print_name'] );
                $table['options']['print_description'] = isset( $_POST['table']['options']['print_description'] );
                $this->save_table( $table );
                $message = __( 'Table edited successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'swap_rows':
                $table_id = $_POST['table']['id'];
                $row_id1 = ( isset( $_POST['swap']['row'][1] ) ) ? $_POST['swap']['row'][1] : -1;
                $row_id2 = ( isset( $_POST['swap']['row'][2] ) ) ? $_POST['swap']['row'][2] : -1;
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                // swap rows $row_id1 and $row_id2
                if ( ( 1 < $rows ) && ( -1 < $row_id1 ) && ( -1 < $row_id2 ) ) {
                    $temp_row = $table['data'][$row_id1];
                    $table['data'][$row_id1] = $table['data'][$row_id2];
                    $table['data'][$row_id2] = $temp_row;
                    unset($temp_row);
                }
                $this->save_table( $table );
                $message = __( 'Rows swapped successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'swap_cols':
                $table_id = $_POST['table']['id'];
                $col_id1 = ( isset( $_POST['swap']['col'][1] ) ) ? $_POST['swap']['col'][1] : -1;
                $col_id2 = ( isset( $_POST['swap']['col'][2] ) ) ? $_POST['swap']['col'][2] : -1;
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
                // swap rows $col_id1 and $col_id2
                if ( ( 1 < $cols ) && ( -1 < $col_id1 ) && ( -1 < $col_id2 ) ) {
                  foreach ( $table['data'] as $row_idx => $row ) {
                        $temp_col = $table['data'][$row_idx][$col_id1];
                        $table['data'][$row_idx][$col_id1] = $table['data'][$row_idx][$col_id2];
                        $table['data'][$row_idx][$col_id2] = $temp_col;
                    }
                    unset($temp_col);
                }
                $this->save_table( $table );
                $message = __( 'Columns swapped successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'sort':
                $table_id = $_POST['table']['id'];
                $column = ( isset( $_POST['sort']['col'] ) ) ? $_POST['sort']['col'] : -1;
                $sort_order= ( isset( $_POST['sort']['order'] ) ) ? $_POST['sort']['order'] : 'ASC';
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                // sort array for $column in $sort_order
                if ( ( 1 < $rows ) && ( -1 < $column ) ) {
                    $sortarray = $this->create_class_instance( 'arraysort', 'arraysort.class.php' );
                    $sortarray->input_array = $table['data'];
                    $sortarray->column = $column;
                    $sortarray->order = $sort_order;
                    $sortarray->sort();
                    $table['data'] = $sortarray->sorted_array;
                }
                $this->save_table( $table );
                $message = __( 'Table sorted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            default:
                $this->do_action_list();
            }

            $this->print_success_message( $message );
            if ( 'save_back' == $subaction ) {
                $this->do_action_list();
            } else {
                $this->print_edit_table_form( $table['id'] );
            }
        } elseif ( isset( $_GET['table_id'] ) ) {
            $this->print_edit_table_form( $_GET['table_id'] );
        } else {
            $this->do_action_list();
        }
    }

    // ###################################################################################################################
    function do_action_bulk_edit() {
        if ( isset( $_POST['submit'] ) ) {
            check_admin_referer( $this->get_nonce( 'bulk_edit' ) );

            if ( isset( $_POST['tables'] ) ) {

                $subactions = array_keys( $_POST['submit'] );
                $subaction = $subactions[0];

                switch( $subaction ) {
                case 'copy': // see do_action_copy for explanations
                    foreach ( $_POST['tables'] as $table_id ) {
                        $table_to_copy = $this->load_table( $table_id );
                        $new_table = $table_to_copy;
                        $new_table['id'] = $this->get_new_table_id();
                        $new_table['name'] = __( 'Copy of', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' ' . $table_to_copy['name'];
                        unset( $table_to_copy );
                        $this->save_table( $new_table );
                    }
                    $message = __ngettext( 'Table copied successfully.', 'Tables copied successfully.', count( $_POST['tables'] ), WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                case 'delete': // see do_action_delete for explanations
                    foreach ( $_POST['tables'] as $table_id ) {
                        $this->tables[ $table_id ] = ( isset( $this->tables[ $table_id ] ) ) ? $this->tables[ $table_id ] : $this->optionname['table'] . '_' . $table_id;
                        delete_option( $this->tables[ $table_id ] );
                        unset( $this->tables[ $table_id ] );
                    }
                    $this->update_tables();
                    $message = __ngettext( 'Table deleted successfully.', 'Tables deleted successfully.', count( $_POST['tables'] ), WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                case 'wp_table_import': // see do_action_import for explanations
                    $this->import_instance = $this->create_class_instance( 'WP_Table_Reloaded_Import', 'wp-table-reloaded-import.class.php' );
                    $this->import_instance->import_format = 'wp_table';
                    foreach ( $_POST['tables'] as $table_id ) {
                        $this->import_instance->wp_table_id = $table_id;
                        $this->import_instance->import_table();
                        $imported_table = $this->import_instance->imported_table;
                        $table = array_merge( $this->default_table, $imported_table );
                        $table['id'] = $this->get_new_table_id();
                        $this->save_table( $table );
                    }
                    $message = __ngettext( 'Table imported successfully.', 'Tables imported successfully.', count( $_POST['tables'] ), WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                default:
                    break;
                }

            } else {
                $message = __( 'You did not select any tables!', WP_TABLE_RELOADED_TEXTDOMAIN );
            }
            $this->print_success_message( $message );
        }
        $this->do_action_list();
    }

    // ###################################################################################################################
    function do_action_copy() {
        if ( isset( $_GET['table_id'] ) ) {
            check_admin_referer( $this->get_nonce( 'copy' ) );

            $table_to_copy = $this->load_table( $_GET['table_id'] );

            // new table
            $new_table = $table_to_copy;
            $new_table['id'] = $this->get_new_table_id();
            $new_table['name'] = __( 'Copy of', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' ' . $table_to_copy['name'];
            unset( $table_to_copy );

            $this->save_table( $new_table );

            $this->print_success_message( sprintf( __( 'Table "%s" copied successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $new_table['name'] ) ) );
        }
        $this->do_action_list();
    }

    // ###################################################################################################################
    function do_action_delete() {
        if ( isset( $_GET['table_id'] ) && isset( $_GET['item'] ) ) {
            check_admin_referer( $this->get_nonce( 'delete', $_GET['item'] ) );

            $table_id = $_GET['table_id'];
            $table = $this->load_table( $table_id );

            switch( $_GET['item'] ) {
            case 'table':
                $this->tables[ $table_id ] = ( isset( $this->tables[ $table_id ] ) ) ? $this->tables[ $table_id ] : $this->optionname['table'] . '_' . $table_id;
                delete_option( $this->tables[ $table_id ] );
                unset( $this->tables[ $table_id ] );
                $this->update_tables();
                $this->print_success_message( sprintf( __( 'Table "%s" deleted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table['name'] ) ) );
                $this->do_action_list();
                break;
            case 'row':
                $row_id = ( isset( $_GET['element_id'] ) ) ? $_GET['element_id'] : -1;
                $rows = count( $table['data'] );
                // delete row with key $row_id, if there are at least 2 rows
                if ( ( 1 < $rows ) && ( -1 < $row_id ) ) {
                    array_splice( $table['data'], $row_id, 1 );
                    $this->save_table( $table );
                    $this->print_success_message( __( 'Row deleted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                }
                $this->print_edit_table_form( $table_id );
                break;
            case 'col':
                $col_id = ( isset( $_GET['element_id'] ) ) ? $_GET['element_id'] : -1;
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
                // delete column with key $col_id, if there are at least 2 columns
                if ( ( 1 < $cols ) && ( -1 < $col_id ) ) {
                    foreach ( $table['data'] as $row_idx => $row )
                        array_splice( $table['data'][$row_idx], $col_id, 1 );
                    $this->save_table( $table );
                    $this->print_success_message( __( 'Column deleted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                }
                $this->print_edit_table_form( $table_id );
                break;
            default:
                $this->print_success_message( __( 'Delete failed.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->do_action_list();
            } // end switch
        } else {
            $this->do_action_list();
        }
    }

    // ###################################################################################################################
    function do_action_insert() {
        if ( isset( $_GET['table_id'] ) && isset( $_GET['item'] ) && isset( $_GET['element_id'] ) ) {
            check_admin_referer( $this->get_nonce( 'insert', $_GET['item']  ) );

            $table_id = $_GET['table_id'];
            $table = $this->load_table( $table_id );

            switch( $_GET['item'] ) {
            case 'row':
                $row_id = $_GET['element_id'];
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
                // init new empty row (with all columns) and insert it before row with key $row_id
                $new_row = array( array_fill( 0, $cols, '' ) );
                array_splice( $table['data'], $row_id, 0, $new_row );
                $this->save_table( $table );
                $message = __( 'Row inserted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'col':
                $col_id = $_GET['element_id'];
                // init new empty row (with all columns) and insert it before row with key $row_id
                $new_col = '';
                foreach ( $table['data'] as $row_idx => $row )
                    array_splice( $table['data'][$row_idx], $col_id, 0, $new_col );
                $this->save_table( $table );
                $message = __( 'Column inserted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            default:
                $message = __( 'Insert failed.', WP_TABLE_RELOADED_TEXTDOMAIN );
            }
            $this->print_success_message( $message );
            $this->print_edit_table_form( $table_id );
        } else {
            $this->do_action_list();
        }
    }

    // ###################################################################################################################
    function do_action_import() {
        $this->import_instance = $this->create_class_instance( 'WP_Table_Reloaded_Import', 'wp-table-reloaded-import.class.php' );
        if ( isset( $_POST['submit'] ) && ( isset( $_FILES['import_file'] ) || isset( $_POST['import_data'] ) ) ) {
            check_admin_referer( $this->get_nonce( 'import' ) );

            // do import
            if ( false == empty( $_FILES['import_file']['tmp_name'] ) ) {
                $this->import_instance->tempname = $_FILES['import_file']['tmp_name'];
                $this->import_instance->filename = $_FILES['import_file']['name'];
                $this->import_instance->mimetype = $_FILES['import_file']['type'];
                $this->import_instance->import_from = 'file-upload';
                $this->import_instance->import_format = $_POST['import_format'];
                $this->import_instance->import_table();
                $error = $this->import_instance->error;
                $imported_table = $this->import_instance->imported_table;
                $this->import_instance->unlink_uploaded_file();
            } elseif ( isset( $_POST['import_data'] ) ) {
                $this->import_instance->tempname = '';
                $this->import_instance->filename = __( 'Imported Table', WP_TABLE_RELOADED_TEXTDOMAIN );
                $this->import_instance->mimetype = __( 'via form', WP_TABLE_RELOADED_TEXTDOMAIN );
                $this->import_instance->import_from = 'form-field';
                $this->import_instance->import_data = stripslashes( $_POST['import_data'] );
                $this->import_instance->import_format = $_POST['import_format'];
                $this->import_instance->import_table();
                $error = $this->import_instance->error;
                $imported_table = $this->import_instance->imported_table;
            }

            $table = array_merge( $this->default_table, $imported_table );

            $table['id'] = $this->get_new_table_id();

            $this->save_table( $table );

            if ( false == $error ) {
                $this->print_success_message( __( 'Table imported successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->print_edit_table_form( $table['id'] );
            } else {
                $this->print_success_message( __( 'Table could not be imported.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->print_import_table_form();
            }
        } elseif (  'wp_table' == $_GET['import_format'] && isset( $_GET['wp_table_id'] ) ) {
            check_admin_referer( $this->get_nonce( 'import' ) );

            // do import
            $this->import_instance->import_format = 'wp_table';
            $this->import_instance->wp_table_id = $_GET['wp_table_id'];
            $this->import_instance->import_table();
            $imported_table = $this->import_instance->imported_table;

            $table = array_merge( $this->default_table, $imported_table );

            $table['id'] = $this->get_new_table_id();

            $this->save_table( $table );

            $this->print_success_message( __( 'Table imported successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
            $this->print_edit_table_form( $table['id'] );
        } else {
            $this->print_import_table_form();
        }
    }

    // ###################################################################################################################
    function do_action_export() {
        $this->export_instance = $this->create_class_instance( 'WP_Table_Reloaded_Export', 'wp-table-reloaded-export.class.php' );
        if ( isset( $_POST['submit'] ) && isset( $_POST['table_id'] ) && isset( $_POST['export_format'] ) ) {
            check_admin_referer( $this->get_nonce( 'export' ) );

            $table_to_export = $this->load_table( $_POST['table_id'] );
            
            $this->export_instance->table_to_export = $table_to_export;
            $this->export_instance->export_format = $_POST['export_format'];
            $this->export_instance->delimiter = $_POST['delimiter'];
            $this->export_instance->export_table();
            $exported_table = $this->export_instance->exported_table;

            if ( isset( $_POST['wp_table_reloaded_download_export_file'] ) ) {
                $filename = $table_to_export['id'] . '-' . $table_to_export['name'] . '-' . date('Y-m-d') . '.' . $_POST['export_format'];
                $this->prepare_download( $filename, strlen( $exported_table ), 'text/' . $_POST['export_format'] );
                echo $exported_table;
                exit;
            } else {
                $this->print_success_message( sprintf( __( 'Table "%s" exported successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table_to_export['name'] ) ) );
                $this->print_export_table_form( $_POST['table_id'], $exported_table );
            }
        } else {
            $this->print_export_table_form( $_REQUEST['table_id'] );
        }
    }
    
    // ###################################################################################################################
    function do_action_options() {
        if ( isset( $_POST['submit'] ) && isset( $_POST['options'] ) ) {
            check_admin_referer( $this->get_nonce( 'options' ) );

            $new_options = $_POST['options'];
            
            // checkboxes: option value is defined by whether option isset (e.g. was checked) or not
            $this->options['uninstall_upon_deactivation'] = isset( $new_options['uninstall_upon_deactivation'] );
            $this->options['enable_tablesorter'] = isset( $new_options['enable_tablesorter'] );
            $this->options['use_custom_css'] = isset( $new_options['use_custom_css'] );
            // clean up CSS style input (if user enclosed it into <style...></style>
            if ( isset( $new_options['custom_css'] ) ) {
                    if ( 1 == preg_match( '/<style.*?>(.*?)<\/style>/is', stripslashes( $new_options['custom_css'] ), $matches ) )
                        $new_options['custom_css'] = $matches[1]; // if found, take match as style to save
                    $this->options['custom_css'] = $new_options['custom_css'];
            }

            $this->update_options();

            $this->print_success_message( __( 'Options saved successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        }
        $this->print_plugin_options_form();
    }
    
    // ###################################################################################################################
    function do_action_uninstall() {
        check_admin_referer( $this->get_nonce( 'uninstall' ) );

        // everything shall be deleted (manual uninstall)
        $this->options['uninstall_upon_deactivation'] = true;
        $this->update_options();

        $plugin = WP_TABLE_RELOADED_BASENAME;
        deactivate_plugins( $plugin );
        if ( false !== get_option( 'recently_activated', false ) )
            update_option( 'recently_activated', array( $plugin => time()) + (array)get_option( 'recently_activated' ) );

        $this->print_page_header( __( 'WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_success_message( __( 'Plugin deactivated successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        echo "<p>" . __( "All tables, data and options were deleted. You may now remove the plugin's subfolder from your WordPress plugin folder.", WP_TABLE_RELOADED_TEXTDOMAIN ) . "</p>";
        $this->print_page_footer();
    }
    
    // ###################################################################################################################
    function do_action_info() {
        $this->print_plugin_info_form();
    }

    // ###################################################################################################################
    function do_action_ajax_list() {
        check_admin_referer( $this->get_nonce( 'ajax_list' ) );

        // init language support
        $this->init_language_support();

        $this->print_page_header( __( 'List of Tables', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        ?>
        <div style="clear:both;"><p style="width:97%;"><?php _e( 'This is a list of all available tables.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You may insert a table into a post or page here.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br />
		<?php _e( 'Click the "Insert" link after the desired table and the corresponding shortcode will be inserted into the editor (<strong>[table id=&lt;the_table_ID&gt; /]</strong>).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p></div>
		<?php
        if ( 0 < count( $this->tables ) ) {
            ?>
        <div style="clear:both;">
            <table class="widefat" style="width:97%;">
            <thead>
                <tr>
                    <th scope="col"><?php _e( 'ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Action', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $bg_style_index = 0;
            foreach ( $this->tables as $id => $tableoptionname ) {
                $bg_style_index++;
                $bg_style = ( 0 == ($bg_style_index % 2) ) ? ' class="alternate"' : '';

                // get name and description to show in list
                $table = $this->load_table( $id );
                    $name = $this->safe_output( $table['name'] );
                    $description = $this->safe_output( $table['description'] );
                unset( $table );

                echo "<tr{$bg_style}>\n";
                echo "\t<th scope=\"row\">{$id}</th>";
                echo "<td>{$name}</td>";
                echo "<td>{$description}</td>";
                echo "<td><a class=\"send_table_to_editor\" title=\"{$id}\" href=\"#\" style=\"color:#21759B;\">" . __( 'Insert', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a></td>\n";
                echo "</tr>\n";
            }
            ?>
           </tbody>
           </table>
        </div>
        <?php
        } else { // end if $tables
            echo "<div style=\"clear:both;\"><p>" . __( 'No tables found.', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</p></div>";
        }
        $this->print_page_footer();

        // necessary to stop page building here!
        exit;
    }
    
    // ###################################################################################################################
    // ##########################################                     ####################################################
    // ##########################################     Print Forms     ####################################################
    // ##########################################                     ####################################################
    // ###################################################################################################################

    // ###################################################################################################################
    // list all tables
    function print_list_tables_form()  {
        $this->print_page_header( __( 'List of Tables', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'list' );
        ?>
        <div style="clear:both;"><p><?php _e( 'This is a list of all available tables.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You may add, edit, copy or delete tables here.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br />
		<?php _e( 'If you want to show a table in your pages, posts or text-widgets, use the shortcode <strong>[table id=&lt;the_table_ID&gt; /]</strong> or click the button "Table" in the editor toolbar.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p></div>
		<?php
        if ( 0 < count( $this->tables ) ) {
            ?>
        <div style="clear:both;">
            <form method="post" action="<?php echo $this->get_action_url(); ?>">
            <?php wp_nonce_field( $this->get_nonce( 'bulk_edit' ) ); ?>
            <table class="widefat">
            <thead>
                <tr>
                    <th class="check-column" scope="col"><input type="checkbox" /></th>
                    <th scope="col"><?php _e( 'ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Action', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                </tr>
            </thead>
            <?php
            echo "<tbody>\n";
            $bg_style_index = 0;
            foreach ( $this->tables as $id => $tableoptionname ) {
                $bg_style_index++;
                $bg_style = ( 0 == ($bg_style_index % 2) ) ? ' class="alternate"' : '';

                // get name and description to show in list
                $table = $this->load_table( $id );
                    $name = $this->safe_output( $table['name'] );
                    $description = $this->safe_output( $table['description'] );
                unset( $table );

                $edit_url = $this->get_action_url( array( 'action' => 'edit', 'table_id' => $id ), false );
                $copy_url = $this->get_action_url( array( 'action' => 'copy', 'table_id' => $id ), true );
                $export_url = $this->get_action_url( array( 'action' => 'export', 'table_id' => $id ), false );
                $delete_url = $this->get_action_url( array( 'action' => 'delete', 'table_id' => $id, 'item' => 'table' ), true );

                echo "<tr{$bg_style}>\n";
                echo "\t<th class=\"check-column\" scope=\"row\"><input type=\"checkbox\" name=\"tables[]\" value=\"{$id}\" /></th>";
                echo "<th scope=\"row\">{$id}</th>";
                echo "<td>{$name}</td>";
                echo "<td>{$description}</td>";
                echo "<td><a href=\"{$edit_url}\">" . __( 'Edit', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                echo "<a class=\"copy_table_link\" href=\"{$copy_url}\">" . __( 'Copy', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                echo "<a href=\"{$export_url}\">" . __( 'Export', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                echo "<a class=\"delete_table_link delete\" href=\"{$delete_url}\">" . __( 'Delete', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a></td>\n";
                echo "</tr>\n";

            }
            echo "</tbody>\n";
            echo "</table>\n";
        ?>
        <input type="hidden" name="action" value="bulk_edit" />
        <p class="submit"><?php _e( 'Bulk actions:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>  <input type="submit" name="submit[copy]" class="button-primary bulk_copy_tables" value="<?php _e( 'Copy Tables', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" /> <input type="submit" name="submit[delete]" class="button-primary bulk_delete_tables" value="<?php _e( 'Delete Tables', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </p>

        </form>
        <?php
            echo "</div>";
        } else { // end if $tables
            $add_url = $this->get_action_url( array( 'action' => 'add' ), false );
            $import_url = $this->get_action_url( array( 'action' => 'import' ), false );
            echo "<div style=\"clear:both;\"><p>" . __( 'No tables found.', WP_TABLE_RELOADED_TEXTDOMAIN ) . '<br/>' . sprintf( __( 'You might <a href="%s">add</a> or <a href="%s">import</a> one!', WP_TABLE_RELOADED_TEXTDOMAIN ), $add_url, $import_url ) . "</p></div>";
        }
        $this->print_page_footer();
    }
    
    // ###################################################################################################################
    function print_add_table_form() {
        // Begin Add Table Form
        $this->print_page_header( __( 'Add new Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'add' );
        ?>
        <div style="clear:both;">
        <p><?php _e( 'You can add a new table here. Just enter it\'s name, a description (optional) and the number of rows and columns.<br/>You may add, insert or delete rows and columns later.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
		<div style="clear:both;">
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'add' ) ); ?>

        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><label for="table[name]"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table[name]" value="<?php _e( 'Enter Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" style="width:250px;" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table[description]"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><textarea name="table[description]" id="table[description]" rows="15" cols="40" style="width:250px;height:85px;"><?php _e( 'Enter Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></textarea></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table[rows]"><?php _e( 'Number of Rows', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table[rows]" id="table[rows]" value="5" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table[cols]"><?php _e( 'Number of Columns', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table[cols]" id="table[cols]" value="5" /></td>
        </tr>
        </table>

        <input type="hidden" name="action" value="add" />
        <p class="submit">
        <input type="submit" name="submit" class="button-primary" value="<?php _e( 'Add Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </p>

        </form>
        </div>
        <?php
        $this->print_page_footer();
    }

    // ###################################################################################################################
    function print_edit_table_form( $table_id ) {

        $table = $this->load_table( $table_id );

        $rows = count( $table['data'] );
        $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;

        $this->print_page_header( sprintf( __( 'Edit Table "%s"', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table['name'] ) ) );
        $this->print_submenu_navigation( 'edit' );
        ?>
        <div style="clear:both;"><p><?php _e( 'You may edit the content of the table here. It is also possible to add or delete columns and rows.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br />
		<?php echo sprintf( __( 'If you want to show a table in your pages, posts or text-widgets, use this shortcode: <strong>[table id=%s /]</strong>', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table_id ) ); ?></p></div>
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'edit' ) ); ?>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Table Information', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><label for="table[name]"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table[name]" id="table[name]" value="<?php echo $this->safe_output( $table['name'] ); ?>" style="width:250px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table[description]"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><textarea name="table[description]" id="table[description]" rows="15" cols="40" style="width:250px;height:85px;"><?php echo $this->safe_output( $table['description'] ); ?></textarea></td>
        </tr>
        </table>
        </div>
        </div>

        <p class="submit">
        <input type="submit" name="submit[update]" class="button-primary" value="<?php _e( 'Update Changes', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <input type="submit" name="submit[save_back]" class="button-primary" value="<?php _e( 'Save and go back', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php
        $list_url = $this->get_action_url( array( 'action' => 'list' ) );
        echo " <a class=\"button-primary\" href=\"{$list_url}\">" . __( 'Cancel', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
        ?>
        </p>

        <?php if ( 0 < $cols && 0 < $rows ) { ?>
            <div class="postbox">
            <h3 class="hndle"><span><?php _e( 'Table Contents', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php _e( 'Hide', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
            <div class="inside">
            <table class="widefat" style="width:auto;" id="table_contents">
                <thead>
                    <tr>
                        <th>&nbsp;</th>
                        <?php
                            // Table Header (Columns get a Letter between A and A+$cols-1)
                            foreach ( range( 'A', chr( ord( 'A' ) + $cols - 1 ) ) as $letter )
                                echo "<th scope=\"col\">".$letter."</th>";
                        ?>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach ( $table['data'] as $row_idx => $table_row ) {
                    echo "<tr>\n";
                    // Table Header (Rows get a Number between 1 and $rows)
                    $output_idx = $row_idx + 1;
                    echo "\t<th scope=\"row\">{$output_idx}</th>\n";
                    foreach ( $table_row as $col_idx => $cell_content ) {
                        $cell_content = $this->safe_output( $cell_content );
                        $cell_name = "table[data][{$row_idx}][{$col_idx}]";
                        echo "\t<td><input type=\"text\" name=\"{$cell_name}\" value=\"{$cell_content}\" /></td>\n";
                    }
                    $insert_row_url = $this->get_action_url( array( 'action' => 'insert', 'table_id' => $table['id'], 'item' => 'row', 'element_id' => $row_idx ), true );
                    $delete_row_url = $this->get_action_url( array( 'action' => 'delete', 'table_id' => $table['id'], 'item' => 'row', 'element_id' => $row_idx ), true );
                    echo "\t<td><a href=\"{$insert_row_url}\">" . __( 'Insert Row', WP_TABLE_RELOADED_TEXTDOMAIN )."</a>";
                    if ( 1 < $rows ) // don't show delete link for last and only row
                        echo " | <a class=\"delete_row_link\" href=\"{$delete_row_url}\">".__( 'Delete Row', WP_TABLE_RELOADED_TEXTDOMAIN )."</a>";
                    echo "</td>\n</tr>";
                }
                ?>
                <?php
                    echo "<tr>\n";
                    echo "\t<th scope=\"row\">&nbsp;</th>\n";
                    foreach ( $table['data'][0] as $col_idx => $cell_content ) {
                        $insert_col_url = $this->get_action_url( array( 'action' => 'insert', 'table_id' => $table['id'], 'item' => 'col', 'element_id' => $col_idx ), true );
                        $delete_col_url = $this->get_action_url( array( 'action' => 'delete', 'table_id' => $table['id'], 'item' => 'col', 'element_id' => $col_idx ), true );
                        echo "\t<td><a href=\"{$insert_col_url}\">" . __( 'Insert Column', WP_TABLE_RELOADED_TEXTDOMAIN )."</a>";
                        if ( 1 < $cols ) // don't show delete link for last and only column
                            echo " | <a class=\"delete_column_link\" href=\"{$delete_col_url}\">" . __('Delete Column', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
                        echo "</td>\n";
                    }
                    $add_row_url = $this->get_action_url( array( 'action' => 'insert', 'table_id' => $table['id'],'item' => 'row',  'element_id' => $rows ), true ); // number of $rows is equal to new row's id
                    $add_col_url = $this->get_action_url( array( 'action' => 'insert', 'table_id' => $table['id'],'item' => 'col',  'element_id' => $cols ), true ); // number of $cols is equal to new col's id
                    echo "\t<td><a href=\"{$add_row_url}\">" . __( 'Add Row', WP_TABLE_RELOADED_TEXTDOMAIN )."</a> | <a href=\"{$add_col_url}\">" . __( 'Add Column', WP_TABLE_RELOADED_TEXTDOMAIN )."</a></td>\n";
                    echo "</tr>";
                ?>
                </tbody>
            </table>
        </div>
        </div>
        <?php } //endif ?>
            <div class="postbox">
            <h3 class="hndle"><span><?php _e( 'Data Manipulation', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php _e( 'Hide', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
            <div class="inside">
<table class="wp-table-reloaded-data-manipulation"><tr><td>
        <?php if ( 1 < $rows ) { // swap rows form

            $row1_select = '<select name="swap[row][1]">';
            foreach ( $table['data'] as $row_idx => $table_row )
                       $row1_select .= "<option value=\"{$row_idx}\">" . ( $row_idx + 1 ) . "</option>";
            $row1_select .= '</select>';

            $row2_select = '<select name="swap[row][2]">';
            foreach ( $table['data'] as $row_idx => $table_row )
                      $row2_select .= "<option value=\"{$row_idx}\">" . ( $row_idx + 1 ) . "</option>";
            $row2_select .= '</select>';
            
            echo sprintf( __( 'Swap rows %s and %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $row1_select, $row2_select );

            ?>
            <input type="submit" name="submit[swap_rows]" class="button-primary" value="<?php _e( 'Swap', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php } // end if form swap rows ?>

        <?php if ( 1 < $cols ) { // swap cols form ?>
            <br/>
            <?php
            
            $col1_select = '<select name="swap[col][1]">';
            foreach ( $table['data'][0] as $col_idx => $cell_content )
                $col1_select .= "<option value=\"{$col_idx}\">" . ( chr( ord( 'A' ) + $col_idx ) ) . "</option>";
            $col1_select .= '</select>';

            $col2_select = '<select name="swap[col][2]">';
            foreach ( $table['data'][0] as $col_idx => $cell_content )
                $col2_select .= "<option value=\"{$col_idx}\">" . ( chr( ord( 'A' ) + $col_idx ) ) . "</option>";
            $col2_select .= '</select>';

            echo sprintf( __( 'Swap columns %s and %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $col1_select, $col2_select );

            ?>
            <input type="submit" name="submit[swap_cols]" class="button-primary" value="<?php _e( 'Swap', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php } // end if form swap cols ?>
</td><td>
        <a id="a-insert-link" class="button-primary" href=""><?php _e( 'Insert Link', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a> <a id="a-insert-image" class="button-primary" href=""><?php _e( 'Insert Image', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
</td>
<td>
        <?php if ( 1 < $rows ) { // sort form

            $col_select = '<select name="sort[col]">';
            foreach ( $table['data'][0] as $col_idx => $cell_content )
                $col_select .= "<option value=\"{$col_idx}\">" . ( chr( ord( 'A' ) + $col_idx ) ) . "</option>";
            $col_select .= '</select>';

            $sort_order_select = '<select name="sort[order]">';
            $sort_order_select .= "<option value=\"ASC\">" . __( 'ascending', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</option>";
            $sort_order_select .= "<option value=\"DESC\">" . __( 'descending', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</option>";
            $sort_order_select .= '</select>';

            echo sprintf( __( 'Sort table by column %s in %s order', WP_TABLE_RELOADED_TEXTDOMAIN ), $col_select, $sort_order_select );

        ?>
            <input type="submit" name="submit[sort]" class="button-primary" value="<?php _e( 'Sort', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php } // end if sort form ?>
</td>
</tr>
</table>
        </div>
        </div>
        
        <br/>
        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Table Settings', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php _e( 'Hide', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'These settings will only be used for this table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'Alternating row colors', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][alternating_row_colors]" id="table[options][alternating_row_colors]"<?php echo ( true == $table['options']['alternating_row_colors'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table[options][alternating_row_colors]"><?php _e( 'Every second row will have an alternating background color.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top" id="options_use_tableheadline">
            <th scope="row"><?php _e( 'Use Table Headline', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][first_row_th]" id="table[options][first_row_th]"<?php echo ( true == $table['options']['first_row_th'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table[options][first_row_th]"><?php _e( 'The first row of your table will use the [th] tag.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Print Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][print_name]" id="table[options][print_name]"<?php echo ( true == $table['options']['print_name'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table[options][print_name]"><?php _e( 'The Table Name will be written above the table in a [h2] tag.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Print Table Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][print_description]" id="table[options][print_description]"<?php echo ( true == $table['options']['print_description'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table[options][print_description]"><?php _e( 'The Table Description will be written under the table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top" id="options_use_tablesorter">
            <th scope="row"><?php _e( 'Use Tablesorter', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][use_tablesorter]" id="table[options][use_tablesorter]"<?php echo ( true == $table['options']['use_tablesorter'] ) ? ' checked="checked"': '' ; ?><?php echo ( false == $this->options['enable_tablesorter'] || false == $table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table[options][use_tablesorter]"><?php _e( 'You may sort a table using the <a href="http://www.tablesorter.com/">Tablesorter-jQuery-Plugin</a>. <small>Attention: You must have Tablesorter enabled on the "Plugin Options" page and the option "Use Table Headline" has to be enabled above for this to work!</small>', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        </table>
        </div>
        </div>
        <input type="hidden" id="tablesorter_enabled" value="<?php echo $this->options['enable_tablesorter']; ?>" />
        <input type="hidden" name="table[id]" value="<?php echo $table['id']; ?>" />
        <input type="hidden" name="action" value="edit" />
        <p class="submit">
        <input type="submit" name="submit[update]" class="button-primary" value="<?php _e( 'Update Changes', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <input type="submit" name="submit[save_back]" class="button-primary" value="<?php _e( 'Save and go back', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php
        $list_url = $this->get_action_url( array( 'action' => 'list' ) );
        echo " <a class=\"button-primary\" href=\"{$list_url}\">" . __( 'Cancel', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
        
        echo '<br/><br/>' . __( 'Other actions', WP_TABLE_RELOADED_TEXTDOMAIN ) . ':';
        $delete_url = $this->get_action_url( array( 'action' => 'delete', 'table_id' => $table['id'], 'item' => 'table' ), true );
        $export_url = $this->get_action_url( array( 'action' => 'export', 'table_id' => $table['id'] ), false );
        echo " <a class=\"button-secondary delete_table_link\" href=\"{$delete_url}\">" . __( 'Delete Table', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
        echo " <a class=\"button-secondary\" href=\"{$export_url}\">" . __( 'Export Table', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
        ?>
        </p>
        </form>
        <?php
        $this->print_page_footer();
    }

    // ###################################################################################################################
    function print_import_table_form() {
        // Begin Import Table Form
        $this->print_page_header( __( 'Import a Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'import' );
        ?>
        <div style="clear:both;">
        <p><?php _e( 'You may import a table from existing data here.<br/>It may be a CSV, XML or HTML file. It needs a certain structure though. Please consult the documentation.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        <div style="clear:both;">
        <form method="post" enctype="multipart/form-data" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'import' ) ); ?>
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><label for="import_format"><?php _e( 'Select Import Format', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><select id="import_format" name="import_format">
        <?php
            $import_formats = $this->import_instance->import_formats;
            foreach ( $import_formats as $import_format => $longname )
                echo "<option value=\"{$import_format}\">{$longname}</option>";
        ?>
        </select></td>
        </tr>
        <tr valign="top" class="tr-import-file">
            <th scope="row"><label for="import_file"><?php _e( 'Select File with Table to Import', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input name="import_file" id="import_file" type="file" /></td>
        </tr>
        <tr valign="top">
            <th scope="row" style="text-align:center;"><strong><?php _e( '- or -', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></strong></th>
            <td><small><?php _e( '(upload will be preferred over pasting)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></td>
        </tr>
        <tr valign="top" class="tr-import-data">
            <th scope="row" style="vertical-align:top;"><label for="import_data"><?php _e( 'Paste data with Table to Import', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><textarea  name="import_data" id="import_data" rows="15" cols="40" style="width:600px;height:300px;"></textarea></td>
        </tr>
        </table>
        <input type="hidden" name="action" value="import" />
        <p class="submit">
        <input type="submit" name="submit" class="button-primary" value="<?php _e( 'Import Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </p>
        </form>
        </div>
        <?php // check if plugin is installed at all / if tables in db exist
        global $wpdb;
        $wpdb->golftable  = $wpdb->prefix . 'golftable';
        $wpdb->golfresult = $wpdb->prefix . 'golfresult';

        if ( $wpdb->golftable == $wpdb->get_var( "show tables like '{$wpdb->golftable}'" ) && $wpdb->golfresult == $wpdb->get_var( "show tables like '{$wpdb->golfresult}'" ) ) {
        // wp-Table tables exist -> the plugin might be installed, so we output all found tables

        ?>
        <h2><?php _e( 'Import from original wp-Table plugin', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></h2>
        <div style="clear:both;">
        <?php
        $tables = $wpdb->get_results("SELECT * FROM $wpdb->golftable ORDER BY 'table_aid' ASC ");
        if ( 0 < count( $tables ) ) {
            // Tables found in db
        ?>
            <form method="post" action="<?php echo $this->get_action_url(); ?>">
            <?php wp_nonce_field( $this->get_nonce( 'bulk_edit' ) ); ?>
            <table class="widefat">
            <thead>
                <tr>
                    <th class="check-column" scope="col"><input type="checkbox" /></th>
                    <th scope="col"><?php _e( 'ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Action', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                </tr>
            </thead>
            <?php
            echo "<tbody>\n";
            $bg_style_index = 0;
            foreach ( $tables as $table ) {
                $bg_style_index++;
                $bg_style = ( 0 == ($bg_style_index % 2) ) ? ' class="alternate"' : '';

                $table_id = $table->table_aid;
                $name = $table->table_name;
                $description = $table->description;

                $import_url = $this->get_action_url( array( 'action' => 'import', 'import_format' => 'wp_table', 'wp_table_id' => $table_id ), true );

                echo "<tr{$bg_style}>\n";
                echo "\t<th class=\"check-column\" scope=\"row\"><input type=\"checkbox\" name=\"tables[]\" value=\"{$table_id}\" /></th>";
                echo "<th scope=\"row\">{$table_id}</th>";
                echo "<td>{$name}</td>";
                echo "<td>{$description}</td>";
                echo "<td><a class=\"import_wptable_link\" href=\"{$import_url}\">" . __( 'Import', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a></td>\n";
                echo "</tr>\n";

            }
            echo "</tbody>\n";
            echo "</table>\n";
        ?>
        <input type="hidden" name="action" value="bulk_edit" />
        <p class="submit"><?php _e( 'Bulk actions:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>  <input type="submit" name="submit[wp_table_import]" class="button-primary bulk_wp_table_import_tables" value="<?php _e( 'Import Tables', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </p>

        </form>
        <?
        } else { // end if $tables
            echo "<div style=\"clear:both;\"><p>" . __( 'wp-Table by Alex Rabe seems to be installed, but no tables were found.', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</p></div>";
        }
            ?>
        </div>
        <?php
        } else {
            // one of the wp-Table tables was not found in database, so nothing to show here
        }
        $this->print_page_footer();
    }

    // ###################################################################################################################
    function print_export_table_form( $table_id, $output = false ) {
        // Begin Export Table Form
        $table = $this->load_table( $table_id );

        $rows = count( $table['data'] );
        $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;

        $this->print_page_header( __( 'Export a Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'export' );
        ?>
        <div style="clear:both;">
        <p><?php _e( 'You may export a table here. Just select the table, your desired export format and a delimiter (needed for CSV only).<br/>You may opt to download the export file. Otherwise it will be shown on this page.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        <?php if( 0 < count( $this->tables ) ) { ?>
        <div style="clear:both;">
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'export' ) ); ?>
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><label for="table_id"><?php _e( 'Select Table to Export', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><select id="table_id" name="table_id">
        <?php
            foreach ( $this->tables as $id => $tableoptionname ) {
                // get name and description to show in list
                $table = $this->load_table( $id );
                    $name = $this->safe_output( $table['name'] );
                    //$description = $this->safe_output( $table['description'] );
                unset( $table );
                echo "<option" . ( ( $id == $table_id ) ? ' selected="selected"': '' ) . " value=\"{$id}\">{$name} (ID {$id})</option>";
            }
        ?>
        </select></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="export_format"><?php _e( 'Select Export Format', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><select id="export_format" name="export_format">
        <?php
            $export_formats = $this->export_instance->export_formats;
            foreach ( $export_formats as $export_format => $longname )
                echo "<option" . ( ( $export_format == $_POST['export_format'] ) ? ' selected="selected"': '' ) . " value=\"{$export_format}\">{$longname}</option>";
        ?>
        </select></td>
        </tr>
        <tr valign="top" class="tr-export-delimiter">
            <th scope="row"><label for="delimiter"><?php _e( 'Select Delimiter to use', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><select id="delimiter" name="delimiter">
        <?php
            $delimiters = $this->export_instance->delimiters;
            foreach ( $delimiters as $delimiter => $longname )
                echo "<option" . ( ( $delimiter == $_POST['delimiter'] ) ? ' selected="selected"': '' ) . " value=\"{$delimiter}\">{$longname}</option>";
        ?>
        </select> <?php _e( '<small>(Only needed for CSV export.)</small>', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Download file', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="wp_table_reloaded_download_export_file" id="wp_table_reloaded_download_export_file" value="true" /> <label for="wp_table_reloaded_download_export_file"><?php _e( 'Yes, I want to download the export file.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        </table>
        <input type="hidden" name="action" value="export" />
        <p class="submit">
        <input type="submit" name="submit" class="button-primary" value="<?php _e( 'Export Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </p>
        <?php if ( false != $output ) { ?>
        <textarea rows="15" cols="40" style="width:600px;height:300px;"><?php echo htmlspecialchars( $output ); ?></textarea>
        <?php } ?>
        </form>
        </div>
        <?php
        } else { // end if $tables
            $add_url = $this->get_action_url( array( 'action' => 'add' ), false );
            $import_url = $this->get_action_url( array( 'action' => 'import' ), false );
            echo "<div style=\"clear:both;\"><p>" . __( 'No tables found.', WP_TABLE_RELOADED_TEXTDOMAIN ) . '<br/>' . sprintf( __( 'You might <a href="%s">add</a> or <a href="%s">import</a> one!', WP_TABLE_RELOADED_TEXTDOMAIN ), $add_url, $import_url ) . "</p></div>";
        }

        $this->print_page_footer();
    }

    // ###################################################################################################################
    function print_plugin_options_form() {
        // Begin Add Table Form
        $this->print_page_header( __( 'General Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'options' );
        ?>
        <div style="clear:both;">
        <p><?php _e( 'You may change these global options.<br/>They will effect all tables or the general plugin behaviour.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>

        <div style="clear:both;">
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'options' ) ); ?>
        <div class="postbox">
<h3 class="hndle"><span><?php _e( 'Frontend Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
<div class="inside">
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'Enable Tablesorter-JavaScript?', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[enable_tablesorter]" id="options[enable_tablesorter]"<?php echo ( true == $this->options['enable_tablesorter'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options[enable_tablesorter]"><?php _e( 'Yes, enable the <a href="http://www.tablesorter.com/">Tablesorter jQuery plugin</a>. This can be used to make tables sortable (can be activated for each table separately in its options).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top" id="options_use_custom_css">
            <th scope="row"><?php _e( 'Add custom CSS?', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[use_custom_css]" id="options[use_custom_css]"<?php echo ( true == $this->options['use_custom_css'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options[use_custom_css]">
            <?php _e( 'Yes, include and load the following CSS-snippet on my site inside a [style]-HTML-tag.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
             </label></td>
        </tr>
        <tr valign="top">
            <th scope="row">&nbsp;</th>
            <td><label for="options_custom_css"><?php _e( 'Enter custom CSS', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label><br/>
            <textarea name="options[custom_css]" id="options_custom_css" rows="15" cols="40" style="width:600px;height:300px;"<?php echo ( false == $this->options['use_custom_css'] ) ? ' disabled="disabled"': '' ; ?>><?php echo $this->safe_output( $this->options[custom_css] ); ?></textarea><br/><br/>
            <?php echo sprintf( __( '(You might get a better website performance, if you add the CSS styling to your theme\'s "style.css" <small>(located at %s)</small>) instead.', WP_TABLE_RELOADED_TEXTDOMAIN ), get_stylesheet_uri() ); ?><br/>
            <?php echo sprintf( __( 'See the <a href="%s">plugin website</a> for styling examples or use one of the following: <a href="%s">Example Style 1</a> <a href="%s">Example Style 2</a>', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/', 'http://tobias.baethge.com/download/plugins/additional/example-style-1.css', 'http://tobias.baethge.com/download/plugins/additional/example-style-2.css' ); ?><br/><?php _e( 'Just copy the contents of a file into the textarea.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            </td>
        </tr>
        </table>
        </div>
        </div>
        
        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Admin Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <table class="wp-table-reloaded-options">
        <tr valign="top" id="options_uninstall">
            <th scope="row"><?php _e( 'Uninstall Plugin upon Deactivation?', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[uninstall_upon_deactivation]" id="options[uninstall_upon_deactivation]"<?php echo ( true == $this->options['uninstall_upon_deactivation'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options[uninstall_upon_deactivation]"><?php _e( 'Yes, uninstall everything when the plugin is deactivated. Attention: You should only enable this checkbox directly before deactivating the plugin from the WordPress plugins page!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( '<small>(This setting does not influence the "Manually Uninstall Plugin" button below!)</small>', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        </table>
        </div>
        </div>

        <input type="hidden" name="options[installed_version]" value="<?php echo $this->options['installed_version']; ?>" />
        <input type="hidden" name="options[last_id]" value="<?php echo $this->options['last_id']; ?>" />
        <input type="hidden" name="action" value="options" />
        <p class="submit">
        <input type="submit" name="submit[form]" class="button-primary" value="<?php _e( 'Save Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </p>

        </form>
        </div>
        
        <h2><?php _e( 'Manually Uninstall Plugin', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></h2>
        <div style="clear:both;">
            <p><?php _e( 'You may uninstall the plugin here. This <strong>will delete</strong> all tables, data, options, etc., that belong to the plugin, including all tables you added or imported.<br/> Be very careful with this and only click the button if you know what you are doing!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <?php
            $uninstall_url = $this->get_action_url( array( 'action' => 'uninstall' ), true );
            echo " <a class=\"button-secondary delete uninstall_plugin_link\" href=\"{$uninstall_url}\">" . __( 'Uninstall Plugin WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
        ?>
        </div>
        <br style="clear:both;" />
        <?php
        $this->print_page_footer();
    }

    // ###################################################################################################################
    function print_plugin_info_form() {
        // Begin Add Table Form
        $this->print_page_header( __( 'Information about the plugin', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'info' );
        ?>

        <div style="clear:both;">
        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Plugin Purpose', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php _e( 'This plugin allows you to create and manage tables in the admin-area of WordPress.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'Those tables may contain strings, numbers and even HTML (e.g. to include images or links).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You can then show the tables in your posts, on your pages or in text widgets by using a shortcode.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'If you want to show your tables anywhere else in your theme, you can use a template tag function.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Usage', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php _e( 'At first you should add or import a table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'This means that you either let the plugin create an empty table for you or that you load an existing table from either a CSV, XML or HTML file.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p><p><?php _e( 'Then you can edit your data or change the structure of your table (e.g. by inserting or deleting rows or columns, swaping rows or columns or sorting them) and select specific table options like alternating row colors or whether to print the name or description, if you want.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'To easily add a link or an image to a cell, use the provided buttons. Those will ask you for the URL and a title. Then you can click into a cell and the corresponding HTML will be added to it for you.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p><p><?php _e( 'To include the table into your posts, pages or text widgets, write the shortcode [table id=&lt;table-id&gt;] into them.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You can also select the desired table from a list (after clicking the button "Table" in the editor toolbar) and the corresponding shortcode will be added for you.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p><p><?php _e( 'You may also style your table via CSS. Example files are provided on the plugin website. Every table has the CSS class "wp-table-reloaded". Each table also has the class "wp-table-reloaded-&lt;table-id&gt;".', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You can also use the classes "column-&lt;number&gt;" and "row-&lt;number&gt;" to style rows and columns individually. Use this to style columns width and text alignment for example.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
        </p>
        </div>
        </div>
        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'More Information and Documentation', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php _e( 'More information can be found on the <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/">plugin\'s website</a> or on its page in the <a href="http://wordpress.org/extend/plugins/wp-table-reloaded/">WordPress Plugin Directory</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'See the <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/documentation/">documentation</a> or find out how to get <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/support/">support</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Author and Licence', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php _e( 'This plugin was written by <a href="http://tobias.baethge.com/">Tobias B&auml;thge</a>. It is licenced as Free Software under GPL 2.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'If you like the plugin, please consider <a href="http://tobias.baethge.com/wordpress-plugins/donate/"><strong>a donation</strong></a> and rate the plugin in the <a href="http://wordpress.org/extend/plugins/wp-table-reloaded/">WordPress Plugin Directory</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'Donations and good ratings encourage me to further develop the plugin and to provide countless hours of support. Any amount is appreciated! Thanks!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Credits and Thanks', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p>
            <?php _e( 'Thanks go to <a href="http://alexrabe.boelinger.com/">Alex Rabe</a> for the original wp-Table plugin,', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'Christian Bach for the <a href="http://www.tablesorter.com/">Tablesorter jQuery plugin</a>,', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'the submitters of translations:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Albanian (thanks to <a href="http://www.romeolab.com/">Romeo</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Czech (thanks to <a href="http://separatista.net/">Pavel</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'French (thanks to <a href="http://ultratrailer.net/">Yin-Yin</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Russian (thanks to <a href="http://wp-skins.info/">Truper</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Spanish (thanks to <a href="http://theindependentproject.com/">Alejandro Urrutia</a> and <a href="http://halles.cl/">Matias Halles</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Swedish (thanks to <a href="http://www.zuperzed.se/">ZuperZed</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Turkish (thanks to <a href="http://www.wpuzmani.com/">Semih</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/><?php _e( 'and to all donors, contributors, supporters, reviewers and users of the plugin!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
        </p>
        </div>
        </div>
        
        <div class="postbox closed">
        <h3 class="hndle"><span><?php _e( 'Debug and Version Information', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php _e( 'Hide', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p>
            <?php _e( 'You are using the following versions of the software. <strong>Please provide this information in bug reports.</strong>', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <br/>&middot; WP-Table Reloaded (DB): <?php echo $this->options['installed_version']; ?>
            <br/>&middot; WP-Table Reloaded (Script): <?php echo $this->plugin_version; ?>
            <br/>&middot; WordPress: <?php echo $GLOBALS['wp_version']; ?>
            <br/>&middot; PHP: <?php echo phpversion(); ?>
            <br/>&middot; mySQL (Server): <?php echo mysql_get_server_info(); ?>
            <br/>&middot; mySQL (Client): <?php echo mysql_get_client_info(); ?>
        </p>
        </div>
        </div>

        </div>
        <?php
        $this->print_page_footer();
    }

    // ###################################################################################################################
    // #########################################                      ####################################################
    // #########################################     Print Support    ####################################################
    // #########################################                      ####################################################
    // ###################################################################################################################

    // ###################################################################################################################
    function print_success_message( $text ) {
        echo "<div id='message' class='updated fade'><p><strong>{$text}</strong></p></div>";
    }

    // ###################################################################################################################
    function print_page_header( $text = 'WP-Table Reloaded' ) {
        echo <<<TEXT
<div class='wrap'>
<h2>{$text}</h2>
<div id='poststuff'>
TEXT;
    }

    // ###################################################################################################################
    function print_page_footer() {
        echo "</div></div>";
    }

    // ###################################################################################################################
    function print_submenu_navigation( $action ) {
        ?>
        <ul class="subsubsub">
            <li><a <?php if ( 'list' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'list' ) ); ?>"><?php _e( 'List Tables', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a> | </li>
            <li><a <?php if ( 'add' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'add' ) ); ?>"><?php _e( 'Add new Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a> | </li>
            <li><a <?php if ( 'import' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'import' ) ); ?>"><?php _e( 'Import a Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a> | </li>
            <li><a <?php if ( 'export' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'export' ) ); ?>"><?php _e( 'Export a Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a></li>
            <li>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</li>
            <li><a <?php if ( 'options' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'options' ) ); ?>"><?php _e( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a> | </li>
            <li><a <?php if ( 'info' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'info' ) ); ?>"><?php _e( 'About the Plugin', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a></li>
        </ul>
        <br class="clear" />
        <?php
    }

    // ###################################################################################################################
    function get_contextual_help_string() {
        return __( 'More information can be found on the <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/">plugin\'s website</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ) . '<br/>' . __( 'See the <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/documentation/">documentation</a> or find out how to get <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/support/">support</a>.', WP_TABLE_RELOADED_TEXTDOMAIN );
    }

    // ###################################################################################################################
    function safe_output( $string ) {
        return htmlspecialchars( stripslashes( $string ) );
    }

    // ###################################################################################################################
    // #########################################                      ####################################################
    // #########################################     Options Funcs    ####################################################
    // #########################################                      ####################################################
    // ###################################################################################################################

    // ###################################################################################################################
    function get_new_table_id() {
        $this->options['last_id'] = $this->options['last_id'] + 1;
        $this->update_options();
        return $this->options['last_id'];
    }
    
    // ###################################################################################################################
    // create new two-dimensional array with $num_rows rows and $num_cols columns, each cell filled with $default_cell_content
    function create_empty_table( $num_rows = 1, $num_cols = 1, $default_cell_content = '' ) {
        return array_fill( 0, $num_rows, array_fill( 0, $num_cols, $default_cell_content ) );
    }


    // ###################################################################################################################
    function update_options() {
        update_option( $this->optionname['options'], $this->options );
    }

    // ###################################################################################################################
    function update_tables() {
        update_option( $this->optionname['tables'], $this->tables );
    }

    // ###################################################################################################################
    function save_table( $table ) {
        if ( 0 < $table['id'] ) {
            $this->tables[ $table['id'] ] = ( isset( $this->tables[ $table['id'] ] ) ) ? $this->tables[ $table['id'] ] : $this->optionname['table'] . '_' . $table['id'];
            update_option( $this->tables[ $table['id'] ], $table );
            $this->update_tables();
        }
    }

    // ###################################################################################################################
    function load_table( $table_id ) {
        if ( 0 < $table_id ) {
            $this->tables[ $table_id ] = ( isset( $this->tables[ $table_id ] ) ) ? $this->tables[ $table_id ] : $this->optionname['table'] . '_' . $table_id;
            $table = get_option( $this->tables[ $table_id ], $this->default_table);
            return $table;
        } else {
            return $this->default_table;
        }
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
    // #########################################                      ####################################################
    // #########################################      URL Support     ####################################################
    // #########################################                      ####################################################
    // ###################################################################################################################
    
    // ###################################################################################################################
    function get_nonce( $action, $item = false ) {
        return ( false !== $item ) ? $this->nonce_base . '_' . $action . '_' . $item : $this->nonce_base . '_' . $action;
    }

    // ###################################################################################################################
    function get_action_url( $params = array(), $add_nonce = false ) {
        $default_params = array(
                'page' => $_REQUEST['page'],
                'action' => false,
                'item' => false
        );
        $url_params = array_merge( $default_params, $params );

        $action_url = add_query_arg( $url_params, $_SERVER['PHP_SELF'] );
        $action_url = ( true == $add_nonce ) ? wp_nonce_url( $action_url, $this->get_nonce( $url_params['action'], $url_params['item'] ) ) : $action_url;
        return $action_url;
    }
    
    // ###################################################################################################################
    // #######################################                         ###################################################
    // #######################################    Plugin Management    ###################################################
    // #######################################                         ###################################################
    // ###################################################################################################################

    // ###################################################################################################################
    function create_class_instance( $class, $file ) {
        if ( !class_exists( $class ) ) {
            include_once ( WP_TABLE_RELOADED_ABSPATH . 'php/' . $file );
            if ( class_exists( $class ) )
                return new $class;
        }
    }

    // ###################################################################################################################
    function init_plugin() {
        // load options and table information from database, if not available: default
		$this->options = get_option( $this->optionname['options'] );
		$this->tables = get_option( $this->optionname['tables'] );
        if ( false === $this->options || false === $this->tables )
            $this->plugin_install();
    }

    // ###################################################################################################################
    function plugin_activation_hook() {
        $this->options = get_option( $this->optionname['options'] );
        if ( false !== $this->options && isset( $this->options['installed_version'] ) ) {
            // check if update needed, or just reactivated the latest version of it
            if ( version_compare( $this->options['installed_version'], $this->plugin_version, '<') ) {
                $this->plugin_update();
            } else {
                // just reactivating, but latest version of plugin installed
            }
        } else {
            // plugin has never been installed before
            $this->plugin_install();
        }
    }

    // ###################################################################################################################
    function plugin_deactivation_hook() {
        $this->options = get_option( $this->optionname['options'] );
   		$this->tables = get_option( $this->optionname['tables'] );
        if ( false !== $this->options && isset( $this->options['uninstall_upon_deactivation'] ) ) {
            if ( true == $this->options['uninstall_upon_deactivation'] ) {
                // delete all options and tables
                foreach ( $this->tables as $id => $tableoptionname )
                    delete_option( $tableoptionname );
                delete_option( $this->optionname['tables'] );
                delete_option( $this->optionname['options'] );
            }
        }
    }

    // ###################################################################################################################
    function plugin_install() {
        $this->options = $this->default_options;
        $this->options['installed_version'] = $this->plugin_version;
        $this->update_options();
        $this->tables = $this->default_tables;
        $this->update_tables();
    }

    // ###################################################################################################################
    function plugin_update() {
        // update general plugin options
        // 1. step: by adding/overwriting existing options
		$this->options = get_option( $this->optionname['options'] );
		$new_options = array();

        // 2a. step: add/delete new/deprecated options by overwriting new ones with existing ones, if existant
		foreach ( $this->default_options as $key => $value )
            $new_options[ $key ] = ( true == isset( $this->options[ $key ] ) ) ? $this->options[ $key ] : $this->default_options[ $key ] ;

        // 2b., take care of css
        $new_options['use_custom_css'] = ( false == isset( $this->options['use_custom_css'] ) && true == isset( $this->options['use_global_css'] ) ) ? $this->options['use_global_css'] : $this->options['use_custom_css'];

        // 3. step: update installed version number
        $new_options['installed_version'] = $this->plugin_version;

        // 4. step: save the new options
        $this->options = $new_options;
        $this->update_options();

        // update individual tables and their options
		$this->tables = get_option( $this->optionname['tables'] );
        foreach ( $this->tables as $id => $tableoptionname ) {
            $table = $this->load_table( $id );
            
            foreach ( $this->default_table as $key => $value )
                $new_table[ $key ] = ( true == isset( $table[ $key ] ) ) ? $table[ $key ] : $this->default_table[ $key ] ;

            foreach ( $this->default_table['options'] as $key => $value )
                $new_table['options'][ $key ] = ( true == isset( $table['options'][ $key ] ) ) ? $table['options'][ $key ] : $this->default_table['options'][ $key ] ;

            $this->save_table( $new_table );
        }
    }

    // ###################################################################################################################
    // initialize i18n support, load textdomain
    function init_language_support() {
        $language_directory = basename( dirname( __FILE__ ) ) . '/languages';
        load_plugin_textdomain( WP_TABLE_RELOADED_TEXTDOMAIN, 'wp-content/plugins/' . $language_directory, $language_directory );
    }

    // ###################################################################################################################
    // enqueue javascript-file, with some jQuery stuff
    function add_manage_page_js() {
        $jsfile = 'admin-script.js';
        if ( file_exists( WP_TABLE_RELOADED_ABSPATH . 'admin/' . $jsfile ) ) {
            wp_register_script( 'wp-table-reloaded-admin-js', WP_TABLE_RELOADED_URL . 'admin/' . $jsfile, array( 'jquery' ) );
            // add all strings to translate here
            wp_localize_script( 'wp-table-reloaded-admin-js', 'WP_Table_Reloaded_Admin', array(
	  	        'str_UninstallCheckboxActivation' => __( 'Do you really want to activate this? You should only do that right before uninstallation!', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationLinkInsertURL' => __( 'URL of link to insert', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationLinkInsertText' => __( 'Text of link', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationLinkInsertExplain' => __( 'To insert the following link into a cell, just click the cell after closing this dialog.', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationImageInsertURL' => __( 'URL of image to insert', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationImageInsertAlt' => __( "''alt'' text of the image", WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationImageInsertExplain' => __( 'To insert the following image into a cell, just click the cell after closing this dialog.', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_BulkCopyTablesLink' => __( 'Do you want to copy the selected tables?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_BulkDeleteTablesLink' => __( 'The selected tables and all content will be erased. Do you really want to delete them?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_BulkImportwpTableTablesLink' => __( 'Do you really want to import the selected tables from the wp-Table plugin?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_CopyTableLink' => __( 'Do you want to copy this table?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteTableLink' => __( 'The complete table and all content will be erased. Do you really want to delete it?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteRowLink' => __( 'Do you really want to delete this row?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteColumnLink' => __( 'Do you really want to delete this column?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_ImportwpTableLink' => __( 'Do you really want to import this table from the wp-Table plugin?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_UninstallPluginLink_1' => __( 'Do you really want to uninstall the plugin and delete ALL data?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_UninstallPluginLink_2' => __( 'Are you really sure?', WP_TABLE_RELOADED_TEXTDOMAIN )
            ) );
            wp_print_scripts( 'wp-table-reloaded-admin-js' );
        }
    }

    // ###################################################################################################################
    // enqueue css-stylesheet-file for admin, if it exists
    function add_manage_page_css() {
        $cssfile = 'admin-style.css';
        if ( file_exists( WP_TABLE_RELOADED_ABSPATH . 'admin/' . $cssfile ) ) {
            if ( function_exists( 'wp_enqueue_style' ) )
                wp_enqueue_style( 'wp-table-reloaded-admin-css', WP_TABLE_RELOADED_URL . 'admin/' . $cssfile );
            else
                add_action( 'admin_head', array( &$this, 'print_admin_style' ) );
        }
    }

    // ###################################################################################################################
    // print our style in wp-admin-head (only needed for WP < 2.6)
    function print_admin_style() {
        $cssfile = 'admin-style.css';
        echo "<link rel='stylesheet' href='" . WP_TABLE_RELOADED_URL . 'admin/' . $cssfile . "' type='text/css' media='' />\n";
    }

    // ###################################################################################################################
    // add button to visual editor
    function add_editor_button() {
        if ( 0 < count( $this->tables ) ) {
            $this->init_language_support();
            add_action( 'admin_footer', array( &$this, 'add_editor_button_js' ) );
        }
    }

    // ###################################################################################################################
    // print out the JS in the admin footer
    function add_editor_button_js() {
        $params = array(
                'page' => 'wp_table_reloaded_manage_page',
                'action' => 'ajax_list'
        );
        $ajax_url = add_query_arg( $params, dirname( $_SERVER['PHP_SELF'] ) . '/tools.php' );
        $ajax_url = wp_nonce_url( $ajax_url, $this->get_nonce( $params['action'], false ) );

        $jsfile = 'admin-editor-buttons-script.js';
        if ( file_exists( WP_TABLE_RELOADED_ABSPATH . 'admin/' . $jsfile ) ) {
            wp_register_script( 'wp-table-reloaded-admin-editor-buttons-js', WP_TABLE_RELOADED_URL . 'admin/' . $jsfile, array( 'jquery', 'thickbox' ) );
            // add all strings to translate here
            wp_localize_script( 'wp-table-reloaded-admin-editor-buttons-js', 'WP_Table_Reloaded_Admin', array(
	  	        'str_EditorButtonCaption' => __( 'Table', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_EditorButtonAjaxURL' => $ajax_url
            ) );
            wp_print_scripts( 'wp-table-reloaded-admin-editor-buttons-js' );
        }
    }

} // class WP_Table_Reloaded_Admin

?>