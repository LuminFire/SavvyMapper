<?php

add_filter( 'savvy_load_interfaces','load_savvy_carto_interface' );
function load_savvy_carto_interface( $interfaces ) {
	class SavvyCartoDB extends SavvyInterface {
		/**
		 * @var Override default name
		 */
		var $name = 'CartoDB';

		/**
		 * implements required method
		 */
		function connection_setup_actions() {
			add_action( 'wp_ajax_savvy_cartodb_get_fields', array( $this, 'get_fields_for_table' ) );
			add_action( 'wp_ajax_nopriv_savvy_cartodb_get_fields', array( $this, 'get_fields_for_table' ) );
		}

		/**
		 * implements required method
		 */
		function autocomplete( $mapping, $term = null ) {
			$sql = 'SELECT DISTINCT ON (' . $mapping['lookup_field'] . ')  ' . $mapping['lookup_field'] . ' FROM ' . $mapping['cdb_table'];

			if ( ! empty( $term ) ) {
				$sql .= ' WHERE ' . $mapping['lookup_field'] . " ILIKE '" . $term . "%'";
			}

			$sql .= ' ORDER BY ' . $mapping['lookup_field'];
			$sql .= ' LIMIT 15';

			$json_str = $this->carto_sql( $sql,false );
			$json = json_decode( $json_str,true );
			$suggestions = array();
			foreach ( $json['rows'] as $row ) {
				$suggestions[] = $row[ $mapping['lookup_field'] ];
			}
			$suggestions = array_filter( $suggestions );
			return $suggestions;
		}

		/**
		 * implements required method
		 */
		function options_div() {
			$connection_details = array_merge( array(
				'username' => '',
				'key' => '',
			), $this->config );
			$html .= $this->form_make_text( 'CartoDB Username','username',$connection_details['username'] );
			$html .= "<br>\n";
			$html .= $this->form_make_text( 'CartoDB API Key','key',$connection_details['key'] );
			$html .= "<br>\n";
			return $html;
		}

		/**
		 * implements required method
		 */
		function mapping_div( $mapping ) {
			$defaults = array(
				'cdb_table' => '',
				'lookup_field' => '',
				'cdb_visualizations' => array(),
				'cdb_show_markers' => 1,
			);

			$mapping = array_merge( $defaults, $mapping );

			$user_tables = $this->carto_user_tables();

			if ( isset( $user_tables[ $mapping['cdb_table'] ] ) ) {
				$cdb_table = $user_tables[ $mapping['cdb_table'] ];
			} else {
				$cdb_table = array();
			}

			$html .= $this->form_make_select( 'CartoDB Table', 'cdb_table', array_keys( $user_tables ), array_keys( $user_tables ), $mapping[ 'cdb_table' ] ) . '<br>' . "\n";
			$html .= $this->form_make_select( 'CartoDB Field', 'lookup_field', $cdb_table, $cdb_table, $mapping[ 'lookup_field' ] ) . '<br>' . "\n";
			$html .= $this->form_make_textarea( 'Visualizations', 'cdb_visualizations', implode( "\n", $mapping[ 'cdb_visualizations' ] )  ) . '<br>' . "\n";

			return $html;
		}

		/**
		 * implements required method
		 */
		function get_attribute_names( $mapping ) {
			$mapping['cdb_table'];

			$user_tables = $this->carto_user_tables();
			if ( isset( $user_tables[ $mapping['cdb_table'] ] ) ) {
				return $user_tables[ $mapping['cdb_table'] ];
			} else {
				return array();
			}
		}

		/**
		 * implements required method
		 */
		function extra_metabox_fields( $post, $mapping, $current_settings = array() ) {
			$visualizations = $current_settings['cdb_visualizations'];

			$html = '<label>Visualizations</label><br>';
			$html .= '<textarea name="savvymapper_visualizations">' . implode( "\n",$visualizations ). '</textarea>' . "\n";

			return $html;
		}

		/**
		 * implements required method
		 */
		function save_meta( $post_id ) {
			if ( isset( $_POST['savvymapper_visualizations'] ) ) {
				$visAr = explode( "\n", $_POST['savvymapper_visualizations'] );
				$visAr = array_map( 'trim', $visAr );
				$visValue = array_filter( $visAr );
			} else {
				$visValue = '';
			}
			return array( 'cdb_visualizations' => $visValue );
		}

		/**
		 * implements required method
		 */
		function get_map_shortcode_properties( $attrs, $contents, $mapping, $curent_settings ) {
			$attrs = array_merge( array(
				'vizes' => array(),
			), $attrs );

			$mapping['cdb_visualizations'] = explode( "\n", $mapping['cdb_visualizations'] );
			$mapping['cdb_visualizations'] = array_filter( $mapping['cdb_visualizations'] );

			if ( empty( $curent_settings['cdb_visualizations'] ) ) {
				$curent_settings['cdb_visualizations'] = array();
			}

			$vizes = array_merge( $mapping['cdb_visualizations'], $curent_settings['cdb_visualizations'], $attrs['vizes'] );

			return array( 'vizes' => $vizes );
		}

		/**
		 * implements required method
		 */
		function get_attribute_shortcode_geojson( $attrs, $contents, $mapping, $current_settings ) {
			$q = 'SELECT ' . $attrs['attr']  . ', the_geom FROM ' . $mapping['cdb_table'] . ' WHERE ' . $mapping['lookup_field'] . " ILIKE '" . $current_settings['lookup_value'] . "'";
			$features = $this->carto_sql( $q );
			return $features;
		}

		/**
		 * implements required method
		 */
		function get_geojson_for_post( $mapping, $current_settings ) {
			$q = 'SELECT * FROM ' . $mapping['cdb_table'] . ' WHERE ' . $mapping['lookup_field'] . " ILIKE '" . $current_settings['lookup_value'] . "'";
			$features = $this->carto_sql( $q );
			return $features;
		}

		/**
		 * implements required method
		 */
		function setup_actions() {
			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		}

		/* ------------ Below this arre CartoDB specific implementation functions ------------ */

		/**
		 * Load the carto scripts
		 */
		function load_scripts() {
			$plugin_dir_url = plugin_dir_url( __FILE__ );
			wp_enqueue_script( 'savvycarto',$plugin_dir_url . 'cartodb.js',array( 'jquery','savvymapjs' ) );
		}

		/**
		 * Get the users' CDB tables and their columns
		 */
		function carto_user_tables() {
			if ( $tables = $this->get_cache( 'user_tables' ) ) {
				return $tables;
			}

			$q = 'SELECT * FROM CDB_UserTables()';
			$tables_raw_string = $this->carto_sql( $q,false );
			$tables_raw = json_decode( $tables_raw_string );

			$tables = array();
			foreach ( $tables_raw->rows as $table ) {
				$tables[ $table->cdb_usertables ] = array();
			}

			$columns_string = $this->carto_sql( "SELECT table_name,column_name FROM information_schema.columns WHERE table_name IN ('" . implode( "','",array_keys( $tables ) ) . "')",false );
			$columns = json_decode( $columns_string );
			foreach ( $columns->rows as $column ) {
				$tables[ $column->table_name ][] = $column->column_name;
			}

			$this->set_cache( 'user_tables',$tables );
			return $tables;
		}

		/**
		 * Given a query make a CartoDB API request
		 *
		 * @param string $sql A cartodb-friendly SQL statement.
		 * @param bool   $json Should the result be parsed as json and returned.
		 *
		 * @return A string or json object, depending on the value of $json.
		 */
		function carto_sql( $sql, $json = true ) {
			$un = $this->config['username'];
			$key = $this->config['key'];

			$querystring = array(
				'q' => $sql,
				'api_key' => $key,
			);

			if ( $json ) {
				$querystring['format'] = 'GeoJSON';
			}

			$url = 'https://' . $un . '.cartodb.com/api/v2/sql?' . http_build_query( $querystring );

			$ret = $this->curl_request( $url );

			if ( $json ) {
				return json_decode( $ret, true );
			} else {
				return $ret;
			}
		}

		/**
		 * Get a list of field names for a given table.
		 *
		 * This is an ajax callback. Print and exit.
		 */
		function get_fields_for_table() {
			$table = $_GET['table'];

			$user_tables = $this->carto_user_tables();

			if ( isset( $user_tables[ $table ] ) ) {
				$cdb_table = $user_tables[ $table ];
			} else {
				$cdb_table = array();
			}

			$html = $this->form_make_select( '','lookup_field', $cdb_table ) . '<br>' . "\n";

			print $html;
			exit();
		}
	}
	$int = new SavvyCartoDB();
	$interfaces[ $int->get_type() ] = $int;

	return $interfaces;
}
