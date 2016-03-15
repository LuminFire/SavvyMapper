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
 * @class The list of functions that a class must implement
 * to be used as an interface
 */
abstract class SavvyInterface {
	/**
	 * @var The SavvyMapper instance.
	 */
	var $savvy;

	/**
	 * @var This interface's name.
	 */
	var $name = 'Default Savvy Interface';

	/**
	 * @var This instance's config
	 */
	var $config;

	/**
	 * @var A place for the instance to cache things
	 *
	 * Instances shouldn't use this directly but should use
	 *
	 * get_cache($key) and set_cache($key,$value) instead
	 */
	var $cache;

	/**
	 * Init self and load self into SavvyMapper
	 *
	 * @param array $config
	 */
	function __construct( $config = array() ) {
		$this->savvy = SavvyMapper::get_instance();
		$this->setup_actions();
		$this->set_config( $config );
	}

	/**
	 * Ajax autocomplete. Return the JSON features that match the query
	 *
	 * Matching should ideally be done as a case-insensitive match, matching the first part of the string
	 *
	 * @param mappingconfig $mapping The current mapping for the connection.
	 * @param string        $term The term we're autocompleting with
	 *
	 * @note This should really only return some practical number of items. Probably 15ish, and they should be sorted alphabetically
	 *
	 * @return An array of matched values
	 */
	abstract function autocomplete( $mapping, $term );

	/**
	 * Make the metabox for this interface
	 *
	 * @param WP_Post $post The post this metabox is for.
	 * @param Array   $mapping The current connection mapping.
	 * @param array   $current_settings The current settings for the given post.
	 *
	 *   Prints the metabox html
	 */
	abstract function extra_metabox_fields( $post, $mapping, $current_settings = array() );

	/**
	 * Get the part of the form for the connection interface
	 *
	 * @return Any HTML form elements needed to set up a new connection on the
	 * options page, as a string, or an empty string if no config is needed.
	 *
	 * Input elements should have a data-name property with the name of the field
	 *
	 * For existing connections, input elements should populate the inputs with the values in $this->config
	 */
	abstract function options_div();

	/**
	 * Get the part of the form for setting up the mapping
	 *
	 * @param array $mapping The current mapping config
	 *
	 * Create the html needed to set up a new mapping in the current connection
	 *
	 * Input elements should have a data-name property with the name of the field
	 *
	 * For existing mappings, input elements should populate the inputs with the values in the passed-in $mapping parameter
	 */
	abstract function mapping_div( $mapping );

	/**
	 * Get a list of all the available attributes
	 *
	 * @param array $mapping The current mapping config
	 *
	 * @return An array of attribute/property names
	 */
	abstract function get_attribute_names( $mapping );

	/**
	 * App postmeta is actually stored in savvymapper_post_meta, but SavvyMapper
	 * only knows about the default mapping options.
	 *
	 * This save_meta function looks for interface-specific values in $_POST
	 * and returns an array with any additional keys that SavvyMapper should
	 * merge in for this post's meta.
	 *
	 * @param string/int $post_id The post that meta is being saved for.
	 * @param integer    $index The mapping instance index
	 */
	abstract function save_meta( $post_id, $index );

	/**
	 * Map initialization is all done in JavaScript.
	 *
	 * This function allows an interface to supply additional settings which the JavaScript can access.
	 *
	 * @param array  $attrs The shortcode $attrs.
	 * @param string $contents The contents between the shortcode tags.
	 * @param array  $mapping The current mapping config.
	 * @param array  $current_settings The settings for this specific post.
	 *
	 * @note Values returned here should be handled in the js file corresponding to the interface.
	 *
	 * @return An Array of additional properties for the map to be aware of.
	 */
	abstract function get_map_shortcode_properties( $attrs, $contents, $mapping, $current_settings );

	/**
	 * Get a GeoJSON object for the current post
	 *
	 * @param array $mapping The current mapping config.
	 * @param array $current_settings The settings for this specific post.
	 *
	 * @return A GeoJSON-compatible array.
	 */
	abstract function get_geojson_for_post( $mapping, $current_settings );

	/**
	 * This is the actual class called when we need json for a post
	 * It calls the interface's get_geojson_for_post and then calls
	 * make_popups with that geojson
	 *
	 * @param array $mapping The current mapping config.
	 * @param array $current_settings The settings for this specific post.
	 *
	 * @return A GeoJSON-compatible array.
	 */
	function _get_geojson_for_post( $mapping, $current_settings ) {
		$json = $this->get_geojson_for_post( $mapping, $current_settings );

		if ( ! isset( $json['features'] ) ) {
			$json['features'] = array();
		}

		if ( ! isset( $json['type'] ) ) {
			$json['type'] = 'FeatureCollection';
		}

		$show_popups = ( isset( $current_settings['show_popups'] ) ?  $current_settings['show_popups'] : $mapping['show_popups'] );

		if ( $show_popups ) {
			$json = $this->make_popups( $mapping, $json );
		}

		return $json;
	}

	/**
	 * Setup the actions to get things started
	 *
	 * Enqueue js/css needed for this interface
	 *
	 * This is run for all instances of the interface, even empty ones
	 */
	abstract function setup_actions();


	/**
	 * Fetch the GeoJSON feature(s) for the current post so we can print their attributes
	 * for the attribute shortcode.
	 *
	 * Returned features should have at least the property specified in $attrs['attr'].
	 *
	 * @note This is separate from get_geojson_for_post because some optimizations
	 * can be made by selecting only the columsn needed in this call, while
	 * get_geojson_for_post probably needs to return all columns For now we're just
	 * going to fetch the geojson the same way for both calls. We'll see if it's an
	 * issue later
	 *
	 * @param array  $attrs The shortcode $attrs.
	 * @param string $contents The contents between the shortcode tags.
	 * @param array  $mapping The current mapping config.
	 * @param array  $current_settings The settings for this specific post.
	 *
	 * @return GeoJSON with each feature having the property specified $attrs['attr']
	 */
	function get_attribute_shortcode_geojson( $attrs, $contents, $mapping, $current_settings ) {
		return $this->get_geojson_for_post( $mapping, $current_settings );
	}




	/**
	 * Setup actions for a specific connection
	 *
	 * This is run only when we have a connection configured
	 */
	function connection_setup_actions() { }

	/**
	 * @param array $config This instance's config
	 */
	function set_config( $config = array() ) {
		$this->config = $config;

		if ( ! empty( $config ) ) {
			$this->connection_setup_actions();
		}
	}

	/**
	 * Wrapper for wp_remote_post with caching
	 *
	 * @param string $url The url to post to.
	 * @param array  $args The args for the post.
	 * @param bool   $cache Should cache be enabled for this request.
	 *
	 *   return The post body.
	 */
	function remote_post( $url, $args = array(), $cache = true ) {
		if ( $cache ) {
			$queryString = http_build_query( $args );
			$cache_string = $url . $queryString;
			$cache_hash = sha1( $cache_string );

			$cached = $this->savvy->get_from_cache( $cache_hash );
			if ( $cached ) {
				return $cached;
			}
		}

		$result = wp_remote_post( $url, $args );

		if ( $cache && strpos( $result['response']['code'], '2' ) === 0 ) {
			$this->savvy->write_to_cache( $cache_hash, $result['body'] );
		}

		return $result['body'];
	}

	/**
	 * Wrapper for wp_remote_post with caching
	 *
	 * @param string $url The url to post to.
	 * @param array  $args The args for the post.
	 * @param bool   $cache Should cache be enabled for this request.
	 *
	 *   return The post body.
	 */
	function remote_get( $url, $args = array(), $cache = true ) {
		if ( $cache ) {
			$queryString = http_build_query( $args );
			$cache_string = $url . $queryString;
			$cache_hash = sha1( $cache_string );

			$cached = $this->savvy->get_from_cache( $cache_hash );
			if ( $cached ) {
				return $cached;
			}
		}

		$result = wp_remote_get( $url, $args );

		if ( $cache && strpos( $result['response']['code'], '2' ) === 0 ) {
			$this->savvy->write_to_cache( $cache_hash, $result['body'] );
		}

		return $result['body'];
	}

	/**
	 * Get the ID for this instance
	 *
	 * @return The ID, or the curren time if this interface isn't set up yet.
	 */
	function get_id() {
		if ( ! empty( $this->config['_id'] ) ) {
			return $this->config['_id'];
		}

		return time();
	}

	/**
	 * Get the connection_name for this instance
	 *
	 * @return The connection name, or empty string if this interface isn't set up yet.
	 */
	function get_connection_name() {
		if ( ! empty( $this->config['connection_name'] ) ) {
			return $this->config['connection_name'];
		}
		return '';
	}

	/**
	 * Get something from cache
	 */
	function get_cache( $cache_key ) {
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}
		return false;
	}

	/**
	 * Set the cache
	 */
	function set_cache( $cache_key, $cache_value ) {
		$this->cache[ $cache_key ] = $cache_value;
	}

	/**
	 * Interfaces should use these form_make functions so that css and layout is consistant.
	 *
	 * @param string      $label The label to display.
	 * @param string      $param_name The input parameter
	 * @param array       $values An arry of values for the options
	 * @param array       $labels the labels to use, if the values shouldn't also be used for the labels.
	 * @param bool/string $selected The value to pre-select
	 *
	 * @return An html string.
	 */
	function form_make_select( $label, $param_name, $values, $labels = array(), $selected = false ) {
		if ( $label == '' ) {
			$html = '';
		} else {
			$html = '<label>' . $label . '</label>';
		}
		$html .= '<select data-name="' . $param_name . '">';
		$html .= '<option value="">--</option>';
		foreach ( $values as $k => $value ) {
			$html .= '<option value="' . $value . '"';
			if ( $selected == $value ) {
				$html .= ' selected="selected"';
			}
			$html .= '>';

			if ( ! empty( $labels ) ) {
				$label = $labels[ $k ];
			} else {
				$label = $value;
			}

			$html .= $label . '</option>';
		}
		$html .= '</select>';
		return $html;
	}

	/**
	 * Make a textarea
	 *
	 * @param string $label The label to display.
	 * @param string $param_name The input parameter
	 * @param string $value the value to include in the textarea
	 *
	 * @return An html string.
	 */
	function form_make_textarea( $label, $param_name, $value = '' ) {
		$html = '<label>' . $label . '</label>';
		if ( is_array( $value ) ) {
			$value = implode( "\n",$value );
		}
		$html .= '<textarea data-name="' . $param_name . '">' . $value . '</textarea>';
		return $html;
	}

	/**
	 * Make a checkbox
	 *
	 * @param string $label The label to display.
	 * @param string $param_name The input parameter.
	 * @param bool   $checked Should the checkbox be checked.
	 *
	 * @return An html string.
	 */
	function form_make_checkbox( $label, $param_name, $checked ) {
		$html = '<label>' . $label . '</label>';
		$html .= '<input data-name="' . $param_name . '" type="checkbox" value="1"';

		if ( $checked ) {
			$html .= ' checked="checked"';
		}
		$html .= '>';

		return $html;
	}

	/**
	 * Make a text input
	 *
	 * @param string $label The label to display.
	 * @param string $param_name The input parameter.
	 * @param bool   $value The pre-populated input of the text input
	 *
	 * @return An html string
	 */
	function form_make_text( $label, $param_name, $value ) {
		$html = '<label>' . $label . '</label>';
		$html .= '<input type="text" data-name="' . $param_name . '" value="' . $value . '">';
		return $html;
	}

	/**
	 * Get the name of this plugin
	 */
	function get_name() {
		return $this->name;
	}

	/**
	 * The type should be an html-attribute friendly name
	 */
	function get_type() {
		$typename = sanitize_title( $this->name );
		$typename = str_replace( '-', '_', $typename );
		return $typename;
	}

	/**
	 * Make the popups for the geojson
	 *
	 * @param array   $mapping The current mapping we're working with.
	 * @param geojson $json A GeoJSON FeatureCollection.
	 *
	 * @return The modified GeoJSON.
	 */
	function make_popups( $mapping, $json ) {

		if ( empty( $json['features'] ) ) {
			return $json;
		}

		foreach ( $json['features'] as &$feature ) {
			$popup_properties = apply_filters( 'savvymapper_popup_fields', $feature['properties'] , $feature, $mapping );

			$html = '';
			if ( ! empty( $popup_properties ) ) {
				$html .= '<div class="savvymapper_popup_wrapper"><table class="savvymapper_popup">';
				foreach ( $popup_properties as $k => $v ) {
					$empty_row = '';
					if ( empty( $v ) ) {
						$empty_row = ' class="empty_row"';
					}
					$html .= '<tr' . $empty_row . '><th>' . $k . '</th><td>' . $v . '</td></tr>';
				}
				$html .= '</table></div>';
			}

			$popuphtml = apply_filters( 'savvymapper_popup_html', $html, $feature, $mapping );

			if ( ! empty( $popuphtml ) ) {
				$feature['_popup_contents'] = $popuphtml;
			}
		}

		return $json;
	}

	/**
	 * Handle any unsupported methods here
	 */
	function __call( $method, $args ) {
		error_log( 'The method ' . $method . ' is not supported by this class.' );
	}
}
