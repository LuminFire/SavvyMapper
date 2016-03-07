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
/**
* This is the main SavvyMapper class. It handles core functionality and assets on all pages. 
*
* All classes extend a simple JS inheritance class based on an example by John Resig. 
*/

// Singleton
SAVVY = (function(){
	var SavvyMapper = SavvyClass.extend({
		// Set up listeners
		init: function(){

			this.maps = {};
			this.filters = {};
			this.actions = {};

			// Set up listeners for options page
			var _this = this;
			jQuery('document').ready( function(){
				_this._setup_listeners();
				_this._do_action( 'savvymapper_setup_done', _this );
			} );
		},

		/**
		 * Set up listeners and hooks to run when different elements are present
		 */
		_setup_listeners: function(){
			var _this = this;

			jQuery('#savvyconnectionoptions').on('click','.remove-instance',function(e){
				jQuery(e.target).closest('.instance-config').remove();
				_this._update_savvy_config();
			});

			jQuery('#savvy_mapping_settings').on('click','.remove-instance',function(e){
				jQuery(e.target).closest('.mapping-config').remove();
				_this._update_mapping_config();
			});

			jQuery('#savvysettings').on('change',':input',this._update_savvy_config);
			jQuery('#savvyconnectionoptions').on('change',':input',this._update_savvy_config);
			jQuery('#savvy_mapping_settings').on('change',':input',this._update_mapping_config);
			jQuery('#savvyclearcache').on('click',this._clear_cache);

			// Set up behavior for autocomplete fields in metaboxes
			jQuery('.savvy_lookup_ac').each( function(){
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
						_this._replace_map_search_layer(acfield, term);
					}
				});

				acfield.on('change',function(e){
					_this._replace_map_search_layer(e.target, e.target.value);
				});

				// Don't submit when users hit enter
				jQuery(this).on('keypress',function(e){
					if( e.keyCode == 13 ) {
						return false;
					}
				});
			} );
		},

		// SETTINGS: Add a new API connection
		_add_connection: function(button){
			jQuery.get(ajaxurl, {
				'action'	: 'savvy_get_interface_options_form',
				'interface' : jQuery(button).data('type')
			}).then(function(success){
				jQuery('#savvyconnectionoptions').append(success);
			});
		},

		// SETTINGS: Add a new mapping
		_add_mapping: function() {
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
				_this._update_mapping_config();
			});
		},

		// SETTINGS: Update the connection config json string
		_update_savvy_config: function(){
			var config = {'connections': []};

			jQuery('#savvysettings :input').each(function(j,input){
				input = jQuery(input);
				if( input.data('name') === undefined ) {
					return;
				}

				if(input.attr('type') == 'checkbox'){
					config[ input.data('name') ] = (input.prop('checked') ? 1 : 0);
				}else{
					config[ input.data('name') ] = input.val();
				}

			});

			var oneconfig;
			jQuery('.instance-config').each(function(i,instance){
				oneconfig = {};
				jQuery(instance).find(':input').each(function(j,input){
					input = jQuery(input);

					if( input.data('name') === undefined ) {
						return;
					}

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
		_update_mapping_config: function(){
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
			this._do_action('savvymapper_map_added', this, newMap);
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
		_replace_map_search_layer: function( target, newSearch ) {
			var map_id = jQuery(target).closest('.savvy_metabox_wrapper').find('.savvy_metabox_map_div').data('mapmeta').map_id;
			this.maps[ map_id ].set_search_layer( {'lookup_value': newSearch} );
		},

		// add a new filter callback
		add_filter: function( tag, callback, priority ){
			this._add_stuff( 'filters', tag, callback, priority);
		},

		// add a new action callback
		add_action: function( tag, callback, priority ) {
			this._add_stuff( 'actions', tag, callback, priority);
		},

		_add_stuff: function( type, tag, callback, priority ) {
			priority = priority || 10;
			if( this[type][tag] === undefined ){
				this[type][tag] = [];
			}
			this[type][tag].push({'callback':callback,'priority':priority});

			this[type][tag].sort(function(a,b){
				return (a.priority - b.priority);
			});
		},

		_do_action: function( tag ) {
			if (this.actions[tag] === undefined ){
				return;
			}
			
			var args = Array.prototype.slice.call(arguments);
			var fun;
			args.shift();
			var the_this = args.shift();
			for ( var f = 0; f < this.actions[tag].length; f++ ){
				fun = this.actions[tag][f].callback;
				fun.apply(the_this, args);
			}
		},

		_apply_filters: function( tag ) {
			var args = Array.prototype.slice.call(arguments);
			var fun;
			args.shift();
			var the_this = args.shift();
			var filteredval = args[0];

			if (this.filters[tag] === undefined ){
				return filteredval;
			}
			
			for ( var f = 0; f < this.filters[tag].length; f++ ){
				fun = this.filters[tag][f].callback;
				filteredval = fun.apply(the_this, args);
				args[0] = filteredval;
			}

			return filteredval;
		},

		_clear_cache: function() {
			jQuery.get(ajaxurl, {
				'action'		: 'savvy_clearcache'
			}).then(function(){
				alert("SavvyMapper cache cleared!");
			});
		}
	});
	return new SavvyMapper();
})();
