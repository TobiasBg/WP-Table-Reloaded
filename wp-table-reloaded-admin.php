<?php
/*
File Name: WP-Table Reloaded - Admin Class (see main file wp-table-reloaded.php)
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: Description: This plugin allows you to create and easily manage tables in the admin-area of WordPress. A comfortable backend allows an easy manipulation of table data. You can then include the tables into your posts, on your pages or in text-widgets by using a shortcode or a template tag function. Tables can be imported and exported from/to CSV, XML and HTML.
Version: 1.5-beta4
Author: Tobias B&auml;thge
Author URI: http://tobias.baethge.com/
Donate URI: http://tobias.baethge.com/donate/
*/

define( 'WP_TABLE_RELOADED_TEXTDOMAIN', 'wp-table-reloaded' );

class WP_Table_Reloaded_Admin {

    // ###################################################################################################################
    var $plugin_version = '1.5-beta4';
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
    var $allowed_actions = array( 'list', 'add', 'edit', 'bulk_edit', 'copy', 'delete', 'import', 'export', 'options', 'uninstall', 'info', 'hide_donate_nag', 'hide_welcome_message' ); // 'ajax_list', 'ajax_preview', 'ajax_delete_table', but handled separatly
    // current action, populated in load_manage_page
    var $action = 'list';
    // allowed actions in this class
    var $possible_admin_menu_parent_pages = array( 'tools.php', 'top-level', 'edit.php', 'edit-pages.php', 'plugins.php', 'index.php', 'options-general.php' );
    // init vars
    var $tables = array();
    var $options = array();

    // default values, could be different in future plugin versions
    var $default_options = array(
        'installed_version' => '0',
        'uninstall_upon_deactivation' => false,
        'show_exit_warning' => true,
        'growing_textareas' => true,
        'add_target_blank_to_links' => false,
        'enable_tablesorter' => true,
        'tablesorter_script' => 'datatables', // others are datatables-tabletools, tablesorter and tablesorter_extended
        'use_default_css' => true,
        'use_custom_css' => true,
        'custom_css' => '',
        'admin_menu_parent_page' => 'tools.php',
        'user_access_plugin' => 'author', // others are contributor, editor and admin
        'user_access_plugin_options' => 'author', // others are editor and admin
        'install_time' => 0,
        'show_donate_nag' => true,
        'show_welcome_message' => 0, // 0 = no message, 1 = install message, 2 = update message
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
            'table_footer' => false,
            'print_name' => false,
            'print_description' => false,
            'use_tablesorter' => true,
            'datatables_sort' => true,
            'datatables_paginate' => true,
            'datatables_lengthchange' => true,
            'datatables_filter' => true,
            'datatables_info' => true,
            'datatables_tabletools' => false,
            'datatables_customcommands' => ''
        ),
        'custom_fields' => array()
    );
    
    // class instances
    var $export_instance;
    var $import_instance;
    var $helper;
    
    // temporary variables
    var $hook = '';
    var $page_url = '';
    var $wp27 = false;

    // ###################################################################################################################
    // add admin-page to sidebar navigation, function called by PHP when class is constructed
    function WP_Table_Reloaded_Admin() {
        // needed for different calls to context translation (WP 27: _c, WP 28+: _x), soon to be removed
        $this->wp27 = version_compare( $GLOBALS['wp_version'], '2.8', '<');
    
        // load common functions, stored in separate file for better overview and maintenance
        $this->helper = $this->create_class_instance( 'WP_Table_Reloaded_Helper', 'wp-table-reloaded-helper.class.php' );
    
        // init plugin (means: load plugin options and existing tables)
        $this->init_plugin();

        // init variables to check whether we do valid AJAX
        $doing_ajax = defined( 'DOING_AJAX' ) ? DOING_AJAX : false;
        $valid_ajax_call = ( isset( $_GET['page'] ) && $this->page_slug == $_GET['page'] ) ? true : false;

        // real WP AJAX actions (other AJAX calls can not be done like this, because they are GET-requests)
        if ( $doing_ajax ) {
            add_action( 'wp_ajax_delete-wp-table-reloaded-table', array( &$this, 'do_action_ajax_delete_table') );
        }

        // have to check for possible "export all" request this early,
        // because otherwise http-headers will be sent by WP before we can send download headers
        if ( !$doing_ajax && $valid_ajax_call && isset( $_POST['export_all'] ) ) {
            // can be done in plugins_loaded, as no language support is needed
            add_action( 'plugins_loaded', array( &$this, 'do_action_export_all' ) );
            $doing_ajax = true;
        }
        // have to check for possible export file download request this early,
        // because otherwise http-headers will be sent by WP before we can send download headers
        if ( !$doing_ajax && $valid_ajax_call && isset( $_POST['download_export_file'] ) && 'true' == $_POST['download_export_file'] ) {
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

            // add remote message, if update available / add additional links on Plugins page, but only if page is plugins.php
            if ( 'plugins.php' == $GLOBALS['pagenow'] ) {
                if ( !$this->wp27 ) { // this is for WP 2.8 and later
                    add_action( 'in_plugin_update_message-' . WP_TABLE_RELOADED_BASENAME, array( &$this, 'add_plugin_update_message' ), 10, 2 );
                    add_filter( 'plugin_row_meta', array( &$this, 'add_plugin_row_meta_28' ), 10, 2);
                } else { // this is for WP 2.7
                    add_action( 'after_plugin_row_' . WP_TABLE_RELOADED_BASENAME, array( &$this, 'add_plugin_update_message_row' ), 10, 2 );
                    add_filter( 'plugin_action_links', array( &$this, 'add_plugin_row_meta_27' ), 10, 2);
                }
            }
        }
    }

    // ###################################################################################################################
    // add page, and what happens when page is loaded or shown
    function add_manage_page() {
        // user needs at least this capability to view WP-Table Reloaded config page
        // capabilities from http://codex.wordpress.org/Roles_and_Capabilities
        switch ( $this->options['user_access_plugin'] ) {
            case 'admin':
                $min_needed_capability = 'manage_options';
                break;
            case 'editor':
                $min_needed_capability = 'publish_pages';
                break;
            case 'author':
                $min_needed_capability = 'publish_posts';
                break;
            case 'contributor':
                $min_needed_capability = 'edit_posts';
                break;
            default:
                $min_needed_capability = 'manage_options';
        }
        $min_needed_capability = apply_filters( 'wp_table_reloaded_min_needed_capability', $min_needed_capability ); // plugins may filter/change this though
        
        $display_name = 'WP-Table Reloaded'; // the name that is displayed in the admin menu on the left
        $display_name = apply_filters( 'wp_table_reloaded_plugin_display_name', $display_name ); // can be filtered to something shorter maybe

        $admin_menu_parent_page = $this->options['admin_menu_parent_page']; // default: add menu entry under "Tools" (i.e. Management)
        $admin_menu_parent_page = apply_filters( 'wp_table_reloaded_admin_menu_parent_page', $admin_menu_parent_page ); // overwrite settings from Option page

        // check if still valid after possible change by filter
        if ( false == in_array( $admin_menu_parent_page, $this->possible_admin_menu_parent_pages ) )
            $admin_menu_parent_page = 'tools.php';

        // Top-Level menu is created in different function. All others are created with the filename as a parameter
        if ( 'top-level' == $admin_menu_parent_page ) {
            $this->hook = add_menu_page( 'WP-Table Reloaded', $display_name, $min_needed_capability, $this->page_slug, array( &$this, 'show_manage_page' ) );
            $this->page_url = 'admin.php';
        } else {
            $this->hook = add_submenu_page( $admin_menu_parent_page, 'WP-Table Reloaded', $display_name, $min_needed_capability, $this->page_slug, array( &$this, 'show_manage_page' ) );
            $this->page_url = $admin_menu_parent_page;
        }

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
		add_filter( 'admin_footer_text', array (&$this->helper, 'add_admin_footer_text') );

        // init language support
        $this->init_language_support();

        // get and check action parameter from passed variables
        $default_action = 'list';
        $default_action = apply_filters( 'wp_table_reloaded_default_action', $default_action );
        $this->allowed_actions = apply_filters( 'wp_table_reloaded_allowed_actions', $this->allowed_actions );
        $action = ( isset( $_REQUEST['action'] ) && !empty( $_REQUEST['action'] ) ) ? $_REQUEST['action'] : $default_action;
        // check if action is in allowed actions and if method is callable, if yes, call it
        if ( in_array( $action, $this->allowed_actions ) )
            $this->action = $action;

        // need thickbox to be able to show table in iframe on certain action pages (but not all)
        $thickbox_actions = array ( 'list', 'edit', 'copy', 'delete', 'bulk_edit', 'hide_donate_nag', 'hide_welcome_message', 'import' ); // those all might show the "List of tables"
        if ( in_array( $action, $thickbox_actions ) ) {
            add_thickbox();
            wp_enqueue_script( 'media-upload' ); // for resizing the thickbox
        }

        // after get_action, because needs action parameter
        add_contextual_help( $this->hook, $this->helper->get_contextual_help_string( $this->action ) );
    }

    // ###################################################################################################################
    function show_manage_page() {
    
        // do WP plugin action (before action is fired) -> can stop further plugin execution by returning true
        $overwrite = apply_filters( 'wp_table_reloaded_action_pre_' . $this->action, false );
        if ( $overwrite )
            return;
    
        // call appropriate action, $this->action is populated in load_manage_page
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

        switch( $this->options['show_welcome_message'] ) {
            case 0:
                $message = false;
                break;
            case 1:
                $message = sprintf( __( 'Welcome to WP-Table Reloaded %s. If you encounter any questions or problems, please refer to the <a href="%s">FAQ</a>, the <a href="%s">documentation</a>, and the <a href="%s">support</a> section.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->options['installed_version'], 'http://tobias.baethge.com/go/wp-table-reloaded/faq/', 'http://tobias.baethge.com/go/wp-table-reloaded/documentation/', 'http://tobias.baethge.com/go/wp-table-reloaded/support/' );
                break;
            case 2:
                $plugin_options_url = $this->get_action_url( array( 'action' => 'options' ), false );
                $message = sprintf( __( 'Thank you for upgrading to WP-Table Reloaded %s.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->options['installed_version'] ) . ' ' . __( 'This version adds support for the DataTables JavaScript library (with features like sorting, pagination, and filtering) and includes a lot more enhancements.', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' ' . sprintf( __( 'Please read the <a href="%s">release announcement</a> for more information and check your settings in the <a href="%s">%s</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/release-announcement/', $plugin_options_url, __( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ) ) . '<br/>' . sprintf( __( 'If you like the new features and enhancements, I would appreciate a small <a href="%s">donation</a>. Thank you.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/donate/' );
                break;
            default:
                $message = false;
        }
        if ( $message ) {
            $hide_welcome_message_url = $this->get_action_url( array( 'action' => 'hide_welcome_message' ), true );
            $this->helper->print_header_message( $message . '<br/><br/>' . sprintf( '<a href="%s" style="font-weight:normal;">%s</a>', $hide_welcome_message_url, __( 'Hide this message', WP_TABLE_RELOADED_TEXTDOMAIN ) ) );
        }

        if ( $this->may_print_donate_nag() ) {
            $donate_url = 'http://tobias.baethge.com/go/wp-table-reloaded/donate/message/';
            $donated_true_url = $this->get_action_url( array( 'action' => 'hide_donate_nag', 'user_donated' => true ), true );
            $donated_false_url = $this->get_action_url( array( 'action' => 'hide_donate_nag', 'user_donated' => false ), true );
            $this->helper->print_header_message(
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
            $table['data'] = $this->helper->create_empty_table( $rows, $cols );
            $table['visibility']['rows'] = array_fill( 0, $rows, false );
            $table['visibility']['columns'] = array_fill( 0, $cols, false );
            $table['name'] = $_POST['table']['name'];
            $table['description'] = $_POST['table']['description'];

            $this->save_table( $table );

            $this->helper->print_header_message( sprintf( __( 'Table &quot;%s&quot; added successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $table['name'] ) ) );
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
                // save table options (checkboxes!), only checked checkboxes are submitted (then as true)
                $table['options']['alternating_row_colors'] = isset( $_POST['table']['options']['alternating_row_colors'] );
                $table['options']['first_row_th'] = isset( $_POST['table']['options']['first_row_th'] );
                $table['options']['table_footer'] = isset( $_POST['table']['options']['table_footer'] );
                $table['options']['print_name'] = isset( $_POST['table']['options']['print_name'] );
                $table['options']['print_description'] = isset( $_POST['table']['options']['print_description'] );
                $table['options']['use_tablesorter'] = isset( $_POST['table']['options']['use_tablesorter'] );
                $table['options']['datatables_sort'] = isset( $_POST['table']['options']['datatables_sort'] );
                $table['options']['datatables_paginate'] = isset( $_POST['table']['options']['datatables_paginate'] );
                $table['options']['datatables_lengthchange'] = isset( $_POST['table']['options']['datatables_lengthchange'] );
                $table['options']['datatables_filter'] = isset( $_POST['table']['options']['datatables_filter'] );
                $table['options']['datatables_info'] = isset( $_POST['table']['options']['datatables_info'] );
                $table['options']['datatables_tabletools'] = isset( $_POST['table']['options']['datatables_tabletools'] );
                // $table['options']['datatables_customcommands'] is an input type=text field that is always submitted

                // save visibility settings (checkboxes!)
                foreach ( $table['data'] as $row_idx => $row )
                    $table['visibility']['rows'][$row_idx] = ( isset( $_POST['table']['visibility']['rows'][$row_idx] ) && ( 'true' == $_POST['table']['visibility']['rows'][$row_idx] ) );
                ksort( $table['visibility']['rows'], SORT_NUMERIC );
                foreach ( $table['data'][0] as $col_idx => $col )
                    $table['visibility']['columns'][$col_idx] = ( isset( $_POST['table']['visibility']['columns'][$col_idx] ) && ( 'true' == $_POST['table']['visibility']['columns'][$col_idx] ) );
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
            case 'delete_rows':
                $table_id = $_POST['table']['id'];
                $delete_rows = ( isset( $_POST['table_select']['rows'] ) ) ? $_POST['table_select']['rows'] : array();
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
                $message = _n( 'Row could not be deleted.', 'Rows could not be deleted.', count( $delete_rows ), WP_TABLE_RELOADED_TEXTDOMAIN ); // only used if deletion fails below
                if ( ( 1 < $rows ) && ( 0 < count( $delete_rows ) ) && ( count( $delete_rows ) < $rows ) ) {
                    // remove rows and re-index
                    foreach( $delete_rows as $row_idx => $value) {
                        unset( $table['data'][$row_idx] );
                        unset( $table['visibility']['rows'][$row_idx] );
                    }
                    $table['data'] = array_merge( $table['data'] );
                    $table['visibility']['rows'] = array_merge( $table['visibility']['rows'] );
                    $message = _n( 'Row deleted successfully.', 'Rows deleted successfully.', count( $delete_rows ), WP_TABLE_RELOADED_TEXTDOMAIN );
                }
                $this->save_table( $table );
                break;
            case 'delete_cols':
                $table_id = $_POST['table']['id'];
                $delete_columns = ( isset( $_POST['table_select']['columns'] ) ) ? $_POST['table_select']['columns'] : array();
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
                $message = _n( 'Column could not be deleted.', 'Columns could not be deleted.', count( $delete_columns ), WP_TABLE_RELOADED_TEXTDOMAIN ); // only used if deletion fails below
                if ( ( 1 < $cols ) && ( 0 < count( $delete_columns ) ) && ( count( $delete_columns ) < $cols ) ) {
                    foreach ( $table['data'] as $row_idx => $row ) {
                        // remove columns and re-index
                        foreach( $delete_columns as $col_idx => $value) {
                            unset( $table['data'][$row_idx][$col_idx] );
                        }
                        $table['data'][$row_idx] = array_merge( $table['data'][$row_idx] );
                    }
                    foreach( $delete_columns as $col_idx => $value) {
                        unset( $table['visibility']['columns'][$col_idx] );
                    }
                    $table['visibility']['columns'] = array_merge( $table['visibility']['columns'] );
                    $message = _n( 'Column deleted successfully.', 'Columns deleted successfully.', count( $delete_columns ), WP_TABLE_RELOADED_TEXTDOMAIN );
                }
                $this->save_table( $table );
                break;
            case 'insert_rows': // insert row before each selected row
                $table_id = $_POST['table']['id'];
                $insert_rows = ( isset( $_POST['table_select']['rows'] ) ) ? $_POST['table_select']['rows'] : array();
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;

                // insert rows and re-index
                $row_change = 0; // row_change is growing parameter, needed because indices change
                $new_row = array( array_fill( 0, $cols, '' ) );
                foreach( $insert_rows as $row_idx => $value) {
                    $row_id = $row_idx + $row_change;
                    // init new empty row (with all columns) and insert it before row with key $row_id
                    array_splice( $table['data'], $row_id, 0, $new_row );
                    array_splice( $table['visibility']['rows'], $row_id, 0, false );
                    $row_change++;
                }
                
                $this->save_table( $table );
                $message = _n( 'Row inserted successfully.', 'Rows inserted successfully.', count( $insert_rows ), WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'insert_cols': // insert column before each selected column
                $table_id = $_POST['table']['id'];
                $insert_columns = ( isset( $_POST['table_select']['columns'] ) ) ? $_POST['table_select']['columns'] : array();
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;

                // insert cols and re-index
                $new_col = '';
                foreach ( $table['data'] as $row_idx => $row ) {
                    $col_change = 0; // col_change is growing parameter, needed because indices change
                    foreach( $insert_columns as $col_idx => $value) {
                        $col_id = $col_idx + $col_change;
                        array_splice( $table['data'][$row_idx], $col_id, 0, $new_col );
                        $col_change++;
                    }
                }
                $col_change = 0; // col_change is growing parameter, needed because indices change
                foreach( $insert_columns as $col_idx => $value) {
                    $col_id = $col_idx + $col_change;
                    array_splice( $table['visibility']['columns'], $col_id, 0, false );
                    $col_change++;
                }

                $this->save_table( $table );
                $message = _n( 'Column inserted successfully.', 'Columns inserted successfully.', count( $insert_columns ), WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'append_rows':
                $table_id = $_POST['table']['id'];
                $number = ( isset( $_POST['insert']['row']['number'] ) && ( 0 < $_POST['insert']['row']['number'] ) ) ? $_POST['insert']['row']['number'] : 1;
                $row_id = $_POST['insert']['row']['id'];
                $table = $this->load_table( $table_id );
                $rows = count( $table['data'] );
                $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;
                // init new empty row (with all columns) and insert it before row with key $row_id
                $new_rows = $this->helper->create_empty_table( $number, $cols, '' );
                $new_rows_visibility = array_fill( 0, $number, false );
                array_splice( $table['data'], $row_id, 0, $new_rows );
                array_splice( $table['visibility']['rows'], $row_id, 0, $new_rows_visibility );
                $this->save_table( $table );
                $message = _n( 'Row added successfully.', 'Rows added successfully.', $number, WP_TABLE_RELOADED_TEXTDOMAIN );
                break;
            case 'append_cols':
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
                $message = _n( 'Column added successfully.', 'Columns added successfully.', $number, WP_TABLE_RELOADED_TEXTDOMAIN );
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

            $this->helper->print_header_message( $message );
            if ( 'save_back' == $subaction ) {
                $this->do_action_list();
            } else {
                $this->print_edit_table_form( $table['id'] );
            }
        } elseif ( isset( $_GET['table_id'] ) && $this->table_exists( $_GET['table_id'] ) ) {
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
                    $message = _n( 'Table copied successfully.', 'Tables copied successfully.', count( $_POST['tables'] ), WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                case 'delete': // see do_action_delete for explanations
                    foreach ( $_POST['tables'] as $table_id ) {
                        $this->delete_table( $table_id );
                    }
                    $message = _n( 'Table deleted successfully.', 'Tables deleted successfully.', count( $_POST['tables'] ), WP_TABLE_RELOADED_TEXTDOMAIN );
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
                    $message = _n( 'Table imported successfully.', 'Tables imported successfully.', count( $_POST['tables'] ), WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                default:
                    break;
                }

            } else {
                $message = __( 'You did not select any tables!', WP_TABLE_RELOADED_TEXTDOMAIN );
            }
            $this->helper->print_header_message( $message );
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

            $this->helper->print_header_message( sprintf( __( 'Table &quot;%s&quot; copied successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $new_table['name'] ) ) );
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
                $this->helper->print_header_message( sprintf( __( 'Table &quot;%s&quot; deleted successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $table['name'] ) ) );
                $this->do_action_list();
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
                $this->helper->print_header_message( $message );
                $this->print_edit_table_form( $table_id );
                break;
            default:
                $this->helper->print_header_message( __( 'Delete failed.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->do_action_list();
            } // end switch
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
                $this->helper->print_header_message( __( 'Table could not be imported.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->print_import_table_form();
                return;
            }

            $table = array_merge( $this->default_table, $imported_table );

            if ( isset( $_POST['import_addreplace'] ) && isset( $_POST['import_addreplace_table'] ) && ( 'replace' == $_POST['import_addreplace'] ) && $this->table_exists( $_POST['import_addreplace_table'] ) ) {
                $existing_table = $this->load_table( $_POST['import_addreplace_table'] );
                $table['id'] = $existing_table['id'];
                $table['name'] = $existing_table['name'];
                $table['description'] = $existing_table['description'];
                $success_message = sprintf( __( 'Table %s (%s) replaced successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $table['name'] ), $this->helper->safe_output( $table['id'] ) );
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
                $this->helper->print_header_message( $success_message );
                $this->print_edit_table_form( $table['id'] );
            } else {
                $this->helper->print_header_message( __( 'Table could not be imported.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
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

            $this->helper->print_header_message( __( 'Table imported successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
            $this->print_edit_table_form( $table['id'] );
        } elseif ( isset( $_POST['import_wp_table_reloaded_dump_file'] ) ) {
            check_admin_referer( $this->get_nonce( 'import_dump' ) );
            
            // check if user is admin
            if ( false == current_user_can( 'manage_options' ) ) {
                $this->helper->print_header_message( __( 'You do not have sufficient rights to perform this action.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->print_plugin_options_form();
                return;
            }
            
            // check if file was uploaded
            if ( true === empty( $_FILES['dump_file']['tmp_name'] ) ) {
                $this->helper->print_header_message( __( 'You did not upload a WP-Table Reloaded dump file.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->print_plugin_options_form();
                return;
            }
            // read data from file and rewrite string to array
            $import_data = file_get_contents( $_FILES['dump_file']['tmp_name'] );
            $import = unserialize( $import_data );
            // check if import dump is not empty
            if ( empty( $import ) ) {
                $this->helper->print_header_message( __( 'The uploaded dump file is empty. Please upload a valid dump file.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->print_plugin_options_form();
                return;
            }

            // NEED TO ADD SOME MORE CHECKS HERE, IF IMPORT IS VALID AND COMPLETE!

            // remove all existing data
            foreach ( $this->tables as $id => $tableoptionname )
                delete_option( $tableoptionname );
            delete_option( $this->optionname['tables'] );
            delete_option( $this->optionname['options'] );

            // import and save options
            $this->options = $import['options'];
            $this->update_options();
            // import and save table overview
            $this->tables = $import['table_info'];
            $this->update_tables();
            // import each table
            foreach ( $this->tables as $table_id => $tableoptionname ) {
                $dump_table = $import['tables'][ $table_id ];
                update_option( $tableoptionname, $dump_table );
            }
            // check if plugin update is necessary, compared to imported data
            if ( version_compare( $this->options['installed_version'], $this->plugin_version, '<') ) {
                $this->plugin_update();
            }

            $this->helper->print_header_message( __( 'All Tables, Settings and Options were successfully imported.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
            $this->do_action_list();
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
                $filename = $table_to_export['id'] . '-' . $table_to_export['name'] . '-' . date( 'Y-m-d' ) . '.' . $_POST['export_format'];
                $this->helper->prepare_download( $filename, strlen( $exported_table ), 'text/' . $_POST['export_format'] );
                echo $exported_table;
                exit;
            } else {
                $this->helper->print_header_message( sprintf( __( 'Table &quot;%s&quot; exported successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $table_to_export['name'] ) ) );
                $this->print_export_table_form( $_POST['table_id'], $exported_table );
            }
        } else {
            $table_id = isset( $_REQUEST['table_id'] ) ? $_REQUEST['table_id'] : 0;
            $this->print_export_table_form( $table_id );
        }
    }
    
    // ###################################################################################################################
    function do_action_export_all() {
        if ( isset( $_POST['export_all'] ) ) {
            check_admin_referer( $this->get_nonce( 'export_all' ) );

            // store all data, like tables, options, etc. in a single array
            $export = array();
            $export['table_info'] = $this->tables;
            foreach ( $this->tables as $table_id => $tableoptionname ) {
                $dump_table = $this->load_table( $table_id );
                $export['tables'][ $table_id ] = $dump_table;
            }
            $export['options'] = $this->options;
            
            // serialize the export array to a string
            $export_dump = serialize( $export );

            $filename = 'wp-table-reloaded-export-' . date( 'Y-m-d' ) . '.dump';
            $this->helper->prepare_download( $filename, strlen( $export_dump ), 'text/data' );
            echo $export_dump;
            exit;

        }
    }

    // ###################################################################################################################
    function do_action_options() {
        if ( isset( $_POST['submit'] ) && isset( $_POST['options'] ) ) {
            check_admin_referer( $this->get_nonce( 'options' ) );

            // check if user can access Plugin Options
            if ( false == $this->user_has_access( 'plugin-options' ) ) {
                $this->helper->print_header_message( __( 'You do not have sufficient rights to access the Plugin Options.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
                $this->print_plugin_options_form();
                return;
            }

            $new_options = $_POST['options'];
            
            // checkboxes: option value is defined by whether option isset (e.g. was checked) or not
            $this->options['show_exit_warning'] = isset( $new_options['show_exit_warning'] );
            $this->options['growing_textareas'] = isset( $new_options['growing_textareas'] );
            $this->options['enable_tablesorter'] = isset( $new_options['enable_tablesorter'] );
            $this->options['use_default_css'] = isset( $new_options['use_default_css'] );
            $this->options['use_custom_css'] = isset( $new_options['use_custom_css'] );
            $this->options['add_target_blank_to_links'] = isset( $new_options['add_target_blank_to_links'] );
            $this->options['tablesorter_script'] = $new_options['tablesorter_script'];

            // only save these settings, if user is administrator, as they are admin options
            if ( current_user_can( 'manage_options' ) ) {
                // plugin uninstall
                $this->options['uninstall_upon_deactivation'] = isset( $new_options['uninstall_upon_deactivation'] );
                // admin menu parent page
                if ( in_array( $new_options['admin_menu_parent_page'], $this->possible_admin_menu_parent_pages ) )
                    $this->options['admin_menu_parent_page'] = $new_options['admin_menu_parent_page'];
                else
                    $this->options['admin_menu_parent_page'] = 'tools.php';
                // user access to plugin
                if ( in_array( $new_options['user_access_plugin'], array( 'admin', 'editor', 'author', 'contributor' ) ) )
                    $this->options['user_access_plugin'] = $new_options['user_access_plugin'];
                else
                    $this->options['user_access_plugin'] = 'admin'; // better set it high, if something is wrong
                // user access to plugin options
                if ( in_array( $new_options['user_access_plugin_options'], array( 'admin', 'editor', 'author' ) ) )
                    $this->options['user_access_plugin_options'] = $new_options['user_access_plugin_options'];
                else
                    $this->options['user_access_plugin_options'] = 'admin'; // better set it high, if something is wrong
            }
            // adjust $this->page_url, so that next page load will work
            $this->page_url = ( 'top-level' == $this->options['admin_menu_parent_page'] ) ? 'admin.php' : $this->options['admin_menu_parent_page'] ;
            
            // clean up CSS style input (if user enclosed it into <style...></style>
            if ( isset( $new_options['custom_css'] ) ) {
                    if ( 1 == preg_match( '/<style.*?>(.*?)<\/style>/is', stripslashes( $new_options['custom_css'] ), $matches ) )
                        $new_options['custom_css'] = $matches[1]; // if found, take match as style to save
                    $this->options['custom_css'] = $new_options['custom_css'];
            }

            $this->update_options();

            $this->helper->print_header_message( __( 'Options saved successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        }
        $this->print_plugin_options_form();
    }
    
    // ###################################################################################################################
    function do_action_uninstall() {
        check_admin_referer( $this->get_nonce( 'uninstall' ) );

        // check if user is admin
        if ( false == current_user_can( 'manage_options' ) ) {
            $this->helper->print_header_message( __( 'You do not have sufficient rights to perform this action.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
            $this->print_plugin_options_form();
            return;
        }

        // everything shall be deleted (manual uninstall)
        $this->options['uninstall_upon_deactivation'] = true;
        $this->update_options();

        $plugin = WP_TABLE_RELOADED_BASENAME;
        deactivate_plugins( $plugin );
        if ( false !== get_option( 'recently_activated', false ) )
            update_option( 'recently_activated', array( $plugin => time() ) + (array)get_option( 'recently_activated' ) );

        $this->helper->print_page_header( __( 'WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->helper->print_header_message( __( 'Plugin deactivated successfully.', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        echo "<p>" . __( 'All tables, data and options were deleted. You may now remove the plugin\'s subfolder from your WordPress plugin folder.', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</p>";
        $this->helper->print_page_footer();
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

        $this->helper->print_page_header( __( 'List of Tables', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        ?>
        <div style="clear:both;"><p>
        <?php _e( 'This is a list of all available tables.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You may insert a table into a post or page here.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br />
		<?php printf( __( 'Click the &quot;%s&quot; link after the desired table and the corresponding shortcode will be inserted into the editor (<strong>[table id=&lt;ID&gt; /]</strong>).', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Insert', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?>
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
                $bg_style = ( 1 == ($bg_style_index % 2) ) ? ' class="alternate"' : '';

                // get name and description to show in list
                $table = $this->load_table( $id );
                    $name = $this->helper->safe_output( $table['name'] );
                    $description = $this->helper->safe_output( $table['description'] );
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
            echo "<div style=\"clear:both;\"><p>" . __( 'No tables were found.', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</p></div>";
        }
        $this->helper->print_page_footer();

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

            $this->helper->print_page_header( sprintf( __( 'Preview of Table &quot;%s&quot; (ID %s)', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $table['name'] ), $this->helper->safe_output( $table['id'] ) ) );
            ?>
            <div style="clear:both;"><p>
            <?php _e( 'This is a preview of your table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'Because of CSS styling, the table might look different on your page!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'The JavaScript libraries are also not available in this preview.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php printf( __( 'To insert the table into a page, post or text-widget, copy the shortcode <strong>[table id=%s /]</strong> and paste it into the corresponding place in the editor.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $table['id'] ) ); ?>
            </p></div>
            <div style="clear:both;">
            <?php
                $WP_Table_Reloaded_Frontend = $this->create_class_instance( 'WP_Table_Reloaded_Frontend', 'wp-table-reloaded-frontend.php', '' );
                $atts = array( 'id' => $_GET['table_id'] );
                echo $WP_Table_Reloaded_Frontend->handle_content_shortcode_table( $atts );
            ?>
            </div>
            <?php
            $this->helper->print_page_footer();
        } else {
            ?>
            <div style="clear:both;"><p style="width:97%;"><?php _e( 'There is no table with this ID!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p></div>
            <?php
        }

        // necessary to stop page building here!
        exit;
    }

    // ###################################################################################################################
    function do_action_ajax_delete_table() {
        check_ajax_referer( $this->get_nonce( 'delete', 'table' ) );
        $table_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        $this->delete_table( $table_id );
        die( '1' );
    }

    // ###################################################################################################################
    // hide 1.5 update notification
    function do_action_hide_welcome_message() {
        check_admin_referer( $this->get_nonce( 'hide_welcome_message' ) );

        $this->options['show_welcome_message'] = 0;
        $this->update_options();

        $this->do_action_list();
    }
    
    // ###################################################################################################################
    // user donated
    function do_action_hide_donate_nag() {
        check_admin_referer( $this->get_nonce( 'hide_donate_nag' ) );

        $this->options['show_donate_nag'] = false;
        $this->update_options();

        if ( isset( $_GET['user_donated'] ) && true == $_GET['user_donated'] ) {
            $this->helper->print_header_message( __( 'Thank you very much! Your donation is highly appreciated. You just contributed to the further development of WP-Table Reloaded!', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        } else {
            $this->helper->print_header_message( sprintf( __( 'No problem! I still hope you enjoy the benefits that WP-Table Reloaded brings to you. If you should want to change your mind, you\'ll always find the &quot;%s&quot; button on the <a href="%s">WP-Table Reloaded website</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Donate', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/website/' ) );
        }
        
        $this->do_action_list();
    }
    
    // ###################################################################################################################
    // ##########################################                     ####################################################
    // ##########################################     Print Forms     ####################################################
    // ##########################################                     ####################################################
    // ###################################################################################################################

    // ###################################################################################################################
    // list all tables
    function print_list_tables_form()  {
        $this->helper->print_page_header( __( 'List of Tables', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' &lsaquo; ' . __( 'WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'list' );
        ?>
        <div style="clear:both;"><p><?php _e( 'This is a list of all available tables.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You may add, edit, copy, delete or preview tables here.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br /><br/><?php printf( __( 'To insert the table into a page, post or text-widget, copy the shortcode <strong>[table id=%s /]</strong> and paste it into the corresponding place in the editor.', WP_TABLE_RELOADED_TEXTDOMAIN ), '&lt;ID&gt;' ); ?> <?php _e( 'Each table has a unique ID that needs to be adjusted in that shortcode.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php printf( __( 'You can also click the button &quot;%s&quot; in the editor toolbar to select and insert a table.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></p></div>
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
                    <th scope="col" style="display:none;"></th>
                    <th scope="col"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Last Modified', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                </tr>
            </thead>
            <tfoot>
                <tr>
                    <th class="check-column" scope="col"><input type="checkbox" /></th>
                    <th scope="col"><?php _e( 'ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col" style="display:none;"></th>
                    <th scope="col"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                    <th scope="col"><?php _e( 'Last Modified', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></th>
                </tr>
            </tfoot>
            <?php
            echo "<tbody id=\"the-list\" class=\"list:wp-table-reloaded-table\">\n";
            $bg_style_index = 0;
            foreach ( $this->tables as $id => $tableoptionname ) {
                $bg_style_index++;
                $bg_style = ( 1 == ($bg_style_index % 2) ) ? ' class="even"' : ' class="odd"';

                // get name and description to show in list
                $table = $this->load_table( $id );
                    $name = ( !empty( $table['name'] ) ) ? $this->helper->safe_output( $table['name'] ) : __( '(no name)', WP_TABLE_RELOADED_TEXTDOMAIN );
                    $description = ( !empty( $table['description'] ) ) ? $this->helper->safe_output( $table['description'] ) : __( '(no description)', WP_TABLE_RELOADED_TEXTDOMAIN );
                    $last_modified = $this->helper->format_datetime( $table['last_modified'] );
                    $last_editor = $this->helper->get_last_editor( $table['last_editor_id'] );
                    if ( !empty( $last_editor ) )
                        $last_editor = __( 'by', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' ' . $last_editor;
                unset( $table );

                $edit_url = $this->get_action_url( array( 'action' => 'edit', 'table_id' => $id ), false );
                $copy_url = $this->get_action_url( array( 'action' => 'copy', 'table_id' => $id ), true );
                $export_url = $this->get_action_url( array( 'action' => 'export', 'table_id' => $id ), false );
                $delete_url = $this->get_action_url( array( 'action' => 'delete', 'table_id' => $id, 'item' => 'table' ), true );
                $preview_url = $this->get_action_url( array( 'action' => 'ajax_preview', 'table_id' => $id ), true );

                echo "<tr id=\"wp-table-reloaded-table-{$id}\" {$bg_style}>\n";
                echo "\t<td class=\"check-column no-wrap\"><input type=\"checkbox\" name=\"tables[]\" value=\"{$id}\" /></td>\n";
                echo "\t<td class=\"no-wrap table-id\">{$id}</td>\n";
                echo "\t<td style=\"display:none;\">{$name}</td>\n";
                echo "\t<td>\n";
                echo "\t\t<a title=\"" . sprintf( __( 'Edit %s', WP_TABLE_RELOADED_TEXTDOMAIN ), "&quot;{$name}&quot;" ) . "\" class=\"row-title\" href=\"{$edit_url}\">{$name}</a>\n";
                echo "\t\t<div class=\"row-actions no-wrap\">";
                echo "<a href=\"{$edit_url}\">" . __( 'Edit', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                $shortcode = "[table id={$id} /]";
                echo "<a href=\"javascript:void(0);\" class=\"table_shortcode_link\" title=\"{$shortcode}\">" . __( 'Shortcode', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                echo "<a class=\"copy_table_link\" href=\"{$copy_url}\">" . __( 'Copy', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                echo "<a href=\"{$export_url}\">" . __( 'Export', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>" . " | ";
                echo "<span class=\"delete\"><a class=\"delete:the-list:wp-table-reloaded-table-{$id} delete_table_link\" href=\"{$delete_url}\">" . __( 'Delete', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a></span>" . " | ";
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
        <p class="submit" style="clear:both;"><?php _e( 'Bulk actions:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>  <input type="submit" name="submit[copy]" class="button-primary bulk_copy_tables" value="<?php _e( 'Copy Tables', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" /> <input type="submit" name="submit[delete]" class="button-primary bulk_delete_tables" value="<?php _e( 'Delete Tables', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </p>

        </form>
        <?php
            echo "</div>";
        } else { // end if $tables
            $add_url = $this->get_action_url( array( 'action' => 'add' ), false );
            $import_url = $this->get_action_url( array( 'action' => 'import' ), false );
            echo "<div style=\"clear:both;\"><p>" . __( 'No tables were found.', WP_TABLE_RELOADED_TEXTDOMAIN ) . '<br/><br/><strong>' . sprintf( __( 'You should <a href="%s">add</a> or <a href="%s">import</a> a table to get started!', WP_TABLE_RELOADED_TEXTDOMAIN ), $add_url, $import_url ) . "</strong></p></div>";
        }
        $this->helper->print_page_footer();
        // add tablesorter script
        add_action( 'admin_footer', array( &$this, 'output_tablesorter_js' ) );
    }

    // ###################################################################################################################
    function print_add_table_form() {
        // Begin Add Table Form
        $this->helper->print_page_header( __( 'Add new Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'add' );
        ?>
        <div style="clear:both;">
        <p><?php _e( 'To add a new table, enter its name, a description (optional) and the number of rows and columns.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'You may also add, insert or delete rows and columns later.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
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
        $this->helper->print_page_footer();
    }

    // ###################################################################################################################
    function print_edit_table_form( $table_id ) {
        $table = $this->load_table( $table_id );

        $rows = count( $table['data'] );
        $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;

        $this->helper->print_page_header( sprintf( __( 'Edit Table &quot;%s&quot; (ID %s)', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $table['name'] ), $this->helper->safe_output( $table['id'] ) ) );
        $this->print_submenu_navigation( 'edit' );
        ?>
        <div style="clear:both;"><p><?php _e( 'On this page, you can edit the content of the table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'It is also possible to change the table structure by inserting, deleting, moving, and swapping columns and rows.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br />
		<?php printf( __( 'To insert the table into a page, post or text-widget, copy the shortcode <strong>[table id=%s /]</strong> and paste it into the corresponding place in the editor.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $table_id ) ); ?></p></div>
        <form id="wp_table_reloaded_edit_table" method="post" action="<?php echo $this->get_action_url( array( 'action' => 'edit', 'table_id' => $table_id ), false ); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'edit' ) ); ?>
        <input type="hidden" name="table[id]" value="<?php echo $table['id']; ?>" />

        <div class="postbox<?php echo $this->helper->postbox_closed( 'table-information', false ); ?>">
        <h3 class="hndle"><span><?php _e( 'Table Information', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <table class="wp-table-reloaded-table-information">
        <tr valign="top">
            <th scope="row"><label for="table_id"><?php _e( 'Table ID', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table_id" id="table_id" value="<?php echo $this->helper->safe_output( $table['id'] ); ?>" style="width:80px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table_name"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="table[name]" id="table_name" value="<?php echo $this->helper->safe_output( $table['name'] ); ?>" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="table_description"><?php _e( 'Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
            <td><textarea name="table[description]" id="table_description" rows="15" cols="40" style="height:84px;"><?php echo $this->helper->safe_output( $table['description'] ); ?></textarea></td>
        </tr>
        <?php if ( !empty( $table['last_editor_id'] ) ) { ?>
        <tr valign="top">
            <th scope="row"><?php _e( 'Last Modified', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><?php echo $this->helper->format_datetime( $table['last_modified'] ); ?> <?php _e( 'by', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php echo $this->helper->get_last_editor( $table['last_editor_id'] ); ?></td>
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
            <div class="postbox<?php echo $this->helper->postbox_closed( 'table-contents', false ); ?>">
            <h3 class="hndle"><span><?php _e( 'Table Contents', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
            <div class="inside">
            <table class="widefat" style="width:auto;" id="table_contents">
                <tbody>
                <?php
                    // first row
                    echo "<tr class=\"table-head\">\n";
                        echo "\t<td class=\"check-column\"><input type=\"checkbox\" style=\"display:none;\" /></td>\n";
                        foreach ( $table['data'][0] as $col_idx => $cell_content ) {
                            $letter = chr( ord( 'A' ) + $col_idx );
                            $hidden = ( isset( $table['visibility']['columns'][$col_idx] ) && true == $table['visibility']['columns'][$col_idx] ) ? 'true': '' ;
                            $col_hidden = ( isset( $table['visibility']['columns'][$col_idx] ) && true == $table['visibility']['columns'][$col_idx] ) ? ' column-hidden' : '';
                            echo "\t<td class=\"edit_col_{$col_idx}{$col_hidden}\">{$letter}</td>\n";
                        }
                        echo "\t<td>&nbsp;</td>\n";
                    echo "</tr>\n";

                    // data rows, with checkboxes to select rows
                foreach ( $table['data'] as $row_idx => $table_row ) {
                    $row_hidden = ( isset( $table['visibility']['rows'][$col_idx] ) && true == $table['visibility']['rows'][$row_idx] ) ? ' row-hidden' : '';
                    echo "<tr class=\"edit_row_{$row_idx}{$row_hidden}\">\n";
                        $output_idx = $row_idx + 1; // start counting at 1 on output
                        $hidden = ( isset( $table['visibility']['rows'][$row_idx] ) && true == $table['visibility']['rows'][$row_idx] ) ? 'true': '' ;
                        echo "\t<td class=\"check-column\"><label for=\"select_row_{$row_idx}\">{$output_idx} </label><input type=\"checkbox\" name=\"table_select[rows][{$row_idx}]\" id=\"select_row_{$row_idx}\" value=\"true\" /><input type=\"hidden\" name=\"table[visibility][rows][{$row_idx}]\" id=\"edit_row_{$row_idx}\" class=\"cell-hide\" value=\"{$hidden}\" /></td>\n";
                        foreach ( $table_row as $col_idx => $cell_content ) {
                            $cell_content = $this->helper->safe_output( $cell_content );
                            $cell_name = "table[data][{$row_idx}][{$col_idx}]";
                            $col_hidden = ( isset( $table['visibility']['columns'][$col_idx] ) && true == $table['visibility']['columns'][$col_idx] ) ? ' column-hidden' : '';
                            echo "\t<td class=\"edit_col_{$col_idx}{$col_hidden}\"><textarea rows=\"1\" cols=\"20\" name=\"{$cell_name}\">{$cell_content}</textarea></td>\n";
                        }
                        echo "\t<th scope=\"row\">{$output_idx}</th>\n";
                    echo "</tr>\n";
                }

                    // last row (with checkboxes to select columns)
                    echo "<tr class=\"table-foot\">\n";
                        echo "\t<td>&nbsp;</td>\n";
                        foreach ( $table['data'][0] as $col_idx => $cell_content ) {
                            $letter = chr( ord( 'A' ) + $col_idx );
                            $hidden = ( isset( $table['visibility']['columns'][$col_idx] ) && true == $table['visibility']['columns'][$col_idx] ) ? 'true': '' ;
                            $col_hidden = ( isset( $table['visibility']['columns'][$col_idx] ) && true == $table['visibility']['columns'][$col_idx] ) ? ' column-hidden' : '';
                            echo "\t<td class=\"check-column edit_col_{$col_idx}{$col_hidden}\"><label for=\"select_col_{$col_idx}\">{$letter} </label><input type=\"checkbox\" name=\"table_select[columns][{$col_idx}]\" id=\"select_col_{$col_idx}\" value=\"true\" /><input type=\"hidden\" name=\"table[visibility][columns][{$col_idx}]\" id=\"edit_col_{$col_idx}\" class=\"cell-hide\" value=\"{$hidden}\" /></td>\n";
                        }
                        echo "\t<td>&nbsp;</td>\n";
                    echo "</tr>\n";
                ?>
                </tbody>
            </table>
        </div>
        </div>
        <?php } //endif 0 < $rows/$cols ?>

        <div class="postbox<?php echo $this->helper->postbox_closed( 'table-data-manipulation', false ); ?>">
        <h3 class="hndle"><span><?php _e( 'Data Manipulation', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
    <table class="wp-table-reloaded-data-manipulation widefat">

        <tr><td>
                <a id="a-insert-link" class="button-primary" href="javascript:void(0);"><?php _e( 'Insert Link', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
                <a id="a-insert-image" href="<?php echo admin_url( 'media-upload.php' ); ?>?type=image&amp;tab=library&amp;TB_iframe=true" class="thickbox button-primary" title="<?php _e( 'Insert Image', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" onclick="javascript:return false;"><?php _e( 'Insert Image', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
        </td><td>
        <?php if ( 1 < $rows ) { // sort form ?>
            <?php
            $col_select = '<select name="sort[col]">';
            foreach ( $table['data'][0] as $col_idx => $cell_content )
                $col_select .= "<option value=\"{$col_idx}\">" . ( chr( ord( 'A' ) + $col_idx ) ) . "</option>";
            $col_select .= '</select>';

            $sort_order_select = '<select name="sort[order]">';
            $sort_order_select .= "<option value=\"ASC\">" . __( 'ascending', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</option>";
            $sort_order_select .= "<option value=\"DESC\">" . __( 'descending', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</option>";
            $sort_order_select .= '</select>';

            printf( __( 'Sort table by column %s in %s order', WP_TABLE_RELOADED_TEXTDOMAIN ), $col_select, $sort_order_select );
        ?>
            <input type="submit" name="submit[sort]" class="button-primary" value="<?php _e( 'Sort', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php } // end if sort form ?>
        </td></tr>

        <tr><td>
            <?php _e( 'Selected rows:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <a id="a-hide-rows" class="button-primary" href="javascript:void(0);"><?php echo ( $this->wp27 ) ? _c( 'Hide|item', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'item', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
            <a id="a-unhide-rows" class="button-primary" href="javascript:void(0);"><?php echo ( $this->wp27 ) ? _c( 'Unhide|item', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Unhide', 'item', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
        </td><td>
            <?php _e( 'Selected columns:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <a id="a-hide-columns" class="button-primary" href="javascript:void(0);"><?php echo ( $this->wp27 ) ? _c( 'Hide|item', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'item', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
            <a id="a-unhide-columns" class="button-primary" href="javascript:void(0);"><?php echo ( $this->wp27 ) ? _c( 'Unhide|item', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Unhide', 'item', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
        </td></tr>

        <tr><td>
            <?php // don't show delete link for last and only row
                $row_disabled = ( 1 < $rows ) ? '' : 'disabled="disabled" ';
                $col_disabled = ( 1 < $cols ) ? '' : 'disabled="disabled" ';
            ?>
            <?php _e( 'Selected rows:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <input id="button-insert-rows" type="submit" name="submit[insert_rows]" class="button-primary" value="<?php _e( 'Insert row', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
            <input id="button-delete-rows" type="submit" name="submit[delete_rows]" class="button-primary" value="<?php _e( 'Delete', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" <?php echo $row_disabled; ?>/>
            <br/>
            <?php _e( 'Selected columns:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <input id="button-insert-columns" type="submit" name="submit[insert_cols]" class="button-primary" value="<?php _e( 'Insert column', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
            <input id="button-delete-columns" type="submit" name="submit[delete_cols]" class="button-primary" value="<?php _e( 'Delete', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" <?php echo $col_disabled; ?>/>
        </td><td>
        <?php
            // add rows/columns buttons
            echo "<input type=\"hidden\" name=\"insert[row][id]\" value=\"{$rows}\" /><input type=\"hidden\" name=\"insert[col][id]\" value=\"{$cols}\" />";

            $row_insert = '<input type="text" name="insert[row][number]" value="1" style="width:30px" />';
            $col_insert = '<input type="text" name="insert[col][number]" value="1" style="width:30px" />';
        ?>
        <?php printf( __( 'Add %s row(s)', WP_TABLE_RELOADED_TEXTDOMAIN ), $row_insert ); ?>
        <input type="submit" name="submit[append_rows]" class="button-primary" value="<?php _e( 'Add', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <br/>
        <?php printf( __( 'Add %s column(s)', WP_TABLE_RELOADED_TEXTDOMAIN ), $col_insert ); ?>
        <input type="submit" name="submit[append_cols]" class="button-primary" value="<?php _e( 'Add', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </td></tr>
        
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

            printf( __( 'Swap rows %s and %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $row1_select, $row2_select );
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

            printf( __( 'Swap columns %s and %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $col1_select, $col2_select );
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

            printf( __( 'Move row %s %s row %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $row1_select, $move_where_select, $row2_select );
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

            printf( __( 'Move column %s %s column %s', WP_TABLE_RELOADED_TEXTDOMAIN ), $col1_select, $move_where_select, $col2_select );
            ?>
            <input type="submit" name="submit[move_col]" class="button-primary" value="<?php _e( 'Move', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php } // end if form move col ?>
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

        <div class="postbox<?php echo $this->helper->postbox_closed( 'table-styling-options', false ); ?>">
        <h3 class="hndle"><span><?php _e( 'Table Styling Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'These settings will only be used for this table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'Alternating row colors', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][alternating_row_colors]" id="table_options_alternating_row_colors"<?php echo ( true == $table['options']['alternating_row_colors'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_alternating_row_colors"><?php _e( 'Every second row has an alternating background color.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Table head', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][first_row_th]" id="table_options_first_row_th"<?php echo ( true == $table['options']['first_row_th'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_first_row_th"><?php _e( 'The first row of your table is the table head (HTML tag &lt;th&gt;).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Table footer', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][table_footer]" id="table_options_table_footer"<?php echo ( true == $table['options']['table_footer'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_table_footer"><?php _e( 'The last row of your table is the table footer (HTML tag &lt;th&gt;).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][print_name]" id="table_options_print_name"<?php echo ( true == $table['options']['print_name'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_print_name"><?php _e( 'The Table Name will be written above the table (HTML tag &lt;h2&gt;).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Table Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][print_description]" id="table_options_print_description"<?php echo ( true == $table['options']['print_description'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_print_description"><?php _e( 'The Table Description will be written under the table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top" id="options_use_tablesorter">
            <th scope="row"><?php _e( 'Use JavaScript library', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td>
            <?php
            switch ( $this->options['tablesorter_script'] ) {
                case 'datatables':
                    $js_library = 'DataTables';
                    $js_library_text = __( 'You can change further settings for this library below.' , WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                case 'datatables-tabletools':
                    $js_library = 'DataTables+TableTools';
                    $js_library_text = __( 'You can change further settings for this library below.' , WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                case 'tablesorter':
                    $js_library = 'Tablesorter';
                    $js_library_text = __( 'The table will then be sortable by the visitor.' , WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                case 'tablesorter_extended':
                    $js_library = 'Tablesorter Extended';
                    $js_library_text = __( 'The table will then be sortable by the visitor.' , WP_TABLE_RELOADED_TEXTDOMAIN );
                    break;
                default;
                    $js_library = 'DataTables';
                    $js_library_text = __( 'You can change further settings for this library below.' , WP_TABLE_RELOADED_TEXTDOMAIN );
            }
            ?>
            <input type="checkbox" name="table[options][use_tablesorter]" id="table_options_use_tablesorter"<?php echo ( true == $table['options']['use_tablesorter'] ) ? ' checked="checked"': '' ; ?><?php echo ( false == $this->options['enable_tablesorter'] || ( false == $table['options']['first_row_th'] && 'datatables' != $this->options['tablesorter_script'] && 'datatables-tabletools' != $this->options['tablesorter_script'] ) ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_use_tablesorter"><?php printf( __( 'Yes, use the &quot;%s&quot; JavaScript library with this table.', WP_TABLE_RELOADED_TEXTDOMAIN ), $js_library ); ?> <?php echo $js_library_text; ?><?php if ( !$this->options['enable_tablesorter'] ) { ?><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<small><?php printf( __( 'You must enable the use of a JavaScript library on the &quot;%s&quot; screen first.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></small><?php } ?></label></td>
        </tr>
        </table>
        </div>
        </div>
        
        <?php
        $datatables_enabled = $this->options['enable_tablesorter'] && ( 'datatables' == $this->options['tablesorter_script'] || 'datatables-tabletools' == $this->options['tablesorter_script'] );
        $tabletools_enabled = $this->options['enable_tablesorter'] && ( 'datatables-tabletools' == $this->options['tablesorter_script'] );
        ?>
        <div class="postbox<?php echo $this->helper->postbox_closed( 'datatables-features', true ); ?>">
        <h3 class="hndle"><span><?php _e( 'DataTables JavaScript Features', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'You can enable certain features for the DataTables JavaScript library here.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'More information on these features can be found on the <a href="http://www.datatables.net/">DataTables website</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <?php if ( !$datatables_enabled ) { ?>
        <p><strong><?php printf( __( 'You can currently not change these options, because you have not enabled the &quot;DataTables&quot; or the &quot;DataTables+TableTools&quot; JavaScript library on the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?><br/><?php _e( 'It is not possible to use these features with the &quot;Tablesorter&quot; or &quot;Tablesorter Extended&quot; libraries.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></strong></p>
        <?php } ?>
        <table class="wp-table-reloaded-options wp-table-reloaded-datatables-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'Sorting', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_sort]" id="table_options_datatables_sort"<?php echo ( true == $table['options']['datatables_sort'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$datatables_enabled || false == $table['options']['use_tablesorter'] || false == $table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_sort"><?php _e( 'Yes, enable sorting of table data by the visitor.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Pagination', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_paginate]" id="table_options_datatables_paginate"<?php echo ( true == $table['options']['datatables_paginate'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$datatables_enabled || false == $table['options']['use_tablesorter'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_paginate"><?php _e( 'Yes, enable pagination of the table (showing only a certain number of rows) by the visitor.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Length Change', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_lengthchange]" id="table_options_datatables_lengthchange"<?php echo ( true == $table['options']['datatables_lengthchange'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$datatables_enabled || false == $table['options']['use_tablesorter'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_lengthchange"><?php _e( 'Yes, allow visitor to change the number of rows shown when using pagination.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Filtering', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_filter]" id="table_options_datatables_filter"<?php echo ( true == $table['options']['datatables_filter'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$datatables_enabled || false == $table['options']['use_tablesorter'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_filter"><?php _e( 'Yes, enable the visitor to filter or search the table. Only rows with the search word in them are shown.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Info Bar', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_info]" id="table_options_datatables_info"<?php echo ( true == $table['options']['datatables_info'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$datatables_enabled || false == $table['options']['use_tablesorter'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_info"><?php _e( 'Yes, show the table information display. This shows information and statistics about the currently visible data, including filtering.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'TableTools', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_tabletools]" id="table_options_datatables_tabletools"<?php echo ( true == $table['options']['datatables_tabletools'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$tabletools_enabled || false == $table['options']['use_tablesorter'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_tabletools">
            <?php _e( 'Yes, activate the TableTools functions (Copy to Clipboard, Save to CSV, Save to XLS, Print Table) for this table.', WP_TABLE_RELOADED_TEXTDOMAIN );
            if ( !$tabletools_enabled ) { echo '<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<small>('; _e( 'This option can only be used with the &quot;DataTables+TableTools&quot; JavaScript library.', WP_TABLE_RELOADED_TEXTDOMAIN ); echo ')</small>';}
            ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Custom Commands', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="text" name="table[options][datatables_customcommands]" id="table_options_datatables_customcommands"<?php echo ( !$datatables_enabled || false == $table['options']['use_tablesorter'] ) ? ' disabled="disabled"': '' ; ?> value="<?php echo $this->helper->safe_output( $table['options']['datatables_customcommands'] ); ?>" style="width:100%" /> <label for="table_options_datatables_customcommands"><small><br/><?php _e( 'Enter additional DataTables JavaScript parameters that will be included with the script call here.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> (<?php _e( 'For advanced use only. Read the <a href="http://www.datatables.net/">DataTables documentation</a> before.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</small></label></td>
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

        <div class="postbox<?php echo $this->helper->postbox_closed( 'custom-data-fields', true ); ?>">
        <h3 class="hndle"><span><?php _e( 'Custom Data Fields', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <?php _e( 'Custom Data Fields can be used to add extra metadata to a table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'For example, this could be information about the source or the creator of the data.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
        <br/>
        <?php printf( __( 'You can show this data in the same way as tables by using the shortcode <strong>[table-info id=%s field="&lt;field-name&gt;" /]</strong>.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $table_id ) ); ?>
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
                    $name = $this->helper->safe_output( $name );
                    $value = $this->helper->safe_output( $value );
                    echo "<tr>\n";
                        echo "\t<td style=\"width:10%;\">{$name}</td>\n";
                        echo "\t<td style=\"width:75%;\"><textarea rows=\"1\" cols=\"20\" name=\"table[custom_fields][{$name}]\" style=\"width:90%\">{$value}</textarea></td>\n";
                        $delete_cf_url = $this->get_action_url( array( 'action' => 'delete', 'table_id' => $table['id'], 'item' => 'custom_field', 'element_id' => $name ), true );
                        echo "\t<td style=\"width:15%;min-width:200px;\">";
                        echo "<a href=\"{$delete_cf_url}\">" . __( 'Delete Field', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
                        $shortcode = "[table-info id=" . $this->helper->safe_output( $table_id ) . " field=&quot;{$name}&quot; /]";
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
    $this->helper->print_page_footer();
    }

    // ###################################################################################################################
    function print_import_table_form() {
        // Begin Import Table Form
        $this->helper->print_page_header( __( 'Import a Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'import' );
        ?>
        <div style="clear:both;">
        <p><?php _e( 'WP-Table Reloaded can import tables from existing data.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'This may be a CSV, XML or HTML file, each with a certain structure.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><br/><?php _e( 'To import an existing table, please select its format and the source for the import.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php if( 0 < count( $this->tables ) ) _e( 'You can also decide, if you want to import it as a new table or replace an existing table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
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
                    $name = $this->helper->safe_output( $table['name'] );
                    //$description = $this->helper->safe_output( $table['description'] );
                unset( $table );
                echo "<option" . ( isset( $_POST['import_addreplace_table'] ) && ( $id == $_POST['import_addreplace_table'] ) ? ' selected="selected"': '' ) . " value=\"{$id}\">{$name} (ID {$id})</option>";
            }
        ?>
        </select></td>
        </tr>
        <?php } ?>
        <tr valign="top" class="tr-import-from">
            <th scope="row"><?php _e( 'Select source for Import', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
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
            <th scope="row"><label for="import_url"><?php _e( 'URL to Import Table from', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label></th>
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
                $bg_style = ( 1 == ($bg_style_index % 2) ) ? ' class="alternate"' : '';

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
        $this->helper->print_page_footer();
    }

    // ###################################################################################################################
    function print_export_table_form( $table_id, $output = false ) {
        // Begin Export Table Form
        $table = $this->load_table( $table_id );

        $rows = count( $table['data'] );
        $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;

        $this->helper->print_page_header( __( 'Export a Table', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'export' );
        ?>
        <div style="clear:both;">
        <p><?php _e( 'It is recommended to export and backup the data of important tables regularly.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'Select the table, the desired export format and (for CSV only) a delimiter.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You may choose to download the export file. Otherwise it will be shown on this page.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'Be aware that only the table data, but no options or settings are exported.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php printf( __( 'To backup all tables, including their settings, at once use the &quot;%s&quot; button in the &quot;%s&quot;.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Create and Download Dump File', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></p>
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
                    $name = $this->helper->safe_output( $table['name'] );
                    //$description = $this->helper->safe_output( $table['description'] );
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
        </select> <small>(<?php _e( 'Only needed for CSV export.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</small></td>
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
            echo "<div style=\"clear:both;\"><p>" . __( 'No tables were found.', WP_TABLE_RELOADED_TEXTDOMAIN ) . '<br/>' . sprintf( __( 'You should <a href="%s">add</a> or <a href="%s">import</a> a table to get started!', WP_TABLE_RELOADED_TEXTDOMAIN ), $add_url, $import_url ) . "</p></div>";
        }

        $this->helper->print_page_footer();
    }

    // ###################################################################################################################
    function print_plugin_options_form() {
        // Begin Add Table Form
        $this->helper->print_page_header( __( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' &lsaquo; ' . __( 'WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'options' );
        ?>
        <div style="clear:both;">
        <p><?php _e( 'WP-Table Reloaded has several options which affect the plugin behavior in different areas.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
        <?php _e( 'Frontend Options influence the output and used features of tables in pages, posts or text-widgets.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php printf( __( 'The Backend Options control the plugin\'s admin area, e.g. the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Edit Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?> <?php _e( 'Administrators have access to further Admin Options.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>

        <?php
        // only show certain settings, if user is administrator, as they are admin options
        $is_admin = current_user_can( 'manage_options' );

        // check if user can access Plugin Options
        if ( $this->user_has_access( 'plugin-options' ) ) { ?>

        <div style="clear:both;">
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'options' ) ); ?>
        
        <div class="postbox<?php echo $this->helper->postbox_closed( 'frontend-plugin-options', false ); ?>">
<h3 class="hndle"><span><?php _e( 'Frontend Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
<div class="inside">
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'JavaScript library', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[enable_tablesorter]" id="options_enable_tablesorter"<?php echo ( true == $this->options['enable_tablesorter'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_enable_tablesorter"><?php _e( 'Yes, enable the use of a JavaScript library.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'WP-Table Reloaded includes three JavaScript libraries that can add useful features, like sorting, pagination, and filtering, to a table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row">&nbsp;</th>
            <td><?php _e( 'Select the library to use:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <select id="options_tablesorter_script" name="options[tablesorter_script]"<?php echo ( false == $this->options['enable_tablesorter'] ) ? ' disabled="disabled"': '' ; ?>>
                <option<?php echo ( 'datatables' == $this->options['tablesorter_script'] ) ? ' selected="selected"': ''; ?> value="datatables">DataTables (<?php _e( 'recommended', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</option>
                <option<?php echo ( 'datatables-tabletools' == $this->options['tablesorter_script'] ) ? ' selected="selected"': ''; ?> value="datatables-tabletools">DataTables+TableTools</option>
                <option<?php echo ( 'tablesorter' == $this->options['tablesorter_script'] ) ? ' selected="selected"': ''; ?> value="tablesorter">Tablesorter</option>
                <option<?php echo ( 'tablesorter_extended' == $this->options['tablesorter_script'] ) ? ' selected="selected"': ''; ?> value="tablesorter_extended">Tablesorter Extended</option>
        </select> <?php printf( __( '(You can read more about each library\'s features on the <a href="%s">plugin\'s website</a>.)', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/website/' ); ?></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Default CSS', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[use_default_css]" id="options_use_default_css"<?php echo ( true == $this->options['use_default_css'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_use_default_css">
            <?php _e( 'Yes, include and load the plugin\'s default CSS Stylesheets. This is highly recommended, if you use one of the JavaScript libraries!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
             </label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Custom CSS', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[use_custom_css]" id="options_use_custom_css"<?php echo ( true == $this->options['use_custom_css'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_use_custom_css">
            <?php _e( 'Yes, include and load the following custom CSS commands.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'This should be used to change the table layout and styling.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
             </label></td>
        </tr>
        <tr valign="top">
            <th scope="row">&nbsp;</th>
            <td><textarea name="options[custom_css]" id="options_custom_css" rows="10" cols="40"<?php echo ( false == $this->options['use_custom_css'] ) ? ' disabled="disabled"': '' ; ?>><?php echo $this->helper->safe_output( $this->options['custom_css'] ); ?></textarea><br/><br/>
            <?php printf( __( 'You can get styling examples from the <a href="%s">plugin\'s website</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/website/' ); ?> <?php printf( __( 'Information on available CSS selectors can be found in the <a href="%s">documentation</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/documentation/' ); ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Links in new window', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[add_target_blank_to_links]" id="options_add_target_blank_to_links"<?php echo ( true == $this->options['add_target_blank_to_links'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_add_target_blank_to_links"><?php printf( __( 'Yes, open links that are inserted with the &quot;%s&quot; button on the &quot;%s&quot; screen in a new browser window <strong>from now on</strong>.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Insert Link', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Edit Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></label></td>
        </tr>
        </table>
        </div>
        </div>
        
        <div class="postbox<?php echo $this->helper->postbox_closed( 'backend-plugin-options', false ); ?>">
        <h3 class="hndle"><span><?php _e( 'Backend Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'Exit warning', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[show_exit_warning]" id="options_show_exit_warning"<?php echo ( true == $this->options['show_exit_warning'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_show_exit_warning"><?php printf( __( 'Yes, show a warning message, if I leave the &quot;%s&quot; screen and have not yet saved my changes.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Edit Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Growing textareas', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[growing_textareas]" id="options_growing_textareas"<?php echo ( true == $this->options['growing_textareas'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_growing_textareas"><?php printf( __( 'Yes, enlarge the textareas on the &quot;%s&quot; screen when they are focussed.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Edit Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></label></td>
        </tr>
        
        </table>
        </div>
        </div>
        
        <div class="postbox<?php echo $this->helper->postbox_closed( 'admin-plugin-options', ( $is_admin) ? false : true ); ?>">
        <h3 class="hndle"><span><?php _e( 'Admin Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'This area are only available to site administrators!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><?php if ( !$is_admin ) echo ' ' . __( 'You can therefore not change these options.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <table class="wp-table-reloaded-options">

        <?php // the strings don't have a textdomain, because they shall be the same as in the original WP admin menu (and those strings are in WP's textdomain) ?>
        <tr valign="top">
            <th scope="row"><?php _e( 'Plugin Access', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><?php _e( 'To access WP-Table Reloaded, a user needs to be:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <select id="options_user_access_plugin" name="options[user_access_plugin]"<?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?>>
                <option<?php echo ( 'admin' == $this->options['user_access_plugin'] ) ? ' selected="selected"': ''; ?> value="admin"><?php echo ( $this->wp27 ) ? _c( 'Administrator|User role' ) : _x( 'Administrator', 'User role' ); ?></option>
                <option<?php echo ( 'editor' == $this->options['user_access_plugin'] ) ? ' selected="selected"': ''; ?> value="editor"><?php echo ( $this->wp27 ) ? _c( 'Editor|User role' ) : _x( 'Editor', 'User role' ); ?></option>
                <option<?php echo ( 'author' == $this->options['user_access_plugin'] ) ? ' selected="selected"': ''; ?> value="author"><?php echo ( $this->wp27 ) ? _c( 'Author|User role' ) : _x( 'Author', 'User role' ); ?></option>
                <option<?php echo ( 'contributor' == $this->options['user_access_plugin'] ) ? ' selected="selected"': ''; ?> value="author"><?php echo ( $this->wp27 ) ? _c( 'Contributor|User role' ) : _x( 'Contributor', 'User role' ); ?></option>
        </select></td>
        </tr>

        <?php // the strings don't have a textdomain, because they shall be the same as in the original WP admin menu (and those strings are in WP's textdomain) ?>
        <tr valign="top">
            <th scope="row"><?php _e( 'Plugin Options Access', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><?php _e( 'To access the Plugin Options of WP-Table Reloaded, a user needs to be:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <select id="options_user_access_plugin_options" name="options[user_access_plugin_options]"<?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?>>
                <option<?php echo ( 'admin' == $this->options['user_access_plugin_options'] ) ? ' selected="selected"': ''; ?> value="admin"><?php echo ( $this->wp27 ) ? _c( 'Administrator|User role' ) : _x( 'Administrator', 'User role' ); ?></option>
                <option<?php echo ( 'editor' == $this->options['user_access_plugin_options'] ) ? ' selected="selected"': ''; ?> value="editor"><?php echo ( $this->wp27 ) ? _c( 'Editor|User role' ) : _x( 'Editor', 'User role' ); ?></option>
                <option<?php echo ( 'author' == $this->options['user_access_plugin_options'] ) ? ' selected="selected"': ''; ?> value="author"><?php echo ( $this->wp27 ) ? _c( 'Author|User role' ) : _x( 'Author', 'User role' ); ?></option>
        </select><br/><small>(<?php _e( 'Admin Options, Dump file Import, and Manual Plugin Uninstall are always accessible by Administrators only, regardless of this setting.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</small></td>
        </tr>

        <?php // the strings don't have a textdomain, because they shall be the same as in the original WP admin menu (and those strings are in WP's textdomain) ?>
        <tr valign="top">
            <th scope="row"><?php _e( 'Admin menu entry', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><?php _e( 'WP-Table Reloaded shall be shown in this section of the admin menu:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <select id="options_admin_menu_parent_page" name="options[admin_menu_parent_page]"<?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?>>
                <option<?php echo ( 'tools.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="tools.php"><?php _e( 'Tools' ); ?> (<?php _e( 'recommended', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</option>
                <option<?php echo ( 'edit.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="edit.php"><?php _e( 'Posts' ); ?></option>
                <option<?php echo ( 'edit-pages.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="edit-pages.php"><?php _e( 'Pages' ); ?></option>
                <option<?php echo ( 'plugins.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="plugins.php"><?php _e( 'Plugins' ); ?></option>
                <option<?php echo ( 'options-general.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="options-general.php"><?php _e( 'Settings' ); ?></option>
                <option<?php echo ( 'index.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="index.php"><?php _e( 'Dashboard' ); ?></option>
                <option<?php echo ( 'top-level' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="top-level"><?php _e( 'Top-Level', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></option>
        </select><br/><small>(<?php _e( 'Change will take effect after another page load after saving.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</small></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e( 'Remove upon Deactivation', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[uninstall_upon_deactivation]" id="options_uninstall_upon_deactivation"<?php echo ( true == $this->options['uninstall_upon_deactivation'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="options_uninstall_upon_deactivation"><?php _e( 'Yes, remove all plugin related data from the database when the plugin is deactivated.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <small>(<?php _e( 'Should be activated directly before deactivation only!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</small></label></td>
        </tr>
        
        </table>
        </div>
        </div>

        <input type="hidden" name="options[submit]" value="true" /><?php // need this, so that options get saved ?>
        <input type="hidden" name="action" value="options" />
        <p class="submit">
        <input type="submit" name="submit[form]" class="button-primary" value="<?php _e( 'Save Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php
        $list_url = $this->get_action_url( array( 'action' => 'list' ) );
        echo " <a class=\"button-primary\" href=\"{$list_url}\">" . __( 'Cancel', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
        ?>
        </p>

        </form>
        </div>
        
        <h2><?php _e( 'WP-Table Reloaded Data Export and Backup', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></h2>

        <div style="clear:both;">
        <p style="margin-bottom:20px;"><?php _e( 'WP-Table Reloaded can export and import a so-called dump file that contains all tables, their settings and the plugin\'s options.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'This file can be used as a backup or to move all data to another WordPress site.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        <div class="postbox<?php echo $this->helper->postbox_closed( 'dump-file-export', true ); ?>">
        <h3 class="hndle"><span><?php _e( 'Export a dump file', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'To export all Tables and their settings, click the button below to generate and download a dump file.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( '<strong>Warning</strong>: Do <strong>not</strong> edit the content of that file under any circumstances as you will destroy the file!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'export_all' ) ); ?>
        <input type="submit" name="export_all" class="button-primary" value="<?php _e( 'Create and Download Dump File', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </form>
        </div>
        </div>

        <div class="postbox<?php echo $this->helper->postbox_closed( 'dump-file-import', true ); ?>">
        <h3 class="hndle"><span><?php _e( 'Import a dump file', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'To import a WP-Table Reloaded dump file and restore the included data, upload the file from your computer.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'All current data of this WP-Table Reloaded installation (Tables, Options, Settings) <strong>WILL BE OVERWRITTEN</strong> with the data from the file!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'Do not proceed, if you do not understand this!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'It is highly recommended to export and backup the data of this installation before importing another dump file (see above).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <?php
            if ( $is_admin ) {
            ?>
                <form method="post" enctype="multipart/form-data" action="<?php echo $this->get_action_url(); ?>">
                <?php wp_nonce_field( $this->get_nonce( 'import_dump' ) ); ?>
                <label for="dump_file"><?php _e( 'Select Dump File', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label> <input name="dump_file" id="dump_file" type="file"<?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?> />
                <input type="hidden" name="action" value="import" />
                <input id="import_wp_table_reloaded_dump_file" type="submit" name="import_wp_table_reloaded_dump_file" class="button-primary" value="<?php _e( 'Import Dump File', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
                </form>
            <?php
            } else {
                echo '<p>' . __( 'This area are only available to site administrators!', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</p>';
            }
        ?>
        </div>
        </div>

        <h2 style="margin-top:40px;"><?php _e( 'Manually Uninstall WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></h2>
        <div style="clear:both;">
            <p><?php _e( 'Uninstalling <strong>will permanently delete</strong> all tables, data, and options, that belong to WP-Table Reloaded from the database, including all tables you added or imported.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'You will manually need to remove the plugin\'s files from the plugin folder afterwards.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'Be very careful with this and only click the button if you know what you are doing!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <?php
            if ( $is_admin ) {
                $uninstall_url = $this->get_action_url( array( 'action' => 'uninstall' ), true );
                echo " <a class=\"button-secondary\" id=\"uninstall_plugin_link\" href=\"{$uninstall_url}\">" . __( 'Uninstall Plugin WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
            } else {
                echo '<p>' . __( 'This area are only available to site administrators!', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</p>';
            }
        ?>
        </div>
        <br style="clear:both;" />
        
        <?php // end check if user can access Plugin Options
        } else { ?>
        <div style="clear:both;">
        <p><strong><?php _e( 'You do not have sufficient rights to access the Plugin Options.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></strong></p>
        </div>
        <?php // end alternate text, if user can not access Plugin Options
        }
        
        $this->helper->print_page_footer();
    }

    // ###################################################################################################################
    function print_plugin_info_form() {
        // Begin Add Table Form
        $this->helper->print_page_header( __( 'About WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) );
        $this->print_submenu_navigation( 'info' );
        ?>

        <div style="clear:both;">
        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Plugin Purpose', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php _e( 'WP-Table Reloaded allows you to create and manage tables in the admin-area of WordPress.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'Those tables may contain strings, numbers and even HTML (e.g. to include images or links).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You can then show the tables in your posts, on your pages or in text-widgets by using a shortcode.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'If you want to show your tables anywhere else in your theme, you can use a template tag function.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Usage', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php _e( 'At first you should add or import a table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'This means that you either let the plugin create an empty table for you or that you load an existing table from either a CSV, XML or HTML file.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p><p><?php _e( 'Then you can edit your data or change the structure of your table (e.g. by inserting or deleting rows or columns, swaping rows or columns or sorting them) and select specific table options like alternating row colors or whether to print the name or description, if you want.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'To easily add a link or an image to a cell, use the provided buttons. Those will ask you for the URL and a title. Then you can click into a cell and the corresponding HTML will be added to it for you.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p><p><?php printf( __( 'To insert the table into a page, post or text-widget, copy the shortcode <strong>[table id=%s /]</strong> and paste it into the corresponding place in the editor.', WP_TABLE_RELOADED_TEXTDOMAIN ), '&lt;ID&gt;' ); ?> <?php printf( __( 'You can also select the desired table from a list (after clicking the button &quot;%s&quot; in the editor toolbar) and the corresponding shortcode will be added for you.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></p><p><?php _e( 'Tables can be styled by changing and adding CSS commands.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'The plugin ships with default CSS Stylesheets, which can be customized with own code or replaced with other Stylesheets.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php __( 'For this, each table is given certain CSS classes that can be used as CSS selectors.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php printf ( __( 'Please see the <a href="%s">documentation</a> for a list of these selectors and for styling examples.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/documentation/' ); ?>
        </p>
        </div>
        </div>
        
        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'More Information and Documentation', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php printf( __( 'More information about WP-Table Reloaded can be found on the <a href="%s">plugin\'s website</a> or on its page in the <a href="%s">WordPress Plugin Directory</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/website/', 'http://wordpress.org/extend/plugins/wp-table-reloaded/' ); ?> <?php printf( __( 'For technical information, see the <a href="%s">documentation</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/documentation/' ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Help and Support', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php printf( __( '<a href="%s">Support</a> is provided through the <a href="%s">WordPress Support Forums</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/support/', 'http://www.wordpress.org/support/' ); ?> <?php printf( __( 'Before asking for support, please carefully read the <a href="%s">Frequently Asked Questions</a> where you will find answered to the most common questions, and search through the forums.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/faq/' ); ?></p><p><?php printf( __( 'If you do not find an answer there, please <a href="%s">open a new thread</a> in the WordPress Support Forums with the tag &quot;wp-table-reloaded&quot;.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://wordpress.org/tags/wp-table-reloaded' ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Author and License', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php printf( __( 'This plugin was written by <a href="%s">Tobias B&auml;thge</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/' ); ?> <?php _e( 'It is licensed as Free Software under GPL 2.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php printf( __( 'If you like the plugin, <a href="%s"><strong>a donation</strong></a> is recommended.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/donate/' ); ?> <?php printf( __( 'Please rate the plugin in the <a href="%s">WordPress Plugin Directory</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://wordpress.org/extend/plugins/wp-table-reloaded/' ); ?><br/><?php _e( 'Donations and good ratings encourage me to further develop the plugin and to provide countless hours of support. Any amount is appreciated! Thanks!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Credits and Thanks', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p>
            <?php _e( 'Thanks go to <a href="http://alexrabe.boelinger.com/">Alex Rabe</a> for the original wp-Table plugin,', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'Allan Jardine for the <a href="http://www.datatables.net/">DataTables jQuery plugin</a>,', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'Christian Bach for the <a href="http://www.tablesorter.com/">Tablesorter jQuery plugin</a>,', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'Soeren Krings for its extension <a href="http://tablesorter.openwerk.de/">Tablesorter Extended</a>,', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'the submitters of translations:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Albanian (thanks to <a href="http://www.romeolab.com/">Romeo</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Belorussian (thanks to <a href="http://www.fatcow.com/">Marcis Gasuns</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Brazilian Portugues (thanks to <a href="http://www.pensarics.com/">Rics</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Czech (thanks to <a href="http://separatista.net/">Separatista</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Finnish (thanks to Jaakko)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'French (thanks to <a href="http://ultratrailer.net/">Yin-Yin</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Italian (thanks to <a href="http://www.scrical.it/">Gabriella Mazzon</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Japanese (thanks to <a href="http://www.u-1.net/">Yuuichi</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Polish (thanks to Alex Kortan)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Russian (thanks to <a href="http://wp-skins.info/">Truper</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Slovak (thanks to <a href="http://lukas.cerro.sk/">55.lukas</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Spanish (thanks to <a href="http://theindependentproject.com/">Alejandro Urrutia</a> and <a href="http://halles.cl/">Matias Halles</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Swedish (thanks to <a href="http://www.zuperzed.se/">ZuperZed</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/>&middot; <?php _e( 'Turkish (thanks to <a href="http://www.wpuzmani.com/">Semih</a>)', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <br/><?php _e( 'and to all donors, contributors, supporters, reviewers and users of the plugin!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
        </p>
        </div>
        </div>
        
        <div class="postbox<?php echo $this->helper->postbox_closed( 'debug-version-information', true ); ?>">
        <h3 class="hndle"><span><?php _e( 'Debug and Version Information', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo ( $this->wp27 ) ? _c( 'Hide|expand', WP_TABLE_RELOADED_TEXTDOMAIN ) : _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p>
            <?php _e( 'You are using the following versions of the software.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <strong><?php _e( 'Please provide this information in bug reports.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></strong><br/>
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
        $this->helper->print_page_footer();
    }

    // ###################################################################################################################
    // #########################################                      ####################################################
    // #########################################     Print Support    ####################################################
    // #########################################                      ####################################################
    // ###################################################################################################################

    // ###################################################################################################################
    function print_submenu_navigation( $the_action ) {
        $table_actions = array(
            'list' =>  __( 'List Tables', WP_TABLE_RELOADED_TEXTDOMAIN ),
            'add' =>  __( 'Add new Table', WP_TABLE_RELOADED_TEXTDOMAIN ),
            'import' => __( 'Import a Table', WP_TABLE_RELOADED_TEXTDOMAIN ),
            'export' => __( 'Export a Table', WP_TABLE_RELOADED_TEXTDOMAIN )
        );
        $table_actions = apply_filters( 'wp_table_reloaded_backend_table_actions', $table_actions );
        $last_table_action = array_pop( array_keys( $table_actions ) );
        
        $plugin_actions = array(
            'options' => __( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ),
            'info' => __( 'About the plugin', WP_TABLE_RELOADED_TEXTDOMAIN )
        );
        $plugin_actions = apply_filters( 'wp_table_reloaded_backend_plugin_actions', $plugin_actions );
        $last_plugin_action = array_pop( array_keys( $plugin_actions ) );
        ?>
        <ul class="subsubsub">
            <?php
            foreach ( $table_actions as $action => $name ) {
                $action_url = $this->get_action_url( array( 'action' => $action ), false );
                $class = ( $action == $the_action ) ? 'class="current" ' : '';
                $bar = ( $last_table_action != $action ) ? ' | ' : '';
                echo "<li><a {$class}href=\"{$action_url}\">{$name}</a>{$bar}</li>";
            }
            echo '<li>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</li>';
            foreach ( $plugin_actions as $action => $name ) {
                $action_url = $this->get_action_url( array( 'action' => $action ), false );
                $class = ( $action == $the_action ) ? 'class="current" ' : '';
                $bar = ( $last_plugin_action != $action ) ? ' | ' : '';
                echo "<li><a {$class}href=\"{$action_url}\">{$name}</a>{$bar}</li>";
            }
            ?>
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
    function update_options() {
        // possibility to overwrite option updating (i.e. to update them in own DB table)
        $options_updated = apply_filters( 'wp_table_reloaded_update_options', false, $this->options );
        if ( $options_updated )
            return;
    
        update_option( $this->optionname['options'], $this->options );
    }

    // ###################################################################################################################
    function update_tables() {
        ksort( $this->tables, SORT_NUMERIC ); // sort for table IDs, as one with a small ID might have been appended
        
        // possibility to overwrite tables updating (i.e. to update them in own DB table)
        $tables_updated = apply_filters( 'wp_table_reloaded_update_tables', false, $this->tables );
        if ( $tables_updated )
            return;
            
        update_option( $this->optionname['tables'], $this->tables );
    }

    // ###################################################################################################################
    function save_table( $table ) {
        if ( 0 < $table['id'] ) {

            // update last changes data
            $table['last_modified'] = current_time( 'mysql' );
            $user = wp_get_current_user();
            $table['last_editor_id'] = $user->ID;

            // possibility to overwrite table saving (i.e. to store it in own DB table)
            $table_saved = apply_filters( 'wp_table_reloaded_save_table', false, $table );
            if ( $table_saved )
                return;

            $table = apply_filters( 'wp_table_reloaded_pre_save_table', $table );
            $table = apply_filters( 'wp_table_reloaded_pre_save_table_id-' . $table['id'], $table );
            
            $this->tables[ $table['id'] ] = ( isset( $this->tables[ $table['id'] ] ) ) ? $this->tables[ $table['id'] ] : $this->optionname['table'] . '_' . $table['id'];
            update_option( $this->tables[ $table['id'] ], $table );
            $this->update_tables();
        }
    }

    // ###################################################################################################################
    function load_table( $table_id ) {
        // possibility to overwrite table loading (i.e. to get it from own DB table)
        $table_loaded = apply_filters( 'wp_table_reloaded_load_table', false, $table_id );
        if ( $table_loaded )
            return $table_loaded;
    
        if ( 0 < $table_id ) {
            $this->tables[ $table_id ] = ( isset( $this->tables[ $table_id ] ) ) ? $this->tables[ $table_id ] : $this->optionname['table'] . '_' . $table_id;
            $table = get_option( $this->tables[ $table_id ], $this->default_table);
            $return_table = $table;
        } else {
            $return_table = $this->default_table;
        }
        
        $return_table = apply_filters( 'wp_table_reloaded_post_load_table', $return_table, $table_id );
        $return_table = apply_filters( 'wp_table_reloaded_post_load_table_id-' . $table_id, $return_table );
        return $return_table;
    }
    
    // ###################################################################################################################
    function delete_table( $table_id ) {
        // possibility to overwrite table deleting (i.e. to delete it in own DB table)
        $table_deleted = apply_filters( 'wp_table_reloaded_delete_table', false, $table_id );
        if ( !$table_deleted ) {
            $this->tables[ $table_id ] = ( isset( $this->tables[ $table_id ] ) ) ? $this->tables[ $table_id ] : $this->optionname['table'] . '_' . $table_id;
            delete_option( $this->tables[ $table_id ] );
        }
        unset( $this->tables[ $table_id ] );
        $this->update_tables();
    }
    
    // ###################################################################################################################
    function load_tables() {
        // possibility to overwrite tables loading (i.e. to get list from own DB table)
        $tables_loaded = apply_filters( 'wp_table_reloaded_load_tables_list', false );
        if ( $tables_loaded )
            return $tables_loaded;

        return get_option( $this->optionname['tables'], false );
    }

    // ###################################################################################################################
    function load_options() {
        // possibility to overwrite options loading (i.e. to get list from own DB table)
        $options_loaded = apply_filters( 'wp_table_reloaded_load_options', false );
        if ( $options_loaded )
            return $options_loaded;

        return get_option( $this->optionname['options'], false );
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

        $action_url = add_query_arg( $url_params, admin_url( $this->page_url ) );
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
		$this->options = $this->load_options();
		$this->tables = $this->load_tables();
        if ( false === $this->options || false === $this->tables )
            $this->plugin_install();
    }

    // ###################################################################################################################
    function plugin_activation_hook() {
        $this->options = $this->load_options();
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
        $this->options = $this->load_options();
   		$this->tables = $this->load_tables();
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
        $this->options['custom_css'] = ''; // we could add initial CSS here, for demonstration
        $this->options['show_welcome_message'] = 1;
        $this->update_options();
        $this->tables = $this->default_tables;
        $this->update_tables();
    }

    // ###################################################################################################################
    function plugin_update() {
        // update general plugin options
        // 1. step: by adding/overwriting existing options
		$this->options = $this->load_options();
		$new_options = array();

        // 1b. step: update new default options before possibly adding them
        $this->default_options['install_time'] = time();

        // 2a. step: add/delete new/deprecated options by overwriting new ones with existing ones, if existant
		foreach ( $this->default_options as $key => $value )
            $new_options[ $key ] = ( isset( $this->options[ $key ] ) ) ? $this->options[ $key ] : $this->default_options[ $key ] ;

        // 2b., take care of css
        $new_options['use_custom_css'] = ( !isset( $this->options['use_custom_css'] ) && isset( $this->options['use_global_css'] ) ) ? $this->options['use_global_css'] : $this->options['use_custom_css'];

        // 2c., take care of tablesorter script, comparison to 1.4.9 equaly means smaller than anything like 1.5
        if ( version_compare( $this->options['installed_version'] , '1.4.9', '<') ) {
            $new_options['tablesorter_script'] = ( isset( $this->options['use_tablesorter_extended'] ) && true == $this->options['use_tablesorter_extended'] ) ? 'tablesorter_extended' : 'tablesorter';
        }

        // 3. step: update installed version number/empty update message cache
        $new_options['installed_version'] = $this->plugin_version;
        $new_options['update_message'] = array();
        $new_options['show_welcome_message'] = 2;
        
        // 4. step: save the new options
        $this->options = $new_options;
        $this->update_options();

        // update individual tables and their options
		$this->tables = $this->load_tables();
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
    // find out whether user can access $screen
    function user_has_access( $screen ) {
        // capabilities from http://codex.wordpress.org/Roles_and_Capabilities
        switch ( $this->options['user_access_plugin_options'] ) {
            case 'admin':
                $needed_cap = 'manage_options';
                break;
            case 'editor':
                $needed_cap = 'publish_pages';
                break;
            case 'author':
                $needed_cap = 'publish_posts';
                break;
            default:
                $needed_cap = 'manage_options';
        }

        $has_access = current_user_can( $needed_cap );
        $has_access = apply_filters( 'wp_table_reloaded_user_access_' . $screen, $has_access, $this->options['user_access_plugin_options'] );
        return $has_access;
    }
    
    // ###################################################################################################################
    // get remote plugin update message and show it right under the "upgrade automatically" message
    // $current and $new are passed by the do_action call and contain respective plugin version information
    function get_plugin_update_message( $current, $new ) {
        if ( !isset( $this->options['update_message'][$new->new_version] ) || empty( $this->options['update_message'][$new->new_version] ) ) {
            $message = $this->helper->retrieve_plugin_update_message( $current['Version'], $new->new_version );
            $this->options['update_message'][$new->new_version] = $message;
            $this->update_options();
        }
        return $this->options['update_message'][$new->new_version];
    }

    // ###################################################################################################################
    // wrapper for above function for WP >= 2.8 where filter "in_plugin_update-..." exists
    function add_plugin_update_message( $current, $new ) {
        $message = $this->get_plugin_update_message( $current, $new );
        if ( !empty( $message ) ) {
            $message = $this->helper->safe_output( $message );
            echo "<br />{$message}";
        }
    }

    // ###################################################################################################################
    // wrapper for above function for WP < 2.8, because filter "in_plugin_update-..." does not yet exist there
    function add_plugin_update_message_row( $file, $plugin_data ) {
        $current = get_option( 'update_plugins' );
        if ( !isset( $current->response[ $file ] ) )
            return false;
        $r = $current->response[ $file ];
        $message = $this->get_plugin_update_message( $plugin_data, $r );
        if ( !empty( $message ) ) {
            $message = $this->helper->safe_output( $message );
            echo "<tr><td colspan=\"5\" class=\"plugin-update\">{$message}</td></tr>";
        }
    }

    // ###################################################################################################################
    // add links to plugin's entry on Plugins page (WP >= 2.8)
	function add_plugin_row_meta_28( $links, $file ) {
		if ( WP_TABLE_RELOADED_BASENAME == $file ) {
			$links[] = '<a href="' . $this->get_action_url() . '" title="' . __( 'WP-Table Reloaded Plugin Page', WP_TABLE_RELOADED_TEXTDOMAIN ) . '">' . __( 'Plugin Page', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>';
			$links[] = '<a href="http://tobias.baethge.com/go/wp-table-reloaded/faq/" title="' . __( 'Frequently Asked Questions', WP_TABLE_RELOADED_TEXTDOMAIN ) . '">' . __( 'FAQ', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>';
			$links[] = '<a href="http://tobias.baethge.com/go/wp-table-reloaded/support/" title="' . __( 'Support', WP_TABLE_RELOADED_TEXTDOMAIN ) . '">' . __( 'Support', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>';
			$links[] = '<a href="http://tobias.baethge.com/go/wp-table-reloaded/documentation/" title="' . __( 'Plugin Documentation', WP_TABLE_RELOADED_TEXTDOMAIN ) . '">' . __( 'Documentation', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>';
			$links[] = '<a href="http://tobias.baethge.com/go/wp-table-reloaded/donate/" title="' . __( 'Support WP-Table Reloaded with your donation!', WP_TABLE_RELOADED_TEXTDOMAIN ) . '"><strong>' . __( 'Donate', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</strong></a>';
		}
		return $links;
	}

    // ###################################################################################################################
    // add links to plugin's entry on Plugins page (WP < 2.8)
	function add_plugin_row_meta_27( $links, $file ) {
		if ( WP_TABLE_RELOADED_BASENAME == $file ) {
			// two links are combined in one entry. That way, the added divider will not be the automatically added "|", but the <br/>
			$faq_link = '<a href="http://tobias.baethge.com/go/wp-table-reloaded/faq/" title="' . __( 'Frequently Asked Questions', WP_TABLE_RELOADED_TEXTDOMAIN ) . '">' . __( 'FAQ', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>';
			$docs_donate_link = '<a href="http://tobias.baethge.com/go/wp-table-reloaded/documentation/" title="' . __( 'Plugin Documentation', WP_TABLE_RELOADED_TEXTDOMAIN ) . '">' . __( 'Documentation', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>' .
    '<br/>' . '<a href="http://tobias.baethge.com/go/wp-table-reloaded/donate/" title="' . __( 'Support WP-Table Reloaded with your donation!', WP_TABLE_RELOADED_TEXTDOMAIN ) . '"><strong>' . __( 'Donate', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</strong></a>';
            $support_link = '<a href="http://tobias.baethge.com/go/wp-table-reloaded/support/" title="' . __( 'Support', WP_TABLE_RELOADED_TEXTDOMAIN ) . '">' . __( 'Support', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>';
            // $support_link is prepended to existing link, because we want <br/> instead | between them. a little bit hacky, but it works :-)
            $links[0]= $support_link . '<br/>' . $links[0];
            array_unshift( $links, $faq_link, $docs_donate_link );
		}
		return $links;
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

        wp_register_script( 'wp-table-reloaded-admin-js', $this->helper->plugins_url( 'admin/' . $jsfile, __FILE__ ), array( 'jquery' ), $this->plugin_version );
        // add all strings to translate here
        wp_localize_script( 'wp-table-reloaded-admin-js', 'WP_Table_Reloaded_Admin', array(
	  	        'str_UninstallCheckboxActivation' => __( 'Do you really want to activate this? You should only do that right before uninstallation!', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationLinkInsertURL' => __( 'URL of link to insert', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationLinkInsertText' => __( 'Text of link', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationLinkInsertExplain' => __( 'To insert the following HTML code for a link into a cell, just click the cell after closing this dialog.', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DataManipulationImageInsertThickbox' => __( 'To insert an image, click the cell into which you want to insert the image.', WP_TABLE_RELOADED_TEXTDOMAIN ) . "\n" . __( 'The Media Library will open, from which you can select the desired image or insert the image URL.', WP_TABLE_RELOADED_TEXTDOMAIN ) . "\n" . sprintf( __( 'Click the &quot;%s&quot; button to insert the image.', WP_TABLE_RELOADED_TEXTDOMAIN ), attribute_escape( __( 'Insert into Post' ) ) ),
	  	        'str_BulkCopyTablesLink' => __( 'Do you want to copy the selected tables?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_BulkDeleteTablesLink' => __( 'The selected tables and all content will be erased. Do you really want to delete them?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_BulkImportwpTableTablesLink' => __( 'Do you really want to import the selected tables from the wp-Table plugin?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_CopyTableLink' => __( 'Do you want to copy this table?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteTableLink' => __( 'The complete table and all content will be erased. Do you really want to delete it?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteRowsConfirm' => __( 'Do you really want to delete the selected rows?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteColsConfirm' => __( 'Do you really want to delete the selected columns?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteRowsFailedNoSelection' => __( 'You have not selected any rows.', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteColsFailedNoSelection' => __( 'You have not selected any columns.', WP_TABLE_RELOADED_TEXTDOMAIN ),
                'str_DeleteRowsFailedNotAll' => __( 'You can not delete all rows of the table at once!', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_DeleteColsFailedNotAll' => __( 'You can not delete all columns of the table at once!', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_UnHideRowsNoSelection' => __( 'You have not selected any rows.', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_UnHideColsNoSelection' => __( 'You have not selected any columns.', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_InsertRowsNoSelection' => __( 'You have not selected any rows.', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_InsertColsNoSelection' => __( 'You have not selected any columns.', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_ImportwpTableLink' => __( 'Do you really want to import this table from the wp-Table plugin?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_UninstallPluginLink_1' => __( 'Do you really want to uninstall the plugin and delete ALL data?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_UninstallPluginLink_2' => __( 'Are you really sure?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_ChangeTableID' => __( 'Do you really want to change the ID of the table?', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_CFShortcodeMessage' => __( 'To show this Custom Data Field, use this shortcode:', WP_TABLE_RELOADED_TEXTDOMAIN ),
	  	        'str_TableShortcodeMessage' => __( 'To show this table, use this shortcode:', WP_TABLE_RELOADED_TEXTDOMAIN ),
                'str_ImportDumpFile' => __( 'Warning: You will lose all current Tables and Settings! You should create a backup first. Be warned!', WP_TABLE_RELOADED_TEXTDOMAIN ),
                'str_saveAlert' => __( 'You have made changes to the content of this table and not yet saved them.', WP_TABLE_RELOADED_TEXTDOMAIN ) . ' ' . sprintf( __( 'You should first click &quot;%s&quot; or they will be lost if you navigate away from this page.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Update Changes', WP_TABLE_RELOADED_TEXTDOMAIN ) ),
                'option_show_exit_warning' => $this->options['show_exit_warning'],
                'option_growing_textareas' => $this->options['growing_textareas'],
                'option_add_target_blank_to_links' => $this->options['add_target_blank_to_links'],
                'option_tablesorter_enabled' => $this->options['enable_tablesorter'],
                'option_datatables_active' => $this->options['enable_tablesorter'] && ( 'datatables' == $this->options['tablesorter_script'] || 'datatables-tabletools' == $this->options['tablesorter_script'] ),
                'option_tabletools_active' => $this->options['enable_tablesorter'] && ( 'datatables-tabletools' == $this->options['tablesorter_script'] ),
                'l10n_print_after' => 'try{convertEntities(WP_Table_Reloaded_Admin);}catch(e){};'
        ) );
        wp_print_scripts( 'wp-table-reloaded-admin-js' );
    }

    // ###################################################################################################################
    // enqueue css-stylesheet-file for admin, if it exists
    function add_manage_page_css() {
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';
        $cssfile = "admin-style{$suffix}.css";
        wp_enqueue_style( 'wp-table-reloaded-admin-css', $this->helper->plugins_url( 'admin/' . $cssfile, __FILE__ ), array(), $this->plugin_version );
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
        
        $ajax_url = add_query_arg( $params, admin_url( $this->page_url ) );
        $ajax_url = wp_nonce_url( $ajax_url, $this->get_nonce( $params['action'], false ) );
        $ajax_url = clean_url( $ajax_url );

        // currently doing this by hand in the footer, as footer-scripts are only available since WP 2.8
        $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';
        $jsfile = "admin-editor-buttons-script{$suffix}.js";
        wp_register_script( 'wp-table-reloaded-admin-editor-buttons-js', $this->helper->plugins_url( 'admin/' . $jsfile, __FILE__ ), array( 'jquery', 'thickbox' ), $this->plugin_version );
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
        if ( 0 < count( $this->tables ) ) {
        
            wp_print_scripts( 'wp-lists' ); // for AJAX on list of tables
            $wpList = <<<WPLIST

var delBefore;
delBefore = function(s) {
    return confirm( WP_Table_Reloaded_Admin.str_DeleteTableLink ) ? s : false;
}
$('#the-list').wpList( { alt: 'even', delBefore: delBefore } );
$('.delete a[class^="delete"]').click(function(){return false;});

WPLIST;

            wp_register_script( 'wp-table-reloaded-tablesorter-js', $this->helper->plugins_url( 'js/jquery.datatables.min.js', __FILE__ ), array( 'jquery' ) );
            wp_print_scripts( 'wp-table-reloaded-tablesorter-js' );

            $sProcessing = __( 'Please wait...', WP_TABLE_RELOADED_TEXTDOMAIN );
            $sLengthMenu = __( 'Show _MENU_ Tables', WP_TABLE_RELOADED_TEXTDOMAIN );
            $sZeroRecords = __( 'No tables were found.', WP_TABLE_RELOADED_TEXTDOMAIN );
            $sInfo = __( '_START_ to _END_ of _TOTAL_ Tables', WP_TABLE_RELOADED_TEXTDOMAIN );
            $sInfoEmpty = '';
            $sInfoFiltered = __( '(filtered from _MAX_ Tables)', WP_TABLE_RELOADED_TEXTDOMAIN );
            $sInfoPostFix = '';
            $sSearch = __( 'Filter:', WP_TABLE_RELOADED_TEXTDOMAIN );
            $sFirst = __( 'First', WP_TABLE_RELOADED_TEXTDOMAIN );
            $sPrevious = __( 'Back', WP_TABLE_RELOADED_TEXTDOMAIN );
            $sNext = __( 'Next', WP_TABLE_RELOADED_TEXTDOMAIN );
            $sLast = __( 'Last', WP_TABLE_RELOADED_TEXTDOMAIN );

            $tsscript = <<<TSSCRIPT
var tablelist = $('#wp-table-reloaded-list').dataTable({
    "bSortClasses": false,
    "aaSorting": [],
    "bProcessing": true,
    "sPaginationType": "full_numbers",
    "asStripClasses": ['even','odd'],
    "aoColumns": [
        { "sWidth": "24px", "bSortable": false, "bSearchable": false },
        { "sType": "numeric" },
        { "bVisible": false, "bSearchable": true, "sType": "string" },
        { "bSearchable": false, "iDataSort": 2 },
        { "sType": "string" },
        { "bSortable": false }
	],
    "oLanguage": {
	   "sProcessing": "{$sProcessing}",
	   "sLengthMenu": "{$sLengthMenu}",
	   "sZeroRecords": "{$sZeroRecords}",
	   "sInfo": "{$sInfo}",
	   "sInfoEmpty": "{$sInfoEmpty}",
	   "sInfoFiltered": "{$sInfoFiltered}",
	   "sInfoPostFix": "{$sInfoPostFix}",
	   "sSearch": "{$sSearch}",
	   "oPaginate": {
            "sFirst": "{$sFirst}",
            "sPrevious": "{$sPrevious}",
            "sNext": "{$sNext}",
            "sLast": "{$sLast}"
        }
    }
})
.find('.sorting').append('&nbsp;<span>&nbsp;&nbsp;&nbsp;</span>');\n
TSSCRIPT;

            if ( 2 > count( $this->tables ) )
                $tsscript = '';

            echo <<<JSSCRIPT
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready(function($){
{$tsscript}{$wpList}
});
/* ]]> */
</script>
JSSCRIPT;
        }
    }

} // class WP_Table_Reloaded_Admin

?>