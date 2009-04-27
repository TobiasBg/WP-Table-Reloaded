<?php
/*
File Name: WP-Table Reloaded - Frontend Class (see main file wp-table-reloaded.php)
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: This plugin allows you to create and manage tables in the admin-area of WordPress. You can then show them in your posts, on your pages or in text widgets by using a shortcode. The plugin is a completely rewritten and extended version of Alex Rabe's "wp-Table" and uses the state-of-the-art WordPress techniques which makes it faster and lighter than the original plugin.
Version: 1.1-beta
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
		if ( true == $this->options['enable_tablesorter'] )
    		$this->add_head_tablesorter_js();

        // if global css shall be used
		if ( true == $this->options['use_custom_css'] )
            add_action( 'wp_head', array( &$this, 'add_custom_css' ) );
    }

    // ###################################################################################################################
    // handle [table id=<the_table_id> /] in the_content()
    function handle_content_shortcode( $attr ) {

        // parse shortcode attributs, only allow those specified
        $default_atts = array(
                'id' => 0,
                'output_id' => false,
                'column_widths' => ''
                );
      	$atts = shortcode_atts( $default_atts, $atts );

        // get atts from array to variables
        $table_id = $attr['id'];
        $output_id = (  true == $attr['output_id'] ) ? true : false;
        $column_widths = explode( '|', $attr['column_widths'] );

        if ( !is_numeric( $table_id ) || 1 > $table_id || false == $this->table_exists( $table_id ) )
            return "[table \"{$table_id}\" not found /]<br />\n";

        $table = $this->load_table( $table_id );

        $output = $this->render_table( $table, $column_widths, $output_id );

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
    function render_table( $table, $column_widths, $output_id ) {
        // classes that will be added to <table class=...>, can be used for css-styling
        $cssclasses = array( 'wp-table-reloaded', "wp-table-reloaded-id-{$table['id']}" );
        $cssclasses = implode( ' ', $cssclasses );

        $id_output = ( true == $output_id ) ? " id=\"wp-table-reloaded-id-{$table['id']}\"" : '';

        $rows = count( $table['data'] );
        $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;

        // make array $column_widths have $cols entries
        $column_widths = array_pad( $column_widths, $cols, '' );

        $output = '';

        if ( 0 < $rows && 0 < $cols) {
        
            if ( true == $table['options']['print_name'] )
                $output .= '<h2 class="wp-table-reloaded-table-name">' . $this->safe_output( $table['name'] ) . "</h2>\n";
        
            $output .= "<table{$id_output} class=\"{$cssclasses}\" cellspacing=\"1\" cellpadding=\"0\" border=\"0\">\n";

            foreach( $table['data'] as $row_idx => $row ) {
                if ( true == $table['options']['alternating_row_colors'] )
                    $row_class = ( 1 == ($row_idx % 2) ) ? ' class="even row-' . ( $row_idx + 1 ) . '"' : ' class="odd row-' . ( $row_idx + 1 ) . '"';
                else
                    $row_class = ' class="row-' . ( $row_idx + 1 ) . '"';
                    
                if( 0 == $row_idx ) {
                    if ( true == $table['options']['first_row_th'] ) {
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

            if ( true == $table['options']['print_description'] )
                $output .= '<span class="wp-table-reloaded-table-description">' . $this->safe_output( $table['description'] ) . "</span>\n";

            $widgets = ( true == $table['options']['alternating_row_colors'] ) ? "{widgets: ['zebra']}" : '';
            
            if ( true == $table['options']['use_tablesorter'] && true == $table['options']['first_row_th'] && true == $this->options['enable_tablesorter'] ) {
                $output .= <<<JSSCRIPT
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready(function($){
    $(".wp-table-reloaded-id-{$table['id']}").tablesorter({$widgets});
});
/* ]]> */
</script>
JSSCRIPT;
            }
        }
        return $output;
    }

    // ###################################################################################################################
    function safe_output( $string ) {
        return stripslashes( $string );
    }
    
    // ###################################################################################################################
    // enqueue tablesorter-js-file, if it exists
    function add_head_tablesorter_js() {
        $jsfile =  'jquery.tablesorter.min.js';
        if ( file_exists( WP_TABLE_RELOADED_ABSPATH . 'js/' . $jsfile ) )
            wp_enqueue_script( 'wp-table-reloaded-tablesorter-js', WP_TABLE_RELOADED_URL . 'js/' . $jsfile, array( 'jquery' ) );
    }
    
    // ###################################################################################################################
    // load and print css-style, (only called if enabled, by wp_head-action)
    function add_custom_css() {
        // load css filename from options, if option doesnt exist, use default
        $css = ( isset( $this->options['custom_css'] ) ) ? $this->options['custom_css'] : '';
        
        if ( !empty( $css ) ) {
            $output .= <<<CSSSTYLE
<style type="text/css" media="all">
/* <![CDATA[ */
{$css}
/* ]]> */
</style>
CSSSTYLE;
            echo $output;
        }
    }

} // class WP_Table_Reloaded_Frontend

?>