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

class Eyemagine_HubSpot_Block_Adminhtml_Frontend_Eyemagine_Info
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
        $useContainerId = $element->getData('use_container_id');
        $remoteHtml     = @file_get_contents('http://www.eyemaginetech.com/canvas/info/eyehubspot.php');

        if ($remoteHtml) {
            return sprintf(
                '<tr class="system-fieldset-sub-head" id="row_%s"><td colspan="5">%s</td></tr>',
                $element->getHtmlId(),
                $remoteHtml
            );
        }
    }
}
