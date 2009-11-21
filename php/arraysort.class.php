<?php
/*
File Name: WP-Table Reloaded - array sort Class (see main file wp-table-reloaded.php)
Plugin URI: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/
Description: This plugin allows you to create and manage tables in the admin-area of WordPress. You can then show them in your posts or on your pages by using a shortcode. The plugin is greatly influenced by the plugin "wp-Table" by Alex Rabe, but was completely rewritten and uses the state-of-the-art WordPress techniques which makes it faster and lighter than the original plugin.
Version: 1.5
Author: Tobias B&auml;thge
Author URI: http://tobias.baethge.com/
*/

// should be included by WP_Table_Reloaded_Admin!
class arraysort {

    var $input_array = array();
    var $sorted_array = array();
    var $column = -1;
    var $order = 'ASC';
    var $error = false;

    function arraysort( $array = array(), $column = -1, $order = 'ASC' )
    {
        $this->input_array = $array;
        $this->column = $column;
        $this->order = $order;
        if ( !empty ($array) && -1 != $column )
            $this->sort();
    }

    function compare_rows( $a, $b )
    {
        if ( -1 == $this->column )
            return 0;

        return strnatcasecmp( $a[ $this->column ], $b[ $this->column ] );
    }
    
    function sort() {
        $array_to_sort = $this->input_array;
        if ( usort( $array_to_sort, array( &$this, 'compare_rows' ) ) ) {
            $this->sorted_array = ( 'DESC' == $this->order ) ? array_reverse( $array_to_sort ) : $array_to_sort;
        } else {
            $this->sorted_array = $this->input_array;
            $this->error = true;
        }
    }
}

?>