<?php

use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrderFactory;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\FacetCollection;
/*use PrestaShop\PrestaShop\Core\Product\Search\FacetsRendererInterface;*/
use Symfony\Component\Translation\TranslatorInterface;

class ModmpProductSearchProvider implements ProductSearchProviderInterface/*, FacetsRendererInterface*/{
    private static $FACET_REGEX = '/\~[A-Za-z0-9]+\_[A-Za-z0-9\s]+/m';
    private static $FACETS_END_DELIMITER = '-'; // Should be a single character

    private $translator;
    private $sortOrderFactory;
    private $products = array();
    private $facets = array();
    private $facets_filters = array();

    public function __construct(TranslatorInterface $translator){
    	$this->translator = $translator;
    	$this->sortOrderFactory = new SortOrderFactory($this->translator);
    }

    private function addFacetFilters(string $facet_type, array $filters){
        $this->facets_filters[$facet_type] = $filters;
    }

    private function setFilterActive(string $facet_type, string $filter_value){
       $filters = $this->facets_filters[$facet_type];

       foreach($filters as $fvalue => $filter){
            if($fvalue === $filter_value){
                 $filters[$fvalue]->setActive(true);
            }
       }

       $this->facets_filters[$facet_type] = $filters;
    }

    private function getFacets(){
        $facets = array();
        foreach($this->facets as $facet){
           $facet_filters  = $this->facets_filters[$facet->getType()];
           if(!empty($facet_filters)){
                foreach($facet_filters as $fvalue => $filter){
                    $facet->addFilter($filter);
                }
                $facets[] = $facet;
           }
        }

        return $facets;
    }
   
    private function setActiveFacet(string $facet){
        if(empty($facet)){
            return;
        }

        $facet = str_replace("~", "", $facet);
        $facet = explode("_", $facet);
 
        $facet_type = $facet[0];
        $filter_value = $facet[1];

        $this->setFilterActive($facet_type, $filter_value);
    }

    private function setActiveFacets(array $facets){
        foreach($facets as $facet){
             $this->setActiveFacet($facet);
        }
    }

    private function getNextEncodedFacets(Facet $facet, Filter $filter, $encoded_facets_str){
        $encoded_facets  = $this->matchFacets($encoded_facets_str);
        $facet_string = "~".$facet->getType()."_".$filter->getValue();

        if(!in_array($facet_string, $encoded_facets)){
            $encoded_facets[] = $facet_string;
        }else{
             for($i=0;$i<count($encoded_facets);$i++){
                if($facet_string === $encoded_facets[$i]){
                    unset($encoded_facets[$i]);
                }
             }
        }

        $search_str = $this->cleanFacets($encoded_facets_str);
        if(!empty($search_str)){
           $encoded_facets[] = $search_str;
        }
    
        return implode("", $encoded_facets);
	}

    /** DEFAULT FACETS METHODS **/
    private function setDefaultFacets(ProductSearchContext $context, ProductSearchQuery $query){
        $facet_types = array("City", "Neighbourhood", "Availability");

        foreach($facet_types as $facet_type){
            $method = "getDefault{$facet_type}Facet";
            $this->facets[] = $this->$method($context, $query);
        }
    }

    private function getDefaultCityFacet(ProductSearchContext $context, ProductSearchQuery $query){     
        $products_cities = $this->getAllProductCities($context, $query, Configuration::get('FILTER_SETTINGS_DISPLAY_EMPTY'));

        $facet = new Facet();
        $facet->setType("City");
        $facet->setLabel($this->getFacetLabelString($facet->getType()));
        $facet->setWidgetType("checkbox");
        $facet->setDisplayed(true);
    

        $filters = array();
        foreach($products_cities as $pcity => $pcount){
            $filter = new Filter();
            $filter->setType("CityName")
                   ->setLabel($this->getFilterLabelString($pcity, $facet->getType()))
                   ->setValue($pcity)
                   ->setDisplayed(true)
                   ->setMagnitude($pcount)
                   ->setActive(false);
            $filter->setNextEncodedFacets($this->getNextEncodedFacets($facet, $filter, $this->getSearchString($query)));

            $filters[$pcity] = $filter;
        }

        $this->addFacetFilters($facet->getType(), $filters);

        return $facet;
    }

    private function getDefaultNeighbourhoodFacet(ProductSearchContext $context, ProductSearchQuery $query){
        $max_distance = array('distance'=>Configuration::get('FILTER_SETTINGS_NEIGHBOURHOOD_MAX_RANGE'), 'unit'=>Configuration::get('FILTER_SETTINGS_NEIGHBOURHOOD_UNIT'));

        $facet = new Facet();
        $facet->setType('Neighbourhood');
        $facet->setLabel($this->getFacetLabelString($facet->getType()));
        $facet->setDisplayed(true);

        $filters = array();

        $step = $max_distance['distance'] / 10;
        for($start = 0,$end = $step; $end <= $max_distance['distance']; $start=$end, $end+=$step){
            $label = "{$start}{$max_distance['unit']} - {$end}{$max_distance['unit']}";
            $value = str_replace(" - ", " ", $label);
            $products_count = $this->getProductsByNeighbourhood($value, $context, $query, true, true);

            if($products_count <= 0 && !Configuration::get('FILTER_SETTINGS_DISPLAY_EMPTY')){
                continue;
            }

            $filter = new Filter();
            $filter->setType("NeighbourhoodRange");
            $filter->setLabel($label);
            $filter->setValue($value);
            $filter->setDisplayed(true);
            $filter->setMagnitude($products_count);
            $filter->setActive(false);
            $filter->setNextEncodedFacets($this->getNextEncodedFacets($facet, $filter, $this->getSearchString($query)));

            $filters[$value] = $filter;
        }

        $this->addFacetFilters($facet->getType(), $filters);

        return $facet;
    }

    private function getDefaultAvailabilityFacet(ProductSearchContext $context, ProductSearchQuery $query){
        $facet = new Facet();
        $facet->setType("Availability")
              ->setLabel($this->getFacetLabelString($facet->getType()));

        $online_only_filter = new Filter();
        $online_only_filter->setType("OnlineProductsOnly")
                           ->setLabel($this->getFilterLabelString($online_only_filter->getType(), $facet->getType()))
                           ->setValue($online_only_filter->getType())
                           ->setMagnitude($this->countOnlineProducts($context, $query))
                           ->setActive(false)
                           ->setNextEncodedFacets($this->getNextEncodedFacets($facet, $online_only_filter, $this->getSearchString($query)));

        $physical_store_only_filter = new Filter();
        $physical_store_only_filter->setType("PhysicalProductsOnly")
                                   ->setLabel($this->getFilterLabelString($physical_store_only_filter->getType(), $facet->getType()))
                                   ->setValue($physical_store_only_filter->getType())
                                   ->setMagnitude($this->countPhysicalStoreProducts($context, $query))
                                   ->setActive(false)
                                   ->setNextEncodedFacets($this->getNextEncodedFacets($facet, $physical_store_only_filter, $this->getSearchString($query)));

        $this->addFacetFilters($facet->getType(), 
                               array($online_only_filter->getValue() => $online_only_filter, 
                                     $physical_store_only_filter->getValue() => $physical_store_only_filter)
                              );

        return $facet;
    }

    private function getVisitorCityName(){
    	 $visitor_current_location = Locator::getCurrentVisitorLocation();

         if($visitor_current_location){
    	    $visitor_city = Locator::getAddressLatLng($visitor_current_location->getLongAddress(), true);
         }

    	 return !empty($visitor_city)?$visitor_city:false;
    }

    private function getSearchString(ProductSearchQuery $query){
        if(!empty($query->getSearchString())){
            return $query->getSearchString();
        }else if(!empty($query->getEncodedFacets())){
            return $query->getEncodedFacets();
        }

        return "";
    }

    private function getStoresIdsByShopId(int $shop_id){
    	$db = DB::getInstance();
    	$sql = "SELECT `id_store` FROM `" . _DB_PREFIX_ . "store_shop` WHERE id_shop='{$shop_id}';";

    	return $db->executeS($sql);
    }

    private function getShopsByCity(string $city_name, int $id_lang = null){
    	$shops_ids_by_city = array();
    	$all_shops_ids = Shop::getShops(true, null, true);
    	foreach ($all_shops_ids as $shop_id) {
    		$shop_stores_ids = $this->getStoresIdsByShopId($shop_id);
    		foreach($shop_stores_ids as $store_id){
    			$store = new Store($store_id, $id_lang);
    			if($store->city == $city_name || $city_name == "none"){
    				$shops_ids_by_city[] = $shop_id;
    			}
    		}	
    	}

    	return array_unique($shops_ids_by_city);
    }

    private function setProducts(array $products){
        $this->products = array_merge($this->products, $products);
        $this->products = array_unique($this->products, SORT_REGULAR);
    }

    private function getProductsByShopId($shop_id, ProductSearchContext $context, ProductSearchQuery $query){
    	$all_products =  $this->getProductsByString($this->getSearchString($query), $context, $query, true);
    	$shop_products = array();

    	foreach ($all_products as $product) {
    		if($product['id_shop_default'] == $shop_id){
    			$shop_products[] = $product;
    		}
    	}

    	return $shop_products;
    }

    private function getProductsByMultipleShopIds(array $shop_ids, ProductSearchContext $context, ProductSearchQuery $query){
   		$products = array();
   		foreach($shop_ids as $shop_id){
   			$current_shop_products = $this->getProductsByShopId($shop_id, $context, $query);
   			$products = array_merge($products, $current_shop_products);
   		}

   		return array_unique($products, SORT_REGULAR);
    }

	private function getAllProducts(ProductSearchContext $context, ProductSearchQuery $query){
		 $all_products = Product::getProducts(
		 		$context->getIdLang(),
		 		$query->getPage(),
		 		$query->getResultsPerPage(),
				$query->getSortOrder()->toLegacyOrderBy(),
	            $query->getSortOrder()->toLegacyOrderWay(),
	            $query->getIdCategory(),
	            true
      	 );

		 return $all_products;
	}

	private function getProductsByString(string $search_string, ProductSearchContext $context, ProductSearchQuery $query, $ret = false){
    	$search_string = $this->cleanFacets($search_string);

        $sort_order = $query->getSortOrder();
        $order_by_distance = $sort_order->getField() === "distance";
       
        // Cleans facets delimiter from search string
        if(!empty($search_string) && $search_string[0] == self::$FACETS_END_DELIMITER){
            $search_string = substr($search_string, 1, strlen($search_string));
        }
 
        if(empty($search_string)){
        	$result = $this->getAllProducts($context, $query);
        	$result = array(
        		'total' => count($result),
        		'result' => $result
        	);
        }else{
        	$result = Search::find(
				$context->getIdLang(),
				$search_string,
				$query->getPage(),
				$query->getResultsPerPage(),
				$query->getSortOrder()->toLegacyOrderBy(),
	            $query->getSortOrder()->toLegacyOrderWay()
	        );
        }

        if(!$result){
        	return false;
        }

        if($order_by_distance){
            $result['result'] = $this->orderProductsByDistance($result['result'], $sort_order->getDirection());
        }

        if($ret){
        	return $result['result'];
        }else{
        	$this->setProducts($result['result']);
        	return true;
    	}
	}

	private function getProductsByCity(string $city_name, ProductSearchContext $context, ProductSearchQuery $query, bool $ret = false, bool $count = false){
		if(empty($city_name)){
			$city_name = "none";
		}else if(strtolower($city_name) == "mycity"){
		    $city_name = $this->getVisitorCityName();
		}

		// 1. Fetch all available shop's addresses
		// 2. Find shops which have city_name in their addresses
		// 3. Fetch all products that belong to these shops
		// 4. Put these products in $products property 
		// 5. return true on success

		$shops = $this->getShopsByCity($city_name, $context->getIdLang());
        $returned_products = $this->getProductsByMultipleShopIds($shops, $context, $query);

        if($ret){
            return $count?count($returned_products):$returned_products;
        }else{
    		$this->setProducts($returned_products);
	    }

		return !empty($this->products);
	}

	private function getProductsByMultipleCities(array $cities, ProductSearchContext $context, ProductSearchQuery $query){
		$allsucceed = false;

		foreach ($cities as $city) {
			$allsucceed = $this->getProductsByCity($city, $context, $query);
		}

		return $allsucceed;
	}

    private function getProductsByNeighbourhood(string $distance_range, ProductSearchContext $context, ProductSearchQuery $query, $return_result = false, $return_count = false){
        $regex = '/(?\'distance\'[0-9]+)(?\'unit\'[mki]+){1}/m';
        $matches = null;
        preg_match_all($regex, $distance_range, $matches);

        if(empty($matches)){
            return null;
        }
        
        $min_distance = (float)$matches['distance'][0];
        $max_distance = (float)$matches['distance'][1];
        $unit = $matches['unit'][0];

        $all_products = $this->getProductsByString($this->getSearchString($query), $context, $query, true);
  
        $result = array();
        foreach($all_products as $product){
            $product_distance = (float)$this->getProductVisitorDistance($product, $unit);
            if($product_distance >= $min_distance && $product_distance <= $max_distance){
                $result[] = $product;
            }
        }

        if($return_result){
            return $return_count?count($result):$result;
        }else{
            $this->setProducts($result);
        }

        return true;
    }

    private function getProductsByAvailability(string $filter_name, ProductSearchContext $context, ProductSearchQuery $query, $return_result = false){
        $result = array();

        switch($filter_name){
            case 'OnlineProductsOnly':
                $result = $this->getOnlineProducts($context, $query);
                break;
            case 'PhysicalProductsOnly':
                $result = $this->getPhysicalStoreProducts($context, $query);
                break;
            default:
                break;
        }     

        if($return_result){
             return $result;
        }else{
             $this->setProducts($result);
             return true;
        }
    }

    private function getAllProductCities(ProductSearchContext $context, ProductSearchQuery $query, bool $add_empty = true){
        $result = array();
 
        $all_stores = Store::getStores($context->getIdLang());
        foreach($all_stores as $store){
            $city = $store['city'];
            if(!array_key_exists($city, $result)){
                $products_count = $this->getProductsByCity($city, $context, $query, true, true);
                if($products_count > 0 || $add_empty){
                  $result[$city] = $products_count;
                }
            }
        }

        if(!empty($result)){
            $current_visitor_city = $this->getVisitorCityName();
            if(is_string($current_visitor_city) && array_key_exists($current_visitor_city, $result)){
                $result['MyCity'] = $result[$current_visitor_city];
                unset($result[$current_visitor_city]);
            }else{
                $result['MyCity'] = 0;
            }
        }
        
        return array_reverse($result);
    }

    private function getProductVisitorDistance(array $product, string $distance_unit = "m"){
        $product_address = $this->getProductAddress($product);
        $visitor_address = Locator::getCurrentVisitorLocation();

        if(!is_object($product_address) || !is_object($visitor_address)){
            return false;
        }

        $distance = Locator::calcDistance($product_address, $visitor_address, $distance_unit, false);

        return $distance['distance'];
    }

    private function getProductAddress(array $product){
        $address = null;

        $shop_id = $product['id_shop_default'];
        $stores_ids = $this->getStoresIdsByShopId($shop_id);
        if(!empty($stores_ids)){
            $store = new Store($stores_ids[0]);
            $address = new LocatorAddress($store->latitude, $store->longitude, "Unknown", $store->address1[1]);
        }

        return $address;
    }

    private function countOnlineProducts(ProductSearchContext $context, ProductSearchQuery $query){
        return count($this->getOnlineProducts($context, $query));
    }

    private function countPhysicalStoreProducts(ProductSearchContext $context, ProductSearchQuery $query){
        return count($this->getPhysicalStoreProducts($context, $query));
    }
    
    private function getOnlineProducts(ProductSearchContext $context, ProductSearchQuery $query){
        $products = $this->getProductsByString($this->getSearchString($query), $context, $query, true);

        $result = array();
        foreach($products as $product){
            if($this->isOnlyOnlineProduct($product)){
                $result[] = $product;
            }
        }

        return $result;
    }

    private function getPhysicalStoreProducts(ProductSearchContext $context, ProductSearchQuery $query){
        $products = $this->getProductsByString($this->getSearchString($query), $context, $query,true);

        $result = array();
        foreach($products as $product){
            if($this->hasPhysicalStore($product)){
                $result[] = $product;
            }
        }

        return $result;
    }

    private function isOnlyOnlineProduct(array $product){
        return !$this->hasPhysicalStore($product);
    }

    private function hasPhysicalStore(array $product){
        $store_address = $this->getProductAddress($product);
        return !empty($store_address);
    }

	private function getProductsCount(){
		return count($this->products);
	}

	private function getFacetLabelString(string $facet_type){
		if(empty($facet_type)){
			$facet_type = "None";
		}

		$label = $facet_type;

		switch ($facet_type) {
			case 'City':
				$label = "City";
				break;
			default:
				break;
		}

		return $this->translator->trans($label, array(), "Modmp.ProductSearchProviders.{$facet_type}Facet");
	}

	private function getFilterLabelString(string $filter_value, string $facet_type){
		if(empty($filter_value)){
			$filter_value = "none";
		}

		$label  = $filter_value;
		
		switch($filter_value){
			case 'MyCity': 
				$label = "Your current city";
				break;
            case 'OnlineProductsOnly':
                $label = "Online products";
                break;
            case 'PhysicalProductsOnly':
                $label = "Physical store products";
                break;
			default:
				break;
		}

		return $this->translator->trans($label, array(), "Modmp.ProductSearchProviders.{$facet_type}Facet.Filters");
	}

    private function getNearestFirstSortOrder(){
        $so = new SortOrder("product", "distance", "asc");
        $so->setLabel($this->translator->trans("Nearest first", array(), "Modmp.ProductSearchProviders.SortOrders"));
        return $so;
    }

    private function orderProductsByDistance(array $products, string $direction = "asc"){
        uasort($products, function($prod_one, $prod_two){
            $prod_one_distance = $this->getProductVisitorDistance($prod_one);
            $prod_two_distance = $this->getProductVisitorDistance($prod_two);

            if($prod_one_distance == $prod_two_distance){
                return 0;
            }

            if($direction == "desc"){
                return ($prod_one_distance > $prod_two_distance)?-1:1;
            }

            return ($prod_one_distance < $prod_two_distance)?-1:1;
        });

        return $products;
    }

    private function hasActiveFacets(string $search_string){
         return $this->matchFacets($search_string, true) > 0;
    }

	private function matchFacets(string $encoded_facets, bool $count = false){
		$matched_facets = null;
		preg_match_all(self::$FACET_REGEX, $encoded_facets, $matched_facets);
		return $count?count($matched_facets[0]):$matched_facets[0];
	}

	private function cleanFacets(string $encoded_facets){
			return @preg_replace(self::$FACET_REGEX, "", $encoded_facets);
	}

	public function runQuery(ProductSearchContext $context, ProductSearchQuery $query){
        $search_string = $this->getSearchString($query);
        
        $this->setDefaultFacets($context, $query);
        $this->setActiveFacets($this->matchFacets($search_string));

        $result = new ProductSearchResult();

        $facets = $this->getFacets();

        // Get all products corresponding to active facets and the rest part of search string
        foreach($facets as $facet){
            foreach($facet->getFilters() as $filter){
                if($filter->isActive()){
                    $method = "getProductsBy".$facet->getType();
                    $this->$method($filter->getValue(), $context, $query);
                }
            }
        }
       
        // If there are no active facets - then we try to find products corresponding to search query string.
        // In case the string is empty - we get all active products. 
		if(!$this->hasActiveFacets($search_string)){
			$this->getProductsByString($search_string, $context, $query);
		}

		$products_count = $this->getProductsCount();
		$products = $this->products;

		$sort_orders = $this->sortOrderFactory->getDefaultSortOrders();
        $sort_orders[] = $this->getNearestFirstSortOrder();

        if(!isset($_REQUEST['order'])){
            $query->setSortOrder($sort_orders[1]);
        }

		$facet_collection = new FacetCollection();
		$facet_collection->setFacets($facets);

		$result->setProducts($products)
			   ->setTotalProductsCount($products_count)
			   ->setAvailableSortOrders($sort_orders)
			   ->setCurrentSortOrder($query->getSortOrder())
			   ->setEncodedFacets($this->getSearchString($query))
			   ->setFacetCollection($facet_collection);

		return $result;
	}

	/*public function renderFacets(ProductSearchContext $context, ProductSearchResult $result){

	}


	public function renderActiveFilters(ProductSearchContext $context, ProductSearchResult $result){
		
	}*/
}
