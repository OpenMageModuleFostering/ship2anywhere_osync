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
 * Ship 2 Anywhere System Config Token Info Block
 *
 * @category   Ship2Anywhere
 * @package    Ship2Anywhere_OrderSync
 * @subpackage Block
 * @author     Michael Teasdale <michael@ship2anywhere.com.au>
 */

class Ship2Anywhere_OrderSync_Block_Adminhtml_Sales_Order_View_Tab_Info
    extends Mage_Adminhtml_Block_Sales_Order_View_Tab_Info
{
    public function getTrackingNumbers()
    {
        $trackNums = array();
        $shipmentCollection = $this->getSource()->getShipmentsCollection();
        foreach ($shipmentCollection as $_shipment) {
            foreach ($_shipment->getAllTracks() as $tracknum) {
                $trackNums[] = $tracknum->getNumber();
            }
        }
        if (count($trackNums)) {
            return implode(', ', $trackNums);
        } else {
            return false;
        }
    }
}
