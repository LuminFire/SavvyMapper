<?php

/**
 * SavvyMapper connects your WordPress install to your GIS data in a savvy way
 *
 * Plugin Name: SavvyMapper Core
 * Author: Michael Moore
 * Author URI: http://cimbura.com
 * Version: 0.0.1
 */


/**
 * @class Presents the SavvyMapper UI and API to WordPress
 */
class SavvyMapper {
	/**
	 * @var The singleton instance itself.
	 */
	private static $_instance = NULL;

	/**
	 * @var The users settings.
	 *
	 * Includes information needed to create connections, like
	 * usernames and API keys
	 *
	 * (Not yet set or used)
	 */
	var $settings;

	/**
	 * @var The loaded interfaces.
	 *
	 * An array of geo-api interface instances
	 *
	 * An interface is something we could talk to, if we had
	 * credentials or other settings. An interface will 
	 * probably usually represent a service's API or a file type
	 *
	 * The array is associative, with the shortname as they key
	 * and the instance as the value.
	 *
	 * There should be one interface per service type
	 *
	 * Corresponds with savvymapper_connections
	 */
	var $interfaces;

	/**
	 * @var The loaded interfaces.
	 */
	var $interface_classes;

	/**
	 * @var The mappings between savvyconnector instances and post types.
	 *
	 * It's an array, the keys are the post type, the values are the mapping details
	 *
	 * Corresponds with savvymapper_mappings
	 */
	var $mappings;

	/**
	 * 
	 */

	/**
	 * Get the singleton instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	/**
	 * Initializes the SavvyMapper instance
	 */
	protected function __construct() {
		$this->settings = Array();

		$this->get_mappings();
		$this->setup_actions();
		// $this->setup_shortcodes();
	}

	/**
	 * Set up all the hooks and filters
	 */
	function setup_actions( ) {
		add_action( 'wp_enqueue_scripts', Array( $this, 'load_scripts' ) );
		add_action( 'admin_enqueue_scripts', Array( $this, 'load_scripts' ) );
		add_filter( 'plugin_action_links', Array( $this, 'plugin_add_settings_link' ), 10, 5 );

		add_action( 'wp_ajax_savvy_query_post', Array( $this, 'ajax_query_post' ) );
		add_action( 'wp_ajax_nopriv_savvy_query_post', Array( $this, 'ajax_query' ) );

		add_action( 'wp_ajax_savvy_query_archive', Array( $this, 'ajax_query_archive' ) );
		add_action( 'wp_ajax_nopriv_savvy_query_archive', Array( $this, 'ajax_query_archive' ) );

		add_action( 'wp_ajax_savvy_autocomplete', Array( $this, 'ajax_autocomplete' ) );
		add_action( 'wp_ajax_nopriv_savvy_autocomplete', Array( $this, 'ajax_autocomplete' ) );

		add_action( 'wp_ajax_savvy_get_interface_options_form', Array( $this, 'get_interface_options_form' ) );
		add_action( 'wp_ajax_nopriv_savvy_get_interface_options_form', Array( $this, 'get_interface_options_form' ) );

		add_action( 'wp_ajax_savvy_get_mapping_options_form', Array( $this, 'get_mapping_options_form' ) );
		add_action( 'wp_ajax_nopriv_savvy_get_mapping_options_form', Array( $this, 'get_mapping_options_form' ) );

		add_action( 'add_meta_boxes', Array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', Array($this,'save_meta'));
		add_action( 'loop_start', Array( $this, 'make_archive_map' ) );

		add_action( 'admin_menu', Array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', Array( $this, 'settings_init' ) );

		add_action('plugins_loaded', Array( $this, 'load_interfaces' ) );
	}

	/**
	 * Set up the shortcodes
	 */
	// function setup_shortcodes() {
	// 	add_shortcode( 'savvy', Array( $this, 'do_shortcodes' ) );
	// }

	/**
	 * Load the needed javascript
	 */
	function load_scripts() {
		$plugin_dir_url = plugin_dir_url(__FILE__);
		wp_enqueue_style('savvycss',$plugin_dir_url . 'savvy.css'); 

		wp_enqueue_style('jquery-ui-css',$plugin_dir_url . 'jqui/jquery-ui-1.11.4/jquery-ui.min.css',Array('jquery'));
		wp_enqueue_script('jquery-ui-js',$plugin_dir_url . 'jqui/jquery-ui-1.11.4/jquery-ui.min.js',Array('jquery'));

		wp_enqueue_script('savvyjs',$plugin_dir_url . 'savvy.js',Array('jquery','cartodbjs','markercluster-js')); 
		wp_localize_script( 'savvyjs', 'ajaxurl', admin_url( 'admin-ajax.php' ));


		wp_enqueue_script('dminit',$plugin_dir_url . 'dm_init.js',Array('jquery','dmjs')); 
	}

	/**
	 * Execute the shortcodes
	 *
	 * @param array $attrs The shortcode attributes.
	 * @param string $contents What was between two sets of shortcode tags.
	 *
	 * @return HTML
	 */
	// function do_shortcodes( $attrs, $contents ) {
	// 	return '';
	// }

	/**
	 * Add a link to the settings page, just to be nice
	 *
	 * @param array $actions Array of actions link.
	 * @param string $plugin_file Link to a plugin file, hopefully this one.
	 *
	 * @return The modified list of actions.
	 */
	function plugin_add_settings_link( $actions, $plugin_file) {
		static $plugin;

		if (!isset($plugin)) { 
			$plugin = plugin_basename(__FILE__);
		}

		if ($plugin == $plugin_file) {
			$settings = array('settings' => '<a href="options-general.php?page=savvymapper">' . __('Settings', 'General') . '</a>');
			$actions = array_merge($settings, $actions);

			// $site_link = array('support' => '<a href="http://thetechterminus.com" target="_blank">Support</a>');
			// $actions = array_merge($site_link, $actions);
		}

		return $actions;
	}

	/**
	 * Get the json for a single post from an instance of an interface
	 */
	function ajax_query_post() {
		$instance = $_GET['instance'];
		$json = $this->settings[$instance]->get_post_json();
		$this->send_json($json);
	}

	/**
	 * Get the json for a archive from an instance of an interface
	 */
	function ajax_query_archive() {
		$instance = $_GET['instance'];
		$json = $this->settings[$instance]->get_archive_json();
		$this->send_json($json);
	}

	/**
	 * Autocomplete!
	 */
	function ajax_autocomplete() {
		$connection = $_GET['connection'];
		$mapping = $this->mappings[$_GET['mapping']];
		$term = $_GET['term'];

		list($json,$lookup) = $this->connections[$connection]->autocomplete($mapping,$term);

		if(is_null($json)){
			http_response_code(500);
			exit();
		}

		$suggestions = Array();
		foreach($json->features as $i => $feature){
			$suggestions[] = Array(
				'label' => $feature->properties->$lookup,
				'value' => $feature->properties->$lookup,
			);
		}

		header("Content-Type: application/json");
		print json_encode($suggestions,JSON_FORCE_OBJECT);
		exit();
	}

	/**
	 * Given SQL, fetch the features, set their popup contents and print the GeoJSON
	 */
	function send_json($json){

		$post_type_info = get_post_type_object($post_type);

		foreach($json->features as &$feature){
			$permalink = get_permalink($ids[$feature->properties->cartodb_id]);

			$popup_contents = '<table class="leafletpopup">';
			// $popup_contents .= '<tr><th colspan="2"><a href="' . $permalink . '">View ' .$post_type_info->labels->singular_name .'</a></tr>';
			foreach($feature->properties as $k => $v){
				$popup_contents .= '<tr><th>' . $k . '</th><td>' . $v . '</td></tr>';
			}
			$popup_contents .= '</table>';
			$feature->savvy_popup = $popup_contents;
		}

		header("Content-Type: application/json");
		print json_encode($json);
		exit();

	}

	/**
	 * Make metaboxes!
	 */
	function add_meta_boxes() {
		global $post;

		$this->get_mappings();
		foreach($this->mappings as $mapping){
			if($mapping['post_type'] == $post->post_type){
				$connection = $this->connections[$mapping['connection_id']];
				add_meta_box(
					$mappings['mapping_id'],
					$connection->get_connection_name(),
					Array($this,'make_meta_box'),
					null,
					'advanced',
					'default',
					Array($connection,$mapping)
				);
			}
		}
	} 

	function make_meta_box($post, $metabox){
		$connection = $metabox['args'][0];
		$mapping = $metabox['args'][1];
		// $target_table = $mapping['cdb_table'];
		// $lookup_field = $mapping['lookup_field'];

		// $settings_string = get_post_meta($post->ID,'savvymapper_post_meta',TRUE);
		// $settings_ar = json_decode($settings_string,TRUE);
		// foreach($settings_ar as $set){
		// 	if($set['mapping_id'] == $mapping['mapping_id']){
		// 		$settings = $set;
		// 		break;
		// 	}
		// }

		// $visualizations = implode(',',explode("\n",$mapping['cdb_visualizations']));


		$html = '<div class="savvy_metabox_wrapper">';

		// Two columns
		// Col. 1: settings, help and hidden metadata
		$html .= '<div class="savvymapper_metabox_col">';
		$html .= '<label>Look up value' . $mapping['lookup_field'] . ': </label><input class="db_lookup_ac" name="savvymapper_lookup_value" value="' . $settings['lookup_val'] . '"><br>' . "\n";
		$html .= '<input type="hidden" name="savvyampper_mapping" value="' . json_encode($mapping) . '">';
		$html .= $connection->extra_metabox_fields($post, $mapping);

		ob_start();
		wp_nonce_field( 'savvymapper_meta_box', 'savvymapper_meta_box_nonce' );
		$html .= ob_get_clean();

		$html .= '</div>';



		// Col. 2: map div
		$html .= '<div class="savvymapper_metabox_col">';
		$html .= '<div class="savvy_metabox_map_div"></div>';
		$html .= '</div>';



		$html .= '</div>';

		print $html;
	}

	/**
	 * Make the map for the archive, if the settings say so
	 */
	function make_archive_map($query) {
		if(!is_archive()){
			return;
		}

		if( $query->is_main_query() ){
			$post_type = get_post_type();
			foreach( $this->mappings as $mapping_type => $mapping ){
				if ($mapping_type === $post_type ) {
					$this->make_archive_map();
				}
			}
		}
	}

	/**
	 * Add the admin menu entry
	 */
	function add_admin_menu() { 
		add_menu_page( 
			'SavvyMapper',		// page title
			'SavvyMapper',		// menu title
			'manage_options',	// capability
			'savvymapper',		// menu slug
			Array($this,'options_page') // function to show page contents
			// icon url
			// position
		);

		add_submenu_page(
			'savvymapper',	//parent_slug
			'Post Mapping', // page title
			'Post Mapping', // menu title
			'manage_options', // capability
			'savvy_connections', // menu slug
			Array($this,'connection_mapping_page') // function to get output contents
		);
	}

	/**
	 * Show the mappings and edit them
	 */
	function connection_mapping_page() {
		$html = '<div class="wrap savvymapper_mapping_wrap">';
		$html .= '<h2>SavvyMapper Post Mapping</h2>';
		$html .= '<p>Configure mapping between your post types and your SavvyMapper connections.</p>';

		// Get all post types and make an option dropdown.
		$post_types = get_post_types(Array('public' => true,));
		ksort($post_types);
		$post_type_options = Array();
		foreach($post_types as $post_type){
			$post_type_object = get_post_type_object($post_type);
			// $post_type_list[$post_type] = $post_type_object->labels->singular_name;
			$post_type_options[] = '<option value="' . $post_type . '">' . $post_type_object->labels->singular_name . '</option>';
		}

		$connection_options = Array();
		$connections = $this->get_connections();
		foreach($connections as $connection){
			$connection_options[] = '<option value="' . $connection->get_id() . '">' . $connection->get_connection_name() . '</option>';
		}

		// Show all the settings
		$mapping = $this->get_mappings();
		$html .= '<div id="savvy_mapping_settings">';
		foreach( $mapping as $one_mapping ) {
			$html .= $this->_get_mapping_options_form($one_mapping);
		}
		$html .= '<br></div>';

		// Show the form to add new mappings
		$html .= '<div id="savvy_mapping_form">';
		$html .= '<select name="savvy_post_type">' . implode( "\n", $post_type_options ) . '</select> ';
		$html .= '<select name="savvy_connection_id">' . implode( "\n", $connection_options ) . '</select> ';
		$html .= '<input type="button" onclick="savvy.add_mapping(this);" value="Add Mapping">';
		$html .= '</div>';

		// Save options form
		$html .= '<form method="post" action="options.php">';

		ob_start();
		settings_fields('savvymapper_mapping_page');
		do_settings_sections('savvymapper_mapping_page');
		submit_button();
		$html .= ob_get_clean();

		$html .= '</form>';
		$html .= '</div>';

		print $html;
	}

	/**
	 * Print the options form
	 */
	function options_page() {
		$html = '<div class="wrap savvymapper_options_wrap">';
		$html .= '<h2>SavvyMapper</h2>';
		$html .= '<p>Welcome to SavvyMapper. Add connections to services below.</p>';
		foreach( $this->interface_classes as $interface ) {
			$html .= '<input type="button" onclick="savvy.add_connection(this);" data-type="' . $interface->get_type() . '" value="Add ' . $interface->get_name() . ' Connection"> ';
		}
		$html .= '<hr>';

		$html .= '<div id="savvyoptions">';


		$connections = $this->get_connections();
		foreach($connections as $connection){
			$html .= $this->_get_interface_options_form( $connection );
		}

		$html .= '</div>';

		$html .= '<form method="post" action="options.php">';

		ob_start();
		settings_fields('savvymapper_plugin_page');
		do_settings_sections('savvymapper_plugin_page');
		submit_button();
		$html .= ob_get_clean();

		$html .= '</form>';
		$html .= '</div>';

		print $html;
	}

	/**
	 *
	 */
	function settings_init() {
		register_setting( 
			'savvymapper_plugin_page',						// option group
			'savvymapper_connections'							// option name
		);													// sanitize_callback
		add_settings_section(
			'savvymapper_plugin_page_section',				// id
			__( 'SavvyMapper Settings Page', 'wordpress' ), // title
			Array($this,'getting_started_callback'),		// callback
			'savvymapper_plugin_page'						// page
		);
		add_settings_field( 
			'savvymapper_connections',							// id
			__( 'SavvyMapper Connections', 'wordpress' ),		// title  
			Array($this,'savvymapper_connections_callback'),	// callback
			'savvymapper_plugin_page',						// page
			'savvymapper_plugin_page_section',				// section
			array()											// args
		);



		register_setting( 
			'savvymapper_mapping_page',						// option group
			'savvymapper_mappings'							// option name
		);		
		add_settings_section(
			'savvymapper_mapping_page_section',					// id
			__( 'SavvyMapper Mapping Settings', 'wordpress' ),  // title
			Array($this,'getting_started_callback'),			// callback
			'savvymapper_mapping_page'							// page
		);
		add_settings_field( 
			'savvymapper_mappings',							// id
			__( 'SavvyMapper Mappings', 'wordpress' ),		// title  
			Array($this,'savvymapper_mapping_callback'),	// callback
			'savvymapper_mapping_page',						// page
			'savvymapper_mapping_page_section',				// section
			array()											// args
		);
	}

	function load_interfaces() {
		require_once( dirname( __FILE__ ) . '/savvyinterface.php' );
		$this->interface_classes = apply_filters( 'savvy_load_interfaces', $this->interface_classes);

		// Now that we have all our interfaces we can make our connections
		$this->get_connections();
	}


	/**
	 * This is called by ajax to make new boxes, and by options page to load existing options
	 */
	function get_interface_options_form(){
		$interface_type = $_GET['interface'];
		$interface = $this->interface_classes[$interface_type];
		print $this->_get_interface_options_form( $interface );
		exit();
	}

	/**
	 * Actually create the options form
	 *
	 * @param SavvyInterface $interface An instance of SavvyInterface.
	 *
	 * @return HTML for a single instance-config block.
	 */
	function _get_interface_options_form( $interface ){
		$html .= '<div class="instance-config">';
		$html .= '<h3><span class="remove-instance">(X)</span> ' . $interface->get_name() . ' Connection</h3>';
		$html .= '<label>Connection Name</label> <input type="text" data-name="connection_name" value="' . $interface->get_connection_name() . '"><br>' . "\n";
		$html .= '<input type="hidden" data-name="interface" value="' . $interface->get_type() . '">' . "\n";
		$html .= '<input type="hidden" data-name="_id" value="' . $interface->get_id() . '">' . "\n";
		$html .= $interface->options_div();
		$html .= '<hr>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * This is called by ajax to make new data mapping boxes, and by options page to load existing values
	 */
	function get_mapping_options_form(){
		$connection_id = $_GET['connection_id'];

		$mapping = Array(
			'post_type' => $_GET['post_type'],
			'connection_id' => $_GET['connection_id'],
		);

		print $this->_get_mapping_options_form( $mapping );
		exit();	
	}

	function _get_mapping_options_form( $mapping ){
		$postType = $mapping[ 'post_type' ];
		$post_type_info = get_post_type_object(  $postType );

		$connections = $this->get_connections();
		$connection = $connections[ $mapping[ 'connection_id' ] ];

		$mapping_id = (empty($mapping['mapping_id']) ? time() : $mapping['mapping_id']);

		$html = '<div class="mapping-config">';
		$html .= '<h3><span class="remove-instance">(X)</span> ' . $post_type_info->labels->singular_name . ' => ' . $connection->get_connection_name() . '</h3>';
		$html .= '<label>Mapping Name</label> <input type="text" data-name="mapping_name" value="' . $mapping['mapping_name'] . '"><br>' . "\n";
		$html .= '<input type="hidden" data-name="connection_id" value="' . $connection->get_id() . '"><br>' . "\n";
		$html .= '<input type="hidden" data-name="mapping_id" value="' . $mapping_id . '"><br>' . "\n";
		$html .= '<input type="hidden" data-name="post_type" value="' . $mapping['post_type'] . '"><br>' . "\n";
		$html .= $connection->mapping_div($mapping);
		$html .= '<hr>';
		$html .= '</div>';
		return $html;
	}

	function getting_started_callback() {
		return 'getting started callback';
	}

	function savvymapper_connections_callback() {
		$settings = get_option( 'savvymapper_connections', '{}' );
		print '<textarea name="savvymapper_connections" id="savvymapper_connections">' . $settings . '</textarea>';
	}

	function savvymapper_mapping_callback() {
		$settings = get_option( 'savvymapper_mappings', '{}' );
		print '<textarea name="savvymapper_mappings" id="savvymapper_mappings">' . $settings . '</textarea>';
	}

	/**
	 * Get the known connections, requesting them from the database, if needed
	 *
	 * A connection will have at least: 
	 * {'interface': $interface_type, '_id': $the_id
	 *
	 *
	 * @return $this->connections, an array of connections
	 */
	function get_connections() {
		if ( isset( $this->connections ) ) {
			return $this->connections;
		}

		$settings_connections_string = get_option( 'savvymapper_connections', '{}' );
		$settings = json_decode( $settings_connections_string, TRUE );
		$connections_list = Array();
		foreach($settings['connections'] as $connection){
			$interfaceClass = get_class($this->interface_classes[ $connection[ 'interface' ] ]);
			$connections_list[ $connection[ '_id' ] ] = new $interfaceClass($connection);
		}

		$this->connections = $connections_list;
		return $this->connections;
	}

	/**
	 * Get the known mappings, requesting them from the database, if needed
	 *
	 * @return $this->mappings, an array of mappings
	 */
	function get_mappings() {
		if ( isset( $this->mappings ) ) {
			return $this->mappings;
		}

		$mapping_string = get_option( 'savvymapper_mappings', "{'mapping':[]}" );
		$mapping = json_decode( $mapping_string, TRUE );

		$this->mappings = Array();
		foreach($mapping['mappings'] as $mapping){
			$this->mappings[$mapping['mapping_id']] = $mapping;
		}

		return $this->mappings;
	}

	function save_meta($post_id){ 
		// Check if our nonce is set.
		if(!isset($_POST['savvymapper_meta_box_nonce'])){
			return;
		}

		// Verify that the nonce is valid.
		if(!wp_verify_nonce($_POST['savvymapper_meta_box_nonce'],'savvymapper_meta_box')){
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

		$settings = Array();
		foreach($_POST['savvymapper_lookup_value'] as $k => $lookup_val){
			$settingGroup = Array(
				'lookup_val' =>		sanitize_text_field($_POST['savvymapper_lookup_value'][$k]),
				'mapping_id' =>		sanitize_text_field($_POST['savvymapper_mapping_id'][$k]),
				'connection_id' =>	sanitize_text_field($_POST['savvymapper_connection_id'][$k])
			);
			$settings[] = $settingGroup;
		}



		update_post_meta($post_id,'savvymapper_post_meta',json_encode($settings));

	}
}
SavvyMapper::get_instance();

foreach( glob( dirname( __FILE__ ) . '/interfaces/*.php' ) as $interface ) {
	require_once( $interface );
}
