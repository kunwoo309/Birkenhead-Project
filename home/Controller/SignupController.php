<?php

App::uses('AppController', 'Controller');
App::uses('CakeTime', 'Utility');

class SignupController extends AppController {

/**
 * Components property.
 *
 * @var array
 */
    public $components = array('RequestHandler');

/**
 * Name of layout to use with this View.
 *
 * @var string
 */
    public $layout = 'signup';

/**
 * This controller uses the following models.
 *
 * @var array
 */
    public $uses = array(
        'Contact',
        'Merchant',
        'MerchantRetailer',
        'Signup',
        'Subscriber'
    );

/**
 * Callback is called before any controller action logic is executed.
 *
 * @return void
 */
    public function beforeFilter() {
        parent::beforeFilter();

        //$this->Auth->allow('index', 'check_exist', 'check_store_name', 'setup');
    }

/**
 * Index function.
 *
 * @return void
 */
    public function index() {
        if ($this->request->is('post')) {
            $data = $this->request->data;
            $data['name'] = $data['store_name'];
            $data['domain_prefix'] = str_replace(' ', '_', strtolower($data['name']));
            $this->_createSubscriber($data);
        }
    }

/**
 * Check the merchant code.
 *
 * @return void
 */
    public function check_exist() {
        $result = array(
            'success' => false
        );

        if ($this->request->is('post') || $this->request->is('ajax')) {
            $this->loadModel('Merchant');

            try {
                $data = $this->request->data;
                $merchant = $this->Merchant->findByMerchantCode($data);

                if (!empty($merchant) && is_array($merchant)) {
                    $result['success'] = true;
                    $result['merchant_id'] = $merchant['Merchant']['id'];
                    $result['store_name'] = $merchant['Merchant']['name'];
                    $result['subscriber_id'] = $merchant['Merchant']['subscriber_id'];
                }
            } catch (Exception $e) {
                $result['message'] = $e->getMessage();
            }
        }
        $this->serialize($result);
    }

/**
 * Check the merchant name.
 *
 * @return void
 */
    public function check_store_name() {
        $result = array(
            'success' => false
        );

        if ($this->request->is('ajax') || $this->request->is('post')) {
            $this->loadModel('Merchant');

            try {
                $data = $this->request->data;
                $merchant = $this->Merchant->findByName($data);

                if (!empty($merchant) && is_array($merchant)) {
                    $result['success'] = true;
                }
            } catch (Exception $e) {
                $result['message'] = $e->getMessage();
            }
        }
        $this->serialize($result);
    }
    
/**
 * Test setup function.
 *
 * @return void
 */
    public function setup() {
        $data = array(
            'name' => 'master',
            'plan_id' => 'subscriber_plan_retailer_trial',
            'domain_prefix' => 'master',
            'first_name' => 'Steve',
            'last_name' => 'Park',
            'username' => 'master@onzsa.com',
            'password' => 'master',
            'physical_city' => 'Auckland',
            'physical_country_id' => 'NZ',
            'default_currency' => 'NZD',
            'time_zone' => 'Pacific/Auckland',
        );
        $this->_createSubscriber($data);
    }

/**
 * Generate a random merchant code
 *
 * @return string
 */
    protected function _generateMerchantCode($length = 6) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?";
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $password = substr(str_shuffle( $chars ), 0, $length);
        return $password;
    }

/**
 * Create the subscriber function.
 *
 * @param array signup data
 * @return void
 */
    protected function _createSubscriber($data) {
        $dataSource = $this->Contact->getDataSource();
        $dataSource->begin();

        try {
            if (isset($data['plan_id_1']) && !empty($data['plan_id_1']) && $data['plan_id_1'] == 'subscriber_plan_franchise') {
                // create a retailer
                $this->MerchantRetailer->create();
                $data['merchant_id'] = $data['parent_merchant_id'];
                $this->MerchantRetailer->save($data);
                $merchant_id = $data['parent_merchant_id'];
                $retailer_id = $this->MerchantRetailer->id;
                
                // create a default user
                $this->_createDefaultUser($merchant_id, $retailer_id, $data['username'], $data['password'], $data['first_name'] . ' ' . $data['last_name']);
                
                // create a quick key
                $quick_key_id = $this->_createDefaultQuickKey($merchant_id, $retailer_id);

                // create a receipt template
                $receipt_template_id = $this->_createDefaultReceiptTemplate($merchant_id, $retailer_id, $data['name']);
                
                // create a main outlet and register
                $this->createDefaultOutlet($merchant_id, $retailer_id, $quick_key_id, $receipt_template_id);
            } else {
                // create a contact
                $this->Contact->create();
                $contact['Contact'] = $data;
                $contact['Contact']['email'] = $data['username'];
                $this->Contact->save($contact);
                
                // create a subscriber
                $this->Subscriber->create();
                $subscriber['Subscriber']['contact_id'] = $this->Contact->id;
                $subscriber['Subscriber']['username'] = $data['username'];
                $subscriber['Subscriber']['password'] = $data['password'];
                $this->Subscriber->save($subscriber);
                $data['subscriber_id'] = $this->Subscriber->id;
    
                // create a merchant
                $this->Merchant->create();
                $merchant['Merchant'] = $data;
                $merchant['Merchant']['merchant_code'] = $this->_generateMerchantCode(6);
                if ($data['plan_id'] == 'subscriber_plan_retailer_trial') {
                    $merchant['Merchant']['trial_ends'] = CakeTime::format('+30 days', '%Y-%m-%d');
                }
                $this->Merchant->save($merchant);
                $merchant_id = $this->Merchant->id;
                
                // create a default user
                $this->_createDefaultUser($merchant_id, NULL, $data['username'], $data['password'], $data['first_name'] . ' ' . $data['last_name']);
                
                // create a default customer group
                $customer_group_id = $this->_createDefaultCustomerGroup($merchant_id);
    
                // create a default customer
                $this->_createDefaultCustomer($merchant_id, $customer_group_id);
    
                // create default tax rates
                $default_tax_id = $this->_createDefaultTaxRates($merchant_id, $data['physical_country_id']);
    
                // create default payment types
                $this->_createDefaultPaymentTypes($merchant_id);
    
                // create a quick key
                $quick_key_id = $this->_createDefaultQuickKey($merchant_id, NULL);
    
                // create a receipt template
                $receipt_template_id = $this->_createDefaultReceiptTemplate($merchant_id, NULL, $data['name']);
    
                // create a main outlet and register
                $this->_createDefaultOutlet($merchant_id, NULL, $quick_key_id, $receipt_template_id);
    
                // create default supplier
                $supplier_id = $this->_createDefaultSupplier($merchant_id, $contact, $data['name']);
    
                // create default products
                $this->_createDefaultProducts($merchant_id, $supplier_id, $default_tax_id, $quick_key_id);
    
                // create a default price book
                $this->_createDefaultPriceBook($merchant_id, $customer_group_id);
    
                // create a default inventories
                $this->_createDefaultInventory($merchant_id);
    
                // create a merchant loyalty
                $this->_createMerchantLoyalty($merchant_id, $data['name']);
            }

            $dataSource->commit();

		    $names = explode(".", $_SERVER['HTTP_HOST']);
            if ($_SERVER['HTTP_HOST'] !== 'localhost' && !is_numeric($names[0])) {
                $this->redirect('https://'.$data['domain_prefix'].'.onzsa.com/users/login');
            } else {
                $this->redirect('/users/login');
            }
        } catch (Exception $e) {
            $dataSource->rollback();
            $this->Session->setFlash($e->getMessage());
        }
    }

/**
 * Create a default user function.
 *
 * @return void
 */
    protected function _createDefaultUser($merchant_id, $retailer_id, $username, $password, $display_name) {
        $this->loadModel('MerchantUser');

        $this->MerchantUser->create();

        $user = array();
        $user['MerchantUser']['merchant_id'] = $merchant_id;
        $user['MerchantUser']['retailer_id'] = $retailer_id;
        $user['MerchantUser']['user_type_id'] = 'user_type_admin';
        $user['MerchantUser']['username'] = $username;
        $user['MerchantUser']['password'] = $password;
        $user['MerchantUser']['display_name'] = $display_name;
        $user['MerchantUser']['email'] = $username;

        $this->MerchantUser->save($user);
    }

/**
 * Create a default customer group function.
 *
 * @return uuid
 */
    protected function _createDefaultCustomerGroup($merchant_id) {
        $this->loadModel('MerchantCustomerGroup');

        // create a default customer group
        $this->MerchantCustomerGroup->create();
        $group['MerchantCustomerGroup']['merchant_id'] = $merchant_id;
        $group['MerchantCustomerGroup']['group_code'] = 'onzsa';
        $group['MerchantCustomerGroup']['name'] = 'All Customers';
        $this->MerchantCustomerGroup->save($group);

        return $this->MerchantCustomerGroup->id;
    }

/**
 * Create a default customer function.
 *
 * @return uuid
 */
    protected function _createDefaultCustomer($merchant_id, $customer_group_id) {
        $this->loadModel('MerchantCustomer');

        // create a default customer
        $this->MerchantCustomer->create();
        $customer['MerchantCustomer']['merchant_id'] = $merchant_id;
        $customer['MerchantCustomer']['customer_group_id'] = $customer_group_id;
        $customer['MerchantCustomer']['customer_code'] = 'walkin';
        $this->MerchantCustomer->save($customer);

        return $this->MerchantCustomer->id;
    }

/**
 * Create default payment types function.
 *
 * @return void
 */
    protected function _createDefaultPaymentTypes($merchant_id) {
        $this->loadModel('MerchantPaymentType');
        $this->loadModel('PaymentType');

        $payments = $this->PaymentType->find('all', array(
            'conditions' => array(
                'PaymentType.name' => array('Cash', 'Credit Card')
            )
        ));

        foreach ($payments as $payment) {
            $data['merchant_id'] = $merchant_id;
            $data['payment_type_id'] = $payment['PaymentType']['id'];
            $data['name'] = $payment['PaymentType']['name'];

            $this->MerchantPaymentType->create();
            $this->MerchantPaymentType->save(array(
                'MerchantPaymentType' => $data
            ));
        }
    }

/**
 * Create default tax rates function.
 *
 * @return uuid
 */
    protected function _createDefaultTaxRates($merchant_id, $country_id) {
        $this->loadModel('MerchantTaxRate');
        $this->loadModel('TaxRate');

        $rates = $this->TaxRate->find('all', array(
            'conditions' => array(
                'OR' => array(
                    'TaxRate.country_id' => $country_id,
                    'TaxRate.country_id IS NULL'
                )
            )
        ));

        $default_tax_id = null;
        foreach ($rates as $rate) {
            $data = $rate['TaxRate'];
            $data['merchant_id'] = $merchant_id;
            unset($data['id']);

            $this->MerchantTaxRate->create();
            $this->MerchantTaxRate->save(array(
                'MerchantTaxRate' => $data
            ));

/*
            if ($rate['TaxRate']['rate'] != 0) {
                $default_tax_id = $this->MerchantTaxRate->id;
            }
*/
            $default_tax_id = $this->MerchantTaxRate->id;
        }

        // update a default tax id
        $this->Merchant->id = $merchant_id;
        $this->Merchant->saveField('default_tax_id', $default_tax_id);

        return $default_tax_id;
    }

/**
 * Create a default quick key function.
 *
 * @return uuid
 */
    protected function _createDefaultQuickKey($merchant_id, $retailer_id) {
        $this->loadModel('MerchantQuickKey');

        // create a quick key
        $this->MerchantQuickKey->create();
        $data['merchant_id'] = $merchant_id;
        $data['retailer_id'] = $retailer_id;
        $data['name'] = 'Default Quick Keys';
        $this->MerchantQuickKey->save(array('MerchantQuickKey' => $data));

        // update a default quick key id
        if (empty($retailer_id)) {
            $this->Merchant->id = $merchant_id;
            $this->Merchant->saveField('default_quick_key_id', $this->MerchantQuickKey->id);
        }
        return $this->MerchantQuickKey->id;
    }

/**
 * Create a default receipt template function.
 *
 * @return uuid
 */
    protected function _createDefaultReceiptTemplate($merchant_id, $retailer_id, $store_name) {
        $this->loadModel('MerchantReceiptTemplate');
        $this->loadModel('ReceiptStyle');

        if (empty($retailer_id)) {
            // create a receipt template
            $this->MerchantReceiptTemplate->create();
            $data['merchant_id'] = $merchant_id;
            $data['name'] = 'Standard Receipt';
            $data['receipt_style_id'] = 1;
            $data['receipt_header'] = $store_name;
            $this->MerchantReceiptTemplate->save(array(
                'MerchantReceiptTemplate' => $data
            ));
    
            return $this->MerchantReceiptTemplate->id;
        } else {
            return $this->MerchantReceiptTemplate->findByMerchantId($merchant_id)['MerchantReceiptTemplate']['id'];
        }
    }

/**
 * Create a main outlet and register function.
 *
 * @return void
 */
    protected function _createDefaultOutlet($merchant_id, $retailer_id, $quick_key_id, $receipt_template_id) {
        $this->loadModel('MerchantOutlet');
        $this->loadModel('MerchantRegister');

        // create a main outlet
        $this->MerchantOutlet->create();
        $outlet['MerchantOutlet']['merchant_id'] = $merchant_id;
        $outlet['MerchantOutlet']['name'] = 'Main Outlet';
        $this->MerchantOutlet->save($outlet);

        // create a main register
        $this->MerchantRegister->create();
        $register['MerchantRegister']['outlet_id'] = $this->MerchantOutlet->id;
        $register['MerchantRegister']['name'] = 'Main Register';
        $register['MerchantRegister']['quick_key_id'] = $quick_key_id;
        $register['MerchantRegister']['receipt_template_id'] = $receipt_template_id;
        $this->MerchantRegister->save($register);
    }

/**
 * Create a default supplier function.
 *
 * @return uuid
 */
    protected function _createDefaultSupplier($merchant_id, $contact, $name) {
        $this->loadModel('MerchantSupplier');

        // create a contact
        $this->Contact->create();
        $this->Contact->save($contact);

        // create a default supplier
        $this->MerchantSupplier->create();
        $supplier['MerchantSupplier']['merchant_id'] = $merchant_id;
        $supplier['MerchantSupplier']['contact_id'] = $this->Contact->id;
        $supplier['MerchantSupplier']['name'] = $name;
        $this->MerchantSupplier->save($supplier);

        return $this->MerchantSupplier->id;
    }

/**
 * Create default products function.
 *
 * @return void
 */
    protected function _createDefaultProducts($merchant_id, $supplier_id, $tax_id, $quick_key_id) {
        $this->loadModel('MerchantProductBrand');
        $this->loadModel('MerchantProductType');
        $this->loadModel('MerchantProductTag');
        $this->loadModel('MerchantProductCategory');
        $this->loadModel('MerchantProduct');

        // create a default product brand
        $this->MerchantProductBrand->create();
        $brand['MerchantProductBrand']['merchant_id'] = $merchant_id;
        $brand['MerchantProductBrand']['name'] = 'General';
        $this->MerchantProductBrand->save($brand);

        // create a default product type
        $this->MerchantProductType->create();
        $type['MerchantProductType']['merchant_id'] = $merchant_id;
        $type['MerchantProductType']['name'] = 'General';
        $this->MerchantProductType->save($type);

        // create a default product tag
        $this->MerchantProductTag->create();
        $tag['MerchantProductTag']['merchant_id'] = $merchant_id;
        $tag['MerchantProductTag']['name'] = 'General';
        $this->MerchantProductTag->save($tag);

        // get a default tax rate
        $rate = $this->MerchantTaxRate->findById($tax_id);

        // create a product of yoghurt flavour banana
        $this->MerchantProduct->create();
        $product1['MerchantProduct']['merchant_id'] = $merchant_id;
        $product1['MerchantProduct']['name'] = 'Yoghurt Fruity Tart (Demo)';
        $product1['MerchantProduct']['handle'] = 'yoghurt-fruity-tart';
        $product1['MerchantProduct']['sku'] = 'yoghurt-fruity-tart';
        $product1['MerchantProduct']['product_brand_id'] = $this->MerchantProductBrand->id;
        $product1['MerchantProduct']['product_type_id'] = $this->MerchantProductType->id;
        $product1['MerchantProduct']['supplier_id'] = $supplier_id;
        $product1['MerchantProduct']['supply_price'] = 3.0;
        $product1['MerchantProduct']['markup'] = 0.20;
        $product1['MerchantProduct']['price'] = $product1['MerchantProduct']['supply_price'] * (1.0 + $product1['MerchantProduct']['markup']);
        $product1['MerchantProduct']['tax'] = $product1['MerchantProduct']['price'] * $rate['MerchantTaxRate']['rate'];
        $product1['MerchantProduct']['tax_id'] = $rate['MerchantTaxRate']['id'];
        $product1['MerchantProduct']['price_include_tax'] = $product1['MerchantProduct']['price'] + $product1['MerchantProduct']['tax'];
        $product1['MerchantProduct']['image'] = "/img/no-image.png";
        $product1['MerchantProduct']['image_large'] = "/img/no-image.png";
        $this->MerchantProduct->save($product1);
        $product1['MerchantProduct']['id'] = $this->MerchantProduct->id;

        // Add a product category
        $this->MerchantProductCategory->create();
        $category['MerchantProductCategory']['product_id'] = $product1['MerchantProduct']['id'];
        $category['MerchantProductCategory']['product_tag_id'] = $this->MerchantProductTag->id;
        $this->MerchantProductCategory->save($category);

        // create a product of yoghurt flavour banana
        $this->MerchantProduct->create();
        $product2['MerchantProduct']['merchant_id'] = $merchant_id;
        $product2['MerchantProduct']['name'] = 'Yoghurt Creamy Delight (Demo)';
        $product2['MerchantProduct']['handle'] = 'yoghurt-creamy-delight';
        $product2['MerchantProduct']['sku'] = 'yoghurt-creamy-delight';
        $product2['MerchantProduct']['product_brand_id'] = $this->MerchantProductBrand->id;
        $product2['MerchantProduct']['product_type_id'] = $this->MerchantProductType->id;
        $product2['MerchantProduct']['supplier_id'] = $supplier_id;
        $product2['MerchantProduct']['supply_price'] = 3.2;
        $product2['MerchantProduct']['markup'] = 0.20;
        $product2['MerchantProduct']['price'] = $product2['MerchantProduct']['supply_price'] * (1.0 + $product2['MerchantProduct']['markup']);
        $product2['MerchantProduct']['tax'] = $product2['MerchantProduct']['price'] * $rate['MerchantTaxRate']['rate'];
        $product2['MerchantProduct']['tax_id'] = $rate['MerchantTaxRate']['id'];
        $product2['MerchantProduct']['price_include_tax'] = $product2['MerchantProduct']['price'] + $product2['MerchantProduct']['tax'];
        $product2['MerchantProduct']['image'] = "/img/no-image.png";
        $product2['MerchantProduct']['image_large'] = "/img/no-image.png";
        $this->MerchantProduct->save($product2);
        $product2['MerchantProduct']['id'] = $this->MerchantProduct->id;

        // Add a product category
        $this->MerchantProductCategory->create();
        $category['MerchantProductCategory']['product_id'] = $product2['MerchantProduct']['id'];
        $category['MerchantProductCategory']['product_tag_id'] = $this->MerchantProductTag->id;
        $this->MerchantProductCategory->save($category);

        // update a default quick key layout
        $quick_key_layout = array(
            'name' => 'Default',
            'pages' => array(
                array(
                    'page' => 1,
                    'keys' => array(
                        array(
                            'position' => 0,
                            'product_id' => $product1['MerchantProduct']['id'],
                            'sku' => $product1['MerchantProduct']['sku'],
                            'label' => $product1['MerchantProduct']['name'],
                            'color' => '#ffffff',
                            'image' => '/img/no-image.png'
                        ),
                        array(
                            'position' => 1,
                            'product_id' => $product2['MerchantProduct']['id'],
                            'sku' => $product2['MerchantProduct']['sku'],
                            'label' => $product2['MerchantProduct']['name'],
                            'color' => '#ffffff',
                            'image' => '/img/no-image.png'
                        )
                    )
                )
            )
        );

        $this->MerchantQuickKey->id = $quick_key_id;
        $this->MerchantQuickKey->saveField('key_layouts', json_encode($quick_key_layout));
    }

/**
 * Create default price book function.
 *
 * @return void
 */
    protected function _createDefaultPriceBook($merchant_id, $customer_group_id) {
        $this->loadModel('MerchantProduct');
        $this->loadModel('MerchantPriceBook');
        $this->loadModel('MerchantPriceBookEntry');

        // create a default price book
        $this->MerchantPriceBook->create();
        $priceBook['MerchantPriceBook']['merchant_id'] = $merchant_id;
        $priceBook['MerchantPriceBook']['name'] = 'General Price Book (All Products)';
        $priceBook['MerchantPriceBook']['customer_group_id'] = $customer_group_id;
        $priceBook['MerchantPriceBook']['is_default'] = 1;
        $this->MerchantPriceBook->save($priceBook);

        // get product list
        $products = $this->MerchantProduct->find('all', array(
            'conditions' => array(
                'MerchantProduct.merchant_id' => $merchant_id
            )
        ));

        foreach ($products as $product) {
            // create a price book entry
            $this->MerchantPriceBookEntry->create();
            $data['MerchantPriceBookEntry']['price_book_id'] = $this->MerchantPriceBook->id;
            $data['MerchantPriceBookEntry']['product_id'] = $product['MerchantProduct']['id'];
            $data['MerchantPriceBookEntry']['markup'] = $product['MerchantProduct']['markup'];
            $data['MerchantPriceBookEntry']['price'] = $product['MerchantProduct']['price'];
            $data['MerchantPriceBookEntry']['price_include_tax'] = $product['MerchantProduct']['price_include_tax'];
            $data['MerchantPriceBookEntry']['tax'] = $product['MerchantProduct']['tax'];
            $this->MerchantPriceBookEntry->save($data);
        }

        $this->Merchant->id = $merchant_id;
        $this->Merchant->saveField('default_price_book_id', $this->MerchantPriceBook->id);
    }

/**
 * Create default inventories function.
 *
 * @return void
 */
    protected function _createDefaultInventory($merchant_id) {
        $this->loadModel('MerchantOutlet');
        $this->loadModel('MerchantProduct');
        $this->loadModel('MerchantProductInventory');

        // get outlet list
        $outlets = $this->MerchantOutlet->find('all', array(
            'conditions' => array(
                'MerchantOutlet.merchant_id' => $merchant_id
            )
        ));

        // get product list
        $products = $this->MerchantProduct->find('all', array(
            'conditions' => array(
                'MerchantProduct.merchant_id' => $merchant_id
            )
        ));

        foreach ($outlets as $outlet) {
            foreach ($products as $product) {
                // create a product inventory
                $this->MerchantProductInventory->create();
                $data['outlet_id'] = $outlet['MerchantOutlet']['id'];
                $data['product_id'] = $product['MerchantProduct']['id'];
                $this->MerchantProductInventory->save(array(
                    'MerchantProductInventory' => $data
                ));
            }
        }
    }

/**
 * Create a merchant loyalty function.
 *
 * @return void
 */
    protected function _createMerchantLoyalty($merchant_id, $store_name) {
        $this->loadModel('MerchantLoyalty');

        // create a merchant loyalty
        $this->MerchantLoyalty->create();
        $loyalty['MerchantLoyalty']['merchant_id'] = $merchant_id;
        $loyalty['MerchantLoyalty']['loyalty_earn_amount'] = 1;
        $loyalty['MerchantLoyalty']['loyalty_spend_amount'] = 50;
        $loyalty['MerchantLoyalty']['offer_signup_bonus_loyalty'] = 0;
        $loyalty['MerchantLoyalty']['signup_bonus_loyalty_amount'] = 0;
        $loyalty['MerchantLoyalty']['send_welcome_email'] = 0;
        $loyalty['MerchantLoyalty']['welcome_email_subject'] = 'Welcome to the ' . $store_name . ' Loyalty Program';

        $email_body = '<h1>Welcome to ' . $store_name . ' Loyalty Program</h1>';
        $email_body .= '<p>You can earn Loyalty $ when you make purchases at onzsa and redeem your credit in store.</p>';
        $email_body .= '<p>Thanks,<br>' . $store_name . '</p>';
        $email_body .= '<p>Register your details with the ' . $store_name . ' Loyalty Program to earn an additional $ Loyalty:</p>';
        $loyalty['MerchantLoyalty']['welcome_email_body'] = base64_encode($email_body);

        $this->MerchantLoyalty->save($loyalty);
    }

}
