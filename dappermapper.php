<?php

/*
 * @package Cimbura
 * Plugin Name: DapperMapper for CartoDB
 * Author: Michael Moore
 * Author URI: http://cimbura.com
 * Description: Easily tie your CartoDB data to posts in WordPress. Access CartoDB properties dynamically via shortcode and show maps on post archive pages.
 * Version: 0.1
 */

require_once(__DIR__ . '/options.php');
require_once(__DIR__ . '/metabox.php');
require_once(__DIR__ . '/ajax.php');
require_once(__DIR__ . '/shortcodes.php');
require_once(__DIR__ . '/archive.php');

function dm_load_scripts() {
    wp_enqueue_style('leafletcss',plugin_dir_url(__FILE__) . '/leaflet/leaflet.css'); 
    wp_enqueue_style('dmcss',plugin_dir_url(__FILE__) . '/dm.css'); 
    wp_enqueue_style('cartodbcss','http://libs.cartocdn.com/cartodb.js/v3/3.15/themes/css/cartodb.css');
    wp_enqueue_style('jquery-ui-css',plugin_dir_url(__FILE__) . '/jqui/jquery-ui-1.11.4/jquery-ui.min.css',Array('jquery'));


    wp_enqueue_script('leafletjs',plugin_dir_url(__FILE__) . '/leaflet/leaflet.js');    
    wp_enqueue_script('dmjs',plugin_dir_url(__FILE__) . '/dm.js',Array('jquery')); 
    wp_enqueue_script('cartodbjs','http://libs.cartocdn.com/cartodb.js/v3/3.15/cartodb.js');
    wp_enqueue_script('jquery-ui-js',plugin_dir_url(__FILE__) . '/jqui/jquery-ui-1.11.4/jquery-ui.min.js',Array('jquery'));
}
add_action( 'wp_enqueue_scripts', 'dm_load_scripts' );
add_action( 'admin_enqueue_scripts', 'dm_load_scripts' );

function dm_plugin_add_settings_link( $links ) {
        $settings_link = '<a href="options-general.php?page=dappermapper">' . __( 'Settings' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
}
add_filter( "plugin_action_links_dappermapper/dappermapper.php",'dm_plugin_add_settings_link' );
