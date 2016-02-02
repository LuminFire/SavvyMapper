jQuery(document).ready(function(){

	// On the settings page get the fields when changing tables
	jQuery('#savvy_mapping_settings').on('change','select[data-name=cdb_table]',function(e){
		jQuery.get(ajaxurl, {
			'action'	: 'savvy_cartodb_get_fields',
			'table'		: jQuery(e.target).val()
		}).then(function(success){
			jQuery(e.target).parent().find('select[data-name=cdb_field]').html(success);
		});
	});

	// On individual post pages, get the map
	jQuery('.savvy_map_cartodb').each(function(){
		var mapdiv = jQuery(this);
		var mapconf = mapdiv.data('map');
		var newMap = new SavvyCartoMap(this,mapconf);
		SAVVY.add_map(newMap);
	});
});

var SavvyCartoMap = SavvyMap.extend({

	init: function(div,args){
		this._super(div,args);
		for(var v = 0;v<this.args.vizes.length;v++){
			this.addVisualization(this.args.vizes[v]);
		}
	},

	/**
	* Add a CartoDB visualization
	*/
	addVisualization: function(vis_url){
		this.layers[vis_url] = cartodb.createLayer(this.map,vis_url);
		return this.layers[vis_url].addTo(this.map);
	}
});
