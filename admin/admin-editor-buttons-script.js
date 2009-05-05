jQuery(document).ready(function($){

    var editor_toolbar = $("#ed_toolbar");

    if ( editor_toolbar ) {
        var theButton = document.createElement('input');
            theButton.type = 'button';
            theButton.value = WP_Table_Reloaded_Admin.str_EditorButtonCaption;
            theButton.className = 'ed_button';
            theButton.title = WP_Table_Reloaded_Admin.str_EditorButtonCaption;
            theButton.id = 'ed_button_wp_table_reloaded';
            editor_toolbar.append( theButton );
            $("#ed_button_wp_table_reloaded").click( wp_table_reloaded_button_click );
    }

    function wp_table_reloaded_button_click() {

        var title = 'WP-Table Reloaded';
        var url = WP_Table_Reloaded_Admin.str_EditorButtonAjaxURL.replace(/&amp;/g, "&");

        tb_show( title, url, false);
        
        $("#TB_ajaxContent").width("100%").height("100%");

        return false;
    }

});