/*
	ClusterMarker Version 1.3.2

	A marker manager for the Google Maps API
	http://googlemapsapi.martinpearman.co.uk/clustermarker

	Copyright Martin Pearman 2008
	Last updated 29th September 2008

	This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/
/*
	ClusterMarker Version 1.3.0

	A marker manager for the Google Maps API
	http://googlemapsapi.martinpearman.co.uk/clustermarker

	Copyright Martin Pearman 2008
	Last updated 5th March 2008

	This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

function ClusterMarker($map, $options){
	this._map=$map;
	this._mapMarkers=[];
	this._iconBounds=[];
	this._clusterMarkers=[];
	this._eventListeners=[];
	if(typeof($options)==='undefined'){
		$options={};
	}
	this.borderPadding=($options.borderPadding)?$options.borderPadding:256;
	this.clusteringEnabled=($options.clusteringEnabled===false)?false:true;
	if($options.clusterMarkerClick){
		this.clusterMarkerClick=$options.clusterMarkerClick;
	}
	if($options.clusterMarkerIcon){
		this.clusterMarkerIcon=$options.clusterMarkerIcon;
	}else{
		this.clusterMarkerIcon=new GIcon();
		this.clusterMarkerIcon.image=grpMarkerUrl;
		this.clusterMarkerIcon.iconSize=new GSize(39, 34);
		this.clusterMarkerIcon.iconAnchor=new GPoint(9, 31);
		this.clusterMarkerIcon.infoWindowAnchor=new GPoint(9, 31);
		this.clusterMarkerIcon.shadow=grpShadowUrl;
		this.clusterMarkerIcon.shadowSize=new GSize(39, 34);
	}
	this.clusterMarkerTitle=($options.clusterMarkerTitle)?$options.clusterMarkerTitle:'Click to zoom in and see %count markers';
	if($options.fitMapMaxZoom){
		this.fitMapMaxZoom=$options.fitMapMaxZoom;
	}
	this.intersectPadding=($options.intersectPadding)?$options.intersectPadding:0;
	if($options.markers){
		this.addMarkers($options.markers);
	}
	GEvent.bind(this._map, 'moveend', this, this._moveEnd);
	GEvent.bind(this._map, 'zoomend', this, this._zoomEnd);
	GEvent.bind(this._map, 'maptypechanged', this, this._mapTypeChanged);
}

ClusterMarker.prototype.addMarkers=function($markers){
	var i;
	if(!$markers[0]){
		//	assume $markers is an associative array and convert to a numerically indexed array
		var $numArray=[];
		for(i in $markers){
			$numArray.push($markers[i]);
		}
		$markers=$numArray;
	}
	for(i=$markers.length-1; i>=0; i--){
		$markers[i]._isVisible=false;
		$markers[i]._isActive=false;
		$markers[i]._makeVisible=false;
	}
	this._mapMarkers=this._mapMarkers.concat($markers);
};

ClusterMarker.prototype._clusterMarker=function($clusterGroupIndexes){
	function $newClusterMarker($location, $icon, $title){
		return new GMarker($location, {icon:$icon, title:$title});
	}
	var $clusterGroupBounds=new GLatLngBounds(), i, $clusterMarker, $clusteredMarkers=[], $marker, $this=this;
	for(i=$clusterGroupIndexes.length-1; i>=0; i--){
		$marker=this._mapMarkers[$clusterGroupIndexes[i]];
		$marker.index=$clusterGroupIndexes[i];
		$clusterGroupBounds.extend($marker.getLatLng());
		$clusteredMarkers.push($marker);
	}
	$clusterMarker=$newClusterMarker($clusterGroupBounds.getCenter(), this.clusterMarkerIcon, this.clusterMarkerTitle.replace(/%count/gi, $clusterGroupIndexes.length));
	$clusterMarker.clusterGroupBounds=$clusterGroupBounds;	//	only req'd for default cluster marker click action
	this._eventListeners.push(GEvent.addListener($clusterMarker, 'click', function(){
		$this.clusterMarkerClick({clusterMarker:$clusterMarker, clusteredMarkers:$clusteredMarkers });
	}));
	return $clusterMarker;
};

ClusterMarker.prototype.clusterMarkerClick=function($args){
	this._map.setCenter($args.clusterMarker.getLatLng(), this._map.getBoundsZoomLevel($args.clusterMarker.clusterGroupBounds) );
};

ClusterMarker.prototype._filterActiveMapMarkers=function(){
	var $borderPadding=this.borderPadding, $mapZoomLevel=this._map.getZoom(), $mapProjection=this._map.getCurrentMapType().getProjection(), $mapPointSw, $activeAreaPointSw, $activeAreaLatLngSw, $mapPointNe, $activeAreaPointNe, $activeAreaLatLngNe, $activeAreaBounds=this._map.getBounds(), i, $marker, $uncachedIconBoundsIndexes=[], $oldState;
	if($borderPadding){
		$mapPointSw=$mapProjection.fromLatLngToPixel($activeAreaBounds.getSouthWest(), $mapZoomLevel);
		$activeAreaPointSw=new GPoint($mapPointSw.x-$borderPadding, $mapPointSw.y+$borderPadding);
		$activeAreaLatLngSw=$mapProjection.fromPixelToLatLng($activeAreaPointSw, $mapZoomLevel);
		$mapPointNe=$mapProjection.fromLatLngToPixel($activeAreaBounds.getNorthEast(), $mapZoomLevel);
		$activeAreaPointNe=new GPoint($mapPointNe.x+$borderPadding, $mapPointNe.y-$borderPadding);
		$activeAreaLatLngNe=$mapProjection.fromPixelToLatLng($activeAreaPointNe, $mapZoomLevel);
		$activeAreaBounds.extend($activeAreaLatLngSw);
		$activeAreaBounds.extend($activeAreaLatLngNe);
	}
	this._activeMarkersChanged=false;
	if(typeof(this._iconBounds[$mapZoomLevel])==='undefined'){
		//	no iconBounds cached for this zoom level
		//	no need to check for existence of individual iconBounds elements
		this._iconBounds[$mapZoomLevel]=[];
		this._activeMarkersChanged=true;	//	force refresh(true) as zoomed to uncached zoom level
		for(i=this._mapMarkers.length-1; i>=0; i--){
			$marker=this._mapMarkers[i];
			$marker._isActive=$activeAreaBounds.containsLatLng($marker.getLatLng())?true:false;
			$marker._makeVisible=$marker._isActive;
			if($marker._isActive){
				$uncachedIconBoundsIndexes.push(i);
			}
		}
	}else{
		//	icondBounds array exists for this zoom level
		//	check for existence of individual iconBounds elements
		for(i=this._mapMarkers.length-1; i>=0; i--){
			$marker=this._mapMarkers[i];
			$oldState=$marker._isActive;
			$marker._isActive=$activeAreaBounds.containsLatLng($marker.getLatLng())?true:false;
			$marker._makeVisible=$marker._isActive;
			if(!this._activeMarkersChanged && $oldState!==$marker._isActive){
				this._activeMarkersChanged=true;
			}
			if($marker._isActive && typeof(this._iconBounds[$mapZoomLevel][i])==='undefined'){
				$uncachedIconBoundsIndexes.push(i);
			}
		}
	}
	return $uncachedIconBoundsIndexes;
};

ClusterMarker.prototype._filterIntersectingMapMarkers=function(){
	var $clusterGroup, i, j, $mapZoomLevel=this._map.getZoom();
	for(i=this._mapMarkers.length-1; i>0; i--)
	{
		if(this._mapMarkers[i]._makeVisible){
			$clusterGroup=[];
			for(j=i-1; j>=0; j--){
				if(this._mapMarkers[j]._makeVisible && this._iconBounds[$mapZoomLevel][i].intersects(this._iconBounds[$mapZoomLevel][j])){
					$clusterGroup.push(j);
				}
			}
			if($clusterGroup.length!==0){
				$clusterGroup.push(i);
				for(j=$clusterGroup.length-1; j>=0; j--){
					this._mapMarkers[$clusterGroup[j]]._makeVisible=false;
				}
				this._clusterMarkers.push(this._clusterMarker($clusterGroup));
			}
		}
	}
};

ClusterMarker.prototype.fitMapToMarkers=function(){
	var $markers=this._mapMarkers, $markersBounds=new GLatLngBounds(), i;
	for(i=$markers.length-1; i>=0; i--){
		$markersBounds.extend($markers[i].getLatLng());
	}
	var $fitMapToMarkersZoom=this._map.getBoundsZoomLevel($markersBounds);

	if(this.fitMapMaxZoom && $fitMapToMarkersZoom>this.fitMapMaxZoom){
		$fitMapToMarkersZoom=this.fitMapMaxZoom;
	}
	this._map.setCenter($markersBounds.getCenter(), $fitMapToMarkersZoom);
	this.refresh();
};

ClusterMarker.prototype._mapTypeChanged=function(){
	this.refresh(true);
};

ClusterMarker.prototype._moveEnd=function(){
	if(!this._cancelMoveEnd){
		this.refresh();
	}else{
		this._cancelMoveEnd=false;
	}
};

ClusterMarker.prototype._preCacheIconBounds=function($indexes){
	var $mapProjection=this._map.getCurrentMapType().getProjection(), $mapZoomLevel=this._map.getZoom(), i, $marker, $iconSize, $iconAnchorPoint, $iconAnchorPointOffset, $iconBoundsPointSw, $iconBoundsPointNe, $iconBoundsLatLngSw, $iconBoundsLatLngNe, $intersectPadding=this.intersectPadding;
	for(i=$indexes.length-1; i>=0; i--){
		$marker=this._mapMarkers[$indexes[i]];
		$iconSize=$marker.getIcon().iconSize;
		$iconAnchorPoint=$mapProjection.fromLatLngToPixel($marker.getLatLng(), $mapZoomLevel);
		$iconAnchorPointOffset=$marker.getIcon().iconAnchor;
		$iconBoundsPointSw=new GPoint($iconAnchorPoint.x-$iconAnchorPointOffset.x-$intersectPadding, $iconAnchorPoint.y-$iconAnchorPointOffset.y+$iconSize.height+$intersectPadding);
		$iconBoundsPointNe=new GPoint($iconAnchorPoint.x-$iconAnchorPointOffset.x+$iconSize.width+$intersectPadding, $iconAnchorPoint.y-$iconAnchorPointOffset.y-$intersectPadding);
		$iconBoundsLatLngSw=$mapProjection.fromPixelToLatLng($iconBoundsPointSw, $mapZoomLevel);
		$iconBoundsLatLngNe=$mapProjection.fromPixelToLatLng($iconBoundsPointNe, $mapZoomLevel);
		this._iconBounds[$mapZoomLevel][$indexes[i]]=new GLatLngBounds($iconBoundsLatLngSw, $iconBoundsLatLngNe);
	}
};

ClusterMarker.prototype.refresh=function($forceFullRefresh){
	var i,$marker, $uncachedIconBoundsIndexes=this._filterActiveMapMarkers();
	if(this._activeMarkersChanged || $forceFullRefresh){
		this._removeClusterMarkers();
		if(this.clusteringEnabled && this._map.getZoom()<this._map.getCurrentMapType().getMaximumResolution()){
			if($uncachedIconBoundsIndexes.length>0){
				this._preCacheIconBounds($uncachedIconBoundsIndexes);
			}
			this._filterIntersectingMapMarkers();
		}
		for(i=this._clusterMarkers.length-1; i>=0; i--){
			this._map.addOverlay(this._clusterMarkers[i]);
		}
		for(i=this._mapMarkers.length-1; i>=0; i--){
			$marker=this._mapMarkers[i];
			if(!$marker._isVisible && $marker._makeVisible){
				this._map.addOverlay($marker);
				$marker._isVisible=true;
			}
			if($marker._isVisible && !$marker._makeVisible){
				this._map.removeOverlay($marker);
				$marker._isVisible=false;
			}
		}
	}
};

ClusterMarker.prototype._removeClusterMarkers=function(){
	for(var i=this._clusterMarkers.length-1; i>=0; i--){
		this._map.removeOverlay(this._clusterMarkers[i]);
	}
	for(i=this._eventListeners.length-1; i>=0; i--){
		GEvent.removeListener(this._eventListeners[i]);
	}
	this._clusterMarkers=[];
	this._eventListeners=[];
};

ClusterMarker.prototype.removeMarkers=function(){
	for(var i=this._mapMarkers.length-1; i>=0; i--){
		if(this._mapMarkers[i]. _isVisible){
			this._map.removeOverlay(this._mapMarkers[i]);
		}
		delete this._mapMarkers[i]._isVisible;
		delete this._mapMarkers[i]._isActive;
		delete this._mapMarkers[i]._makeVisible;
	}
	this._removeClusterMarkers();
	this._mapMarkers=[];
	this._iconBounds=[];
};


ClusterMarker.prototype.triggerClick=function($index){
	var $marker=this._mapMarkers[$index];
	if($marker._isVisible){
		//	$marker is visible
		GEvent.trigger($marker, 'click');
	}
	else if($marker._isActive){
		//	$marker is clustered
		this._map.setCenter($marker.getLatLng());
		this._map.zoomIn();
		this.triggerClick($index);
	}else{
		// $marker is not within active area (map bounds + border padding)
		this._map.setCenter($marker.getLatLng());
		this.triggerClick($index);
	}
};

ClusterMarker.prototype._zoomEnd=function(){
	this._cancelMoveEnd=true;
	this.refresh(true);
};


//START CUSTOM Code

function add_static_map(id, lat, lng, zm, w, h, api_key, mrk_src, shd_src)
{
	var map  = new Image();
	jQuery(map).load(function(){
		jQuery('#map-'+id).append(this);
		var shadow = new Image();
		jQuery(shadow).load(function(){
			jQuery('#map-'+id).append(this);
			jQuery('#map-'+id).pngFix();
			var marker = new Image();
			jQuery(marker).load(function(){
				jQuery('#map-'+id).append(this);
				jQuery('#map-'+id).pngFix();
			})
			.attr('class', 'marker')
			.attr('src', mrk_src);
		})
		.attr('class', 'shadow')
		.attr('src', shd_src);
	})
	.attr('src', 'http://maps.google.com/staticmap?center='+lat+','+lng+'&zoom='+zm+'&size='+w+'x'+h+'&maptype=terrain&key='+api_key);
}

var pl_markersArray =[];

function add_dynamic_map(id, lat, lng, zm, w, h, cat, arch)
{
	jQuery('#map-'+id).css('height', h+'px').css('width', w+'px');
	var map = new GMap2(document.getElementById('map-'+id));
	map.setCenter(new GLatLng(lat, lng), zm, G_PHYSICAL_MAP);
	map.addControl(new GLargeMapControl(), new GControlPosition(G_ANCHOR_TOP_LEFT, new GSize(7, 7)));
	jQuery('#cat-filter-map-'+id+',#arch-filter-map-'+id).change(function(){update_markers(id);});
	pl_markersArray[id] = new ClusterMarker(map, {markers:[]}); //save the map object for later use

	update_markers(id);
}

function update_markers(id)
{
	var cat=jQuery('#cat-filter-map-'+id).val();
	var month = jQuery('#arch-filter-map-'+id).val();
	jQuery('#arch-filter-map-'+id).attr('disabled', true);
	jQuery('#cat-filter-map-'+id).attr('disabled', true);
	var cluster = pl_markersArray[id];
	cluster.removeMarkers();
 	jQuery("#loading-map-"+id).show();
	jQuery.post(ajaxUrl, {action:'get_markers', cat:cat, month:month}, function(data){
		var map, cluster, markersArray;
		var bupaIcon = new GIcon();
		bupaIcon.image = markerUrl;
		bupaIcon.shadow = shadowUrl;
		bupaIcon.iconSize = new GSize(29,25);
		bupaIcon.shadowSize = new GSize(60, 25);
		bupaIcon.iconAnchor = new GPoint(18, 25);
		bupaIcon.shadowAnchor = new GPoint(18, 25);
		bupaIcon.infoWindowAnchor = new GPoint(15, 2);
		bupaIcon.infoShadowAnchor = new GPoint(24, 25);

		var marker, markersArray=[];
		for(var i=0; i <data.length; i++)
		{
			markersArray.push(newMarker(data[i].latitude, data[i].longitude, data[i].title, data[i].html, bupaIcon));
		}
		var cluster = pl_markersArray[id];
		cluster.addMarkers(markersArray);
		cluster.refresh();
		jQuery('#arch-filter-map-'+id).attr('disabled', false);
		jQuery('#cat-filter-map-'+id).attr('disabled', false);
		jQuery("#loading-map-"+id).fadeOut();
	}, "json");
}

function newMarker(lat, lng, title, text, icon)
{
	var marker = new GMarker(new GLatLng(lat, lng), {icon:icon, title:title});
	if(text != '')
	{
		GEvent.addListener(marker, 'click', function(){
			marker.openInfoWindowHtml(text);
		});
	}
	return marker;
}

jQuery(window).unload(function(){
	GUnload();
});