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

We can't use noConflict until Leaflet.markercluster [Issue #387](https://github.com/Leaflet/Leaflet.markercluster/issues/387) is fixed.



TODO
----

### Pre-Release
 * Set up sample site
 * Sample maps
    * From various GIS sectors
        * http://www.esri.com/industries
    * Usings points, lines and polygons
    * With and without CartoDB viz layers
    * Tying in with CartoDB Case Studies
 * Wordpress page template with auto-posts by CartoDB attribute name
    * Data driven pages based on CartoDB data!
    * Will need a wide table for this to make it interesting
 * Show map using other Leaflet plugin

### Future enhancements
 * Set map ID to post ID or archive name 
 * Consider a singleton instead of prefixed functions
 * Show list of un-blogged points
 * Carto submission form shortcode
 * Triggers on map div for various events
    * Let users set basemaps
    * Let users format features

### Descriptive
 * Needs to be good enough for users to get something done with just the plugin
 * And expose enough functionality for developers to really make them need it


Usage
-----

### Shortcodes

#### Displaying CartoDB attributes

[dm attr="/attribute name/"]

##### Styling CartoDB attributes

Attributes printed with shortcodes will be wrapped with span with the class 'dapper-attr'.

```
    <span class="dapper-attr">the attribute value</span>
```

#### Displaying the current post's map

[dm show="map"]

The Map shortcode has the following optional attributes: 

* onarchive = (show|hide) 
    * show -- Show this map on the archive page
    * hide -- Hide this post's map when the current page is an archive page
* vizes = ('',false|'url,url,url') 
    * Empty String ('') -- The default visualizations will be used
    * A comma separated string of CartoDB .viz URLs  -- These will be appended to the default visualizations list and shown on the map 
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



### The DM Object

This plugin provides a DapperMapper javascript object and also creates a global ```DM``` object.

The DM object holds all the DapperMapper instances, as initialized by dm_init.js. When DapperMapper
creates a map, it adds a data-mapId attribute with the DapperMapper instance ID to the map element. 

So, DM.dmap0 corresponds to ```<div ... data-mapId="dmap0">```. 

This allows multiple maps to appear on the same page.

Each DM.dmap* object has the following properties

#### Public properties:

##### DapperMapper.id

The DapperMapper instance ID (dmap0, etc.). The instance will have an ID, even if no map is shown.

##### DapperMapper.id

The DapperMapper instance ID (dmap0, etc.). The instance will have an ID, even if no map is shown.

##### DapperMapper.layers

A dictionary of layers added to the map. The keys are layer names, the values are the layer objects. 

##### DapperMapper.data

Raw data we have fetched and want to save for later. On the archives page this will include the list of all points.

##### DapperMapper.map

The actual Leaflet.js map object itself. 

##### DapperMapper.archive_type

When an archive page map is generated this is set to the slug of the archive type. On other pages this is set to null.


#### Public methods:

##### Constructor 

No parameters are required.

    var dmap = new DapperMapper();

Currently the only argument is 'id' which will set the DapperMapper instance's ID

    var dmap = new DapperMapper({id: 'mainMap'});
    console.log(dmap.id);
    > mainMap

##### DapperMapper.addVisualization(vis_url)

Add a CartoDB visualization to the current map.

eg. DM.dmap0.addVisualization('https://stuporglue.cartodb.com/api/v2/viz/62546226-7429-11e5-988c-0e787de82d45/viz.json')


