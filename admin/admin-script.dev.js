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
	});
    //.change();

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
            $('.tr-export-delimiter').show();
        else
            $('.tr-export-delimiter').hide();
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
            $( '.tr-import-addreplace-table' ).show();
        else
            $( '.tr-import-addreplace-table' ).hide();
    } );
    $( '.tr-import-addreplace input:checked' ).click();

    // show only checked import fields depending on radio button
    $( '.tr-import-from input' ).click( function () {
        $('.tr-import-file-upload').hide();
        $('.tr-import-url').hide();
        $('.tr-import-form-field').hide();
        $('.tr-import-server').hide();

        $( '.tr-import-' + $( '.tr-import-from input:checked' ).val() ).show();
    } );
    $('.tr-import-from input:checked').click();

    // enable/disable custom css textarea according to state of checkbox
    $( '#options_use_custom_css' ).change( function () {
        if( $(this).attr('checked') )
            $( '#options_custom_css' ).removeAttr("disabled");
        else
            $( '#options_custom_css' ).attr("disabled", true);
    } );

    // enable/disable Extended Tablesorter checkbox according to state of checkbox
    $( '#options_enable_tablesorter' ).change( function () {
        if( $(this).attr('checked') )
            $( '#options_use_tablesorter_extended' ).removeAttr("disabled");
        else
            $( '#options_use_tablesorter_extended' ).attr("disabled", true);
    } );

    // enable/disable "use tableheadline" according to state of checkbox
    $( '#table_options_first_row_th' ).change( function () {
        if( $(this).attr('checked') && $( '#tablesorter_enabled' ).val() ) {
            $( '#table_options_use_tablesorter' ).removeAttr("disabled");
        } else {
            $( '#table_options_use_tablesorter' ).attr("disabled", true);
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

    // insert image functions
    function call_media_library_thickbox() {
        edCanvas = this;
        $( '#table_contents textarea' ).unbind( 'click', call_media_library_thickbox );
        var link = $( '#a-insert-image' );
        tb_show( link.attr('title'), link.attr('href'), link.attr('rel') );
        $(this).blur();
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