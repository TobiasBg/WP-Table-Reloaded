jQuery(document).ready( function( $ ) {

    // WP_Table_Reloaded_Admin object will contain all localized strings

    // jQuery's original toggleClass needs jQuery 1.3, which is only available since 1.3
    // which is only available since WP 2.8, that's why we copy the function here to maintain
    // backward compatibility
    jQuery.each({
        TBtoggleClass: function( classNames, state ) {
            if( typeof state !== "boolean" )
                state = !jQuery.className.has( this, classNames );
            jQuery.className[ state ? "add" : "remove" ]( this, classNames );
        }
    }, function(name, fn){
        jQuery.fn[ name ] = function() {
            return this.each( fn, arguments );
        };
    });

    // function to toggle textarea background color according to state of checkboxes
    // uses TBtoggleClass instead of toggleClass, see above
    var cb_id, cb_class;
    $( '#table_contents tbody :checkbox' ).change( function() {
        $( '#table_contents tbody :checkbox' ).each( function() {
            cb_id = $(this).attr('id');
            cb_class = ( -1 != cb_id.search(/row/) ) ? 'row-hidden' : 'column-hidden';
            $( '#table_contents .' + cb_id ).TBtoggleClass( cb_class, $(this).attr('checked') );
        } );
	})
    .change();

    // functions to make focussed textareas bigger
    // commented code is for handling all textareas in same row or same column
    // var ta_idx, tas;
    $( '#table_contents textarea' ).focus( function() {
        $( '#table_contents .focus' ).removeClass('focus');
        $(this).parents('tr').find('textarea').addClass('focus');
        //tas = $(this).parents('tr').find('textarea');
        //ta_idx = $( tas ).index( this ) + 2; // 2 is from: 1: <th> infront, 1: 1-based-index
        //$( '#table_contents tr :nth-child(' + ta_idx + ') textarea' ).add( tas ).addClass('focus');
    } );
    //.blur( function() {
    //    $(this).parents('tr').find('textarea').removeClass('focus');
        //tas = $(this).parents('tr').find('textarea');
        //ta_idx = $( tas ).index( this ) + 2; // 2 is from: 1: <th> infront, 1: 1-based-index
        //$( '#table_contents tr :nth-child(' + ta_idx + ') textarea' ).add( tas ).removeClass('focus');
    //} );

    // old code that makes textareas grow depending on content
    /*
    $("#table_contents textarea").keypress(function () {
        var currentTextsize = $(this).val().split('\n').length;

        if ( 0 < currentTextsize ) {
            $(this).attr('rows', currentTextsize);
        }
	}).keypress();
    */

    // show export delimiter selectbox only if export format is csv
    $( '#export_format' ).change( function () {
        if ( 'csv' == $(this).val() )
            $('.tr-export-delimiter').css('display','table-row');
        else
            $('.tr-export-delimiter').css('display','none');
    })
    .change();

    // confirm change of table ID
    var table_id = $( '.wp-table-reloaded-table-information #table_id' ).val();
    $( '.wp-table-reloaded-table-information #table_id' ).change( function () {
        if ( table_id != $(this).val() ) {
            if ( confirm( WP_Table_Reloaded_Admin.str_ChangeTableID ) )
                table_id = $(this).val();
            else
                $(this).val( table_id );
        }
    } );

    // show select box for table to replace only if needed
    $( '.tr-import-addreplace input' ).click( function () {
        if( 'replace' == $( '.tr-import-addreplace input:checked' ).val() )
            $( '.tr-import-addreplace-table' ).css('display','table-row');
        else
            $( '.tr-import-addreplace-table' ).css('display','none');
    } );
    $( '.tr-import-addreplace input:checked' ).click();

    // show only checked import fields depending on radio button
    $( '.tr-import-from input ').click( function () {
        $('.tr-import-file-upload').css('display','none');
        $('.tr-import-url').css('display','none');
        $('.tr-import-form-field').css('display','none');
        $('.tr-import-server').css('display','none');

        $( '.tr-import-' + $( '.tr-import-from input:checked' ).val() ).css('display','table-row');
    } );
    $('.tr-import-from input:checked').click();

    // enable/disable custom css textarea according to state of checkbox
    $( '#options_use_custom_css :checkbox' ).change( function () {
        if( $(this).attr('checked') )
            $( '#options_custom_css' ).removeAttr("disabled");
        else
            $( '#options_custom_css' ).attr("disabled", true);
    } );

    // enable/disable "use tableheadline" according to state of checkbox
    $( '#options_use_tableheadline :checkbox' ).change( function () {
        if( $(this).attr('checked') && $( '#tablesorter_enabled' ).val() ) {
            $( '#options_use_tablesorter :checkbox' ).removeAttr("disabled");
        } else {
            $( '#options_use_tablesorter :checkbox' ).attr("disabled", true);
        }
    } );

    // confirm uninstall setting
    $( '#options_uninstall :checkbox ').click( function () {
        if( $(this).attr('checked') )
            return confirm( WP_Table_Reloaded_Admin.str_UninstallCheckboxActivation );
    } );


    // insert image / insert link functions
    var insert_html = '';

    function add_html() {
        var current_content = $(this).val();
        $(this).val( current_content + insert_html );
        $( '#table_contents textarea' ).unbind( 'click', add_html );
    }

    $( '#a-insert-link' ).click( function () {
        var link_url = prompt( WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertURL + ':', 'http://' );
        if ( link_url ) {
            var link_text = prompt( WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText + ':', WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText );
            if ( link_text ) {
                insert_html = '<a href="' + link_url + '">' + link_text + '</a>';
                if ( confirm( WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertExplain + '\n\n' + insert_html ) ) {
                    $("#table_contents textarea").bind('click', add_html);
                }
            }
        }
		return false;
    } );

    $( '#a-insert-image' ).click( function () {
        var image_url = prompt( WP_Table_Reloaded_Admin.str_DataManipulationImageInsertURL + ':', 'http://' );
        if ( image_url ) {
            var image_alt = prompt( WP_Table_Reloaded_Admin.str_DataManipulationImageInsertAlt + ':', '' );
            // if ( image_alt ) { // won't check for alt, because there are cases where an empty one makes sense
                insert_html = '<img src="' + image_url + '" alt="' + image_alt + '" />';
                if ( true == confirm( WP_Table_Reloaded_Admin.str_DataManipulationImageInsertExplain + '\n\n' + insert_html ) ) {
                    $("#table_contents textarea").bind('click', add_html);
                }
            // }
        }
		return false;
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

    $( 'a.delete_table_link' ).click( function () {
    	return confirm( WP_Table_Reloaded_Admin.str_DeleteTableLink );
    } );
    
    $(' a.delete_row_link' ).click( function () {
    	return confirm( WP_Table_Reloaded_Admin.str_DeleteRowLink );
    } );

    $( 'a.delete_column_link' ).click( function () {
    	return confirm( WP_Table_Reloaded_Admin.str_DeleteColumnLink );
    } );

    $( 'a.import_wptable_link' ).click( function () {
    	return confirm( WP_Table_Reloaded_Admin.str_ImportwpTableLink );
    } );

    $( 'a.uninstall_plugin_link' ).click( function () {
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

} );