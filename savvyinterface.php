<?php

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
	var $name = "Default Savvy Interface";

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
	function __construct($config = Array()){
		$this->savvy = SavvyMapper::get_instance();
		$this->setup_actions();
		$this->set_config( $config );
	}

	/**
	 * Handle any unsupported methods here
	 */
	function __call( $method, $args ) {
		error_log( 'The method ' . $method . ' is not supported by this class.' );
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
	 * For a given query get the json for a specific post
	 */
	abstract function get_post_json();

	/**
	 * For a given query get the json for a specific archive
	 */
	abstract function get_archive_json();

	/**
	 * Ajax autocomplete. Return the JSON features that match the query
	 */
	abstract function autocomplete($mapping,$term);

	/**
	 * Make the metabox for this interface
	 *
	 * @param WP_Post $post The post this metabox is for.
	 * @param notsure $metabox Something else.
	 *
	 * Prints the metabox html
	 */
	abstract function extra_metabox_fields($post,$metabox);

	/**
	 * Get the part of the form for the connection interface
	 */
	abstract function options_div();

	/**
	 * Get the part of the form for setting up the mapping
	 */
	abstract function mapping_div( $mapping_definition );

	/**
	 * Save the settings.
	 */
	abstract function settings_init();

	/**
	 * Setup the actions to get things started
	 *
	 * This is run for all instances of the interface, even empty ones
	 */
	function setup_actions() { }
		
	/**
	 * Setup actions for a specific connection
	 *
	 * This is run only when we have a config
	 */
	function connection_setup_actions() { }

	/**
	 * @param array $config This instance's config
	 */
	function set_config( $config = Array() ) {
		$this->config = $config;

		if(!empty($config)){
			$this->connection_setup_actions();
		}
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
    function curl_request( $url, $data = Array(), $debug = FALSE){

        $post = curl_init();
        curl_setopt($post, CURLOPT_URL, $url);
        curl_setopt($post, CURLOPT_POST, count($data));
        curl_setopt($post, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($post, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($post, CURLINFO_HEADER_OUT, true);
        curl_setopt($post, CURLOPT_VERBOSE, 1);
        curl_setopt($post, CURLOPT_HEADER, 1);

        curl_setopt($post, CURLOPT_CONNECTTIMEOUT, 5); // connect timeout
        curl_setopt($post, CURLOPT_TIMEOUT, 60); //timeout in seconds

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
        }else{
            $this->last_curl = $response;
        }

        return $response['body'];
    }

	/**
	 * Get the ID for this instance
	 *
	 * @return The ID, or the curren time if this interface isn't set up yet.
	 */
	function get_id() {
		if ( !empty( $this->config[ '_id' ] ) ) {
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
		if ( !empty( $this->config[ 'connection_name' ] ) ) {
			return $this->config[ 'connection_name' ];
		}
		return '';
	}

	/**
	 * Get something from cache
	 */
	function get_cache($cache_key) {
		if(isset($this->cache[$cache_key])){
			return $this->cache[$cache_key];
		}
		return FALSE;
	}

	/**
	 * Set the cache
	 */
	function set_cache($cache_key,$cache_value){
		$this->cache[$cache_key] = $cache_value;
	}

	/* --- form generation stuff --- */
	function form_make_select( $param_name, $values, $labels = Array(), $selected = FALSE ){
		$html = '<select data-name="' . $param_name . '">';
		$html .= '<option value="">--</option>';
		foreach($values as $k => $value){
			$html .= '<option value="' . $value . '"';
			if ( $selected == $value ) {
				$html .= ' selected="selected"';
			}
			$html .= '>';

			if(!empty($labels)){
				$label = $labels[$k];
			}else{
				$label = $value;
			}

			$html .= $label . '</option>';
		}
		$html .= '</select>';
		return $html;
	}

	function form_make_textarea( $param_name, $value = ''){
		$html = '<textarea data-name="' . $param_name . '">' . $value . '</textarea>';
		return $html;
	}

	function form_make_checkbox( $param_name, $checked ) {
		$html .= '<input data-name="' . $param_name . '" type="checkbox" value="1"';

		if($checked){
			$html .= ' checked="checked"';
		}
		$html .= '>';

		return $html;
	}

	abstract function save_meta($post_id);
}
