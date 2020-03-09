class LocationBlock{
    static toggle(){
        if(LocationBlock.get_state()){
            LocationBlock.turn_off();
        }else{
            LocationBlock.turn_on();
        }
    }

    static turn_off(custom_msg=null){
        $('.modmp-location-block .autodetect-toggle').removeClass('state-on');
        $('.modmp-location-block .autodetect-toggle').addClass('state-off');
        $('.modmp-location-block').attr("title", "Location autodetect feature is turned off.");
        
        if(custom_msg){
            LocationBlock.display_message(custom_msg);
        }else{
            LocationBlock.display_message("Unknown");
        }

        LocationBlock.set_message_title("Your location is currently unknown.");
        LocationBlock.update_text();
        ModmpCookie.setCookie("modmplocator_off", true, 1);
    }

    static turn_on(){
        $('.modmp-location-block .autodetect-toggle').removeClass('state-off');
        $('.modmp-location-block .autodetect-toggle').addClass('state-on');
        $('.modmp-location-block').attr("title","We found out that your location is very close to this address.");

        Locator.tryAutodetect();
        
        LocationBlock.update_text();        
        ModmpCookie.setCookie("modmplocator_off", false, -1);
    }

    static update_text(){
        $('.modmp-location-block a.autodetect-toggle').html("&raquo; "+LocationBlock.get_state_string()+" &laquo;");
        $('.modmp-location-block a.autodetect-toggle').attr("title", LocationBlock.get_state_string()+" location autodetect feature.");
    }

    static get_state(){
        return $('.modmp-location-block .autodetect-toggle').hasClass('state-on');
    }

    static get_state_string(){
        if(LocationBlock.get_state()){
            return "Turn off";
        }else{
            return "Turn on";
        }
    }

    static display_message(msg){
        $('.modmp-location-block .address').text(msg);
    }

    static set_message_title(title){
        $('.modmp-location-block .address').attr("title", title);
    }

    static get_address_content(){
        return $('.modmp-location-block .address').text();
    }

    static get_address_title(){
        return $('.modmp-location-block .address').attr("title");
    }
}