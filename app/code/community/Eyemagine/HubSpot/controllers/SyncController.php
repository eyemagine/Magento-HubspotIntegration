<?php
/**
 * EYEMAGINE - The leading Magento Solution Partner
 *
 * HubSpot Integration with Magento
 *
 * @author    EYEMAGINE <magento@eyemaginetech.com>
 * @category  Eyemagine
 * @package   Eyemagine_HubSpot
 * @copyright Copyright (c) 2013 EYEMAGINE Technology, LLC (http://www.eyemaginetech.com)
 * @license   http://www.eyemaginetech.com/license.txt
 */

/**
 * HubSpot Integration Access Controller
 */
class Eyemagine_HubSpot_SyncController extends Mage_Core_Controller_Front_Action
{
    /**
     * Error codes
     */
    const ERROR_CODE_SECURE                    = 9001;
    const ERROR_CODE_ISSECURE                  = 9002;
    const ERROR_CODE_INVALID_STORECODE         = 9003;
    const ERROR_CODE_INVALID_REQUEST           = 9004;
    const ERROR_CODE_INVALID_CREDENTIALS       = 9005;
    const ERROR_CODE_UNKNOWN_EXCEPTION         = 9006;
    const ERROR_CODE_SYSTEM_CONFIG_DISABLED    = 9020;
    const ERROR_CODE_UNSUPPORTED_SQL           = 9500;
    const ERROR_CODE_UNSUPPORTED_FEATURE       = 9600;

    /**
     * preset values definitions
     */
    const MAX_CUSTOMER_PERPAGE                 = 100;
    const MAX_ORDER_PERPAGE                    = 20;
    const MAX_ASSOC_PRODUCT_LIMIT              = 10;
    const IS_ABANDONED_IN_SECS                 = 3600;
    const LONG_DATE_FORMAT                     = 'l, F j, Y \a\t h:i A \U\T\C';


    /**
     * Generate a list of customers updated since start date
     */
    public function getcustomersAction()
    {
        if (!$this->_authenticate()) {
            return;
        }

        try {
            $request       = $this->getRequest();
            $helper        = Mage::helper('eyehubspot');
            $maxperpage    = $request->getParam('maxperpage', self::MAX_CUSTOMER_PERPAGE);
            $start         = date('Y-m-d H:i:s', $request->getParam('start', 0));
            $end           = date('Y-m-d H:i:s', time() - 300);
            $websiteId     = Mage::app()->getWebsite()->getId();
            $storeId       = Mage::app()->getStore()->getId();
            $custGroups    = $helper->getCustomerGroups();
            $collection    = Mage::getModel('customer/customer')->getCollection();
            $customerData  = array();

            $collection->addAttributeToSelect('*')
                ->addFieldToFilter('updated_at', array(
                    'from' => $start,
                    'to'   => $end,
                    'date' => true
                ))
                ->setOrder('updated_at', Varien_Data_Collection::SORT_ORDER_ASC)
                ->setPageSize($maxperpage);

            // only add the filter if website id > 0
            if ($websiteId) {
                $collection->addFieldToFilter('website_id', array('eq' => $websiteId));
            }

            foreach ($collection as $customer) {
                if ($customer->getDefaultBilling()) {
                    $customer->setDefaultBillingAddress(
                        $helper->convertAttributeData($customer->getDefaultBillingAddress())
                    );
                }

                if ($customer->getDefaultShipping()) {
                    $customer->setDefaultShippingAddress(
                        $helper->convertAttributeData($customer->getDefaultShippingAddress())
                    );
                }

                // clear password hash
                $customer->unsetData('password_hash');

                $groupId = (int)$customer->getGroupId();

                if (isset($custGroups[$groupId])) {
                    $customer->setCustomerGroup($custGroups[$groupId]);
                }

                $customerData[$customer->getId()] = $customer->getData();
            }
        } catch (Exception $e) {
            $this->_outputError(
                self::ERROR_CODE_UNKNOWN_EXCEPTION,
                'Unknown exception on request',
                $e
            );
            return;
        }

        $this->_outputJson(array(
            'customers'     => $customerData,
            'website'       => $websiteId,
            'store'         => $storeId
        ));
    }


    /**
     * Get order data
     */
    public function getordersAction()
    {
        if (!$this->_authenticate()) {
            return;
        }

        try {
            $request       = $this->getRequest();
            $helper        = Mage::helper('eyehubspot');
            $maxperpage    = $request->getParam('maxperpage', self::MAX_ORDER_PERPAGE);
            $maxAssociated = $request->getParam('maxassoc', self::MAX_ASSOC_PRODUCT_LIMIT);
            $start         = date('Y-m-d H:i:s', $request->getParam('start', 0));
            $end           = date('Y-m-d H:i:s', time() - 300);
            $websiteId     = Mage::app()->getWebsite()->getId();
            $store         = Mage::app()->getStore();
            $storeId       = Mage::app()->getStore()->getId();
            $custGroups    = $helper->getCustomerGroups();
            $collection    = Mage::getModel('sales/order')->getCollection();
            $ordersData    = array();

            // setup the query and page size
            $collection->addFieldToFilter('updated_at', array(
                    'from' => $start,
                    'to'   => $end,
                    'date' => true
                ))
                ->setOrder('updated_at', Varien_Data_Collection::SORT_ORDER_ASC)
                ->setPageSize($maxperpage);

            // only add the filter if store id > 0
            if ($storeId) {
                $collection->addFieldToFilter('store_id', array('eq' => $storeId));
            }

            // in order to get the full order details, have to load each order
            foreach ($collection as $order) {
                $result       = $helper->convertAttributeData($order);
                $groupId      = (int)$order->getCustomerGroupId();

                $result['customer_group']   = (isset($custGroups[$groupId])) ? $custGroups[$groupId] : 'Guest';
                $result['website_id']       = $websiteId;
                $result['store_url']        = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
                $result['media_url']        = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
                $result['shipping_address'] = $helper->convertAttributeData($order->getShippingAddress());
                $result['billing_address']  = $helper->convertAttributeData($order->getBillingAddress());
                $result['items']            = array();

                foreach ($order->getAllItems() as $item) {
                    $helper->loadCatalogData($item, $storeId, $websiteId, $maxAssociated);
                    $result['items'][] = $helper->convertAttributeData($item);
                }

                $ordersData[$order->getId()] = $result;
            }
        } catch (Exception $e) {
            $this->_outputError(
                self::ERROR_CODE_UNKNOWN_EXCEPTION,
                'Unknown exception on request',
                $e
            );
            return;
        }

        $this->_outputJson(array(
            'orders'        => $ordersData,
            'website'       => $websiteId,
            'store'         => $storeId
        ));
    }


    /**
     * Loads all abandoned carts (only those created by registered customers)
     */
    public function getabandonedAction()
    {
        if (!$this->_authenticate()) {
            return;
        }

        try {
            $request       = $this->getRequest();
            $helper        = Mage::helper('eyehubspot');
            $maxperpage    = $request->getParam('maxperpage', self::MAX_ORDER_PERPAGE);
            $maxAssociated = $request->getParam('maxassoc', self::MAX_ASSOC_PRODUCT_LIMIT);
            $start         = date('Y-m-d H:i:s', $request->getParam('start', 0));
            $end           = date('Y-m-d H:i:s', time() - $request->getParam('offset', self::IS_ABANDONED_IN_SECS));
            $websiteId     = Mage::app()->getWebsite()->getId();
            $store         = Mage::app()->getStore();
            $storeId       = Mage::app()->getStore()->getId();
            $custGroups    = $helper->getCustomerGroups();
            $collection    = Mage::getModel('sales/quote')->getCollection();
            $returnData    = array();

            // setup the query and page size
            $collection->addFieldToFilter('updated_at', array(
                    'from' => $start,
                    'to'   => $end,
                    'date' => true
                ))
                ->addFieldToFilter('is_active', array('neq' => 0))
                ->addFieldToFilter('customer_email', array('like' => '%@%'))
                ->addFieldToFilter('items_count', array('gt' => 0))
                ->setOrder('updated_at', Varien_Data_Collection::SORT_ORDER_ASC)
                ->setPageSize($maxperpage);

            // only add the filter if store id > 0
            if ($storeId) {
                $collection->addFieldToFilter('store_id', array('eq' => $storeId));
            }

            foreach ($collection as $cart) {
                $result   = $helper->convertAttributeData($cart);
                $groupId  = (int)$cart->getCustomerGroupId();

                if (isset($custGroups[$groupId])) {
                    $result['customer_group'] = $custGroups[$groupId];
                } else {
                    $result['customer_group'] = 'Guest';
                }

                $result['website_id']       = $websiteId;
                $result['store_url']        = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
                $result['media_url']        = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
                $result['shipping_address'] = $helper->convertAttributeData($cart->getShippingAddress());
                $result['billing_address']  = $helper->convertAttributeData($cart->getBillingAddress());
                $result['items']            = array();

                foreach ($cart->getAllItems() as $item) {
                    $helper->loadCatalogData($item, $cartStoreId, $websiteId, $maxAssociated);
                    $result['items'][] = $helper->convertAttributeData($item);
                }

                // make sure there are items before adding to return
                if (count($result['items'])) {
                    $returnData[$cart->getId()] = $result;
                }
            }
        } catch (Exception $e) {
            $this->_outputError(
                self::ERROR_CODE_UNKNOWN_EXCEPTION,
                'Unknown exception on request',
                $e
            );
            return;
        }

        $this->_outputJson(array(
            'abandoned'     => $returnData,
            'website'       => $websiteId,
            'store'         => $storeId
        ));
    }


    /**
     * Retrieves recent activity for customers (sorted by last visit date)
     */
    function getactivityAction()
    {
        if (!$this->_authenticate()) {
            return;
        }

        try {
            $request       = $this->getRequest();
            $helper        = Mage::helper('eyehubspot');
            $maxperpage    = $request->getParam('maxperpage', self::MAX_CUSTOMER_PERPAGE);
            $maxAssociated = $request->getParam('maxassoc', self::MAX_ASSOC_PRODUCT_LIMIT);
            $start         = date('Y-m-d H:i:s', $request->getParam('start', 0));
            $end           = date('Y-m-d H:i:s', time() - 300);
            $websiteId     = Mage::app()->getWebsite()->getId();
            $store         = Mage::app()->getStore();
            $storeId       = Mage::app()->getStore()->getId();
            $collection    = Mage::getModel('customer/customer')->getCollection();
            $resource      = Mage::getSingleton('core/resource');
            $read          = $resource->getConnection('core_read');
            $customerData  = array();

            try {
                // because of limitations in the log areas of magento, we cannot use the
                // standard collection to retreive the results
                $select = $read->select()
                    ->from(array('lc' => $resource->getTableName('log/customer')))
                    ->joinInner(
                        array('lv' => $resource->getTableName('log/visitor')),
                        'lc.visitor_id = lv.visitor_id'
                    )
                    ->joinInner(
                        array('vi' => $resource->getTableName('log/visitor_info')),
                        'lc.visitor_id = vi.visitor_id'
                    )
                    ->joinInner(
                        array('c' => $resource->getTableName('customer/entity')),
                        'c.entity_id = lc.customer_id',
                        array('email' => 'email', 'customer_since' => 'created_at')
                    )
                    ->joinInner(
                        array('p' => $resource->getTableName('log/url_info_table')),
                        'p.url_id = lv.last_url_id',
                        array('last_url' => 'p.url', 'last_referer' => 'p.referer')
                    )
                    ->where('lc.customer_id > 0')
                    ->where("lv.last_visit_at >= '$start'")
                    ->where("lv.last_visit_at < '$end'")
                    ->order('lv.last_visit_at')
                    ->limit($maxperpage);

                $collection = $read->fetchAll($select);
            } catch (Exception $e) {
                $this->_outputError(self::ERROR_CODE_UNSUPPORTED_SQL, 'DB Exception on query', $e);
                return;
            }

            foreach ($collection as $assoc) {
                $log        = new Varien_Object($assoc);
                $customerId = $log->getCustomerId();

                // merge and replace older data with newer
                if (isset($customerData[$customerId])) {
                    $temp = $customerData[$customerId];
                    $log->addData($temp->getData());
                    $log->setFirstVisitAt($temp->getFirstVisitAt());
                } else {
                    $log->setViewed($helper->getProductViewedList($customerId, $maxAssociated));
                    $log->setCompare($helper->getProductCompareList($customerId, $maxAssociated));
                    $log->setWishlist($helper->getProductWishlist($customerId, $maxAssociated));
                }

                $log->unsetData('session_id');
                $customerData[$customerId] = $log;
            }
        } catch (Exception $e) {
            $this->_outputError(
                self::ERROR_CODE_UNKNOWN_EXCEPTION,
                'Unknown exception on request',
                $e
            );
            return;
        }

        $this->_outputJson(array(
            'visitors'      => $helper->convertAttributeData($customerData),
            'website'       => $websiteId,
            'store'         => $storeId
        ));
    }


    /**
     * Display information about the Magento stores that are installed
     */
    public function getstoresAction()
    {
        if (!$this->_authenticate()) {
            return;
        }

        // get state names
        $regionModel    = Mage::getModel('directory/region');
        $storeData      = array();
        $edition        = method_exists('Mage', 'getEdition') ? Mage::getEdition() : '';

        foreach (Mage::app()->getStores(true, true) as $storeCode => $store) {
            $storeId    = $store->getId();
            $region     = null;

            if (is_object($regionModel)) {
                $regionId = (int)Mage::getStoreConfig('shipping/origin/region_id', $storeId);

                if ($regionModel->getId() != $regionId) {
                    $regionModel->load($regionId);
                }

                $region = $regionModel->getDefaultName();
            }

            $storeData[$storeCode] = array(
                'store_id'         => $storeId,
                'store_code'       => $storeCode,
                'website_id'       => $store->getWebsiteId(),
                'store_name'       => Mage::getStoreConfig('system/store/name', $storeId),
                'business_name'    => Mage::getStoreConfig('general/store_information/name', $storeId),
                'email'            => Mage::getStoreConfig('trans_email/ident_general/email', $storeId),
                'magento_state'    => $region,
                'magento_country'  => Mage::getStoreConfig('general/store_information/merchant_country', $storeId),
                'base_url'         => $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB),
                'secure_base_url'  => $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, true),
                'media_url'        => $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA),
                'locale_code'      => Mage::getStoreConfig('general/locale/code', $storeId),
            );
        }

        $this->_outputJson(array(
            'stores'               => $storeData,
            'script_version'       => Mage::helper('eyehubspot')->getVersion(),
            'magento_version'      => Mage::getVersion(),
            'magento_edition'      => $edition,
        ));
    }


    /**
     * Display information about the current Magento store
     */
    public function getinfoAction()
    {
        if (!$this->_authenticate()) {
            return;
        }

        $store                 = Mage::app()->getStore();
        $storeId               = $store->getId();
        $regionModel           = Mage::getModel('directory/region');
        $edition               = method_exists('Mage', 'getEdition') ? Mage::getEdition() : '';
        $storeData             = array();
        $region                = null;

        if (is_object($regionModel)) {
            $regionId = (int)Mage::getStoreConfig('shipping/origin/region_id', $storeId);

            if ($regionModel->getId() != $regionId) {
                $regionModel->load($regionId);
            }

            $region = $regionModel->getDefaultName();
        }

        $storeData = array(
            'store_id'         => $storeId,
            'store_code'       => $store->getCode(),
            'website_id'       => $store->getWebsiteId(),
            'store_name'       => Mage::getStoreConfig('system/store/name', $storeId),
            'business_name'    => Mage::getStoreConfig('general/store_information/name', $storeId),
            'magento_email'    => Mage::getStoreConfig('trans_email/ident_general/email', $storeId),
            'magento_state'    => $region,
            'magento_country'  => Mage::getStoreConfig('general/store_information/merchant_country', $storeId),
            'base_url'         => $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, false),
            'secure_base_url'  => $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, true),
            'media_url'        => $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA, false),
            'locale_code'      => Mage::getStoreConfig('general/locale/code', $storeId),
        );

        $this->_outputJson(array(
            'info'                 => $storeData,
            'script_version'       => Mage::helper('eyehubspot')->getVersion(),
            'magento_version'      => Mage::getVersion(),
            'magento_edition'      => $edition,
        ));
    }


    /**
     * Verify that the requestor has valid access
     *
     * @return boolean
     */
    protected function _authenticate()
    {
        $enabled    = (int)Mage::getStoreConfig('eyehubspot/settings/active');
        $key1       = Mage::getStoreConfig('eyehubspot/settings/userkey');
        $key2       = Mage::getStoreConfig('eyehubspot/settings/passcode');

        if (!$enabled) {
            $this->_outputError(
                self::ERROR_CODE_SYSTEM_CONFIG_DISABLED,
                'Magento System Configuration has disabled access to this resource.
                To re-enable, please go to Magento Admin > System > Configuration >
                Services > HubSpot Integration.'
            );
            return false;

        } elseif ($enabled && !empty($key1) && !empty($key2)) {
            if ((strcasecmp($key1, $this->getRequest()->getParam('ukey')) == 0)
                && (strcasecmp($key2, $this->getRequest()->getParam('code')) == 0)
            ) {
                return true;
            }
        }

        $this->_outputError(self::ERROR_CODE_INVALID_CREDENTIALS, 'Invalid user key and/or access code');

        return false;
    }


    /**
     * Function used to output an error and quit.
     *
     * @param  integer $code
     * @param  string $error
     * @param  Exception $e
     */
    public function _outputError($code, $error, Exception $e = null)
    {
        $this->_outputJson(array(
            'error' => $error,
            'code'  => $code,
            'extra' => ($e && $authen) ? $e->getMessage() : ''
        ));
    }


    /**
     * Flushes the data as JSON response
     *
     * @param mixed $data
     */
    protected function _outputJson($data)
    {
        $this->getResponse()
            ->clearHeaders()
            ->setHeader('Content-Type', 'application/json;charset=utf-8')
            ->setBody(Mage::helper('core')->jsonEncode($data));
    }
}
