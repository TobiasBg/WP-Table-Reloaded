<?php
/*
File Name: WP-Table Reloaded - Frontend Class (see main file wp-table-reloaded.php)
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: Description: This plugin allows you to create and easily manage tables in the admin-area of WordPress. A comfortable backend allows an easy manipulation of table data. You can then include the tables into your posts, on your pages or in text widgets by using a shortcode or a template tag function. Tables can be imported and exported from/to CSV, XML and HTML.
Version: 1.4-beta2
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

    // ###################################################################################################################
    function WP_Table_Reloaded_Frontend() {
        // load options and table information from database, if not available: default
		$this->options = get_option( $this->optionname['options'], false );
		$this->tables = get_option( $this->optionname['tables'], false );

		if ( false === $this->options || false === $this->tables )
            return '';

		// front-end function, shortcode for the_content, manual filter for widget_text
		// shortcode "table-info" needs to be declared before "table"! Otherwise it will not be recognized!
        add_shortcode( $this->shortcode_table_info, array( &$this, 'handle_content_shortcode_table_info' ) );
        add_shortcode( $this->shortcode_table, array( &$this, 'handle_content_shortcode_table' ) );

        add_filter( 'widget_text', array( &$this, 'handle_widget_filter_table_info' ) );
        add_filter( 'widget_text', array( &$this, 'handle_widget_filter_table' ) );

        // if tablesorter enabled (globally) include javascript
		if ( true == $this->options['enable_tablesorter'] ) {
    		$this->add_head_jquery_js(); // jquery needed in any case (it's too late to do this, when shortcode is executed
            add_action( 'wp_footer', array( &$this, 'output_tablesorter_js' ) ); // but if we actually need the tablesorter script can be determined in the footer
        }

        // if global css shall be used
		if ( true == $this->options['use_custom_css'] )
            add_action( 'wp_head', array( &$this, 'add_custom_css' ) );
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
        if ( !is_numeric( $table_id ) || 1 > $table_id || false == $this->table_exists( $table_id ) )
            return "[table \"{$table_id}\" not found /]<br />\n";

        $field = $atts['field'];
        $format = $atts['format'];
        
        $table = $this->load_table( $table_id );

        switch ( $field ) {
            case 'name':
            case 'description':
                $output = $table[ $field ];
                break;
            case 'last_modified':
                $output = ( 'raw' == $format ) ?  $table['last_modified'] : $this->format_datetime( $table['last_modified'] );
                break;
            case 'last_editor':
                $output = $this->get_last_editor( $table['last_editor_id'] );
                break;
            default:
                if ( isset( $table['custom_fields'][ $field ] ) ) {
                    $output = $table['custom_fields'][ $field ];
                } else {
                    $output = "[table-info field &quot;{$field}&quot; not found in table {$table_id} /]<br />\n";
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
                'print_name' => -1,
                'print_description' => -1,
                'use_tablesorter' => -1,
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
        if ( !is_numeric( $table_id ) || 1 > $table_id || false == $this->table_exists( $table_id ) )
            return "[table &quot;{$table_id}&quot; not found /]<br />\n";

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
        if ( !isset( $table['data'] ) || empty( $table['data'] ) )
            return "[table &quot;{$table_id}&quot; seems to be empty /]<br />\n";

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
        $count = ( isset( $this->shown_tables[ $table_id ] ) ) ? $this->shown_tables[ $table['id'] ] : 0;
        $count = $count + 1;
        $this->shown_tables[ $table_id ] = $count;
        $output_options['html_id'] = "wp-table-reloaded-id-{$table_id}-no-{$count}";
        
        $output = $this->render_table( $table, $output_options );

        return $output;
    }

    // ###################################################################################################################
    // handle [table-info id=<the_table_id> field="name" /] in widget texts
    function handle_widget_filter_table_info( $text ) {
        // pattern to search for in widget text (only our plugin's shortcode!)
        if ( version_compare( $GLOBALS['wp_version'], '2.8alpha', '>=') ) {
            $pattern = '(.?)\[(' . preg_quote( $this->shortcode_table_info ) . ')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)';
        } else {
            $pattern = '\[(' . preg_quote( $this->shortcode_table_info ) . ')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\1\])?';
        }
        // search for it, if found, handle as if it were a shortcode
        return preg_replace_callback( '/'.$pattern.'/s', 'do_shortcode_tag', $text );
    }

    // handle [table id=<the_table_id> /] in widget texts
    function handle_widget_filter_table( $text ) {
        // pattern to search for in widget text (only our plugin's shortcode!)
        if ( version_compare( $GLOBALS['wp_version'], '2.8alpha', '>=') ) {
            $pattern = '(.?)\[(' . preg_quote( $this->shortcode_table ) . ')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)';
        } else {
            $pattern = '\[(' . preg_quote( $this->shortcode_table ) . ')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\1\])?';
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
        
            if ( true == $output_options['print_name'] )
                $output .= '<h2 class="wp-table-reloaded-table-name">' . $this->safe_output( $table['name'] ) . "</h2>\n";
        
            $output .= "<table id=\"{$output_options['html_id']}\" class=\"{$cssclasses}\" cellspacing=\"{$output_options['cellspacing']}\" cellpadding=\"{$output_options['cellpadding']}\" border=\"{$output_options['border']}\">\n";

            foreach( $table['data'] as $row_idx => $row ) {
                if ( true == $output_options['alternating_row_colors'] )
                    $row_class = ( 1 == ($row_idx % 2) ) ? ' class="even row-' . ( $row_idx + 1 ) . '"' : ' class="odd row-' . ( $row_idx + 1 ) . '"';
                else
                    $row_class = ' class="row-' . ( $row_idx + 1 ) . '"';
                    
                if( 0 == $row_idx ) {
                    if ( true == $output_options['first_row_th'] ) {
                        $output .= "<thead>\n";
                        $output .= "\t<tr{$row_class}>\n\t\t";
                        foreach( $row as $col_idx => $cell_content ) {
                            $col_class = ' class="column-' . ( $col_idx + 1 ) . '"';
                            $width_style = ( !empty( $output_options['column_widths'][$col_idx] ) ) ? " style=\"width:{$output_options['column_widths'][$col_idx]};\"" : '';
                            $cell_content = do_shortcode( $this->safe_output( $cell_content ) );
                            $output .= "<th{$col_class}{$width_style}>" . "{$cell_content}" . "</th>";
                        }
                        $output .= "\n\t</tr>\n";
                        $output .= "</thead>\n";
                        $output .= "<tbody>\n";
                    } else {
                        $output .= "<tbody>\n";
                        $output .= "\t<tr{$row_class}>\n\t\t";
                        foreach( $row as $col_idx => $cell_content ) {
                            $col_class = ' class="column-' . ( $col_idx + 1 ) . '"';
                            $width_style = ( !empty( $output_options['column_widths'][$col_idx] ) ) ? " style=\"width:{$output_options['column_widths'][$col_idx]};\"" : '';
                            $cell_content = do_shortcode( $this->safe_output( $cell_content ) );
                            $output .= "<td{$col_class}{$width_style}>" . "{$cell_content}" . "</td>";
                        }
                        $output .= "\n\t</tr>\n";
                    }
                } else {
                    $output .= "\t<tr{$row_class}>\n\t\t";
                    foreach( $row as $col_idx => $cell_content ) {
                        $col_class = ' class="column-' . ( $col_idx + 1 ) . '"';
                        $cell_content = do_shortcode( $this->safe_output( $cell_content ) );
                        $output .= "<td{$col_class}>" . "{$cell_content}" . "</td>";
                    }
                    $output .= "\n\t</tr>\n";
                }
            }
            $output .= "</tbody>\n";
            $output .= "</table>\n";

            if ( true == $output_options['print_description'] )
                $output .= '<span class="wp-table-reloaded-table-description">' . $this->safe_output( $table['description'] ) . "</span>\n";

            // if alternating row colors, we want to keep those when sorting
            $widgets = ( true == $output_options['alternating_row_colors'] ) ? "{widgets: ['zebra']}" : '';

            // eventually add this table to list of tables which will be tablesorted and thus be included in the script call in wp_footer
            if ( true == $output_options['use_tablesorter'] && true == $output_options['first_row_th'] ) {
                // check if tablesorter is generally enabled already done
                $this->tablesorter_tables[ $output_options['html_id'] ] = $widgets;
            }
            
        } // endif rows and cols exist
        
        return $output;
    }

    // ###################################################################################################################
    function safe_output( $string ) {
        // replace any & with &amp; that is not already an encoded entity (from function htmlentities2 in WP 2.8)
        $string = preg_replace( "/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,4};)/", "&amp;", $string );
        // then we only remove slashes and change line breaks, htmlspecialchars would encode <HTML> tags which we don't want
        return nl2br( stripslashes( $string ) );
    }

    // ###################################################################################################################
    function format_datetime( $last_modified ) {
        return mysql2date( get_option('date_format'), $last_modified ) . ' ' . mysql2date( get_option('time_format'), $last_modified );
    }

    // ###################################################################################################################
    function get_last_editor( $last_editor_id ) {
        $user = get_userdata( $last_editor_id );
        return $user->nickname;
    }

    // ###################################################################################################################
    // enqueue jquery-js-file
    function add_head_jquery_js() {
        wp_enqueue_script( 'jquery' );
    }

    // ###################################################################################################################
    // load and print css-style, (only called if enabled, by wp_head-action)
    function add_custom_css() {
        // load css filename from options, if option doesnt exist, use default
        $css = ( isset( $this->options['custom_css'] ) ) ? $this->options['custom_css'] : '';
        $css = stripslashes( $css );

        if ( !empty( $css ) ) {
            echo <<<CSSSTYLE
<style type="text/css" media="all">
/* <![CDATA[ */
{$css}
/* ]]> */
</style>
CSSSTYLE;
        }
    }

    // ###################################################################################################################
    // output tablesorter execution js for all tables in wp_footer
    function output_tablesorter_js() {
        if ( isset( $this->options['use_tablesorter_extended'] ) && true == $this->options['use_tablesorter_extended'] )
            $jsfile =  'jquery.tablesorter.extended.js'; // filename of the tablesorter extended script
        else
            $jsfile =  'jquery.tablesorter.min.js'; // filename of the tablesorter script

        if ( 0 < count( $this->tablesorter_tables ) && file_exists( WP_TABLE_RELOADED_ABSPATH . 'js/' . $jsfile ) ) {
        
            // we have tables that shall be sortable, so we load the js
            wp_register_script( 'wp-table-reloaded-tablesorter-js', WP_TABLE_RELOADED_URL . 'js/' . $jsfile, array( 'jquery' ) );
            wp_print_scripts( 'wp-table-reloaded-tablesorter-js' );

            // generate the commands to make them sortable
            $commands = "\n";
            foreach ( $this->tablesorter_tables as $html_id => $widgets )
                $commands .= "\t$(\"#{$html_id}\").tablesorter({$widgets});\n";

            // and echo the commands
            echo <<<JSSCRIPT
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready(function($){{$commands}});
/* ]]> */
</script>
JSSCRIPT;
        }
    }

} // class WP_Table_Reloaded_Frontend

?>