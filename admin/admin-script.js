jQuery(document).ready(function($){

    // WP_Table_Reloaded_Admin object will contain all localized strings

    $("#export_format").change(function () {
          if ( 'csv' == $(this).val() )
		$(".tr-export-delimiter").css('display','table-row');
	  else
		$(".tr-export-delimiter").css('display','none');
        })
        .change();

    $("#options_use_custom_css input").click(function () {
	  if( $('#options_use_custom_css input:checked').val() ) {
        $('#options_custom_css textarea').removeAttr("disabled");
	  } else {
        $('#options_custom_css textarea').attr("disabled", true);
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

    var insert_html = '';

    function add_html() {
        var old_value = $(this).val();
        var new_value = old_value + insert_html;
        $(this).val( new_value );
        $("#table_contents input").unbind('click', add_html);
    }

    $("#a-insert-link").click(function () {
        var link_url = prompt( WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertURL + ':', 'http://' );
        if ( link_url ) {
            var link_text = prompt( WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText + ':', WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText );
            if ( link_text ) {
                insert_html = '<a href="' + link_url + '">' + link_text + '</a>';
                if ( confirm( WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertExplain + '\n\n' + insert_html ) ) {
                    $("#table_contents input").bind('click', add_html);
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
                    $("#table_contents input").bind('click', add_html);
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