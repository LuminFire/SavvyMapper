/*
 Copyright (C) 2016 Cimbura.com

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.


 */
jQuery(document).ready(function(){

	// On the settings page get the fields when changing tables
	jQuery('#savvy_mapping_settings').on('change','select[data-name=cdb_table]',function(e){
		jQuery.get(ajaxurl, {
			'action'	: 'savvy_cartodb_get_fields',
			'table'		: jQuery(e.target).val()
		}).then(function(success){
			jQuery(e.target).parent().find('select[data-name=lookup_field]').html(success);
		});
	});

	// On individual post pages, get the map
	jQuery('.savvy_map_cartodb,.savvy_metabox_map_cartodb').each(function(){
		new SavvyCartoMap(this);
	});
});

var SavvyCartoMap = SavvyMap.extend({
	init: function(div,args){
		this._super(div,args);
		if( this.args.vizes instanceof Array ){
			for(var v = 0;v<this.args.vizes.length;v++){
				this.addVisualization(this.args.vizes[v]);
			}
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
