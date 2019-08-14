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

class Ship2Anywhere_OrderSync_Block_Adminhtml_System_Config_Form_Field_TokenInfo
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    const KEY_LENGTH = 32;
    protected $_element;

    protected function _getOsyncKey()
    {
        if (!Mage::getStoreConfig("s2a_osync/token")) {
            $key = Mage::helper('core')->getRandomString(self::KEY_LENGTH);
            $op = new Mage_Core_Model_Config();
            $op->saveConfig('s2a_osync/token', $key, 'default', 0);
            return $key;
        }

        return Mage::getStoreConfig("s2a_osync/token");
    }

    protected function _getOsyncUrl()
    {
        return Mage::getBaseUrl() . 'osync/rest/';
    }
    
    /**
     * @see Mage_Adminhtml_Block_System_Config_Form_Field::render()
     *
     * Adds the "required" star to the form field.
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = '<div style="margin:5px 5px; float:left;">';
        $html .= '<strong>' . $this->__('Your Sync URL') . '</strong>';
        $html .= '</div>';

        $html .= '<div style="margin:5px 210px;">';
        $html .= '<strong>' . $this->_getOsyncUrl() . '</strong>';
        $html .= '</div>';

        $html .= '<div style="margin:5px 5px 20px; float:left;">';
        $html .= '<strong>' . $this->__('Your Sync Key') . '</strong>';
        $html .= '</div>';

        $html .= '<div style="margin:5px 210px 20px;">';
        $html .= '<strong>' . $this->_getOsyncKey() . '</strong>';
        $html .= '</div>';

        $html .= '<div style="clear:both;"></div>';


        return $html;
    }
}
