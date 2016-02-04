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

#### Displaying CartoDB attributes

[savvy attr="/attribute name/"]

##### Styling CartoDB attributes

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
    * NOTE: If you include a CartoDB visualization which includes a popup, that popup will still work, regardless of this setting
* zoom = (default|1-19ish)
    * default -- Fit the map bounds to the feature's bounding box
    * 1-19ish -- Set map zoom level manually. Most slippy basemaps support levels 1-19, but some go to 21 or beyond.
* lat = ('default'|latitude), lng = ('default'|longitude)
    * Set the centerpoint of the map



### The SAVVY Object

This plugin provides a global ```SAVVY``` object which still needs to be documented here.
