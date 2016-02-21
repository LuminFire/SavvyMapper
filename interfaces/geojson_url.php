<?php
/*
 Copyright (C) 2016 Cimbura.com

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.


 */


/**
 * An interface for loading GEOJson from a URL
 *
 * Won't work for large files, obviously
 */

add_filter( 'savvymapper_load_interfaces','load_savvy_geojson_url_interface' );
function load_savvy_geojson_url_interface( $interfaces ) {
	class SavvyGeoJsonURL extends SavvyInterface {
		/**
		 * @var Override default name
		 */
		var $name = 'GeoJsonURL';

		/**
		 * implements required method
		 */
		function connection_setup_actions() {
			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		}

		/**
		 * implements required method
		 */
		function autocomplete( $mapping, $term = null ) {
			// JSON Url, so we need to fetch the whole thing
			$json = $this->get_geojson_file();
			$candidates = array();
			foreach ( $json[ 'features' ] as $feature ){
				// stripos -- case IN-sensitive compare!
				$lookupval = $feature[ 'properties' ][ $mapping[ 'lookup_field' ] ];
				if ( stripos( $lookupval, $term ) === 0 ) {
					$candidates[] = $lookupval;
				}
			}
			$candidates = array_unique( $candidates );
			natcasesort( $candidates );
			$candidates = array_slice( $candidates, 0, 15 );
			return $candidates;
		}

		/**
		 * implements required method
		 */
		function options_div() {
			$connection_details = array_merge( array(
				'geojson_url' => ''
			), $this->config );

			$html = $this->form_make_text( 'GeoJSON URL','geojson_url',$connection_details['geojson_url'] );
			return $html;
		}

		/**
		 * implements required method
		 */
		function mapping_div( $mapping ) {
			$defaults = array( 
				'lookup_field' => ''
			);

			$mapping = array_merge( $defaults, $mapping );

			$field_names = $this->get_attribute_names( $mapping );

			$html = $this->form_make_select( 'Lookup Field', 'lookup_field', $field_names, $field_names, $mapping[ 'lookup_field' ] ) . '<br>' . "\n";

			return $html;
		}

		/**
		 * implements required method
		 */
		function get_attribute_names( $mapping ) {
			$json = $this->get_geojson_file();

			if( !empty( $json[ 'features' ] ) ){
				return array_keys( $json[ 'features' ][ 0 ][ 'properties' ] );
			}
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
		function get_geojson_for_post( $mapping, $current_settings ) {
			$json = $this->get_geojson_file();

			$allFeatures = $json[ 'features' ];
			$json[ 'features' ] = array();


			foreach ( $allFeatures as $feature ) {
				$lookupval = $feature[ 'properties' ][ $mapping[ 'lookup_field' ] ];
				if ( stripos( $lookupval, $current_settings[ 'lookup_value' ] ) === 0 ) {
					$json[ 'features' ][] = $feature;
				}
			}

			return $json;
		}

		/**
		 * implements required method
		 */
		function setup_actions() {
			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		}

		/* --------- GeoJSON URL specific implementation functions ------------- */

		function load_scripts() {
			$plugin_dir_url = plugin_dir_url( __FILE__ );
			wp_enqueue_script( 'savvygeojsonurljs',$plugin_dir_url . 'geojson_url.js',array( 'jquery','savvymapjs') );
		}

		function get_geojson_file() {
			$json_str = $this->remote_get( $this->config[ 'geojson_url' ] );
			$json = json_decode( $json_str, true );

			return $json;
		}
	}

	$int = new SavvyGeoJsonURL();
	$interfaces[ $int->get_type() ] = $int;

	return $interfaces;
}
