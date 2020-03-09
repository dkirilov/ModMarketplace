<?php

if(Tools::getIsset('visitor_ip')){
     die(Tools::getRemoteAddr());
}

require_once(_PS_MODULE_DIR_ . 'modmarketplace/classes/LocatorAddress.class.php');

class Locator{
   public static function getCurrentVisitorLocation($field = null){
        if(!isset($_COOKIE['modmplocator'])){
            return false;
        }

        $vl = json_decode($_COOKIE['modmplocator'], true);

        $visitor_location = new LocatorAddress($vl['latitude'], $vl['longitude'], $vl['short_address'], $vl['long_address']);

        if(empty($field)){
            return $visitor_location;
        }else{
            $field = array_map('ucfirst', explode("_", $field));
            $field = implode("", $field);
            $field = 'get'.$field;

            return $visitor_location->$field();
        }
   }

   public static function getAddressComponent(array $address_components, string $component, bool $long_name = false){
        $component = explode("|", $component);

        foreach ($address_components as $comp) {
            foreach ($comp['types'] as $comp_type) {
               if(in_array($comp_type, $component)){
                  return $long_name?$comp['long_name']:$comp['short_name'];
               }
            }
        }

        return false;
   }

   public static function getAddressLatlng(string $address, bool $return_city = false){
        $req_url = "https://maps.googleapis.com/maps/api/geocode/json?address=". urlencode($address) ."&key=" . Configuration::get('GEOCODE_API_KEY');
        $response = @file_get_contents($req_url);
        $response = json_decode($response, true);

        if($response['status'] == 'OK'){
            $response = $response['results'][0];

            $number = self::getAddressComponent($response['address_components'], "street_number");
            $street = self::getAddressComponent($response['address_components'], "route");
            $city = self::getAddressComponent($response['address_components'], "locality|postal_town|town|city");

            if($return_city){
                return $city;
            }

            $lat = $response['geometry']['location']['lat'];
            $lng = $response['geometry']['location']['lng'];
            $short_address = $street . " " . $number .  ", " . $city;
            $long_address =  $response['formatted_address'];

            return new LocatorAddress($lat, $lng, $short_address, $long_address);
        }

        return null;
    }

   public static function calcDistance(\LocatorAddress $location_one, \LocatorAddress $location_two, string $unit = "km", bool $format_distance = true){
        $earth_radius = 6371 * 1000; // in metres
        $f1 = $location_one->getLatitude(true); // Location one latitude in radians
        $f2 = $location_two->getLatitude(true); // Location two latitude in radians
        $df = $f2 - $f1; // Difference between the two locations' latitudes in radians
        $dh = $location_two->getLongitude(true) - $location_one->getLongitude(true); // Difference between the two locations' longitudes in radians

        // Calculates distance between locations using the ‘haversine’ formula
        $a = sin($df / 2) * sin($df / 2) +
             cos($f1) * cos($f2) *
             sin($dh / 2) * sin($dh / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $d = $earth_radius * $c; // Distance between both locations in metres

        // Just makes sure that $unit string parameter is never empty
        if(empty($unit)){
            $unit = "km";
        }

        // Prepares the final result
        $result = array(
            'distance' => 0,
            'unit' => $unit
        );

        switch($unit){
          case 'km':
          case 'kilometers':
                $result['distance'] = $format_distance?number_format($d / 1000, 3):($d / 1000);
                break;
          case 'm':
          case 'metres':
          case 'meters':
                $result['distance']  = $format_distance?number_format($d):(float)$d;
                break;
          case 'mi':
          case 'miles':
                $result['distance'] = ($d / 1000) * 0.621371192;
                if($format_distance){
                    $result['distance'] = number_format($result['distance'], 2);
                }
                break;
          default:
                throw new \Exception("Unsupported distance unit!", 300);
                break;
        }

        return $result;
   }

}
