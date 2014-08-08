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

class Eyemagine_HubSpot_Model_Observer
{
    /**
     * Updates the Hubspot User Token (UTK) if the cookie exists
     *
     * Event: sales_quote_collect_totals_before
     *
     * @param  Varien_Event_Observer $observer
     * @return Eyemagine_HubSpot_Model_Observer
     */
    public function updateHubSpotUtkForQuote(Varien_Event_Observer $observer)
    {
        $quote    = $observer->getEvent()->getQuote();
        $store    = Mage::app()->getStore();

        // ignore cookie if admin store
        if (!$store->isAdmin()) {
            $utk = isset($_COOKIE['hubspotutk']) ? $_COOKIE['hubspotutk'] : null;

            if (!empty($utk)) {
                $quote->setHubspotUserToken($utk);
            }
        }

        return $this;
    }


    /**
     * Updates the Hubspot User Token (UTK) if the cookie exists
     *
     * Event: sales_convert_quote_to_order
     *
     * @param  Varien_Event_Observer $observer
     * @return Eyemagine_HubSpot_Model_Observer
     */
    public function copyHubSpotUtk(Varien_Event_Observer $observer)
    {
        $quote    = $observer->getEvent()->getQuote();
        $order    = $observer->getEvent()->getOrder();

        $order->setHubspotUserToken($quote->getHubspotUserToken());

        return $this;
    }
}
