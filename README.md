Documentation
=============

Setup and Configuration
-----------------------

 * Install this plugin the standard WordPress way

Known Bugs
----------

This plugin loads cartodb.js which includes its own copy of Leaflet. If other 
plugins are also loading leaflet, there will likely be conflicts. These conflicts
may manifest themselves in various ways including not finding loaded plugins.

-----

### Shortcodes

#### Displaying attributes

[savvy attr="/attribute name/"]

##### Styling attributes

Attributes printed with shortcodes will be wrapped with <span class="savvy-attr"> and
each set of attributes is wrapped in <span class="savvy-attrs">.

    <span class="savvy-attrs">
        <span class="savvy-attr">Feature #1 value</span>
        <span class="savvy-attr">Feature #2 value</span>
        <span class="savvy-attr">Feature #3 value</span>
    </span>

#### Displaying the current post's map

[savvy show="map"]

The Map shortcode has the following optional attributes: 

    * false -- Don't load the default visualizations on this map
* marker = (true|false)
    * true -- Show the default Leaflet.js marker (or line or polygon) for this post's associated feature
    * false -- Make the feature invisible. 
* popup = (true|false)
    * true -- Show the attributes popup when the feature is clicked
    * false -- Don't show the attributes popup when the feature is clicked
* zoom = (default|1-19ish)
    * default -- Fit the map bounds to the feature's bounding box
    * 1-19ish -- Set map zoom level manually. Most slippy basemaps support levels 1-19, but some go to 21 or beyond.
* lat = ('default'|latitude), lng = ('default'|longitude)
    * Set the centerpoint of the map



### The SAVVY Object

This plugin provides a global ```SAVVY``` object which still needs to be documented here.


### Filters and Actions

#### Filters

 * savvymapper_filter_popup_fields
	- Takes three parameters, an array of feature properties, the feature itself and the mapping object
	- Return the array of properties you want to display in the popup
 * savvymapper_filter_popup_html
	- Takes three parameters, the popup html, the feature and the mapping object
	- If you want to override the default popup table, here's the place

#### Actions

 * TODO: savvymapper_show

#### Displaying a map in a theme
