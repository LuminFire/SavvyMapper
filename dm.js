var dmMap = {};

jQuery(document).ready(function(){
    var dmMapDiv = jQuery('.dm_map_div');
    if(dmMapDiv.length > 0){
        makeCartoDBMap(dmMapDiv);
    }
});

function makeCartoDBMap(elem){
    var lat = 0;
    var lng = 0;
    var zoom = 1;

    dmMap.map = L.map(elem[0]).setView([lat,lng],zoom); 
    dmMap.table = jQuery('.db_lookupbox').data('table');
    dmMap.lookup = jQuery('.db_lookupbox').data('lookup');

    var subDomains;
    if(window.location.protocol === 'https:'){
        subDomains = ['otile1-s','otile2-s','otile3-s','otile4-s'];
    } else{
        subDomains = ['otile1','otile2','otile3','otile4'];
    }
    new L.TileLayer('//{s}.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png', {
        maxZoom: 18, 
        attribution: 'Tiles from <a href="http://www.mapquest.com/" target="_blank">MapQuest</a>',
        subdomains: subDomains
    }).addTo(dmMap.map);

    jQuery.getJSON(ajaxurl,{
        'action' : 'carto_ajax',
        'table' : dmMap.table, 
        'lookup' : dmMap.lookup
    }).then(function(success){
        dmMap.allpoints = L.geoJson(success).addTo(dmMap.map);
        dmMap.origpoints = success;
        dmMap.map.fitBounds(dmMap.allpoints.getBounds());
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

                var suggestions = [];

                for(var i = 0;i<success.features.length;i++){
                    suggestions.push({
                        'label' : success.features[i].properties[dmMap.lookup],
                        'value' : success.features[i].properties[dmMap.lookup]
                    });
                }

                localresponse(suggestions);
            },function(failure){
                localresponse();
            });

        },
        minLength: 2,
        select: function( event, ui ) {
        }
    });
}
