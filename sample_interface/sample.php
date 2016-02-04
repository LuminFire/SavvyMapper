<?php

add_filter( 'savvy_load_interfaces','load_savvy_SAMPLE_interface' );
function load_savvy_SAMPLE_interface( $interfaces ) {
	class SavvySAMPLE extends SavvyInterface {
		/**
		 * @var Override default name
		 */
		var $name = 'SAMPLE';

		/**
		 * implements required method
		 */
		function connection_setup_actions() {
		}

		/**
		 * implements required method
		 */
		function autocomplete( $mapping, $term = null ) {
			return array();
		}

		/**
		 * implements required method
		 */
		function options_div() {
			return '';
		}

		/**
		 * implements required method
		 */
		function mapping_div( $mapping ) {
			return '';
		}

		/**
		 * implements required method
		 */
		function get_attribute_names( $mapping ) {
			return array();
		}

		/**
		 * implements required method
		 */
		function extra_metabox_fields( $post, $mapping, $current_settings = array() ) {
			return '';
		}

		/**
		 * implements required method
		 */
		function save_meta( $post_id ) {
			return array();
		}

		/**
		 * implements required method
		 */
		function get_map_shortcode_properties( $attrs, $contents, $mapping, $curent_settings ) {
			return array();
		}

		/**
		 * implements required method
		 */
		function get_attribute_shortcode_geojson( $attrs, $contents, $mapping, $current_settings ) {
			$features = array(
				'type' => 'FeatureCollection',
				'features' => array()
				);
			return $features;
		}

		/**
		 * implements required method
		 */
		function get_geojson_for_post( $mapping, $current_settings ) {
			$features = array(
				'type' => 'FeatureCollection',
				'features' => array()
				);
			return $features;
		}
	}

	$int = new SavvyGeoJsonURL();
	$interfaces[ $int->get_type() ] = $int;

	return $interfaces;
}