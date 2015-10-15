/*
Our DapperMapper object
*/
var DM = {
    map: null, // The leaflet map object
    layers: {}, // A dict of layers we initialize so that people can style and interact with them
    data: {}, // Raw data we want to save for later use, saved here so other scan interact with it
    meta: {} // Meta info we use internally. Not really meant for others to use, but we'll put it here to reduce clutter
};

// When the page loads look for any maps we should initialize
jQuery(document).ready(function(){

    // The map in the metabox on an edit page
    // Let the user select a feature
    var dmMapDiv = jQuery('.dm_metabox_map_div');
    if(dmMapDiv.length > 0){
        makeCartoDBMetaboxMap(dmMapDiv);
    }

    // The map on a page (probably from a shortcode)
    // Show the single related feature
    var dmPageMapDiv = jQuery('.dm_page_map_div');
    if(dmPageMapDiv.length > 0){
        makeCartoDBPageMap(dmPageMapDiv);
    }

    // The map on an archive page
    // Show all features
    var dmArchiveMapDiv = jQuery('.dm_archive_map');
    if(dmArchiveMapDiv.length > 0){
        makeArchivePageMap(dmArchiveMapDiv);
    }

    jQuery('.dm_cdb_table_select').on('change',set_cdb_column_opts);
});

/*
 * Set up the map for the metabox
 * Should show all elements, with clustering if we have more than 50 elements
 *
 * Also sets up other metabox interactions
 */
function makeCartoDBMetaboxMap(elem){
    var lat = 0;
    var lng = 0;
    var zoom = 1;

    DM.map = L.map(elem[0]).setView([lat,lng],zoom); 
    DM.meta.table = jQuery('.db_lookupbox').data('table');
    DM.meta.lookup = jQuery('.db_lookupbox').data('lookup');

    setupBasemap().addTo(DM.map);
    DM.layers.allpoints = L.geoJson(null,{
            onEachFeature: function (feature, layer) {
                feature.label = feature.properties[DM.meta.lookup];
                feature.value = feature.properties[DM.meta.lookup];
                layer.on('click',function(e){
                    var feature = e.target.feature;
                    jQuery('input[name=cartodb_lookup_value]').val(feature.properties.cartodb_id);
                    jQuery('input[name=cartodb_lookup_label]').val(feature.properties[DM.meta.lookup]);
                    setMetaboxLayerVisibility(feature);
                    setLookupMeta(feature);
                });
            }
        });
    DM.layers.cluster = L.markerClusterGroup().addTo(DM.map);


    // Fetch all the points from carto and show them on the map, possibly clustered
    jQuery.getJSON(ajaxurl,{
        'action' : 'carto_ajax',
        'table' : DM.meta.table, 
        'lookup' : DM.meta.lookup,
        'cartodb_id' : jQuery('input[name=cartodb_lookup_value]').val()
    }).then(function(success){
        DM.data.origpoints = success;
        setMetaboxLayerVisibility(DM.data.origpoints);
    });

    // On the metabox page set a listener on the lookup input field for when we have <2 chars
    jQuery('.db_lookup_ac').on('keyup blur',function(e){
        if(jQuery('.db_lookup_ac').val().length < 2){
            if(DM.data.origpoints.features.length <= 1){
                // Fetch all the points from carto and show them on the map, possibly clustered
                jQuery.getJSON(ajaxurl,{
                    'action' : 'carto_ajax',
                    'table' : DM.meta.table, 
                    'lookup' : DM.meta.lookup,
                }).then(function(success){
                    DM.data.origpoints = success;
                    setMetaboxLayerVisibility(DM.data.origpoints);
                });
            }else{
                setMetaboxLayerVisibility();
            }
        }
    });

    // If we have 2+ chars then we fire off autocomplete
    jQuery('.db_lookup_ac').autocomplete({
        source: function(request, response){

            request = jQuery.extend(request,{
                'action' : 'carto_ajax',
                'table' : DM.meta.table, 
                'lookup' : DM.meta.lookup
            });

            var localresponse = response;

            jQuery.getJSON(ajaxurl,request).then(function(success){
                setMetaboxLayerVisibility(success);

                var suggestions = success.features;

                for(var i = 0;i<suggestions.length;i++){
                    suggestions[i]['label'] = suggestions[i].properties[DM.meta.lookup];
                    suggestions[i]['value'] = suggestions[i].properties[DM.meta.lookup];
                }

                localresponse(suggestions);
            },function(failure){
                localresponse();
            });

        },
        minLength: 2,
        select: function( event, ui ) {
            setMetaboxLayerVisibility(ui.item);
        }
    });
}

/*
 * Use the properties from a feature and display them for the user
 */
function setLookupMeta(feature){
    var html = '<ul>';
    var props = feature.properties;
    for(var k in props){
        html += '<li><strong>' + k + '</strong> - ' + props[k] + '</li>';
    }
    html += '</ul>';

    jQuery('.dm_lookup_meta').html(html);
}

function makeCartoDBPageMap(elem){
    var lat = 0;
    var lng = 0;
    var zoom = 1;

    DM.map = L.map(elem[0]).setView([lat,lng],zoom); 
    setupBasemap().addTo(DM.map);

    var g = L.geoJson(dm_singleFeature,{
        onEachFeature: function (feature, layer) {
            layer.bindPopup(feature.popup_contents);
        }
    }).addTo(DM.map);
    DM.map.fitBounds(g.getBounds());
}

function makeArchivePageMap(elem){
    var lat = 0;
    var lng = 0;
    var zoom = 1;

    DM.map = L.map(elem[0]).setView([lat,lng],zoom); 
    setupBasemap().addTo(DM.map);

    jQuery.getJSON(ajaxurl,{
        'action': 'carto_archive',
        'table' : elem.data('table'),
        'post_type' : elem.data('post_type'),
    }).then(function(success){
        DM.layers.allpoints = L.geoJson(success,{
            onEachFeature: function (feature, layer) {
                layer.bindPopup(feature.popup_contents);
            }
        }).addTo(DM.map);

        DM.map.fitBounds(DM.layers.allpoints.getBounds());
    });
}

/*
 * Set up the basemap layer. Right now we're just using mapquest, but
 * this should be expanded later to include other free and commercial tile sets
 */
function setupBasemap(){
    var subDomains;
    if(window.location.protocol === 'https:'){
        subDomains = ['otile1-s','otile2-s','otile3-s','otile4-s'];
    } else{
        subDomains = ['otile1','otile2','otile3','otile4'];
    }
    DM.layers.basemap = new L.TileLayer('//{s}.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png', {
        maxZoom: 18, 
        attribution: 'Tiles from <a href="http://www.mapquest.com/" target="_blank">MapQuest</a>',
        subdomains: subDomains
    });

    return DM.layers.basemap;
}

function set_cdb_column_opts(e){
    var select = jQuery(e.target);
    var val = select.val();
    if(cdb_tables_and_columns[val]){
        select.closest('tr').find("td.dm_cdb_field_select_td").html(cdb_tables_and_columns[val]);
    }else if(val === ''){
        select.closest('tr').find("td.dm_cdb_field_select_td").html(cdb_tables_and_columns['dm_empty_select_list']);
    }
}

/*
 * Manage how the map is visualized
 *
 * If more than 50 features, show the cluster
 * If less than 50, show all features 
 * If exactly 1, select it automatically
 * If zero, then show all
 */
function setMetaboxLayerVisibility(newpoints){

    newpoints = newpoints || false;

    // Controll which will be displayed
    if(!newpoints || (newpoints.features !== undefined && newpoints.features.length === 0)){
        DM.layers.allpoints.clearLayers();
        DM.layers.allpoints.addData(DM.data.origpoints);
        DM.layers.cluster.clearLayers();
        DM.layers.cluster.addLayer(DM.layers.allpoints);
    }else{
        DM.layers.allpoints.clearLayers();
        DM.layers.allpoints.addData(newpoints);
        DM.layers.cluster.clearLayers();
        DM.layers.cluster.addLayer(DM.layers.allpoints);
    }
    
    // Controll if it will be clustered or not
    if(DM.layers.allpoints.getLayers().length < 50){
        DM.map.removeLayer(DM.layers.cluster);
        DM.map.addLayer(DM.layers.allpoints);
    }else{
        DM.map.removeLayer(DM.layers.allpoints);
        DM.map.addLayer(DM.layers.cluster);
    }

    // Controll if it will be auto-selected
    if(DM.layers.allpoints.getLayers().length === 1){
        var feature = DM.layers.allpoints.getLayers()[0].feature;
        jQuery('input[name=cartodb_lookup_value]').val(feature.properties.cartodb_id);
        jQuery('input[name=cartodb_lookup_label]').val(feature.label);
        setLookupMeta(feature);
    }

    // Fit map
    DM.map.fitBounds(DM.layers.allpoints.getBounds());
}
