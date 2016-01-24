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
	 * @var The mappings between savvyconnector instances and post types.
	 */
	var $mappings;

	/**
	 * @var The users settings.
	 */
	var $settings;

	/**
	 * @var The loaded interfaces.
	 */
	var $interfaces;

	/**
	 * @var The loaded interfaces.
	 */
	var $interface_classes;

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
		$this->mappings = get_option('savvy_table_mapping');
		$this->settings = get_option('savvy_settings');

		if ( empty( $this->mappings ) ) {
			$this->mappings = Array();
		}

		if ( empty ( $this->settings ) ) {
			$this->settings = Array();
		}

		$this->setup_actions();
		$this->setup_shortcodes();
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

        add_action( 'add_meta_boxes', Array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', Array( $this, 'save_meta' ) );
        add_action( 'loop_start', Array( $this, 'make_archive_map' ) );

        add_action( 'admin_menu', Array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', Array( $this, 'settings_init' ) );

		add_action('plugins_loaded', Array( $this, 'load_interfaces' ) );
	}

	/**
	 * Set up the shortcodes
	 */
	function setup_shortcodes() {
        add_shortcode( 'savvy', Array( $this, 'do_shortcodes' ) );
	}

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
	function do_shortcodes( $attrs, $contents ) {
		return '';
	}

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
		$instance = $_GET['instance'];
		$json = $this->settings[$instance]->autocomplete();

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
     * Given SQL, fetch the features, set their popup contents and print the GeoJSON
     */
    function send_json($json){

        $post_type_info = get_post_type_object($post_type);

        foreach($json->features as &$feature){
            $permalink = get_permalink($ids[$feature->properties->cartodb_id]);

            $popup_contents = '<table class="leafletpopup">';
            $popup_contents .= '<tr><th colspan="2"><a href="' . $permalink . '">View ' .$post_type_info->labels->singular_name .'</a></tr>';
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

		foreach($this->mappings as $mapping){
			$instance = $this->mappings[$post->post_type]['instance'];
			add_meta_box(
				$instance->get_metabox_name(),
				$instance->get_name() . ' Connection',
				Array($instance,'make_meta_box')
			);
		}
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
        add_menu_page( 'SavvyMapper', 'SavvyMapper', 'manage_options', 'savvymapper', Array($this,'options_page'));
		add_submenu_page('savvymapper','Post Mapping','Post Mapping','manage_options','savvy_connections',Array($this,'connection_mapping_page'));
    }

	function connection_mapping_page() {
		$html = '<div class="wrap savvymapper_mapping_wrap">';
		$html .= '<h2>SavvyMapper Post Mapping</h2>';
		$html .= '<p>Configure mapping between your post types and your SavvyMapper connections.</p>';

		$html .= '<div id="savvymapper_mapping">';
		$settings_string = get_option( 'savvymapper_mapping', '{}' );
		$html .= '</div>';


        $post_types = get_post_types(Array('public' => true,));
        ksort($post_types);

		$post_type_list = Array();
		foreach($post_types as $post_type){
            $post_type_object = get_post_type_object($post_type);
			$post_type_list[$post_type] = $post_type_object->labels->singular_name;
        }

		$html .= print_r($post_type_list,TRUE);


		$settings_string = get_option( 'savvymapper_connections', '{}' );
		$settings = json_decode( $settings_string, TRUE );
		$connections_list = Array();
		foreach($settings['connections'] as $connection){
			$connections_list[] = $connection['connection_name'];
		}

		$html .= print_r($connections_list,TRUE);

		$html .= "<ol><li>List of existing connections, one per row, with an hr between each and a remove button on each one</li>";
		$html .= "<li>Last row is dropdown with post types / connections / Add button</li>";
		$html .= "<li>Add button does ajax call to get settings for specified connection (including defaults) and adds row</li>";
		$html .= "</ol>";

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

		$settings_string = get_option( 'savvymapper_connections', '{}' );
		$settings = json_decode( $settings_string, TRUE );
		foreach($settings['connections'] as $connection){
			$interface = $this->interface_classes[$connection['interface']];
			$html .= $this->_get_interface_options_form( $interface, $connection );
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

		$options = Array();
		foreach( $this->interfaces as $interface ) {
			$options[$this->get_name] = $interface->settings_init();
		}
		// Do settings save
    }

	function load_interfaces() {
		require_once( dirname( __FILE__ ) . '/savvyinterface.php' );
		$this->interface_classes = apply_filters( 'savvy_load_interfaces', $this->interface_classes);
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
	 */
	function _get_interface_options_form( $interface, $connection_details = Array() ){
		$connection_name = (isset($connection_details['connection_name']) ? $connection_details['connection_name'] : '');
		
		$html .= '<div class="instance-config">';
		$html .= '<h3><span class="remove-instance">(X)</span> ' . $interface->get_name() . ' Connection</h3>';
		$html .= '<label>Connection Name</label> <input type="text" data-name="connection_name" value="' . $connection_name . '"><br>' . "\n";
		$html .= '<input type="hidden" data-name="interface" value="' . $interface->get_type() . '">' . "\n";
		$html .= $interface->options_div($connection_details);
		$html .= '<hr>';
		$html .= '</div>';
		return $html;
	}

	function getting_started_callback(){
		return 'getting started callback';
	}

	function savvymapper_connections_callback(){
		$settings = get_option( 'savvymapper_connections', '{}' );
        print '<textarea name="savvymapper_connections" id="savvymapper_connections">' . $settings . '</textarea>';
	}

}
SavvyMapper::get_instance();

foreach( glob( dirname( __FILE__ ) . '/interfaces/*.php' ) as $interface ) {
	require_once( $interface );
}
