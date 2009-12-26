<?php if ( !defined( 'WP_TABLE_RELOADED_ABSPATH' ) ) exit; // no direct loading of this file ?>
        <div style="clear:both;">
        <p><?php _e( 'WP-Table Reloaded has several options which affect the plugin behavior in different areas.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
        <?php _e( 'Frontend Options influence the output and used features of tables in pages, posts or text-widgets.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php printf( __( 'The Backend Options control the plugin\'s admin area, e.g. the &quot;%s&quot; screen.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Edit Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?> <?php _e( 'Administrators have access to further Admin Options.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>

        <?php
        // only show certain settings, if user is administrator, as they are admin options
        $is_admin = current_user_can( 'manage_options' );

        // check if user can access Plugin Options
        if ( $this->user_has_access( 'plugin-options' ) ) { ?>

        <div style="clear:both;">
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'options' ) ); ?>

        <div class="postbox<?php echo $this->helper->postbox_closed( 'frontend-plugin-options', false ); ?>">
<h3 class="hndle"><span><?php _e( 'Frontend Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
<div class="inside">
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'JavaScript library', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[enable_tablesorter]" id="options_enable_tablesorter"<?php echo ( $this->options['enable_tablesorter'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_enable_tablesorter"><?php _e( 'Yes, enable the use of a JavaScript library.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'WP-Table Reloaded includes three JavaScript libraries that can add useful features, like sorting, pagination, and filtering, to a table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row">&nbsp;</th>
            <td><?php _e( 'Select the library to use:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <select id="options_tablesorter_script" name="options[tablesorter_script]"<?php echo ( !$this->options['enable_tablesorter'] ) ? ' disabled="disabled"': '' ; ?>>
                <option<?php echo ( 'datatables' == $this->options['tablesorter_script'] ) ? ' selected="selected"': ''; ?> value="datatables">DataTables (<?php _e( 'recommended', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</option>
                <option<?php echo ( 'datatables-tabletools' == $this->options['tablesorter_script'] ) ? ' selected="selected"': ''; ?> value="datatables-tabletools">DataTables+TableTools</option>
                <option<?php echo ( 'tablesorter' == $this->options['tablesorter_script'] ) ? ' selected="selected"': ''; ?> value="tablesorter">Tablesorter</option>
                <option<?php echo ( 'tablesorter_extended' == $this->options['tablesorter_script'] ) ? ' selected="selected"': ''; ?> value="tablesorter_extended">Tablesorter Extended</option>
        </select> <?php printf( __( '(You can read more about each library\'s features on the <a href="%s">plugin\'s website</a>.)', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/website/' ); ?></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Default CSS', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[use_default_css]" id="options_use_default_css"<?php echo ( $this->options['use_default_css'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_use_default_css">
            <?php _e( 'Yes, include and load the plugin\'s default CSS Stylesheets. This is highly recommended, if you use one of the JavaScript libraries!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
             </label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Custom CSS', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[use_custom_css]" id="options_use_custom_css"<?php echo ( $this->options['use_custom_css'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_use_custom_css">
            <?php _e( 'Yes, include and load the following custom CSS commands.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'This should be used to change the table layout and styling.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
             </label></td>
        </tr>
        <tr valign="top">
            <th scope="row">&nbsp;</th>
            <td><textarea name="options[custom_css]" id="options_custom_css" rows="10" cols="40"<?php echo ( !$this->options['use_custom_css'] ) ? ' disabled="disabled"': '' ; ?>><?php echo $this->helper->safe_output( $this->options['custom_css'] ); ?></textarea><br/><br/>
            <?php printf( __( 'You can get styling examples from the <a href="%s">plugin\'s website</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/website/' ); ?> <?php printf( __( 'Information on available CSS selectors can be found in the <a href="%s">documentation</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/documentation/' ); ?>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Links in new window', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[add_target_blank_to_links]" id="options_add_target_blank_to_links"<?php echo ( $this->options['add_target_blank_to_links'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_add_target_blank_to_links"><?php printf( __( 'Yes, open links that are inserted with the &quot;%s&quot; button on the &quot;%s&quot; screen in a new browser window <strong>from now on</strong>.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Insert Link', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Edit Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></label></td>
        </tr>
        </table>
        </div>
        </div>

        <div class="postbox<?php echo $this->helper->postbox_closed( 'backend-plugin-options', false ); ?>">
        <h3 class="hndle"><span><?php _e( 'Backend Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <table class="wp-table-reloaded-options">
        <tr valign="top">
            <th scope="row"><?php _e( 'Exit warning', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[show_exit_warning]" id="options_show_exit_warning"<?php echo ( $this->options['show_exit_warning'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_show_exit_warning"><?php printf( __( 'Yes, show a warning message, if I leave the &quot;%s&quot; screen and have not yet saved my changes.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Edit Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></label></td>
        </tr>
        <tr valign="top">
            <th scope="row"><?php _e( 'Growing textareas', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[growing_textareas]" id="options_growing_textareas"<?php echo ( $this->options['growing_textareas'] ) ? ' checked="checked"': '' ; ?> value="true" /> <label for="options_growing_textareas"><?php printf( __( 'Yes, enlarge the textareas on the &quot;%s&quot; screen when they are focussed.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Edit Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></label></td>
        </tr>

        </table>
        </div>
        </div>

        <div class="postbox<?php echo $this->helper->postbox_closed( 'admin-plugin-options', ( $is_admin) ? false : true ); ?>">
        <h3 class="hndle"><span><?php _e( 'Admin Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'This area are only available to site administrators!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><?php if ( !$is_admin ) echo ' ' . __( 'You can therefore not change these options.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <table class="wp-table-reloaded-options">

        <?php // the strings don't have a textdomain, because they shall be the same as in the original WP admin menu (and those strings are in WP's textdomain) ?>
        <tr valign="top">
            <th scope="row"><?php _e( 'Plugin Access', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><?php _e( 'To access WP-Table Reloaded, a user needs to be:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <select id="options_user_access_plugin" name="options[user_access_plugin]"<?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?>>
                <option<?php echo ( 'admin' == $this->options['user_access_plugin'] ) ? ' selected="selected"': ''; ?> value="admin"><?php echo _x( 'Administrator', 'User role' ); ?></option>
                <option<?php echo ( 'editor' == $this->options['user_access_plugin'] ) ? ' selected="selected"': ''; ?> value="editor"><?php echo _x( 'Editor', 'User role' ); ?></option>
                <option<?php echo ( 'author' == $this->options['user_access_plugin'] ) ? ' selected="selected"': ''; ?> value="author"><?php echo _x( 'Author', 'User role' ); ?></option>
                <option<?php echo ( 'contributor' == $this->options['user_access_plugin'] ) ? ' selected="selected"': ''; ?> value="contributor"><?php echo _x( 'Contributor', 'User role' ); ?></option>
        </select></td>
        </tr>

        <?php // the strings don't have a textdomain, because they shall be the same as in the original WP admin menu (and those strings are in WP's textdomain) ?>
        <tr valign="top">
            <th scope="row"><?php _e( 'Plugin Options Access', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><?php _e( 'To access the Plugin Options of WP-Table Reloaded, a user needs to be:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <select id="options_user_access_plugin_options" name="options[user_access_plugin_options]"<?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?>>
                <option<?php echo ( 'admin' == $this->options['user_access_plugin_options'] ) ? ' selected="selected"': ''; ?> value="admin"><?php echo _x( 'Administrator', 'User role' ); ?></option>
                <option<?php echo ( 'editor' == $this->options['user_access_plugin_options'] ) ? ' selected="selected"': ''; ?> value="editor"><?php echo _x( 'Editor', 'User role' ); ?></option>
                <option<?php echo ( 'author' == $this->options['user_access_plugin_options'] ) ? ' selected="selected"': ''; ?> value="author"><?php echo _x( 'Author', 'User role' ); ?></option>
        </select><br/><small>(<?php _e( 'Admin Options, Dump file Import, and Manual Plugin Uninstall are always accessible by Administrators only, regardless of this setting.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</small></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e( 'Plugin Language', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><?php _e( 'WP-Table Reloaded shall be shown in this language:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <select id="options_plugin_language" name="options[plugin_language]"<?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?>>
                <option<?php echo ( 'auto' == $this->options['plugin_language'] ) ? ' selected="selected"': ''; ?> value="auto"><?php printf( __( 'WordPress Default (currently %s)', WP_TABLE_RELOADED_TEXTDOMAIN ), get_locale() ); ?></option>
                <?php foreach ( $this->available_plugin_languages as $lang_abbr => $language ) { ?>
                <option<?php echo ( $lang_abbr == $this->options['plugin_language'] ) ? ' selected="selected"': ''; ?> value="<?php echo $lang_abbr; ?>"><?php echo "{$language} ({$lang_abbr})"; ?></option>
                <?php } ?>
        </select></td>
        </tr>

        <?php // the strings don't have a textdomain, because they shall be the same as in the original WP admin menu (and those strings are in WP's textdomain) ?>
        <tr valign="top">
            <th scope="row"><?php _e( 'Admin menu entry', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><?php _e( 'WP-Table Reloaded shall be shown in this section of the admin menu:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <select id="options_admin_menu_parent_page" name="options[admin_menu_parent_page]"<?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?>>
                <option<?php echo ( 'tools.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="tools.php"><?php _e( 'Tools' ); ?> (<?php _e( 'recommended', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</option>
                <option<?php echo ( 'edit.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="edit.php"><?php _e( 'Posts' ); ?></option>
                <option<?php echo ( 'edit-pages.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="edit-pages.php"><?php _e( 'Pages' ); ?></option>
                <option<?php echo ( 'plugins.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="plugins.php"><?php _e( 'Plugins' ); ?></option>
                <option<?php echo ( 'options-general.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="options-general.php"><?php _e( 'Settings' ); ?></option>
                <option<?php echo ( 'index.php' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="index.php"><?php _e( 'Dashboard' ); ?></option>
                <option<?php echo ( 'top-level' == $this->options['admin_menu_parent_page'] ) ? ' selected="selected"': ''; ?> value="top-level"><?php _e( 'Top-Level', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></option>
        </select></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e( 'Frontend Edit Link', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[frontend_edit_table_link]" id="options_frontend_edit_table_link"<?php echo ( $this->options['frontend_edit_table_link'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="options_frontend_edit_table_link"><?php _e( 'Yes, show an "Edit" link to users with sufficient rights near every table on the frontend.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e( 'WordPress Search', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[enable_search]" id="options_enable_search"<?php echo ( $this->options['enable_search'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="options_enable_search"><?php _e( 'Yes, the WordPress Search shall also find posts and pages that contain the search term inside a table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></label></td>
        </tr>

        <tr valign="top">
            <th scope="row"><?php _e( 'Remove upon Deactivation', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</th>
            <td><input type="checkbox" name="options[uninstall_upon_deactivation]" id="options_uninstall_upon_deactivation"<?php echo ( $this->options['uninstall_upon_deactivation'] ) ? ' checked="checked"': '' ; ?><?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?> value="true" /> <label for="options_uninstall_upon_deactivation"><?php _e( 'Yes, remove all plugin related data from the database when the plugin is deactivated.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <small>(<?php _e( 'Should be activated directly before deactivation only!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>)</small></label></td>
        </tr>

        </table>
        </div>
        </div>

        <input type="hidden" name="options[submit]" value="true" /><?php // need this, so that options get saved ?>
        <input type="hidden" name="action" value="options" />
        <p class="submit">
        <input type="submit" name="submit[form]" class="button-primary" value="<?php _e( 'Save Options', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        <?php
        $list_url = $this->get_action_url( array( 'action' => 'list' ) );
        echo " <a class=\"button-primary\" href=\"{$list_url}\">" . __( 'Cancel', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
        ?>
        </p>

        </form>
        </div>

        <h2><?php _e( 'WP-Table Reloaded Data Export and Backup', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></h2>

        <div style="clear:both;">
        <p style="margin-bottom:20px;"><?php _e( 'WP-Table Reloaded can export and import a so-called dump file that contains all tables, their settings and the plugin\'s options.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'This file can be used as a backup or to move all data to another WordPress site.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        <div class="postbox<?php echo $this->helper->postbox_closed( 'dump-file-export', true ); ?>">
        <h3 class="hndle"><span><?php _e( 'Export a dump file', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'To export all Tables and their settings, click the button below to generate and download a dump file.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( '<strong>Warning</strong>: Do <strong>not</strong> edit the content of that file under any circumstances as you will destroy the file!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'export_all' ) ); ?>
        <input type="submit" name="export_all" class="button-primary" value="<?php _e( 'Create and Download Dump File', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
        </form>
        </div>
        </div>

        <div class="postbox<?php echo $this->helper->postbox_closed( 'dump-file-import', true ); ?>">
        <h3 class="hndle"><span><?php _e( 'Import a dump file', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p><?php _e( 'To import a WP-Table Reloaded dump file and restore the included data, upload the file from your computer.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'All current data of this WP-Table Reloaded installation (Tables, Options, Settings) <strong>WILL BE OVERWRITTEN</strong> with the data from the file!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'Do not proceed, if you do not understand this!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'It is highly recommended to export and backup the data of this installation before importing another dump file (see above).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <?php
            if ( $is_admin ) {
            ?>
                <form method="post" enctype="multipart/form-data" action="<?php echo $this->get_action_url(); ?>">
                <?php wp_nonce_field( $this->get_nonce( 'import_dump' ) ); ?>
                <label for="dump_file"><?php _e( 'Select Dump File', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>:</label> <input name="dump_file" id="dump_file" type="file"<?php echo ( !$is_admin ) ? ' disabled="disabled"': '' ; ?> />
                <input type="hidden" name="action" value="import" />
                <input id="import_wp_table_reloaded_dump_file" type="submit" name="import_wp_table_reloaded_dump_file" class="button-primary" value="<?php _e( 'Import Dump File', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>" />
                </form>
            <?php
            } else {
                echo '<p>' . __( 'This area are only available to site administrators!', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</p>';
            }
        ?>
        </div>
        </div>

        <h2 style="margin-top:40px;"><?php _e( 'Manually Uninstall WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></h2>
        <div style="clear:both;">
            <p><?php _e( 'Uninstalling <strong>will permanently delete</strong> all tables, data, and options, that belong to WP-Table Reloaded from the database, including all tables you added or imported.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'You will manually need to remove the plugin\'s files from the plugin folder afterwards.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php _e( 'Be very careful with this and only click the button if you know what you are doing!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        <?php
            if ( $is_admin ) {
                $uninstall_url = $this->get_action_url( array( 'action' => 'uninstall' ), true );
                echo " <a class=\"button-secondary\" id=\"uninstall_plugin_link\" href=\"{$uninstall_url}\">" . __( 'Uninstall Plugin WP-Table Reloaded', WP_TABLE_RELOADED_TEXTDOMAIN ) . "</a>";
            } else {
                echo '<p>' . __( 'This area are only available to site administrators!', WP_TABLE_RELOADED_TEXTDOMAIN ) . '</p>';
            }
        ?>
        </div>
        <br style="clear:both;" />

        <?php // end check if user can access Plugin Options
        } else { ?>
        <div style="clear:both;">
        <p><strong><?php _e( 'You do not have sufficient rights to access the Plugin Options.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></strong></p>
        </div>
        <?php // end alternate text, if user can not access Plugin Options
        }
        ?>