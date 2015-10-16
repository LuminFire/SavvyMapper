/*
Our DapperMapper object
*/
function DapperMapper() {
    this._init();
}

DapperMapper.prototype = {
    // Meta info we use internally. 

    _init:  function(){
        this.layers = {}; // A dict of layers we initialize so that people can style and interact with them
        this.data = {}; // Raw data we want to save for later use, saved here so other scan interact with it
        this._meta =  {};
        this.map = null; // The leaflet map object
    },


    /* Public functions */

    addVisualization: function(vis_url){
        return cartodb.createLayer(this.map,vis_url).addTo(this.map);
    },

    /* Private functions */


    /*
    * Set up the map for the metabox
    * Should show all elements, with clustering if we have more than 50 elements
    *
    * Also sets up other metabox interactions
    */
    _makeCartoDBMetaboxMap: function (elem){
        var lat = 0;
        var lng = 0;
        var zoom = 1;
        var _this = this;

        this.map = L.map(elem[0]).setView([lat,lng],zoom); 
        this._meta.table = jQuery('.db_lookupbox').data('table');
        this._meta.lookup = jQuery('.db_lookupbox').data('lookup');

        this._setupBasemap().addTo(this.map);
        this.layers.allpoints = L.geoJson(null,{
            onEachFeature: function (feature, layer) {
                feature.label = feature.properties[this._meta.lookup];
                feature.value = feature.properties[this._meta.lookup];
                layer.on('click',function(e){
                    var feature = e.target.feature;
                    jQuery('input[name=cartodb_lookup_value]').val(feature.properties.cartodb_id);
                    jQuery('input[name=cartodb_lookup_label]').val(feature.properties[this._meta.lookup]);
                    _this._setMetaboxLayerVisibility(feature);
                    _this._setLookupMeta(feature);
                });
            }
        });
        this.layers.cluster = L.markerClusterGroup().addTo(this.map);


        // Fetch all the points from carto and show them on the map, possibly clustered
        jQuery.getJSON(ajaxurl,{
            'action' : 'carto_ajax',
            'table' : _this._meta.table, 
            'lookup' : _this._meta.lookup,
            'cartodb_id' : jQuery('input[name=cartodb_lookup_value]').val()
        }).then(function(success){
            _this.data.origpoints = success;
            _this._setMetaboxLayerVisibility(_this.data.origpoints);
        });

        // On the metabox page set a listener on the lookup input field for when we have <2 chars
        jQuery('.db_lookup_ac').on('keyup blur',function(e){
            if(jQuery('.db_lookup_ac').val().length < 2){
                if(_this.data.origpoints.features.length <= 1){
                    // Fetch all the points from carto and show them on the map, possibly clustered
                    jQuery.getJSON(ajaxurl,{
                        'action' : 'carto_ajax',
                        'table' : _this._meta.table, 
                        'lookup' : _this._meta.lookup,
                    }).then(function(success){
                        _this.data.origpoints = success;
                        _this._setMetaboxLayerVisibility(_this.data.origpoints);
                    });
                }else{
                    _this._setMetaboxLayerVisibility();
                }
            }
        });

        // If we have 2+ chars then we fire off autocomplete
        jQuery('.db_lookup_ac').autocomplete({
            source: function(request, response){

                request = jQuery.extend(request,{
                    'action' : 'carto_ajax',
                    'table' : _this._meta.table, 
                    'lookup' : _this._meta.lookup
                });

                var localresponse = response;

                jQuery.getJSON(ajaxurl,request).then(function(success){
                    _this._setMetaboxLayerVisibility(success);

                    var suggestions = success.features;

                    for(var i = 0;i<suggestions.length;i++){
                        suggestions[i]['label'] = suggestions[i].properties[_this._meta.lookup];
                        suggestions[i]['value'] = suggestions[i].properties[_this._meta.lookup];
                    }

                    localresponse(suggestions);
                },function(failure){
                    localresponse();
                });

            },
            minLength: 2,
            select: function( event, ui ) {
                _this._setMetaboxLayerVisibility(ui.item);
            }
        });
    },

    /*
    * Use the properties from a feature and display them for the user
    */
    _setLookupMeta: function(feature){
        var html = '<ul>';
        var props = feature.properties;
        for(var k in props){
            html += '<li><strong>' + k + '</strong> - ' + props[k] + '</li>';
        }
        html += '</ul>';

        jQuery('.dm_lookup_meta').html(html);
    },

    _makeCartoDBPageMap: function(elem){
        var lat = 0;
        var lng = 0;
        var zoom = 1;

        this.map = L.map(elem[0]).setView([lat,lng],zoom); 
        this._setupBasemap().addTo(this.map);

        var g = L.geoJson(dm_singleFeature,{
            onEachFeature: function (feature, layer) {
                layer.bindPopup(feature.popup_contents);
            }
        }).addTo(this.map);
        this.map.fitBounds(g.getBounds());
    },

    _makeArchivePageMap: function(elem){
        var lat = 0;
        var lng = 0;
        var zoom = 1;
        var _this = this;

        this.map = L.map(elem[0]).setView([lat,lng],zoom); 
        this._setupBasemap().addTo(this.map);

        jQuery.getJSON(ajaxurl,{
            'action': 'carto_archive',
            'table' : elem.data('table'),
            'post_type' : elem.data('post_type'),
        }).then(function(success){
            _this.layers.allpoints = L.geoJson(success,{
                onEachFeature: function (feature, layer) {
                    layer.bindPopup(feature.popup_contents);
                }
            }).addTo(_this.map);

            _this.map.fitBounds(_this.layers.allpoints.getBounds());
        });
    },

    /*
    * Set up the basemap layer. Right now we're just using mapquest, but
    * this should be expanded later to include other free and commercial tile sets
    */
    _setupBasemap: function(){
        var subDomains;
        if(window.location.protocol === 'https:'){
            subDomains = ['otile1-s','otile2-s','otile3-s','otile4-s'];
        } else{
            subDomains = ['otile1','otile2','otile3','otile4'];
        }
        this.layers.basemap = new L.TileLayer('//{s}.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png', {
            maxZoom: 18, 
            attribution: 'Tiles from <a href="http://www.mapquest.com/" target="_blank">MapQuest</a>',
            subdomains: subDomains
        });

        return this.layers.basemap;
    },

    _setCDBColumnOpts: function(e){
        var select = jQuery(e.target);
        var val = select.val();
        if(cdb_tables_and_columns[val]){
            select.closest('tr').find("td.dm_cdb_field_select_td").html(cdb_tables_and_columns[val]);
        }else if(val === ''){
            select.closest('tr').find("td.dm_cdb_field_select_td").html(cdb_tables_and_columns['dm_empty_select_list']);
        }
    },

    /*
    * Manage how the map is visualized
    *
    * If more than 50 features, show the cluster
    * If less than 50, show all features 
    * If exactly 1, select it automatically
    * If zero, then show all
    */
    _setMetaboxLayerVisibility: function(newpoints){

        newpoints = newpoints || false;

        // Controll which will be displayed
        if(!newpoints || (newpoints.features !== undefined && newpoints.features.length === 0)){
            this.layers.allpoints.clearLayers();
            this.layers.allpoints.addData(this.data.origpoints);
            this.layers.cluster.clearLayers();
            this.layers.cluster.addLayer(this.layers.allpoints);
        }else{
            this.layers.allpoints.clearLayers();
            this.layers.allpoints.addData(newpoints);
            this.layers.cluster.clearLayers();
            this.layers.cluster.addLayer(this.layers.allpoints);
        }

        // Controll if it will be clustered or not
        if(this.layers.allpoints.getLayers().length < 50){
            this.map.removeLayer(this.layers.cluster);
            this.map.addLayer(this.layers.allpoints);
        }else{
            this.map.removeLayer(this.layers.allpoints);
            this.map.addLayer(this.layers.cluster);
        }

        // Controll if it will be auto-selected
        if(this.layers.allpoints.getLayers().length === 1){
            var feature = this.layers.allpoints.getLayers()[0].feature;
            jQuery('input[name=cartodb_lookup_value]').val(feature.properties.cartodb_id);
            jQuery('input[name=cartodb_lookup_label]').val(feature.label);
            this._setLookupMeta(feature);
        }

        // Fit map
        this.map.fitBounds(this.layers.allpoints.getBounds());
    },
};
