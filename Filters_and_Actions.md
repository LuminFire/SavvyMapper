Filters and Actions
-------------------

SavvyMapper makes use of filters and actions so that you can
hook into and control the mapping process anywhere along the way.

These are still in early development, so if you feel that there's 
a filter or action missing, please let us know what you need.

### PHP Filters and Actions

 * savvymapper_geojson
    - $geojson
    - $mapping

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
    - $properties
    - $feature
    - $mapping

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
    - $html
    - $feature
    - $mapping

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



 * savvymapper_attr_values 
    - $allProps
    - $mapping
    - $attributeName

This lets you filter the list of found attribute values. The other parameters
are the current mapping and the requested attribute name.


 * savvymapper_attr_html
    - $finalHtml
    - $mapping
    - $attributeName

This lets you filter the final HTML generated for the list of attributes.
The mapping and attribute name are also passed in.


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
SavvyMapper instance. (eg. /this/ will refer to SAVVY). 

#### Mapping Specific actions and filters

In many cases you will only want to run a filter or action for a specific SavvyMapper Mapping. 

Many of the filters and some of the actions are also available in Mapping specific versions. To
use these you will need to know the mapping's slug. The slug is the slugified version of the mapping's
name and can be seen on the SavvyMapper Mapping options page.


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


##### SavvyMap Filters


 * savvymap_args
	- args -- The map arguments, as found in the map div's ```data-map``` attribute

Modify the arguments for an instance of SavvyMap. These args are used in the 
map creation. 

Called during initialization before the map has been created.


 * savvymap_meta
	- meta -- The map metadata, as found in the map div's ```data-mapmeta``` attribute.

Modify the meta values for an instance of SavvyMap. The meta values are mostly
for your use so you can determine which mapping is active.

Called during initialization before the map has been created.


 * savvymap_map_initialized
    -  map -- The Leaflet.js map instance

This is called after Leaflet is initialized but before any layers have been added.


 * savvymap_init_done

Called when the SavvyMap object initialization is done.


 * savvymap_basemap_url
 * savvymap_{mapping_slug}_basemap_url
    -  basemapurl -- The basemap URL for the L.TileLayer basemap

Allows you to modify the basemap url.

Called before creating the basemap. 

The basemap will be an instance of	L.TileLayer. If you want to provide your own 
basemap, return something falsey and use /savvymap_map_initialized/ to set up yours.


 * savvymap_basemap_config
 * savvymap_{mapping_slug}_basemap_config
    -  basemapconfig -- The config options passed in to L.TileLayer

Basemapconfig is an object of settings which will be passed to L.TileLayer

You would probably use this in conjunction with savvymap_basemap_url. 


 * savvymap_{layer_name}_bounds
	- bounds -- the bounds of the current layer
	- layer -- The Leaflet layer under consideration

Called when setting the initial bounds for the map. This allows you to override the 
bounds for a specific layer. For example if you want to give padding around a layer, or 
if you want to zoom in on the layer. 

The bounds of all layers are extended together, so if you're trying to set specific
bounds, use ```savvymap_map_bounds``` instead.


  * savvymap_map_bounds
    -  bounds -- The bounds that will be used by the map.

If you wish to change the initial map bounds, this is the place. 


 * savvymap_layer_features
 * savvymap_{mapping_slug}_layer_features
	- geojson -- A GeoJSON object
	
You can filter the GeoJSON objects before the L.GeoJSON layer is initialized
with this filter. 

Filtering server-side is probably more efficient, but you could do it here too.

	- geojson -- A GeoJSON object


 * savvymap_popup_contents
 * savvymap_{mapping_slug}_popup_contents
    -  popupcontents -- The current contents of the popup (a string of HTML)
    -  feature -- The feature this popup is for
    -  layer -- The layer object

The popup contents can be set or modified by JavaScript here. This is called
once per feature.

Note: This only fires if args.show_popups is true.


 * savvymap_feature_point
 * savvymap_{mapping_slug}_feature_point
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
 * savvymap_{mapping_slug}_feature_style
    -  thestyle -- The Leaflet style definition
    -  feature -- The feature the style is for

Set the style for Polygons, Lines, and circleMarker points.

The feature being styled is passed in. 


 * savvymap_layer_config
 * savvymap_{mapping_slug}_layer_config
    -  geojsonconfig -- The layer config passed to L.GeoJSON

If you want to modify or completely replace the config passed to L.geoJson for
the main map layer, you can do so here. Modifying this may prevent other filters
from being called.


#### Actions

##### SavvyMap Actions

 * savvymap_init_done

Called when the map init process is complete, including loading the main layer.


 * savvymap_view_changed

Called after Leaflet is initialized an any time a SavvyMap instance sets the bounds
or view.

 * savvymap_basemap_added
     - basemap -- The basemap layer
	 - map -- The map object

Called after the basemap has been added. The basemap is passed. The basemap layer and 
the Leaflet map object are passed as parameters.


 * savvymap_layer_added
 * savvymap_{mapping_slug}_layer_added
	 - theLayer -- The layer which was just added

Called after the main layer has been added. The main layer is passed as a parameter.


##### SavvyMapper Actions

 * savvymapper_setup_done

Called after the SAVVY object has finished initialization. 

Some initialization occurs inside a jQuery('document').ready(), so the SAVVY object
will not be available before this is called.


 * savvymapper_map_added
    -  newMap -- The new SavvyMap object

Called any time a new SavvyMap object is added to SAVVY.
