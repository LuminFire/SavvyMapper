<?php

/*
 * @package Cimbura
 * Plugin Name: DapperMapper for CartoDB
 * Author: Michael Moore
 * Author URI: http://cimbura.com
 * Description: Easily tie your CartoDB data to posts in WordPress. Access CartoDB properties dynamically via shortcode and show maps on post archive pages.
 * Version: 0.1
 */


class DapperMapper {

    public static function getInstance(){
        if(is_null(self::$_instance)){
            self::$_instance = new self;
            self::$_instance->setupActions(self::$_instance);
        }

        return self::$_instance;
    }

    private static $_instance = NULL;

    protected function __construct(){}

    /**
     * Set up all the hooks, filters and shortcodes.
     *
     * @param $that An instance of DapperMapper
     */
    private function setupActions($that){
        add_action( 'wp_enqueue_scripts', Array($that,'dm_load_scripts'));
        add_action( 'admin_enqueue_scripts', Array($that,'dm_load_scripts'));
        add_filter( "plugin_action_links_dappermapper/dappermapper.php",Array($that,'dm_plugin_add_settings_link'));
        add_action('wp_ajax_carto_query',Array($that,'dm_ajaxCartoQuery'));
        add_action('wp_ajax_nopriv_carto_query',Array($that,'dm_ajaxCartoQuery'));
        add_action( 'wp_ajax_carto_metabox', Array($that,'dm_getCartoGeoJSON'));
        add_action( 'wp_ajax_nopriv_carto_metabox', Array($that,'dm_getCartoGeoJSON'));
        add_action('loop_start',Array($that,'dm_make_archive_map_maybe'));
        add_action( 'save_post', Array($that,'dm_save_meta'));
        add_action( 'add_meta_boxes', Array($that,'dm_add_meta_box'));
        add_action( 'admin_menu', Array($that,'dm_add_admin_menu'));
        add_action( 'admin_init', Array($that,'dm_settings_init'));
        add_shortcode('dm',Array($that,'dm_shortcodes'));
    }

    /**
     * Load all the javascript for this plugin
     *
     * We're probably over-loading stuff here. We could probably only load some stuff on specific pages
     */
    function dm_load_scripts() {
        $plugin_dir_url = plugin_dir_url(__FILE__);

        wp_enqueue_style('cartodbcss','http://libs.cartocdn.com/cartodb.js/v3/3.15/themes/css/cartodb.css');
        wp_enqueue_style('markercluster-css',$plugin_dir_url . 'leaflet/MarkerCluster.css'); 
        wp_enqueue_style('markercluster-default-css',$plugin_dir_url . 'leaflet/MarkerCluster.Default.css'); 
        wp_enqueue_style('dmcss',$plugin_dir_url . 'dm.css'); 
        wp_enqueue_style('jquery-ui-css',$plugin_dir_url . 'jqui/jquery-ui-1.11.4/jquery-ui.min.css',Array('jquery'));


        wp_enqueue_script('cartodbjs','http://libs.cartocdn.com/cartodb.js/v3/3.15/cartodb.js');
        wp_enqueue_script('markercluster-js',$plugin_dir_url . 'leaflet/leaflet.markercluster.js',Array('cartodbjs'));
        wp_enqueue_script('dmjs',$plugin_dir_url . 'DapperMapper.js',Array('jquery'),Array('cartodbjs','markercluster-js')); 
        wp_localize_script( 'dmjs', 'ajaxurl', admin_url( 'admin-ajax.php' ));

        wp_enqueue_script('dminit',$plugin_dir_url . 'dm_init.js',Array('jquery','dmjs'),Array('dmjs')); 
        wp_enqueue_script('jquery-ui-js',$plugin_dir_url . 'jqui/jquery-ui-1.11.4/jquery-ui.min.js',Array('jquery'));
    }

    /**
     * Add a link to the settings page
     */
    function dm_plugin_add_settings_link( $links ) {
        $settings_link = '<a href="options-general.php?page=dappermapper">' . __( 'Settings' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Ajax handler for running CartoDB queries
     */
    function dm_ajaxCartoQuery(){
        if(isset($_GET['archive_type'])){
            $post_type = $_GET['archive_type'];
            $sql = $this->dm_get_sql_for_archive_post($post_type);
        }else if(isset($_GET['post_id'])){
            $post_id = $_GET['post_id'];
            $sql = $this->dm_get_sql_for_single_post($post_id);
        }

        $this->dm_fetch_and_format_features($sql);
    }


    /**
     * Get GeoJSON from CartoDB
     */
    function dm_getCartoGeoJSON(){
        // $_GET['table'];
        // $_GET['lookup'];

        if(strlen($_GET['table']) === 0){
            http_response_code(400);
            exit();
        }
        $sql = "SELECT * FROM " . $_GET['table'];

        if(isset($_GET['term'])){
            $sql .= " WHERE " . $_GET['lookup'] . " ILIKE '" . $_GET['term'] . "%'";
        }else if(isset($_GET['cartodb_id']) && !empty($_GET['cartodb_id'])){
            $sql .= " WHERE cartodb_id = '" . $_GET['cartodb_id'] . "'";
        }

        $sql .= " ORDER BY " . $_GET['lookup'];

        // no limits. We've got clustering
        // $sql .= " LIMIT 500";

        $json = $this->dm_cartoSQL($sql);

        if(is_null($json)){
            http_response_code(500);
            exit();
        }

        header("Content-Type: application/json");
        print json_encode($json);
        exit();
    }

    /**
     * Make the archive map, unless we're not an archive
     * @param $query -- Passed by WP
     */
    function dm_make_archive_map_maybe($query){
        if(!is_archive()){
            return;
        }
        if( $query->is_main_query() ){

            $mappings = get_option('dm_table_mapping');
            $post_type = get_post_type();

            if(isset($mappings[$post_type])){
                $mappings = get_option('dm_table_mapping');

                $vizes = explode("\n",$mappings[$post_type]['visualizations']);
                $vizes = array_filter($vizes);
                $visualizations = implode(',',$vizes);

                $show_markers = ($mappings[$post_type]['show_markers'] === 'checked' ? 'true' : 'false');

                $html = '<article class="hentry archivemapwrap">';
                $html .= '<div class="dm_map_div dm_archive_map" ';
                $html .= 'data-archive_type="'.$post_type.'" ';
                $html .= 'data-vizes="' . $visualizations . '" '; 
                $html .= 'data-marker="' . $show_markers . '" ';
                $html .= '></div>';
                $html .= '</article>';

                print $html;
            }
        }
    }

    /**
     * Add the metabox to the options page
     */
    function dm_add_meta_box(){
        global $post;

        $mappings = get_option('dm_table_mapping');
        $options = get_option('dm_settings');

        if(isset($mappings[$post->post_type])){
            add_meta_box(
                'dm_meta_box',
                'CartoDB Connection',
                Array($this,'dm_make_meta_box')
            );
        }
    }

    /**
     * Make the actual metabox contents
     */
    function dm_make_meta_box($post,$metabox){
        $mappings = get_option('dm_table_mapping');
        $target_table = $mappings[$post->post_type]['table'];
        $lookup_field = $mappings[$post->post_type]['lookup'];

        $cartodb_id = get_post_meta($post->ID,'cartodb_lookup_value',TRUE);
        $cartodb_label = get_post_meta($post->ID,'cartodb_lookup_label',TRUE);
        $visualizations = implode(',',explode("\n",$mappings[$post->post_type]['visualizations']));

        print '<div class="db_lookupbox" data-table="'. $target_table .'" data-lookup="'. $lookup_field .'">';
        print '<label>Look up ' . $lookup_field . ': </label><input class="db_lookup_ac" name="cartodb_lookup_label" value="' . $cartodb_label . '">';
        print '<input type="hidden" name="cartodb_lookup_value" value="' . $cartodb_id . '">';
        wp_nonce_field( 'dm_meta_box', 'dm_meta_box_nonce' );
        print '<div class="shortcodehints"><h3>Available Shortcodes</h3><ul><li><strong>[dm attr="<i>attribute name</i>"]</strong> -- Show the value of the specified attribute</li>';
        print '<li><strong>[dm show="map"]</strong> -- Show the feature on the map</li></ul></div>';
        print '<div class="dm_lookup_meta">';
        print '</div>';
        print '</div>';

        print '<div class="dm_metabox_map_div" data-vizes="'.$visualizations.'"></div>';
    }

    /**
     * Save our custom metabox contents
     */
    function dm_save_meta($post_id){
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

        $cartodb_id = sanitize_text_field($_POST['cartodb_lookup_value']);
        $cartodb_label = sanitize_text_field($_POST['cartodb_lookup_label']);

        update_post_meta($post_id,'cartodb_lookup_value',$cartodb_id);
        update_post_meta($post_id,'cartodb_lookup_label',$cartodb_label);
    }

    /**
     * Add the admin menu entry
     */
    function dm_add_admin_menu(  ) { 
        add_menu_page( 'DapperMapper', 'DapperMapper', 'manage_options', 'dappermapper', 'dm_options_page' );
    }


    /*
     * Build the settings page
     */
    function dm_settings_init(  ) {
        $settings = get_option( 'dm_settings' );

        if(isset($_POST)){
            if(isset($_POST['dm_cdb_table'])){
                $mappings = Array();
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
                        $mappings[$_POST['dm_post_type'][$i]] = Array(
                            'table' => $_POST['dm_cdb_table'][$i], 
                            'lookup' => $_POST['dm_lookup_field'][$i],
                            'visualizations' => $_POST['dm_cdb_visualizations'][$i],
                            'show_markers' => (isset($_POST['dm_show_markers_' . $i]) ? 1 : 0),
                        );
                    }
                }
                update_option('dm_table_mapping',$mappings);
            }
        }

        register_setting( 'pluginPage', 'dm_settings' );

        if(empty($settings['dm_cartodb_api_key'])){
            add_settings_section(
                'dm_pluginPage_section', 
                __( 'Getting Started', 'wordpress' ), 
                Array($this,'dm_getting_started_callback'), 
                'pluginPage'
            );
        }

        add_settings_field( 
            'dm_cartodb_username', 
            __( 'Your CartoDB Username', 'wordpress' ), 
            Array($this,'dm_cartodb_username'), 
            'pluginPage', 
            'dm_pluginPage_section' 
        );

        add_settings_field( 
            'dm_cartodb_api_key', 
            __( 'Your CartoDB API Key', 'wordpress' ), 
            Array($this,'dm_cartodb_api_key'), 
            'pluginPage', 
            'dm_pluginPage_section' 
        );

        if(!empty($settings['dm_cartodb_api_key'])){

            add_settings_section(
                'dm_pluginPage_section', 
                __( 'Instructions', 'wordpress' ), 
                Array($this,'dm_instructions_callback'), 
                'pluginPage'
            );

            add_settings_section(
                'dm_pluginPage_mapping_table', 
                __( 'Post Type to CartoDB Map', 'wordpress' ), 
                Array($this,'dm_pluginPage_mapping_table'), 
                'pluginPage'
            );
        }
    }


    /**
     * Settings input for cartodb_api_key
     */
    function dm_cartodb_api_key(  ) { 
        $settings = get_option( 'dm_settings' );
        print "<input type='text' name='dm_settings[dm_cartodb_api_key]' value='" . $settings['dm_cartodb_api_key'] . "'>";
    }


    /**
     * Settings input for cartodb username
     */
    function dm_cartodb_username(  ) { 
        $settings = get_option( 'dm_settings' );
        print "<input type='text' name='dm_settings[dm_cartodb_username]' value='" . $settings['dm_cartodb_username'] . "'>";
    }

    /**
     * Instructions for users who are just starting
     */
    function dm_getting_started_callback() { 
        echo "<p>";
        echo "Enter your CartoDB Username and API key below and click &ldquo;" . __('Save Changes','wordpress') . "&rdquo; to get started.";
        echo "</p>";
        echo "<p><a href='http://docs.cartodb.com/cartodb-platform/sql-api.html#api-key'>Need help finding your key?</a></p>";
    }

    /**
     * Instructions for users who are just starting
     */
    function dm_instructions_callback(){
        echo "<p>For any post type you would like to associate with a CartoDB table, select the CartoDB table name from the dropdown,</p>";
        echo "<p>blah blah blah</p>";
    }

    /**
     * Get list of user tables and print out a form to let users set mapping between post types and CDB tables
     */
    function dm_pluginPage_mapping_table(){
        $settings = get_option('dm_settings');
        $mappings = get_option('dm_table_mapping');

        $tables_raw = $this->dm_cartoSQL("SELECT * FROM CDB_UserTables()",FALSE);
        $tables_raw = json_decode($tables_raw);

        $tables = Array();
        foreach($tables_raw->rows as $table){
            $tables[$table->cdb_usertables] = Array();
        }

        $columns = $this->dm_cartoSQL("select table_name,column_name from information_schema.columns where table_name IN ('" . implode("','",array_keys($tables)) . "')",FALSE);
        $columns = json_decode($columns);
        foreach($columns->rows as $column){
            $tables[$column->table_name][] = $column->column_name;
        }

        $cdb_tables_and_columns = Array();
        foreach($tables as $table => $fields){
            $cdb_tables_and_columns[$table] = makeCDBFieldSelect($tables,$table);
        }
        $cdb_tables_and_columns['dm_empty_select_list'] = makeCDBFieldSelect($tables);
        print '<script type="text/javascript">var cdb_tables_and_columns = ' . json_encode($cdb_tables_and_columns) . ';</script>';

        $post_types = get_post_types(Array('public' => true,));
        ksort($post_types);

        print '<table class="dm_settings">';
        print '<tr><th>Post Type</th><th>CartoDB Table</th><th>CartoDB Lookup Field</th></tr>';

        $count = 0;
        foreach($post_types as $post_type){
            $post_type_object = get_post_type_object($post_type);
            $selected = FALSE;
            $lookup_field = '';
            $visualizations = '';
            $show_markers = 'checked';

            if(isset($mappings[$post_type])){
                $selected = $mappings[$post_type]['table'];
                $lookup_field = $mappings[$post_type]['lookup'];
                $visualizations = $mappings[$post_type]['visualizations'];
                $show_markers = ($mappings[$post_type]['show_markers'] === 0 ? '' : 'checked');
            }

            // The CDB association row
            print '<tr><td><input type="hidden" name="dm_post_type[]" value="' . $post_type . '">'. $post_type_object->labels->singular_name .'</td>';

            $cdb_select = $this->dm_makeCDBTableSelect($tables,$selected);
            print '<td>' . $cdb_select . '</td>';

            $cdb_field_select = makeCDBFieldSelect($tables,$selected,$lookup_field);
            print '<td class="dm_cdb_field_select_td">'.$cdb_field_select.'</td></tr>';


            // The visualization row
            print '<tr class="dm_vizbox"><td></td><td>Visualizations</td>';
            print '<td><textarea name="dm_cdb_visualizations[]" placeholder="One CartoDB visualization per line">'.$visualizations.'</textarea></td></tr>';

            // Show points row
            print '<tr class="dm_showpoints"><td></td><td>Show markers</td>';
            print '<td><input type="checkbox" name="dm_show_markers_'.$count.'" ' . $show_markers . ' value="1"></td></tr>';

            $count++;
        }
        print "</table>";
    }


    /**
     * Print the options form
     */
    function dm_options_page(  ) {
        print "<form action='options.php' method='post'><h2>DapperMapper</h2>";
        settings_fields( 'pluginPage' );
        do_settings_sections( 'pluginPage' );
        submit_button();
        print "</form>";
    }

    /**
     * Make the table select dropdown
     */
    function dm_makeCDBTableSelect($opts,$selected = NULL){
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
     * Handle all dm shortcodes
     */
    function dm_shortcodes($scatts){
        global $post;

        extract(shortcode_atts(Array(
            'attr' => NULL,
            'show' => NULL,
            'onarchive' => 'show',
            'vizes' => NULL,
            'popup' => 'true',
            'marker' => NULL,
            'zoom' => 'default',
            'lat' => 'default',
            'lng' => 'default',
        ),$scatts));

        // If we're supposed to hide the map on archive pages, bail early.
        if(strtolower($onarchive) == 'hide'){
            if(is_archive()){
                return '';
            }
        }

        $cartoObj = $this->dm_makePostCDBOjb();

        if(!empty($attr)){
            $props = $cartoObj->features[0]->properties;
            if(isset($props->{$attr})){
                return '<span class="dapper-attr">' . $props->{$attr} . '</span>';
            }
        }else if(!empty($show) && $show == 'map'){
            $mappings = get_option('dm_table_mapping');

            // merge vizes
            if(strtolower($vizes) === 'false'){
                $visualizations = '';
            } else {
                $vizes = explode(',',$vizes);
                $vizes = array_merge(explode("\n",$mappings[$post->post_type]['visualizations']),$vizes);
                $vizes = array_filter($vizes);
                $visualizations = implode(',',$vizes);
            }

            // show markers or not?
            if(is_null($maker)){
                $show_markers = ($mapping[$post->post_type]['show_markers'] === 'checked' ? 'true' : 'false');
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
            $html .= '<div class="dm_map_div dm_page_map_div" ';
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

    /*
     * cURL wrapper which returns request and response headers, curl request meta, post and response body.
     *
     * Slightly simplified version of what's used in esco-4d-api.php for the api debugger page
     *
     * @param $url (String) The URL to make the request to
     * @param $data (Array) The data to post. If array is empty, GET will be used
     * @param $debug (Bool, defaults to FALSE) Should debug info be returned?
     *
     * @return A dict with all the requst info, if debug is TRUE. Otherwise just returns the response body
     */
    function dm_curl_request( $url, $data = Array(), $debug = FALSE){

        $post = curl_init();
        curl_setopt($post, CURLOPT_URL, $url);
        curl_setopt($post, CURLOPT_POST, count($data));
        curl_setopt($post, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($post, CURLINFO_HEADER_OUT, true);
        curl_setopt($post, CURLOPT_VERBOSE, 1);
        curl_setopt($post, CURLOPT_HEADER, 1);

        curl_setopt($post, CURLOPT_CONNECTTIMEOUT, 5); // connect timeout
        curl_setopt($post, CURLOPT_TIMEOUT, 20); //timeout in seconds

        // Set the path to any custom cert files
        // curl_setopt($post, CURLOPT_CAINFO, plugin_dir_path( __FILE__ ).'cacert.pem');

        $response = curl_exec($post);

        $header_size = curl_getinfo($post, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $info = curl_getinfo($post);

        $response = Array(
            'request_headers' => (isset($info['request_header']) ? $info['request_header'] : ''),
            'post_body' => http_build_query($data),
            'response_headers' => $header,
            'body' => $body,
            'errno' => curl_errno($post),
            'error' => curl_error($post),
            'curl_info' => $info,
        );

        curl_close($post);

        if($debug){
            return $response;
        }

        return $response['body'];
    }

    /**
     * Given a query make a CartoDB API request
     */
    function dm_cartoSQL($sql,$json = TRUE){

        $options = get_option( 'dm_settings' );
        $un = $options['dm_cartodb_username'];
        $key = $options['dm_cartodb_api_key'];

        $querystring = Array(
            'q' => $sql,
            'api_key' => $key,
        );

        if($json){
            $querystring['format'] = 'GeoJSON';
        }

        $url = 'https://' . $un . '.cartodb.com/api/v2/sql?' . http_build_query($querystring);

        $ret = $this->dm_curl_request($url);

        if($json){
            return json_decode($ret);
        }else{
            return $ret;
        }
    }

    /**
     * Get all CDB IDs for a given post type
     */
    function dm_get_cdbids_for_post_type($post_type){
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
            pm.meta_key='cartodb_lookup_value'");

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
    function dm_get_sql_for_archive_post($post_type){
        $mappings = get_option('dm_table_mapping');
        if(!isset($mappings[$post_type])){
            http_response_code(404);
            exit();
        }

        $ids = $this->dm_get_cdbids_for_post_type($post_type);
        $target_table = $mappings[$post_type]['table'];

        if(!empty($target_table)){
            $sql = 'SELECT * FROM "' . $target_table . '" WHERE "cartodb_id" IN (' . implode(',',array_keys($ids)) . ')';
        }

        return $sql;
    }

    /**
     * Generate the SQL to fetch a single post
     */
    function dm_get_sql_for_single_post($postid){
        $post = get_post($postid);
        $mappings = get_option('dm_table_mapping');
        $target_table = $mappings[$post->post_type]['table'];
        $lookup_field = $mappings[$post->post_type]['lookup'];
        $cartodb_id = get_post_meta($post->ID,'cartodb_lookup_value',TRUE);

        if(!empty($target_table) && !empty($lookup_field) && !empty($cartodb_id)){
            $sql = 'SELECT * FROM "' . $target_table . '" WHERE "cartodb_id"=\'' . $cartodb_id . "'";
            return $sql;
        }
    }

    /**
     * Given SQL, fetch the features, set their popup contents and print the GeoJSON
     */
    function dm_fetch_and_format_features($sql){
        if(empty($sql)){
            http_response_code(500);
            exit();
        }

        $json = $this->dm_cartoSQL($sql);

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
    function dm_makePostCDBOjb($the_post = NULL){
        global $post;
        global $cartoObj;

        if(isset($cartoObj)){
            return $cartoObj;
        }

        $the_post = (is_null($the_post) ? $post : $the_post);
        $mappings = get_option('dm_table_mapping');
        $target_table = $mappings[$the_post->post_type]['table'];
        $lookup_field = $mappings[$the_post->post_type]['lookup'];

        $cartodb_id = get_post_meta($the_post->ID,'cartodb_lookup_value',TRUE);
        $cartodb_label = get_post_meta($the_post->ID,'cartodb_lookup_label',TRUE);
        $cartoObj = $this->dm_cartoSQL("SELECT * FROM " . $target_table . " WHERE cartodb_id='" . $cartodb_id . "'"); 

        return $cartoObj;
    }
}

DapperMapper::getInstance();
