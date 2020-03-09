var google_api_loaded = false;

function apiReady(){
    console.log('Google Maps API is ready for use.');

    google_api_loaded = true;

    initProductMap();
}

class Locator{
   static handleStatus(status){
        var msg = null;
        switch(status){
            case status.ZERO_RESULTS:
                msg = "Address not found";
                break;
            case status.OVER_QUERY_LIMIT:
                msg = "Query limit is reached";
                break;
            case status.REQUEST_DENIED:
                msg = "Unfortunately request was denied";
                break;
            case status.INVALID_REQUEST:
                msg = "Invalid request";
                break;
            case status.UNKNOWN_ERROR:
                msg = "Unknown server error.Please try again later.";
                break;
            case status.ERROR:
                msg = "Request timed out.Please try again later.";
                break;
        }

        return msg;
   }

   static handleGeolocationError(error){
        var msg = null;

        switch(error.code) {
            case error.PERMISSION_DENIED:
              msg = "User denied autodetection";
              break;
            case error.POSITION_UNAVAILABLE:
              msg = "Information unavailable";
              break;
            case error.TIMEOUT:
              msg = "Request timed out";
              break;
            case error.UNKNOWN_ERROR:
              msg = "Unknown error";
              break;
        }

        if(msg){
            LocationBlock.turn_off(msg);
        }

        return msg;
   }

   static getAddressComponent(address_components, component_type){
        var component_type = component_type.split('|');

        for(var current_component = 0; current_component < address_components.length ; current_component++){
            var comp = address_components[current_component];
            for(var curr_type = 0; curr_type < comp.types.length; curr_type++){
                if(component_type.indexOf(comp.types[curr_type]) !== -1){
                    return comp.long_name;
                }
            }
        }

        return false;
   }

   static getResponseContent(results){
        var number = Locator.getAddressComponent(results[0].address_components, "street_number");
        var street = Locator.getAddressComponent(results[0].address_components, "route");
        var city = Locator.getAddressComponent(results[0].address_components, "locality|postal_town|town|city");

        var short_address = street + " " + number + ", " + city;
        var long_address = results[0].formatted_address;

        return {
            'latitude':results[0].geometry.location.lat(),
            'longitude':results[0].geometry.location.lng(),
            'short_address':short_address,
            'long_address':long_address                      
            };
   }

   static getFromCookie(returnJson = false){
        var modmp_cookie = ModmpCookie.getCookie("modmplocator");
        if(modmp_cookie){
            modmp_cookie = JSON.parse(modmp_cookie);

            LocationBlock.display_message(modmp_cookie.short_address);
            LocationBlock.set_message_title(modmp_cookie.long_address);

            return returnJson?modmp_cookie:true;
        }

        return false;
   }

   static coordsEqual(coords_one, coords_two){
    	return coords_one.lat == coords_two.lat && coords_one.lng == coords_two.lng;
   }

   static getCurrentLocationAddress(position){
    	var cookie_location = Locator.getFromCookie(true);
    	if(cookie_location){
    		var cookie_latlng = {'lat':cookie_location.latitude,
    							 'lng':cookie_location.longitude};
    	    var current_latlng = {'lat':position.coords.latitude, 
    	    					  'lng':position.coords.longitude};
    	    if(cookie_latlng && current_latlng && Locator.coordsEqual(cookie_latlng, current_latlng)){
    	    	LocationBlock.display_message(cookie_location.short_address);
                LocationBlock.set_message_title(cookie_location.long_address);

                return true;
    	    }
    	}

        var retries_counter = 0;
        var max_retries = 10;
        while(!google_api_loaded){
             if(retries_counter <= max_retries){
                 console.log("Timeout has been set.");
                 setTimeout(function(){console.log('Timeout expired.');}, 1000);
                 retries_counter ++;
             }else{
                break;
             }
        }

        var latlng = new google.maps.LatLng(position.coords.latitude, position.coords.longitude);
        var geocoder = new google.maps.Geocoder();
        geocoder.geocode({ 'location' : latlng }, function(results, status){
            if(status == 'OK'){
                var response = Locator.getResponseContent(results);

                LocationBlock.display_message(response.short_address);
                LocationBlock.set_message_title(response.long_address);
                ModmpCookie.setCookie("modmplocator", JSON.stringify(response), 1);
            }else{
                var error = Locator.handleStatus(status);
                LocationBlock.turn_off(error);  
            }
        });
   }

   static tryAutodetect(){
        if(!LocationBlock.get_state()){
            LocationBlock.display_message("Autodetect is turned off");
            return false;
        }

        if(navigator.geolocation){
            navigator.geolocation.getCurrentPosition(
                function(position){
                    Locator.getCurrentLocationAddress(position);
                },

                function(error)
                {
                    Locator.handleGeolocationError(error);
                }
            );
        }else{
            // If browser's geolocation feature doesn't exist/work properly - then we try to detect visitor's location using its IP address. 
            // IpFind service's API is used to perform the IP geolocation.

            var visitor_ip_url = window.location.origin + window.location.pathname + "?visitor_ip=1";
            $.get(visitor_ip_url, "", function(visitor_ip){
                $.get("https://ipfind.co/?ip="+ visitor_ip +"&auth=e7a42e6a-9ec8-4538-b642-aefbb11a0fe0", "", function(response){
                    if(response){
                        var position = {
                            'coords': {
                                 'latitude': response.latitude,
                                 'longitude': response.longitude
                            }
                        };

                        Locator.getCurrentLocationAddress(position);
                    }else{
                        LocationBlock.turn_off("Geolocation not supported");
                    }
                }, "json");
            });
        }
    }
}

$("body").ready(function(evt){
    if(ModmpCookie.getCookie("modmplocator_off")){
        LocationBlock.turn_off();
    }else{
        LocationBlock.turn_on();
    }
});
