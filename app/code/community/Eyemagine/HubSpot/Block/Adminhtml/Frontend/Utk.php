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

class Eyemagine_HubSpot_Block_Adminhtml_Frontend_Utk
    extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * Render element html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        return sprintf(
            '<tr class="system-fieldset-sub-head" id="row_%s">
                <td class="label"><label for="%s">%s</label></td>
                <td class="value" colspan="4">%s</td>
            </tr>',
            $element->getHtmlId(),
            $element->getHtmlId(),
            $element->getLabel(),
            '<p>This extension supports the HubSpot UTK Cookie and will include
            the token value for orders and abandoned carts.</p>
            <p>Please note, you are responsible for adding the HubSpot tracking
            javascript to the site. The easiest method to add this javascript is
            to add it to the System Configuration for <b>Design</b> &gt;
            <b>Footer</b> &gt; <b>Miscellaneous HTML</b>.</p>'
        );
    }
}
