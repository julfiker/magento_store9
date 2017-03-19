<?php

/**
 * Magestore
 * 
 * NOTICE OF LICENSE
 * 
 * This source file is subject to the Magestore.com license that is
 * available through the world-wide-web at this URL:
 * http://www.magestore.com/license-agreement.html
 * 
 * DISCLAIMER
 * 
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 * 
 * @category    Magestore
 * @package     Magestore_Webpos
 * @copyright   Copyright (c) 2012 Magestore (http://www.magestore.com/)
 * @license     http://www.magestore.com/license-agreement.html
 */

/**
 * Webpos Observer Model
 * 
 * @category    Magestore
 * @package     Magestore_Webpos
 * @author      Magestore Developer
 */
class Magestore_Webpos_Model_Observer {

    /**
     * process controller_action_predispatch event
     *
     * @return Magestore_Webpos_Model_Observer
     */
    public function controllerActionPredispatch($observer) {
        $action = $observer->getEvent()->getControllerAction();
        return $this;
    }

    public function skipPaymentMethod($observers) {
        $result = $observers->getResult();
        $methodInstance = $observers->getMethodInstance();
        $module = Mage::app()->getRequest()->getRouteName();
        if ($module == 'webpos') {
            $allowPayments = Mage::getModel('webpos/source_adminhtml_payment')->getAllowPaymentMethods();
            if (Mage::getStoreConfig('webpos/payment/allowspecific_payment', Mage::app()->getStore()->getId()) == '1') {
                $specificpayment = Mage::getStoreConfig('webpos/payment/specificpayment', Mage::app()->getStore()->getId());
                $specificpayment = explode(',', $specificpayment);
                if (in_array($methodInstance->getCode(), $specificpayment)) {
                    $result->isAvailable = true;
                    $result->isDeniedInConfig = false;
                } else {
                    $result->isAvailable = false;
                    $result->isDeniedInConfig = true;
                }
            } else {
                if (in_array($methodInstance->getCode(), $allowPayments)) {
                    $result->isAvailable = true;
                    $result->isDeniedInConfig = false;
                } else {
                    $result->isAvailable = false;
                    $result->isDeniedInConfig = true;
                }
            }
        }
        return $this;
    }

    public function orderPlaceAfter($observers) {
        /* unset session after place order Mr.Jack */
        Mage::getSingleton('webpos/session')->setDiscountValue(0);
        Mage::getSingleton('webpos/session')->setWebposCash(0);
        Mage::getSingleton("checkout/session")->setData("coupon_code", null);
        /**/
        $session = Mage::getSingleton('checkout/session');
        $session->unsetData('webpos_cashin');
        $session->unsetData('webpos_admin_discount');
        $giftwrap = $session->getData('webpos_giftwrap');
        $giftwrapAmount = $session->getData('webpos_giftwrap_amount');
        if ($giftwrap || $giftwrapAmount) {
            $session->unsetData('webpos_giftwrap');
            $session->unsetData('webpos_giftwrap_amount');
        }
        Mage::getSingleton('core/session')->setData('webpos_price', null);

        //Save delivery date
        $order = $observers->getEvent()->getOrder();
        $webpos_delivery_date = $session->getData('webpos_delivery_date');
        if ($webpos_delivery_date != "") {
            try {
                $order->setWebposDeliveryDate($webpos_delivery_date)->save();
            } catch (Exception $e) {
                
            }
        }
        //Save Comment
        $order = $observers->getEvent()->getOrder();
        $customerComment = $session->getData('customer_comment');
        if ($customerComment != "") {
            try {
                $order->setWebposOrderComment($customerComment);
                $order->setCustomerNote($customerComment)->save();
            } catch (Exception $e) {
                
            }
        }
        //Save survey				
        $orderId = $order->getId();
        $surveyQuestion = $session->getData('survey_question');
        $surveyAnswer = $session->getData('survey_answer');
        $survey = Mage::getModel('webpos/survey');
        if ($surveyAnswer) {
            try {
                $survey->setData('question', $surveyQuestion)
                        ->setData('answer', $surveyAnswer)
                        ->setData('order_id', $orderId)
                        ->save();
            } catch (Exception $e) {
                
            }
            $session->unsetData('survey_question');
            $session->unsetData('survey_answer');
        }
    }

    /* Mr Jack set item name while adding to cart */

    public function quoteItemSetProduct($observer) {
        $product = $observer['product'];
        if (strpos($product->getSku(), 'webpos-customsale') === false) {
            return;
        }
        $tax_class_id = $product->getCustomOption('tax_class_id');
        if ($tax_class_id && $tax_class_id->getValue()) {
            $item = $observer['quote_item'];
            $item->getProduct()->setTaxClassId($tax_class_id->getValue());
        }
        $name = $product->getCustomOption('name');
        if ($name && $name->getValue()) {
            $item = $observer['quote_item'];
            $item->setName($name->getValue());
        }
    }

    public function applyCouponEvent($observer) {
        $coupon_code = trim(Mage::getSingleton("checkout/session")->getData("coupon_code"));
        if ($coupon_code != '') {
            Mage::getSingleton('checkout/cart')->getQuote()->setCouponCode($coupon_code)->save();
        }
    }

    public function orderPaymentPlaceStart($observer) {
        $payment = $observer['payment'];
        $order = $payment->getOrder();
        if ($order->getWebposBaseCash() > 0.0001) {
            // Update paid info
            $was = $payment->getDataUsingMethod('base_amount_paid');
            $payment->setDataUsingMethod('base_amount_paid', $was + $order->getWebposBaseCash());

            $was = $payment->getDataUsingMethod('amount_paid');
            $payment->setDataUsingMethod('amount_paid', $was + $order->getWebposCash());
        }
    }

    /**/

    public function salesOrderSaveAfter($observer) {
        $order = $observer->getOrder();

        $previousOrderId = $order->getRelationParentId();
        $orderIncrementId = $order->getIncrementId();
        $stateCancel = Mage_Sales_Model_Order::STATE_CANCELED;
        // Only trigger when an order enters processing state.
        if ($order->getState() == $stateCancel && $order->getOrigData('state') != $stateCancel) {
            if ($order->getWebposAdminId()) {
                $webposModel = Mage::getModel('webpos/posorder')->load($orderIncrementId, 'order_id');
                $webposModel->setOrderStatus($stateCancel);
                $webposModel->save();
            }
        }
        if ($previousOrderId) {
            $previousOrderModel = Mage::getModel('sales/order')->load($previousOrderId);
            $userId = $previousOrderModel->getWebposAdminId();
            $orderIncrementPreviousId = $previousOrderModel->getIncrementId();
            $webposOrder = Mage::getModel('webpos/posorder')->load($orderIncrementPreviousId, 'order_id');
            if ($webposOrder->getWebposOrderId() && $userId > 0) {
                $statusOrder = $previousOrderModel->getStatus();
                $webposOrder->setOrderStatus($statusOrder);
                $webposOrder->save();
                $findOrderInWebpos = Mage::getModel('webpos/posorder')->load($orderIncrementId, 'order_id');
                $data = array();
                $data['user_id'] = $userId;
                $data['order_id'] = $order->getIncrementId();
                $data['order_comment'] = $order->getOrderComment();
                $data['order_totals'] = $order->getGrandTotal();
                $data['order_status'] = $order->getStatus();
                $data['user_location_id'] = $webposOrder->getUserLocationId();
                $data['user_role_id'] = $webposOrder->getUserRoleId();
                $data['created_date'] = $order->getCreatedAt();
                if (!$findOrderInWebpos->getWebposOrderId()) {

                    $createModel = Mage::getModel('webpos/posorder');
                    $createModel->setData($data);
                    $createModel->save();
                } else {
                    $findOrderInWebpos->setOrderStatus($order->getStatus());
                    $findOrderInWebpos->setCreatedAt($order->getCreatedAt());
                    $findOrderInWebpos->save();
                }
            }
        }

        //var_dump($previousOrder);die();
    }

    public function catalogProductSaveAfter($observer) {
        $product = $observer->getProduct();
        $productId = $product->getId();
        $productIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
        if (empty($productIds)) {
            $productIds = array();
        }
        $dir = Mage::getBaseDir('media') . DS . 'webpos';
        $product_updatedfile = $dir . DS . 'product_updated.txt';
        if (!is_dir_writeable($dir)) {
            $file = new Varien_Io_File;
            $file->checkAndCreateFolder($dir);
        }
        $date = getdate();
        $updated_time = $date[0];
        $fileContent = file_get_contents($product_updatedfile);
        $fileContent = Zend_Json::decode($fileContent);
        if (count($productIds) > 0) {
            foreach ($productIds as $prdId) {
                $fileContent[$prdId] = $updated_time;
            }
        } else {
            $fileContent[$productId] = $updated_time;
        }
        //$fileContent = array_unique($fileContent);
        $fileContent = Zend_Json::encode($fileContent);
        file_put_contents($product_updatedfile, $fileContent);
    }

    public function customerSaveAfter($observer) {
        $customer = $observer->getCustomer();
        $customerId = $customer->getId();
        $dir = Mage::getBaseDir('media') . DS . 'webpos';
        $customer_updatedfile = $dir . DS . 'customer_updated.txt';
        if (!is_dir_writeable($dir)) {
            $file = new Varien_Io_File;
            $file->checkAndCreateFolder($dir);
        }
        $date = getdate();
        $updated_time = $date[0];
        $fileContent = file_get_contents($customer_updatedfile);
        $fileContent = Zend_Json::decode($fileContent);
        $fileContent[$customerId] = $updated_time;
        $fileContent = Zend_Json::encode($fileContent);
        file_put_contents($customer_updatedfile, $fileContent);
    }

    public function salesQuoteCollectTotalsBefore($observer) {
        $quote = $observer->getQuote();
        $items = $quote->getAllVisibleItems();
        $tax_off = Mage::getModel('webpos/session')->getData('tax_off');
        if (isset($tax_off)) {
            if (count($items) > 0)
                foreach ($items as $item) {
                    if ($tax_off == true) {
                        $item->getProduct()->setTaxClassId(0);
                    } else {
                        $taxClassId = Mage::getModel('catalog/product')->load($item->getProduct()->getId())->getTaxClassId();
                        $item->getProduct()->setTaxClassId($taxClassId);
                    }
                }
        }
        $shipping_amount = Mage::getModel('webpos/session')->getData('custom_shipping_amount');
        if (isset($shipping_amount)) {
            $shippingMethod = $quote->getShippingAddress()->getShippingMethod();
            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->getShippingAddress()->collectShippingRates();
            $address = $quote->getShippingAddress();
            $address->setShippingAmount($shipping_amount);
            $address->setBaseShippingAmount($shipping_amount);
            $rates = $address->collectShippingRates()
                    ->getGroupedAllShippingRates();
            foreach ($rates as $carrier) {
                foreach ($carrier as $rate) {
                    if ($rate->getCode() == $shippingMethod) {
                        $rate->setPrice($shipping_amount);
                        $rate->save();
                    }
                }
            }
            $address->setCollectShippingRates(false);
            $address->save();
        }
    }

    /* Daniel - S: Integrate Apptha Marketplace && Webkul MarketPlace */

    public function webposBlockListproductEvent($observer) {
        $productCollection = $observer->getData('pos_get_product_colection');
        if (Mage::helper('core')->isModuleEnabled('Apptha_Marketplace')) {
            $posUser = Mage::getModel('webpos/session')->getUser();
            $sellerId = $posUser->getSellerId();
            if ($sellerId) {
                $productCollection->addAttributeToFilter('seller_id', $sellerId);
            }
        } elseif (Mage::helper('core')->isModuleEnabled('Webkul_Marketplace')) {
            $seller_id = Mage::getModel('webpos/session')->getUser()->getSellerId();
            if ($seller_id != 0) {
                $collection = Mage::getModel('marketplace/product')->getCollection()->addFieldToSelect('mageproductid')->addFieldToFilter('userid', $seller_id);
                $ids = array();
                if (count($collection) > 0)
                    foreach ($collection as $product) {
                        $ids[] = $product->getData('mageproductid');
                    }
                $productCollection->addAttributeToFilter('entity_id', array('in' => $ids));
            }
        }
    }

    /* Daniel - F: Integrate Apptha Marketplace && Webkul MarketPlace */
    
    /**
     * save address for default customer on webpos
     * @param type $observer
     */
    public function webposConfigurationChange($observer) {
        $customerId = Mage::getStoreConfig('webpos/guest_checkout/customer_id');
        $customerData = Mage::getModel('customer/customer')->load($customerId);

        $first_name = Mage::getStoreConfig('webpos/guest_checkout/first_name');
        $last_name = Mage::getStoreConfig('webpos/guest_checkout/last_name');
        $street = Mage::getStoreConfig('webpos/guest_checkout/street');
        $country_id = Mage::getStoreConfig('webpos/guest_checkout/country_id');
        $region_id = Mage::getStoreConfig('webpos/guest_checkout/region_id');
        $city = Mage::getStoreConfig('webpos/guest_checkout/city');
        $zip = Mage::getStoreConfig('webpos/guest_checkout/zip');
        $telephone = Mage::getStoreConfig('webpos/guest_checkout/telephone');
        $email = Mage::getStoreConfig('webpos/guest_checkout/email');

        $customerData->setData('email',$email);
        $customerData->setData('firstname',$first_name);
        $customerData->setData('lastname',$last_name);
        $customerData->setData('city',$city);
        $customerData->setData('region_id',$region_id);
        $customerData->setData('region',$region_id);

        $addressCustomer = $customerData->getAddresses();

        foreach ($addressCustomer as $key => $value) {
            $value->setData('lastname',$last_name);
            $value->setData('firstname',$first_name);
            $value->setData('city',$city);
            $value->setData('region',$region_id);
            $value->setData('region_id',$region_id);
            $value->setData('postcode',$zip);
            $value->setData('telephone',$telephone);
            $value->setData('street',$street);
            $value->setData('country_id',$country_id);
            $value->setData('email',$email);
            $value->save();
            
        }
        try{
            $customerData->save();
        }  catch (Exception $e){
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }
    }

}
