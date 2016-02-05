SavvyMapper is (partly) for Developers
======================================

The base functionality of SavvyMapper can be used by anyone,
but if you just wanted a simple map to show some static points
on, you'd probably be using one of the several fine Google Map 
plugins out there. 

SavvyMapper is meant to take care of the tedious work 
of associating GIS data with your WordPress posts so that you
can focus on the much more fun map maping part of your site!


Terminology
-----------

 * interface -- An interface is a class which knows how to talk to a GIS service. 
 * connection -- An interface which has been initialized with the credentials 
 or configuration needed to talk to the GIS service.
 * mapping -- The association between a WordPress post type and a named connection.


For example: 

SavvyMapper includes an interface for CartoDB.

When you enter your CartoDB credentials you can make a connection to the CartoDB service.

You can then associate a CartoDB table within that connection with a WordPress post type in a mapping.


Filters and Actions
-------------------

SavvyMapper makes use of filters and actions so that you can
hook into and control the mapping process anywhere along the way.

These are still in early development, so if you feel that there's 
a filter or action missing, please let us know what you need.

### PHP Filters and Actions

 * savvymapper_load_interfaces

This filter is called when SavvyMapper is ready to load interfaces. If you implement
a new interface you should add a filter which will initialize your interface and
append it to the list of interfaces before returning the array.

This filter passes a single argument, an array of instances of classes which 
implement SavvyInterface.


    add_filter( 'savvy_load_interfaces','load_savvy_carto_interface' );
    function load_savvy_carto_interface( $interfaces ) {
    	class SavvyCartoDB extends SavvyInterface {
    		... class definition here...
    	}
    
    	$int = new SavvyCartoDB();
    	$interfaces[ $int->get_type() ] = $int;
    
    	return $interfaces;
    }

 * savvymapper_geojson

This filter gives you final control over which features are sent to the user.

The first argument is the fetched $geojson FeatureCollection.

The second is the $mapping config which you can use to determine if you should act on $geojson.

Add or remove features to $geojson and then return it. 


    add_filter('savvymapper_geojson','remove_arctic_ocean',10 , 2);
    function remove_arctic_ocean($geojson, $mapping) {
    	if( $mapping['mapping_name'] != 'Sea Polygons' ) {
    		return $geojson;
    	}
    
    	foreach ( $geojson[ 'features' ] as $k => $feature ) {
    		if ( $feature['properties']['name'] == 'ARCTIC OCEAN' ) {
    			unset( $geojson[ 'features' ][ $k ] );
    		}
    	}
    
    	return $geojson;
    }


 * savvymapper_popup_fields

The default SavvyMapper popup is a table of the feature's properties. This filter 
gives you control over which properties are shown to the user. 

The first argument is an associative array. They keys are the labels and the values 
are the property values. Modify this array and return it. 

The other two arguments are the GeoJSON feature itself and the mapping object that
it came from. Since you may have many connections or mappings, you can use the 
mapping object to determine if you want to act on the current popup properties.


    add_filter( 'savvymapper_popup_fields', 'make_names_into_links', 10, 3 );

    function make_names_into_links( $properties, $feature, $mapping ) {

    	if( $mapping['mapping_name'] != 'Sea Polygons' ) {
    		return $properties; // Return $properties unmodified
    	}

    	$properties['Find on Google'] = '<a href="https://www.google.com/search?q=' . $properties['name'] . '" target="_blank">' . $properties['name'] . '</a>';

    	unset($properties['name']);

    	return $properties;
    }


 * savvymapper_popup_html

If you want to completely replace the popup with something other than a table, 
this is the place to do it. 

The first parameter here is the popup html which will be sent to the user. You 
can modify the HTML string or return some other html in its place. 

The other two arguments are the feature and the mapping object, just like the
savvymapper_popup_fields filter above.


    add_filter('savvymapper_popup_html','replace_popups',10 , 3);
    function replace_popups($html, $feature, $mapping) {
    	if( $mapping['mapping_name'] != 'Sea Polygons' ) {
    		return $html;
    	}
    
    	// Overwrite the $html
    	$html = '<div class="custom-popup">';
    	$html .= '<p>The ' . $feature['properties']['name'] . ' ocean is awesome!</p>';
    	$html .= '<p><input type="button" value="Like this!" onclick="dosomething()"></p>';
    	$html .= '</div>';
    
    	return $html;
    }

### JavaScript Filters and Actions


Styling Attributes and Maps
---------------------------

### CSS Classes
#### Attribute classes
#### Map Classes

### Style callbacks


Adding Support for Other Services
---------------------------------

### The SavvyInterface PHP abstract class

### The SavvyMap JavaScript parent class








### The SAVVY Object

This plugin provides a global ```SAVVY``` object which still needs to be documented here.

##### Styling attributes

Attributes printed with shortcodes will be wrapped with <span class="savvy-attr"> and
each set of attributes is wrapped in <span class="savvy-attrs">.

    <span class="savvy-attrs">
        <span class="savvy-attr">Feature #1 value</span>
        <span class="savvy-attr">Feature #2 value</span>
        <span class="savvy-attr">Feature #3 value</span>
    </span>





### PHP Filters


### JS Filters and Actions


this.args = this.savvy._apply_filters('savvymap_args',this, this.args);
this.meta = this.savvy._apply_filters('savvymap_meta',this, this.meta);
this.map = this.savvy._apply_filters('savvymap_map_initialized',this, this.map);
basemapurl = this.savvy._apply_filters( 'savvymap_basemap_url', this, basemapurl );
basemapconfig = this.savvy._apply_filters( 'savvymap_basemap_config', this, basemapconfig );
success = _this.savvy._apply_filters( 'savvymap_thegeom_features', _this, success );
popupcontents = _this.savvy._apply_filters( 'savvymap_popup_contents', _this, popupcontents, feature, layer );
pointrep = _this.savvy._apply_filters( 'savvymap_feature_point', _this, pointrep, feature, latlng );
thestyle = _this.savvy._apply_filters( 'savvymap_feature_style', _this, thestyle, feature );
geojsonconfig = _this.savvy._apply_filters( 'savvymap_thegeom_config', _this, geojsonconfig );
geombounds = _this.savvy._apply_filters( 'savvymap_thegeom_bounds', _this, geombounds, _this.layers.thegeom );
_this.savvy._do_action( 'savvymap_init_done', _this);
this.savvy._do_action( 'savvymap_view_changed', this );
this.savvy._do_action('savvymap_basemap_added',this,this.layers.basemap);
_this.savvy._do_action('savvymap_thegeom_added',_this,_this.thegeom);
_this.savvy._do_action( 'savvymap_view_changed', _this );
_this.savvy._do_action( 'savvymap_view_changed', _this );
_this._do_action( 'savvymapper_setup_done', _this );
this._do_action('savvymapper_map_added', this, newMap);
