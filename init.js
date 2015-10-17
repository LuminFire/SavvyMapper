// When the page loads look for any maps we should initialize
DM = new DapperMapper();
jQuery(document).ready(function(){

    // The map in the metabox on an edit page
    // Let the user select a feature
    var dmMapDiv = jQuery('.dm_metabox_map_div');
    if(dmMapDiv.length > 0){
        DM._makeCartoDBMetaboxMap(dmMapDiv);
    }

    // The map on a page (probably from a shortcode)
    // Show the single related feature
    var dmPageMapDiv = jQuery('.dm_page_map_div');
    if(dmPageMapDiv.length > 0){
        DM._makeCartoDBPageMap(dmPageMapDiv);
    }

    // The map on an archive page
    // Show all features
    var dmArchiveMapDiv = jQuery('.dm_archive_map');
    if(dmArchiveMapDiv.length > 0){
        DM._makeArchivePageMap(dmArchiveMapDiv);
    }

    jQuery('.dm_cdb_table_select').on('change',DM._setCDBColumnOpts);
});


