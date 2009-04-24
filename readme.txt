=== WP-Table Reloaded ===
Contributors: TobiasBg 
Donate link: http://tobias.baethge.com/donate/
Tags: html,table,editor,csv,import,export,excel,widget,admin,sidebar
Requires at least: 2.5
Tested up to: 2.8
Stable tag: 1.0.1
	
This plugin allows you to create and manage tables in your WP's admin-area. You can then show them in your posts, on pages or in text widgets by using a shortcode.

== Description ==

This plugin allows you to create and manage tables in the admin-area of WordPress. You can then show them in your posts, on your pages or in text widgets by using a shortcode. The plugin is a completely rewritten and extended version of Alex Rabe's "wp-Table" and uses the state-of-the-art WordPress techniques which makes it faster and lighter than the original plugin. You may also have both plugins installed at the same time and you can also import your tables from the wp-Table plugin!

= More information =
Please see the English plugin website http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/ .

If you like this plugin, please rate it here in the WordPress Plugin Directory. Thank you!

= Informationen auf Deutsch =
Dieses Plugin erlaubt die Verwaltung von Tabellen in WordPress.

Weitere Informationen auf Deutsch: http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-deutsch/

Wenn die das Plugin gef&auml;llt, bewerte es bitte hier im WordPress Plugin Directory. Danke!

== Screenshots ==

1. "List tables" screen
2. "Add table" screen
3. "Edit table" screen
4. "Import table" screen
5. "Export table" screen
6. "Plugin Options" screen

== Installation ==

The easiest way would be through your WordPress Admin area. Go to the plugin section and search for "WP-Table Reloaded" in the WordPress Plugin Directory. Then you can click "Install now" and the following steps will be done for you automatically. You just have to activate the plugin (step 5).

Manual Installation works just as for most other WordPress plugins:

1. Extract the zip file and just drop the folder "wp-table-reloaded" into the wp-content/plugins/ directory of your WordPress installation.

1. Activate the Plugin "WP-Table Reloaded" on your "Plugins" page.

1. Create and manage tables via "WP-Table Reloaded" in the "Tools" section.

1. Include a table by adding the shortcode [table id=&lt;your-table's-id&gt; /] to your post, page or text widget.

1. You might want to add styling features via your blog's theme's CSS file (probably style.css) or via plugin css file. There are two demo file "example-style.css" and "example-style-wp-table.css" in the subfolder "css". Please copy them, edit the copy, and select the copy in the plugin options screen!


== Frequently Asked Questions ==

= Can I use wp-Table and WP-Table Reloaded together? =

Yes! You can have both wp-Table and WP-Table Reloaded installed in your WordPress! They will not interfere (as they are not using anything together). They are completely independent from each other.
If WP-Table Reloaded finds the wp-Table database tables, it can import the found tables into it's own format, so that you can completely upgrade from wp-Table to WP-Table Reloaded.

= Support? =

If you experience trouble in using the plugin or find a bug, please don't hesitate to tell me about it. Right now the preferred way is the comments section of the [plugin page](http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded/) (that way other users with the same problem could get help, too). I might add a support forum later. Till then you might also consider opening a new topic in the [WordPress Support Forums](http://wordpress.org/support/) and send me the link to your thread. You may also make feature requests using this method! Don't be shy!

= Requirements? =

In short: WordPress 2.5 or higher

= Languages and Localization? =

The plugin currently includes the following languages:
Albanian, English, French, German, Russian, Spanish, Swedish and Turkish.

I'd really appreciate it, if you would translate the plugin into your language! Using Heiko Rabe's WordPress plugin [Codestyling Localization](http://www.code-styling.de/english/development/wordpress-plugin-codestyling-localization-en) that really is as easy as pie. Just install the plugin, add your language, create the .po-file, translate the strings in the comfortable editor and create the .mo-file. It will automatically be saved in WP-Table Reloaded's plugin folder. If you send me the .mo- and .po-file, I will gladly include them into future plugin releases.
There is also a .pot-file available to use in the "languages subfolder.


= Where can I get more information? =

Please visit the [official plugin website](http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-english/) for the latest information on this plugin.

Die Webseite des Plugins ist auch [auf Deutsch](http://tobias.baethge.com/wordpress-plugins/wp-table-reloaded-deutsch/) verf&uuml;gbar.

== Usage ==

After installing the plugin, you can add, import, export, edit, copy, delete, ... tables via the "WP-Table Reloaded" screen which appears under the "Tools" section in your Admin menu.
Everything should be self-explaining there.
To show one of your tables in a post, on a page or in a text widget, just include the shortcode [table id=&lt;the-id&gt; /] to your post/page/text-widget, where &lt;the-id&gt is the ID of your table (can be found on the left side of the "List tables" screen.)
After that you might want to change the style of the table: Just copy the CSS-file "example-style.css" in the subfolder "css" of the plugin folder, edit the copy to fit your layout needs. Be sure to activate the option "Use global css-file?" in the plugin options and select your copied file for this to work!
You may also add certain features (like table-sorting, alternating row colors, print name and/or description, ...) by checking the appropriate options in the "Edit table" screen.

== Acknowledgements ==

Thanks go to [Alex Rabe](http://alexrabe.boelinger.com/) for the initial wp-Table plugin!
Thanks go to [Christian Bach](http://tablesorter.com/docs/) for the TableSorter-jQuery-plugin.
Thanks to all language file translators!
Thanks to every supporter and bug reporter!

== License ==

This plugin is Free Software, released under the GPL version 2.
You may use it free of charge for any purposes.
I kindly ask you for link somewhere on your website http://tobias.baethge.com/. This is not required!

== Changelog ==

* 1.0: Language files, more import/export (including directly from wp-Table!), shortcode supported in text widgets
* 0.9.2: fixed bug with plugin deactivation hook, added missing css-file
* 0.9.1: first good release with all mentioned functions working well
* 0.9 beta 1b: small bug which prevented showing of tables (but still not everything implemented)
* 0.9 beta 1: First release (not everything functional)