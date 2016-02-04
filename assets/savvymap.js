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
		this.savvy = SAVVY;

		if( this.classname == 'SavvyClass' ) {
			throw "Classes extending SavvyClass must set a classname";
		}

		this.div = jQuery(div);
		this.args = this.div.data('map');
		this.meta = this.div.data('mapmeta');

		if(typeof this.args === 'string'){
			this.args = JSON.parse(this.args);
		}

		this.map = null; // The leaflet map object
		this.layers = {}; // A dict of layers we initialize so that people can style and interact with them

		this._basicMapSetup();
		this.savvy.add_map(this);
	},


	/**
	 * Set up the map itself
	*/
	_basicMapSetup: function(){
		// Fetch lat/lng/zoom
		var lat = this.args[ 'lat' ] || 'default';
		var lng = this.args[ 'lng' ] || 'default';
		var zoom = this.args[ 'zoom' ] || 'default';
		lat = (parseFloat(lat) == lat ? lat : 0);
		lng = (parseFloat(lng) == lng ? lng : 0);
		zoom = (parseFloat(zoom) == zoom ? zoom : 0);

		this.map = L.map(this.div[0]).setView([lat,lng],zoom); 

		this.map = this.savvy._apply_filters('savvy_map_initialized',this, this.map, this.meta);

		this._setupBasemap().addTo(this.map);

		this.set_search_layer();
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
		this.layers.basemap = new L.TileLayer('//{s}.mqcdn.com/tiles/1.0.0/map/{z}/{x}/{y}.png', {
			maxZoom: 18, 
			attribution: 'Tiles from <a href="http://www.mapquest.com/" target="_blank">MapQuest</a>',
			subdomains: subDomains
		});

		return this.layers.basemap;
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
	 * Set up the search layer. This will be found in 
	 * this.layers.thegeom
	 */
	set_search_layer: function( overrides ) {
		overrides = overrides || {};

		// Fetch lat/lng/zoom
		var lat = this.args[ 'lat' ] || 'default';
		var lng = this.args[ 'lng' ] || 'default';
		var zoom = this.args[ 'zoom' ] || 'default';
		var fitBounds = true;

		// Set popup override
		overrides.show_popups = overrides.show_popups || this.args.show_popups;

		// then make sure they're sane
		if(lat !== 'default' || lng !== 'default' || zoom !== 'default'){
			fitBounds = false;
		}
		lat = (parseFloat(lat) == lat ? lat : 0);
		lng = (parseFloat(lng) == lng ? lng : 0);
		zoom = (parseFloat(zoom) == zoom ? zoom : 0);

		var _this = this;

		// setting to false for now

		if(this.meta.post_id !== undefined){

			var promise = jQuery.getJSON(ajaxurl,{
				'action': 'savvy_get_geojson_for_post',
				'post_id' : this.meta.post_id,
				'overrides' : overrides,
				'mapping_id' : this.meta.mapping_id
			});

			promise = promise.then(function(success){
				if(_this.layers.thegeom !== undefined){
					_this.map.removeLayer( _this.layers.thegeom );
				}

				_this.layers.thegeom = L.geoJson(success,{
					onEachFeature: function (feature, layer) {
						if(_this.args.show_popups && typeof feature._popup_contents == 'string'){
							layer.bindPopup(feature._popup_contents);
						}
					},
					pointToLayer: function(feature, latlng){
						if(_this.args.show_features){
							return L.marker(latlng);
						}else{
							return L.circleMarker(latlng, {
								opacity: 0,
								fillOpacity: 0
							});
						}
					},
					style: function(feature){
						if(_this.args.show_features === 0) {
							return {
								opacity: 0,
								fillOpacity: 0
							};
						} else {
							return {};
						}
					}
				}).addTo(_this.map);
			});

			if(fitBounds) {
				promise = promise.then(function(){
					if(_this.layers.thegeom.getLayers().length > 0){
						_this.map.fitBounds(_this.layers.thegeom.getBounds());
					}
				});
			} else {
				promise = promise.then(function(){
					_this.map.setView(new L.LatLNg(lat,lng),zoom);
				});
			}

			return promise;
		}
	}
});
