<div class="location-map-wrapper">
	<div class="location-map-title">
		<span class="title-txt">Product location:</span>
		<span class="distance">{hook h="displayProductDistance"}</span>
	</div>
	<div id="google-map" class="google-map product-location-map">
		<script type="text/javascript" async>
		  function initProductMap(){
	      	 LocationMap.displayLocation({$location.latitude}, {$location.longitude});
		  }
		</script>
	</div>
</div>