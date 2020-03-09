class SellerSignup{
    static countLocations(){
        return $("#locations .store-location").length - 1;
    }

    static shopsLimitReached(){
        var max_shops = $('#physical_stores_locations').attr('data-max-shops');
        return max_shops != 0 && max_shops !== "" && max_shops == SellerSignup.countLocations();
    }

	static clearAddressForm(){
		$("#physical_stores_locations #add_address_form input").val("");
		$("#physical_stores_locations #add_address_form .inputs").css("display", "none");
	}

	static addNewAction(){
        if(SellerSignup.shopsLimitReached()){
            SellerSignup.displayErrorMessage("You cannot add more shops!");
            return;
        }

		var inputs = $("#physical_stores_locations #add_address_form .inputs");
		inputs.slideDown(800);
		inputs.css("display", "inline-block");

		// Show save address button
		$("#physical_stores_locations .buttons #save").fadeIn();

		$("#physical_stores_locations .buttons #save").css("opacity", "0.6");
	}

	static removeLocationAction(button){
		$(button).parent().remove();
	}

	static saveAction(){
		var fields = [
				  {'name':'house_number', 'required':true}, 
			      {'name':'street', 'required':true}, 
			      {'name':'city', 'required':true}, 
			      {'name':'postcode', 'required':false}, 
			      {'name':'country', 'required':true}, 
			      {'name':'store_name', 'required':false}
			];
		var locations = $("#physical_stores_locations #locations");
		var location = SellerSignup.getLastLocation().clone();
		var location_number = SellerSignup.getLastLocationNumber()+1;

		location.attr("id", "store_location_"+location_number);

		for(var current_field = 0; current_field<fields.length; current_field++){
			var field_input = $("#physical_stores_locations #add_address_form input[name=\""+ fields[current_field].name +"\"]");
			var field_value = field_input.val();

			// Checks for empty required fields
			if(!field_value && fields[current_field].required){
				return;
			}

			// Sets current store address name
			if(fields[current_field].name == "store_name"){
                if(!field_value){
                    field_value = "Store location #"+location_number;
                }
			}

			// Displays current field value in locations list
			location.find("#"+fields[current_field].name).text(field_value);

			// Sets current field value to the corresponding hidden input field
			var inp = location.find("#"+fields[current_field].name+"_inp");
			inp.val(field_value);
			inp.attr("name", fields[current_field].name+"_"+location_number);
		}

		// Make current location block visible
		location.css("display", "block");
		
		// Add current location to locations list
		locations.append(location);

		// Clear address form
		SellerSignup.clearAddressForm();

		$("#physical_stores_locations .buttons #save").css("display", "none");
	}

	static getLastLocationNumber(){
		return parseInt( SellerSignup.getLastLocation().attr("id").replace("store_location_", ""), 10 );
	}

	static getLastLocation(){
		return $("#locations .store-location").last();
	}

	static hasMissingFields(){
		var ret = false;

		$("#physical_stores_locations #add_address_form input.required").each(function(index, element){
			if(!$(element).val()){
				ret = true;
			}
		});

		return ret;
	}

    static displayErrorMessage(msg){
        $("#physical_stores_locations .errors_display").html(msg);
    }
}

// Shows add new store address' input fields when "Add new" button is clicked
$("#physical_stores_locations #add_address_form #add_new").click(function(evt){
	SellerSignup.addNewAction();
});

// Adds the new store address to store locations list
$("#physical_stores_locations #add_address_form #save").click(function(evt){
	SellerSignup.saveAction();
});

// Show/hide store address input form when "I have a physical store" checkbox is clicked
$("#has_physical_store_check").change(function(evt){
	$("#physical_stores_locations").fadeToggle(400, "swing", function(){});
});

// Makes add address form's save button transparent when there are empty required fields
$("#physical_stores_locations #add_address_form input[type=\"text\"]").change(function(){
	if(!SellerSignup.hasMissingFields()){
		$("#physical_stores_locations .buttons #save").css("opacity", "1");
	}else{
		$("#physical_stores_locations .buttons #save").css("opacity", "0.6");
	}
});
