/**
* This is the main SavvyMapper class. It handles core functionality and assets on all pages. 
*
* All classes extend a simple JS inheritance class based on an example by John Resig. 
*/

SavvyMapper = SavvyClass.extend({

	// Set up listeners
	init: function(){
		this.maps = {};

		// Set up listeners for options page
		var _this = this;
		jQuery('#savvyoptions').on('click','.remove-instance',function(e){
			jQuery(e.target).closest('.instance-config').remove();
			_this.update_connection_config();
		});

		jQuery('#savvy_mapping_settings').on('click','.remove-instance',function(e){
			jQuery(e.target).closest('.mapping-config').remove();
			_this.update_mapping_config();
		});

		jQuery('#savvyoptions').on('change',':input',this.update_connection_config);
		jQuery('#savvy_mapping_settings').on('change',':input',this.update_mapping_config);

		this.setup_listeners();
	},

	setup_listeners: function(){
		var _this = this;

		// Set up behavior for autocomplete fields in metaboxes
		jQuery('.savvy_lookup_ac').each(function(){
			var mapping_id = jQuery(this).closest('.savvy_metabox_wrapper').data('mapping_id');
			var acfield = jQuery(this);

			acfield.autoComplete({
				source: function( term, suggest ) {
					try { _this.auto.abort(); } catch(e){}

					_this.auto = jQuery.get(ajaxurl, {
						'action'		: 'savvy_autocomplete',
						'term'			: term,
						'mapping_id'	: mapping_id
					});

					_this.auto.then(function(success){
						suggest(success);
					});
				},
				onSelect: function( e, term, item ) {
					_this.replace_map_search_layer(acfield, term);
				}
			});

			acfield.on('change',function(e){
				_this.replace_map_search_layer(e.target, e.target.value);
			});

			// Don't submit when users hit enter
			jQuery(this).on('keypress',function(e){
				if( e.keyCode == 13 ) {
					return false;
				}
				console.log(e.keyCode);
			});
		});
	},

	// SETTINGS: Add a new API connection
	add_connection: function(button){
		jQuery.get(ajaxurl, {
			'action'	: 'savvy_get_interface_options_form',
			'interface' : jQuery(button).data('type')
		}).then(function(success){
			jQuery('#savvyoptions').append(success);
		});
	},

	// SETTINGS: Add a new mapping
	add_mapping: function() {
		var _this = this;
		var postType = jQuery('select[name=savvy_post_type]').val();	
		var connectionId = jQuery('select[name=savvy_connection_id]').val();	

		jQuery.get(ajaxurl, {
			'action'			: 'savvy_get_mapping_options_form',
			'post_type'			: postType,
			'post_label'		: jQuery('select[name=savvy_post_type] option[value=' + postType + ']').html(),
			'connection_id'		: connectionId,
			'connection_label'	: jQuery('select[name=savvy_connection_id] option[value=' + connectionId + ']').html()
		}).then(function(success){
			jQuery('#savvy_mapping_settings').append(success);
			_this.update_mapping_config();
		});
	},

	// SETTINGS: Update the connection config json string
	update_connection_config: function(){
		var config = {'connections': []};
		var oneconfig;
		jQuery('.instance-config').each(function(i,instance){
			oneconfig = {};
			jQuery(instance).find(':input').each(function(j,input){
				input = jQuery(input);
				if(input.attr('type') == 'checkbox'){
					oneconfig[ input.data('name') ] = (input.prop('checked') ? 1 : 0);
				}else{
					oneconfig[ input.data('name') ] = input.val();
				}
			});
			config['connections'].push(oneconfig);
		});
		jQuery('#savvymapper_connections').val(JSON.stringify(config));
	},

	// SETTINGS: Update the mapping config json string
	update_mapping_config: function(){
		var config = {'mappings': []};
		var oneconfig;
		jQuery('.mapping-config').each(function(i,mapping){
			oneconfig = {};
			jQuery(mapping).find(':input').each(function(j,input){
				input = jQuery(input);
				if(input.attr('type') == 'checkbox'){
					oneconfig[ input.data('name') ] = (input.prop('checked') ? 1 : 0);
				}else{
					oneconfig[ input.data('name') ] = input.val();
				}
			});		
			config['mappings'].push(oneconfig);
		});
		jQuery('#savvymapper_mappings').val(JSON.stringify(config));
	}, 

	// Add a map to this object
	add_map: function( newMap ){
		this.maps[ newMap.getId() ] = newMap;
	},

	getMapsByMeta: function(metaKey,metaValue) {
		metaValue = metaValue || false;
		var maps = {};
		var mval;

		for(var m in this.maps){
			mval = this.maps[m].meta[metaKey];	
			if(metaValue === false || mval == metaValue){
				if(maps[mval] === undefined) {
					maps[mval] = {};
				}
				maps[mval][this.maps[m].getId()] = this.maps[m];
			}
		}

		if(metaValue){
			return maps[metaValue];
		} else {
			return maps;
		}
	},

	// Replace the main layer
	replace_map_search_layer: function( target, newSearch ) {
		var map_id = jQuery(target).closest('.savvy_metabox_wrapper').find('.savvy_metabox_map_div').data('map').id;
		this.maps[ map_id ].set_search_layer( {'lookup_value': newSearch});
	}
});

jQuery(document).ready(function(){
	SAVVY = new SavvyMapper();
});
