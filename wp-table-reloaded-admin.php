<?php
/*
File Name: WP-Table Reloaded - Admin Class (see main file wp-table-reloaded.php)
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: Description: This plugin allows you to create and easily manage tables in the admin-area of WordPress. A comfortable backend allows an easy manipulation of table data. You can then include the tables into your posts, on your pages or in text widgets by using a shortcode or a template tag function. Tables can be imported and exported from/to CSV, XML and HTML.
Version: 1.4-beta2
Author: Tobias B&auml;thge
Author URI: http://tobias.baethge.com/
Donate URI: http://tobias.baethge.com/donate/
*/

define( 'WP_TABLE_RELOADED_TEXTDOMAIN', 'wp-table-reloaded' );

class WP_Table_Reloaded_Admin {

    // ###################################################################################################################
    var $plugin_version = '1.4-beta2';
    // nonce for security of links/forms, try to prevent "CSRF"
    var $nonce_base = 'wp-table-reloaded-nonce';
    var $page_slug = 'wp_table_reloaded';
    // names for the options which are stored in the WP database
    var $optionname = array(
        'tables' => 'wp_table_reloaded_tables',
        'options' => 'wp_table_reloaded_options',
        'table' => 'wp_table_reloaded_data'
    );
    // allowed actions in this class
    var $allowed_actions = array( 'list', 'add', 'edit', 'bulk_edit', 'copy', 'delete', 'insert', 'import', 'export', 'options', 'uninstall', 'info', 'hide_donate_nag' ); // 'ajax_list', 'ajax_preview', but handled separatly
    // current action, populated in load_manage_page
    var $action = 'list';
    
    // init vars
    var $tables = array();
    var $options = array();

    // default values, could be different in future plugin versions
    var $default_options = array(
        'installed_version' => '0',
        'uninstall_upon_deactivation' => false,
        'enable_tablesorter' => true,
        'use_tablesorter_extended' => false,
        'use_custom_css' => true,
        'custom_css' => '.wp-table-reloaded {width:100%;}',
        'install_time' => 0,
        'show_donate_nag' => true,
        'update_message' => array(),
        'last_id' => 0
    );
    var $default_tables = array();
    var $default_table = array(
        'id' => 0,
        'data' => array( 0 => array( 0 => '' ) ),
        'name' => '',
        'description' => '',
        'last_modified' => '0000-00-00 00:00:00',
        'last_editor_id' => '',
        'visibility' => array(
            'rows' => array(),
            'columns' => array()
        ),
        'options' => array(
            'alternating_row_colors' => true,
            'first_row_th' => true,
            'print_name' => false,
            'print_description' => false,
            'use_tablesorter' => true
        ),
        'custom_fields' => array()
    );
    
    // class instances
    var $export_instance;
    var $import_instance;
    
    // temporary variables
    var $hook = '';

    // ###################################################################################################################
    // add admin-page to sidebar navigation, function called by PHP when class is constructed
    function WP_Table_Reloaded_Admin() {
        // init plugin (means: load plugin options and existing tables)
        $this->init_plugin();

        // init variables to check whether we do valid AJAX
        $doing_ajax = false;
        $valid_ajax_call = ( isset( $_GET['page'] ) && $this->page_slug == $_GET['page'] ) ? true : false;

        // have to check for possible export file download request this early,
        // because otherwise http-headers will be sent by WP before we can send download headers
        if ( $valid_ajax_call && isset( $_POST['download_export_file'] ) && 'true' == $_POST['download_export_file'] ) {
            // can be done in plugins_loaded, as no language support is needed
            add_action( 'plugins_loaded', array( &$this, 'do_action_export' ) );
            $doing_ajax = true;
        }
        // have to check for possible call by editor button to show list of tables
        // and possible call to show Table preview in a thickbox on "List tables" screen
        if ( !$doing_ajax && $valid_ajax_call && isset( $_GET['action'] ) && ( 'ajax_list' == $_GET['action'] || 'ajax_preview' == $_GET['action'] ) ) {
            // can not be done earlier, because we need language support
            add_action( 'init', array( &$this, 'do_action_' . $_GET['action'] ) );
            $doing_ajax = true;
        }

        // if we are not doing AJAX, we call the main plugin handler
        if ( !$doing_ajax ) {
            add_action( 'admin_menu', array( &$this, 'add_manage_page' ) );

            // add JS to add button to editor on these pages
            $pages_with_editor_button = array( 'post.php', 'post-new.php', 'page.php', 'page-new.php' );
            foreach ( $pages_with_editor_button as $page )
                add_action( 'load-' . $page, array( &$this, 'add_editor_button' ) );

            // add remote message, if update available
            add_action( 'in_plugin_update_message-' . WP_TABLE_RELOADED_BASENAME, array( &$this, 'plugin_update_message' ), 10, 2 );
        }
    }

    // ###################################################################################################################
    // add page, and what happens when page is loaded or shown
    function add_manage_page() {
        $min_needed_capability = 'publish_posts'; // user needs at least this capability to view WP-Table Reloaded config page
        $this->hook = add_management_page( 'WP-Table Reloaded', 'WP-Table Reloaded', $min_needed_capability, $this->page_slug, array( &$this, 'show_manage_page' ) );
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

        // show admin footer message (only on pages of WP-Table Reloaded)
		add_filter( 'admin_footer_text', array (&$this, 'add_admin_footer_text') );

        // init language support
        $this->init_language_support();

        // get and check action parameter from passed variables
        $action = ( isset( $_REQUEST['action'] ) && !empty( $_REQUEST['action'] ) ) ? $_REQUEST['action'] : 'list';
        // check if action is in allowed actions and if method is callable, if yes, call it
        if ( in_array( $action, $this->allowed_actions ) )
            $this->action = $action;

        // need thickbox to be able to show table in iframe on certain action pages (but not all)
        $thickbox_actions = array ( 'list', 'edit', 'copy', 'delete', 'bulk_edit', 'hide_donate_nag' ); // those all show the "List of tables"
        if ( in_array( $action, $thickbox_actions ) ) {
            add_thickbox();
            wp_enqueue_script( 'media-upload' ); // for resizing the thickbox
        }

        // after get_action, because needs action parameter
        if ( function_exists( 'add_contextual_help' ) ) // then WP version is >= 2.7
            add_contextual_help( $this->hook, $this->get_contextual_help_string( $this->action ) );
    }

    // ###################################################################################################################
    function show_manage_page() {
        // call approriate action, $this->action is populated in load_manage_page
        if( is_callable( array( &$this, 'do_action_' . $this->action ) ) )
            call_user_func( array( &$this, 'do_action_' . $this->action ) );
    }
    
    // ###################################################################################################################
    // ##########################################                   ######################################################
    // ##########################################      ACTIONS      ######################################################
    // ##########################################                   ######################################################
    // ###################################################################################################################

    // ###################################################################################################################
    // list all tables
    function do_action_list() {
        if ( true == $this->may_print_donate_nag() ) {
            $donate_url = 'http://tobias.baethge.com/donate-message/';
            $donated_true_url = $this->get_action_url( array( 'action' => 'hide_donate_nag', 'user_donated' => true ), true );
            $donated_false_url = $this->get_action_url( array( 'action' => 'hide_donate_nag', 'user_donated' => false ), true );
            $this->print_header_message(
                sprintf( __( 'Thanks for using this plugin! You\'ve installed WP-Table Reloaded over a month ago. If it works and you are satisfied with the results of managing your %s tables, isn\'t it worth at least one dollar or euro?', WP_TABLE_RELOADED_TEXTDOMAIN ), count( $this->tables ) ) . '<br/><br/>' .
                sprintf( __( '<a href="%s">Donations</a> help me to continue support and development of this <i>free</i> software - things for which I spend countless hours of my free time! Thank you!', WP_TABLE_RELOADED_TEXTDOMAIN ), $donate_url ) . '<br/><br/>' .
                sprintf( '<a href="%s" target="_blank">%s</a>', $donate_url, __( 'Sure, no problem!', WP_TABLE_RELOADED_TEXTDOMAIN ) ) . '&nbsp;&nbsp;&middot;&nbsp;&nbsp;' .
                sprintf( '<a href="%s" style="font-weight:normal;">%s</a>', $donated_true_url, __( 'I already donated.', WP_TABLE_RELOADED_TEXTDOMAIN ) ) . '&nbsp;&nbsp;&middot;&nbsp;&nbsp;' .
                sprintf( '<a href="%s" style="font-weight:normal;">%s</a>', $donated_false_url, __( 'No, thanks. Don\'t ask again.', WP_TABLE_RELOADED_TEXTDOMAIN ) )
            );
        }
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
            $table['visibility']['rows'] = array_fill( 0, $rows, false );
            $table['visibility']['columns'] = array_fill( 0, $cols, false );
            $table['name'] = $_POST['table']['name'];
            $table['description'] = $_POST['table']['description'];

            $this->save_table( $table );

            $this->print_header_message( sprintf( __( 'Table "%s" added successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table['name'] ) ) );
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
                // do we want to change the ID?
                $new_table_id = ( isset( $_POST['table_id'] ) ) ? $_POST['table_id'] : $table['id'] ;
                if ( $new_table_id != $table['id'] && is_numeric( $new_table_id ) && ( 0 < $new_table_id ) ) {
                    if ( false === $this->table_exists( $new_table_id ) ) {
                        // delete table with old ID
                        $old_table_id = $table['id'];
                        $this->delete_table( $old_table_id );
                        // set new table ID
                        $table['id'] = $new_table_id;
                        $message = sprintf( __( "Table edited successfully. This Table now has the ID %s. You'll need to adjust existing shortcodes accordingly.", WP_TABLE_RELOADED_TEXTDOMAIN ), $new_table_id );
                    } else {
                        $message = sprintf( __( 'The ID could not be changed from %s to %s, because there already is a Table with that ID.', WP_TABLE_RELOADED_TEXTDOMAIN ), $table['id'], $new_table_id );
                    }
                } else {
                    $message = __( 'Table edited successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                }
                // save table options (checkboxes!)
                $table['options']['alternating_row_colors'] = isset( $_POST['table']['options']['alternating_row_colors'] );
                $table['options']['first_row_th'] = isset( $_POST['table']['options']['first_row_th'] );
                $table['options']['print_name'] = isset( $_POST['table']['options']['print_name'] );
                $table['options']['print_description'] = isset( $_POST['table']['options']['print_description'] );
                $table['options']['use_tablesorter'] = isset( $_POST['table']['options']['use_tablesorter'] );

                // save visibility settings (checkboxes!)
                foreach ( $table['data'] as $row_idx => $row )
                    $table['visibility']['rows'][$row_idx] = isset( $_POST['table']['visibility']['rows'][$row_idx] );
                ksort( $table['visibility']['rows'], SORT_NUMERIC );
                foreach ( $table['data'][0] as $col_idx => $col )
                    $table['visibility']['columns'][$col_idx] = isset( $_POST['table']['visibility']['columns'][$col_idx] );
                ksort( $table['visibility']['columns'], SORT_NUMERIC );

                if ( isset( $table['custom_fields'] ) && !empty( $table['custom_fields'] ) )
                    uksort( $table['custom_fields'], 'strnatcasecmp' ); // sort the keys naturally

                $this->save_table( $table );
                break;
            case 'swap_rows':
                $table_id = $_POST['table']['id'];
                $row_id1 = ( isset( $_POST['swap']['row'][1] ) ) ? $_POST['swap']['row'][1] : -1;
                $row_id2 = ( isset( $_POST['swap']['row'][2] ) ) ? $_POST['swap']['row'][2] : -1;
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                // swap rows $row_id1 and $row_id2
                if ( ( 1 < $rows ) && ( -1 < $row_id1 ) && ( -1 < $row_id2 ) && ( $row_id1 != $row_id2 ) ) {
                    $temp_row = $table['data'][$row_id1];
                    $table['data'][$row_id1] = $table['data'][$row_id2];
                    $table['data'][$row_id2] = $temp_row;
                    $temp_visibility = $table['visibility']['rows'][$row_id1];
                    $table['visibility']['rows'][$row_id1] = $table['visibility']['rows'][$row_id2];
                    $table['visibility']['rows'][$row_id2] = $temp_visibility;
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
                if ( ( 1 < $cols ) && ( -1 < $col_id1 ) && ( -1 < $col_id2 ) && ( $col_id1 != $col_id2 )) {
                    foreach ( $table['data'] as $row_idx => $row ) {
                        $temp_col = $table['data'][$row_idx][$col_id1];
                        $table['data'][$row_idx][$col_id1] = $table['data'][$row_idx][$col_id2];
                        $table['data'][$row_idx][$col_id2] = $temp_col;
                    }
                    $temp_visibility = $table['visibility']['columns'][$col_id1];
                    $table['visibility']['columns'][$col_id1] = $table['visibility']['columns'][$col_id2];
                    $table['visibility']['columns'][$col_id2] = $temp_visibility;
                }
                $this->save_table( $table );
                $message = __( 'Columns swapped successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'sort':
                $table_id = $_POST['table']['id'];
                $column = ( isset( $_POST['sort']['col'] ) ) ? $_POST['sort']['col'] : -1;
                $sort_order = ( isset( $_POST['sort']['order'] ) ) ? $_POST['sort']['order'] : 'ASC';
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
                // sort array for $column in $sort_order
                if ( ( 1 < $rows ) && ( -1 < $column ) ) {

                    // for sorting: temporarily store row visibility in data, so that it gets sorted, too
                    foreach ( $table['data'] as $row_idx => $row )
                        array_splice( $table['data'][$row_idx], $cols, 0, $table['visibility']['rows'][$row_idx] );

                    $sortarray = $this->create_class_instance( 'arraysort', 'arraysort.class.php' );
                    $sortarray->input_array = $table['data'];
                    $sortarray->column = $column;
                    $sortarray->order = $sort_order;
                    $sortarray->sort();
                    $table['data'] = $sortarray->sorted_array;

                    // then restore row visibility from sorted data and remove temporary column
                    foreach ( $table['data'] as $row_idx => $row ) {
                        $table['visibility']['rows'][$row_idx] = $table['data'][$row_idx][$cols];
                        array_splice( $table['data'][$row_idx], $cols, 1 );
                    }

                }
                $this->save_table( $table );
                $message = __( 'Table sorted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'insert_rows':
                $table_id = $_POST['table']['id'];
                $number = ( isset( $_POST['insert']['row']['number'] ) && ( 0 < $_POST['insert']['row']['number'] ) ) ? $_POST['insert']['row']['number'] : 1;
                $row_id = $_POST['insert']['row']['id'];
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
                // init new empty row (with all columns) and insert it before row with key $row_id
                $new_rows = $this->create_empty_table( $number, $cols, '' );
                $new_rows_visibility = array_fill( 0, $number, false );
                array_splice( $table['data'], $row_id, 0, $new_rows );
                array_splice( $table['visibility']['rows'], $row_id, 0, $new_rows_visibility );
                $this->save_table( $table );
                $message = __ngettext( 'Row added successfully.', 'Rows added successfully.', $number, WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'insert_cols':
                $table_id = $_POST['table']['id'];
                $number = ( isset( $_POST['insert']['col']['number'] ) && ( 0 < $_POST['insert']['col']['number'] ) ) ? $_POST['insert']['col']['number'] : 1;
                $col_id = $_POST['insert']['col']['id'];
                $table = $this->load_table( $table_id );
                // init new empty row (with all columns) and insert it before row with key $col_id
                $new_cols = array_fill( 0, $number, '' );
                $new_cols_visibility = array_fill( 0, $number, false );
                foreach ( $table['data'] as $row_idx => $row )
                    array_splice( $table['data'][$row_idx], $col_id, 0, $new_cols );
                array_splice( $table['visibility']['columns'], $col_id, 0, $new_cols_visibility );
                $this->save_table( $table );
                $message = __ngettext( 'Column added successfully.', 'Columns added successfully.', $number, WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'move_row':
                $table_id = $_POST['table']['id'];
                $row_id1 = ( isset( $_POST['move']['row'][1] ) ) ? $_POST['move']['row'][1] : -1;
                $row_id2 = ( isset( $_POST['move']['row'][2] ) ) ? $_POST['move']['row'][2] : -1;
                $move_where = ( isset( $_POST['move']['where'] ) ) ? $_POST['move']['where'] : 'before';
                if ( 'after' == $move_where )
                    $row_id2 = $row_id2 + 1; // move after is the same as move before the next row
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                // move row $row_id1 before/after $row_id2
                if ( ( 1 < $rows ) && ( -1 < $row_id1 ) && ( -1 < $row_id2 ) && ( $row_id1 != $row_id2 ) ) {
                    $temp_row = array( $table['data'][$row_id1] );
                    unset( $table['data'][$row_id1] );
                    array_splice( $table['data'], $row_id2, 0, $temp_row );
                    $temp_visibility = $table['visibility']['rows'][$row_id1];
                    unset( $table['visibility']['rows'][$row_id1] );
                    array_splice( $table['visibility']['rows'], $row_id2, 0, $temp_visibility );
                }
                $this->save_table( $table );
                $message = __( 'Row moved successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'move_col':
                $table_id = $_POST['table']['id'];
                $col_id1 = ( isset( $_POST['move']['col'][1] ) ) ? $_POST['move']['col'][1] : -1;
                $col_id2 = ( isset( $_POST['move']['col'][2] ) ) ? $_POST['move']['col'][2] : -1;
                $move_where = ( isset( $_POST['move']['where'] ) ) ? $_POST['move']['where'] : 'before';
                if ( 'after' == $move_where )
                    $col_id2 = $col_id2 + 1; // move after is the same as move before the next row
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
                // move col $col_id1 before/after $col_id2
                if ( ( 1 < $cols ) && ( -1 < $col_id1 ) && ( -1 < $col_id2 ) && ( $col_id1 != $col_id2 ) ) {
                    foreach ( $table['data'] as $row_idx => $row ) {
                        $temp_col = $table['data'][$row_idx][$col_id1];
                        unset( $table['data'][$row_idx][$col_id1] );
                        array_splice( $table['data'][$row_idx], $col_id2, 0, $temp_col );

                    }
                    $temp_visibility = $table['visibility']['columns'][$col_id1];
                    unset( $table['visibility']['columns'][$col_id1] );
                    array_splice( $table['visibility']['columns'], $col_id2, 0, $temp_visibility );
                }
                $this->save_table( $table );
                $message = __( 'Column moved successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'insert_cf':
                $table_id = $_POST['table']['id'];
                $table = $this->load_table( $table_id );
                $name = ( isset( $_POST['insert']['custom_field'] ) ) ? $_POST['insert']['custom_field'] : '';
                if ( empty( $name ) ) {
                    $message = __( 'Could not add Custom Data Field, because you did not enter a name.', WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                }
                $reserved_names = array( 'name', 'description', 'last_modified', 'last_editor' );
                if ( in_array( $name, $reserved_names ) ) {
                    $message = __( 'Could not add Custom Data Field, because the name you entered is reserved for other table data.', WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                }
                // Name can only contain lowercase letters, numbers, _ and - (like permalink slugs)
                $clean_name = sanitize_title_with_dashes( $name );
                if ( $name != $clean_name ) {
                    $message = __( 'Could not add Custom Data Field, because the name contained illegal characters.', WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                }
                if ( isset( $table['custom_fields'][$name] ) ) {
                    $message = __( 'Could not add Custom Data Field, because a Field with that name already exists.', WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                }
                $table['custom_fields'][$name] = '';
                uksort( $table['custom_fields'], 'strnatcasecmp' ); // sort the keys naturally
                $this->save_table( $table );
                $message = __( 'Custom Data Field added successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            default:
                $this->do_action_list();
                return;
            }

            $this->print_header_message( $message );
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
                        $this->delete_table( $table_id );
                    }
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
                        
                        $rows = count( $table['data'] );
                        $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
                        $rows = ( 0 < $rows ) ? $rows : 1;
                        $cols = ( 0 < $cols ) ? $cols : 1;
                        $table['visibility']['rows'] = array_fill( 0, $rows, false );
                        $table['visibility']['columns'] = array_fill( 0, $cols, false );
                        
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
            $this->print_header_message( $message );
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

            $this->print_header_message( sprintf( __( 'Table "%s" copied successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $new_table['name'] ) ) );
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
                $this->delete_table( $table_id );
                $this->print_header_message( sprintf( __( 'Table "%s" deleted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table['name'] ) ) );
                $this->do_action_list();
                break;
            case 'row':
                $row_id = ( isset( $_GET['element_id'] ) ) ? $_GET['element_id'] : -1;
                $rows = count( $table['data'] );
                // delete row with key $row_id, if there are at least 2 rows
                if ( ( 1 < $rows ) && ( -1 < $row_id ) ) {
                    array_splice( $table['data'], $row_id, 1 );
                    array_splice( $table['visibility']['rows'], $row_id, 1 );
                    $this->save_table( $table );
                    $this->print_header_message( __( 'Row deleted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
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
                    array_splice( $table['visibility']['columns'], $col_id, 1 );
                    $this->save_table( $table );
                    $this->print_header_message( __( 'Column deleted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                }
                $this->print_edit_table_form( $table_id );
                break;
            case 'custom_field':
                $name = ( isset( $_GET['element_id'] ) ) ? $_GET['element_id'] : '';
                if ( !empty( $name ) && isset( $table['custom_fields'][$name] ) ) {
                    unset( $table['custom_fields'][$name] );
                    $this->save_table( $table );
                    $message = __( 'Custom Data Field deleted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                } else {
                    $message = __( 'Custom Data Field could not be deleted.', WP_TABLE_RELOADED_TEXTDOMAIN );
                }
                $this->print_header_message( $message );
                $this->print_edit_table_form( $table_id );
                break;
            default:
                $this->print_header_message( __( 'Delete failed.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
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
                array_splice( $table['visibility']['rows'], $row_id, 0, false );
                $this->save_table( $table );
                $message = __( 'Row inserted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'col':
                $col_id = $_GET['element_id'];
                // init new empty row (with all columns) and insert it before row with key $row_id
                $new_col = '';
                foreach ( $table['data'] as $row_idx => $row )
                    array_splice( $table['data'][$row_idx], $col_id, 0, $new_col );
                array_splice( $table['visibility']['columns'], $col_id, 0, false );
                $this->save_table( $table );
                $message = __( 'Column inserted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            default:
                $message = __( 'Insert failed.', WP_TABLE_RELOADED_TEXTDOMAIN );
            }
            $this->print_header_message( $message );
            $this->print_edit_table_form( $table_id );
        } else {
            $this->do_action_list();
        }
    }

    // ###################################################################################################################
    function do_action_import() {
        $this->import_instance = $this->create_class_instance( 'WP_Table_Reloaded_Import', 'wp-table-reloaded-import.class.php' );
        if ( isset( $_POST['submit'] ) && isset( $_POST['import_from'] ) ) {
            check_admin_referer( $this->get_nonce( 'import' ) );

            // do import
            if ( 'file-upload' == $_POST['import_from'] && false === empty( $_FILES['import_file']['tmp_name'] ) ) {
                $this->import_instance->tempname = $_FILES['import_file']['tmp_name'];
                $this->import_instance->filename = $_FILES['import_file']['name'];
                $this->import_instance->mimetype = $_FILES['import_file']['type'];
                $this->import_instance->import_from = 'file-upload';
                $this->import_instance->import_format = $_POST['import_format'];
                $this->import_instance->import_table();
                $error = $this->import_instance->error;
                $imported_table = $this->import_instance->imported_table;
                $this->import_instance->unlink_uploaded_file();
            } elseif ( 'server' == $_POST['import_from'] && false === empty( $_POST['import_server'] ) ) {
                $this->import_instance->tempname = $_POST['import_server'];
                $this->import_instance->filename = __( 'Imported Table', WP_TABLE_RELOADED_TEXTDOMAIN );
                $this->import_instance->mimetype = sprintf( __( 'from %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $_POST['import_server'] );
                $this->import_instance->import_from = 'server';
                $this->import_instance->import_format = $_POST['import_format'];
                $this->import_instance->import_table();
                $error = $this->import_instance->error;
                $imported_table = $this->import_instance->imported_table;
            } elseif ( 'form-field' == $_POST['import_from'] && false === empty( $_POST['import_data'] ) ) {
                $this->import_instance->tempname = '';
                $this->import_instance->filename = __( 'Imported Table', WP_TABLE_RELOADED_TEXTDOMAIN );
                $this->import_instance->mimetype = __( 'via form', WP_TABLE_RELOADED_TEXTDOMAIN );
                $this->import_instance->import_from = 'form-field';
                $this->import_instance->import_data = stripslashes( $_POST['import_data'] );
                $this->import_instance->import_format = $_POST['import_format'];
                $this->import_instance->import_table();
                $error = $this->import_instance->error;
                $imported_table = $this->import_instance->imported_table;
            } elseif ( 'url' == $_POST['import_from'] && false === empty( $_POST['import_url'] ) ) {
                $this->import_instance->tempname = '';
                $this->import_instance->filename = __( 'Imported Table', WP_TABLE_RELOADED_TEXTDOMAIN );
                $this->import_instance->mimetype = sprintf( __( 'from %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $_POST['import_url'] );
                $this->import_instance->import_from = 'url';
                $url = clean_url( $_POST['import_url'] );
                $temp_data = wp_remote_fopen( $url );
                $this->import_instance->import_data = ( false !== $temp_data ) ? $temp_data : '';
                $this->import_instance->import_format = $_POST['import_format'];
                $this->import_instance->import_table();
                $error = $this->import_instance->error;
                $imported_table = $this->import_instance->imported_table;
            } else { // no valid data submitted
                $this->print_header_message( __( 'Table could not be imported.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->print_import_table_form();
                return;
            }

            $table = array_merge( $this->default_table, $imported_table );

            if ( isset( $_POST['import_addreplace'] ) && isset( $_POST['import_addreplace_table'] ) && ( 'replace' == $_POST['import_addreplace'] ) && $this->table_exists( $_POST['import_addreplace_table'] ) ) {
                $existing_table = $this->load_table( $_POST['import_addreplace_table'] );
                $table['id'] = $existing_table['id'];
                $table['name'] = $existing_table['name'];
                $table['description'] = $existing_table['description'];
                $success_message = sprintf( __( 'Table %s (%s) replaced successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table['name'] ), $this->safe_output( $table['id'] ) );
                unset( $existing_table );
            } else {
                $table['id'] = $this->get_new_table_id();
                $success_message = sprintf( __( 'Table imported successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
            }

            $rows = count( $table['data'] );
            $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
            $rows = ( 0 < $rows ) ? $rows : 1;
            $cols = ( 0 < $cols ) ? $cols : 1;
            $table['visibility']['rows'] = array_fill( 0, $rows, false );
            $table['visibility']['columns'] = array_fill( 0, $cols, false );

            if ( false == $error ) {
                $this->save_table( $table );
                $this->print_header_message( $success_message );
                $this->print_edit_table_form( $table['id'] );
            } else {
                $this->print_header_message( __( 'Table could not be imported.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->print_import_table_form();
            }
        } elseif ( isset( $_GET['import_format'] ) && 'wp_table' == $_GET['import_format'] && isset( $_GET['wp_table_id'] ) ) {
            check_admin_referer( $this->get_nonce( 'import' ) );

            // do import
            $this->import_instance->import_format = 'wp_table';
            $this->import_instance->wp_table_id = $_GET['wp_table_id'];
            $this->import_instance->import_table();
            $imported_table = $this->import_instance->imported_table;

            $table = array_merge( $this->default_table, $imported_table );

            $rows = count( $table['data'] );
            $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
            $table['visibility']['rows'] = array_fill( 0, $rows, false );
            $table['visibility']['columns'] = array_fill( 0, $cols, false );

            $table['id'] = $this->get_new_table_id();

            $this->save_table( $table );

            $this->print_header_message( __( 'Table imported successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
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

            if ( isset( $_POST['download_export_file'] ) && 'true' == $_POST['download_export_file'] ) {
                $filename = $table_to_export['id'] . '-' . $table_to_export['name'] . '-' . date('Y-m-d') . '.' . $_POST['export_format'];
                $this->prepare_download( $filename, strlen( $exported_table ), 'text/' . $_POST['export_format'] );
                echo $exported_table;
                exit;
            } else {
                $this->print_header_message( sprintf( __( 'Table "%s" exported successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table_to_export['name'] ) ) );
                $this->print_export_table_form( $_POST['table_id'], $exported_table );
            }
        } else {
            $table_id = isset( $_REQUEST['table_id'] ) ? $_REQUEST['table_id'] : 0;
            $this->print_export_table_form( $table_id );
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
            $this->options['use_tablesorter_extended'] = isset( $new_options['use_tablesorter_extended'] );
            $this->options['show_donate_nag'] = isset( $new_options['show_donate_nag'] );
            $this->options['use_custom_css'] = isset( $new_options['use_custom_css'] );
            // clean up CSS style input (if user enclosed it into <style...></style>
            if ( isset( $new_options['custom_css'] ) ) {
                    if ( 1 == preg_match( '/<style.*?>(.*?)<\/style>/is', stripslashes( $new_options['custom_css'] ), $matches ) )
                        $new_options['custom_css'] = $matches[1]; // if found, take match as style to save
                    $this->options['custom_css'] = $new_options['custom_css'];
            }

            $this->update_options();

            $this->print_header_message( __( 'Options saved successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
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
            update_option( 'recently_activated', array( $plugin => time() ) + (array)get_option( 'recently_activated' ) );

        $this->print_page_header( __( 'WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_header_message( __( 'Plugin deactivated successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        echo "<p>" . __( 'All tables, data and options were deleted. You may now remove the plugin\'s subfolder from your WordPress plugin folder.', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</p>";
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
        <div style="clear:both;"><p>
        <?php _e( 'This is a list of all available tables.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You may insert a table into a post or page here.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br />
		<?php _e( 'Click the "Insert" link after the desired table and the corresponding shortcode will be inserted into the editor (<strong>[table id=&lt;the_table_ID&gt; /]</strong>).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
        </p></div>
		<?php
        if ( 0 < count( $this->tables ) ) {
            ?>
        <div style="clear:both;">
            <table class="widefat">
            <thead>
                <tr>
                    <th scope="col"><?php _e( 'ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Action', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <th scope="col"><?php _e( 'ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Action', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                </tr>
            </tfoot>
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
                echo "<td style=\"vertical-align:baseline;\">{$name}</td>";
                echo "<td style=\"vertical-align:baseline;\">{$description}</td>";
                echo "<td style=\"vertical-align:baseline;\"><a class=\"send_table_to_editor\" title=\"{$id}\" href=\"#\" style=\"color:#21759B;\">" . __( 'Insert', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a></td>\n";
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
    function do_action_ajax_preview() {
        check_admin_referer( $this->get_nonce( 'ajax_preview' ) );

        // init language support
        $this->init_language_support();

        $table_id = ( isset( $_GET['table_id'] ) && 0 < $_GET['table_id'] ) ? $_GET['table_id'] : 0;

        if ( $this->table_exists( $table_id ) ) {
            $table = $this->load_table( $_GET['table_id'] );

            $this->print_page_header( sprintf( __( 'Preview of Table "%s" (ID %s)', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table['name'] ), $this->safe_output( $table['id'] ) ) );
            ?>
            <div style="clear:both;"><p>
            <?php _e( 'This is a preview of the table data.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'Because of CSS styling, the table might look different on your page!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php echo sprintf( __( 'To show this table in your pages, posts or text-widgets, use the shortcode <strong>[table id=%s /]</strong>.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table['id'] ) ); ?>
            </p></div>
            <div style="clear:both;">
            <?php
                $WP_Table_Reloaded_Frontend = $this->create_class_instance( 'WP_Table_Reloaded_Frontend', 'wp-table-reloaded-frontend.php', '' );
                $atts = array( 'id' => $_GET['table_id'] );
                echo $WP_Table_Reloaded_Frontend->handle_content_shortcode_table( $atts );
            ?>
            </div>
            <?php
            $this->print_page_footer();
        } else {
            ?>
            <div style="clear:both;"><p style="width:97%;"><?php _e( 'There is no table with this ID!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p></div>
            <?php
        }

        // necessary to stop page building here!
        exit;
    }
    
    // ###################################################################################################################
    // user donated
    function do_action_hide_donate_nag() {
        check_admin_referer( $this->get_nonce( 'hide_donate_nag' ) );

        $this->options['show_donate_nag'] = false;
        $this->update_options();

        if ( isset( $_GET['user_donated'] ) && true == $_GET['user_donated'] ) {
            $this->print_header_message( __( 'Thank you very much! Your donation is highly appreciated. You just contributed to the further development of WP-Table Reloaded!', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        } else {
            $this->print_header_message( sprintf( __( 'No problem! I still hope you enjoy the benefits that WP-Table Reloaded brings to you. If you should want to change your mind, you\'ll always find the "Donate" button on the <a href="%s">WP-Table Reloaded website</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/donate-message/' ) );
        }
        
        $this->print_list_tables_form();
    }
    
    // ###################################################################################################################
    // ##########################################                     ####################################################
    // ##########################################     Print Forms     ####################################################
    // ##########################################                     ####################################################
    // ###################################################################################################################

    // ###################################################################################################################
    // list all tables
    function print_list_tables_form()  {
        $this->print_page_header( __( 'List of Tables', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' &lsaquo; ' . __( 'WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) );
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
            <table class="widefat" id="wp-table-reloaded-list">
            <thead>
                <tr>
                    <th class="check-column" scope="col"><input type="checkbox" /></th>
                    <th scope="col"><?php _e( 'ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Last Modified', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <th class="check-column" scope="col"><input type="checkbox" /></th>
                    <th scope="col"><?php _e( 'ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Last Modified', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                </tr>
            </tfoot>
            <?php
            echo "<tbody>\n";
            $bg_style_index = 0;
            foreach ( $this->tables as $id => $tableoptionname ) {
                $bg_style_index++;
                $bg_style = ( 0 == ($bg_style_index % 2) ) ? ' class="alternate"' : '';

                // get name and description to show in list
                $table = $this->load_table( $id );
                    $name = ( !empty( $table['name'] ) ) ? $this->safe_output( $table['name'] ) : __( '(no name)', WP_TABLE_RELOADED_TEXTDOMAIN );
                    $description = ( !empty( $table['description'] ) ) ? $this->safe_output( $table['description'] ) : __( '(no description)', WP_TABLE_RELOADED_TEXTDOMAIN );
                    $last_modified = $this->format_datetime( $table['last_modified'] );
                    $last_editor = $this->get_last_editor( $table['last_editor_id'] );
                    if ( !empty( $last_editor ) )
                        $last_editor = __( 'by', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' ' . $last_editor;
                unset( $table );

                $edit_url = $this->get_action_url( array( 'action' => 'edit', 'table_id' => $id ), false );
                $copy_url = $this->get_action_url( array( 'action' => 'copy', 'table_id' => $id ), true );
                $export_url = $this->get_action_url( array( 'action' => 'export', 'table_id' => $id ), false );
                $delete_url = $this->get_action_url( array( 'action' => 'delete', 'table_id' => $id, 'item' => 'table' ), true );
                $preview_url = $this->get_action_url( array( 'action' => 'ajax_preview', 'table_id' => $id ), true );

                echo "<tr{$bg_style}>\n";
                echo "\t<th class=\"check-column no-wrap\" scope=\"row\"><input type=\"checkbox\" name=\"tables[]\" value=\"{$id}\" /></th>\n";
                echo "\t<th scope=\"row\" class=\"no-wrap table-id\">{$id}</th>\n";
                echo "\t<td>\n";
                echo "\t\t<a title=\"" . sprintf( __( 'Edit %s', WP_TABLE_RELOADED_TEXTDOMAIN ), "&quot;{$name}&quot;" ) . "\" class=\"row-title\" href=\"{$edit_url}\">{$name}</a>\n";
                echo "\t\t<div class=\"row-actions no-wrap\">";
                echo "<a href=\"{$edit_url}\">" . __( 'Edit', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                $shortcode = "[table id={$id} /]";
                echo "<a href=\"javascript:void(0);\" class=\"table_shortcode_link\" title=\"{$shortcode}\">" . __( 'Shortcode', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                echo "<a class=\"copy_table_link\" href=\"{$copy_url}\">" . __( 'Copy', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                echo "<a href=\"{$export_url}\">" . __( 'Export', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                echo "<a class=\"delete_table_link\" href=\"{$delete_url}\">" . __( 'Delete', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                $preview_title = sprintf( __( 'Preview of Table %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $id );
                echo "<a class=\"thickbox\" href=\"{$preview_url}\" title=\"{$preview_title}\">" . __( 'Preview', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
                echo "</div>\n";
                echo "\t</td>\n";
                echo "\t<td>{$description}</td>\n";
                echo "\t<td class=\"no-wrap\">{$last_modified}<br/>{$last_editor}</td>\n";
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
        // add tablesorter script
        add_action( 'admin_footer', array( &$this, 'output_tablesorter_js' ) );
    }

    // ###################################################################################################################
    function print_add_table_form() {
        // Begin Add Table Form
        $this->print_page_header( __( 'Add new Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'add' );
        ?>
        <div style="clear:both;">
        <p><?php _e( 'You can add a new table here. Just enter its name, a description (optional) and the number of rows and columns.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'You may add, insert or delete rows and columns later.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
		<div style="clear:both;">
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'add' ) ); ?>

        <table class="wp-table-reloaded-options wp-table-reloaded-newtable">
        <tr valign="top">
            <th scope="row"><label for="table_name"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table[name]" id="table_name" class="focus-blur-change" value="<?php _e( 'Enter Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" style="width:100%;" title="<?php _e( 'Enter Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table_description"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><textarea name="table[description]" id="table_description" class="focus-blur-change" rows="15" cols="40" style="width:100%;height:85px;" title="<?php _e( 'Enter Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>"><?php _e( 'Enter Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></textarea></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table_rows"><?php _e( 'Number of Rows', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table[rows]" id="table_rows" value="5" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table_cols"><?php _e( 'Number of Columns', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table[cols]" id="table_cols" value="5" /></td>
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

        $this->print_page_header( sprintf( __( 'Edit Table "%s" (ID %s)', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table['name'] ), $this->safe_output( $table['id'] ) ) );
        $this->print_submenu_navigation( 'edit' );
        ?>
        <div style="clear:both;"><p><?php _e( 'You may edit the content of the table here. It is also possible to add or delete columns and rows.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br />
		<?php echo sprintf( __( 'To show this table in your pages, posts or text-widgets, use the shortcode <strong>[table id=%s /]</strong>.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table_id ) ); ?></p></div>
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'edit' ) ); ?>
        <input type="hidden" name="table[id]" value="<?php echo $table['id']; ?>" />
        <input type="hidden" name="action" value="edit" />

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Table Information', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <table class="wp-table-reloaded-table-information">
        <tr valign="top">
            <th scope="row"><label for="table_id"><?php _e( 'Table ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table_id" id="table_id" value="<?php echo $this->safe_output( $table['id'] ); ?>" style="width:80px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table_name"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table[name]" id="table_name" value="<?php echo $this->safe_output( $table['name'] ); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table_description"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><textarea name="table[description]" id="table_description" rows="15" cols="40" style="height:84px;"><?php echo $this->safe_output( $table['description'] ); ?></textarea></td>
        </tr>
        <?php if ( !empty( $table['last_editor_id'] ) ) { ?>
        <tr valign="top">
            <th scope="row"><?php _e( 'Last Modified', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><?php echo $this->format_datetime( $table['last_modified'] ); ?> <?php _e( 'by', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php echo $this->get_last_editor( $table['last_editor_id'] ); ?></td>
        </tr>
        <?php } ?>
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
                <?php
                    // Table Header (Columns get a Letter between A and A+$cols-1)
                    $cols_output = '';
                    foreach ( range( 'A', chr( ord( 'A' ) + $cols - 1 ) ) as $letter )
                        $cols_output .= "<th scope=\"col\">".$letter."</th>";
                ?>
                <thead>
                    <tr>
                        <th scope="col">&nbsp;</th>
                        <?php echo $cols_output; ?>
                        <th scope="col">&nbsp;</th>
                        <th class="check-column" scope="col"><input type="checkbox" style="display:none;" /></th><?php // "display:none;" because JS checks wrong index otherwise ?>
                        <th scope="col">&nbsp;</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th scope="col">&nbsp;</th>
                        <?php echo $cols_output; ?>
                        <th scope="col">&nbsp;</th>
                        <th scope="col">&nbsp;</th>
                        <th scope="col">&nbsp;</th>
                    </tr>
                </tfoot>
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
                            echo "\t<td><textarea rows=\"1\" cols=\"20\" name=\"{$cell_name}\" class=\"edit_row_{$row_idx} edit_col_{$col_idx}\">{$cell_content}</textarea></td>\n";
                        }
                        $insert_row_url = $this->get_action_url( array( 'action' => 'insert', 'table_id' => $table['id'], 'item' => 'row', 'element_id' => $row_idx ), true );
                        $delete_row_url = $this->get_action_url( array( 'action' => 'delete', 'table_id' => $table['id'], 'item' => 'row', 'element_id' => $row_idx ), true );
                        echo "\t<td><a href=\"{$insert_row_url}\">" . __( 'Insert Row', WP_TABLE_RELOADED_TEXTDOMAIN )."</a>";
                        if ( 1 < $rows ) // don't show delete link for last and only row
                            echo " | <a class=\"delete_row_link\" href=\"{$delete_row_url}\">".__( 'Delete Row', WP_TABLE_RELOADED_TEXTDOMAIN )."</a>";
                        echo "</td>\n";
                        $checked = ( isset( $table['visibility']['rows'][$col_idx] ) && true == $table['visibility']['rows'][$row_idx] ) ? 'checked="checked" ': '' ;
                        echo "\t<td class=\"check-column\"><input type=\"checkbox\" name=\"table[visibility][rows][{$row_idx}]\" id=\"edit_row_{$row_idx}\" value=\"true\" {$checked}/> <label for=\"edit_row_{$row_idx}\">" . __( 'Row hidden', WP_TABLE_RELOADED_TEXTDOMAIN ) ."</label></td>\n";
                        echo "\t<th scope=\"row\">{$output_idx}</th>\n";
                    echo "</tr>";
                }

                // ACTION links
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

                    // add rows/columns buttons
                        echo "\t<td><input type=\"hidden\" name=\"insert[row][id]\" value=\"{$rows}\" /><input type=\"hidden\" name=\"insert[col][id]\" value=\"{$cols}\" />";

                        $row_insert = '<input type="text" name="insert[row][number]" value="1" style="width:30px" />';
                        $col_insert = '<input type="text" name="insert[col][number]" value="1" style="width:30px" />';
                        ?>
                        <?php echo sprintf( __( 'Add %s row(s)', WP_TABLE_RELOADED_TEXTDOMAIN ), $row_insert ); ?>
                        <input type="submit" name="submit[insert_rows]" class="button-primary" value="<?php _e( 'Add', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" /><br/>
                        <?php echo sprintf( __( 'Add %s column(s)', WP_TABLE_RELOADED_TEXTDOMAIN ), $col_insert ); ?>
                        <input type="submit" name="submit[insert_cols]" class="button-primary" value="<?php _e( 'Add', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" /></td>
                        <?php
                        echo "\t<td>&nbsp;</td>\n";
                        echo "\t<th scope=\"row\">&nbsp;</th>\n";
                    echo "</tr>";

                // hide checkboxes
                    echo "<tr class=\"hide-columns\">\n";
                        echo "\t<th scope=\"row\">&nbsp;</th>\n";
                        foreach ( $table['data'][0] as $col_idx => $cell_content ) {
                            $checked = ( isset( $table['visibility']['columns'][$col_idx] ) && true == $table['visibility']['columns'][$col_idx] ) ? 'checked="checked" ': '' ;
                            echo "\t<td class=\"check-column\"><input type=\"checkbox\" name=\"table[visibility][columns][{$col_idx}]\" id=\"edit_col_{$col_idx}\" value=\"true\" {$checked}/> <label for=\"edit_col_{$col_idx}\">" . __( 'Column hidden', WP_TABLE_RELOADED_TEXTDOMAIN ) ."</label></td>";
                        }
                        echo "\t<td>&nbsp;</td>";
                        echo "\t<td>&nbsp;</td>";
                        echo "\t<th scope=\"row\">&nbsp;</th>\n";
                    echo "</tr>";
                ?>
                </tbody>
            </table>
        </div>
        </div>
        <?php } //endif 0 < $rows/$cols ?>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Data Manipulation', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php _e( 'Hide', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
    <table class="wp-table-reloaded-data-manipulation widefat">
    <tr><td>
        <?php if ( 1 < $rows ) { // swap rows form
            $row1_select = '<select name="swap[row][1]">';
            $row2_select = '<select name="swap[row][2]">';
            foreach ( $table['data'] as $row_idx => $table_row ) {
                $row1_select .= "<option value=\"{$row_idx}\">" . ( $row_idx + 1 ) . "</option>";
                $row2_select .= "<option value=\"{$row_idx}\">" . ( $row_idx + 1 ) . "</option>";
            }
            $row1_select .= '</select>';
            $row2_select .= '</select>';
            
            echo sprintf( __( 'Swap rows %s and %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $row1_select, $row2_select );
            ?>
            <input type="submit" name="submit[swap_rows]" class="button-primary" value="<?php _e( 'Swap', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php } // end if form swap rows ?>

        <?php if ( 1 < $cols ) { // swap cols form ?>
            <br/>
            <?php
            $col1_select = '<select name="swap[col][1]">';
            $col2_select = '<select name="swap[col][2]">';
            foreach ( $table['data'][0] as $col_idx => $cell_content ) {
                $col_letter = chr( ord( 'A' ) + $col_idx );
                $col1_select .= "<option value=\"{$col_idx}\">{$col_letter}</option>";
                $col2_select .= "<option value=\"{$col_idx}\">{$col_letter}</option>";
            }
            $col1_select .= '</select>';
            $col2_select .= '</select>';

            echo sprintf( __( 'Swap columns %s and %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $col1_select, $col2_select );
            ?>
            <input type="submit" name="submit[swap_cols]" class="button-primary" value="<?php _e( 'Swap', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php } // end if form swap cols ?>
    </td><td>
        <?php if ( 1 < $rows ) { // move row form
            $row1_select = '<select name="move[row][1]">';
            $row2_select = '<select name="move[row][2]">';
            foreach ( $table['data'] as $row_idx => $table_row ) {
                $row1_select .= "<option value=\"{$row_idx}\">" . ( $row_idx + 1 ) . "</option>";
                $row2_select .= "<option value=\"{$row_idx}\">" . ( $row_idx + 1 ) . "</option>";
            }
            $row1_select .= '</select>';
            $row2_select .= '</select>';

            $move_where_select = '<select name="move[where]">';
            $move_where_select .= "<option value=\"before\">" . __( 'before', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</option>";
            $move_where_select .= "<option value=\"after\">" . __( 'after', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</option>";
            $move_where_select .= '</select>';

            echo sprintf( __( 'Move row %s %s row %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $row1_select, $move_where_select, $row2_select );
            ?>
            <input type="submit" name="submit[move_row]" class="button-primary" value="<?php _e( 'Move', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php } // end if form move row ?>

        <?php if ( 1 < $cols ) { // move col form ?>
            <br/>
            <?php
            $col1_select = '<select name="move[col][1]">';
            $col2_select = '<select name="move[col][2]">';
            foreach ( $table['data'][0] as $col_idx => $cell_content ) {
                $col_letter = chr( ord( 'A' ) + $col_idx );
                $col1_select .= "<option value=\"{$col_idx}\">{$col_letter}</option>";
                $col2_select .= "<option value=\"{$col_idx}\">{$col_letter}</option>";
            }
            $col1_select .= '</select>';
            $col2_select .= '</select>';

            $move_where_select = '<select name="move[where]">';
            $move_where_select .= "<option value=\"before\">" . __( 'before', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</option>";
            $move_where_select .= "<option value=\"after\">" . __( 'after', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</option>";
            $move_where_select .= '</select>';

            echo sprintf( __( 'Move column %s %s column %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $col1_select, $move_where_select, $col2_select );
            ?>
            <input type="submit" name="submit[move_col]" class="button-primary" value="<?php _e( 'Move', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php } // end if form move col ?>
    </td></tr>
    <tr><td>
        <a id="a-insert-link" class="button-primary" href="javascript:void(0);"><?php _e( 'Insert Link', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
        <a id="a-insert-image" href="media-upload.php?type=image&amp;tab=library&amp;TB_iframe=true" class="thickbox button-primary" title="<?php _e( 'Insert Image', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" onclick="javascript:return false;"><?php _e( 'Insert Image', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
    </td><td>
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
    </td></tr>
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

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Table Settings', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php _e( 'Hide', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'These settings will only be used for this table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'Alternating row colors', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][alternating_row_colors]" id="table_options_alternating_row_colors"<?php echo ( true == $table['options']['alternating_row_colors'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_alternating_row_colors"><?php _e( 'Every second row will have an alternating background color.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Use Table Headline', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][first_row_th]" id="table_options_first_row_th"<?php echo ( true == $table['options']['first_row_th'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_first_row_th"><?php _e( 'The first row of your table will use the &lt;th&gt; tag.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Print Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][print_name]" id="table_options_print_name"<?php echo ( true == $table['options']['print_name'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_print_name"><?php _e( 'The Table Name will be written above the table in a &lt;h2&gt; tag.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Print Table Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][print_description]" id="table_options_print_description"<?php echo ( true == $table['options']['print_description'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_print_description"><?php _e( 'The Table Description will be written under the table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top" id="options_use_tablesorter">
            <th scope="row"><?php _e( 'Use Tablesorter', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td>
            <input type="hidden" id="tablesorter_enabled" value="<?php echo $this->options['enable_tablesorter']; ?>" />
            <input type="checkbox" name="table[options][use_tablesorter]" id="table_options_use_tablesorter"<?php echo ( true == $table['options']['use_tablesorter'] ) ? ' checked="checked"': '' ; ?><?php echo ( false == $this->options['enable_tablesorter'] || false == $table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_use_tablesorter"><?php _e( 'You may sort a table using the <a href="http://www.tablesorter.com/">Tablesorter-jQuery-Plugin</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e( '<small>Attention: You must have Tablesorter enabled on the "Plugin Options" screen and the option "Use Table Headline" has to be enabled above for this to work!</small>', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
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

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Custom Data Fields', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php _e( 'Hide', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <?php _e( 'Custom Data Fields can be used to add extra metadata to a table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'For example, this could be information about the source or the creator of the data.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
        <br/>
        <?php echo sprintf( __( 'You can show this data in the same way as tables by using the shortcode <strong>[table-info id=%s field="&lt;field-name&gt;" /]</strong>.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->safe_output( $table_id ) ); ?>
        <br/><br/>
        <?php if ( isset( $table['custom_fields'] ) && !empty( $table['custom_fields'] ) ) { ?>
            <table class="widefat" style="width:100%" id="table_custom_fields">
                <thead>
                    <tr>
                        <th scope="col"><?php _e( 'Field Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                        <th scope="col"><?php _e( 'Value', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                        <th scope="col"><?php _e( 'Action', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach ( $table['custom_fields'] as $name => $value ) {
                    $name = $this->safe_output( $name );
                    $value = $this->safe_output( $value );
                    echo "<tr>\n";
                        echo "\t<td style=\"width:10%;\">{$name}</td>\n";
                        echo "\t<td style=\"width:75%;\"><textarea rows=\"1\" cols=\"20\" name=\"table[custom_fields][{$name}]\" style=\"width:90%\">{$value}</textarea></td>\n";
                        $delete_cf_url = $this->get_action_url( array( 'action' => 'delete', 'table_id' => $table['id'], 'item' => 'custom_field', 'element_id' => $name ), true );
                        echo "\t<td style=\"width:15%;min-width:200px;\">";
                        echo "<a href=\"{$delete_cf_url}\">" . __( 'Delete Field', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
                        $shortcode = "[table-info id=" . $this->safe_output( $table_id ) . " field=&quot;{$name}&quot; /]";
                        echo " | <a href=\"javascript:void(0);\" class=\"cf_shortcode_link\" title=\"{$shortcode}\">" . __( 'View shortcode', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
                        echo "</td>\n";
                    echo "</tr>";
                }
                ?>
                </tbody>
            </table>
            <br/>
        <?php } // endif custom_fields ?>
        <?php _e( 'To add a new Custom Data Field, enter its name (only lowercase letters, numbers, _ and -).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
        <?php _e( 'Custom Data Field Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>: <input type="text" id="insert_custom_field_name" name="insert[custom_field]" value="" style="width:300px" /> <input type="submit" name="submit[insert_cf]" class="button-primary" value="<?php _e( 'Add', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
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

    <p>
    <?php echo __( 'Other actions', WP_TABLE_RELOADED_TEXTDOMAIN ) . ':';
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
        <p><?php _e( 'You may import a table from existing data here.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'This may be a CSV, XML or HTML file, which need a certain structure though. Please consult the documentation.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You may also select the import source.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
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
                echo "<option" . ( isset( $_POST['import_format'] ) && ( $import_format == $_POST['import_format'] ) ? ' selected="selected"': '' ) . " value=\"{$import_format}\">{$longname}</option>\n";
        ?>
        </select></td>
        </tr>
        <?php if( 0 < count( $this->tables ) ) { ?>
        <tr valign="top" class="tr-import-addreplace">
            <th scope="row"><?php _e( 'Add or Replace Table?', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td>
            <input name="import_addreplace" id="import_addreplace_add" type="radio" value="add" <?php echo ( isset( $_POST['import_addreplace'] ) && 'add' != $_POST['import_addreplace'] ) ? '' : 'checked="checked" ' ; ?>/> <label for="import_addreplace_add"><?php _e( 'Add as new Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label>
            <input name="import_addreplace" id="import_addreplace_replace" type="radio" value="replace" <?php echo ( isset( $_POST['import_addreplace'] ) && 'replace' == $_POST['import_addreplace'] ) ? 'checked="checked" ': '' ; ?>/> <label for="import_addreplace_replace"><?php _e( 'Replace existing Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label>
            </td>
        </tr>
        <tr valign="top" class="tr-import-addreplace-table">
            <th scope="row"><label for="import_addreplace_table"><?php _e( 'Select existing Table to Replace', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><select id="import_addreplace_table" name="import_addreplace_table">
        <?php
            foreach ( $this->tables as $id => $tableoptionname ) {
                // get name and description to show in list
                $table = $this->load_table( $id );
                    $name = $this->safe_output( $table['name'] );
                    //$description = $this->safe_output( $table['description'] );
                unset( $table );
                echo "<option" . ( isset( $_POST['import_addreplace_table'] ) && ( $id == $_POST['import_addreplace_table'] ) ? ' selected="selected"': '' ) . " value=\"{$id}\">{$name} (ID {$id})</option>";
            }
        ?>
        </select></td>
        </tr>
        <?php } ?>
        <tr valign="top" class="tr-import-from">
            <th scope="row"><?php _e( 'Select source for import', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td>
            <input name="import_from" id="import_from_file" type="radio" value="file-upload" <?php echo ( isset( $_POST['import_from'] ) && 'file-upload' != $_POST['import_from'] ) ? '' : 'checked="checked" ' ; ?>/> <label for="import_from_file"><?php _e( 'File upload', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label>
            <input name="import_from" id="import_from_url" type="radio" value="url" <?php echo ( isset( $_POST['import_from'] ) && 'url' == $_POST['import_from'] ) ? 'checked="checked" ': '' ; ?>/> <label for="import_from_url"><?php _e( 'URL', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label>
            <input name="import_from" id="import_from_field" type="radio" value="form-field" <?php echo ( isset( $_POST['import_from'] ) && 'form-field' == $_POST['import_from'] ) ? 'checked="checked" ': '' ; ?>/> <label for="import_from_field"><?php _e( 'Manual input', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label>
            <input name="import_from" id="import_from_server" type="radio" value="server" <?php echo ( isset( $_POST['import_from'] ) && 'server' == $_POST['import_from'] ) ? 'checked="checked" ': '' ; ?>/> <label for="import_from_server"><?php _e( 'File on server', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label>
            </td>
        </tr>
        <tr valign="top" class="tr-import-file-upload">
            <th scope="row"><label for="import_file"><?php _e( 'Select File with Table to Import', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input name="import_file" id="import_file" type="file" /></td>
        </tr>
        <tr valign="top" class="tr-import-url">
            <th scope="row"><label for="import_url"><?php _e( 'URL to import table from', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="import_url" id="import_url" style="width:400px;" value="<?php echo ( isset( $_POST['import_url'] ) ) ? $_POST['import_url'] : 'http://' ; ?>" /></td>
        </tr>
        <tr valign="top" class="tr-import-server">
            <th scope="row"><label for="import_server"><?php _e( 'Path to file on server', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="import_server" id="import_server" style="width:400px;" value="<?php echo ( isset( $_POST['import_server'] ) ) ? $_POST['import_server'] : '' ; ?>" /></td>
        </tr>
        <tr valign="top" class="tr-import-form-field">
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
            <tfoot>
                <tr>
                    <th class="check-column" scope="col"><input type="checkbox" /></th>
                    <th scope="col"><?php _e( 'ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Action', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                </tr>
            </tfoot>
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
        <?php
        } else { // end if $tables
            echo "<div style=\"clear:both;\"><p>" . __( 'wp-Table by Alex Rabe seems to be installed, but no tables were found.', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</p></div>";
        }
            ?>
        </div>
        <?php
        } else {
            // at least one of the wp-Table tables was *not* found in database, so nothing to show here
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
        <p><?php _e( 'You may export a table here. Just select the table, your desired export format and (for CSV only) a delimiter.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
        <?php _e( 'You may opt to download the export file. Otherwise it will be shown on this page.', WP_TABLE_RELOADED_TEXTDOMAIN ); echo ' '; ?>
        <?php _e( 'Be aware that only the table data, but no options or settings are exported.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
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
                echo "<option" . ( ( isset( $_POST['export_format'] ) && $export_format == $_POST['export_format'] ) ? ' selected="selected"': '' ) . " value=\"{$export_format}\">{$longname}</option>";
        ?>
        </select></td>
        </tr>
        <tr valign="top" class="tr-export-delimiter">
            <th scope="row"><label for="delimiter"><?php _e( 'Select Delimiter to use', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><select id="delimiter" name="delimiter">
        <?php
            $delimiters = $this->export_instance->delimiters;
            foreach ( $delimiters as $delimiter => $longname )
                echo "<option" . ( ( isset( $_POST['delimiter'] ) && $delimiter == $_POST['delimiter'] ) ? ' selected="selected"': '' ) . " value=\"{$delimiter}\">{$longname}</option>";
        ?>
        </select> <?php _e( '<small>(Only needed for CSV export.)</small>', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Download file', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="download_export_file" id="download_export_file" value="true"<?php echo ( isset( $_POST['submit'] ) && !isset( $_POST['download_export_file'] ) ) ? '' : ' checked="checked"'; ?> /> <label for="download_export_file"><?php _e( 'Yes, I want to download the export file.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
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
        <p><?php _e( 'You may change these global options.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
        <?php _e( 'They will effect all tables or the general plugin behavior.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
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
            <td><input type="checkbox" name="options[enable_tablesorter]" id="options_enable_tablesorter"<?php echo ( true == $this->options['enable_tablesorter'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_enable_tablesorter"><?php _e( 'Yes, enable the <a href="http://www.tablesorter.com/">Tablesorter jQuery plugin</a>. This can be used to make tables sortable (can be activated for each table separately in its options).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Add custom CSS?', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[use_custom_css]" id="options_use_custom_css"<?php echo ( true == $this->options['use_custom_css'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_use_custom_css">
            <?php _e( 'Yes, include and load the following CSS-snippet on my site inside a &lt;style&gt;-HTML-tag.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
             </label></td>
        </tr>
        <tr valign="top">
            <th scope="row">&nbsp;</th>
            <td><label for="options_custom_css"><?php _e( 'Enter custom CSS', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label><br/>
            <textarea name="options[custom_css]" id="options_custom_css" rows="15" cols="40" style="width:600px;height:300px;"<?php echo ( false == $this->options['use_custom_css'] ) ? ' disabled="disabled"': '' ; ?>><?php echo $this->safe_output( $this->options['custom_css'] ); ?></textarea><br/><br/>
            <?php
            $stylesheet = '/themes/' . get_stylesheet() . '/style.css';
            $editor_uri = 'theme-editor.php?file=' . $stylesheet;
            echo sprintf( __( 'You might get a better website performance, if you add the CSS styling to your theme\'s "style.css" (located at <a href="%s">%s</a>) instead.', WP_TABLE_RELOADED_TEXTDOMAIN ), $editor_uri, $stylesheet ); ?><br/>
            <?php echo sprintf( __( 'See the <a href="%s">plugin website</a> for styling examples or use one of the following: <a href="%s">Example Style 1</a> <a href="%s">Example Style 2</a>', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/', 'http://tobias.baethge.com/download/plugins/additional/example-style-1.css', 'http://tobias.baethge.com/download/plugins/additional/example-style-2.css' ); ?><br/><?php _e( 'Just copy the contents of a file into the textarea.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php echo sprintf( __( 'Another possibility is, to include a CSS file (e.g. from your theme folder) with the CSS @import command: %s', WP_TABLE_RELOADED_TEXTDOMAIN ), '<code>@import url( "YOUR-CSS-FILE.css" ) screen, print;</code>' ); ?>
            </td>
        </tr>
        </table>
        </div>
        </div>
        
        <div class="postbox closed">
        <h3 class="hndle"><span><?php _e( 'Advanced Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php _e( 'Hide', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'Use Tablesorter Extended?', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[use_tablesorter_extended]" id="options_use_tablesorter_extended"<?php echo ( true == $this->options['use_tablesorter_extended'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_use_tablesorter_extended"><small><?php _e( 'Yes, use Extended Tablesorter from <a href="http://tablesorter.openwerk.de">S&ouml;ren Krings</a> instead of original Tablesorter script (EXPERIMENTAL FEATURE!).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Uninstall Plugin upon Deactivation?', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[uninstall_upon_deactivation]" id="options_uninstall_upon_deactivation"<?php echo ( true == $this->options['uninstall_upon_deactivation'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_uninstall_upon_deactivation"><small><?php _e( 'Yes, uninstall everything when the plugin is deactivated. Attention: You should only enable this checkbox directly before deactivating the plugin from the WordPress plugins page!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php _e( '(This setting does not influence the "Manually Uninstall Plugin" button below!)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Allow donation message?', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[show_donate_nag]" id="options_show_donate_nag"<?php echo ( true == $this->options['show_donate_nag'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_show_donate_nag"><small><?php _e( 'Yes, show a donation message after 30 days of using the plugin.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></label></td>
        </tr>
        </table>
        </div>
        </div>

        <input type="hidden" name="options[submit]" value="true" /><?php // need this, so that options get saved ?>
        <input type="hidden" name="action" value="options" />
        <p class="submit">
        <input type="submit" name="submit[form]" class="button-primary" value="<?php _e( 'Save Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </p>

        </form>
        </div>
        
        <h2><?php _e( 'Manually Uninstall Plugin', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></h2>
        <div style="clear:both;">
            <p><?php _e( 'You may uninstall the plugin here. This <strong>will delete</strong> all tables, data, options, etc., that belong to the plugin, including all tables you added or imported.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'Be very careful with this and only click the button if you know what you are doing!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
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
        $this->print_page_header( __( 'About WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) );
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
        <p><?php _e( 'This plugin was written by <a href="http://tobias.baethge.com/">Tobias B&auml;thge</a>. It is licensed as Free Software under GPL 2.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'If you like the plugin, please consider <a href="http://tobias.baethge.com/donate/"><strong>a donation</strong></a> and rate the plugin in the <a href="http://wordpress.org/extend/plugins/wp-table-reloaded/">WordPress Plugin Directory</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'Donations and good ratings encourage me to further develop the plugin and to provide countless hours of support. Any amount is appreciated! Thanks!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
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
            <br/>&middot; <?php _e( 'Belorussian (thanks to <a href="http://www.fatcow.com/">Marcis Gasuns</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Brazilian Portugues (thanks to <a href="http://www.pensarics.com/">Rics</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Czech (thanks to <a href="http://separatista.net/">Separatista</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'French (thanks to <a href="http://ultratrailer.net/">Yin-Yin</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Italian (thanks to <a href="http://www.scrical.it/">Gabriella Mazzon</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Japanese (thanks to <a href="http://www.u-1.net/">Yuuichi</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Polish (thanks to Alex Kortan)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
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
            <br/>&middot; <?php _e( 'Plugin installed', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>: <?php echo date( 'Y/m/d H:i:s', $this->options['install_time'] ); ?>
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
    function print_header_message( $text ) {
        echo "<div id='message' class='updated fade'><p><strong>{$text}</strong></p></div>";
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
    function print_submenu_navigation( $action ) {
        ?>
        <ul class="subsubsub">
            <li><a <?php if ( 'list' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'list' ), false ); ?>"><?php _e( 'List Tables', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a> | </li>
            <li><a <?php if ( 'add' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'add' ), false ); ?>"><?php _e( 'Add new Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a> | </li>
            <li><a <?php if ( 'import' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'import' ), false ); ?>"><?php _e( 'Import a Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a> | </li>
            <li><a <?php if ( 'export' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'export' ), false ); ?>"><?php _e( 'Export a Table', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a></li>
            <li>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</li>
            <li><a <?php if ( 'options' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'options' ), false ); ?>"><?php _e( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a> | </li>
            <li><a <?php if ( 'info' == $action ) echo 'class="current" '; ?>href="<?php echo $this->get_action_url( array( 'action' => 'info' ), false ); ?>"><?php _e( 'About the Plugin', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a></li>
        </ul>
        <br class="clear" />
        <?php
    }
    
    // ###################################################################################################################
    function may_print_donate_nag() {
        if ( false == $this->options['show_donate_nag'] )
            return false;

        // how long has the plugin been installed?
        $secs = time() - $this->options['install_time'];
        $days = floor( $secs / (60*60*24) );
        return ( $days >= 30 ) ? true : false;
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
                $help = __( 'This is the "List Tables" screen.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'insert':
            case 'edit':
                $help = __( 'This is the "Edit Table" screen.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'add':
                $help = __( 'This is the "Add new Table" screen.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'import':
                $help = __( 'This is the "Import a Table" screen.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'export':
                $help = __( 'This is the "Export a Table" screen.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'options':
                $help = __( 'This is the "Plugin Options" screen.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'uninstall':
                $help = __( 'Plugin deactivated successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' ' . __( 'All tables, data and options were deleted. You may now remove the plugin\'s subfolder from your WordPress plugin folder.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'info':
                $help = __( 'This is the "About WP-Table Reloaded" screen.', WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            // case 'ajax_list': // not needed, no contextual_help here
            // case 'ajax_preview': // not needed, no contextual_help here
            default:
                $help = '';
                break;
        }

        $help .= '<br/><br/>' . __( 'More information can be found on the <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/">plugin\'s website</a>.', WP_TABLE_RELOADED_TEXTDOMAIN );
        $help .= ' ' . __( 'See the <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/documentation/">documentation</a> or find out how to get <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/support/">support</a>.', WP_TABLE_RELOADED_TEXTDOMAIN );
        $help .= '<br/>' . __( 'If you like the plugin, please consider <a href="http://tobias.baethge.com/donate/"><strong>a donation</strong></a> and rate the plugin in the <a href="http://wordpress.org/extend/plugins/wp-table-reloaded/">WordPress Plugin Directory</a>.', WP_TABLE_RELOADED_TEXTDOMAIN );

        return $help;
    }

    // ###################################################################################################################
    function safe_output( $string ) {
        $string = stripslashes( $string ); // because $string is add_slashed before WP stores it in DB
        return wp_specialchars( $string, ENT_QUOTES, false, true );
    }

    // ###################################################################################################################
    // #########################################                      ####################################################
    // #########################################     Options Funcs    ####################################################
    // #########################################                      ####################################################
    // ###################################################################################################################

    // ###################################################################################################################
    // check, if given table id really exists
    function table_exists( $table_id ) {
        return isset( $this->tables[ $table_id ] );
    }

    // ###################################################################################################################
    function get_new_table_id() {
        // need to check new ID, because a higher one might have been used by manually changing an existing ID
        do {
            $this->options['last_id'] = $this->options['last_id'] + 1;
        } while ( $this->table_exists( $this->options['last_id'] ) );
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
        ksort( $this->tables, SORT_NUMERIC ); // sort for table IDs, as one with a small ID might have been appended
        update_option( $this->optionname['tables'], $this->tables );
    }

    // ###################################################################################################################
    function save_table( $table ) {
        if ( 0 < $table['id'] ) {
            // update last changes data
            $table['last_modified'] = current_time('mysql');
            $user = wp_get_current_user();
            $table['last_editor_id'] = $user->ID;
            
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
    function delete_table( $table_id ) {
        $this->tables[ $table_id ] = ( isset( $this->tables[ $table_id ] ) ) ? $this->tables[ $table_id ] : $this->optionname['table'] . '_' . $table_id;
        delete_option( $this->tables[ $table_id ] );
        unset( $this->tables[ $table_id ] );
        $this->update_tables();
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
                'page' => $this->page_slug,
                'action' => false,
                'item' => false
        );
        $url_params = array_merge( $default_params, $params );

        $action_url = add_query_arg( $url_params, $_SERVER['PHP_SELF'] );
        $action_url = ( true == $add_nonce ) ? wp_nonce_url( $action_url, $this->get_nonce( $url_params['action'], $url_params['item'] ) ) : $action_url;
        $action_url = clean_url( $action_url );
        return $action_url;
    }
    
    // ###################################################################################################################
    // #######################################                         ###################################################
    // #######################################    Plugin Management    ###################################################
    // #######################################                         ###################################################
    // ###################################################################################################################

    // ###################################################################################################################
    function create_class_instance( $class, $file, $folder = 'php' ) {
        if ( !class_exists( $class ) )
            include_once ( WP_TABLE_RELOADED_ABSPATH . $folder . '/' . $file );
        return new $class;
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
        $this->options['install_time'] = time();
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

        // 1b. step: update new default options before possibly adding them
        $this->default_options['install_time'] = time();

        // 2a. step: add/delete new/deprecated options by overwriting new ones with existing ones, if existant
		foreach ( $this->default_options as $key => $value )
            $new_options[ $key ] = ( isset( $this->options[ $key ] ) ) ? $this->options[ $key ] : $this->default_options[ $key ] ;

        // 2b., take care of css
        $new_options['use_custom_css'] = ( !isset( $this->options['use_custom_css'] ) && isset( $this->options['use_global_css'] ) ) ? $this->options['use_global_css'] : $this->options['use_custom_css'];

        // 3. step: update installed version number/empty update message cache
        $new_options['installed_version'] = $this->plugin_version;
        $new_options['update_message'] = array();
        
        // 4. step: save the new options
        $this->options = $new_options;
        $this->update_options();

        // update individual tables and their options
		$this->tables = get_option( $this->optionname['tables'] );
        foreach ( $this->tables as $id => $tableoptionname ) {
            $table = $this->load_table( $id );
            
            $temp_table = $this->default_table;
            
            // if table doesn't have visibility information, it gets them
            $rows = count( $table['data'] );
            $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
            $temp_table['visibility']['rows'] = array_fill( 0, $rows, false );
            $temp_table['visibility']['columns'] = array_fill( 0, $cols, false );
            
            foreach ( $temp_table as $key => $value )
                $new_table[ $key ] = ( isset( $table[ $key ] ) ) ? $table[ $key ] : $temp_table[ $key ] ;

            foreach ( $temp_table['options'] as $key => $value )
                $new_table['options'][ $key ] = ( isset( $table['options'][ $key ] ) ) ? $table['options'][ $key ] : $temp_table['options'][ $key ] ;

            $this->save_table( $new_table );
        }
    }
    
    // ###################################################################################################################
    // get remote plugin update message and show it right under the "upgrade automatically" message
    // $current and $new are passed by the do_action call and contain respective plugin version information
    function plugin_update_message( $current, $new ) {
        if ( !isset( $this->options['update_message'][$new->new_version] ) || empty( $this->options['update_message'][$new->new_version] ) ) {
            $message_text = '';
            $update_message = wp_remote_fopen( "http://tobias.baethge.com/dev/plugin/update/wp-table-reloaded/{$current['Version']}/{$new->new_version}/" );
            if ( false !== $update_message ) {
                if ( 1 == preg_match( '/<info>(.*?)<\/info>/is', $update_message, $matches ) )
                    $message_text = $matches[1];
            }
            $this->options['update_message'][$new->new_version] = $message_text;
            $this->update_options();
        }

        $message = $this->options['update_message'][$new->new_version];
        if ( !empty( $message ) )
            echo '<br />' . $this->safe_output( $message );
    }

    // ###################################################################################################################
    // initialize i18n support, load textdomain
    function init_language_support() {
        $language_directory = basename( dirname( __FILE__ ) ) . '/languages';
        load_plugin_textdomain( WP_TABLE_RELOADED_TEXTDOMAIN, false, $language_directory );
    }

    // ###################################################################################################################
    // enqueue javascript-file, with some jQuery stuff
    function add_manage_page_js() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';
        $jsfile = "admin-script{$suffix}.js";

        wp_register_script( 'wp-table-reloaded-admin-js', WP_TABLE_RELOADED_URL . 'admin/' . $jsfile, array( 'jquery' ), $this->plugin_version );
        // add all strings to translate here
        wp_localize_script( 'wp-table-reloaded-admin-js', 'WP_Table_Reloaded_Admin', array(
	  	        'str_UninstallCheckboxActivation' => __( 'Do you really want to activate this? You should only do that right before uninstallation!', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationLinkInsertURL' => __( 'URL of link to insert', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationLinkInsertText' => __( 'Text of link', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationLinkInsertExplain' => __( 'To insert the following link into a cell, just click the cell after closing this dialog.', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationImageInsertThickbox' => __( 'To insert an image, click the cell into which you want to insert the image.', WP_TABLE_RELOADED_TEXTDOMAIN ) . "\n" . __( 'The Media Library will open, from which you can select the desired image or insert the image URL.', WP_TABLE_RELOADED_TEXTDOMAIN ) . "\n" . sprintf( __( 'Click the "%s" button to insert the image.', WP_TABLE_RELOADED_TEXTDOMAIN ), attribute_escape( __('Insert into Post') ) ),
	  	        'str_BulkCopyTablesLink' => __( 'Do you want to copy the selected tables?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_BulkDeleteTablesLink' => __( 'The selected tables and all content will be erased. Do you really want to delete them?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_BulkImportwpTableTablesLink' => __( 'Do you really want to import the selected tables from the wp-Table plugin?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_CopyTableLink' => __( 'Do you want to copy this table?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteTableLink' => __( 'The complete table and all content will be erased. Do you really want to delete it?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteRowLink' => __( 'Do you really want to delete this row?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteColumnLink' => __( 'Do you really want to delete this column?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_ImportwpTableLink' => __( 'Do you really want to import this table from the wp-Table plugin?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_UninstallPluginLink_1' => __( 'Do you really want to uninstall the plugin and delete ALL data?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_UninstallPluginLink_2' => __( 'Are you really sure?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_ChangeTableID' => __( 'Do you really want to change the ID of the table?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_CFShortcodeMessage' => __( 'To show this Custom Data Field, use this shortcode:', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_TableShortcodeMessage' => __( 'To show this table, use this shortcode:', WP_TABLE_RELOADED_TEXTDOMAIN ),
                'l10n_print_after' => 'try{convertEntities(WP_Table_Reloaded_Admin);}catch(e){};'
        ) );
        wp_print_scripts( 'wp-table-reloaded-admin-js' );
    }

    // ###################################################################################################################
    // enqueue css-stylesheet-file for admin, if it exists
    function add_manage_page_css() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';
        $cssfile = "admin-style{$suffix}.css";
        wp_enqueue_style( 'wp-table-reloaded-admin-css', WP_TABLE_RELOADED_URL . 'admin/' . $cssfile, array(), $this->plugin_version );
    }

    // ###################################################################################################################
    // add button to visual editor
    function add_editor_button() {
        if ( 0 < count( $this->tables ) ) {
            $this->init_language_support();
            add_thickbox(); // we need thickbox to show the list
            add_action( 'admin_footer', array( &$this, 'add_editor_button_js' ) );
        }
    }

    // ###################################################################################################################
    // print out the JS in the admin footer
    function add_editor_button_js() {
        $params = array(
                'page' => $this->page_slug,
                'action' => 'ajax_list'
        );
        $ajax_url = add_query_arg( $params, 'tools.php' );
        $ajax_url = wp_nonce_url( $ajax_url, $this->get_nonce( $params['action'], false ) );
        $ajax_url = clean_url( $ajax_url );

        // currently doing this by hand in the footer, as footer-scripts are only available since WP 2.8
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';
        $jsfile = "admin-editor-buttons-script{$suffix}.js";
        wp_register_script( 'wp-table-reloaded-admin-editor-buttons-js', WP_TABLE_RELOADED_URL . 'admin/' . $jsfile, array( 'jquery', 'thickbox' ), $this->plugin_version );
        // add all strings to translate here
        wp_localize_script( 'wp-table-reloaded-admin-editor-buttons-js', 'WP_Table_Reloaded_Admin', array(
	  	        'str_EditorButtonCaption' => __( 'Table', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_EditorButtonAjaxURL' => $ajax_url,
                'l10n_print_after' => 'try{convertEntities(WP_Table_Reloaded_Admin);}catch(e){};'
        ) );
        wp_print_scripts( 'wp-table-reloaded-admin-editor-buttons-js' );
    }
    
    // ###################################################################################################################
    // output tablesorter execution js for all tables in wp_footer
    function output_tablesorter_js() {
        $jsfile =  'jquery.tablesorter.min.js'; // filename of the tablesorter script

        if ( 0 < count( $this->tables ) ) {
            wp_register_script( 'wp-table-reloaded-tablesorter-js', WP_TABLE_RELOADED_URL . 'js/' . $jsfile, array( 'jquery' ) );
            wp_print_scripts( 'wp-table-reloaded-tablesorter-js' );
            echo <<<JSSCRIPT
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready(function($){
$('#wp-table-reloaded-list').tablesorter({widgets: ['zebra'], headers: {0: {sorter: false},4: {sorter: false}}})
.find('.header').append('&nbsp;<span>&nbsp;&nbsp;&nbsp;</span>');
});
/* ]]> */
</script>
JSSCRIPT;
        }
    }

    // ###################################################################################################################
    // add admin footer text
    function add_admin_footer_text( $content ) {
        $content .= ' | ' . __( 'Thank you for using <a href="http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/">WP-Table Reloaded</a>.', WP_TABLE_RELOADED_TEXTDOMAIN );
        return $content;
    }

} // class WP_Table_Reloaded_Admin

?>