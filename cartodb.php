<?php

/**
 * This is the CartoDB plugin for DapperMapper
 *
 * It implements the DapperInterface
 */

add_action('init',function(){
	$cdb = new DapperCartoDB();
	$cdb->activate();
});

require_once(__DIR__ . '/dapperPlugin.php');
class DapperCartoDB extends DapperPlugin {

	var $pluginName = 'CartoDB';

	function __construct(){
		if(empty($this->settings)){
			$this->settings = Array(
				'dm_cartodb_api_key' => '',
				'dm_cartodb_username' => ''
			);
		}
	}

	function saveSettings(){
		if(isset($_POST)){
			if(isset($_POST['dm_cdb_table'])){
				$newMappings = Array();
				// Clean up visualizations
				foreach($_POST['dm_cdb_visualizations'] as $i => $vis){
					// Split on newlines and commas. They're supposed to use newlines, but people are going to not listen
					$tmpv = preg_split("|[\s,]|",$vis);

					// Trim whitespace
					$tmpv = array_map('trim',$tmpv);

					// Sanitize all URLs
					$tmpv = array_map(function($u){
						$valid = filter_var($u,FILTER_SANITIZE_URL);
						return $valid;
					},$tmpv);

					// Remove any falsey entries
					$tmpv = array_filter($tmpv,function($u){
						// strip illegal chars
						$valid = filter_var($u,FILTER_SANITIZE_URL);
						return filter_var($u,FILTER_VALIDATE_URL);      
					});

					// Make a newline delimited list of visualizations
					$_POST['dm_cdb_visualizations'][$i] = implode("\n",$tmpv);
				}

				foreach($_POST['dm_cdb_table'] as $i => $cdb_tablename){
					if($cdb_tablename){
						$newMappings[$_POST['dm_post_type'][$i]] = Array(
							'table' => $_POST['dm_cdb_table'][$i], 
							'lookup' => $_POST['dm_lookup_field'][$i],
							'visualizations' => $_POST['dm_cdb_visualizations'][$i],
							'show_markers' => (isset($_POST['dm_show_markers_' . $i]) ? 1 : 0),
						);
					}
				}
				update_option('dm_table_mapping',$newMappings);
			}
		}
	}


	function getSettings(){
		if(empty($this->settings['dm_cartodb_api_key'])){
			add_settings_section(
				'dm_pluginPage_section', 
				__( 'Getting Started', 'wordpress' ), 
				Array($this,'getting_started_callback'), 
				'dm_pluginPage'
			);
		}

		add_settings_field( 
			'dm_cartodb_username', 
			__( 'Your CartoDB Username', 'wordpress' ), 
			Array($this,'cartodb_username'), 
			'dm_pluginPage', 
			'dm_pluginPage_section' 
		);

		add_settings_field( 
			'dm_cartodb_api_key', 
			__( 'Your CartoDB API Key', 'wordpress' ), 
			Array($this,'cartodb_api_key'), 
			'dm_pluginPage', 
			'dm_pluginPage_section' 
		);

		if(!empty($this->settings['dm_cartodb_api_key'])){

			add_settings_section(
				'dm_pluginPage_section', 
				__( 'Instructions', 'wordpress' ), 
				Array($this,'instructions_callback'), 
				'dm_pluginPage'
			);

			add_settings_section(
				'dm_pluginPage_mapping_table', 
				__( 'Post Type to CartoDB Map', 'wordpress' ), 
				Array($this,'pluginPage_mapping_table'), 
				'dm_pluginPage'
			);
		}
	}

	function load_scripts(){
		$plugin_dir_url = plugin_dir_url(__FILE__);

		wp_enqueue_style('cartodbcss','http://libs.cartocdn.com/cartodb.js/v3/3.15/themes/css/cartodb.css');
		wp_enqueue_style('markercluster-css',$plugin_dir_url . 'leaflet/MarkerCluster.css'); 
		wp_enqueue_style('markercluster-default-css',$plugin_dir_url . 'leaflet/MarkerCluster.Default.css'); 

		wp_enqueue_script('cartodbjs','http://libs.cartocdn.com/cartodb.js/v3/3.15/cartodb.js');
		wp_enqueue_script('markercluster-js',$plugin_dir_url . 'leaflet/leaflet.markercluster.js',Array('cartodbjs'));

		return Array('cartodbjs','markercluster-js');
	}

	/**
	 * Get GeoJSON from CartoDB
	 */
	function getGeoJSON(){
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

		$json = $this->cartoSQL($sql);

		if(is_null($json)){
			http_response_code(500);
			exit();
		}

		header("Content-Type: application/json");
		print json_encode($json);
		exit();
	}

	/**
	 * Autocomplete the text for the selected field
	 */
	function getAutocomplete(){
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

		$json = $this->cartoSQL($sql);

		if(is_null($json)){
			http_response_code(500);
			exit();
		}

		$suggestions = Array();
		foreach($json->features as $i => $feature){
			$suggestions[] = Array(
				'label' => $feature->properties->{$_GET['lookup']},
				'value' => $feature->properties->{$_GET['lookup']},
			);
		}

		header("Content-Type: application/json");
		print json_encode($suggestions,JSON_FORCE_OBJECT);
		exit();
	}


	/**
	 * Settings input for cartodb_api_key
	 */
	function cartodb_api_key(  ) { 
		print "<input type='text' name='dm_settings[dm_cartodb_api_key]' value='" . $this->settings['dm_cartodb_api_key'] . "'>";
	}


	/**
	 * Settings input for cartodb username
	 */
	function cartodb_username(  ) { 
		print "<input type='text' name='dm_settings[dm_cartodb_username]' value='" . $this->settings['dm_cartodb_username'] . "'>";
	}

	/**
	 * Instructions for users who are just starting
	 */
	function getting_started_callback() { 
		echo "<p>";
		echo "Enter your CartoDB Username and API key below and click &ldquo;" . __('Save Changes','wordpress') . "&rdquo; to get started.";
		echo "</p>";
		echo "<p><a href='http://docs.cartodb.com/cartodb-platform/sql-api.html#api-key'>Need help finding your key?</a></p>";
	}

	/**
	 * Instructions for users who are just starting
	 */
	function instructions_callback(){
		echo "<p>For any post type you would like to associate with a CartoDB table, select the CartoDB table name from the dropdown,</p>";
	}


	/**
	 * Get list of user tables and print out a form to let users set mapping between post types and CDB tables
	 */
	function pluginPage_mapping_table(){
		$html = '';
		$tables_raw = $this->cartoSQL("SELECT * FROM CDB_UserTables()",FALSE);
		$tables_raw = json_decode($tables_raw);

		$tables = Array();
		foreach($tables_raw->rows as $table){
			$tables[$table->cdb_usertables] = Array();
		}

		$columns = $this->cartoSQL("select table_name,column_name from information_schema.columns where table_name IN ('" . implode("','",array_keys($tables)) . "')",FALSE);
		$columns = json_decode($columns);
		foreach($columns->rows as $column){
			$tables[$column->table_name][] = $column->column_name;
		}

		$cdb_tables_and_columns = Array();
		foreach($tables as $table => $fields){
			$cdb_tables_and_columns[$table] = $this->makeCDBFieldSelect($tables,$table);
		}
		$cdb_tables_and_columns['dm_empty_select_list'] = $this->makeCDBFieldSelect($tables);
		$html .= '<script type="text/javascript">var cdb_tables_and_columns = ' . json_encode($cdb_tables_and_columns) . ';</script>';

		$post_types = get_post_types(Array('public' => true,));
		ksort($post_types);

		$html .= '<table class="dm_settings">';
		$html .= '<tr><th>Post Type</th><th>CartoDB Table</th><th>CartoDB Lookup Field</th></tr>';

		$count = 0;
		foreach($post_types as $post_type){
			$post_type_object = get_post_type_object($post_type);
			$selected = FALSE;
			$lookup_field = '';
			$visualizations = '';
			$show_markers = '1';

			if(isset($this->mappings[$post_type])){
				$selected = $this->mappings[$post_type]['table'];
				$lookup_field = $this->mappings[$post_type]['lookup'];
				$visualizations = $this->mappings[$post_type]['visualizations'];
				$show_markers = ($this->mappings[$post_type]['show_markers'] === 0 ? '' : 'checked');
			}

			// The CDB association row
			$html .= '<tr><td><input type="hidden" name="dm_post_type[]" value="' . $post_type . '">'. $post_type_object->labels->singular_name .'</td>';

			$cdb_select = $this->makeCDBTableSelect($tables,$selected);
			$html .= '<td>' . $cdb_select . '</td>';

			$cdb_field_select = $this->makeCDBFieldSelect($tables,$selected,$lookup_field);
			$html .= '<td class="dm_cdb_field_select_td">'.$cdb_field_select.'</td></tr>';


			// The visualization row
			$html .= '<tr class="dm_vizbox"><td></td><td>Visualizations</td>';
			$html .= '<td><textarea name="dm_cdb_visualizations[]" placeholder="One CartoDB visualization per line">'.$visualizations.'</textarea></td></tr>';

			// Show points row
			$html .= '<tr class="dm_showpoints"><td></td><td>Show markers</td>';
			$html .= '<td><input type="checkbox" name="dm_show_markers_'.$count.'" ' . $show_markers . ' value="1"></td></tr>';

			$count++;
		}
		$html .= "</table>";

		print $html;
	}


	/**
	 * Make the table select dropdown
	 */
	function makeCDBTableSelect($opts,$selected = NULL){
		$cdb_select = '<select name="dm_cdb_table[]" class="dm_cdb_table_select">';
		$cdb_select .= '<option value="">--</option>';
		foreach($opts as $table => $fields){
			$selectedattr = ($table == $selected ? 'selected="selected"' : '');
			$cdb_select .= '<option value="' . $table . '" ' . $selectedattr . '>' . $table . '</option>';
		}
		$cdb_select .= "</select>";

		return $cdb_select;
	}

	/**
	 * Make the CDB field select dropdown
	 */
	function makeCDBFieldSelect($tables,$tablename,$selected = NULL){
		$cdb_select = '<select name="dm_lookup_field[]" class="dm_cdb_field_select">';
		$cdb_select .= '<option value="">--</option>';
		foreach($tables[$tablename] as $field){
			$selectedattr = ($field == $selected ? 'selected="selected"' : '');
			$cdb_select .= '<option value="' . $field . '" ' . $selectedattr . '>' . $field . '</option>';
		}
		$cdb_select .= "</select>";

		return $cdb_select;
	}

	/**
	 * Given a query make a CartoDB API request
	 */
	function cartoSQL($sql,$json = TRUE){

		$un = $this->settings['dm_cartodb_username'];
		$key = $this->settings['dm_cartodb_api_key'];

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

	/**
	 * Get all CDB IDs for a given post type
	 */
	function get_cdbids_for_post_type($post_type){
		global $wpdb;
		$res = $wpdb->get_results( "SELECT 
			p.ID,
			pm.meta_value
			FROM 
			wp_posts p,
			wp_postmeta pm
			WHERE 
			p.post_type='".$post_type."' AND 
			p.post_status='publish' AND
			pm.post_id=p.ID AND
			pm.meta_key='dapper_lookup_value'");

		$ids = Array();
		foreach($res as $one){
			if($one->meta_value){
				$ids[$one->meta_value] = $one->ID;
			}
		}
		return $ids;
	}

	/**
	 * Generate SQL to fetch all CDB objects which have posts
	 */
	function get_sql_for_archive_post($post_type){
		if(!isset($this->mappings[$post_type])){
			http_response_code(404);
			exit();
		}

		$ids = $this->get_cdbids_for_post_type($post_type);
		$target_table = $this->mappings[$post_type]['table'];

		if(!empty($target_table)){
			$sql = 'SELECT * FROM "' . $target_table . '" WHERE "cartodb_id" IN (' . implode(',',array_keys($ids)) . ')';
		}

		return $sql;
	}

	/**
	 * Generate the SQL to fetch a single post
	 */
	function get_sql_for_single_post($postid){
		$post = get_post($postid);
		$target_table = $this->mappings[$post->post_type]['table'];
		$lookup_field = $this->mappings[$post->post_type]['lookup'];
		$cartodb_value = $this->get_post_meta($post->ID,'dapper_lookup_value');

		if(!empty($target_table) && !empty($lookup_field) && !empty($cartodb_value)){
			$sql = 'SELECT * FROM "' . $target_table . '" WHERE "'.$lookup_field.'"=\'' . $cartodb_value . "'";
			return $sql;
		}
	}

	/**
	 * Given SQL, fetch the features, set their popup contents and print the GeoJSON
	 */
	function fetch_and_format_features($sql){
		if(empty($sql)){
			http_response_code(500);
			exit();
		}

		$json = $this->cartoSQL($sql);

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

	/**
	 * Get single CDB object baed on the requested post
	 *
	 * @param $the_post Which post to use. defaults to $post
	 */
	function makePostCDBOjb($the_post = NULL){
		global $post;
		global $dapperObj;

		if(isset($dapperObj)){
			return $dapperObj;
		}

		$the_post = (is_null($the_post) ? $post : $the_post);
		$target_table = $this->mappings[$the_post->post_type]['table'];
		$lookup_field = $this->mappings[$the_post->post_type]['lookup'];

		$cartodb_value = $this->get_post_meta($the_post->ID,'dapper_lookup_value');
		$dapperObj = $this->cartoSQL("SELECT * FROM " . $target_table . " WHERE \"$lookup_field\"='" . $cartodb_value . "'"); 

		return $dapperObj;
	}
}
