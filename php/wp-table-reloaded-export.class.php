<?php
/*
File Name: WP-Table Reloaded - Export Class (see main file wp-table-reloaded.php)
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: This plugin allows you to create and manage tables in the admin-area of WordPress. You can then show them in your posts or on your pages by using a shortcode. The plugin is greatly influenced by the plugin "wp-Table" by Alex Rabe, but was completely rewritten and uses the state-of-the-art WordPress techniques which makes it faster and lighter than the original plugin.
Version: 1.0
Author: Tobias B&auml;thge
Author URI: http://tobias.baethge.com/
*/

// should be included by WP_Table_Reloaded_Admin!
class WP_Table_Reloaded_Export {

    // ###################################################################################################################
    var $export_class_version = '1.0';

    var $export_formats = array();
    var $delimiters = array();
    
    var $export_format = '';
    var $delimiter = ';';
    var $table_to_export = array();
    var $exported_table = '';

    // ###################################################################################################################
    // constructor class
    function WP_Table_Reloaded_Export() {
        // initiate here, because function call __() not allowed outside function
        $this->export_formats = array(
            'csv' => __( 'CSV - Character-Separated Values', WP_TABLE_RELOADED_TEXTDOMAIN ),
            'html' => __( 'HTML - Hypertext Markup Language', WP_TABLE_RELOADED_TEXTDOMAIN ),
            'xml' => __( 'XML - eXtended Markup Language', WP_TABLE_RELOADED_TEXTDOMAIN )
        );
        $this->delimiters = array(
            ';' => __( '; (semicolon)', WP_TABLE_RELOADED_TEXTDOMAIN ),
            ',' => __( ', (comma)', WP_TABLE_RELOADED_TEXTDOMAIN ),
            ':' => __( ': (colon)', WP_TABLE_RELOADED_TEXTDOMAIN )
        );
    }

    // ###################################################################################################################
    function export_table() {
        $output = '';
        
        $data = $this->table_to_export['data'];
        
        $rows = count( $data );
        $cols = (0 < $rows) ? count( $data[0] ) : 0;
        
        switch( $this->export_format ) {
            case 'csv':
                if ( 0 < $rows && 0 < $cols) {
                if ( function_exists( 'fputcsv' ) ) { // introduced in PHP 5.1.0
                    $temp_file = tempnam( $this->get_temp_dir(), 'export_table_' . $this->table_to_export['id'] . '_' );
                    $handle = fopen( $temp_file, 'w' );
                    foreach ( $data as $row_idx => $row ) {
                        $row = array_map( 'stripslashes', $row );
                        fputcsv( $handle, $row, $this->delimiter, '"' );
                    }
                    fclose( $handle );
                    $output = file_get_contents( $temp_file );
                } else { // should word for all PHP versions, but might not be as good as fputcsv
                    foreach( $data as $row_idx => $row ) {
                        $row = array_map( array( &$this, 'csv_wrap_and_escape' ), $row );
                        $output .= implode( $this->delimiter, $row ) . "\n";
                    }
                }
                }
                break;
            case 'xml':
                if ( 0 < $rows && 0 < $cols) {
                    $output .= "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n";
                    $output .= "<table>\n";
                    foreach ( $data as $row_idx => $row ) {
                        $output .= "\t<row>\n";
                        $row = array_map( array( &$this, 'xml_wrap_and_escape' ), $row );
                        $output .= implode( '', $row );
                        $output .= "\t</row>\n";
                    }
                    $output .= '</table>';
                }
                break;
            case 'html':
                if ( 0 < $rows && 0 < $cols) {
                    $output .= "<table>\n";
                    foreach ( $data as $row_idx => $row ) {
                        $output .= "\t<tr>\n";
                        $row = array_map( array( &$this, 'html_wrap_and_escape' ), $row );
                        $output .= implode( '', $row );
                        $output .= "\t</tr>\n";
                    }
                    $output .= '</table>';
                }
                break;
            default:
        }
        $this->exported_table = $output;
    }

    // ###################################################################################################################
    function csv_wrap_and_escape( $string ) {
        $string = stripslashes( $string );
        $string = str_replace( '"', '""', $string );
        return ( false !== strpos( $string, $this->delimiter ) || false !== strpos( $string, '""' ) ) ? ( '"' . $string . '"' ) : $string;
    }

    // ###################################################################################################################
    function xml_wrap_and_escape( $string ) {
        $string = stripslashes( $string );
        if ( $string != htmlspecialchars( $string ) )
            $string = "<![CDATA[{$string}]]>";
        return "\t\t<col>" . $string . "</col>\n";
    }

    // ###################################################################################################################
    function html_wrap_and_escape( $string ) {
        $string = stripslashes( $string );
        return "\t\t<td>" . $string . "</td>\n";
    }

    // ###################################################################################################################
    function get_temp_dir() {
        if ( function_exists( 'sys_get_temp_dir' ) ) { return sys_get_temp_dir(); } // introduced in PHP 5.2.1

        if ( !empty($_ENV['TMP'] ) ) { return realpath( $_ENV['TMP'] ); }
        if ( !empty($_ENV['TMPDIR'] ) ) { return realpath( $_ENV['TMPDIR'] ); }
        if ( !empty($_ENV['TEMP'] ) ) { return realpath( $_ENV['TEMP'] ); }

        $tempfile = tempnam( uniqid( rand(), true ), '' );
        if ( file_exists( $tempfile ) ) {
            unlink( $tempfile );
            return realpath( dirname( $tempfile ) );
        }
    }
    
} // class WP_Table_Reloaded_Export

?>