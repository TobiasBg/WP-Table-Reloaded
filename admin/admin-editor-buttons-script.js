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
        $("#wp_table_reloaded_tables_window").click(function () {
            $("#TB_ajaxContent").width("100%").height("100%");
            $('#TB_ajaxWindowTitle').text('WP-Table Reloaded');
            return false;
        });
        
        $("#wp_table_reloaded_tables_window").click();
        return false;
    }

});