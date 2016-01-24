function SavvyMapper(){
	this._init();
};

SavvyMapper.prototype = {
	_init: function(){
		var _this = this;
		jQuery('#savvyoptions').on('click','.remove-instance',function(e){
			jQuery(e.target).closest('.instance-config').remove();
			_this.update_config();
		});

		jQuery('#savvyoptions').on('change',':input',this.update_config);
	},

	add_connection: function(button){
		jQuery.get(ajaxurl, {
			'action'	: 'savvy_get_interface_options_form',
			'interface' : jQuery(button).data('type')
		}).then(function(success){
			jQuery('#savvyoptions').append(success);
		});
	},

	update_config: function(){
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
	}
};

jQuery(document).ready(function(){
	savvy = new SavvyMapper();
});
