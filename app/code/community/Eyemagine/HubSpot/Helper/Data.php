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

class Eyemagine_HubSpot_Helper_Data extends Mage_Core_Helper_Abstract
{
    const ERROR_CODE_UNSUPPORTED_FEATURE       = 9600;


    /**
     * Caches the version string defined in the module config.xml
     *
     * @var string
     */
    protected $_version = null;


    /**
     * Returns the extension version from the module config.xml
     *
     * @return string
     */
    public function getVersion()
    {
        if (!$this->_version) {
            $info          = explode('_Helper_', get_class($this));
            $extensionName = array_shift($info);

            $this->_version = (string)Mage::getConfig()->getNode(
                'modules/' . $extensionName . '/version'
            );
        }

        return $this->_version;
    }


    /**
     * Get customer group data
     *
     * @return array
     */
    public function getCustomerGroups()
    {
        $collection = Mage::getModel('customer/group')->getCollection();
        $result     = array();

        foreach ($collection as $group) {
            $result[$group->getId()] = $group->getCustomerGroupCode();
        }

        return $result;
    }


    /**
     * Convert the object values to associative array
     *
     * @param  Varien_Object|array $input
     * @return array
     */
    public function convertAttributeData($input)
    {
        $result = array();

        if (is_object($input) && $input instanceof Varien_Object) {
            foreach ($input->getData() as $attribute => $value) {
                if (is_object($value) || is_array($value)) {
                    $result[$attribute] = $this->convertAttributeData($value);
                } else {
                    $result[$attribute] = $value;
                }
            }
        } elseif (is_array($input)) {
            foreach ($input as $k => $v) {
                $result[$k] = $this->convertAttributeData($v);
            }
        } else {
            return $input;
        }

        return $result;
    }


    /**
     * Loads all relevant product and category data for the item
     *
     * @param  Mage_Sales_Model_Order_Item|Mage_Sales_Model_Quote_Item $item
     * @param  int $orderStoreId
     * @param  int $websiteId
     * @param  int $maxLimit
     */
    public function loadCatalogData($item, $storeId, $websiteId, $maxLimit = 10)
    {
        $product     = null;
        $categories  = array();
        $related     = array();
        $upsells     = array();

        // load product details
        if ($item->getProductId()) {
            $product = Mage::getModel('catalog/product')->load($item->getProductId());

            // deleted
            if (!$product->getId()) {
                $product = null;
            }

            if ($product) {
                $relatedCollection = $product->getRelatedProductCollection()
                    ->addAttributeToSelect('name')
                    ->addAttributeToSelect('sku')
                    ->addAttributeToSelect('url_path')
                    ->addAttributeToSelect('image')
                    ->addAttributeToSelect('visibility')
                    ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                    ->setPageSize($maxLimit);

                foreach ($relatedCollection as $p) {
                    $websiteIds = $p->getWebsiteIds();
                    if (in_array($websiteId, $websiteIds)) {
                        $related[$p->getId()] = $this->convertAttributeData($p);
                    }
                }

                $upsellCollection = $product->getUpSellProductCollection()
                    ->addAttributeToSelect('name')
                    ->addAttributeToSelect('sku')
                    ->addAttributeToSelect('url_path')
                    ->addAttributeToSelect('image')
                    ->addAttributeToSelect('visibility')
                    ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                    ->setPageSize($maxLimit);

                foreach ($upsellCollection as $p) {
                    $websiteIds = $p->getWebsiteIds();
                    if (in_array($websiteId, $websiteIds)) {
                        $upsells[$p->getId()] = $this->convertAttributeData($p);
                    }
                }

                $categoryCollection = $product->getCategoryCollection()
                    ->addAttributeToSelect('name')
                    ->addAttributeToSelect('is_active')
                    ->addAttributeToSelect('url_path')
                    ->addAttributeToFilter('level', array('gt' => 1))
                    ->setPageSize($maxLimit);

                foreach ($categoryCollection as $category) {
                    $storeIds = $category->getStoreIds();
                    if (in_array($storeId, $storeIds)) {
                        $categories[$category->getId()] = $this->convertAttributeData($category);
                    }
                }

                $product->setRelatedProducts($related);
                $product->setUpSellProducts($upsells);
            }

            $item->setData('product', $product);
            $item->setCategories($categories);
        }
    }


    /**
     * Load the customer recently viewed products list
     *
     * @param  int $customerId
     * @return array
     */
    public function getProductViewedList($customerId, $limit = 10)
    {
        $customerId  = (int)$customerId;
        $storeId     = Mage::app()->getStore()->getId();
        $maxpagesize = ((int)$limit) ? (int)$limit : 10;
        $returnData  = array();

        if ($customerId) {
            try {
                $collection = Mage::getModel('reports/event')
                    ->getCollection()
                    ->addRecentlyFiler(
                        Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW,
                        $customerId,
                        0
                    )
                    ->setPageSize($maxpagesize)
                    ->setOrder('logged_at', Varien_Data_Collection::SORT_ORDER_DESC);

                if ($storeId) {
                    $collection->addStoreFilter($storeId);
                }

                $productIds = array();

                foreach ($collection as $event) {
                    $productIds[] = $event->getObjectId();
                }

                if (count($productIds)) {
                    $productCollection = Mage::getModel('catalog/product')
                        ->getCollection()
                        ->addAttributeToSelect('name')
                        ->addAttributeToSelect('sku')
                        ->addAttributeToSelect('price')
                        ->addAttributeToSelect('image')
                        ->addAttributeToSelect('url_path')
                        ->addIdFilter($productIds);

                    if ($storeId) {
                        $productCollection->setStoreId($storeId)
                            ->addStoreFilter($storeId);
                    }

                    foreach ($productCollection as $viewed) {
                        $returnData[] = $this->convertAttributeData($viewed);
                    }
                }
            } catch (Exception $e) {
                $returnData['error'] = self::ERROR_CODE_UNSUPPORTED_FEATURE;
            }
        }

        return $returnData;
    }


    /**
     * Load the customer compare list
     *
     * @param  int $customerId
     * @return array
     */
    public function getProductCompareList($customerId, $limit = 10)
    {
        $customerId  = (int)$customerId;
        $storeId     = Mage::app()->getStore()->getId();
        $maxpagesize = ((int)$limit) ? (int)$limit : 10;
        $returnData  = array();

        if ($customerId) {
            try {
                $model = Mage::getModel('catalog/product_compare_list');

                $collection = $model->getItemCollection()
                    ->useProductItem(true)
                    ->setCustomerId($customerId)
                    ->addAttributeToSelect('name')
                    ->addAttributeToSelect('sku')
                    ->addAttributeToSelect('price')
                    ->addAttributeToSelect('image')
                    ->addAttributeToSelect('url_path')
                    ->addAttributeToSelect('status')
                    ->setOrder('catalog_compare_item_id', 'DESC');

                if ($storeId) {
                    $collection->setStoreId($storeId)
                        ->addStoreFilter($storeId);
                }

                foreach ($collection as $compare) {
                    $returnData[] = $this->convertAttributeData($compare);
                }
            } catch (Exception $e) {
                $returnData['error'] = self::ERROR_CODE_UNSUPPORTED_FEATURE;
            }
        }

        return $returnData;
    }


    /**
     * Load the customer wishlist
     *
     * @param  int $customerId
     * @return array
     */
    public function getProductWishlist($customerId, $limit = 10)
    {
        $customerId  = (int)$customerId;
        $storeId     = Mage::app()->getStore()->getId();
        $maxpagesize = ((int)$limit) ? (int)$limit : 10;
        $returnData  = array();

        if ($customerId) {
            try {
                $model = Mage::getModel('wishlist/wishlist')
                    ->loadByCustomer($customerId);

                $collection = $model->getProductCollection()
                    ->addAttributeToSelect('name')
                    ->addAttributeToSelect('sku')
                    ->addAttributeToSelect('price')
                    ->addAttributeToSelect('image')
                    ->addAttributeToSelect('url_path')
                    ->addAttributeToSelect('status');

                foreach ($collection as $wishlist) {
                    $returnData[] = $this->convertAttributeData($wishlist);
                }
            } catch (Exception $e) {
                $returnData['error'] = self::ERROR_CODE_UNSUPPORTED_FEATURE;
            }
        }

        return $returnData;
    }


    /**
     * Writes random access keys to the system config
     *
     * @param string $scope
     * @param int $scopeId
     */
    public function generateAccessKeys($scope = 'default', $scopeId = 0)
    {
        $config   = Mage::getConfig();
        $key1     = md5(now() . rand(0, 32767) . $scope);
        $key2     = md5((rand(0, 32767) * (17 + $scopeId)) . now() . 'eyehubspot');

        $config->saveConfig('eyehubspot/settings/userkey', $key1, $scope, $scopeId);
        $config->saveConfig('eyehubspot/settings/passcode', $key2, $scope, $scopeId);
    }
}
