/*
 Copyright (C) 2016 Cimbura.com

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.


 */
/**
* This is the base SavvyMap class. It implements the base functionality of any map displayed with SavvyMapper. 
* 
* Interfaces should provide a .js file with a class that extends SavvyMap and implements any additional functionality
* beyond the base functionality. 
*
* In some cases the base functionality may be sufficient for a given interface
*/
var SavvyMap = SavvyClass.extend({
	// Meta info we use internally. 
	init: function(div) {
		var _this = this;
		this.savvy = SAVVY;

		this.div = jQuery(div);

		this.meta = this.div.data('mapmeta');

		if(typeof this.args === 'string'){
			this.args = JSON.parse(this.args);
		}
		this.args = this.div.data('map');

		this.map = null; // The leaflet map object
		this.layers = {}; // A dict of layers we initialize so that people can style and interact with them

		this.args = this.savvy._apply_filters('savvymap_args',this, this.args);
		this.meta = this.savvy._apply_filters('savvymap_meta',this, this.meta);

		var setuppromise = this._basicMapSetup();
		this.savvy.add_map(this);
		setuppromise.then(function(){
			_this.savvy._do_action( 'savvymap_init_done', _this);
		});
	},


	/**
	* Set up the map itself
	*/
	_basicMapSetup: function(){
		// Fetch lat/lng/zoom
		var lat = this.args.lat || 'default';
		var lng = this.args.lng || 'default';
		var zoom = this.args.zoom || 'default';
		lat = (parseFloat(lat) == lat ? lat : 0);
		lng = (parseFloat(lng) == lng ? lng : 0);
		zoom = (parseFloat(zoom) == zoom ? zoom : 0);

		var mapconfig = {};
		mapconfig = this.savvy._apply_filters('savvymap_map_config',this,mapconfig);
		this.map = L.map(this.div[0],mapconfig).setView([lat,lng],zoom); 
		this.savvy._do_action( 'savvymap_view_changed', this );

		this.map = this.savvy._apply_filters('savvymap_map_initialized',this, this.map);

		this._setupBasemap();

		var layerspromise = this._setupLayers();
		return this._setupMap(layerspromise);
	},


	/**
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

		var basemapurl = '//{s}.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png';
		var basemapconfig = {
			maxZoom: 18, 
			attribution: 'Tiles from <a href="http://www.mapquest.com/" target="_blank">MapQuest</a>',
			subdomains: subDomains
		};

		basemapurl = this.savvy._apply_filters( 'savvymap_basemap_url', this, basemapurl );
		basemapurl = this.savvy._apply_filters( 'savvymap_' + this.meta.post_type + '_basemap_url', this, basemapurl );

		basemapconfig = this.savvy._apply_filters( 'savvymap_basemap_config', this, basemapconfig );
		basemapconfig = this.savvy._apply_filters( 'savvymap_' + this.meta.post_type + '_basemap_config', this, basemapconfig );

		if ( basemapurl ) { 
			this.layers.basemap = new L.TileLayer(basemapurl, basemapconfig);
			this.layers.basemap.addTo(this.map);
			this.savvy._do_action('savvymap_basemap_added',this,this.layers.basemap, this.map);
		}
	},

	/**
	* Get this map's id
	*/
	getId: function(){
		return this.meta.map_id;
	}, 

	/**
	* Get this map's type
	*/
	getClass: function(){
		return this.meta.connection_type;
	},

	/**
	* Get this map's slug
	*/
	getName: function(){
		return this.meta.mapping_slug;
	},
	
	/**
	 * Setup the map itself
	 *
	 * @param layerspromise A promise that will complete when all layers have been fetched
	 *
	 * @return A modified promise which includes a bounds setting function added to the end
	 */
	_setupMap: function(layerspromise) {

		// Fetch lat/lng/zoom
		var lat = this.args.lat || 'default';
		var lng = this.args.lng || 'default';
		var zoom = this.args.zoom || 'default';
		var fitBounds = true;

		// then make sure they're sane
		if(lat !== 'default' || lng !== 'default' || zoom !== 'default'){
			fitBounds = false;
		}
		lat = (parseFloat(lat) == lat ? lat : 0);
		lng = (parseFloat(lng) == lng ? lng : 0);
		zoom = (parseFloat(zoom) == zoom ? zoom : 0);

		if(fitBounds) {
			var _this = this;
			layerspromise = layerspromise.then(function(layersToCheck){

				var bounds;
				var moreBounds;
				for( var l = 0; l < layersToCheck.length; l++ ){
					if( typeof _this.layers[ layersToCheck[l] ].getBounds == 'function' ) {
						moreBounds = _this.layers[ layersToCheck[l] ].getBounds();
						moreBounds = _this.savvy._apply_filters( 'savvymap_' + l + '_bounds', _this, moreBounds, _this.layers[ layersToCheck[l] ]);

						if( bounds === undefined ) {
							bounds = moreBounds;
						} else {
							bounds.extend(moreBounds);
						}
					}	
				}

				bounds = _this.savvy._apply_filters( 'savvymap_map_bounds', _this, bounds);
				_this.map.fitBounds(bounds);
				_this.savvy._do_action( 'savvymap_view_changed', _this );
			});
		} else {
			layerspromise = layerspromise.then(function(){
				_this.map.setView(new L.LatLNg(lat,lng),zoom);
				_this.savvy._do_action( 'savvymap_view_changed', _this );
			});
		}

		// Now resort layer depth
		layerspromise = layerspromise.then(function(){
			var curLayer;
			for(var l = _this.args.layers.length; l > 0; l-- ){
				curLayer = _this.layers[ _this.args.layers[ l - 1 ].mapping_slug ];
				if ( curLayer !== undefined ) {
					curLayer.bringToFront();
				}
			}
		});

		return layerspromise;
	},

	/**
	* Set up the layers. 
	*/
	_setupLayers: function() {
		var layerpromises = [];

		var promise;
		var layersToCheck = [];
		for(var l = 0;l<this.args.layers.length;l++){
			promise = this._addLayer( this.args.layers[ l ] );	
			layerpromises.push(promise);
			layersToCheck.push(this.args.layers[ l ].mapping_slug);
		}

		var retpromise = jQuery.when.apply(jQuery, layerpromises);
		retpromise = retpromise.then(function(){
			return layersToCheck;
		});
		return retpromise;
	},

	// We assume that the first layer is the top layer and the one we want to search on
	set_search_layer: function(overrides) {
		overrides = overrides || {};
		if( this.args.layers.length > 0 ) {
			var promise = this._addLayer(this.args.layers[0],overrides);
			var _this = this;
			promise = promise.then(function(){
				return [_this.args.layers[0].mapping_slug];
			});
			return this._setupMap(promise);
		}
	},

	/**
	 * Add a single layer
	 */
	_addLayer: function(config, overrides) {
		var _this = this;
		overrides = overrides || {};

		// setting to false for now
		var promise = jQuery.getJSON(ajaxurl,{
			'action': 'savvy_get_geojson_for_post',
			'post_id' : this.meta.post_id,
			'mapping_id' : config.mapping_id,
			'overrides' : overrides
		});

		promise = promise.then(function(success){
			if(_this.layers[config.mapping_slug] !== undefined){
				_this.map.removeLayer( _this.layers[config.mapping_slug] );
			}

			success = _this.savvy._apply_filters( 'savvymap_layer_features', _this, success );
			success = _this.savvy._apply_filters( 'savvymap_' + config.mapping_slug + '_features', _this, success );

			var geojsonconfig = {
				onEachFeature: function (feature, layer) {
					if(config.show_popups){
						var popupcontents = '';
						if( typeof feature._popup_contents == 'string'){
							popupcontents = feature._popup_contents;
						}
						popupcontents = _this.savvy._apply_filters( 'savvymap_popup_contents', _this, popupcontents, feature, layer );
						popupcontents = _this.savvy._apply_filters( 'savvymap_' + config.mapping_slug + '_contents', _this, popupcontents, feature, layer );
						if( popupcontents.length > 0 ){
							layer.bindPopup( popupcontents );
						}
					}
				},
				pointToLayer: function(feature, latlng){
					var pointrep = _this.savvy._apply_filters( 'savvymap_feature_point', _this, null, feature, latlng );
					pointrep = _this.savvy._apply_filters( 'savvymap_' + config.mapping_slug + '_point', _this, pointrep, feature, latlng );

					if(pointrep !== null){
						return pointrep;
					}

					if(config.show_features === 0){

						var circlestyle = {
							opacity: 0,
							fillOpacity: 0
						};

						circlestyle = _this.savvy._apply_filters( 'savvymap_feature_style', _this, circlestyle, feature );
						circlestyle = _this.savvy._apply_filters( 'savvymap_' + config.mapping_slug + '_style', _this, circlestyle, feature );

						pointrep = L.circleMarker(latlng, circlestyle);
					}else{
						pointrep = L.marker(latlng);
					}

					return pointrep;
				},
				style: function(feature){
					var thestyle = {};
					if(config.show_features === 0) {
						thestyle = {
							opacity: 0,
							fillOpacity: 0
						};
					}

					thestyle = _this.savvy._apply_filters( 'savvymap_feature_style', _this, thestyle, feature );
					thestyle = _this.savvy._apply_filters( 'savvymap_' + config.mapping_slug + '_style', _this, thestyle, feature );
					return thestyle;
				}
			};

			geojsonconfig = _this.savvy._apply_filters( 'savvymap_layer_config', _this, geojsonconfig );
			geojsonconfig = _this.savvy._apply_filters( 'savvymap_' + config.mapping_slug + '_config', _this, geojsonconfig );

			_this.layers[config.mapping_slug] = L.geoJson(success, geojsonconfig).addTo(_this.map);
			_this.savvy._do_action('savvymap_layer_added',_this,_this.layers[config.mapping_slug]);
			_this.savvy._do_action('savvymap_' + config.mapping_slug + '_added',_this,_this.layers[config.mapping_slug]);
		});

		return promise;
	}
});
