jQuery(document).ready(function(b){b("#a-hide-rows").click(function(){var k=b('#table_contents tr:not(".table-foot") :checked').length;if(k==0){alert(WP_Table_Reloaded_Admin.str_UnHideRowsNoSelection)}else{b('#table_contents tr:not(".table-foot") :checked').removeAttr("checked").next().val(true).parents("tr").addClass("row-hidden");c()}});b("#a-unhide-rows").click(function(){var k=b('#table_contents tr:not(".table-foot") :checked').length;if(k==0){alert(WP_Table_Reloaded_Admin.str_UnHideRowsNoSelection)}else{b('#table_contents tr:not(".table-foot") :checked').removeAttr("checked").next().val(false).parents("tr").removeClass("row-hidden");c()}});b("#a-hide-columns").click(function(){var k=b("#table_contents .table-foot :checked").length;if(k==0){alert(WP_Table_Reloaded_Admin.str_UnHideColsNoSelection)}else{b("#table_contents .table-foot :checked").removeAttr("checked").next().val(true).each(function(){b("#table_contents ."+b(this).attr("id")).addClass("column-hidden")});c()}});b("#a-unhide-columns").click(function(){var k=b("#table_contents .table-foot :checked").length;if(k==0){alert(WP_Table_Reloaded_Admin.str_UnHideColsNoSelection)}else{b("#table_contents .table-foot :checked").removeAttr("checked").next().val(false).each(function(){b("#table_contents ."+b(this).attr("id")).removeClass("column-hidden")});c()}});b("#button-insert-rows").click(function(){var k=b('#table_contents tr:not(".table-foot") :checked').length;if(k==0){alert(WP_Table_Reloaded_Admin.str_InsertRowsNoSelection);return false}else{return true}});b("#button-insert-columns").click(function(){var k=b("#table_contents .table-foot :checked").length;if(k==0){alert(WP_Table_Reloaded_Admin.str_InsertColsNoSelection);return false}else{return true}});var e=null;if(WP_Table_Reloaded_Admin.option_growing_textareas){b("#table_contents textarea").focus(function(){b(e).removeClass("focus");e=b(this).parents("tr").find("textarea");b(e).addClass("focus")})}function g(){b("#options_custom_css").addClass("focus").unbind("focus",g)}b("#options_custom_css").bind("focus",g);b("#export_format").change(function(){if("csv"==b(this).val()){b(".tr-export-delimiter").show()}else{b(".tr-export-delimiter").hide()}}).change();var h=b(".wp-table-reloaded-table-information #table_id").val();b(".wp-table-reloaded-table-information #table_id").change(function(){if(h!=b(this).val()){if(confirm(WP_Table_Reloaded_Admin.str_ChangeTableID)){h=b(this).val();c()}else{b(this).val(h)}}});b(".tr-import-addreplace input").click(function(){if("replace"==b(".tr-import-addreplace input:checked").val()){b(".tr-import-addreplace-table").show()}else{b(".tr-import-addreplace-table").hide()}});b(".tr-import-addreplace input:checked").click();b(".tr-import-from input").click(function(){b(".tr-import-file-upload, .tr-import-url, .tr-import-form-field, .tr-import-server").hide();b(".tr-import-"+b(".tr-import-from input:checked").val()).show()});b(".tr-import-from input:checked").click();b("#options_use_custom_css").change(function(){if(b(this).attr("checked")){b("#options_custom_css").removeAttr("disabled")}else{b("#options_custom_css").attr("disabled","disabled")}});b("#options_enable_tablesorter").change(function(){if(b(this).attr("checked")){b("#options_tablesorter_script").removeAttr("disabled")}else{b("#options_tablesorter_script").attr("disabled","disabled")}});b("#table_options_first_row_th").change(function(){if(WP_Table_Reloaded_Admin.option_datatables_active){if(b(this).attr("checked")&&b("#table_options_use_tablesorter").attr("checked")){b("#table_options_datatables_sort").removeAttr("disabled")}else{b("#table_options_datatables_sort").attr("disabled","disabled")}}else{if(WP_Table_Reloaded_Admin.option_tablesorter_enabled){if(b(this).attr("checked")){b("#table_options_use_tablesorter").removeAttr("disabled")}else{b("#table_options_use_tablesorter").attr("disabled","disabled")}}}});b("#table_options_use_tablesorter").change(function(){if(WP_Table_Reloaded_Admin.option_datatables_active&&b(this).attr("checked")){b(".wp-table-reloaded-datatables-options input").removeAttr("disabled");if(!b("#table_options_first_row_th").attr("checked")){b("#table_options_datatables_sort").attr("disabled","disabled")}if(!WP_Table_Reloaded_Admin.option_tabletools_active){b("#table_options_datatables_tabletools").attr("disabled","disabled")}}else{b(".wp-table-reloaded-datatables-options input").attr("disabled","disabled")}});b("#options_uninstall_upon_deactivation").click(function(){if(b(this).attr("checked")){return confirm(WP_Table_Reloaded_Admin.str_UninstallCheckboxActivation)}});var i="";function d(){var k=b(this).val();b(this).val(k+i);b("#table_contents textarea").unbind("click",d);c()}b("#a-insert-link").click(function(){var m="";if(WP_Table_Reloaded_Admin.option_add_target_blank_to_links){m=' target="_blank"'}var l=prompt(WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertURL+":","http://");if(l){var k=prompt(WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText+":",WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText);if(k){i='<a href="'+l+'"'+m+">"+k+"</a>";i=prompt(WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertExplain,i);if(i){b("#table_contents textarea").bind("click",d)}}}return false});function a(){edCanvas=this;b("#table_contents textarea").unbind("click",a);var k=b("#a-insert-image");tb_show(k.attr("title"),k.attr("href"),k.attr("rel"));b(this).blur();c()}function f(){b(this).unbind("click");b(this).bind("click",f);if(true==confirm(WP_Table_Reloaded_Admin.str_DataManipulationImageInsertThickbox)){b("#table_contents textarea").bind("click",a)}}b("#a-insert-image").unbind("click").bind("click",f);b("#insert_custom_field_name").keyup(function(){b(this).val(b(this).val().toLowerCase().replace(/[^a-z0-9_-]/g,""))});b(".focus-blur-change").focus(function(){if(b(this).attr("title")==b(this).val()){b(this).val("")}}).blur(function(){if(""==b(this).val()){b(this).val(b(this).attr("title"))}});b("#table_custom_fields textarea").focus(function(){b("#table_custom_fields .focus").removeClass("focus");b(this).addClass("focus")});b("input.bulk_copy_tables").click(function(){return confirm(WP_Table_Reloaded_Admin.str_BulkCopyTablesLink)});b("input.bulk_delete_tables").click(function(){return confirm(WP_Table_Reloaded_Admin.str_BulkDeleteTablesLink)});b("input.bulk_wp_table_import_tables").click(function(){return confirm(WP_Table_Reloaded_Admin.str_BulkImportwpTableTablesLink)});b("a.copy_table_link").click(function(){return confirm(WP_Table_Reloaded_Admin.str_CopyTableLink)});b("#button-delete-rows").click(function(){var l=b('#table_contents tr:not(".table-foot") :checkbox').length-1;var k=b('#table_contents tr:not(".table-foot") :checked').length;if(k==0){alert(WP_Table_Reloaded_Admin.str_DeleteRowsFailedNoSelection);return false}else{if(l==k){alert(WP_Table_Reloaded_Admin.str_DeleteRowsFailedNotAll);return false}else{return confirm(WP_Table_Reloaded_Admin.str_DeleteRowsConfirm)}}});b("#button-delete-columns").click(function(){var k=b("#table_contents .table-foot :checkbox").length;var l=b("#table_contents .table-foot :checked").length;if(l==0){alert(WP_Table_Reloaded_Admin.str_DeleteColsFailedNoSelection);return false}else{if(k==l){alert(WP_Table_Reloaded_Admin.str_DeleteColsFailedNotAll);return false}else{return confirm(WP_Table_Reloaded_Admin.str_DeleteColsConfirm)}}});b("a.import_wptable_link").click(function(){return confirm(WP_Table_Reloaded_Admin.str_ImportwpTableLink)});b("#import_wp_table_reloaded_dump_file").click(function(){return confirm(WP_Table_Reloaded_Admin.str_ImportDumpFile)});b("a.uninstall_plugin_link").click(function(){if(confirm(WP_Table_Reloaded_Admin.str_UninstallPluginLink_1)){return confirm(WP_Table_Reloaded_Admin.str_UninstallPluginLink_2)}else{return false}});b("a.cf_shortcode_link").click(function(){var k=prompt(WP_Table_Reloaded_Admin.str_CFShortcodeMessage,b(this).attr("title"));return false});b("a.table_shortcode_link").click(function(){var k=prompt(WP_Table_Reloaded_Admin.str_TableShortcodeMessage,b(this).attr("title"));return false});b(".postbox h3, .postbox .handlediv").click(function(){b(b(this).parent().get(0)).toggleClass("closed")});var j=false;function c(){j=true;b("#wp_table_reloaded_edit_table").find("#table_id, #table_name, textarea").unbind("click",c)}if(WP_Table_Reloaded_Admin.option_show_exit_warning){window.onbeforeunload=function(){if(j){return WP_Table_Reloaded_Admin.str_saveAlert}};b("#wp_table_reloaded_edit_table input[name='submit[update]'], #wp_table_reloaded_edit_table input[name='submit[save_back]']").click(function(){b("#wp_table_reloaded_edit_table .wp-table-reloaded-options input").removeAttr("disabled");window.onbeforeunload=null});b("#wp_table_reloaded_edit_table").find("#table_name, textarea").bind("change",c);b("#wp_table_reloaded_edit_table .wp-table-reloaded-options :checkbox").bind("change",c)}});