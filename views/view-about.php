<?php if ( !defined( 'WP_TABLE_RELOADED_ABSPATH' ) ) exit; // no direct loading of this file ?>
        <div style="clear:both;">

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Plugin Purpose', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php _e( 'WP-Table Reloaded allows you to create and manage tables in the admin-area of WordPress.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'Those tables may contain strings, numbers and even HTML (e.g. to include images or links).', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'You can then show the tables in your posts, on your pages or in text-widgets by using a shortcode.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'If you want to show your tables anywhere else in your theme, you can use a template tag function.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Usage', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php _e( 'At first you should add or import a table.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'This means that you either let the plugin create an empty table for you or that you load an existing table from either a CSV, XML or HTML file.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p><p><?php _e( 'Then you can edit your data or change the structure of your table (e.g. by inserting or deleting rows or columns, swaping rows or columns or sorting them) and select specific table options like alternating row colors or whether to print the name or description, if you want.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'To easily add a link or an image to a cell, use the provided buttons. Those will ask you for the URL and a title. Then you can click into a cell and the corresponding HTML will be added to it for you.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p><p><?php printf( __( 'To insert the table into a page, post or text-widget, copy the shortcode <strong>[table id=%s /]</strong> and paste it into the corresponding place in the editor.', WP_TABLE_RELOADED_TEXTDOMAIN ), '&lt;ID&gt;' ); ?> <?php printf( __( 'You can also select the desired table from a list (after clicking the button &quot;%s&quot; in the editor toolbar) and the corresponding shortcode will be added for you.', WP_TABLE_RELOADED_TEXTDOMAIN ), __( 'Table', WP_TABLE_RELOADED_TEXTDOMAIN ) ); ?></p><p><?php _e( 'Tables can be styled by changing and adding CSS commands.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'The plugin ships with default CSS Stylesheets, which can be customized with own code or replaced with other Stylesheets.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php _e( 'For this, each table is given certain CSS classes that can be used as CSS selectors.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <?php printf ( __( 'Please see the <a href="%s">documentation</a> for a list of these selectors and for styling examples.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/documentation/' ); ?>
        </p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'More Information and Documentation', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php printf( __( 'More information about WP-Table Reloaded can be found on the <a href="%s">plugin\'s website</a> or on its page in the <a href="%s">WordPress Plugin Directory</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/website/', 'http://wordpress.org/extend/plugins/wp-table-reloaded/' ); ?> <?php printf( __( 'For technical information, see the <a href="%s">documentation</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/documentation/' ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Help and Support', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php printf( __( '<a href="%s">Support</a> is provided through the <a href="%s">WordPress Support Forums</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/support/', 'http://www.wordpress.org/support/' ); ?> <?php printf( __( 'Before asking for support, please carefully read the <a href="%s">Frequently Asked Questions</a> where you will find answered to the most common questions, and search through the forums.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/faq/' ); ?></p><p><?php printf( __( 'If you do not find an answer there, please <a href="%s">open a new thread</a> in the WordPress Support Forums with the tag &quot;wp-table-reloaded&quot;.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://wordpress.org/tags/wp-table-reloaded' ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Author and License', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p><?php printf( __( 'This plugin was written by <a href="%s">Tobias B&auml;thge</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/' ); ?> <?php _e( 'It is licensed as Free Software under GPL 2.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/><?php printf( __( 'If you like the plugin, <a href="%s"><strong>a donation</strong></a> is recommended.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://tobias.baethge.com/go/wp-table-reloaded/donate/' ); ?> <?php printf( __( 'Please rate the plugin in the <a href="%s">WordPress Plugin Directory</a>.', WP_TABLE_RELOADED_TEXTDOMAIN ), 'http://wordpress.org/extend/plugins/wp-table-reloaded/' ); ?><br/><?php _e( 'Donations and good ratings encourage me to further develop the plugin and to provide countless hours of support. Any amount is appreciated! Thanks!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></p>
        </div>
        </div>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Credits and Thanks', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <p>
            <?php _e( 'Thanks go to <a href="http://alexrabe.boelinger.com/">Alex Rabe</a> for the original wp-Table plugin,', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'Allan Jardine for the <a href="http://www.datatables.net/">DataTables jQuery plugin</a>,', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'Christian Bach for the <a href="http://www.tablesorter.com/">Tablesorter jQuery plugin</a>,', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'Soeren Krings for its extension <a href="http://tablesorter.openwerk.de/">Tablesorter Extended</a>,', WP_TABLE_RELOADED_TEXTDOMAIN ); ?><br/>
            <?php _e( 'the submitters of translations:', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
            <?php
                // list of languages and their credits line, $_languages are inactive languages, that are not up-to-date but shall be kept to remain translatable
                $_languages['sq_AL']     = __( 'Albanian', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['sq_AL'] = '<a href="http://www.romeolab.com/">Romeo</a>';
                $_languages['by_BY']     = __( 'Belorussian', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['by_BY'] = '<a href="http://www.fatcow.com/">Marcis Gasuns</a>';
                $languages['pt_BR']     = __( 'Brazilian Portuguese', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['pt_BR'] = '<a href="http://www.pensarics.com/">Rics</a>';
                $languages['cs_CZ']     = __( 'Czech', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['cs_CZ'] = '<a href="http://separatista.net/">Separatista</a>';
                $_languages['fi_FI']     = __( 'Finnish', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['fi_FI'] = 'Jaakko';
                $_languages['fr_FR']     = __( 'French', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['fr_FR'] = '<a href="http://ultratrailer.net/">Yin-Yin</a>';
                $languages['hi_IN']     = __( 'Hindi', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['hi_IN'] = '<a href="http://outshinesolutions.com/">Outshine Solutions</a>';
                $_languages['it_IT']     = __( 'Italian', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['it_IT'] = '<a href="http://www.scrical.it/">Gabriella Mazzon</a>';
                $languages['ja']        = __( 'Japanese', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['ja']    = '<a href="http://www.u-1.net/">Yuuichi</a>';
                $_languages['pl_PL']     = __( 'Polish', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['pl_PL'] = 'Alex Kortan';
                $_languages['ru_RU']     = __( 'Russian', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['ru_RU'] = '<a href="http://wp-skins.info/">Truper</a>';
                $languages['sk_SK']     = __( 'Slovak', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['sk_SK'] = '<a href="http://lukas.cerro.sk/">55.lukas</a>';
                $_languages['es_ES']     = __( 'Spanish', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['es_ES'] = '<a href="http://theindependentproject.com/">Alejandro Urrutia</a> and <a href="http://halles.cl/">Matias Halles</a>';
                $languages['sv_SE']     = __( 'Swedish', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['sv_SE'] = '<a href="http://www.zuperzed.se/">ZuperZed</a>';
                $_languages['tr_TR']     = __( 'Turkish', WP_TABLE_RELOADED_TEXTDOMAIN );
                $credits_links['tr_TR'] = '<a href="http://www.wpuzmani.com/">Semih</a>';
                asort( $languages );
                
                foreach ( $languages as $code => $language ) {
                    echo "<br/>&middot; " . sprintf( __( '%s (thanks to %s)', WP_TABLE_RELOADED_TEXTDOMAIN ), $language, $credits_links[ $code ] ) . "\n";
                }
            ?>
            <br/><?php _e( 'and to all donors, contributors, supporters, reviewers and users of the plugin!', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>
        </p>
        </div>
        </div>

        <div class="postbox<?php echo $this->helper->postbox_closed( 'debug-version-information', true ); ?>">
        <h3 class="hndle"><span><?php _e( 'Debug and Version Information', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></span><span class="hide_link"><small><?php echo _x( 'Hide', 'expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span><span class="expand_link"><small><?php _e( 'Expand', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></small></span></h3>
        <div class="inside">
        <p>
            <?php _e( 'You are using the following versions of the software.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?> <strong><?php _e( 'Please provide this information in bug reports.', WP_TABLE_RELOADED_TEXTDOMAIN ); ?></strong><br/>
            <br/>&middot; WP-Table Reloaded (DB): <?php echo $this->options['installed_version']; ?>
            <br/>&middot; WP-Table Reloaded (Script): <?php echo WP_TABLE_RELOADED_PLUGIN_VERSION; ?>
            <br/>&middot; <?php _e( 'Plugin installed', WP_TABLE_RELOADED_TEXTDOMAIN ); ?>: <?php echo date( 'Y/m/d H:i:s', $this->options['install_time'] ); ?>
            <br/>&middot; WordPress: <?php echo $GLOBALS['wp_version']; ?>
            <br/>&middot; PHP: <?php echo phpversion(); ?>
            <br/>&middot; mySQL (Server): <?php echo mysql_get_server_info(); ?>
            <br/>&middot; mySQL (Client): <?php echo mysql_get_client_info(); ?>
        </p>
        </div>
        </div>

        </div>