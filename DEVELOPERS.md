### PHP Filters
	* savvy_load_interfaces
- Called after plugins are loaded (and so, the SavvyInterface class)
	- Your interface should listen for this filter before declaring itself
	and should add itself to array of interface instances

### JS Filters and Actions


this.args = this.savvy._apply_filters('savvymap_args',this, this.args);
this.meta = this.savvy._apply_filters('savvymap_meta',this, this.meta);
this.map = this.savvy._apply_filters('savvymap_map_initialized',this, this.map);
basemapurl = this.savvy._apply_filters( 'savvymap_basemap_url', this, basemapurl );
basemapconfig = this.savvy._apply_filters( 'savvymap_basemap_config', this, basemapconfig );
success = _this.savvy._apply_filters( 'savvymap_thegeom_features', _this, success );
popupcontents = _this.savvy._apply_filters( 'savvymap_popup_contents', _this, popupcontents, feature, layer );
pointrep = _this.savvy._apply_filters( 'savvymap_feature_point', _this, pointrep, feature, latlng );
thestyle = _this.savvy._apply_filters( 'savvymap_feature_style', _this, thestyle );
geojsonconfig = _this.savvy._apply_filters( 'savvymap_thegeom_config', _this, geojsonconfig );
geombounds = _this.savvy._apply_filters( 'savvymap_thegeom_bounds', _this, geombounds, _this.layers.thegeom );
_this.savvy._do_action( 'savvymap_init_done', _this);
this.savvy._do_action( 'savvymap_view_changed', this );
this.savvy._do_action('savvymap_basemap_added',this,this.layers.basemap);
_this.savvy._do_action('savvymap_thegeom_added',_this,_this.thegeom);
_this.savvy._do_action( 'savvymap_view_changed', _this );
_this.savvy._do_action( 'savvymap_view_changed', _this );
_this._do_action( 'savvymapper_setup_done', _this );
this._do_action('savvymapper_map_added', this, newMap);
