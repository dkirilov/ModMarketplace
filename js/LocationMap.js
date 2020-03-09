class LocationMap{
	static displayLocation(latitude, longitude){
		var coords = {lat: parseFloat(latitude), lng: parseFloat(longitude)};

        var map = new google.maps.Map(document.getElementById('google-map') , {
				zoom: 11,
				center: coords
		});

        var marker = new google.maps.Marker({position: coords, map: map});

        // Show map title
        $(".location-map-title").css("display", "block");
	}
}
