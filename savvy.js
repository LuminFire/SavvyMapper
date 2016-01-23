function SavvyMapper(){
	this._init();
};

SavvyMapper.prototype = {
	_init: function(){
	},

	add_connection: function(button){
		jQuery.get(ajaxurl, {
			'action'	: 'savvy_get_interface_options_form',
			'interface' : jQuery(button).data('type')
		}).then(function(success){
			jQuery('#savvyoptions').append(success);
		});
	}
};

jQuery(document).ready(function(){
	savvy = new SavvyMapper();
});
