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




TODO
----

 * Documentation

 * Needs to be good enough for users to get something done with just the plugin
 * And expose enough functionality for developers to really make them need it
 * Use L.noConflict 
 * Add [dm show="map" onarchive="hide"]
 * Add [dm show="map" viz=[list,of,viz,urls]]
 * Add [dm show="map" popup="false"]
 * Add [dm show="map" callback="function_name"]
 * Let user set map ID in DapperMapper() init function
 * Consider a singleton instead of prefixed functions
 * Let users set basemaps
 * Un-blogged points
 * Carto submission form shortcode
 * Fix missing ajaxurl when not logged in as admin


Usage
-----

### Shortcodes

#### Displaying CartoDB attributes

[dm attr=""]

##### Styling CartoDB attributes

Attributes printed with shortcodes will be wrapped with span with the class 'dapper-attr'.

```
    <span class="dapper-attr">the attribute value</span>
```

#### Displaying the current post's map

[dm map="true"]


### The DM Object

DapperMapper stores all of its properties and functions in a global ```DM``` object.

#### Public properties:

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


