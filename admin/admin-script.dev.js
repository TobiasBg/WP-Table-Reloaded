/***************************************************************
* This JS file belongs to the Admin part of WP-Table Reloaded! *
*       PLEASE DO NOT make any changes here! Thank you!        *
***************************************************************/

jQuery(document).ready( function( $ ) {

    // WP_Table_Reloaded_Admin object will contain all localized strings and options that influence JavaScript

    // function to toggle textarea background color according to state of checkboxes
    $( '#a-hide-rows' ).click( function() {
        var rows_selected = $( '#table_contents tr:not(".table-foot") :checked' ).length;
        if ( rows_selected == 0 ) {
            alert( WP_Table_Reloaded_Admin.str_UnHideRowsNoSelection );
        } else {
            $( '#table_contents tr:not(".table-foot") :checked' ).removeAttr( 'checked' ).next().val( true ).parents('tr').addClass('row-hidden');
            set_table_data_changed();
        }
	});
    $( '#a-unhide-rows' ).click( function() {
        var rows_selected = $( '#table_contents tr:not(".table-foot") :checked' ).length;
        if ( rows_selected == 0 ) {
            alert( WP_Table_Reloaded_Admin.str_UnHideRowsNoSelection );
        } else {
            $( '#table_contents tr:not(".table-foot") :checked' ).removeAttr( 'checked' ).next().val( false ).parents('tr').removeClass('row-hidden');
            set_table_data_changed();
        }
	});
	
    $( '#a-hide-columns' ).click( function() {
        var cols_selected = $( '#table_contents .table-foot :checked' ).length;
        if ( cols_selected == 0 ) {
            alert( WP_Table_Reloaded_Admin.str_UnHideColsNoSelection );
        } else {
            $( '#table_contents .table-foot :checked' ).removeAttr( 'checked' ).next().val( true ).each( function() {
                $( '#table_contents .' + $(this).attr('id') ).addClass( 'column-hidden' );
            } );
            set_table_data_changed();
        }
	});
    $( '#a-unhide-columns' ).click( function() {
        var cols_selected = $( '#table_contents .table-foot :checked' ).length;
        if ( cols_selected == 0 ) {
            alert( WP_Table_Reloaded_Admin.str_UnHideColsNoSelection );
        } else {
            $( '#table_contents .table-foot :checked' ).removeAttr( 'checked' ).next().val( false ).each( function() {
                $( '#table_contents .' + $(this).attr('id') ).removeClass( 'column-hidden' );
            } );
            set_table_data_changed();
        }
	});
	
	$( '#button-insert-rows' ).click( function() {
        var rows_selected = $( '#table_contents tr:not(".table-foot") :checked' ).length;
        if ( rows_selected == 0 ) {
            alert( WP_Table_Reloaded_Admin.str_InsertRowsNoSelection );
            return false;
        } else {
            return true;
        }
	});

    $( '#button-insert-columns' ).click( function() {
        var cols_selected = $( '#table_contents .table-foot :checked' ).length;
        if ( cols_selected == 0 ) {
            alert( WP_Table_Reloaded_Admin.str_InsertColsNoSelection );
            return false;
        } else {
            return true;
        }
	});

    // functions to make focussed textareas bigger (if backend option is enabled)
    var focussed = null;
    if ( WP_Table_Reloaded_Admin.option_growing_textareas ) {
        $( '#table_contents textarea' ).focus( function() {
            $(focussed).removeClass('focus');
            focussed = $(this).parents('tr').find('textarea');
            $(focussed).addClass('focus');
        } );
    }

    // custom css textarea grows on focus
    function css_textarea_focus() {
        $( '#options_custom_css' ).addClass('focus')
        .unbind( 'focus', css_textarea_focus );
    }
    $( '#options_custom_css' ).bind( 'focus', css_textarea_focus );

    // show export delimiter dropdown box only if export format is csv
    $( '#export_format' ).change( function () {
        if ( 'csv' == $(this).val() )
            $('.tr-export-delimiter').show();
        else
            $('.tr-export-delimiter').hide();
    })
    .change();

    // confirm change of table ID
    var table_id = $( '.wp-table-reloaded-table-information #table_id' ).val();
    $( '.wp-table-reloaded-table-information #table_id' ).change( function () {
        if ( table_id != $(this).val() ) {
            if ( confirm( WP_Table_Reloaded_Admin.str_ChangeTableID ) ) {
                table_id = $(this).val();
                set_table_data_changed();
            } else {
                $(this).val( table_id );
            }
        }
    } );

    // show select box for table to replace only if needed
    $( '.tr-import-addreplace input' ).click( function () {
        if( 'replace' == $( '.tr-import-addreplace input:checked' ).val() )
            $( '.tr-import-addreplace-table' ).show();
        else
            $( '.tr-import-addreplace-table' ).hide();
    } );
    $( '.tr-import-addreplace input:checked' ).click();

    // show only checked import fields depending on radio button
    $( '.tr-import-from input' ).click( function () {
        $('.tr-import-file-upload, .tr-import-url, .tr-import-form-field, .tr-import-server').hide();
        $( '.tr-import-' + $( '.tr-import-from input:checked' ).val() ).show();
    } );
    $('.tr-import-from input:checked').click();

    // enable/disable custom css textarea according to state of checkbox
    $( '#options_use_custom_css' ).change( function () {
        if( $(this).attr('checked') )
            $( '#options_custom_css' ).removeAttr( 'disabled' );
        else
            $( '#options_custom_css' ).attr( 'disabled', 'disabled' );
    } );

    // tablesorter selection dropdown according to state of checkbox
    $( '#options_enable_tablesorter' ).change( function () {
        if( $(this).attr('checked') )
            $( '#options_tablesorter_script' ).removeAttr( 'disabled' );
        else
            $( '#options_tablesorter_script' ).attr( 'disabled' , 'disabled' );
    } );

    // enable/disable "use tableheadline" according to state of checkbox
    $( '#table_options_first_row_th' ).change( function () {
        if( WP_Table_Reloaded_Admin.option_datatables_active ) {
            if( $(this).attr('checked') && $( '#table_options_use_tablesorter' ).attr('checked') ) {
                $( '#table_options_datatables_sort' ).removeAttr( 'disabled' );
            } else {
                $( '#table_options_datatables_sort' ).attr( 'disabled', 'disabled' );
            }
        } else if( WP_Table_Reloaded_Admin.option_tablesorter_enabled ) {
            if( $(this).attr('checked') ) {
                $( '#table_options_use_tablesorter' ).removeAttr( 'disabled' );
            } else {
                $( '#table_options_use_tablesorter' ).attr( 'disabled', 'disabled' );
            }
        }
    } );

    // enable/disable DataTables options according to checkbox state
    $( '#table_options_use_tablesorter' ).change( function () {
        if( WP_Table_Reloaded_Admin.option_datatables_active && $(this).attr('checked') ) {
            $( '.wp-table-reloaded-datatables-options input' ).removeAttr( 'disabled' );
            if ( !$( '#table_options_first_row_th' ).attr('checked') )
                $( '#table_options_datatables_sort' ).attr( 'disabled', 'disabled' );
            if ( !WP_Table_Reloaded_Admin.option_tabletools_active )
                $( '#table_options_datatables_tabletools' ).attr( 'disabled', 'disabled' );

        } else {
            $( '.wp-table-reloaded-datatables-options input' ).attr( 'disabled', 'disabled' );
        }
    } );

    // confirm uninstall setting
    $( '#options_uninstall_upon_deactivation').click( function () {
        if( $(this).attr('checked') )
            return confirm( WP_Table_Reloaded_Admin.str_UninstallCheckboxActivation );
    } );


    // insert link functions
    var insert_html = '';
    function add_html() {
        var current_content = $(this).val();
        $(this).val( current_content + insert_html );
        $( '#table_contents textarea' ).unbind( 'click', add_html );
        set_table_data_changed();
    }

    $( '#a-insert-link' ).click( function () {
        var target = '';
        if ( WP_Table_Reloaded_Admin.option_add_target_blank_to_links )
            target = ' target="_blank"';
        var link_url = prompt( WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertURL + ':', 'http://' );
        if ( link_url ) {
            var link_text = prompt( WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText + ':', WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText );
            if ( link_text ) {
                insert_html = '<a href="' + link_url + '"' + target + '>' + link_text + '</a>';
                insert_html = prompt( WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertExplain, insert_html )
                if ( insert_html ) {
                    $("#table_contents textarea").bind('click', add_html);
                }
            }
        }
		return false;
    } );

    // insert image functions
    function call_media_library_thickbox() {
        edCanvas = this;
        $( '#table_contents textarea' ).unbind( 'click', call_media_library_thickbox );
        var link = $( '#a-insert-image' );
        tb_show( link.attr('title'), link.attr('href'), link.attr('rel') );
        $(this).blur();
        set_table_data_changed();
    }

    function add_image() {
        $(this).unbind( 'click' ); // this unbind is for WP 2.8, where our script is added before thickbox.js
        $(this).bind('click', add_image);
        if ( true == confirm( WP_Table_Reloaded_Admin.str_DataManipulationImageInsertThickbox ) )
            $("#table_contents textarea").bind( 'click', call_media_library_thickbox );
    }
    $( '#a-insert-image' ).unbind( 'click' ).bind('click', add_image); // this unbind is for WP < 2.8, where our script is added after thickbox.js

    // not all characters allowed for name of Custom Data Field
    $( '#insert_custom_field_name' ).keyup( function () {
        $(this).val( $(this).val().toLowerCase().replace(/[^a-z0-9_-]/g, '') );
    } );

    // remove/add title on focus/blur
    $( '.focus-blur-change' ).focus( function () {
        if ( $(this).attr('title') == $(this).val() )
            $(this).val( '' );
    } )
    .blur( function () {
        if ( '' == $(this).val() )
            $(this).val( $(this).attr('title') );
    } );

    $( '#table_custom_fields textarea' ).focus( function() {
        $( '#table_custom_fields .focus' ).removeClass('focus');
        $(this).addClass('focus');
    } );

    // confirmation of certain actions
    $( 'input.bulk_copy_tables' ).click( function () {
        return confirm( WP_Table_Reloaded_Admin.str_BulkCopyTablesLink );
    } );

    $( 'input.bulk_delete_tables' ).click( function () {
    	return confirm( WP_Table_Reloaded_Admin.str_BulkDeleteTablesLink );
    } );

    $( 'input.bulk_wp_table_import_tables' ).click( function () {
    	return confirm( WP_Table_Reloaded_Admin.str_BulkImportwpTableTablesLink );
    } );

    $( 'a.copy_table_link' ).click( function () {
    	return confirm( WP_Table_Reloaded_Admin.str_CopyTableLink );
    } );

    /*
    // moved to inline script, because of using wpList script
    $( 'a.delete_table_link' ).click( function () {
    	return confirm( WP_Table_Reloaded_Admin.str_DeleteTableLink );
    } );
    */

    $( '#button-delete-rows' ).click( function () {
        var rows_cb = $( '#table_contents tr:not(".table-foot") :checkbox' ).length - 1; // -1 because of invisible checkbox in .table-head
        var rows_selected = $( '#table_contents tr:not(".table-foot") :checked' ).length;

        if ( rows_selected == 0 ) {
            alert( WP_Table_Reloaded_Admin.str_DeleteRowsFailedNoSelection );
            return false;
        } else {
            if ( rows_cb == rows_selected ) {
                alert( WP_Table_Reloaded_Admin.str_DeleteRowsFailedNotAll );
                return false;
            } else {
        	   return confirm( WP_Table_Reloaded_Admin.str_DeleteRowsConfirm );
            }
        }
    } );
    
    $( '#button-delete-columns' ).click( function () {
        var cols_cb = $( '#table_contents .table-foot :checkbox' ).length;
        var cols_selected = $( '#table_contents .table-foot :checked' ).length;

        if ( cols_selected == 0 ) {
            alert( WP_Table_Reloaded_Admin.str_DeleteColsFailedNoSelection );
            return false;
        } else {
            if ( cols_cb == cols_selected ) {
                alert( WP_Table_Reloaded_Admin.str_DeleteColsFailedNotAll );
                return false;
            } else {
        	   return confirm( WP_Table_Reloaded_Admin.str_DeleteColsConfirm );
            }
        }
    } );
    
    $( 'a.import_wptable_link' ).click( function () {
    	return confirm( WP_Table_Reloaded_Admin.str_ImportwpTableLink );
    } );

    $( '#import_wp_table_reloaded_dump_file' ).click( function () {
    	return confirm( WP_Table_Reloaded_Admin.str_ImportDumpFile );
    } );

    $( '#uninstall_plugin_link' ).click( function () {
        if ( confirm( WP_Table_Reloaded_Admin.str_UninstallPluginLink_1 ) )
            return confirm( WP_Table_Reloaded_Admin.str_UninstallPluginLink_2 );
        else
            return false;
    } );

    $( 'a.cf_shortcode_link' ).click( function () {
    	var dummy = prompt( WP_Table_Reloaded_Admin.str_CFShortcodeMessage, $(this).attr('title') );
    	return false;
    } );

    $( 'a.table_shortcode_link' ).click( function () {
    	var dummy = prompt( WP_Table_Reloaded_Admin.str_TableShortcodeMessage, $(this).attr('title') );
    	return false;
    } );
    
    // toggling of boxes
    $( '.postbox h3, .postbox .handlediv' ).click( function() {
        $( $(this).parent().get(0) ).toggleClass('closed');
    } );

    // exit message, if table content was changed but not yet saved
    var table_data_changed = false;

    function set_table_data_changed() {
        table_data_changed = true;
        $( '#wp_table_reloaded_edit_table' ).find( '#table_id, #table_name, textarea' ).unbind( 'click', set_table_data_changed );
    }

    if ( WP_Table_Reloaded_Admin.option_show_exit_warning ) {
        window.onbeforeunload = function(){
            if ( table_data_changed )
                return WP_Table_Reloaded_Admin.str_saveAlert;
        };

        $("#wp_table_reloaded_edit_table input[name='submit[update]'], #wp_table_reloaded_edit_table input[name='submit[save_back]']").click(function(){
            $( '#wp_table_reloaded_edit_table .wp-table-reloaded-options input' ).removeAttr( 'disabled' );
            window.onbeforeunload = null;
        } );

        $( '#wp_table_reloaded_edit_table' ).find( '#table_name, textarea' ).bind( 'change', set_table_data_changed ); // see also ID change function above
        
        $( '#wp_table_reloaded_edit_table .wp-table-reloaded-options :checkbox' ).bind( 'change', set_table_data_changed );
    }

} );