<?php
/*
File Name: WP-Table Reloaded - Frontend Class (see main file wp-table-reloaded.php)
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: Description: This plugin allows you to create and easily manage tables in the admin-area of WordPress. A comfortable backend allows an easy manipulation of table data. You can then include the tables into your posts, on your pages or in text widgets by using a shortcode or a template tag function. Tables can be imported and exported from/to CSV, XML and HTML.
Version: 1.2
Author: Tobias B&auml;thge
Author URI: http://tobias.baethge.com/
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
    var $shortcode = 'table';

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
		add_shortcode( $this->shortcode, array( &$this, 'handle_content_shortcode' ) );
        add_filter('widget_text', array( &$this, 'handle_widget_filter' ) );

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
    // handle [table id=<the_table_id> /] in the_content()
    function handle_content_shortcode( $atts ) {
        // parse shortcode attributs, only allow those specified
        $default_atts = array(
                'id' => 0,
                'column_widths' => '',
                'alternating_row_colors' => -1,
                'first_row_th' => -1,
                'print_name' => -1,
                'print_description' => -1,
                'use_tablesorter' => -1,
                'row_offset' => 1,
                'row_count' => null
        );
      	$atts = shortcode_atts( $default_atts, $atts );

        // check if table exists
        $table_id = $atts['id'];
        if ( !is_numeric( $table_id ) || 1 > $table_id || false == $this->table_exists( $table_id ) )
            return "[table \"{$table_id}\" not found /]<br />\n";

        // explode from string to array
        $atts['column_widths'] = explode( '|', $atts['column_widths'] );

        $table = $this->load_table( $table_id );

        // determine options to use (if set in shortcode, use those, otherwise use options from "Edit Table" screen)
        $output_options = array();
        foreach ( $atts as $key => $value ) {
            // have to check this, because strings 'true' or 'false' are not recognized as boolean!
            if ( 'true' == strtolower( $value ) )
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
        $output_options[ 'html_id' ] = "wp-table-reloaded-id-{$table_id}-no-{$count}";
        
        $output = $this->render_table( $table, $output_options );

        return $output;
    }
    
    // ###################################################################################################################
    // handle [table id=<the_table_id> /] in widget texts
    function handle_widget_filter( $text ) {
        // pattern to search for in widget text (only our plugin's shortcode!)
        $pattern = '\[(' . preg_quote( $this->shortcode ) . ')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\1\])?';
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
        if ( null === $output_options['row_count'] )
            $table['data'] = array_slice( $table['data'], $output_options['row_offset'] - 1 ); // -1 because we start from 1
        else
            $table['data'] = array_slice( $table['data'], $output_options['row_offset'] - 1 , $output_options['row_count'] ); // -1 because we start from 1

        $rows = count( $table['data'] );
        $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;

        // make array $shortcode_atts['column_widths'] have $cols entries
        $output_options['column_widths'] = array_pad( $output_options['column_widths'], $cols, '' );

        $output = '';

        if ( 0 < $rows && 0 < $cols) {
        
            if ( true == $output_options['print_name'] )
                $output .= '<h2 class="wp-table-reloaded-table-name">' . $this->safe_output( $table['name'] ) . "</h2>\n";
        
            $output .= "<table id=\"{$output_options['html_id']}\" class=\"{$cssclasses}\" cellspacing=\"1\" cellpadding=\"0\" border=\"0\">\n";

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
                            $width_style = ( !empty( $column_widths[$col_idx] ) ) ? " style=\"width:{$column_widths[$col_idx]};\"" : '';
                            $cell_content = $this->safe_output( $cell_content );
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
                            $width_style = ( !empty( $column_widths[$col_idx] ) ) ? " style=\"width:{$column_widths[$col_idx]};\"" : '';
                            $cell_content = $this->safe_output( $cell_content );
                            $output .= "<td{$col_class}{$width_style}>" . "{$cell_content}" . "</td>";
                        }
                        $output .= "\n\t</tr>\n";
                    }
                } else {
                    $output .= "\t<tr{$row_class}>\n\t\t";
                    foreach( $row as $col_idx => $cell_content ) {
                        $col_class = ' class="column-' . ( $col_idx + 1 ) . '"';
                        $cell_content = $this->safe_output( $cell_content );
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
        return stripslashes( $string );
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