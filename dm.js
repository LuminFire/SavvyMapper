var dmMap = {};

jQuery(document).ready(function(){
    var dmMapDiv = jQuery('.dm_map_div');
    if(dmMapDiv.length > 0){
        makeCartoDBMap(dmMapDiv);
    }

    var dmPageMapDiv = jQuery('.dm_page_map_div');
    if(dmPageMapDiv.length > 0){
        makeCartoDBPageMap(dmPageMapDiv);
    }

    var dmArchiveMapDiv = jQuery('.dm_archive_map');
    if(dmArchiveMapDiv.length > 0){
        makeArchivePageMap(dmArchiveMapDiv);
    }

    jQuery('.dm_cdb_table_select').on('change',set_cdb_column_opts);
});

function makeCartoDBMap(elem){
    var lat = 0;
    var lng = 0;
    var zoom = 1;

    dmMap.map = L.map(elem[0]).setView([lat,lng],zoom); 
    dmMap.table = jQuery('.db_lookupbox').data('table');
    dmMap.lookup = jQuery('.db_lookupbox').data('lookup');

    setupBasemap().addTo(dmMap.map);

    jQuery.getJSON(ajaxurl,{
        'action' : 'carto_ajax',
        'table' : dmMap.table, 
        'lookup' : dmMap.lookup,
        'cartodb_id' : jQuery('input[name=cartodb_lookup_value]').val()
    }).then(function(success){
        dmMap.allpoints = L.geoJson(success,{
            onEachFeature: function (feature, layer) {
                layer.on('click',function(e){
                    var feature = e.target.feature;
                    jQuery('input[name=cartodb_lookup_value]').val(feature.properties.cartodb_id);
                    jQuery('input[name=cartodb_lookup_label]').val(feature.properties[dmMap.lookup]);
                    dmMap.allpoints.clearLayers();
                    dmMap.allpoints.addData(feature);
                    setLookupMeta(feature);
                });
            }
        }).addTo(dmMap.map);
        dmMap.origpoints = success;
        dmMap.map.fitBounds(dmMap.allpoints.getBounds());

        if(jQuery('input[name=cartodb_lookup_value]').val() !== ''){
            if(success.features.length === 1){
                setLookupMeta(success.features[0]);
            }
        }
    });

    jQuery('.db_lookup_ac').on('keypress',function(e){
        if(jQuery('.db_lookup_ac').val().length < 2){
            dmMap.allpoints.clearLayers();
            dmMap.allpoints.addData(dmMap.origpoints);

        }
    });

    jQuery('.db_lookup_ac').autocomplete({
        source: function(request, response){

            request = jQuery.extend(request,{
                'action' : 'carto_ajax',
                'table' : dmMap.table, 
                'lookup' : dmMap.lookup
            });

            var localresponse = response;

            jQuery.getJSON(ajaxurl,request).then(function(success){
                dmMap.allpoints.clearLayers();
                dmMap.allpoints.addData(success);

                if(success.features.length === 0){
                    dmMap.allpoints.addData(dmMap.origpoints);
                }

                dmMap.map.fitBounds(dmMap.allpoints.getBounds());

                var suggestions = success.features;

                for(var i = 0;i<suggestions.length;i++){
                    suggestions[i]['label'] = suggestions[i].properties[dmMap.lookup];
                    suggestions[i]['value'] = suggestions[i].properties[dmMap.lookup];
                }

                localresponse(suggestions);
            },function(failure){
                localresponse();
            });

        },
        minLength: 2,
        select: function( event, ui ) {
            jQuery('input[name=cartodb_lookup_value]').val(ui.item.properties.cartodb_id);
            jQuery('input[name=cartodb_lookup_label]').val(ui.item.label);
            dmMap.allpoints.clearLayers();
            dmMap.allpoints.addData(ui.item);
            setLookupMeta(ui.item);
        }
    });
}

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

    dmMap.map = L.map(elem[0]).setView([lat,lng],zoom); 
    setupBasemap().addTo(dmMap.map);

    var g = L.geoJson(dm_singleFeature,{
        onEachFeature: function (feature, layer) {
            layer.bindPopup(feature.popup_contents);
        }
    }).addTo(dmMap.map);
    dmMap.map.fitBounds(g.getBounds());
}

function makeArchivePageMap(elem){
    var lat = 0;
    var lng = 0;
    var zoom = 1;

    dmMap.map = L.map(elem[0]).setView([lat,lng],zoom); 
    setupBasemap().addTo(dmMap.map);

    jQuery.getJSON(ajaxurl,{
        'action': 'carto_archive',
        'table' : elem.data('table'),
        'post_type' : elem.data('post_type'),
    }).then(function(success){
        dmMap.allpoints = L.geoJson(success,{
            onEachFeature: function (feature, layer) {
                layer.bindPopup(feature.popup_contents);
            }
        }).addTo(dmMap.map);

        dmMap.map.fitBounds(dmMap.allpoints.getBounds());
    });
}

function setupBasemap(){
    var subDomains;
    if(window.location.protocol === 'https:'){
        subDomains = ['otile1-s','otile2-s','otile3-s','otile4-s'];
    } else{
        subDomains = ['otile1','otile2','otile3','otile4'];
    }
    return new L.TileLayer('//{s}.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png', {
        maxZoom: 18, 
        attribution: 'Tiles from <a href="http://www.mapquest.com/" target="_blank">MapQuest</a>',
        subdomains: subDomains
    });
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
