jQuery(document).ready(function(b){jQuery.each({TBtoggleClass:function(l,k){if(typeof k!=="boolean"){k=!jQuery.className.has(this,l)}jQuery.className[k?"add":"remove"](this,l)}},function(k,l){jQuery.fn[k]=function(){return this.each(l,arguments)}});var d,j;b("#table_contents tbody :checkbox").change(function(){b("#table_contents tbody :checkbox").each(function(){d=b(this).attr("id");j=(-1!=d.search(/row/))?"row-hidden":"column-hidden";b("#table_contents ."+d).TBtoggleClass(j,b(this).attr("checked"))})});b("#table_contents textarea").focus(function(){b("#table_contents .focus").removeClass("focus");b(this).parents("tr").find("textarea").addClass("focus")});b("#export_format").change(function(){if("csv"==b(this).val()){b(".tr-export-delimiter").show()}else{b(".tr-export-delimiter").hide()}}).change();var g=b(".wp-table-reloaded-table-information #table_id").val();b(".wp-table-reloaded-table-information #table_id").change(function(){if(g!=b(this).val()){if(confirm(WP_Table_Reloaded_Admin.str_ChangeTableID)){g=b(this).val();c()}else{b(this).val(g)}}});b(".tr-import-addreplace input").click(function(){if("replace"==b(".tr-import-addreplace input:checked").val()){b(".tr-import-addreplace-table").show()}else{b(".tr-import-addreplace-table").hide()}});b(".tr-import-addreplace input:checked").click();b(".tr-import-from input").click(function(){b(".tr-import-file-upload").hide();b(".tr-import-url").hide();b(".tr-import-form-field").hide();b(".tr-import-server").hide();b(".tr-import-"+b(".tr-import-from input:checked").val()).show()});b(".tr-import-from input:checked").click();b("#options_use_custom_css").change(function(){if(b(this).attr("checked")){b("#options_custom_css").removeAttr("disabled")}else{b("#options_custom_css").attr("disabled",true)}});b("#options_enable_tablesorter").change(function(){if(b(this).attr("checked")){b("#options_use_tablesorter_extended").removeAttr("disabled")}else{b("#options_use_tablesorter_extended").attr("disabled",true)}});b("#table_options_first_row_th").change(function(){if(b(this).attr("checked")&&b("#tablesorter_enabled").val()){b("#table_options_use_tablesorter").removeAttr("disabled")}else{b("#table_options_use_tablesorter").attr("disabled",true)}});b("#options_uninstall_upon_deactivation").click(function(){if(b(this).attr("checked")){return confirm(WP_Table_Reloaded_Admin.str_UninstallCheckboxActivation)}});var h="";function e(){var k=b(this).val();b(this).val(k+h);b("#table_contents textarea").unbind("click",e)}b("#a-insert-link").click(function(){var l=prompt(WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertURL+":","http://");if(l){var k=prompt(WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText+":",WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertText);if(k){h='<a href="'+l+'">'+k+"</a>";if(confirm(WP_Table_Reloaded_Admin.str_DataManipulationLinkInsertExplain+"\n\n"+h)){b("#table_contents textarea").bind("click",e)}}}return false});function a(){edCanvas=this;b("#table_contents textarea").unbind("click",a);var k=b("#a-insert-image");tb_show(k.attr("title"),k.attr("href"),k.attr("rel"));b(this).blur()}function f(){b(this).unbind("click");b(this).bind("click",f);if(true==confirm(WP_Table_Reloaded_Admin.str_DataManipulationImageInsertThickbox)){b("#table_contents textarea").bind("click",a)}}b("#a-insert-image").unbind("click").bind("click",f);b("#insert_custom_field_name").keyup(function(){b(this).val(b(this).val().toLowerCase().replace(/[^a-z0-9_-]/g,""))});b(".focus-blur-change").focus(function(){if(b(this).attr("title")==b(this).val()){b(this).val("")}}).blur(function(){if(""==b(this).val()){b(this).val(b(this).attr("title"))}});b("#table_custom_fields textarea").focus(function(){b("#table_custom_fields .focus").removeClass("focus");b(this).addClass("focus")});b("input.bulk_copy_tables").click(function(){return confirm(WP_Table_Reloaded_Admin.str_BulkCopyTablesLink)});b("input.bulk_delete_tables").click(function(){return confirm(WP_Table_Reloaded_Admin.str_BulkDeleteTablesLink)});b("input.bulk_wp_table_import_tables").click(function(){return confirm(WP_Table_Reloaded_Admin.str_BulkImportwpTableTablesLink)});b("a.copy_table_link").click(function(){return confirm(WP_Table_Reloaded_Admin.str_CopyTableLink)});b(" a.delete_row_link").click(function(){return confirm(WP_Table_Reloaded_Admin.str_DeleteRowLink)});b("a.delete_column_link").click(function(){return confirm(WP_Table_Reloaded_Admin.str_DeleteColumnLink)});b("a.import_wptable_link").click(function(){return confirm(WP_Table_Reloaded_Admin.str_ImportwpTableLink)});b("a.uninstall_plugin_link").click(function(){if(confirm(WP_Table_Reloaded_Admin.str_UninstallPluginLink_1)){return confirm(WP_Table_Reloaded_Admin.str_UninstallPluginLink_2)}else{return false}});b("a.cf_shortcode_link").click(function(){var k=prompt(WP_Table_Reloaded_Admin.str_CFShortcodeMessage,b(this).attr("title"));return false});b("a.table_shortcode_link").click(function(){var k=prompt(WP_Table_Reloaded_Admin.str_TableShortcodeMessage,b(this).attr("title"));return false});b(".postbox h3, .postbox .handlediv").click(function(){b(b(this).parent().get(0)).toggleClass("closed")});var i=false;function c(){i=true;b("#wp_table_reloaded_edit_table").find("#table_id, #table_name, textarea").unbind("click",c)}b("#wp_table_reloaded_edit_table").find("#table_name, textarea").bind("change",c);if("true"==b("#wp_table_reloaded_edit_table #show_exit_warning").val()){window.onbeforeunload=function(){if(i){return WP_Table_Reloaded_Admin.str_saveAlert}};b("#wp_table_reloaded_edit_table input[name='submit[update]'], #wp_table_reloaded_edit_table input[name='submit[save_back]']").click(function(){window.onbeforeunload=null})}});