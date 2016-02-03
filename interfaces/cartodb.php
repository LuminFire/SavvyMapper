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


			add_action('wp_ajax_carto_query',Array($this,'ajaxCartoQuery'));
			add_action('wp_ajax_nopriv_carto_query',Array($this,'ajaxCartoQuery'));

			// add_shortcode( 'savvy', Array( $this, 'do_shortcodes' ) );
		}

		/**
		 * Load the carto scripts
		 */
		function load_scripts() {
			$plugin_dir_url = plugin_dir_url(__FILE__);
			wp_enqueue_style('cartodbcss','http://libs.cartocdn.com/cartodb.js/v3/3.15/themes/css/cartodb.css');
			// wp_enqueue_script('cartodbjs','http://libs.cartocdn.com/cartodb.js/v3/3.15/cartodb.js');
			wp_enqueue_script('cartodbjs','http://libs.cartocdn.com/cartodb.js/v3/3.15/cartodb.uncompressed.js');
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
		function autocomplete( $mapping, $term = NULL ) {

			$sql = "SELECT DISTINCT ON (" . $mapping[ 'lookup_field' ] . ")  " . $mapping[ 'lookup_field' ] . " FROM " . $mapping[ 'cdb_table' ];

			if(!empty($term)){
				$sql .= " WHERE " . $mapping['lookup_field'] . " ILIKE '" . $term . "%'";
			}

			$sql .= " ORDER BY " . $mapping['lookup_field'];
			$sql .= " LIMIT 25";

			$json_str = $this->carto_sql($sql,FALSE);
			$json = json_decode($json_str,TRUE);
			$suggestions = Array();
			foreach($json['rows'] as $row){
				$suggestions[] = $row[ $mapping[ 'lookup_field' ] ];
			}
			$suggestions = array_filter($suggestions);
			return $suggestions;
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
				'lookup_field' => '',
				'cdb_visualizations' => Array(),
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
			$html .= $this->form_make_select('lookup_field', $cdb_table, $cdb_table, $mapping['lookup_field'] ) . '<br>' . "\n";


			$html .= '<label>Visualizations</label>: ';
			$html .= $this->form_make_textarea( 'cdb_visualizations', implode( "\n", $mapping[ 'cdb_visualizations' ] ) ) . '<br>' . "\n";

			$html .= '<label>Show Markers</label>: ';
			$html .= $this->form_make_checkbox('cdb_show_markers',$mapping['cdb_show_markers']);

			return $html;
		}

		function settings_init() {
			return Array();
		}

		/* ------------ Below this arre CartoDB specific implementation functions ------------ */


		function make_lookup_field_select( $table, $selected = FALSE ) {
			$user_tables = $this->carto_user_tables();
			$html = '<select data-name="lookup_field">';
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
				return json_decode( $ret, TRUE );
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

			$html = $this->form_make_select('lookup_field', $cdb_table) . '<br>' . "\n";

			print $html;
			exit();
		}

		function extra_metabox_fields( $post, $mapping, $current_settings = Array() ) {
			$visualizations = $current_settings['cdb_visualizations'];

			$html = '<label>Visualizations</label><br>';
			$html .= '<textarea name="savvymapper_visualizations">' . implode("\n",$visualizations). '</textarea>' . "\n";

			return $html;
		}

		function save_meta($post_id){
			// TODO: Loop through posted vars to capture interface specific values
			if( isset( $_POST[ 'savvymapper_visualizations' ] ) ) {
				$visAr = explode( "\n", $_POST[ 'savvymapper_visualizations' ] );
				$visAr = array_map( 'trim', $visAr );
				$visValue = array_filter( $visAr );
			} else {
				$visValue = '';
			}
			return Array( 'cdb_visualizations' => $visValue );
		}


		function get_map_shortcode_properties( $attrs, $contents, $mapping, $curent_settings ) {
			$attrs = array_merge( Array(
				'vizes' => Array(),
			), $attrs );

			$mapping['cdb_visualizations'] = explode( "\n", $mapping['cdb_visualizations'] );
			$mapping['cdb_visualizations'] = array_filter($mapping['cdb_visualizations']);

			if( empty( $curent_settings[ 'cdb_visualizations' ] ) ){
				$curent_settings[ 'cdb_visualizations' ] = Array();
			}

			$vizes = array_merge( $mapping[ 'cdb_visualizations' ], $curent_settings[ 'cdb_visualizations' ], $attrs[ 'vizes' ] );

			return Array( 'vizes' => $vizes );
		}

		function get_attribute_shortcode_geosjon( $attrs, $contents, $mapping, $current_settings ){
			$q = "SELECT " . $attrs['attr']  . ", the_geom FROM " . $mapping['cdb_table'] . " WHERE " . $mapping['lookup_field'] . " ILIKE '" . $current_settings['lookup_value'] . "'";
			$features = $this->carto_sql($q);
			return $features;
		}

		function get_geojson_for_post( $mapping, $current_settings ){
			$q = "SELECT * FROM " . $mapping['cdb_table'] . " WHERE " . $mapping['lookup_field'] . " ILIKE '" . $current_settings['lookup_value'] . "'";
			$features = $this->carto_sql($q);
			return $features;
		}


		/**
		 * Generate the SQL to fetch a single post
		 */
		function get_sql_for_single_post($postid){
			list($connection, $mapping, $settings ) = $this->savvy->get_post_info_by_post_id( $postid );

			$post = get_post($postid);
			$target_table = $mapping['cdb_table'];
			$lookup_field = $mapping['lookup_field'];
			$cartodb_value = $settings['lookup_val'];

			if(!empty($target_table) && !empty($lookup_field) && !empty($cartodb_value)){
				$sql = 'SELECT * FROM "' . $target_table . '" WHERE "'.$lookup_field.'" ILIKE \'' . $cartodb_value. "%'";
				return $sql;
			}
		}

		/**
		 * Given SQL, fetch the features, set their popup contents and print the GeoJSON
		 */
		function fetch_and_format_features($sql){
			global $post;
			$post_type = $post->post_type;
			if(empty($sql)){
				http_response_code(500);
				exit();
			}

			$json = $this->carto_sql($sql);

			if(is_null($json)){
				http_response_code(500);
				exit();
			}

			$post_type_info = get_post_type_object($post_type);

			foreach($json->features as &$feature){
				$permalink = get_permalink($ids[$feature->properties->cartodb_id]);

				$popup_contents = '<table class="leafletpopup">';
				$popup_contents .= '<tr><th colspan="2"><a href="' . $permalink . '">View ' .$post_type_info->labels->singular_name .'</a></tr>';
				foreach($feature->properties as $k => $v){
					$popup_contents .= '<tr><th>' . $k . '</th><td>' . $v . '</td></tr>';
				}
				$popup_contents .= '</table>';
				$feature->popup_contents = $popup_contents;
			}

			header("Content-Type: application/json");
			print json_encode($json);
			exit();
		}
	}
	$interfaces['cartodb'] = new SavvyCartoDB();

	return $interfaces;
} 
