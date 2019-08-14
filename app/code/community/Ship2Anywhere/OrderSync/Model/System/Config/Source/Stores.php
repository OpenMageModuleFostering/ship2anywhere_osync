<?php
/**
 * Ship 2 Anywhere extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Ship2Anywhere
 * @package    Ship2Anywhere_OrderSync
 * @copyright  Copyright (C) 2014 Ship 2 Anywhere (https://www.ship2anywhere.com.au/)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Ship 2 Anywhere System Config Source Stores Model
 *
 * @category   Ship2Anywhere
 * @package    Ship2Anywhere_OrderSync
 * @subpackage Model
 * @author     Michael Teasdale <michael@ship2anywhere.com.au>
 */

class Ship2Anywhere_OrderSync_Model_System_Config_Source_Stores 
    extends Ship2Anywhere_OrderSync_Model_System_Config_Source
{
    protected function _setupOptions()
    {
        if (is_null($this->_options)) {
            $this->_options = array();
            foreach (Mage::app()->getWebsites() as $website) {
                foreach ($website->getGroups() as $group) {
                    $stores = $group->getStores();
                    foreach ($stores as $store) {
                        $this->_options[$store->getStoreId()] = $store->getName();
                    }
                }
            }
        }
    }
}
