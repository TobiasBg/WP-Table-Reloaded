<?php if ( !defined( 'WP_TABLE_RELOADED_ABSPATH' ) ) exit; // no direct loading of this file ?>
<?php

        $rows = count( $table['data'] );
        $cols = (0 < $rows) ? count( $table['data'][0] ) : 0;

        ?>
        <div style="clear:both;"><p><?php _e( 'On this page, you can edit the content of the table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'It is also possible to change the table structure by inserting, deleting, moving, and swapping columns and rows.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br />
		<?php printf( __( 'To insert the table into a page, post or text-widget, copy the shortcode <strong>[table id=%s /]</strong> and paste it into the corresponding place in the editor.', WP_TABLE_RELOADED_TEXTDOMAIN ), $this->helper->safe_output( $table_id ) ); ?></p></div>
        <form id="wp_table_reloaded_edit_table" method="post" action="<?php echo $this->get_action_url( array( 'action' => 'edit', 'table_id' => $table_id ), false ); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'edit' ) ); ?>
        <input type="hidden" name="table[id]" value="<?php echo $table['id']; ?>" />

        <div class="postbox<?php echo $this->helper->postbox_closed( 'table-information', false ); ?>">
        <h3 class="hndle"><span><?php _e( 'Table Information', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
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
            <div class="postbox<?php echo $this->helper->postbox_closed( 'table-contents', false ); ?>">
            <h3 class="hndle"><span><?php _e( 'Table Contents', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
            <div class="inside">
            <table class="widefat" style="width:auto;" id="table_contents">
                <tbody>
                <?php
                    // first row
                    echo "<tr class=\"table-head\">\n";
                        echo "\t<td class=\"check-column\"><input type=\"checkbox\" style=\"display:none;\" /></td>\n";
                        foreach ( $table['data'][0] as $col_idx => $cell_content ) {
                            $letter = chr( ord( 'A' ) + $col_idx );
                            $hidden = ( isset( $table['visibility']['columns'][$col_idx] ) && $table['visibility']['columns'][$col_idx] ) ? 'true': '' ;
                            $col_hidden = ( isset( $table['visibility']['columns'][$col_idx] ) && $table['visibility']['columns'][$col_idx] ) ? ' column-hidden' : '';
                            echo "\t<td class=\"edit_col_{$col_idx}{$col_hidden}\">{$letter}</td>\n";
                        }
                        echo "\t<td>&nbsp;</td>\n";
                    echo "</tr>\n";

                    // data rows, with checkboxes to select rows
                foreach ( $table['data'] as $row_idx => $table_row ) {
                    $row_hidden = ( isset( $table['visibility']['rows'][$col_idx] ) && $table['visibility']['rows'][$row_idx] ) ? ' row-hidden' : '';
                    echo "<tr class=\"edit_row_{$row_idx}{$row_hidden}\">\n";
                        $output_idx = $row_idx + 1; // start counting at 1 on output
                        $hidden = ( isset( $table['visibility']['rows'][$row_idx] ) && $table['visibility']['rows'][$row_idx] ) ? 'true': '' ;
                        echo "\t<td class=\"check-column\"><label for=\"select_row_{$row_idx}\">{$output_idx} </label><input type=\"checkbox\" name=\"table_select[rows][{$row_idx}]\" id=\"select_row_{$row_idx}\" value=\"true\" /><input type=\"hidden\" name=\"table[visibility][rows][{$row_idx}]\" id=\"edit_row_{$row_idx}\" class=\"cell-hide\" value=\"{$hidden}\" /></td>\n";
                        foreach ( $table_row as $col_idx => $cell_content ) {
                            $cell_content = $this->helper->safe_output( $cell_content );
                            $cell_name = "table[data][{$row_idx}][{$col_idx}]";
                            $col_hidden = ( isset( $table['visibility']['columns'][$col_idx] ) && $table['visibility']['columns'][$col_idx] ) ? ' column-hidden' : '';
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
                            $hidden = ( isset( $table['visibility']['columns'][$col_idx] ) && $table['visibility']['columns'][$col_idx] ) ? 'true': '' ;
                            $col_hidden = ( isset( $table['visibility']['columns'][$col_idx] ) && $table['visibility']['columns'][$col_idx] ) ? ' column-hidden' : '';
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
        <h3 class="hndle"><span><?php _e( 'Data Manipulation', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
    <table class="wp-table-reloaded-data-manipulation widefat">

        <tr><td>
            <a id="a-add-colspan" class="button-primary" href="javascript:void(0);" title="<?php _e( 'Add colspan', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>"><?php _e( 'Add colspan', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
            <a id="a-add-rowspan" class="button-primary" href="javascript:void(0);" title="<?php _e( 'Add rowspan', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>"><?php _e( 'Add rowspan', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></a>
        </td><td>
        </td></tr>

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
            <?php
            $a_rows_hide = '<a id="a-hide-rows" class="button-primary" href="javascript:void(0);">' . _x( 'Hide', 'item', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>';
            $a_rows_unhide = '<a id="a-unhide-rows" class="button-primary" href="javascript:void(0);">' . _x( 'Unhide', 'item', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>';
            printf( _x( 'Selected rows: %s %s', 'hide_unhide', WP_TABLE_RELOADED_TEXTDOMAIN ), $a_rows_hide, $a_rows_unhide );
            ?>
        </td><td>
            <?php
            $a_cols_hide = '<a id="a-hide-columns" class="button-primary" href="javascript:void(0);">' . _x( 'Hide', 'item', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>';
            $a_cols_unhide = '<a id="a-unhide-columns" class="button-primary" href="javascript:void(0);">' . _x( 'Unhide', 'item', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</a>';
            printf( _x( 'Selected columns: %s %s', 'hide_unhide', WP_TABLE_RELOADED_TEXTDOMAIN ), $a_cols_hide, $a_cols_unhide );
            ?>
        </td></tr>

        <tr><td>
            <?php
            // don't show delete link for last and only row
            $row_disabled = ( 1 < $rows ) ? '' : 'disabled="disabled" ';
            $col_disabled = ( 1 < $cols ) ? '' : 'disabled="disabled" ';

            $a_rows_insert = '<input id="button-insert-rows" type="submit" name="submit[insert_rows]" class="button-primary" value="' . __( 'Insert row', WP_TABLE_RELOADED_TEXTDOMAIN ) . '" />';
            $a_rows_delete = '<input id="button-delete-rows" type="submit" name="submit[delete_rows]" class="button-primary" value="' . __( 'Delete', WP_TABLE_RELOADED_TEXTDOMAIN ) . '" ' . $row_disabled . '/>';
            printf( _x( 'Selected rows: %s %s', 'insert_delete', WP_TABLE_RELOADED_TEXTDOMAIN ), $a_rows_insert, $a_rows_delete );

            echo '<br />';

            $a_cols_insert = '<input id="button-insert-columns" type="submit" name="submit[insert_cols]" class="button-primary" value="' . __( 'Insert column', WP_TABLE_RELOADED_TEXTDOMAIN ) . '" />';
            $a_cols_delete = '<input id="button-delete-columns" type="submit" name="submit[delete_cols]" class="button-primary" value="' . __( 'Delete', WP_TABLE_RELOADED_TEXTDOMAIN ) . '" ' . $col_disabled . '/>';
            printf( _x( 'Selected columns: %s %s', 'insert_delete', WP_TABLE_RELOADED_TEXTDOMAIN ), $a_cols_insert, $a_cols_delete );
            ?>
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
        <h3 class="hndle"><span><?php _e( 'Table Styling Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'These settings will only be used for this table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'Alternating row colors', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][alternating_row_colors]" id="table_options_alternating_row_colors"<?php echo ( $table['options']['alternating_row_colors'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_alternating_row_colors"><?php _e( 'Every second row has an alternating background color.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Row Highlighting', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][row_hover]" id="table_options_row_hover"<?php echo ( $table['options']['row_hover'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_row_hover"><?php _e( 'Highlight a row by changing its background color while the mouse cursor hovers above it.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Table head', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][first_row_th]" id="table_options_first_row_th"<?php echo ( $table['options']['first_row_th'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_first_row_th"><?php _e( 'The first row of your table is the table head (HTML tag &lt;th&gt;).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Table footer', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][table_footer]" id="table_options_table_footer"<?php echo ( $table['options']['table_footer'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_table_footer"><?php _e( 'The last row of your table is the table footer (HTML tag &lt;th&gt;).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Table Name', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][print_name]" id="table_options_print_name"<?php echo ( $table['options']['print_name'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_print_name"><?php _e( 'The Table Name will be written above the table (HTML tag &lt;h2&gt;).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Table Description', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][print_description]" id="table_options_print_description"<?php echo ( $table['options']['print_description'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="table_options_print_description"><?php _e( 'The Table Description will be written under the table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
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
            <input type="checkbox" name="table[options][use_tablesorter]" id="table_options_use_tablesorter"<?php echo ( $table['options']['use_tablesorter'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$this->options['enable_tablesorter'] || !$table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_use_tablesorter"><?php printf( __( 'Yes, use the &quot;%s&quot; JavaScript library with this table.', WP_TABLE_RELOADED_TEXTDOMAIN ), $js_library ); ?> <?php echo $js_library_text; ?><?php if ( !$this->options['enable_tablesorter'] ) { ?><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<small><?php printf( __( 'You must enable the use of a JavaScript library on the &quot;%s&quot; screen first.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></small><?php } ?></label></td>
        </tr>
        </table>
        </div>
        </div>

        <?php
        $datatables_enabled = $this->options['enable_tablesorter'] && ( 'datatables' == $this->options['tablesorter_script'] || 'datatables-tabletools' == $this->options['tablesorter_script'] );
        $tabletools_enabled = $this->options['enable_tablesorter'] && ( 'datatables-tabletools' == $this->options['tablesorter_script'] );
        ?>
        <div class="postbox<?php echo $this->helper->postbox_closed( 'datatables-features', true ); ?>">
        <h3 class="hndle"><span><?php _e( 'DataTables JavaScript Features', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'You can enable certain features for the DataTables JavaScript library here.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'More information on these features can be found on the <a href="http://www.datatables.net/">DataTables website</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <?php if ( !$datatables_enabled ) { ?>
        <p><strong><?php printf( __( 'You can currently not change these options, because you have not enabled the &quot;DataTables&quot; or the &quot;DataTables+TableTools&quot; JavaScript library on the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Plugin Options', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?><br/><?php _e( 'It is not possible to use these features with the &quot;Tablesorter&quot; or &quot;Tablesorter Extended&quot; libraries.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></strong></p>
        <?php } ?>
        <table class="wp-table-reloaded-options wp-table-reloaded-datatables-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'Sorting', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_sort]" id="table_options_datatables_sort"<?php echo ( $table['options']['datatables_sort'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$datatables_enabled || !$table['options']['use_tablesorter'] || !$table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_sort"><?php _e( 'Yes, enable sorting of table data by the visitor.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Pagination', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_paginate]" id="table_options_datatables_paginate"<?php echo ( $table['options']['datatables_paginate'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$datatables_enabled || !$table['options']['use_tablesorter'] || !$table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_paginate"><?php _e( 'Yes, enable pagination of the table (showing only a certain number of rows) by the visitor.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Length Change', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_lengthchange]" id="table_options_datatables_lengthchange"<?php echo ( $table['options']['datatables_lengthchange'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$datatables_enabled || !$table['options']['use_tablesorter'] || !$table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_lengthchange"><?php _e( 'Yes, allow visitor to change the number of rows shown when using pagination.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Filtering', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_filter]" id="table_options_datatables_filter"<?php echo ( $table['options']['datatables_filter'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$datatables_enabled || !$table['options']['use_tablesorter'] || !$table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_filter"><?php _e( 'Yes, enable the visitor to filter or search the table. Only rows with the search word in them are shown.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Info Bar', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_info]" id="table_options_datatables_info"<?php echo ( $table['options']['datatables_info'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$datatables_enabled || !$table['options']['use_tablesorter'] || !$table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_info"><?php _e( 'Yes, show the table information display. This shows information and statistics about the currently visible data, including filtering.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'TableTools', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="table[options][datatables_tabletools]" id="table_options_datatables_tabletools"<?php echo ( $table['options']['datatables_tabletools'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$tabletools_enabled || !$table['options']['use_tablesorter'] || !$table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="table_options_datatables_tabletools">
            <?php _e( 'Yes, activate the TableTools functions (Copy to Clipboard, Save to CSV, Save to XLS, Print Table) for this table.', WP_TABLE_RELOADED_TEXTDOMAIN );
            if ( !$tabletools_enabled ) { echo '<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<small>('; _e( 'This option can only be used with the &quot;DataTables+TableTools&quot; JavaScript library.', WP_TABLE_RELOADED_TEXTDOMAIN ); echo ')</small>';}
            ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Custom Commands', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="text" name="table[options][datatables_customcommands]" id="table_options_datatables_customcommands"<?php echo ( !$datatables_enabled || !$table['options']['use_tablesorter'] || !$table['options']['first_row_th'] ) ? ' disabled="disabled"': '' ; ?> value="<?php echo $this->helper->safe_output( $table['options']['datatables_customcommands'] ); ?>" style="width:100%" /> <label for="table_options_datatables_customcommands"><small><br/><?php _e( 'Enter additional DataTables JavaScript parameters that will be included with the script call here.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> (<?php _e( 'For advanced use only. Read the <a href="http://www.datatables.net/">DataTables documentation</a> before.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</small></label></td>
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
        <h3 class="hndle"><span><?php _e( 'Custom Data Fields', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
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