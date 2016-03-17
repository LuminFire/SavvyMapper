Documentation
=============


What is SavvyMapper?
--------------------
SavvyMapper is a map plugin for GIS professionals, and for smart WordPress
developers who need access to real GIS services and the power of Leaflet.js.

SavvyMapper creates relationships between WordPress posts (or custom posts)
and queries against GIS data. 

It provides shortcodes for displaying maps and feature attributes, and 
gives you easy access to the map and layer objects so you can customize them
as needed. 

Setup and Configuration
-----------------------

 * Install this plugin the standard WordPress way
 * In the SavvyMapper settings add one or more connection type. You may need to enter 
 API keys or other configuration parameters for the service you're connecting to in
 this step. 
 * In the SavvyMapper Post Mapping settings set up an association between a post type
 and the connection you set up in the previous step. 
 * Now when you edit a post of that type, you will see a new Metabox where you can 
 associate the post you're editing with GIS data in the connected service. 
 * In the post body add one or more shortcodes as documented below. Save and then view
 your post. 

Following these steps should get you started. For additional functionality please see
the [documentation for developers](DEVELOPERS.md).


Shortcodes
----------

### Displaying attributes

[savvy attr="/attribute name/"]

The Attribute shortcode has the following optional attributes:

 * multiple = ('unique'|'all'|'first')
	* unique -- If multiple features match the query, show unique values for this attribute
	* all -- If multiple features match the query, show all values, even if they're repeated
	* first -- If multiple features match the query, show the first value. Note that the value may be empty.

### Displaying the current post's map

[savvy show="map"]

The Map shortcode has the following optional attributes: 

 * show_markers = (1|0)
    * 1 -- Show the default Leaflet.js marker (or line or polygon) for this post's associated feature
    * 0-- Make the feature invisible. 
 * show_popups = (1|0)
    * 1 -- Show the attributes popup when the feature is clicked
    * 0-- Don't show the attributes popup when the feature is clicked
 * zoom = (default|1-19ish)
    * default -- Fit the map bounds to the feature's bounding box
    * 1-19ish -- Set map zoom level manually. Most slippy basemaps support levels 1-19, but some go to 21 or beyond.
 * lat = ('default'|latitude), lng = ('default'|longitude)
    * Set the centerpoint of the map

[savvy show="map" show_markers="1" show_popups="0" zoom="6" lat="45" lng="-93"]

Filters, actions, etc.
----------------------

Please see the [developer notes](DEVELOPERS.md).


Known Bugs
----------

This plugin loads cartodb.js which includes its own copy of Leaflet. If other 
plugins are also loading leaflet, there will likely be conflicts. These conflicts
may manifest themselves in various ways including not finding loaded plugins.

Release Log
-----------

### 0.1.0 Initial Release
 * Support for CartoDB
 * Support for remote GeoJSON files
 * Support for caching
