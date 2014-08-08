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
class Eyemagine_HubSpot_Adminhtml_Hubspot_IndexController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Regenerate the access codes for HubSpot Integration
     */
    public function regenerateAction()
    {
        Mage::helper('eyehubspot')->generateAccessKeys();

        $this->_redirectReferer();
    }
}
