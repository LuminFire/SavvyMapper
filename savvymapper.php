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
	private static $_instance = null;

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
		$this->settings = array();

		$this->get_mappings();
		$this->setup_actions();
		$this->setup_shortcodes();
	}

	/**
	 * Set up all the hooks and filters
	 */
	function setup_actions() {
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_filter( 'plugin_action_links', array( $this, 'plugin_add_settings_link' ), 10, 5 );

		add_action( 'wp_ajax_savvy_autocomplete', array( $this, 'ajax_autocomplete' ) );
		add_action( 'wp_ajax_nopriv_savvy_autocomplete', array( $this, 'ajax_autocomplete' ) );

		add_action( 'wp_ajax_savvy_get_interface_options_form', array( $this, 'get_interface_options_form' ) );
		add_action( 'wp_ajax_nopriv_savvy_get_interface_options_form', array( $this, 'get_interface_options_form' ) );

		add_action( 'wp_ajax_savvy_get_mapping_options_form', array( $this, 'get_mapping_options_form' ) );
		add_action( 'wp_ajax_nopriv_savvy_get_mapping_options_form', array( $this, 'get_mapping_options_form' ) );

		add_action( 'wp_ajax_savvy_get_geojson_for_post', array( $this, 'get_geojson_for_post' ) );
		add_action( 'wp_ajax_nopriv_savvy_get_geojson_for_post', array( $this, 'get_geojson_for_post' ) );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta' ) );
		add_action( 'loop_start', array( $this, 'make_archive_map' ) );

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );

		add_action( 'plugins_loaded', array( $this, 'load_interfaces' ) );
	}

	/**
	 * Set up the shortcodes
	 */
	function setup_shortcodes() {
		add_shortcode( 'savvy', array( $this, 'do_shortcodes' ) );
	}

	/**
	 * Load the needed javascript
	 */
	function load_scripts() {
		$plugin_dir_url = plugin_dir_url( __FILE__ );

		wp_enqueue_style( 'savvycss',$plugin_dir_url . 'assets/savvy.css' );

		wp_enqueue_style( 'savvyautocompletecss',$plugin_dir_url . 'assets/jquery.auto-complete.css' );
		wp_enqueue_script( 'savvyautocompletejs',$plugin_dir_url . 'assets/jquery.auto-complete.js',array( 'jquery' ) );

		wp_enqueue_script( 'savvyclassjs',$plugin_dir_url . 'assets/savvyclass.js' );

		wp_enqueue_script( 'savvymapper',$plugin_dir_url . 'assets/savvymapper.js',array( 'jquery', 'savvyclassjs' ) );
		wp_localize_script( 'savvymapper', 'ajaxurl', admin_url( 'admin-ajax.php' ) );

		wp_enqueue_script( 'savvymapjs',$plugin_dir_url . 'assets/savvymap.js',array( 'jquery', 'cartodbjs' ) );
	}

	/**
	 * Execute the shortcodes
	 *
	 * @param array  $attrs The shortcode attributes.
	 * @param string $contents What was between two sets of shortcode tags.
	 *
	 * @return HTML
	 */
	function do_shortcodes( $attrs, $contents ) {
		global $post;

		list($connection, $mapping, $current_settings) = $this->get_post_info_by_post_id( $post->ID );

		$attrs = $this->make_attrs( $attrs, $mapping );

		$html = '';

		if ( isset( $attrs['attr'] ) ) {

			// Attributes are simpler, so we'll just ask for fetures based on a search
			$json = $connection->get_attribute_shortcode_geojson( $attrs, $contents, $mapping, $current_settings );

			$json = apply_filters( 'savvymapper_geojson', $json, $mapping );

			// what happens if multiple features match a query
			$allProp = array();
			foreach ( $json['features'] as $feature ) {

				// multiple can be: empty (unique), all (show all) or first (show first)

				// We only collect non-empty properties to display
				$allProp[] = $feature['properties'][ $attrs['attr'] ];
				if ( $attrs[ 'multiple' ] == 'first' ) {
					// If we're asking for a single, just break
					break;
				}
			}

			// If we're unique, do that now
			if ( $attrs[ 'multiple' ] !== 'all' ) {
				$allProp = array_unique( $allProp );
			}

			// Remove empty values
			$allProp = array_filter( $allProp );

			sort( $allProp );

			$allProp = apply_filters( 'savvymapper_attr_values', $allProp, $mapping, $attrs['attr'] );
			
			if ( count( $allProp ) > 0 ) {
				$propHtml = implode( '</span><span class="savvy-attr">', $allProp );
				$propHtml = '<span class="savvy-attr">' . $propHtml . '</span>';
			}

			$finalHtml = '<span class="savvy-attrs" data-attr="' . $attrs['attr'] . '">' . $propHtml . '</span>';
			$finalHtml = apply_filters( 'savvymapper_attr_html', $finalHtml, $mapping, $attrs['attr'] );
			return $finalHtml;

		} else if ( isset( $attrs['show'] ) ) {

			// For maps, start with the default supported options, then
			// ask the connection to parse out any more details it
			// supports, then put it all in a data- attribute for
			// javascript to process
			if ( $attrs['show'] == 'map' ) {

				$mapSetup = $this->make_map_config( $attrs, $contents, $connection, $mapping );
				$mapMeta = $this->make_map_meta( $connection, $mapping, $post->ID );

				$classes = array(
					'savvy_map_div',
					'savvy_page_map_div',
					'savvy_map_' . $connection->get_type(),
					'savvy_map_' . $mapping[ 'mapping_slug' ],
					);

				$html .= "<div class='" . implode( ' ', $classes ) .  "' data-mapmeta='" . json_encode( $mapMeta ) . "' data-map='" . json_encode( $mapSetup ) . "'></div>";

				return $html;
			}
		}
	}

	/**
	 * Set up the default attributes for shortcodes.
	 *
	 * @param array $attrs The attrs passed in by a shortcode.
	 * @param array $mapping The current mapping.
	 *
	 * @return The array of attrs to actually use
	 */
	function make_attrs( $attrs, $mapping ) {
		// Order of presidence goes
		// 1. $attrs
		// 2. $mapping
		// 3. $defaults

		// Default shortcode option that all interfaces need to support
		$defaults = array(
			'attr' => null,
			'multiple' => 'unique',
			'show' => null,
			'onarchive' => 'show',
			'show_popups' => 1,
			'show_features' => 1,
			'zoom' => 'default',
			'lat' => 'default',
			'lng' => 'default',
		);
		
		// Get attributes out of $mapping
		$mapping_defaults = array_intersect_key( $mapping, $defaults );
		// Now override the hard-coded defaults with the mapping defaults
		$mapping_defaults = array_merge( $defaults, $mapping_defaults );

		// Finally, override that with the passed in $attrs since the shortcode take higest presidence
		$attrs = array_merge( $mapping_defaults, $attrs);

		return $attrs;
	}

	function make_map_config( $attrs, $contents, $connection, $mapping ) {
		global $post;

		$attrs = $this->make_attrs( $attrs, $mapping );

		$mapSetup = array(
			'show_popups'	=> $attrs['show_popups'],
			'show_features'	=> $attrs['show_features'],
			'zoom'			=> $attrs['zoom'],
			'lat'			=> $attrs['lat'],
			'lng'			=> $attrs['lng'],
			'post_id'		=> $post->ID,
			'mapping_id'	=> $mapping['mapping_id'],
		);

		$connectionMapSetup = $connection->get_map_shortcode_properties( $attrs, $contents, $mapping, $current_settings );
		$mapSetup = array_merge( $mapSetup, $connectionMapSetup );

		return $mapSetup;
	}

	/**
	 * Add a link to the settings page, just to be nice
	 *
	 * @param array  $actions Array of actions link.
	 * @param string $plugin_file Link to a plugin file, hopefully this one.
	 *
	 * @return The modified list of actions.
	 */
	function plugin_add_settings_link( $actions, $plugin_file ) {
		static $plugin;

		if ( ! isset( $plugin ) ) {
			$plugin = plugin_basename( __FILE__ );
		}

		if ( $plugin == $plugin_file ) {
			$settings = array( 'settings' => '<a href="options-general.php?page=savvymapper">' . __( 'Settings', 'General' ) . '</a>' );
			$actions = array_merge( $settings, $actions );

			// $site_link = array('support' => '<a href="http://thetechterminus.com" target="_blank">Support</a>');
			// $actions = array_merge($site_link, $actions);
		}

		return $actions;
	}

	/**
	 * Autocomplete!
	 */
	function ajax_autocomplete() {
		$mapping = $this->get_mappings( $_GET['mapping_id'] );
		$connection = $this->get_connections( $mapping['connection_id'] );
		$suggestions = $connection->autocomplete( $mapping, $_GET['term'] );

		header( 'Content-Type: application/json' );
		print json_encode( $suggestions );
		exit();
	}

	/**
	 * Make metaboxes!
	 */
	function add_meta_boxes() {
		global $post;

		$this->get_mappings();
		foreach ( $this->mappings as $mapping ) {
			if ( $mapping['post_type'] == $post->post_type ) {
				$connection = $this->connections[ $mapping['connection_id'] ];
				add_meta_box(
					$mappings['mapping_id'],
					$connection->get_connection_name() . ' : ' . $mapping['mapping_name'],
					array( $this,'make_meta_box' ),
					null,
					'advanced',
					'default',
					array( $connection,$mapping )
				);
			}
		}
	}

	function make_meta_box( $post, $metabox ) {
		$connection = $metabox['args'][0];
		$mapping = $metabox['args'][1];

		list($cur_connection, $cur_mapping, $current_settings ) = $this->get_post_info_by_post_id( $post->ID );
		$mapSetup = $this->make_map_config( array(), array(), $connection, $mapping );
		$mapMeta = $this->make_map_meta( $connection, $mapping, $post->ID );

		$html = "<div class='savvy_metabox_wrapper' data-mapping_id='" . $mapping['mapping_id'] . "'>";

		// Two columns
		// Col. 1: settings, help and hidden metadata
		$html .= '<div class="savvymapper_metabox_col">';

		$html .= '<label>Look up value <em>' . $mapping['lookup_field'] . '</em>: </label>';
		$html .= '<input class="savvy_lookup_ac" name="savvymapper_lookup_value" value="' . $current_settings['lookup_value'] . '">';
		$html .= '<br>' . "\n";

		$html .= "<input type='hidden' name='savvyampper_mapping_id' value='" . $mapping[ 'mapping_id' ] . "'>";
		$html .= $connection->extra_metabox_fields( $post, $mapping, $current_settings );

		ob_start();
		wp_nonce_field( 'savvymapper_meta_box', 'savvymapper_meta_box_nonce' );
		$html .= ob_get_clean();

		$html .= '<hr>';
		$lookup_fields = $connection->get_attribute_names( $mapping );

		$html .= '<h3>Shortcode Cheat Sheet</h3>';
		$html .= '<p>Coming Soon</p>';

		$html .= '</div>';

		// Col. 2: map div
		$html .= '<div class="savvymapper_metabox_col">';

		$classes = array(
			'savvy_metabox_map_div',
		   	'savvy_metabox_map_' . $connection->get_type(),
			'savvy_map_' . $mapping['mapping_slug'],
			);

		$html .= "<div class='" . implode( ' ', $classes ) .  "' data-mapmeta='" . json_encode( $mapMeta ) . "'  data-map='" . json_encode( $mapSetup ) . "'></div>";
		$html .= '</div>';

		$html .= '</div>';

		print $html;
	}


	/**
	 * Save the posted metadata for the current post
	 *
	 * @param $post_id The post ID for this meta data
	 */
	function save_meta( $post_id ) {
		// Check if our nonce is set.
		if ( ! isset( $_POST['savvymapper_meta_box_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['savvymapper_meta_box_nonce'],'savvymapper_meta_box' ) ) {
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

		// Ask the specific connection to capture anything else
		list($connection, $mapping, $current_settings) = $this->get_post_info_by_post_id( $post_id );

		if ( empty( $connection ) ) {
			$mapping = $this->get_mappings( $_POST[ 'savvyampper_mapping_id' ] );
			$connection = $this->get_connections( $mapping[ 'connection_id' ] );
			$current_settings = array();
		}

		// Capture common fields for all interfaces
		$settings = array(
			'mapping_id' => $mapping['mapping_id'],
			'lookup_value' => sanitize_text_field( $_POST['savvymapper_lookup_value'] ),
		);

		$connectionMeta = $connection->save_meta( $post_id, $mapping );

		// Merge them
		$settings = array_merge( $settings, $connectionMeta );

		// serialize settings and update post meta
		update_post_meta( $post_id,'savvymapper_post_meta',json_encode( $settings ) );
	}


	/**
	 * Make the map for the archive, if the settings say so
	 */
	function make_archive_map( $query ) {
		if ( ! is_archive() ) {
			return;
		}

		if ( $query->is_main_query() ) {
			$post_type = get_post_type();
			foreach ( $this->mappings as $mapping_type => $mapping ) {
				if ( $mapping_type === $post_type ) {
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
			array( $this,'options_page' ) // function to show page contents
			// icon url
			// position
		);

		add_submenu_page(
			'savvymapper',	// parent_slug
			'Post Mapping', // page title
			'Post Mapping', // menu title
			'manage_options', // capability
			'savvy_connections', // menu slug
			array( $this,'connection_mapping_page' ) // function to get output contents
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
		$post_types = get_post_types( array( 'public' => true ) );
		ksort( $post_types );
		$post_type_options = array();
		foreach ( $post_types as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			// $post_type_list[$post_type] = $post_type_object->labels->singular_name;
			$post_type_options[] = '<option value="' . $post_type . '">' . $post_type_object->labels->name . '</option>';
		}

		$connection_options = array();
		$connections = $this->get_connections();
		foreach ( $connections as $connection ) {
			$connection_options[] = '<option value="' . $connection->get_id() . '">' . $connection->get_connection_name() . '</option>';
		}

		// Show all the settings
		$mapping = $this->get_mappings();
		$html .= '<div id="savvy_mapping_settings">';
		foreach ( $mapping as $one_mapping ) {
			$html .= $this->_get_mapping_options_form( $one_mapping );
		}
		$html .= '<br></div>';

		// Show the form to add new mappings
		$html .= '<div id="savvy_mapping_form">';
		$html .= '<select name="savvy_post_type">' . implode( "\n", $post_type_options ) . '</select> ';
		$html .= '<select name="savvy_connection_id">' . implode( "\n", $connection_options ) . '</select> ';
		$html .= '<input type="button" onclick="SAVVY._add_mapping(this);" value="Add Mapping">';
		$html .= '</div>';

		// Save options form
		$html .= '<form method="post" action="options.php">';

		ob_start();
		settings_fields( 'savvymapper_mapping_page' );
		do_settings_sections( 'savvymapper_mapping_page' );
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
		foreach ( $this->interface_classes as $interface ) {
			$html .= '<input type="button" onclick="SAVVY._add_connection(this);" data-type="' . $interface->get_type() . '" value="Add ' . $interface->get_name() . ' Connection"> ';
		}
		$html .= '<hr>';

		$html .= '<div id="savvyoptions">';

		$connections = $this->get_connections();
		foreach ( $connections as $connection ) {
			$html .= $this->_get_interface_options_form( $connection );
		}

		$html .= '</div>';

		$html .= '<form method="post" action="options.php">';

		ob_start();
		settings_fields( 'savvymapper_plugin_page' );
		do_settings_sections( 'savvymapper_plugin_page' );
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
			__return_false,			// callback
			'savvymapper_plugin_page'						// page
		);
		add_settings_field(
			'savvymapper_connections',							// id
			__( 'SavvyMapper Connections', 'wordpress' ),		// title
			array( $this,'savvymapper_connections_callback' ),	// callback
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
			__return_false,			// callback
			'savvymapper_mapping_page'							// page
		);
		add_settings_field(
			'savvymapper_mappings',							// id
			__( 'SavvyMapper Mappings', 'wordpress' ),		// title
			array( $this,'savvymapper_mapping_callback' ),	// callback
			'savvymapper_mapping_page',						// page
			'savvymapper_mapping_page_section',				// section
			array()											// args
		);
	}

	function load_interfaces() {
		require_once( dirname( __FILE__ ) . '/savvyinterface.php' );
		$this->interface_classes = apply_filters( 'savvymapper_load_interfaces', $this->interface_classes );

		// Now that we have all our interfaces we can make our connections
		$this->get_connections();
	}


	/**
	 * This is called by ajax to make new boxes, and by options page to load existing options
	 */
	function get_interface_options_form() {
		$interface_type = $_GET['interface'];
		$interface = $this->interface_classes[ $interface_type ];
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
	function _get_interface_options_form( $interface ) {
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
	function get_mapping_options_form() {
		$connection_id = $_GET['connection_id'];

		$mapping = array(
			'post_type' => $_GET['post_type'],
			'connection_id' => $_GET['connection_id'],
		);

		print $this->_get_mapping_options_form( $mapping );
		exit();
	}

	function _get_mapping_options_form( $mapping ) {
		$postType = $mapping['post_type'];
		$post_type_info = get_post_type_object( $postType );

		$connections = $this->get_connections();
		$connection = $connections[ $mapping['connection_id'] ];

		$mapping_id = (empty( $mapping['mapping_id'] ) ? time() : $mapping['mapping_id']);

		$html = '<div class="mapping-config">';
		$html .= '<h3><span class="remove-instance">(X)</span> ' . $post_type_info->labels->name . ' => ' . $connection->get_connection_name() . '</h3>';
		$html .= '<label>Mapping Name</label> <input type="text" data-name="mapping_name" value="' . $mapping['mapping_name'] . '"><br>' . "\n";
		$html .= '<input type="hidden" data-name="connection_id" value="' . $connection->get_id() . '">';
		$html .= '<input type="hidden" data-name="mapping_id" value="' . $mapping_id . '">';
		$html .= '<input type="hidden" data-name="post_type" value="' . $mapping['post_type'] . '">';
		$html .= $connection->mapping_div( $mapping );

		$marker_checked = ( !isset( $mapping[ 'show_features' ] ) || $mapping[ 'show_features' ]  != 0  ? 'checked="checked"' : '' );
		$html .= '<label>Show features</label>: <input type="checkbox" data-name="show_features" value="1" ' . $marker_checked . '><br>';

		$popups_checked = ( !isset( $mapping[ 'show_popups' ] ) || $mapping[ 'show_popups' ] != 0 ? 'checked="checked"' : '' );
		$html .= '<label>Show popups</label>: <input type="checkbox" data-name="show_popups" value="1" ' . $popups_checked . '>';

		$html .= '<hr>';
		$html .= '</div>';
		return $html;
	}

	function savvymapper_connections_callback() {
		$settings = get_option( 'savvymapper_connections', '{}' );
		print '<textarea name="savvymapper_connections" id="savvymapper_connections">' . $settings . '</textarea>';
	}

	function savvymapper_mapping_callback() {
		$settings = get_option( 'savvymapper_mappings', "{'mapping':[]}" );
		print '<textarea name="savvymapper_mappings" id="savvymapper_mappings">' . $settings . '</textarea>';
	}

	/**
	 * Get the known connections, requesting them from the database, if needed
	 *
	 * A connection will have at least:
	 * {'interface': $interface_type, '_id': $the_id}
	 *
	 * @return $this->connections, an array of connections
	 */
	function get_connections( $connection_id = false ) {
		if ( isset( $this->connections ) ) {

			if ( $connection_id ) {
				return $this->connections[ $connection_id ];
			}

			return $this->connections;
		}

		$settings_connections_string = get_option( 'savvymapper_connections', '{}' );
		$settings = json_decode( $settings_connections_string, true );
		$connections_list = array();
		foreach ( $settings['connections'] as $connection ) {
			$interfaceClass = get_class( $this->interface_classes[ $connection['interface'] ] );
			$connections_list[ $connection['_id'] ] = new $interfaceClass( $connection );
		}

		$this->connections = $connections_list;

		if ( $connection_id ) {
			return $this->connections[ $connection_id ];
		}
		return $this->connections;
	}

	/**
	 * Get the known mappings, requesting them from the database, if needed
	 *
	 * @return $this->mappings, an array of mappings
	 */
	function get_mappings( $mapping_id = false ) {
		if ( isset( $this->mappings ) ) {

			if ( $mapping_id ) {
				return $this->mappings[ $mapping_id ];
			}

			return $this->mappings;
		}

		$mapping_string = get_option( 'savvymapper_mappings', "{'mapping':[]}" );
		$mapping = json_decode( $mapping_string, true );

		$this->mappings = array();
		foreach ( $mapping[ 'mappings' ] as $mapping ) {
			$mapping[ 'mapping_slug' ] = sanitize_title( $mapping[ 'mapping_name' ] );
			$this->mappings[ $mapping[ 'mapping_id' ] ] = $mapping;
		}

		if ( $mapping_id ) {
			return $this->mappings[ $mapping_id ];
		}

		return $this->mappings;
	}

	/**
	 * Get the GeoJson for a given post and mapping
	 */
	function get_geojson_for_post() {
		$postId = $_GET['post_id'];
		list($connection, $mapping, $current_settings) = $this->get_post_info_by_post_id( $postId );

		if( empty( $connection ) ) {
			$mapping = $this->get_mappings( $_GET[ 'mapping_id' ] );
			$connection = $this->get_connections( $mapping[ 'connection_id' ] );
			$current_settings = array();
		}

		$overrides = $_GET['overrides'];
		if ( empty( $overrides ) ) {
			$overrides = array();
		}

		$current_settings = array_merge( $current_settings, $overrides );

		$json = $connection->_get_geojson_for_post( $mapping, $current_settings );

		$json = apply_filters( 'savvymapper_geojson', $json, $mapping );

		// Leaflet doesn't like it if there's no zero index
		$json['features'] = array_values($json['features']);

		header( 'Content-Type: application/json' );
		print json_encode( $json );
		exit();
	}

	/**
	 * A convenience function used to find the connection, mapping and current_settings for 
	 * the specified post ID.
	 *
	 * @param event_id $post_id The post ID
	 *
	 * @return An array with $connection, $mapping and $current_settings
	 *
	 * @note In the event that the current post has no connection/mapping/settings (such as when a post is
	 * first created or if a mapping is added to a post type after a post has been created)
	 * then your function will need to handle the null/null/array() that's returned
	 * and instantiante or find the connection info some other way.
	 */
	function get_post_info_by_post_id( $post_id ) {
		// Get the connection and mapping objects for the current post
		$current_settings_str = get_post_meta( $post_id, 'savvymapper_post_meta', true );

		if ( empty( $current_settings_str ) ) {
			return array( null, null, array() );
		}

		$current_settings = json_decode( $current_settings_str, true );
		$mapping = $this->get_mappings( $current_settings[ 'mapping_id' ] );
		$connection = $this->get_connections( $mapping[ 'connection_id' ] );

		return array( $connection, $mapping, $current_settings );
	}

	/**
	 * Make the metadata for a map which JS will use to help make
	 * finding a map instance more reliable.
	 *
	 * @param array $connection The connection.
	 * @param array $mapping The mapping. 
	 *
	 * @return array
	 */
	function make_map_meta( $connection, $mapping, $postId, $archiveId = NULL ) {
		$meta = array(
				'connection_type'	=> $connection->get_type(),
				'connection_name'	=> $connection->config[ 'connection_name' ],
				'connection_id'		=> $mapping[ 'connection_id' ],
				'mapping_slug'		=> $mapping[ 'mapping_slug' ],
				'mapping_id'		=> $mapping['mapping_id'],
				'post_id'			=> $postId,
				'archive_id'		=> $archiveId,
				'map_id'			=> $mapping[ 'connection_id' ] . '_' . $mapping['mapping_id'] . '_' . ( !empty( $postId ) ? $postId : ( !empty( $archiveId ) ? $archiveId : '' ) ),
			);
		return $meta;
	}
}
SavvyMapper::get_instance();

// Load our default supported interfaces
foreach ( glob( dirname( __FILE__ ) . '/interfaces/*.php' ) as $interface ) {
	require_once( $interface );
}
