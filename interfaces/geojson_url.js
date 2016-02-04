jQuery(document).ready(function(){

	// On individual post pages, get the map
	jQuery('.savvy_map_geojsonurl,.savvy_metabox_map_geojsonurl').each(function(){
		new SavvyGeoJsonUrlMap(this);
	});
});

var SavvyGeoJsonUrlMap = SavvyMap.extend({});
