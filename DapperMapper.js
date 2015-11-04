/**
The DapperMapper object
*/
/* jshint proto: true */
/* avoiding jshint proto warning until I know more about mobile support for getPrototypeOf */
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
        this.archive_type = null;
        this.post_id = null;
        this.id = 'dmap' + this.__proto__._curId++;
    },

    /* Public functions */

    /**
     * Add a CartoDB visualization
     */
    addVisualization: function(vis_url){
        this.layers[vis_url] = cartodb.createLayer(this.map,vis_url);
        return this.layers[vis_url].addTo(this.map);
    },

    /* Private functions */


    /*
    * Set up the map for the metabox
    * Should show all elements, with clustering if we have more than 50 elements
    *
    * Also sets up other metabox interactions
    */
    _makeCartoDBMetaboxMap: function (elem){
        var _this = this;
        this._basicMapSetup(elem);

        this._meta.table = jQuery('.db_lookupbox').data('table');
        this._meta.lookup = jQuery('.db_lookupbox').data('lookup');

        this.layers.allgeoms = L.geoJson(null,{
            onEachFeature: function (feature, layer) {
                feature.label = feature.properties[_this._meta.lookup];
                feature.value = feature.properties[_this._meta.lookup];
                layer.on('click',function(e){
                    var feature = e.target.feature;
                    jQuery('input[name=cartodb_lookup_value]').val(feature.properties.cartodb_id);
                    jQuery('input[name=cartodb_lookup_label]').val(feature.properties[_this._meta.lookup]);
                    _this._setMetaboxLayerVisibility(feature);
                    _this._setLookupMeta(feature);
                });
            }
        });
        this.layers.cluster = L.markerClusterGroup().addTo(this.map);


        // Fetch all the geoms from carto and show them on the map, possibly clustered
        jQuery.getJSON(ajaxurl,{
            'action' : 'carto_metabox',
            'table' : _this._meta.table, 
            'lookup' : _this._meta.lookup,
            'cartodb_id' : jQuery('input[name=cartodb_lookup_value]').val()
        }).then(function(success){
            _this.data.origgeoms = success;
            _this._setMetaboxLayerVisibility(_this.data.origgeoms);
        });

        // On the metabox page set a listener on the lookup input field for when we have <2 chars
        jQuery('.db_lookup_ac').on('keyup blur',function(e){
            if(jQuery('.db_lookup_ac').val().length < 2){
                if(_this.data.origgeoms.features.length <= 1){
                    // Fetch all the geoms from carto and show them on the map, possibly clustered
                    jQuery.getJSON(ajaxurl,{
                        'action' : 'carto_metabox',
                        'table' : _this._meta.table, 
                        'lookup' : _this._meta.lookup,
                    }).then(function(success){
                        _this.data.origgeoms = success;
                        _this._setMetaboxLayerVisibility(_this.data.origgeoms);
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
                    'action' : 'carto_metabox',
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
        if(this._cdb_tables_and_columns[val]){
            select.closest('tr').find("td.dm_cdb_field_select_td").html(this._cdb_tables_and_columns[val]);
        }else if(val === ''){
            select.closest('tr').find("td.dm_cdb_field_select_td").html(this._cdb_tables_and_columns['dm_empty_select_list']);
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
    _setMetaboxLayerVisibility: function(newgeoms){

        newgeoms = newgeoms || false;

        // Controll which will be displayed
        if(!newgeoms || (newgeoms.features !== undefined && newgeoms.features.length === 0)){
            this.layers.allgeoms.clearLayers();
            this.layers.allgeoms.addData(this.data.origgeoms);
            this.layers.cluster.clearLayers();
            this.layers.cluster.addLayer(this.layers.allgeoms);
        }else{
            this.layers.allgeoms.clearLayers();
            this.layers.allgeoms.addData(newgeoms);
            this.layers.cluster.clearLayers();
            this.layers.cluster.addLayer(this.layers.allgeoms);
        }

        // Controll if it will be clustered or not
        if(this.layers.allgeoms.getLayers().length < 50){
            this.map.removeLayer(this.layers.cluster);
            this.map.addLayer(this.layers.allgeoms);
        }else{
            this.map.removeLayer(this.layers.allgeoms);
            this.map.addLayer(this.layers.cluster);
        }

        // Controll if it will be auto-selected
        if(this.layers.allgeoms.getLayers().length === 1){
            var feature = this.layers.allgeoms.getLayers()[0].feature;
            jQuery('input[name=cartodb_lookup_value]').val(feature.properties.cartodb_id);
            jQuery('input[name=cartodb_lookup_label]').val(feature.label);
            this._setLookupMeta(feature);
        }

        // Fit map
        this.map.fitBounds(this.layers.allgeoms.getBounds());
    },

    _basicMapSetup: function(elem){
        var lat = 0;
        var lng = 0;
        var zoom = 1;
        var _this = this;

        elem = jQuery(elem);
        
        // Set map id
        elem.data('mapId',this._mapID);
        
        this.map = L.map(elem[0]).setView([lat,lng],zoom); 
        this._setupBasemap().addTo(this.map);
        this.archive_type = elem.data('archive_type');
        this.post_id = elem.data('post_id');

        if(elem.data('archive_type') !== undefined || elem.data('post_id') !== undefined){

            var promise = jQuery.getJSON(ajaxurl,{
                'action': 'carto_query',
                'archive_type' : elem.data('archive_type'),
                'post_id' : elem.data('post_id')
            });
            
            promise = promise.then(function(success){
                _this.layers.thegeom = L.geoJson(success,{
                    onEachFeature: function (feature, layer) {
                        layer.bindPopup(feature.popup_contents);
                    }
                }).addTo(_this.map);

            });

            promise = promise.then(function(){
                _this.map.fitBounds(_this.layers.thegeom.getBounds());
            });

            return promise;
        }
    },

    _curId: 0
};
