<?php

add_filter('savvy_load_interfaces','load_savvy_wfs_interface');
function load_savvy_wfs_interface( $interfaces ) {
	class SavvyWFS extends SavvyInterface {
		var $name = 'WFS';

		function get_post_json() { return ''; }
		function get_archive_json() { return ''; }
		function autocomplete() { return ''; }
		function make_meta_box( $post, $metabox ) { return ''; } 
		function make_archive_map() { return '';} 
		function options_div() { return '<input value="WFS Doesn\'t actually work yet">' . "\n"; }

		function settings_init() {
			return Array();
		}

		function mapping_div( $mapping ) {
			return "<div>No mapping info yet</div>";
		}

	}

	$interfaces['wfs'] = new SavvyWFS();

	return $interfaces;
}
