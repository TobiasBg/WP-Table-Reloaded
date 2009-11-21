jQuery(document).ready(function(f){f("#a-hide-rows").click(function(){var i=f('#table_contents tr:not(".table-foot") :checked').length;if(i==0){alert(WP_Table_Reloaded_Admin.str_UnHideRowsNoSelection)}else{f('#table_contents tr:not(".table-foot") :checked').removeAttr("checked").next().val(true).parents("tr").addClass("row-hidden");b()}});f("#a-unhide-rows").click(function(){var i=f('#table_contents tr:not(".table-foot") :checked').length;if(i==0){alert(WP_Table_Reloaded_Admin.str_UnHideRowsNoSelection)}else{f('#table_contents tr:not(".table-foot") :checked').removeAttr("checked").next().val(false).parents("tr").removeClass("row-hidden");b()}});f("#a-hide-columns").click(function(){var i=f("#table_contents .table-foot :checked").length;if(i==0){alert(WP_Table_Reloaded_Admin.str_UnHideColsNoSelection)}else{f("#table_contents .table-foot :checked").removeAttr("checked").next().val(true).each(function(){f("#table_contents ."+f(this).attr("id")).addClass("column-hidden")});b()}});f("#a-unhide-columns").click(function(){var i=f("#table_contents .table-foot :checked").length;if(i==0){alert(WP_Table_Reloaded_Admin.str_UnHideColsNoSelection)}else{f("#table_contents .table-foot :checked").removeAttr("checked").next().val(false).each(function(){f("#table_contents ."+f(this).attr("id")).removeClass("column-hidden")});b()}});f("#button-insert-rows").click(function(){var i=f('#table_contents tr:not(".table-foot") :checked').length;if(i==0){alert(WP_Table_Reloaded_Admin.str_InsertRowsNoSelection);return false}else{return true}});f("#button-insert-columns").click(function(){var i=f("#table_contents .table-foot :checked").length;if(i==0){alert(WP_Table_Reloaded_Admin.str_InsertColsNoSelection);return false}else{return true}});if(WP_Table_Reloaded_Admin.option_growing_textareas){f("#table_contents textarea").focus(function(){f("#table_contents .focus").removeClass("focus");f(this).parents("tr").find("textarea").addClass("focus")})}f("#export_format").change(function(){if("csv"==f(this).val()){f(".tr-export-delimiter").show()}else{f(".tr-export-delimiter").hide()}}).change();var h=f(".wp-table-reloaded-table-information #table_id").val();f(".wp-table-reloaded-table-information #table_id").change(function(){if(h!=f(this).val()){if(confirm(WP_Table_Reloaded_Admin.str_ChangeTableID)){h=f(this).val();b()}else{f(this).val(h)}}});f(".tr-import-addreplace input").click(function(){if("replace"==f(".tr-import-addreplace input:checked").val()){f(".tr-import-addreplace-table").show()}else{f(".tr-import-addreplace-table").hide()}});f(".tr-import-addreplace input:checked").click();f(".tr-import-from input").click(function(){f(".tr-import-file-upload, .tr-import-url, .tr-import-form-field, .tr-import-server").hide();f(".tr-import-"+f(".tr-import-from input:checked").val()).show()});f(".tr-import-from input:checked").click();f("#options_use_custom_css").change(function(){if(f(this).attr("checked")){f("#options_custom_css").removeAttr("disabled")}else{f("#options_custom_css").attr("disabled","disabled")}});f("#options_enable_tablesorter").change(function(){if(f(this).attr("checked")){f("#options_tablesorter_script").removeAttr("disabled")}else{f("#options_tablesorter_script").attr("disabled","disabled")}});f("#table_options_first_row_th").change(function(){if(WP_Table_Reloaded_Admin.option_datatables_active){if(f(this).attr("checked")&&f("#table_options_use_tablesorter").attr("checked")){f("#table_options_datatables_sort").removeAttr("disabled")}else{f("#table_options_datatables_sort").attr("disabled","disabled")}}else{if(WP_Table_Reloaded_Admin.option_tablesorter_enabled){if(f(this).attr("checked")){f("#table_options_use_tablesorter").removeAttr("disabled")}else{f("#table_options_use_tablesorter").attr("disabled","disabled")}}}});f("#table_options_use_tablesorter").change(function(){if(WP_Table_Reloaded_Admin.option_datatables_active&&f(this).attr("checked")){f(".wp-table-reloaded-datatables-options :checkbox").removeAttr("disabled");if(!f("#table_options_first_row_th").attr("checked")){f("#table_options_datatables_sort").attr("disabled","disabled")}}else{f(".wp-table-reloaded-datatables-options :checkbox").attr("disabled","disabled")}});f("#options_uninstall_upon_deactivation").click(function(){if(f(this).attr("checked")){return confirm(WP_Table_Reloaded_Admin.str_UninstallCheckboxActivation)}});var c="";function e(){var i=f(this).val();f(this).val(i+c);f("#table_contents textarea").unbind("click",e);b()}f("#a-insert-link").click(function(){var k="";if(WP_Table_Reloaded_Admin.option_add_target_blank_to_links){k=' target="_blank"'}var j=prompt(WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertURL+":","http://");if(j){var i=prompt(WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText+":",WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText);if(i){c='<a href="'+j+'"'+k+">"+i+"</a>";c=prompt(WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertExplain,c);if(c){f("#table_contents textarea").bind("click",e)}}}return false});function g(){edCanvas=this;f("#table_contents textarea").unbind("click",g);var i=f("#a-insert-image");tb_show(i.attr("title"),i.attr("href"),i.attr("rel"));f(this).blur();b()}function d(){f(this).unbind("click");f(this).bind("click",d);if(true==confirm(WP_Table_Reloaded_Admin.str_DataManipulationImageInsertThickbox)){f("#table_contents textarea").bind("click",g)}}f("#a-insert-image").unbind("click").bind("click",d);f("#insert_custom_field_name").keyup(function(){f(this).val(f(this).val().toLowerCase().replace(/[^a-z0-9_-]/g,""))});f(".focus-blur-change").focus(function(){if(f(this).attr("title")==f(this).val()){f(this).val("")}}).blur(function(){if(""==f(this).val()){f(this).val(f(this).attr("title"))}});f("#table_custom_fields textarea").focus(function(){f("#table_custom_fields .focus").removeClass("focus");f(this).addClass("focus")});f("input.bulk_copy_tables").click(function(){return confirm(WP_Table_Reloaded_Admin.str_BulkCopyTablesLink)});f("input.bulk_delete_tables").click(function(){return confirm(WP_Table_Reloaded_Admin.str_BulkDeleteTablesLink)});f("input.bulk_wp_table_import_tables").click(function(){return confirm(WP_Table_Reloaded_Admin.str_BulkImportwpTableTablesLink)});f("a.copy_table_link").click(function(){return confirm(WP_Table_Reloaded_Admin.str_CopyTableLink)});f("#button-delete-rows").click(function(){var j=f('#table_contents tr:not(".table-foot") :checkbox').length-1;var i=f('#table_contents tr:not(".table-foot") :checked').length;if(i==0){alert(WP_Table_Reloaded_Admin.str_DeleteRowsFailedNoSelection);return false}else{if(j==i){alert(WP_Table_Reloaded_Admin.str_DeleteRowsFailedNotAll);return false}else{return confirm(WP_Table_Reloaded_Admin.str_DeleteRowsConfirm)}}});f("#button-delete-columns").click(function(){var i=f("#table_contents .table-foot :checkbox").length;var j=f("#table_contents .table-foot :checked").length;if(j==0){alert(WP_Table_Reloaded_Admin.str_DeleteColsFailedNoSelection);return false}else{if(i==j){alert(WP_Table_Reloaded_Admin.str_DeleteColsFailedNotAll);return false}else{return confirm(WP_Table_Reloaded_Admin.str_DeleteColsConfirm)}}});f("a.import_wptable_link").click(function(){return confirm(WP_Table_Reloaded_Admin.str_ImportwpTableLink)});f("#import_wp_table_reloaded_dump_file").click(function(){return confirm(WP_Table_Reloaded_Admin.str_ImportDumpFile)});f("a.uninstall_plugin_link").click(function(){if(confirm(WP_Table_Reloaded_Admin.str_UninstallPluginLink_1)){return confirm(WP_Table_Reloaded_Admin.str_UninstallPluginLink_2)}else{return false}});f("a.cf_shortcode_link").click(function(){var i=prompt(WP_Table_Reloaded_Admin.str_CFShortcodeMessage,f(this).attr("title"));return false});f("a.table_shortcode_link").click(function(){var i=prompt(WP_Table_Reloaded_Admin.str_TableShortcodeMessage,f(this).attr("title"));return false});f(".postbox h3, .postbox .handlediv").click(function(){f(f(this).parent().get(0)).toggleClass("closed")});var a=false;function b(){a=true;f("#wp_table_reloaded_edit_table").find("#table_id, #table_name, textarea").unbind("click",b)}if(WP_Table_Reloaded_Admin.option_show_exit_warning){window.onbeforeunload=function(){if(a){return WP_Table_Reloaded_Admin.str_saveAlert}};f("#wp_table_reloaded_edit_table input[name='submit[update]'], #wp_table_reloaded_edit_table input[name='submit[save_back]']").click(function(){window.onbeforeunload=null});f("#wp_table_reloaded_edit_table").find("#table_name, textarea").bind("change",b);f("#wp_table_reloaded_edit_table .wp-table-reloaded-options :checkbox").bind("change",b)}});