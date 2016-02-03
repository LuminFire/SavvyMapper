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

	init: function(div,args) {
		if(typeof args === 'string'){
			args = JSON.parse(args);
		}
		this.args = args;
		this.div = jQuery(div);

		this.layers = {}; // A dict of layers we initialize so that people can style and interact with them
		this.data = {}; // Raw data we want to save for later use, saved here so other scan interact with it
		this._meta =  {};
		this.map = null; // The leaflet map object
		this.archive_type = null;
		this.post_id = null;

		this._basicMapSetup();
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

	_basicMapSetup: function(){
		// Fetch lat/lng/zoom
		var lat = this.args[ 'lat' ] || 'default';
		var lng = this.args[ 'lng' ] || 'default';
		var zoom = this.args[ 'zoom' ] || 'default';
		lat = (parseFloat(lat) == lat ? lat : 0);
		lng = (parseFloat(lng) == lng ? lng : 0);
		zoom = (parseFloat(zoom) == zoom ? zoom : 0);



		this.map = L.map(this.div[0]).setView([lat,lng],zoom); 
		this._setupBasemap().addTo(this.map);

		this.set_search_layer();
	},

	getId: function(){
		return this.args.id;
	}, 

	set_search_layer: function( overrides ) {
		overrides = overrides || {};

		// Fetch lat/lng/zoom
		var lat = this.args[ 'lat' ] || 'default';
		var lng = this.args[ 'lng' ] || 'default';
		var zoom = this.args[ 'zoom' ] || 'default';
		var fitBounds = true;

		// then make sure they're sane
		if(lat !== 'default' || lng !== 'default' || zoom !== 'default'){
			fitBounds = false;
		}
		lat = (parseFloat(lat) == lat ? lat : 0);
		lng = (parseFloat(lng) == lng ? lng : 0);
		zoom = (parseFloat(zoom) == zoom ? zoom : 0);

		var show_marker = this.args[ 'marker' ];
		show_marker = (show_marker === false ? false : true);
		show_marker = true;

		var popup = this.args[ 'popup' ];
		popup = (popup === false || popup == 'false' ? false : true);
		var _this = this;


		// setting to false for now
		popup = false;

		this.archive_type = this.args[ 'archive_type' ];
		this.post_id = this.args[ 'post_id' ];

		if(this.args[ 'archive_type' ] !== undefined || this.args[ 'post_id' ] !== undefined){

			var promise = jQuery.getJSON(ajaxurl,{
				'action': 'savvy_get_geojson_for_post',
				'post_id' : this.args.post_id,
				'overrides' : overrides,
				'mapping_id' : this.args.mapping_id
			});

			promise = promise.then(function(success){
				if(_this.layers.thegeom !== undefined){
					_this.map.removeLayer( _this.layers.thegeom );
				}

				_this.layers.thegeom = L.geoJson(success,{
					onEachFeature: function (feature, layer) {
						if(popup){
							layer.bindPopup(feature.popup_contents);
						}
					},
					pointToLayer: function(feature, latlng){
						if(show_marker){
							return L.marker(latlng);
						}else{
							return L.circleMarker(latlng, {
								opacity: 0,
								fillOpacity: 0
							});
						}
					},
					style: function(feature){
						return {};
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
