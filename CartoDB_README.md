The CartoDB Interface
=====================

The CartoDB interface adds the following supported attributes to the [savvy show="map"] shortcode:

* vizes = ('',false|'url,url,url') 
    * Empty String ('') -- The default visualizations will be used
    * A comma separated string of CartoDB .viz URLs  -- These will be appended to the default visualizations list and shown on the map 
    * false -- Don't load the default visualizations on this map

Slight variation regarding popups:
    * NOTE: If you include a CartoDB visualization which includes a popup, that popup will still work, regardless of this setting
