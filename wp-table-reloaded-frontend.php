<?php
/*
File Name: WP-Table Reloaded - Frontend Class (see main file wp-table-reloaded.php)
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: Description: This plugin allows you to create and easily manage tables in the admin-area of WordPress. A comfortable backend allows an easy manipulation of table data. You can then include the tables into your posts, on your pages or in text widgets by using a shortcode or a template tag function. Tables can be imported and exported from/to CSV, XML and HTML.
Version: 1.5-beta1
Author: Tobias B&auml;thge
Author URI: http://tobias.baethge.com/
Donate URI: http://tobias.baethge.com/donate/
*/

class WP_Table_Reloaded_Frontend {

    // ###################################################################################################################
    // plugin variables
    var $options = array();
    var $tables = array();

    var $optionname = array(
        'tables' => 'wp_table_reloaded_tables',
        'options' => 'wp_table_reloaded_options',
        'table' => 'wp_table_reloaded_data'
    );
    // shortcodes
    var $shortcode_table = 'table';
    var $shortcode_table_info = 'table-info';
    
    var $shown_tables = array();
    var $tablesorter_tables = array();
    
    // class instances
    var $helper;

    // ###################################################################################################################
    function WP_Table_Reloaded_Frontend() {
        // load common functions, stored in separate file for better overview and maintenance
        $this->helper = $this->create_class_instance( 'WP_Table_Reloaded_Helper', 'wp-table-reloaded-helper.class.php' );
    
        // load options and table information from database, if not available: default
		$this->options = get_option( $this->optionname['options'], false );
		$this->tables = get_option( $this->optionname['tables'], false );

		if ( false === $this->options || false === $this->tables )
            return;

        // make shortcode name filterable
        $this->shortcode_table = apply_filters( 'wp_table_reloaded_shortcode_table', $this->shortcode_table );
        
		// front-end function, shortcode for the_content, manual filter for widget_text
		// shortcode "table-info" needs to be declared before "table"! Otherwise it will not be recognized!
        add_shortcode( $this->shortcode_table_info, array( &$this, 'handle_content_shortcode_table_info' ) );
        add_shortcode( $this->shortcode_table, array( &$this, 'handle_content_shortcode_table' ) );

        add_filter( 'widget_text', array( &$this, 'handle_widget_filter_table_info' ) );
        add_filter( 'widget_text', array( &$this, 'handle_widget_filter_table' ) );

        // if tablesorter enabled (globally) include javascript
		if ( true == $this->options['enable_tablesorter'] ) {
    		wp_enqueue_script( 'jquery' ); // jquery needed in any case (it's too late to do this, when shortcode is executed
            add_action( 'wp_footer', array( &$this, 'add_frontend_js' ) ); // but if we actually need the tablesorter script can be determined in the footer
        }

        // if global css shall be used
		if ( true == $this->options['use_default_css'] || true == $this->options['use_custom_css'] )
            add_action( 'wp_head', array( &$this, 'add_frontend_css' ) );
    }

    // ###################################################################################################################
    // handle [table-info id=<the_table_id> field=<name> /] in the_content()
    function handle_content_shortcode_table_info( $atts ) {
        // parse shortcode attributs, only allow those specified
        $default_atts = array(
                'id' => 0,
                'field' => '',
                'format' => ''
        );
      	$atts = shortcode_atts( $default_atts, $atts );

        // check if table exists
        $table_id = $atts['id'];
        if ( !is_numeric( $table_id ) || 1 > $table_id || false == $this->table_exists( $table_id ) ) {
            $message = "[table \"{$table_id}\" not found /]<br />\n";
            $message = apply_filters( 'wp_table_reloaded_table_not_found_message', $message, $table_id );
            return $message;
        }

        $field = $atts['field'];
        $format = $atts['format'];
        
        $table = $this->load_table( $table_id );

        switch ( $field ) {
            case 'name':
            case 'description':
                $output = $table[ $field ];
                break;
            case 'last_modified':
                $output = ( 'raw' == $format ) ?  $table['last_modified'] : $this->helper->format_datetime( $table['last_modified'] );
                break;
            case 'last_editor':
                $output = $this->helper->get_last_editor( $table['last_editor_id'] );
                break;
            default:
                if ( isset( $table['custom_fields'][ $field ] ) ) {
                    $output = $table['custom_fields'][ $field ];
                } else {
                    $output = "[table-info field &quot;{$field}&quot; not found in table {$table_id} /]<br />\n";
                    $output = apply_filters( 'wp_table_reloaded_table_info_not_found_message', $output, $table_id, $field );
                }
        }

        return $output;
    }

    // ###################################################################################################################
    // handle [table id=<the_table_id> /] in the_content()
    function handle_content_shortcode_table( $atts ) {
        // parse shortcode attributs, only allow those specified
        $default_atts = array(
                'id' => 0,
                'column_widths' => '',
                'alternating_row_colors' => -1,
                'first_row_th' => -1,
                'table_footer' => -1,
                'print_name' => -1,
                'print_description' => -1,
                'use_tablesorter' => -1,
                'datatables_sort' => -1,
                'datatables_paginate' => -1,
                'datatables_lengthchange' => -1,
                'datatables_filter' => -1,
                'datatables_info' => -1,
                'datatables_tabletools' => -1,
                'datatables_customcommands' => -1,
                'row_offset' => 1, // ATTENTION: MIGHT BE DROPPED IN FUTURE VERSIONS!
                'row_count' => null, // ATTENTION: MIGHT BE DROPPED IN FUTURE VERSIONS!
                'show_rows' => '',
                'show_columns' => '',
                'hide_rows' => '',
                'hide_columns' => '',
                'cellspacing' => 1,
                'cellpadding' => 0,
                'border' => 0
        );
      	$atts = shortcode_atts( $default_atts, $atts );

        // check if table exists
        $table_id = $atts['id'];
        if ( !is_numeric( $table_id ) || 1 > $table_id || false == $this->table_exists( $table_id ) ) {
            $message = "[table \"{$table_id}\" not found /]<br />\n";
            $message = apply_filters( 'wp_table_reloaded_table_not_found_message', $message, $table_id );
            return $message;
        }

        // explode from string to array
        $atts['column_widths'] = explode( '|', $atts['column_widths'] );

        // rows/columns are indexed from 0 internally
        $atts['show_rows'] = ( !empty( $atts['show_rows'] ) ) ? explode( ',', $atts['show_rows'] ) : array();
        foreach ( $atts['show_rows'] as $key => $value )
            $atts['show_rows'][$key] = (string) ( $value - 1 );
        $atts['show_columns'] = ( !empty( $atts['show_columns'] ) ) ? explode( ',', $atts['show_columns'] ) : array();
        foreach ( $atts['show_columns'] as $key => $value )
            $atts['show_columns'][$key] = (string) ( $value - 1 );
        $atts['hide_rows'] = ( !empty( $atts['hide_rows'] ) ) ? explode( ',', $atts['hide_rows'] ) : array();
        foreach ( $atts['hide_rows'] as $key => $value )
            $atts['hide_rows'][$key] = (string) ( $value - 1 );
        $atts['hide_columns'] = ( !empty( $atts['hide_columns'] ) ) ? explode( ',', $atts['hide_columns'] ) : array();
        foreach ( $atts['hide_columns'] as $key => $value )
            $atts['hide_columns'][$key] = (string) ( $value - 1 );

        $table = $this->load_table( $table_id );

        // check for table data
        if ( !isset( $table['data'] ) || empty( $table['data'] ) ) {
            $message = "[table &quot;{$table_id}&quot; seems to be empty /]<br />\n";
            $message = apply_filters( 'wp_table_reloaded_table_empty_message', $message, $table_id );
            return $message;
        }
        
        // determine options to use (if set in shortcode, use those, otherwise use options from "Edit Table" screen)
        $output_options = array();
        foreach ( $atts as $key => $value ) {
            // have to check this, because strings 'true' or 'false' are not recognized as boolean!
            if ( is_array( $value ) )
                $output_options[ $key ] = $value;
            elseif ( 'true' == strtolower( $value ) )
                $output_options[ $key ] = true;
            elseif ( 'false' == strtolower( $value ) )
                $output_options[ $key ] = false;
            else
                $output_options[ $key ] = ( -1 !== $value ) ? $value : $table['options'][ $key ] ;
        }
        
        // how often was table displayed on this page yet? get its HTML ID
        $count = ( isset( $this->shown_tables[ $table_id ] ) ) ? $this->shown_tables[ $table_id ] : 0;
        $count = $count + 1;
        $this->shown_tables[ $table_id ] = $count;
        $output_options['html_id'] = "wp-table-reloaded-id-{$table_id}-no-{$count}";
        
        $output = $this->render_table( $table, $output_options );

        return $output;
    }

    // ###################################################################################################################
    // handle [table-info id=<the_table_id> field="name" /] in widget texts
    function handle_widget_filter_table_info( $text ) {
        return widget_filter_replace( $this->shortcode_table_info, $text );
    }

    // handle [table id=<the_table_id> /] in widget texts
    function handle_widget_filter_table( $text ) {
        return widget_filter_replace( $this->shortcode_table, $text );
    }

    // actual replacement of above two functions, they are the same, besides the shortcode
    function widget_filter_replace( $shortcode, $text ) {
        // pattern to search for in widget text (only our plugin's shortcode!)
        if ( version_compare( $GLOBALS['wp_version'], '2.8alpha', '>=') ) {
            $pattern = '(.?)\[(' . preg_quote( $this->shortcode_table_info ) . ')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)';
        } else {
            $pattern = '\[(' . preg_quote( $this->shortcode_table_info ) . ')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\1\])?';
        }
        // search for it, if found, handle as if it were a shortcode
        return preg_replace_callback( '/'.$pattern.'/s', 'do_shortcode_tag', $text );
    }

    // ###################################################################################################################
    // check, if given table id really exists
    function table_exists( $table_id ) {
        return isset( $this->tables[ $table_id ] );
    }

    // ###################################################################################################################
    function load_table( $table_id ) {
        $this->tables[ $table_id ] = ( isset( $this->tables[ $table_id ] ) ) ? $this->tables[ $table_id ] : $this->optionname['table'] . '_' . $table_id;
        $table = get_option( $this->tables[ $table_id ], array() );
        return $table;
    }

    // ###################################################################################################################
    // echo content of array
    function render_table( $table, $output_options ) {
        // classes that will be added to <table class=...>, can be used for css-styling
        $cssclasses = array( 'wp-table-reloaded', "wp-table-reloaded-id-{$table['id']}" );
        $cssclasses = implode( ' ', $cssclasses );

        // filter certain values, so plugins can change them
        $cssclasses = apply_filters( 'wp_table_reloaded_table_css_class', $cssclasses, $table['id'] );
        $output_options['html_id'] = apply_filters( 'wp_table_reloaded_html_id', $output_options['html_id'], $table['id'] );

        // if row_offset or row_count were given, we cut that part from the table and show just that
        // ATTENTION: MIGHT BE DROPPED IN FUTURE VERSIONS!
        if ( null === $output_options['row_count'] )
            $table['data'] = array_slice( $table['data'], $output_options['row_offset'] - 1 ); // -1 because we start from 1
        else
            $table['data'] = array_slice( $table['data'], $output_options['row_offset'] - 1, $output_options['row_count'] ); // -1 because we start from 1

        // load information about hidden rows and columns
        $hidden_rows = isset( $table['visibility']['rows'] ) ? array_keys( $table['visibility']['rows'], true ) : array();
        $hidden_rows = array_merge( $hidden_rows, $output_options['hide_rows'] );
        $hidden_rows = array_diff( $hidden_rows, $output_options['show_rows'] );
        sort( $hidden_rows, SORT_NUMERIC );
        $hidden_columns = isset( $table['visibility']['columns'] ) ? array_keys( $table['visibility']['columns'], true ) : array();
        $hidden_columns = array_merge( $hidden_columns, $output_options['hide_columns'] );
        $hidden_columns = array_merge( array_diff( $hidden_columns, $output_options['show_columns'] ) );
        sort( $hidden_columns, SORT_NUMERIC );

        // remove hidden rows and re-index
        foreach( $hidden_rows as $row_idx ) {
            unset( $table['data'][$row_idx] );
        }
        $table['data'] = array_merge( $table['data'] );
        // remove hidden columns and re-index
        foreach( $table['data'] as $row_idx => $row ) {
            foreach( $hidden_columns as $col_idx ) {
                unset( $row[$col_idx] );
            }
            $table['data'][$row_idx] = array_merge( $row );
        }

        $rows = count( $table['data'] );
        $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;

        // make array $shortcode_atts['column_widths'] have $cols entries
        $output_options['column_widths'] = array_pad( $output_options['column_widths'], $cols, '' );

        $output = '';

        if ( 0 < $rows && 0 < $cols) {
        
            if ( true == $output_options['print_name'] ) {
                $print_name_html_tag = apply_filters( 'wp_table_reloaded_print_name_html_tag', 'h2', $table['id'] );
                $print_name_css_class = apply_filters( 'wp_table_reloaded_print_name_css_class', 'wp-table-reloaded-table-name', $table['id'] );
                $output .= "<{$print_name_html_tag} class=\"{$print_name_css_class}\">" . $this->safe_output( $table['name'] ) . "</{$print_name_html_tag}>\n";
            }
        
            $output .= "<table id=\"{$output_options['html_id']}\" class=\"{$cssclasses}\" cellspacing=\"{$output_options['cellspacing']}\" cellpadding=\"{$output_options['cellpadding']}\" border=\"{$output_options['border']}\">\n";

            $print_colgroup_tag = apply_filters( 'wp_table_reloaded_print_colgroup_tag', false, $table['id'] );
            if ( $print_colgroup_tag ) {
                $output .= '<colgroup>';
                for ( $col = 1; $col <= $cols; $col++ ) {
                    $attributes = "class=\"colgroup-col-{$col}\" ";
                    $attributes = apply_filters( 'wp_table_reloaded_colgroup_tag_attributes', $attributes, $table['id'], $col );
                    $output .= "<col {$attributes}/>";
                }
                $output .= "</colgroup>\n";
            }

            $last_row_idx = $rows - 1; // index of the last row, needed for table footer
            foreach( $table['data'] as $row_idx => $row ) {

                $row_class = 'row-' . ( $row_idx + 1 ) ;

                if ( true == $output_options['alternating_row_colors'] )
                    $row_class = ( 1 == ($row_idx % 2) ) ? $row_class . ' even' : $row_class . ' odd';

                $row_class = apply_filters( 'wp_table_reloaded_row_css_class', $row_class, $table['id'], $row_idx + 1 );

                if ( ( 0 == $row_idx ) && ( 1 < $rows ) ){
                    if ( true == $output_options['first_row_th'] ) {
                        $output .= "<thead>\n";
                        $output .= "\t<tr class=\"{$row_class}\">\n\t\t";
                        foreach( $row as $col_idx => $cell_content ) {
                            $col_class = 'column-' . ( $col_idx + 1 );
                            $col_class = apply_filters( 'wp_table_reloaded_cell_css_class', $col_class, $table['id'], $row_idx + 1, $col_idx + 1 );
                            $width_style = ( !empty( $output_options['column_widths'][$col_idx] ) ) ? " style=\"width:{$output_options['column_widths'][$col_idx]};\"" : '';
                            $cell_content = do_shortcode( $this->safe_output( $cell_content ) );
                            $cell_content = apply_filters( 'wp_table_reloaded_cell_content', $cell_content, $table['id'], $row_idx + 1, $col_idx + 1 );
                            $output .= "<th class=\"{$col_class}\"{$width_style}>{$cell_content}</th>";
                        }
                        $output .= "\n\t</tr>\n";
                        $output .= "</thead>\n";
                        $output .= "<tbody>\n";
                    } else {
                        $output .= "<tbody>\n";
                        $output .= "\t<tr class=\"{$row_class}\">\n\t\t";
                        foreach( $row as $col_idx => $cell_content ) {
                            $col_class = 'column-' . ( $col_idx + 1 );
                            $col_class = apply_filters( 'wp_table_reloaded_cell_css_class', $col_class, $table['id'], $row_idx + 1, $col_idx + 1 );
                            $width_style = ( !empty( $output_options['column_widths'][$col_idx] ) ) ? " style=\"width:{$output_options['column_widths'][$col_idx]};\"" : '';
                            $cell_content = do_shortcode( $this->safe_output( $cell_content ) );
                            $cell_content = apply_filters( 'wp_table_reloaded_cell_content', $cell_content, $table['id'], $row_idx + 1, $col_idx + 1 );
                            $output .= "<td class=\"{$col_class}\"{$width_style}>{$cell_content}</td>";
                        }
                        $output .= "\n\t</tr>\n";
                    }
                } elseif ( $last_row_idx == $row_idx ) {
                    if ( true == $output_options['table_footer'] ) {
                        $output .= "</tbody>\n";
                        $output .= "<tfoot>\n";
                        $output .= "\t<tr class=\"{$row_class}\">\n\t\t";
                        foreach( $row as $col_idx => $cell_content ) {
                            $col_class = 'column-' . ( $col_idx + 1 );
                            $col_class = apply_filters( 'wp_table_reloaded_cell_css_class', $col_class, $table['id'], $row_idx + 1, $col_idx + 1 );
                            $cell_content = do_shortcode( $this->safe_output( $cell_content ) );
                            $cell_content = apply_filters( 'wp_table_reloaded_cell_content', $cell_content, $table['id'], $row_idx + 1, $col_idx + 1 );
                            $output .= "<th class=\"{$col_class}\">{$cell_content}</th>";
                        }
                        $output .= "\n\t</tr>\n";
                        $output .= "</tfoot>\n";
                    } else {
                        $output .= "\t<tr class=\"{$row_class}\">\n\t\t";
                        foreach( $row as $col_idx => $cell_content ) {
                            $col_class = 'column-' . ( $col_idx + 1 );
                            $col_class = apply_filters( 'wp_table_reloaded_cell_css_class', $col_class, $table['id'], $row_idx + 1, $col_idx + 1 );
                            $cell_content = do_shortcode( $this->safe_output( $cell_content ) );
                            $cell_content = apply_filters( 'wp_table_reloaded_cell_content', $cell_content, $table['id'], $row_idx + 1, $col_idx + 1 );
                            $output .= "<td class=\"{$col_class}\">{$cell_content}</td>";
                        }
                        $output .= "\n\t</tr>\n";
                        $output .= "</tbody>\n";
                    }
                } else {
                    $output .= "\t<tr class=\"{$row_class}\">\n\t\t";
                    foreach( $row as $col_idx => $cell_content ) {
                        $col_class = 'column-' . ( $col_idx + 1 );
                        $col_class = apply_filters( 'wp_table_reloaded_cell_css_class', $col_class, $table['id'], $row_idx + 1, $col_idx + 1 );
                        $cell_content = do_shortcode( $this->safe_output( $cell_content ) );
                        $cell_content = apply_filters( 'wp_table_reloaded_cell_content', $cell_content, $table['id'], $row_idx + 1, $col_idx + 1 );
                        $output .= "<td class=\"{$col_class}\">{$cell_content}</td>";
                    }
                    $output .= "\n\t</tr>\n";
                }
            }
            $output .= "</table>\n";

            if ( true == $output_options['print_description'] ) {
                $print_description_html_tag = apply_filters( 'wp_table_reloaded_print_description_html_tag', 'span', $table['id'] );
                $print_description_css_class = apply_filters( 'wp_table_reloaded_print_description_css_class', 'wp-table-reloaded-table-description', $table['id'] );
                $output .= "<{$print_description_html_tag} class=\"{$print_description_css_class}\">" . $this->safe_output( $table['description'] ) . "</{$print_description_html_tag}>\n";
            }

            // js options like alternating row colors
            $js_options = array (
                    'alternating_row_colors' => $output_options['alternating_row_colors'],
                    'datatables_sort' => $output_options['datatables_sort'],
                    'datatables_paginate' => $output_options['datatables_paginate'],
                    'datatables_lengthchange' => $output_options['datatables_lengthchange'],
                    'datatables_filter' => $output_options['datatables_filter'],
                    'datatables_info' => $output_options['datatables_info'],
                    'datatables_tabletools' => $output_options['datatables_tabletools'],
                    'datatables_customcommands' => $output_options['datatables_customcommands']
            );

            // eventually add this table to list of tables which will be tablesorted and thus be included in the script call in wp_footer
            if ( true == $output_options['use_tablesorter'] && ( true == $output_options['first_row_th'] || 'datatables' == $this->options['tablesorter_script'] ) ) {
                // check if tablesorter is generally enabled already done
                $this->tablesorter_tables[] = array (
                    'table_id' => $table['id'],
                    'html_id' => $output_options['html_id'],
                    'js_options' => $js_options
                );
            }
            
        } // endif rows and cols exist
        
        return $output;
    }

    // ###################################################################################################################
    function safe_output( $string ) {
        // replace any & with &amp; that is not already an encoded entity (from function htmlentities2 in WP 2.8)
        $string = preg_replace( "/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,4};)/", "&amp;", $string );
        // then we only remove slashes and change line breaks, htmlspecialchars would encode <HTML> tags which we don't want
        // nl2br can be overwritten to false, if not wanted
        $apply_nl2br = apply_filters( 'wp_table_reloaded_apply_nl2br', true );
        if ( $apply_nl2br )
            return nl2br( stripslashes( $string ) );
        else
            return stripslashes( $string );
    }

    // ###################################################################################################################
    // load and print CSS styles (only called if enabled as a wp_head action)
    function add_frontend_css() {
        // create @import commands for the default styles
        $default_css = array();
        if ( true == $this->options['use_default_css'] ) {
            $plugin_path = plugins_url( '', __FILE__ );
            $default_css[] = "@import url(\"{$plugin_path}/css/plugin.css\");";
            switch ( $this->options['tablesorter_script'] ) {
                case 'datatables-tabletools':
                    $default_css[] = "@import url(\"{$plugin_path}/js/tabletools/tabletools.css\");";
                case 'datatables': // this also applies to the above, because of the missing "break;"
                    $default_css[] = "@import url(\"{$plugin_path}/css/datatables.css\");";
                    break;
                case 'tablesorter':
                case 'tablesorter_extended':
                    $default_css[] = "@import url(\"{$plugin_path}/css/tablesorter.css\");";
                    break;
                default:
            }
        }
        $default_css = apply_filters( 'wp_table_reloaded_default_css', $default_css, $this->options['use_default_css'], $this->options['tablesorter_script'] );
        $default_css = implode( "\n", $default_css );

        // load css filename from options, if option doesn't exist, use default
        $custom_css = '';
        if ( true == $this->options['use_custom_css'] ) {
            $custom_css = ( isset( $this->options['custom_css'] ) ) ? $this->options['custom_css'] : '';
            $custom_css = stripslashes( $custom_css );
        }
        $custom_css = apply_filters( 'wp_table_reloaded_custom_css', $custom_css, $this->options['use_custom_css'] );

        if ( !empty( $default_css ) || !empty( $custom_css ) ) {
            $divider = ( !empty( $default_css ) && !empty( $custom_css ) ) ? "\n" : '';
            // $default_css needs to stand above $custom_css, so that $custom_css commands can overwrite $default_css commands
            $css = <<<CSSSTYLE
<style type="text/css" media="all">
/* <![CDATA[ */
{$default_css}{$divider}{$custom_css}
/* ]]> */
</style>
CSSSTYLE;
        $css = apply_filters( 'wp_table_reloaded_frontend_css', $css );
        echo $css;
        }
    }

    // ###################################################################################################################
    // output tablesorter execution js for all tables in wp_footer
    function add_frontend_js() {
    
        switch ( $this->options['tablesorter_script'] ) {
            case 'datatables-tabletools':
                $include_tabletools = true;
            case 'datatables': // this also applies to datatables-tabletools, because there is no "break;" above
                $jsfile =  'jquery.datatables.min.js';
                $js_command = 'dataTable';
                break;
            case 'tablesorter':
                $jsfile =  'jquery.tablesorter.min.js';
                $js_command = 'tablesorter';
                break;
            case 'tablesorter_extended':
                $jsfile =  'jquery.tablesorter.extended.js';
                $js_command = 'tablesorter';
                break;
            default:
                $jsfile =  'jquery.tablesorter.min.js';
                $js_command = 'tablesorter';
        }

        if ( 0 < count( $this->tablesorter_tables ) && file_exists( WP_TABLE_RELOADED_ABSPATH . 'js/' . $jsfile ) ) {
        
            // we have tables that shall be sortable, so we load the js
            wp_register_script( 'wp-table-reloaded-frontend-js', plugins_url( 'js/' . $jsfile, __FILE__ ), array( 'jquery' ) );
            wp_print_scripts( 'wp-table-reloaded-frontend-js' );

            if ( isset( $include_tabletools ) && $include_tabletools ) {
                // no need to explicitely check for dependencies ( 'wp-table-reloaded-frontend-js' and 'jquery' )
                wp_register_script( 'wp-table-reloaded-tabletools1-js', plugins_url( 'js/tabletools/zeroclipboard.js', __FILE__ ) );
                wp_print_scripts( 'wp-table-reloaded-tabletools1-js' );

                wp_register_script( 'wp-table-reloaded-tabletools2-js', plugins_url( 'js/tabletools/tabletools.js', __FILE__ ) );
                wp_localize_script( 'wp-table-reloaded-tabletools2-js', 'WP_Table_Reloaded_TableTools', array(
    	  	        'swf_path' => plugins_url( 'js/tabletools/zeroclipboard.swf', __FILE__ ),
                    'l10n_print_after' => 'try{convertEntities(WP_Table_Reloaded_TableTools);}catch(e){};'
                ) );
                wp_print_scripts( 'wp-table-reloaded-tabletools2-js' );
            }

            // generate the commands to make them sortable
            $commands = array();
            foreach ( $this->tablesorter_tables as $tablesorter_table ) {
                $table_id = $tablesorter_table['table_id'];
                $html_id = $tablesorter_table['html_id'];
                $js_options = $tablesorter_table['js_options'];

                $parameters = array();
                switch ( $this->options['tablesorter_script'] ) {
                    case 'datatables-tabletools':
                        if ( $js_options['datatables_tabletools'] )
                            $parameters[] = "\"sDom\": 'T<\"clear\">lfrtip'";
                    case 'datatables':
                        $datatables_locale = get_locale();
                        $datatables_locale = apply_filters( 'wp_table_reloaded_datatables_locale', $datatable_locale );
                        $language_file = "languages/datatables/lang-{$datatables_locale}.txt";
                        $language_file = ( file_exists( WP_TABLE_RELOADED_ABSPATH . $language_file ) ) ? '/' . $language_file : '/languages/datatables/lang-default.txt';
                        $language_file_url = plugins_url( $language_file, __FILE__ );
                        $language_file_url = apply_filters( 'wp_table_reloaded_datatables_language_file_url', $language_file_url );
                        if ( !empty( $language_file_url ) )
                            $parameters[] = "\"oLanguage\":{\"sUrl\": \"{$language_file_url}\"}"; // URL with language file
                        $parameters[] = '"aaSorting": []'; // no initial sort
                        $parameters[] = ( $js_options['alternating_row_colors'] ) ? "\"asStripClasses\":['even','odd']" : '"asStripClasses":[]'; // alt row colors is default, so remove them if not wanted with []
                        $parameters[] = ( $js_options['datatables_sort'] ) ? '"bSort": true' : '"bSort": false';
                        $parameters[] = ( $js_options['datatables_paginate'] ) ? '"bPaginate": true' : '"bPaginate": false';
                        $parameters[] = ( $js_options['datatables_lengthchange'] ) ? '"bLengthChange": true' : '"bLengthChange": false';
                        $parameters[] = ( $js_options['datatables_filter'] ) ? '"bFilter": true' : '"bFilter": false';
                        $parameters[] = ( $js_options['datatables_info'] ) ? '"bInfo": true' : '"bInfo": false';
                        $parameters[] = '"bSortClasses": false'; // don't add additional classes, hopefully speeds up things
                        if ( !empty( $js_options['datatables_customcommands'] ) ) // custom commands added, if not empty
                            $parameters[] = stripslashes( $js_options['datatables_customcommands'] ); // stripslashes is necessary!
                        break;
                    case 'tablesorter':
                    case 'tablesorter_extended':
                        $parameters[] = ( $js_options['alternating_row_colors'] ) ? "widgets: ['zebra']" : '';
                        break;
                    default:
                        $parameters[] = ( $js_options['alternating_row_colors'] ) ? "widgets: ['zebra']" : '';
                }
                $parameters = implode( ", ", $parameters );
                $parameters = ( !empty( $parameters ) ) ? "{{$parameters}}" : '';

                $command = "$(\"#{$html_id}\").{$js_command}({$parameters});";

                $command = apply_filters( 'wp_table_reloaded_tablesorter_command', $command, $table_id, $html_id, $this->options['tablesorter_script'], $js_command, $parameters );
                $commands[] = "\t{$command}";
            }

            $commands = implode( "\n", $commands );
            // filter all commands
            $commands = apply_filters( 'wp_table_reloaded_tablesorter_all_commands', $commands );

            // and echo the commands
            if ( !empty( $commands ) ) {
                echo <<<JSSCRIPT
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready(function($){
{$commands}
});
/* ]]> */
</script>
JSSCRIPT;
            }
        }
    }
    
    // ###################################################################################################################
    function create_class_instance( $class, $file, $folder = 'php' ) {
        if ( !class_exists( $class ) )
            include_once ( WP_TABLE_RELOADED_ABSPATH . $folder . '/' . $file );
        return new $class;
    }

} // class WP_Table_Reloaded_Frontend

?>