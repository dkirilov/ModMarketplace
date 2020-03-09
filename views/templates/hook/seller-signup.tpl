{block name="seller-signup-physical-stores"}
<div class="form-group row ">
    <label class="col-md-3 form-control-label">
    </label>
    <div class="col-md-6">
          <span class="custom-checkbox">
            <label>
              <input id="has_physical_store_check" name="has_physical_store" type="checkbox" value="1">
              <span><i class="material-icons rtl-no-flip checkbox-checked"></i></span>
              I have a physical store 
            </label>
          </span>
    </div>
   <div class="col-md-3 form-control-comment">
   </div>
 </div>
 <div id="physical_stores_locations" class="form-group row" data-max-shops="{$max_shops}">
    <div class="errors_display"></div>
    <label class="col-md-3 form-control-label required">
    </label>

    <div id="container" class="col-md-6">
    	<div id="locations">
    		<div id="store_location_0" class="store-location">
    			<strong id="store_name" class="title">Store location #0</strong> <input type="hidden" id="store_name_inp" name="store_name_0">
    			<button class="remove-location" onclick="SellerSignup.removeLocationAction(this);" type="button">Remove</button>
    			<ul>
    				<li>House number: <em id="house_number"></em> <input type="hidden" id="house_number_inp" name="house_number_0"></li>
    				<li>Street: <em id="street"></em> <input type="hidden" id="street_inp" name="street_0"></li>
    				<li>City: <em id="city"></em> <input type="hidden" id="city_inp" name="city_0"></li>
    				<li>ZIP/Postcode: <em id="postcode"></em> <input type="hidden" id="postcode_inp" name="postcode_0"></li>
    				<li>Country: <em id="country"></em> <input type="hidden" id="country_inp" name="country_0"></li>
    			</ul>
    		</div>
    	</div>
    	<div id="add_address_form">
    		<span class="inputs">
    			<button type="button" class="close-form" onclick="$(this).parent().slideUp(800);" title="Close form">X</button>

	    		<input type="text" name="house_number" class="required" placeholder="House number e.g. 45" size="4" maxlength="8">
	    		<input type="text" name="street" class="required" placeholder="Street name e.g. Abbey Road" size="15" maxlength="30">
	    		<input type="text" name="city" class="required" placeholder="City name e.g. New York" size="30" maxlength="30">
	    		<input type="text" name="postcode" placeholder="ZIP/Postcode" size="8" maxlength="15">
	    		<input type="text" name="country" class="required" placeholder="Country e.g. Spain" size="30" maxlength="30">
	    		<input type="text" name="store_name" placeholder="Store name for this address e.g. McDonald's Costa Rica" size="30" maxlength="50">
    		</span>
    		<span class="buttons">
	    		<button id="add_new" type="button" title="Add new store address.">Add store address</button>
	    		<button id="save" type="button" title="Save this store address.">Save address</button>
    		</span>
    	</div>
    </div>

   <div class="col-md-3 form-control-comment">
   </div>
 </div>
{/block}
