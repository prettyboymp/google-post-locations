jQuery(window).unload(function(){
	GUnload();
});

var geocoder = null;
var map = null;

jQuery(document).ready(function(){
	if(GBrowserIsCompatible())
	{
		var mapdiv =  jQuery('#post-location-map');
		if(mapdiv.length > 0)
		{
			geocoder = new GClientGeocoder();
			var lat_input = jQuery('#latitude');
			var lng_input = jQuery('#longitude');
			var ll;
			map = new GMap2(document.getElementById(mapdiv.attr('id')));
			if(parseFloat(lat_input.val())  == lat_input.val() && parseFloat(lng_input.val()) == lng_input.val() )
			{
				ll = new GLatLng(lat_input.val(), lng_input.val());
			}
			else
			{
				ll = new GLatLng(0, 0);
			}
			map.setCenter(ll, 1);
			var marker = new GMarker(ll, {draggable: true});
			GEvent.addListener(marker, "dragend", function() {
        var point = marker.getPoint();
        updateLocation(point);
        map.setCenter(point);
      });
			map.addOverlay(marker);
			map.setUIToDefault();
			jQuery('#search-address').click(function(){
				showAddress(jQuery('#address').val());
				return false;
			});
		}
	}
});

function showAddress(address)
{
	if(geocoder)
	{
		geocoder.getLatLng(
      address,
      function(point) {
        if (!point) {
          alert(address + " not found");
        } else {
          map.setCenter(point);
          map.clearOverlays();
          var marker = new GMarker(point, {draggable: true});
          map.addOverlay(marker);
          updateLocation(point);
        }
      }
    );
	}
}

function updateLocation(point)
{
	jQuery('#latitude').val(point.y);
	jQuery('#longitude').val(point.x);
}