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

class Eyemagine_HubSpot_Block_Adminhtml_Frontend_Button_Regen
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Caches the HTML
     *
     * @var string
     */
    protected $_buttonHtml = null;


    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        if ($this->_buttonHtml === null) {
            $this->_buttonHtml = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setId($element->getId())
                ->setType('button')
                ->setLabel($this->__('Regenerate'))
                ->setOnClick('setLocation(\'' . Mage::helper('adminhtml')->getUrl("*/hubspot_index/regenerate") . '\')')
                ->toHtml();
        }

        return $this->_buttonHtml;
    }
}
