jQuery(document).ready(function(){

	// On individual post pages, get the map
	jQuery('.savvy_map_sample,.savvy_metabox_map_sample').each(function(){
		var mapdiv = jQuery(this);
		var mapconf = mapdiv.data('map');
		var newMap = new SavvySampleMap(this,mapconf);
		SAVVY.add_map(newMap);
	});
});

var SavvySampleMap = SavvyMap.extend({});
