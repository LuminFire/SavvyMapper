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

			// add_action( 'save_post', Array($this,'save_meta'));
			add_shortcode( 'savvy', Array( $this, 'do_shortcodes' ) );
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
		function autocomplete($mapping,$term = NULL) {

			$sql = "SELECT DISTINCT ON (".$mapping['cdb_field'].")  " . $mapping['cdb_field'] . ",the_geom FROM " . $mapping['cdb_table'];

			if(!empty($term)){
				$sql .= " WHERE " . $mapping['cdb_field'] . " ILIKE '" . $term . "%'";
			}

			$sql .= " ORDER BY " . $mapping['cdb_field'];
			$sql .= " LIMIT 25";

			$json = $this->carto_sql($sql);
			return Array($json,$mapping['cdb_field']);
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
		function make_meta_box_map( $post, $mapping ) {
			$target_table = $mapping['cdb_table'];
			$lookup_field = $mapping['cdb_field'];
			$visualizations = implode(',',explode("\n",$mapping['cdb_visualizations']));

			$html = '';
			$html .= '<div class="savvy_lookupbox" data-table="'. $target_table .'" data-lookup="'. $lookup_field .'">';
			$html .= '<label>Look up ' . $lookup_field . ': </label><input class="savvy_lookup_ac" name="savvymapper_' . $mapping['mapping_id'] . '_lookup_value" value="' . $lookup_value. '">';
			$html .= '<input type="hidden" data-vizes="'.$visualizations.'">';
			$html .= '</div>';
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


		function save_meta($post_id){ 
			// Check if our nonce is set.
			if(!isset($_POST['dm_meta_box_nonce'])){
				return;
			}

			// Verify that the nonce is valid.
			if(!wp_verify_nonce($_POST['dm_meta_box_nonce'],'dm_meta_box')){
				return;
			}

			// If this is an autosave, our form has not been submitted, so we don't want to do anything.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			// Check the user's permissions.
			if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
				if ( ! current_user_can( 'edit_page', $post_id ) ) {
					return;
				}
			} else {
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				}
			}

			$cartodb_value = sanitize_text_field($_POST['cartodb_lookup_value']);

			update_post_meta($post_id,'cartodb_lookup_value',$cartodb_value);

		}

		function do_shortcodes( $attrs, $contents ) {
			global $post;

			$attrs = shortcode_atts(Array(
				'attr' => NULL,
				'multiple' => NULL,
				'show' => NULL,
				'onarchive' => 'show',
				'vizes' => NULL,
				'popup' => 'true',
				'marker' => NULL,
				'zoom' => 'default',
				'lat' => 'default',
				'lng' => 'default',
			),$attrs);

			extract($attrs);


			$settings_string = get_post_meta($post->ID,'savvymapper_post_meta',TRUE);
			$settings_ar = json_decode($settings_string,TRUE);
			foreach($settings_ar as $set){
				if($set['connection_id'] == $this->config['_id']){
					$settings = $set;
					break;
				}
			}

			$mapping = $this->savvy->mappings[$settings['mapping_id']];


			// If we're supposed to hide the map on archive pages, bail early.
			if(strtolower($onarchive) == 'hide'){
				if(is_archive()){
					return '';
				}
			}

			$cartoObj = $this->makePostCDBOjb();

			if(!empty($attr)){
				switch($multiple){
					case 'unique':
						$allProp = Array();
						foreach($cartoObj->features as $feature){
							if(!empty($feature->properties->{$attr})){
								$allProp[] = $feature->properties->{$attr};
							}
						}
						$allProp = array_unique($allProp);
						$propHtml = implode(', ',$allProp);
						break;
					default:
						$props = $cartoObj->features[0]->properties;
						$propHtml = $props->{$attr};
				}

				return '<span class="dapper-attr">' . $propHtml . '</span>';
			}else if(!empty($show) && $show == 'map'){
				// merge vizes
				if(strtolower($vizes) === 'false'){
					$visualizations = '';
				} else {
					$vizes = explode(',',$vizes);
					$vizes = array_merge(explode("\n",$mapping['visualizations']),$vizes);
					$vizes = array_filter($vizes);
					$visualizations = implode(',',$vizes);
				}

				// show markers or not?
				if(is_null($marker)){
					$show_markers = ($mapping['show_markers'] === 1 ? 'true' : 'false');
				}else{
					$show_markers = (strtolower($marker) == 'true');
				}

				$html = '';
				$popup_contents = '<table class="leafletpopup">';
				foreach($cartoObj->features[0]->properties as $k => $v){
					$popup_contents .= '<tr><th>' . $k . '</th><td>' . $v . '</td></tr>';
				}
				$popup_contents .= '</table>';
				$cartoObj->features[0]->popup_contents = $popup_contents;
				$props = $cartoObj->features[0]->properties;
				$html .= '<div class="savvy_map_div savvy_page_map_div" ';
				$html .= 'data-post_id="'.$post->ID.'" ';
				$html .= 'data-vizes="' . $visualizations . '" '; 
				$html .= 'data-marker="' . $show_markers . '" ';
				$html .= 'data-lat="' . $lat . '" ';
				$html .= 'data-lng="' . $lng . '" ';
				$html .= 'data-zoom="' . $zoom. '" ';
				$html .= 'data-popup="' . $popup . '" ';
				$html .= '></div>';
				return $html;
			}
			return '';

		}

    /**
     * Get single CDB object baed on the requested post
     *
     * @param $the_post Which post to use. defaults to $post
     */
    function makePostCDBOjb($the_post = NULL){
        global $post;


		$settings_string = get_post_meta($post->ID,'savvymapper_post_meta',TRUE);
		$settings_ar = json_decode($settings_string,TRUE);
		foreach($settings_ar as $set){
			if($set['connection_id'] == $this->config['_id']){
				$settings = $set;
				break;
			}
		}

        $the_post = $post;
		$mapping = $this->savvy->mappings[$settings['mapping_id']];
        $target_table = $mapping['cdb_table'];
        $lookup_field = $mapping['cdb_field'];

        $cartodb_value = $settings['lookup_val'];
		$sql = "SELECT * FROM " . $target_table . " WHERE \"$lookup_field\" ILIKE '" . $cartodb_value . "%'";
        $cartoObj = $this->carto_sql($sql); 

        return $cartoObj;
    }

    /**
     * Ajax handler for running CartoDB queries
     */
    function ajaxCartoQuery(){
        if(isset($_GET['archive_type'])){
            $post_type = $_GET['archive_type'];
            $sql = $this->get_sql_for_archive_post($post_type);
        }else if(isset($_GET['post_id'])){
            $post_id = $_GET['post_id'];
            $sql = $this->get_sql_for_single_post($post_id);
        }

        $this->fetch_and_format_features($sql);
    }

    /**
     * Generate the SQL to fetch a single post
     */
    function get_sql_for_single_post($postid){

			$settings_string = get_post_meta($postid,'savvymapper_post_meta',TRUE);
			$settings_ar = json_decode($settings_string,TRUE);
			foreach($settings_ar as $set){
				if($set['connection_id'] == $this->config['_id']){
					$settings = $set;
					break;
				}
			}

			$mapping = $this->savvy->mappings[$settings['mapping_id']];


        $post = get_post($postid);
        $target_table = $mapping['cdb_table'];
        $lookup_field = $mapping['cdb_field'];
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
