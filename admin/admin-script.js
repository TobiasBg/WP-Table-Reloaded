jQuery(document).ready(function($){

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
        jQuery.fn[ name ] = function(){
            return this.each( fn, arguments );
        };
    });

    // uses TBtoggleClass instead of toggleClass, see above
    $( '#table_contents :checkbox' ).change( function() {
        $( '#table_contents .hide-row :checkbox' ).each(function(){
            $( '#table_contents .' + $(this).attr('id') ).TBtoggleClass( 'row-hidden', $(this).attr( 'checked' ) );
        });
        $( '#table_contents .hide-column :checkbox' ).each(function(){
            $( '#table_contents .' + $(this).attr('id') ).TBtoggleClass( 'column-hidden', $(this).attr( 'checked' ) );
        });
	}).change();

    $("#export_format").change(function () {
        if ( 'csv' == $(this).val() )
            $(".tr-export-delimiter").css('display','table-row');
        else
            $(".tr-export-delimiter").css('display','none');
        })
        .change();

    var table_id = $(".wp-table-reloaded-options #table_id").val();
    $(".wp-table-reloaded-options #table_id").change(function () {
        if ( table_id != $(this).val() ) {
            if ( confirm( WP_Table_Reloaded_Admin.str_ChangeTableID ) )
                table_id = $(this).val();
            else
                $(this).val( table_id );
        }
    });

    $(".tr-import-addreplace input").click(function () {
        $('.tr-import-addreplace-table').css('display','none');

        if( 'replace' == $('.tr-import-addreplace input:checked').val() ) {
            $('.tr-import-addreplace-table').css('display','table-row');
        }
    });
    $('.tr-import-addreplace input:checked').click();

    $(".tr-import-from input").click(function () {
        $('.tr-import-file').css('display','none');
        $('.tr-import-url').css('display','none');
        $('.tr-import-field').css('display','none');
        $('.tr-import-server').css('display','none');
      
        if( 'file-upload' == $('.tr-import-from input:checked').val() ) {
            $('.tr-import-file').css('display','table-row');
        } else if( 'url' == $('.tr-import-from input:checked').val() ) {
            $('.tr-import-url').css('display','table-row');
        } else if( 'form-field' == $('.tr-import-from input:checked').val() ) {
            $('.tr-import-field').css('display','table-row');
        } else if( 'server' == $('.tr-import-from input:checked').val() ) {
            $('.tr-import-server').css('display','table-row');
        }
    });
    $('.tr-import-from input:checked').click();

    $("#options_use_custom_css input").click(function () {
	  if( $('#options_use_custom_css input:checked').val() ) {
        $('#options_custom_css').removeAttr("disabled");
	  } else {
        $('#options_custom_css').attr("disabled", true);
	  }
      return true;
	});

    $("#options_use_tableheadline input").click(function () {
	  if( $('#options_use_tableheadline input:checked').val() && $('#tablesorter_enabled').val() ) {
        $('#options_use_tablesorter input').removeAttr("disabled");
	  } else {
        $('#options_use_tablesorter input').attr("disabled", true);
	  }
      return true;
	});

    $('.postbox h3, .postbox .handlediv').click( function() {
	$($(this).parent().get(0)).toggleClass('closed');
    } );

    $("#options_uninstall input").click(function () {
	  if( $('#options_uninstall input:checked').val() ) {
		return confirm( WP_Table_Reloaded_Admin.str_UninstallCheckboxActivation );
	  }
	});

    /*
    $("#table_contents textarea").keypress(function () {
        var currentTextsize = $(this).val().split('\n').length;

        if ( 0 < currentTextsize ) {
            $(this).attr('rows', currentTextsize);
        }
	}).keypress();
    */

    var insert_html = '';

    function add_html() {
        var old_value = $(this).val();
        var new_value = old_value + insert_html;
        $(this).val( new_value );
        $("#table_contents textarea").unbind('click', add_html);
    }

    $("#a-insert-link").click(function () {
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
	});

    $("#a-insert-image").click(function () {
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
	});

    $("input.bulk_copy_tables").click(function () {
    	return confirm( WP_Table_Reloaded_Admin.str_BulkCopyTablesLink );
    });

    $("input.bulk_delete_tables").click(function () {
    	return confirm( WP_Table_Reloaded_Admin.str_BulkDeleteTablesLink );
    });

    $("input.bulk_wp_table_import_tables").click(function () {
    	return confirm( WP_Table_Reloaded_Admin.str_BulkImportwpTableTablesLink );
    });

    $("a.copy_table_link").click(function () {
    	return confirm( WP_Table_Reloaded_Admin.str_CopyTableLink );
    });

    $("a.delete_table_link").click(function () {
    	return confirm( WP_Table_Reloaded_Admin.str_DeleteTableLink );
    });
    
    $("a.delete_row_link").click(function () {
    	return confirm( WP_Table_Reloaded_Admin.str_DeleteRowLink );
    });

    $("a.delete_column_link").click(function () {
    	return confirm( WP_Table_Reloaded_Admin.str_DeleteColumnLink );
    });

    $("a.import_wptable_link").click(function () {
    	return confirm( WP_Table_Reloaded_Admin.str_ImportwpTableLink );
    });

    $("a.uninstall_plugin_link").click(function () {
        if ( confirm( WP_Table_Reloaded_Admin.str_UninstallPluginLink_1 ) ) { return confirm( WP_Table_Reloaded_Admin.str_UninstallPluginLink_2 ); } else { return false; }
    });

});