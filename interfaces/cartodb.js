jQuery(document).ready(function(){
	jQuery('#savvy_mapping_settings').on('change','select[data-name=cdb_table]',function(e){
		jQuery.get(ajaxurl, {
			'action'	: 'savvy_cartodb_get_fields',
			'table'		: jQuery(e.target).val()
		}).then(function(success){
			jQuery(e.target).parent().find('select[data-name=cdb_field]').html(success);
		});
	});
});
