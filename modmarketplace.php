<?php

if (!defined('_PS_VERSION_')){
  exit;
}

include_once(_PS_MODULE_DIR_.'modmarketplace/classes/Locator.class.php');
include_once(__DIR__ . '/classes/providers/search/product/ModmpProductSearchProvider.php');

class ModMarketplace extends Module
{
  private $templateFiles = array();
  private $hooksList = array();

  public function __construct()
  {
    $this->name = 'modmarketplace';
    $this->tab = 'front_office_features';
    $this->version = '1.0.0';
    $this->author = 'Dian Kirilov';
    $this->need_instance = 0;
    $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_); 
    $this->bootstrap = true;
 
    parent::__construct();
 
    $this->displayName = $this->l('ModMarketplace');
    $this->description = $this->l('Description of my module.');
 
    $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

    $this->init();
  }

  private function init(){
      $this->setHooksList();
      $this->tryRegisterHooks();
      $this->setTemplateFiles();
  }

  public function install(){
        return parent::install() 
                && $this->addProductDistanceTableColumn()
                && $this->installDefaultConfigurations()
                && $this->uninstallPsFacetedSearchModule(); // Uninstalls PS Faceted Search module because it blocks 
  }                                                         // this module's search provider.

  public function uninstall(){
        return parent::uninstall() 
                && $this->unregisterHooks() 
                && $this->uninstallDefaultConfigurations()
                && $this->removeProductDistanceTableColumn()
                && $this->installPsFacetedSearchModule();
  }

  private function setHooksList(){
       $this->hooksList = array(
               'actionFrontControllerSetMedia', 'actionAdminControllerSetMedia', 'productSearchProvider',
               'displayCustomerAccountFormTop',  'displayLocationBlock', 'displayTop', 
               'displayProductDistance', 'displayProductListReviews', 'displayReassurance', 
               'createAccountForm'
       );

       $theme_name = $this->context->shop->theme_name;
       if($theme_name == 'classic'){
            $this->hooksList[] = 'displayProductAdditionalInfo';
       }else if($theme_name == 'leo_bicmart'){
            $this->hooksList[] = 'displayLeoProductListReview';
       }
  }

  private function getDefaultConfigurations(){
      return array(
            'GEOCODE_API_KEY' => 'AIzaSyC4YCLenT3TrHTnNJwnIWMaZQXlAln1ao0',
            'FILTER_SETTINGS_DISPLAY_EMPTY' => true,
            'FILTER_SETTINGS_NEIGHBOURHOOD_MAX_RANGE' => 1300,
            'FILTER_SETTINGS_NEIGHBOURHOOD_UNIT' => 'm',
            'SELLER_MAX_SHOPS_ALLOWED' => 0 // 0 = unlimited
      );
  }

  private function installDefaultConfigurations(){
      $default_settings = $this->getDefaultConfigurations();      

      foreach($default_settings as $sname => $svalue){
               Configuration::updateValue($sname, $svalue);
      }

      return true;
  }

  private function uninstallDefaultConfigurations(){
      $default_settings = array_keys($this->getDefaultConfigurations());      

      foreach($default_settings as $setting){
               Configuration::deleteByName($setting);
      }

      return true;    
  }

  
  private function uninstallPsFacetedSearchModule(){
       $uninstall_success = false;

       if(Module::isInstalled('ps_facetedsearch')){     
            $instance = Module::getInstanceByName('ps_facetedsearch');
            if($instance){
                $uninstall_success = $instance->uninstall();
            }
       }else{
            $uninstall_success = true;
       }

       return $uninstall_success;
  }

  private function installPsFacetedSearchModule(){
       $install_success = false;

       if(!Module::isInstalled('ps_facetedsearch')){     
            $instance = Module::getInstanceByName('ps_facetedsearch');
            if($instance){
                $install_success = $instance->install();
            }
       }else{
            $install_success = true;
       }

       return $install_success;
  }

  private function setTemplateFiles(){
        $this->templateFiles = array(
            'location-block' => 'module:modmarketplace/views/templates/hook/location-block.tpl',
            'seller-signup' => 'module:modmarketplace/views/templates/hook/seller-signup.tpl',  
            'locations-select' => 'module:modmarketplace/views/templates/forms/locations-select.tpl',
            'product-distance' => 'module:modmarketplace/views/templates/hook/product-distance.tpl',
            'product-additional-info' => 'module:modmarketplace/views/templates/hook/product-additional-info.tpl',
        );
  }

  private function tryRegisterHooks(){
        $no_errors = true;
       
        foreach($this->hooksList as $hook_name){
            $no_errors = $this->registerHook($hook_name);
        }

        return $no_errors;
  }

  private function unregisterHooks(){
        $no_errors = true;

        foreach($this->hooksList as $hook_name){
            $no_errors = $this->unregisterHook($hook_name);  
        }

        return $no_errors;
  }

  private function addProductDistanceTableColumn(){
        $sql = "ALTER TABLE `"._DB_PREFIX_."product` ADD `distance` FLOAT NOT NULL DEFAULT '0' AFTER `state`;";
        return Db::getInstance()->execute($sql);
  }

  private function removeProductDistanceTableColumn(){
        $sql = "ALTER TABLE `"._DB_PREFIX_."product` DROP COLUMN `distance`;";
        return Db::getInstance()->execute($sql);
  }

  public function getContent(){
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)){
            $geocodeApiKey = strval(Tools::getValue('GEOCODE_API_KEY'));
            $displayEmptyFilters = (bool)Tools::getValue('FILTER_SETTINGS_DISPLAY_EMPTY');
            $neighbourhoodFilterMaxRange = floatval(Tools::getValue('FILTER_SETTINGS_NEIGHBOURHOOD_MAX_RANGE'));
            $neighbourhoodFilterUnit = strval(Tools::getValue('FILTER_SETTINGS_NEIGHBOURHOOD_UNIT'));
            $sellerMaxShops = (int)Tools::getValue('SELLER_MAX_SHOPS_ALLOWED');

            if(empty($geocodeApiKey)){
                $output .= $this->displayError($this->l('Geocode API key is empty!'));
            }

            if(empty($neighbourhoodFilterMaxRange) || !Validate::isFloat($neighbourhoodFilterMaxRange)){
                $output .= $this->displayError($this->l('Invalid value passed for "Neighbourhood max range"!'));
            }

            if(empty($neighbourhoodFilterUnit) || !Validate::isGenericName($neighbourhoodFilterUnit)){
                $output .= $this->displayError($this->l('Invalid value passed for "Neighbourhood max range"\'s unit!'));
            }

            if(!Validate::isInt($sellerMaxShops) || $sellerMaxShops < 0){
                $output .= $this->displayError($this->l('Invalid value passed for "Seller max shops"!'));
            }

            if(empty($output)){
                Configuration::updateValue('GEOCODE_API_KEY', $geocodeApiKey);
                Configuration::updateValue('FILTER_SETTINGS_DISPLAY_EMPTY', $displayEmptyFilters);
                Configuration::updateValue('FILTER_SETTINGS_NEIGHBOURHOOD_MAX_RANGE', $neighbourhoodFilterMaxRange);
                Configuration::updateValue('FILTER_SETTINGS_NEIGHBOURHOOD_UNIT', $neighbourhoodFilterUnit);
                Configuration::updateValue('SELLER_MAX_SHOPS_ALLOWED', $sellerMaxShops);

                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output.$this->displayForm();
  }

  private function displayForm(){
        // Get default language
        $defaultLang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Google API settings
        $fieldsForm[0]['form'] = [
            'legend' => [
                 'title' => $this->l('Google API settings')
            ],
            'input' => [
                   [
                     'type' => 'text',
                     'label' => $this->l('Geocode API key:'),
                     'name' => 'GEOCODE_API_KEY',
                     'size' => 20,
                     'required' => true
                   ]
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        // Sellers control
        $fieldsForm[1]['form'] = [
            'legend' => [
                 'title' => $this->l('Sellers control')
            ],
            'input' => [
                   [
                     'type' => 'text',
                     'label' => $this->l('Seller max shops(0=unlimited):'),
                     'name' => 'SELLER_MAX_SHOPS_ALLOWED',
                     'size' => 20,
                     'required' => true
                   ]
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        // Filters settings
        $fieldsForm[2]['form'] = [
            'legend' => [
                'title' => $this->l('Filters settings'),
            ],
            'input' => [
                [
                    'type' => 'radio',
                    'label' => $this->l('Display empty filters'),
                    'name' => 'FILTER_SETTINGS_DISPLAY_EMPTY',
                    'is_bool' => true,
                    'values' => [
                        [
                            'id' => 'FILTER_SETTINGS_DISPLAY_EMPTY_YES',
                            'value' => 1,
                            'label' => $this->l('Yes')
                        ],

                        [
                            'id' => 'FILTER_SETTINGS_DISPLAY_EMPTY_NO',
                            'value' => 0,
                            'label' => $this->l('No')
                        ]
                    ]
                ],
    
                [
                    'type' => 'text',
                    'label' => $this->l('Neighbourhood max range:'),
                    'name' => 'FILTER_SETTINGS_NEIGHBOURHOOD_MAX_RANGE',
                    'size' => 20,
                    'required' => true
                ],

                [
                    'type' => 'select',
                    'label' => $this->l('Neighbourhood range unit:'),
                    'name' => 'FILTER_SETTINGS_NEIGHBOURHOOD_UNIT',
                    'required' => true,
                    'options' => [
                         'query' => [ // Here are the options
                              // "Metres" option:
                              [
                                  'id_option' => 'm',
                                  'name' => $this->l('Metres')
                              ],
                              // "Kilometers" option:
                              [
                                  'id_option' => 'km',
                                  'name' => $this->l('Kilometers')
                              ],
                              // "Miles" option:
                              [
                                  'id_option' => 'mi',
                                  'name' => $this->l('Miles')
                              ]
                         ],
                         'id' => 'id_option',
                         'name' => 'name'
                    ]
                ]
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = $defaultLang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;        // false -> remove toolbar
        $helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            ]
        ];

        // Load current value
        $helper->fields_value['FILTER_SETTINGS_DISPLAY_EMPTY'] = Configuration::get('FILTER_SETTINGS_DISPLAY_EMPTY');
        $helper->fields_value['FILTER_SETTINGS_NEIGHBOURHOOD_MAX_RANGE'] = Configuration::get('FILTER_SETTINGS_NEIGHBOURHOOD_MAX_RANGE');
        $helper->fields_value['FILTER_SETTINGS_NEIGHBOURHOOD_UNIT'] = Configuration::get('FILTER_SETTINGS_NEIGHBOURHOOD_UNIT');
        $helper->fields_value['GEOCODE_API_KEY'] = Configuration::get('GEOCODE_API_KEY');
        $helper->fields_value['SELLER_MAX_SHOPS_ALLOWED'] = Configuration::get('SELLER_MAX_SHOPS_ALLOWED');

        return $helper->generateForm($fieldsForm);
  }

 public function hookActionAdminControllerSetMedia($params){
    // Register JS
    //$this->context->controller->addJS(__PS_BASE_URI__  . '/modules/' . $this->name . '/js/admin/StockLocationSelect.js');
 }

  public function hookActionFrontControllerSetMedia($params)
  {
    // Register JS
    $this->context->controller->registerJavascript(
        'google-maps-api',
        'https://maps.googleapis.com/maps/api/js?key='. Configuration::get('GEOCODE_API_KEY') .'&callback=apiReady',
        [
          'position' => 'bottom',
          'inline' => false,
          'priority' => 90,
          'attributes' => 'async',
          'server' => 'remote'
        ]
    );
    $this->context->controller->registerJavascript(
        'modmp-cookie',
        'modules/'.$this->name.'/js/ModmpCookie.js',
        [
          'position' => 'bottom',
          'inline' => false,
          'priority' => 91,
        ]
    );    
    $this->context->controller->registerJavascript(
        'modmp-location-block',
        'modules/'.$this->name.'/js/LocationBlock.js',
        [
          'position' => 'bottom',
          'inline' => false,
          'priority' => 99,
        ]
    );
    $this->context->controller->registerJavascript(
        'modmp-locator',
        'modules/'.$this->name.'/js/Locator.js',
        [
          'position' => 'bottom',
          'inline' => false,
          'priority' => 100,
        ]
    );
    $this->context->controller->registerJavascript(
        'modmp-seller-signup',
        'modules/'.$this->name.'/js/SellerSignup.js',
        [
          'position' => 'bottom',
          'inline' => false,
          'priority' => 100,
        ]
    );
    $this->context->controller->registerJavascript(
        'modmp-location-map',
        'modules/'.$this->name.'/js/LocationMap.js',
        [
          'position' => 'bottom',
          'inline' => false,
          'priority' => 100,
        ]
    );
  
    // Register CSS
    $this->context->controller->registerStylesheet(
        'modmp-style',
        'modules/'.$this->name.'/css/modmp-style.css',
        [
          'priority' => 10
        ]
    );

  }

  public function hookProductSearchProvider($params){
      $modmp_psp = new ModmpProductSearchProvider($this->getTranslator());
      return $modmp_psp;
  }

  public function hookDisplayLocationBlock($params){
       $this->smarty->assign(array(
            'locationicon' => $this->_path.'icons/location-pin-icon-3.png',
       ));

      return $this->fetch($this->templateFiles['location-block']);
  }

  public function hookDisplayTop($params){
      return $this->hookDisplayLocationBlock($params);
  }

  public function hookCreateAccountForm($params){
       $this->smarty->assign(array(
            'max_shops' => Configuration::get('SELLER_MAX_SHOPS_ALLOWED')
       ));

       return $this->fetch($this->templateFiles['seller-signup']);
  }

  public function hookDisplayCustomerAccountFormTop($params){
      $db = DB::getInstance();

      $all_values = Tools::getAllValues();

      $marketplace_group = $this->getMarketplaceShopGroup($params);    
      
      $default_lang_id = (int) Configuration::get('PS_LANG_DEFAULT');
      $default_shop_id = (int) Configuration::get("PS_SHOP_DEFAULT");
      
      $max_shops =  Configuration::get('SELLER_MAX_SHOPS_ALLOWED');

      if(isset($all_values['create_account']) && $_SERVER['REQUEST_METHOD'] == "POST"){  
          if(!empty($all_values['has_physical_store'])){
              for($current_addr = 1; $current_addr<count($all_values); $current_addr++ ){
                  if(isset($all_values['store_name_'.$current_addr])){

                      if(!empty($max_shops) && $max_shops > 0 && $current_addr > $max_shops){
                            return $this->displayError($this->l("Too much physical stores were submitted! Please remove the necessary number of physical stores locations and try to submit the form again. The max number of physical stores locations you can submit is $max_shops."));
                      }

                      // If the seller has checked the has_physical_store checkbox and has entered physical store addresses, then for each address we do as follows:
                      // 1. Create new shop 
                      // 2. Add new Shop URL and associate it with the newly created shop
                      // 3. Add new employee(if not exists) and associate the new shop with it
                      // 4. Create new store(physical store address) and associate it with the new shop and it's owner(employee user) as well
                    
                      // Create new shop
                      $newshop = $this->addNewShop($db, "noname", $params, $all_values, $current_addr);

                      // Add new shop url
                      $this->addNewShopUrl($newshop);

                      // Add new employee
                      $new_employee = $this->addNewEmployee($db, $newshop, $all_values, $params);

                      // Create new store(it contains current physical store's address)
                      $address = array(
                          'store_name' => $all_values['store_name_'.$current_addr],
                          'house_number' => $all_values['house_number_'.$current_addr],
                          'street' => $all_values['street_'.$current_addr],
                          'city' => $all_values['city_'.$current_addr],
                          'postcode' => $all_values['postcode_'.$current_addr],
                          'country' => $all_values['country_'.$current_addr]   
                      );
                      $new_store_id = $this->addNewStore($db, $address, $newshop);                   
                  }
              }
          }else{
              // In case the seller hasn't checked the checkbox, we do following actions:
              // 1. Create new shop
              // 2. Add new Shop URL and associate it with the newly created shop
              // 3. Add new employee(if not exists) and associate the new shop with it

              // Create new shop
              $newshop = $this->addNewShop($db, "noname", $params, $all_values);

              // Add new shop url
              $this->addNewShopUrl($newshop);

              // Add new employee
              $new_employee = $this->addNewEmployee($db, $newshop, $all_values, $params);
          }
      }
  }

  public function hookDisplayLeoProductListReview($params){
      return $this->hookDisplayProductListReviews($params);
  }

  public function hookDisplayProductListReviews($params){
      return $this->hookDisplayProductDistance($params);
  }

  public function hookDisplayProductDistance($params){
      $product_id = null;

      if(isset($params['product'])){
         $product_id = $params['product']['id_product'];
      }else if(isset($params['smarty'])){
         if(isset($params['smarty']->tpl_vars['product']->value['id'])){
            // This line doesn't work on PS 1.7.6
            $product_id = $params['smarty']->tpl_vars['product']->value['id'];
         }else{
             // This line works on PS 1.7.6.
             $product_id = $params['smarty']->tpl_vars['product']->value->getId();
         }
      }

      if(!$product_id){
          return;
      }

      $product_location = $this->getProductLocation($product_id);
      $visitor_location = Locator::getCurrentVisitorLocation();

      if(!$product_location || !$visitor_location){
          return "";
      }

      $distance = Locator::calcDistance($product_location, $visitor_location);

      $this->context->smarty->assign(array(
            'locationicon' => $this->_path.'icons/location-pin-icon-3.png',
            'distance' => $distance['distance'],
            'unit' => $distance['unit']
      ));

      return $this->fetch($this->templateFiles['product-distance']);
  }

  public function hookDisplayProductAdditionalInfo($params){
      $product = $this->context->controller->getProduct();

      $product_location = $this->getProductLocation($product->id);
      
      if(!$product_location){
         return "";
      }
      
      $this->context->smarty->assign(array(
            'location' => array('latitude'=>$product_location->getLatitude(), 'longitude'=>$product_location->getLongitude())
      ));

      return $this->fetch($this->templateFiles['product-additional-info']);
  }

  /* This hook is needed to display product location map and distance when using Leo Bicmart theme */
  public function hookDisplayReassurance($params){
      return $this->hookDisplayProductAdditionalInfo($params);
  }

/*  public function hookDisplayOverrideTemplate($params){
      return;
  }*/

  private function getMarketplaceShopGroup(array $params = null, string $group_name = "Marketplace"){
      $shopgroups = ShopGroup::getShopGroups()->getResults();
      $marketplacegrp = null;
      foreach ($shopgroups as $shopgrp) {
         if($shopgrp->name == $group_name){
            $marketplacegrp = $shopgrp;
            break;
         }
      }

      if(!$marketplacegrp){
          $this->createMarketplaceShopGroup($params, $group_name);
          $this->getMarketplaceShopGroup($params, $group_name);                
      }

      return $marketplacegrp;
  }

  private function createMarketplaceShopGroup(array $params = null, string $group_name = "Marketplace"){
      $lang_id = $this->getLangId($params);
      $shop_id = $this->getShopId($params);
          
      $shopgrp = new ShopGroup(null, $lang_id, $shop_id);
      $shopgrp->name = $group_name;
      return $shopgrp->save();
  }

  private function getSellerProfile(array $params = null, string $profile_name = "MarketplaceSeller"){
      $lang_id = $this->getLangId($params);

      if(!$this->profileExists($profile_name, $lang_id)){
          $this->createSellerProfile($lang_id, $params, $profile_name);
      }
     
      $profiles = Profile::getProfiles($lang_id);
      foreach ($profiles as $profile) {
         if($profile['name'] == $profile_name){
            return $profile;
         }
      }
  }

  private function createSellerProfile(int $lang_id, array $params = null, string $profile_name = "MarketplaceSeller"){
      $success = false;

      $shop_id = $this->getShopId($params);

      // Creates new profile
      $seller_profile = new Profile(null, $lang_id, $shop_id);
      $seller_profile->name = $profile_name;
      $seller_profile->add();

      // Gets new profile details
      $seller_profile = $this->getProfileDirect($profile_name, $lang_id);
      $id_profile = $seller_profile['id_profile'];

      // Sets permissions for the new profile
      $default_roles = $this->getDefaultAuthRoles();
      foreach($default_roles as $role){
            $role = $role['id_authorization_role'];
            $sql = "INSERT INTO `"._DB_PREFIX_."access` VALUES($id_profile, $role);";
            $success = Db::getInstance()->execute($sql);
            if(!$success){
                return false;
            }
      }     

      return $success;
  }

  private function getDefaultAuthRoles(){
       $sql = "SELECT id_authorization_role FROM `"._DB_PREFIX_."authorization_role` WHERE slug LIKE 'ROLE_%';";
       $db = Db::getInstance();
       return $db->executeS($sql);     
  }

  private function profileExists(string $profile_name, int $id_lang){
       $result = $this->getProfileDirect($profile_name, $id_lang);
       return !empty($result);
  }

  private function getProfileDirect(string $profile_name, int $id_lang){
       $sql = "SELECT * FROM `"._DB_PREFIX_."profile_lang` WHERE id_lang='$id_lang' AND name='$profile_name';";
       $db = Db::getInstance();
       $results = $db->executeS($sql);

       if(count($results) == 0){
            return null;
       }

       return $results[0];
  }

  private function getStoreByName($store_name){
        $stores = Store::getStores(Configuration::get('PS_LANG_DEFAULT'));
        foreach ($stores as $store) {
            if($store['name'] == $store_name){
                return $store;
            }
        }

        return false;
  }

  private function getStoreIdByName($store_name){
        return $this->getStoreByName($store_name)['id'];
  }

  private function getDefaultStoreHours(){
        $stores = Store::getStores(Configuration::get('PS_LANG_DEFAULT'));
        foreach($stores as $store){
          if(!empty($store['hours'])){
              return $store['hours'];
          }
        }

        return false;
  }

  private function getShopId(array $params = null){
      $shop_id = null;

      if(isset($params['smarty'])){
          $shop_id = Shop::getIdByName($params['smarty']->tpl_vars['shop']->value['name']);
      }else if(isset($this->context->shop)){
          $shop_id = $this->context->shop->id;
      }else{
          $shop_id = (int) Configuration::get("PS_SHOP_DEFAULT");
      }

      return $shop_id;  
  }

  private function getLangId(array $params = null){
      $lang_id = null;

      if(isset($params['smarty'])){
         $lang_id = $params['smarty']->tpl_vars['language']->value['id'];
      }else if(isset($this->context->cookie)){
         $lang_id = $this->context->cookie->__get('id_lang');
      }

      if(!$lang_id){
         $lang_id = (int) Configuration::get("PS_LANG_DEFAULT");
      }

      return $lang_id;
  }

  private function addNewShop(DB $db, string $name = "noname", array $params = null, array $all_values = null, int $current_address = -1){
      $marketplace_group = $this->getMarketplaceShopGroup($params);    
      $default_lang_id = (int) Configuration::get('PS_LANG_DEFAULT');
      $default_shop_id = (int) Configuration::get("PS_SHOP_DEFAULT");

      if($name == "noname"){
         if(isset($all_values['company_name'])){
            $name = $all_values['company_name'] . ' shop';
         }else if(isset($all_values['firstname'])){
            $name = $all_values['firstname'].' '.$all_values['lastname'].'\'s shop';
         }
      }

      $name .= $current_address>-1?' #'.$current_address:'';

      // Create new Shop
      $newshop = new Shop(null, $default_lang_id);
      $newshop->copyShopData(null, Shop::getAssoTables());
      $newshop->id_shop_group = $marketplace_group->id;
      $newshop->id_category = $this->context->shop->id_category;
      $newshop->name = $name;
      $newshop->theme_name = $this->context->shop->theme_name;
      $newshop->save();
      $newshop_id = Shop::getIdByName($newshop->name);

      // Associate current shop with seller's currency
      $visitor_currency_id = $this->context->currency->id;
      $visitor_currency_rate = $this->context->currency->conversion_rate;
      $sql = "INSERT INTO `". _DB_PREFIX_ . "currency_shop`(id_currency, id_shop, conversion_rate) VALUES({$visitor_currency_id}, {$newshop_id}, {$visitor_currency_rate});";
      $db->execute($sql);
  
      return $newshop_id?array( 'object' => $newshop, 'id' => $newshop_id):false;
  }

  private function addNewShopUrl(array $shop){
      if(empty($shop)){
          return false;
      }
      
      $default_lang_id = (int) Configuration::get('PS_LANG_DEFAULT');

      // Create new shop url 
      $shopurl = new ShopUrl(null, $default_lang_id, $shop['id']);
      $shopurl->id_shop = $shop['id'];
      $shopurl->active = true;
      $shopurl->domain = $this->context->shop->domain;
      $shopurl->domain_ssl = $this->context->shop->domain_ssl;
      $shopurl->physical_uri = $this->context->shop->physical_uri;
      $shopurl->virtual_uri = str_replace("-" , "", Tools::str2url(str_replace("'s", "", $shop['object']->name)));
      $shopurl->setMain();
      $shopurl_saved = (bool) $shopurl->save();
      if($shopurl_saved){
          Tools::generateHtaccess();
          Tools::generateRobotsFile();
          Tools::clearSmartyCache();
          Media::clearCache();
      }

      return $shopurl_saved;
  }

  private function addNewEmployee(DB $db, $shop, array $all_values, array $params = null){
      if(empty($db) || empty($shop) || empty($all_values)){
          return false;
      }

      $default_lang_id = (int) Configuration::get('PS_LANG_DEFAULT');
      $default_shop_id = (int) Configuration::get("PS_SHOP_DEFAULT");
      $employee_profile = $this->getSellerProfile($params);

      // Add new employee if it doesn't exist
      $employee = new Employee(null, $default_lang_id, $shop['id']);
      $employee_existing = $employee->getByEmail($all_values['email']);
      if(!$employee_existing){
          $employee->firstname = $all_values['firstname'];
          $employee->lastname = $all_values['lastname'];
          $employee->email = $all_values['email'];
          $employee->id_lang = $default_lang_id;
          $employee->passwd = Tools::encrypt($all_values['password']);
          $employee->id_profile = $employee_profile['id_profile'];
          $employee->save();
          $employee = $employee->getByEmail($employee->email);
      }else{
          $employee = $employee_existing;
      }

      if($shop['id']){
          $shop_id = $shop['id'];

          // Associates newly created shop with the newly created empoyee
          $sql = "INSERT INTO `" . _DB_PREFIX_ . "employee_shop`(id_employee, id_shop) VALUES({$employee->id}, {$shop_id});";
          $db->execute($sql);

          // Unassociates new employee with the default shop
          $sql = "DELETE FROM `" . _DB_PREFIX_ . "employee_shop` WHERE id_employee='{$employee->id}' AND id_shop='{$default_shop_id}';";
          $db->execute($sql);
      }

      return $employee;
  }

  private function addNewStore(DB $db, array $address, $shop){
      if(empty($db) || empty($address) || empty($shop)){
          return false;
      }

      $default_lang_id = Configuration::get('PS_LANG_DEFAULT');
      $default_shop_id = (int) Configuration::get("PS_SHOP_DEFAULT");
      $country_id = Country::getIdByName($default_lang_id, $address['country']);

      $store = new Store(null, $default_lang_id);
      $store->id_country = $country_id;
      $store->name = $address['store_name'];
      $store->address1 = $address['street'] . ' ' . $address['house_number'];
      $store->postcode = $address['postcode'];
      $store->city = $address['city'];
      $store_latlng  = Locator::getAddressLatlng($store->postcode . ', ' 
                                                 . $store->address1 . ', '
                                                 . $store->city . ', '
                                                 . $address['country']
                       );
      $store->latitude = $store_latlng->getLatitude();
      $store->longitude = $store_latlng->getLongitude();
      $store->hours = $this->getDefaultStoreHours();
      $store->save();

      $new_store_id = $this->getStoreIdByName($store->name);

      $shop_id = $shop['id'];

      if($shop_id !== $default_shop_id){
          // Associate newly created store to the last created shop
          $sql = "INSERT INTO `" . _DB_PREFIX_ . "store_shop`(id_store, id_shop) VALUES({$new_store_id}, {$shop_id});";
          $db->execute($sql);

          // Unassociate newly created store with the default shop
          $sql = "DELETE FROM `" . _DB_PREFIX_ . "store_shop` WHERE id_store={$new_store_id} AND id_shop={$default_shop_id};";
          $db->execute($sql);
      }

      return $new_store_id;
  }

  private function getProductLocation($product_id){
      $product = new Product($product_id);
      $shop_id = $product->id_shop_default;
      $store = $this->getStoreByShopId($shop_id);

      if(!empty($store)){
          $product_location = new LocatorAddress($store->latitude, $store->longitude, "Unknown", "Unknown");

          return $product_location;
      }

      return false;
  }

  private function getStoreByShopId(int $shop_id){
      $store = null;

      $db = DB::getInstance();

      $storeid_sql = "SELECT id_store FROM `" . _DB_PREFIX_ . "store_shop` WHERE id_shop=$shop_id;";
      $result = $db->executeS($storeid_sql);
      if($result){
          $id_store = $result[0]['id_store'];
          $store = new Store($id_store, (int)Configuration::get('PS_LANG_DEFAULT'));
      }

      return $store;
  }

}
