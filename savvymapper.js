/**
Simple JavaScript Inheritance
By John Resig http://ejohn.org/blog/simple-javascript-inheritance/
MIT Licensed.

This sets up a method this._super() which can call the parent method (if it exists) from inside the child method. 
*/
(function(){
	var initializing = false, fnTest = /xyz/.test(function(){xyz;}) ? /\b_super\b/ : /.*/;

	// The base SavvyClass implementation (does nothing)
	this.SavvyClass = function(){};
	this.SavvyClass.prototype = {
		_defined: function(string){
			try{
				var v = eval(string);
				return v !== undefined;
			}catch(err){
				return false;
			}
		}
	};

	// Create a new SavvyClass that inherits from this class
	SavvyClass.extend = function(prop) {
		var _super = this.prototype;

		// Instantiate a base class (but only create the instance,
			// don't run the init constructor)
			initializing = true;
			var prototype = new this();
			initializing = false;

			// Copy the properties over onto the new prototype
			for (var name in prop) {
				// Check if we're overwriting an existing function
				prototype[name] = typeof prop[name] == "function" &&
				typeof _super[name] == "function" && fnTest.test(prop[name]) ?
				(function(name, fn){
					return function() {
						var tmp = this._super;

						// Add a new ._super() method that is the same method
						// but on the super-class
						this._super = _super[name];

						// The method only need to be bound temporarily, so we
						// remove it when we're done executing
						var ret = fn.apply(this, arguments);        
						this._super = tmp;

						return ret;
					};
				})(name, prop[name]) :
				prop[name];
			}

			// The dummy class constructor
			function SavvyClass() {
				// All construction is actually done in the init method
				if ( !initializing && this.init ){
					this.init.apply(this, arguments);
				}
			}

			/**
			* Make this class appendable
			*
			* Any props appended will be added to the object's prototype so all instnaces will gain them
			* If we append a method with the same name as an existing method, the previous method will 
			* be available to the new method with the alias _super
			*/
			SavvyClass.append = function(prop) {
				var _super = this.prototype;
				initializing = true;
				var prototype = new this();
				initializing = false;
				for (var name in prop){
					if(typeof prop[name] == "function" && typeof _super[name] == "function" && fnTest.test(prop[name])){
						prototype[name] = (function(name,fn){
							return function(){
								var tmp = this._super;
								this._super = _super[name];
								var ret = fn.apply(this,arguments);
								this._super = tmp;
								return ret;
							};
						}
						)(name,prop[name]);
					}else{
						prototype[name] = prop[name];
					}
				}

				this.prototype = prototype;
			};

			// Populate our constructed prototype object
			SavvyClass.prototype = prototype;

			// Enforce the constructor to be what we expect
			SavvyClass.prototype.constructor = SavvyClass;

			// And make this class extendable
			SavvyClass.extend = arguments.callee;

			return SavvyClass;
	};
})();



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
	},

	// Add a new API connection
	add_connection: function(button){
		jQuery.get(ajaxurl, {
			'action'	: 'savvy_get_interface_options_form',
			'interface' : jQuery(button).data('type')
		}).then(function(success){
			jQuery('#savvyoptions').append(success);
		});
	},

	// Add a new mapping
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

	// Update the connection config json string
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

	// Update the mapping config json string
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

	add_map: function(newMap){
		this.maps[newMap.getId()] = newMap;
	},
});

jQuery(document).ready(function(){
	SAVVY = new SavvyMapper();
});
