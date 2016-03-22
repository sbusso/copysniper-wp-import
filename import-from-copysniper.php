<?php
/*
Plugin Name: Import from Copysniper
Plugin URI: http://copysniper.com/
Description: Imports Copysniper files into WordPress pages. See the <a href="http://copysniper.com">User Guide</a> for details or <a href="index.php">visit the Dashboard</a> to get started.
Version: 1.0
Author: Tom Frearson
Author URI: http://linkedin.com/in/tomfrearson/
License: GPL 2
*/

require_once ('copysniper-importer.php');
require_once ('import-from-copysniper-options.php');

// plugin_activation_check() by Otto
function import_from_copysniper_activation_check() {
	if (version_compare(PHP_VERSION, '5.0.0', '<')) {
		deactivate_plugins(basename(__FILE__)); // Deactivate myself
		wp_die("Sorry, you can't run this plugin. It requires PHP 5 or higher.");
	}
}
register_activation_hook(__FILE__, 'import_from_copysniper_activation_check');

// i18n
//$plugin_dir = basename(dirname(__FILE__)) . '/languages';
//load_plugin_textdomain( 'import_from_copysniper', 'wp-content/plugins/' . $plugin_dir, $plugin_dir );

// set default options
function import_from_copysniper_set_defaults() {
	$options = import_from_copysniper_get_options();
	add_option( 'import_from_copysniper', $options, '', 'no' );
}
register_activation_hook(__FILE__, 'import_from_copysniper_set_defaults');

// register our settings
function register_import_from_copysniper_settings() {
	register_setting( 'import_from_copysniper', 'import_from_copysniper' );
}

// when uninstalled, remove option
function import_from_copysniper_remove_options() {
	delete_option('import_from_copysniper');
}
register_uninstall_hook( __FILE__, 'import_from_copysniper_remove_options' );
// for testing only!
//register_deactivation_hook( __FILE__, 'import_from_copysniper_remove_options' );

// force page template
function catch_plugin_template( $template ) {
	//global $post;
	//$theme = get_post_meta( $post->ID, 'theme', true );
    if( ( is_page() ) /*&& ( $theme == 'none' )*/ ) {
        $template = WP_PLUGIN_DIR . '/import-from-copysniper/notheme/page.php';
    return $template;
	}
}
add_filter('page_template', 'catch_plugin_template');

// add dashboard widget
function import_from_copysniper_add_dashboard_widgets() {
	if( current_user_can( 'import' ) ) {
		wp_add_dashboard_widget(
			'import_from_copysniper_dashboard_widget',
			'Import from Copysniper',
			'import_from_copysniper_dashboard_widget'
		);
	}
}
add_action( 'wp_dashboard_setup', 'import_from_copysniper_add_dashboard_widgets' );

// output content to dashboard widget
function import_from_copysniper_dashboard_widget() {
	echo '<a href="admin.php?import=copysniper" class="button" style="margin-top: 5px;">Import</a>';
	$text .= '<div style="margin-top: 15px; border-top: solid 1px #ececec;">';
	$text .= '<ul>';
	$text .= '<li><h4>Help and Support</h4></li>';
	$text .= '<li><a href="http://copysniper.com/">' . __( 'User Guide', 'import-from-copysniper' ) . '</a></li>';
	$text .= '<li><a href="http://copysniper.com/">' . __( 'Plugin Home Page', 'import-from-copysniper' ) . '</a></li>';
	$text .= '</ul>';
	$text .= '</div>';
	echo $text;
}

?>