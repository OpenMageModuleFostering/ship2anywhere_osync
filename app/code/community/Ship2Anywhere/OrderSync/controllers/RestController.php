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
 * Order sync rest controller 
 *
 * @category   Ship2Anywhere
 * @package    Ship2Anywhere_OrderSync
 * @subpackage controllers
 * @author     Michael Teasdale <michael@ship2anywhere.com.au>
 */

class Ship2Anywhere_OrderSync_RestController 
    extends Mage_Core_Controller_Front_Action
{
    protected function _checkKey()
    {
        $result = array('success' => false);
        if ($key = $this->getRequest()->getParam('key')) {
            $validKey = Mage::getStoreConfig("s2a_osync/token");
            if ($key == $validKey)
                $result['success'] = true;
            else
                $result['error'] = 'Your Sync Key is not valid';
        } else
            $result['error'] = 'Synk key was not found';

        return $result;
    }

    protected function _prepareResponse($data)
    {
        $response = $this->getResponse();
        if (isset($data['error']) && $data['error'])
            $response->setHeader('HTTP/1.0','403',true);
        else
            $response->setHeader('HTTP/1.0','200',true);

        $response->setBody(Mage::helper('core')->jsonEncode($data));
        return;
    }

    public function authAction()
    {
        $this->_prepareResponse($this->_checkKey());
    }

    public function ordersAction()
    {
        if (!Mage::getStoreConfig('s2a_osync/general/enable')) {
            $this->_prepareResponse(array(
                'success'   => false, 
                'error'     => 'Order Synchronization is disabled')
            );
            return;
        }

        $authResult = $this->_checkKey();
        if (isset($authResult['success']) AND !$authResult['success']) {
            $this->_prepareResponse($authResult);
            return;
        }

        switch ($this->getRequest()->getMethod()) {
            case 'GET':
                $this->_getOrders();
                break;

            case 'PUT':
            case 'POST':
                $this->_updateOrder($this->getRequest()->getParams());
                break;
        }
    }

    /**
     * Prepare Invoice info
     */
    public function documentsAction()
    {
        if (!Mage::getStoreConfig('s2a_osync/general/enable')) {
            $this->_prepareResponse(array(
                'success'   => false, 
                'error'     => 'Order Synchronization is disabled')
            );
            return;
        }

        $authResult = $this->_checkKey();
        if (isset($authResult['success']) AND !$authResult['success']) {
            $this->_prepareResponse($authResult);
            return;
        }

        $result = array();
        $params = $this->getRequest()->getParams();
        if (!isset($params['order_id']) OR !$params['order_id'])
            $result['error'] = "Order Id doesn't exist";

        $order = Mage::getModel('sales/order')
            ->loadByIncrementId($params['order_id']);

        if ($order->getId()) {
            $result['store_logo']     = '';
            if ($invoiceLogo = Mage::getStoreConfig('sales/identity/logo')) {
                $invoiceLogoPath = Mage::getBaseDir('media')
                     . DS . 'sales' . DS . 'store' . DS . 'logo' . DS . $invoiceLogo;
                if (file_exists($invoiceLogoPath))
                    $result['store_logo'] = base64_encode(file_get_contents($invoiceLogoPath));
            }

            $result['order_id']         = $order->getIncrementId();
            $result['order_created_at'] = $order->getCreatedAt();
            $result['destination']      = $this->_prepareDestination($order);
            $result['items']            = $this->_prepareOrderItems($order);
            $result['subtotal_amount']  = $order->getSubtotal();
            $result['shipping_amount']  = $order->getShippingAmount();
            $result['tax_amount']       = $order->getTaxAmount();
            $result['discount_amount']  = $order->getDiscountAmount();
            $result['total_amount']     = $order->getGrandTotal();
        } else
            $result['error'] = "Order with this ID doesn't exist";

        $this->_prepareResponse($result);
    }

    protected function _getOrders()
    {
        $orderCollection = Mage::getModel('sales/order')
            ->getCollection();

        if ($allowedStores = Mage::getStoreConfig('s2a_osync/general/allowed_stores')) {
            $allowedStores = explode(',', $allowedStores);
            if (count($allowedStores))
                $orderCollection->addFieldToFilter('store_id', array('in' => $allowedStores));
        }

        if ($allowedStatuses = Mage::getStoreConfig('s2a_osync/general/allowed_order_statuses')) {
            $allowedStatuses = explode(',', $allowedStatuses);
            if (count($allowedStatuses))
                $orderCollection->addFieldToFilter('status', array('in' => $allowedStatuses));
        }

        $result = array();
        if ($orderCollection->count()) {
            $i = 1;
            foreach ($orderCollection as $_order) {
                $result[$i]['order_id'] = $_order->getIncrementId();
                $result[$i]['destination'] = $this->_prepareDestination($_order);
                $result[$i]['items'] = $this->_prepareOrderItems($_order);

                $i++;
            }
        }
        if (count($result))
            $this->_prepareResponse(array('orders' => $result));
        else {
            $this->_prepareResponse(array(
                'success'   => false, 
                'error'     => 'There are no orders to import')
            );
            return;
        }
    }

    protected function _updateOrder($params)
    {
        $result = array('success' => false);
        if (!isset($params['order_id']) OR !$params['order_id'])
            $result['error'] = "Order Id doesn't exist";

        if (!isset($params['tracking_number']) OR !$params['tracking_number'])
            $result['error'] = "Tracking Number doesn't exist";

        $order = Mage::getModel('sales/order')
            ->loadByIncrementId($params['order_id']);

        if ($order->getId()) {
            if ($order->canShip()) {
                $shipment = Mage::getModel('sales/service_order', $order)
                    ->prepareShipment($this->_getItemQtys($order));

                $shipment->addTrack(Mage::getModel('sales/order_shipment_track')
                    ->addData(array(
                        'carrier_code'  => $order->getShippingCarrier()->getCarrierCode(),
                        'title'         => $order->getShippingDescription(),
                        'number'        => $params['tracking_number'],
                    )));

                $shipment->register();
                $order->setIsInProcess(true);

                Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($order)
                    ->save();

                if (!$shipment->getEmailSent()) {
                    $shipment->sendEmail(true);
                    $shipment->setEmailSent(1);
                    $shipment->save();
                }
                $result = array('success' => true);
            } else
                $result['error'] = "Order with this ID can't be shipped";
        } else
            $result['error'] = "Order with this ID doesn't exist";

        $this->_prepareResponse($result);
    }

    /**
     * Prepare Shipping address
     *
     * @param $order Mage_Sales_Model_Order
     * @return array
     */
    protected function _prepareDestination(Mage_Sales_Model_Order $order)
    {
        $destination = array();
        $shippingAddress = $order->getShippingAddress();
        $destination['firstname'] = $shippingAddress->getFirstname();
        $destination['lastname'] = $shippingAddress->getLastname();
        $destination['street'] = $shippingAddress->getStreet();
        $destination['city'] = $shippingAddress->getCity();
        $destination['postcode'] = $shippingAddress->getPostcode();
        $destination['country'] = $shippingAddress->getCountryId();
        $destination['phone'] = $shippingAddress->getTelephone();
        $destination['email'] = $order->getCustomerEmail();

        return $destination;
    }

    /**
     * Prepare all Visible order items
     *
     * @param $order Mage_Sales_Model_Order
     * @return array
     */
    protected function _prepareOrderItems(Mage_Sales_Model_Order $order)
    {
        $orderItems = array();
        foreach ($order->getAllVisibleItems() as $_orderItem) {
            $orderItems[$_orderItem->getItemId()]['name'] = $_orderItem->getName();
            $orderItems[$_orderItem->getItemId()]['sku'] = $_orderItem->getSku();
            $orderItems[$_orderItem->getItemId()]['qty'] = $_orderItem->getQtyOrdered();
            $orderItems[$_orderItem->getItemId()]['weight'] = $_orderItem->getRowWeight();

            $qty = floatval($_orderItem->getQty());
            if (!$qty)
                $qty = floatval($_orderItem->getQtyOrdered());

            $rowPrice = floatval($_orderItem->getValue()) * $qty;
            if (!$rowPrice)
                $rowPrice = floatval($_orderItem->getRowTotalInclTax());
            if (!$rowPrice)
                $rowPrice = floatval($_orderItem->getRowTotal());
            if (!$rowPrice)
                $rowPrice = floatval($_orderItem->getPrice()) * $qty;

            $orderItems[$_orderItem->getItemId()]['price'] = $rowPrice;
        }

        return $orderItems;
    }

    /**
     * Get the Quantities shipped for the Order, based on an item-level
     * This method can also be modified, to have the Partial Shipment functionality in place
     *
     * @param $order Mage_Sales_Model_Order
     * @return array
     */
    protected function _getItemQtys(Mage_Sales_Model_Order $order)
    {
        $qty = array();
        foreach ($order->getAllItems() as $_item) {
            if ($_item->getParentItemId())
                $qty[$_item->getParentItemId()] = $_item->getQtyOrdered();
            else
                $qty[$_item->getId()] = $_item->getQtyOrdered();
        }

        return $qty;
    }
}
