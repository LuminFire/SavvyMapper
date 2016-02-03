jQuery(document).ready(function(){

	// On individual post pages, get the map
	jQuery('.savvy_map_geojsonurl,.savvy_metabox_map_geojsonurl').each(function(){
		var mapdiv = jQuery(this);
		var mapconf = mapdiv.data('map');
		var newMap = new SavvyGeoJsonUrlMap(this,mapconf);
		SAVVY.add_map(newMap);
	});
});

var SavvyGeoJsonUrlMap = SavvyMap.extend({});
