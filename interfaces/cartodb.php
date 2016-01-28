<?php

add_filter('savvy_load_interfaces','load_savvy_carto_interface');
function load_savvy_carto_interface( $interfaces ) {
	class SavvyCartoDB extends SavvyInterface {
		/**
		 * @var Override default name
		 */
		var $name = "CartoDB";

		/**
		 * Set up all the hooks and filters
		 */
		function setup_actions() {
			add_action( 'wp_enqueue_scripts', Array( $this, 'load_scripts' ) );
			add_action( 'admin_enqueue_scripts', Array( $this, 'load_scripts' ) );
		}

		/**
		 *
		 */
		function connection_setup_actions() {
			add_action( 'wp_ajax_savvy_cartodb_get_fields', Array( $this, 'get_fields_for_table' ) );
			add_action( 'wp_ajax_nopriv_savvy_cartodb_get_fields', Array( $this, 'get_fields_for_table' ) );
		}

		/**
		 * Load the carto scripts
		 */
		function load_scripts() {
			$plugin_dir_url = plugin_dir_url(__FILE__);
			wp_enqueue_style('cartodbcss','http://libs.cartocdn.com/cartodb.js/v3/3.15/themes/css/cartodb.css');
			wp_enqueue_style('markercluster-css',$plugin_dir_url . 'leaflet/MarkerCluster.css'); 
			wp_enqueue_style('markercluster-default-css',$plugin_dir_url . 'leaflet/MarkerCluster.Default.css'); 
			wp_enqueue_script('cartodbjs','http://libs.cartocdn.com/cartodb.js/v3/3.15/cartodb.js');
			wp_enqueue_script('markercluster-js',$plugin_dir_url . 'leaflet/leaflet.markercluster.js',Array('cartodbjs'));
			wp_enqueue_script('savvycarto',$plugin_dir_url . 'cartodb.js',Array('jquery'));
		}

		function get_post_json() {
			return $this->get_carto_geo_json();
		}

		function get_archive_json() {
			return $this->get_carto_geo_json();
		}

		/**
		 * Autocomplete the text for the selected field
		 */
		function autocomplete() {
			if(empty($_GET['table'])){
				http_response_code(400);
				exit();
			}

			$sql = "SELECT DISTINCT ON (".$_GET['lookup'].")  " . $_GET['lookup'] . ",the_geom FROM " . $_GET['table'];

			if(isset($_GET['term']) && !empty($_GET['term'])){
				$sql .= " WHERE " . $_GET['lookup'] . " ILIKE '" . $_GET['term'] . "%'";
			}

			$sql .= " ORDER BY " . $_GET['lookup'];
			$sql .= " LIMIT 25";

			$json = $this->carto_sql($sql);
			return $json;
		}


		/**
		 * Get GeoJSON from CartoDB
		 */
		function get_carto_geo_json(){
			if(empty($_GET['table'])){
				http_response_code(400);
				exit();
			}
			$sql = "SELECT * FROM " . $_GET['table'];

			if(isset($_GET['val']) && !empty($_GET['val'])){
				$sql .= " WHERE " . $_GET['lookup'] . " ILIKE '" . $_GET['val'] . "%'";
			}

			$sql .= " ORDER BY " . $_GET['lookup'];

			if(empty($_GET['limit'])){
				$sql .= " LIMIT 500";
			}else{
				$sql .= " LIMIT " . $_GET['limit'];
			}

			$json = $this->carto_sql($sql);

			if(is_null($json)){
				http_response_code(500);
				exit();
			}

			return $json;
		}

		

		/**
		 * Make the metabox
		 */
		function make_meta_box( $post, $metabox ) {
			$target_table = $this->mappings[$post->post_type]['table'];
			$lookup_field = $this->mappings[$post->post_type]['lookup'];

			$cartodb_value = get_post_meta($post->ID,'cartodb_lookup_value',TRUE);
			$visualizations = implode(',',explode("\n",$this->mappings[$post->post_type]['visualizations']));

			$html = '';
			$html .= '<div class="db_lookupbox" data-table="'. $target_table .'" data-lookup="'. $lookup_field .'">';
			$html .= '<label>Look up ' . $lookup_field . ': </label><input class="db_lookup_ac" name="cartodb_lookup_value" value="' . $cartodb_value . '">';
			// $html .= '<input type="hidden" name="cartodb_lookup_value" value="' . $cartodb_id . '">';
			ob_start();
			wp_nonce_field( 'dm_meta_box', 'dm_meta_box_nonce' );
			$html .= ob_get_clean();
			$html .= '<div class="shortcodehints"><h3>Available Shortcodes</h3><ul><li><strong>[dm attr="<i>attribute name</i>"]</strong> -- Show the value of the specified attribute</li>';
			$html .= '<li><strong>[dm show="map"]</strong> -- Show the feature on the map</li></ul></div>';
			$html .= '<h3>Available Attributes</h3>';
			$html .= '<p>Attribute values from the first feature found will be used unless you specify what do do in case of multiple (eg. multiple="all").</p>';
			$html .= '<div class="dm_lookup_meta">';
			$html .= '</div>';
			$html .= '</div>';

			$html .= '<div class="dm_metabox_map_div" data-vizes="'.$visualizations.'"></div>';

			return $html;
		}

		function make_archive_map() {
			$vizes = explode("\n",$this->mappings[$post_type]['visualizations']);
			$vizes = array_filter($vizes);
			$visualizations = implode(',',$vizes);

			$show_markers = ($this->mappings[$post_type]['show_markers'] === '1' ? 'true' : 'false');

			$html = '<article class="hentry archivemapwrap">';
			$html .= '<div class="dm_map_div dm_archive_map" ';
			$html .= 'data-archive_type="'.$post_type.'" ';
			$html .= 'data-vizes="' . $visualizations . '" '; 
			$html .= 'data-marker="' . $show_markers . '" ';
			$html .= '></div>';
			$html .= '</article>';

			return $html;
		}

		function options_div() {
			$connection_details = shortcode_atts( Array(
				'username' => '',
				'key' => '',
				), $this->config );
			$html .= '<label>CartoDB Username </label><input type="text" data-name="username" value="' . $connection_details['username'] . '"><br>' . "\n";
			$html .= '<label>CartoDB API Key </label><input type="text" data-name="key" value="' . $connection_details['key'] . '"><br>' . "\n";
			return $html;
		}

		function mapping_div( $mapping ) {
			$defaults = Array(
				'cdb_table' => '',
				'cdb_field' => '',
				'cdb_visualizations' => '',
				'cdb_show_markers' => 1
				);

			$mapping = shortcode_atts($defaults, $mapping);

			$user_tables = $this->carto_user_tables();
			$html .= '<label>CartoDB Table</label>: ';
			$html .= $this->form_make_select('cdb_table', array_keys($user_tables), array_keys($user_tables), $mapping['cdb_table'] ) . '<br>' . "\n";

			if(isset($user_tables[$mapping['cdb_table']])){
				$cdb_table = $user_tables[$mapping['cdb_table']];
			} else {
				$cdb_table = Array();
			}

			$html .= '<label>CartoDB Field</label>: ';
			$html .= $this->form_make_select('cdb_field', $cdb_table, $cdb_table, $mapping['cdb_field'] ) . '<br>' . "\n";


			$html .= '<label>Visualizations</label>: ';
			$html .= $this->form_make_textarea('cdb_visualizations',$mapping['cdb_visualizations']) . '<br>' . "\n";

			$html .= '<label>Show Markers</label>: ';
			$html .= $this->form_make_checkbox('cdb_show_markers',$mapping['cdb_show_markers']);


			return $html;
		}

		function settings_init() {
			return Array();
		}

		/* ------------ Below this arre CartoDB specific implementation functions ------------ */


		function make_cdb_field_select( $table, $selected = FALSE ) {
			$user_tables = $this->carto_user_tables();
			$html = '<select data-name="cdb_field">';
			$html = '<option value="">--</option>';
			if(isset($user_tables[$table])){
				foreach( $user_tables[$table] as $field_name){
					$html .= '<option value="' . $field_name . '"';
					if ( $select == $field_name ) {
						$html .= ' selected="selected"';
					}
					$html .= '>' . $field_name . '</option>';
				}
			}
			$html .= '</select>';

			return $html;
		}


		/**
		 * Get the users' CDB tables and their columns
		 */
		function carto_user_tables() {
			if($tables = $this->get_cache('user_tables')){
				return $tables;
			}

			$q = 'SELECT * FROM CDB_UserTables()';
			$tables_raw_string = $this->carto_sql($q,FALSE);
			$tables_raw = json_decode($tables_raw_string);

			$tables = Array();
			foreach($tables_raw->rows as $table){
				$tables[$table->cdb_usertables] = Array();
			}

			$columns_string = $this->carto_sql("SELECT table_name,column_name FROM information_schema.columns WHERE table_name IN ('" . implode("','",array_keys($tables)) . "')",FALSE);
			$columns = json_decode($columns_string);
			foreach($columns->rows as $column){
				$tables[$column->table_name][] = $column->column_name;
			}

			$this->set_cache('user_tables',$tables);
			return $tables;
		}

		/**
		 * Given a query make a CartoDB API request
		 */
		function carto_sql($sql,$json = TRUE){

			$un = $this->config['username'];
			$key = $this->config['key'];

			$querystring = Array(
				'q' => $sql,
				'api_key' => $key,
			);

			if($json){
				$querystring['format'] = 'GeoJSON';
			}

			$url = 'https://' . $un . '.cartodb.com/api/v2/sql?' . http_build_query($querystring);

			$ret = $this->curl_request($url);

			if($json){
				return json_decode($ret);
			}else{
				return $ret;
			}
		}

		function get_fields_for_table(){
			$table = $_GET['table'];

			$user_tables = $this->carto_user_tables();

			if(isset($user_tables[$table])){
				$cdb_table = $user_tables[$table];
			} else {
				$cdb_table = Array();
			}

			$html = $this->form_make_select('cdb_field', $cdb_table) . '<br>' . "\n";

			print $html;
			exit();
		}
	}
	$interfaces['cartodb'] = new SavvyCartoDB();

	return $interfaces;
} 
