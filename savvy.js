function SavvyMapper(){
	this._init();
};

SavvyMapper.prototype = {

	// Set up listeners
	_init: function(){
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
				oneconfig[ input.data('name') ] = input.val();
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
				oneconfig[ input.data('name') ] = input.val();
			});		
			config['mappings'].push(oneconfig);
		});
		jQuery('#savvymapper_mappings').val(JSON.stringify(config));
	}
};

jQuery(document).ready(function(){
	savvy = new SavvyMapper();
});
