/**
Simple JavaScript Inheritance
By John Resig http://ejohn.org/blog/simple-javascript-inheritance/
MIT Licensed.

This sets up a method this._super() which can call the parent method (if it exists) from inside the child method. 
*/
(function(){
	var initializing = false, fnTest = /xyz/.test(function(){xyz;}) ? /\b_super\b/ : /.*/;

	// The base SavvyMapBase implementation (does nothing)
	this.SavvyMapBase = function(){};
	this.SavvyMapBase.prototype = {
		_defined: function(string){
			try{
				var v = eval(string);
				return v !== undefined;
			}catch(err){
				return false;
			}
		}
	};

	// Create a new SavvyMapBase that inherits from this class
	SavvyMapBase.extend = function(prop) {
		var _super = this.prototype;

		// Instantiate a base class (but only create the instance,
			// don't run the init constructor)
			initializing = true;
			var prototype = new this();
			initializing = false;

			// Copy the properties over onto the new prototype
			for (var name in prop) {
				// Check if we're overwriting an existing function
				prototype[name] = typeof prop[name] == "function" &&
				typeof _super[name] == "function" && fnTest.test(prop[name]) ?
				(function(name, fn){
					return function() {
						var tmp = this._super;

						// Add a new ._super() method that is the same method
						// but on the super-class
						this._super = _super[name];

						// The method only need to be bound temporarily, so we
						// remove it when we're done executing
						var ret = fn.apply(this, arguments);        
						this._super = tmp;

						return ret;
					};
				})(name, prop[name]) :
				prop[name];
			}

			// The dummy class constructor
			function SavvyMapBase() {
				// All construction is actually done in the init method
				if ( !initializing && this.init ){
					this.init.apply(this, arguments);
				}
			}

			/**
			* Make this class appendable
			*
			* Any props appended will be added to the object's prototype so all instnaces will gain them
			* If we append a method with the same name as an existing method, the previous method will 
			* be available to the new method with the alias _super
			*/
			SavvyMapBase.append = function(prop) {
				var _super = this.prototype;
				initializing = true;
				var prototype = new this();
				initializing = false;
				for (var name in prop){
					if(typeof prop[name] == "function" && typeof _super[name] == "function" && fnTest.test(prop[name])){
						prototype[name] = (function(name,fn){
							return function(){
								var tmp = this._super;
								this._super = _super[name];
								var ret = fn.apply(this,arguments);
								this._super = tmp;
								return ret;
							};
						}
						)(name,prop[name]);
					}else{
						prototype[name] = prop[name];
					}
				}

				this.prototype = prototype;
			};

			// Populate our constructed prototype object
			SavvyMapBase.prototype = prototype;

			// Enforce the constructor to be what we expect
			SavvyMapBase.prototype.constructor = SavvyMapBase;

			// And make this class extendable
			SavvyMapBase.extend = arguments.callee;

			return SavvyMapBase;
	};
})();


var SavvyMap = SavvyMapBase.extend({
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

	_setCDBColumnOpts: function(e){
		var select = jQuery(e.target);
		var val = select.val();
		if(this._cdb_tables_and_columns[val]){
			select.closest('tr').find("td.dm_cdb_field_select_td").html(this._cdb_tables_and_columns[val]);
		}else if(val === ''){
			select.closest('tr').find("td.dm_cdb_field_select_td").html(this._cdb_tables_and_columns['dm_empty_select_list']);
		}
	},

	_basicMapSetup: function(){
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

		this.map = L.map(this.div[0]).setView([lat,lng],zoom); 
		this._setupBasemap().addTo(this.map);
		this.archive_type = this.args[ 'archive_type' ];
		this.post_id = this.args[ 'post_id' ];

		if(this.args[ 'archive_type' ] !== undefined || this.args[ 'post_id' ] !== undefined){

			var promise = jQuery.getJSON(ajaxurl,{
				'action': 'get_geojson_for_post',
				'post_id' : this.args[ 'post_id' ]
			});

			promise = promise.then(function(success){
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
					_this.map.fitBounds(_this.layers.thegeom.getBounds());
				});
			} else {
				promise = promise.then(function(){
					_this.map.setView(new L.LatLNg(lat,lng),zoom);
				});
			}

			return promise;
		}
	},

	getId: function(){
		return this.args.id;
	}
});
