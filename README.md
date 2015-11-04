Documentation
=============


TODO
----

 * Documentation

 * Needs to be good enough for users to get something done with just the plugin
 * And expose enough functionality for developers to really make them need it

Setup and Configuration
-----------------------






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

##### DM.layers

A dictionary of layers added to the map. The keys are layer names, the values are the layer objects. 

##### DM.data

Raw data we have fetched and want to save for later. On the archives page this will include the list of all points.

##### DM.map

The actual Leaflet.js map object itself. 

##### DM.archive_type

When an archive page map is generated this is set to the slug of the archive type. On other pages this is set to null.


#### Public methods:

##### DM.addVisualization(vis_url)

Add a CartoDB visualization to the current map.

eg. DM.addVisualization('https://stuporglue.cartodb.com/api/v2/viz/62546226-7429-11e5-988c-0e787de82d45/viz.json')


