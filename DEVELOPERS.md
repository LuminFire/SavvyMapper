SavvyMapper is (partly) for Developers
======================================

The base functionality of SavvyMapper can be used by anyone,
but if you just wanted a simple map to show some static points
on, you'd probably be using one of the several fine Google Map 
plugins out there. 

SavvyMapper is meant to take care of the tedious work 
of associating GIS data with your WordPress posts so that you
can focus on the much more fun map maping part of your site!


Versioning
----------
The master branch is the main development branch. Releases are tagged along the way. 

Tags should correspond to the plugin version and the plugin version should use [Semantic Versioning](http://semver.org/)

Building
--------

Do you just want to build this thing? 

You'll need npm installed. Simply run: 

    npm run build

This will generate a zip file which you can install in WordPress.


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


Styling Attributes and Maps
---------------------------

Here are some key classes you might be interested in:

 * .savvy-attrs -- Wraps a collection of attributes. Includes a data-attr property with the name of the attribute.
 * .savvy-attr  -- An individual attribute, inside a .savvy-attrs.
 * .savvy_map_div -- The div that Leaflet is initialized in.
    - .savvy_map_cartodb -- It will also have a class like this, with the connection type at the end.
    - .savvy_map_sea-polygons -- It will also have a class like this with the mapping slug at the end.
 * table.savvymapper_popup tr.empty_row -- empty rows are present, but hidden, by default in popups.
 * .savvyampper_popup_wrapper -- This div wraps the popup contents. This has the max-height set to 250px by default.
 * table.savvymapper_popup  -- The default popup contents is a table. Its width is set to 250 by default.



Adding Support for Other Services
---------------------------------

Support for more interfaces should be fairly simple. There is a
PHP interface and a JavaScript interface which should be implemented
in order to achieve compatibility with the SavvyMapper plugin.

### The SavvyInterface PHP abstract class

savvyinterface.php defines an abstract class SavvyInterface.

The code is well documented and all of the abstract methods
are grouped together near the top of the file. 

See cartodb.php or geojson_url.php for example implementations.


### The SavvyMap JavaScript parent class


You should create a new JavaScript file for your interface
and in it you should:

 1. Create a new class using
 
		var myClass = SavvyMap.extend({..your optional functionality here...});

    This class should handle any special arguments and handling or behavior your interface expects. 

 For simple cases, it may simply extend  SavvyMap with an empty object.

 2. Use ```jQuery('document').ready(function(){});``` to initialize your
 object once the page is ready.

 
See cartodb.js and geojson_url.js for example implementations.


### Loading your instance

This is technically a filter, but it would only be used if you were adding new interfaces.


 * savvymapper_load_interfaces
    - $instances (Array)

This filter is called when SavvyMapper is ready to load interfaces. If you implement
a new interface you should add a filter which will initialize your interface and
append it to the list of interfaces before returning the array.

This filter passes a single argument, an array of instances of classes which 
implement SavvyInterface.


    add_filter( 'savvy_load_interfaces','load_savvy_carto_interface' );
    function load_savvy_carto_interface( $interfaces ) {

		// Class is only defined inside the function in 
		// case your theme or plugin is loaded before
		// SavvyInterface is available.

    	class SavvyCartoDB extends SavvyInterface {
    		... class definition here...
    	}
    
		// Initialize your interface
    	$int = new SavvyCartoDB();

		// Then add it to $interfaces with its name as the key
    	$interfaces[ $int->get_type() ] = $int;
    
		// Finally, return $interfaces.
    	return $interfaces;
    }


