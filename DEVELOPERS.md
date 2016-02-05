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

The ```SAVVY``` object is the singleton instance the SavvyMapper class.

It has functions /add_filter/ and /add_action/ which work pretty much
the same way that the WordPress PHP functions do. 

    SAVVY.add_filter('tag_name',callback_function,priority);
    SAVVY.add_action('tag_name',callback_function,priority);

The main difference is due to how JavaScript's execution context works,
which changes what /this/ will refer to. They also don't worry about
how many variables to pass to the callback function since passing too 
many or too few arguments won't trigger an error in JavaScript.

Action and Filter tag names start with either /savvymap/ or /savvymapper/.

Tag names starting with /savvymap/ will be run in the context of the 
SavvyMap instance they correspond with. (eg. /this/ will refer to 
the instance of SavvyCartoMap, which extends SavvyMap).

Tag names starting with /savvymapper/ will be run in the context of the
SavvyMapper instance. (eg. /this/ will rever to SAVVY). 


#### Filters

Example: 

	// Modify the default line and polygon feature styles
    SAVVY.add_filter('savvymap_feature_style',function(thestyle, feature){
    	thestyle = {
    		"color": "#ff7800",
    		"weight": 2,
    		"opacity": 0.65
    	};
    	return thestyle;
    },99);

I won't give an example of how to use all of these, just a brief description
of what they're for and their arguments. 



 * savvymap_args
	- args

Modify the arguments for an instance of SavvyMap. These args are used in the 
map creation. 

Called during initialization before the map has been created.



 * savvymap_meta
	- meta

Modify the meta values for an instance of SavvyMap. The meta values are mostly
for your use so you can determine which mapping is active.

Called during initialization before the map has been created.



 * savvymap_map_initialized
    -  map

The ```map``` parameter is the instance of Leaflet for this instance of SavvyMap.

This is called after Leaflet is initialized but before any layers have been added.



 * savvymap_basemap_url
    -  basemapurl

Allows you to modify the basemap url.

Called before creating the basemap. 

The basemap will be an instance of	L.TileLayer. If you want to provide your own 
basemap, return something falsey and use /savvymap_map_initialized/ to set up yours.



 * savvymap_basemap_config
    -  basemapconfig

Basemapconfig is an object of settings which will be passed to L.TileLayer

You would probably use this in conjunction with savvymap_basemap_url. 



 * savvymap_thegeom_features
	- features

The geojson features fetched from the connection before being used to create 
the L.geojson layer. 

Filtering server-side is probably more efficient, but you could do it here too.



 * savvymap_popup_contents
    -  popupcontents
    -  feature
    -  layer

The popup contents can be set or modified by JavaScript here. 

The feature and layer that the popup comes from are also passed in as arguments.

Note: This only fires if args.show_popups is true.



 * savvymap_feature_point
    -  pointrep 
    -  feature
    -  latlng

If you want to supply your own point representation, instead of the default
Leaflet marker, use this feature and return whatever you want. 

pointrep will be null by default, but could contain a value if you have
multiple filters for this tag.

If a non-null value is returned by this filter, the default point representation
will be used (either a transparent L.circleMarker or a L.marker depending on 
if arg.show_features is true or false).



 * savvymap_feature_style
    -  thestyle
    -  feature

Set the style for Polygons, Lines, and circleMarker points.

The feature being styled is passed in. 



 * savvymap_thegeom_config
    -  geojsonconfig

If you want to modify or completely replace the config passed to L.geoJson for
the main map layer, you can do so here. Modifying this may prevent other filters
from being called.


 * savvymap_thegeom_bounds
    -  geombounds
    -  thegeom

map.fitBounds is called after the main layer is loaded. If you want to change
what those bounds are, you can modify the bounds here. 

The main layer is passed as a second parameter.


#### Actions

 * savvymap_init_done

Called when the map init process is complete, including loading the main layer.
 


 * savvymap_view_changed

Called after Leaflet is initialized an any time a SavvyMap instance sets the bounds
or view.



 * savvymap_basemap_added
     - basemap
	 - map

Called after the basemap has been added. The basemap is passed. The basemap layer and 
the Leaflet map object are passed as parameters.



 * savvymap_thegeom_added
     - thegeom

Called after the main layer has been added. The main layer is passed as a parameter.



 * savvymapper_setup_done

Called after the SAVVY object has finished initialization. 

Some initialization occurs inside a jQuery('document').ready(), so the SAVVY object
will be available before this is called.


 * savvymapper_map_added
    -  newMap

Called any time a new map is added to SAVVY.



Styling Attributes and Maps
---------------------------

### CSS Classes
#### Attribute classes
#### Map Classes



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

