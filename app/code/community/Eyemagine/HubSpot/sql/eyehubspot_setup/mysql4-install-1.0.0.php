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

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

$installer->getConnection()->addColumn($installer->getTable('sales/quote'),
    'hubspot_user_token', 'VARCHAR(40) DEFAULT NULL');
$installer->getConnection()->addColumn($installer->getTable('sales/order'),
    'hubspot_user_token', 'VARCHAR(40) DEFAULT NULL');

$installer->endSetup();

Mage::helper('eyehubspot')->generateAccessKeys();