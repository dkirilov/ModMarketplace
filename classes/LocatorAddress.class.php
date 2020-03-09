<?php

class LocatorAddress{
    private $latitude;
    private $longitude;
    private $short_address;
    private $long_address;

    public function __construct(float $latitude, float $longitude, string $short_address, string $long_address){
        $this->setLatitude($latitude);
        $this->setLongitude($longitude);
        $this->setShortAddress($short_address);
        $this->setLongAddress($long_address);
    }

    private function setLatitude(float $latitude){
        if($latitude < -90 || $latitude > 90){
            throw new \Exception("Invalid Latitude! It should be a float number in the range between -90 and +90.");
        }

        $this->latitude = $latitude;
    }

    private function setLongitude(float $longitude){
        if($longitude < -180 || $longitude > 180){
            throw new \Exception("Invalid longitude! It should be a float number in the range between -180 and +180.");
        }

        $this->longitude = $longitude;
    }

    private function setShortAddress(string $short_address){
        if(empty($short_address)){
            throw new \Exception("Short address is empty!",232);
        }

        if(strlen($short_address) > 70){
            throw new \Exception("Short address is too long! It must be max 70 characters long.", 233);            
        }

        $this->short_address = $short_address;
    }

    private function setLongAddress(string $long_address){
        if(empty($long_address)){
            throw new \Exception("Long address is empty!",234);
        }

        if(strlen($long_address) > 120){
            throw new \Exception("Long address is too long! It must be max 120 characters long.", 235);  
        }

        $this->long_address = $long_address;
    }

    public function getLatitude($radians = false){
        if($radians){
            return deg2rad($this->latitude);
        }

        return $this->latitude;
    }

    public function getLongitude($radians = false){
        if($radians){
            return deg2rad($this->longitude);
        }

        return $this->longitude;
    }

    public function getShortAddress(){
        return $this->short_address;
    }

    public function getLongAddress(){
        return $this->long_address;
    }
}