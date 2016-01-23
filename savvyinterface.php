<?php

/**
 * @class The list of functions that a class must implement
 * to be used as an interface
 */
abstract class SavvyInterface {

	/**
	 * @var The SavvyMapper instance;
	 */
	var $savvy;

	/**
	 * @var This interface's name
	 */
	var $name = "Default Savvy Interface";

	/**
	 * Init self and load self into SavvyMapper
	 */
	function __construct(){
		$this->savvy = SavvyMapper::get_instance();
		$this->savvy->register_interface( $this );
	}

	/**
	 * Handle any unsupported methods here
	 */
	function __call( $method, $args ) {
		error_log( 'The method ' . $method . ' is not supported by this class.' );
	}

	function get_name() {
		return $this->name;
	}

	function get_metabox_name() {
		$metaname = sanitize_title($this->name . ' meta_box');
		$metaname = str_replace('-','_',$metaname);
		return $metaname;
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
	abstract function autocomplete();

	/**
	 * Make the metabox for this interface
	 *
	 * @param WP_Post $post The post this metabox is for.
	 * @param notsure $metabox Something else.
	 *
	 * Prints the metabox html
	 */
	abstract function make_meta_box($post,$metabox);

	/**
	 * Get the part of the form for this interface
	 */
	abstract function options_page();

	/**
	 * Save the settings.
	 */
	abstract function settings_init();

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
}
