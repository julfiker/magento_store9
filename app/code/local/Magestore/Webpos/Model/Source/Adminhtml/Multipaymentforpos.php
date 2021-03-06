<?php

/*
 * Web POS by Magestore.com
 * Version 2.3
 * Updated by Daniel - 12/2015
 */

class Magestore_Webpos_Model_Source_Adminhtml_Multipaymentforpos {

    protected $_allowPayments = array();
    protected $_allowPaymentsWithLabel = array();

    public function __construct() {
        $this->_allowPayments = array('cashforpos', 'ccforpos', 'codforpos', 'cp1forpos', 'cp2forpos');
        $this->_allowPaymentsWithLabel = array(
            'cashforpos' => Mage::helper('webpos/payment')->getCashMethodTitle(),
            'ccforpos' => Mage::helper('webpos/payment')->getCcMethodTitle(),
            'codforpos' => Mage::helper('webpos/payment')->getCodMethodTitle(),
            'cp1forpos' => Mage::helper('webpos/payment')->getCp1MethodTitle(),
            'cp2forpos' => Mage::helper('webpos/payment')->getCp2MethodTitle()
        );
    }

    public function toOptionArray() {
        $collection = Mage::getModel('payment/config')->getAllMethods();

        if (!count($collection))
            return;

        $options = array();
        foreach ($collection as $item) {
            if (!in_array($item->getId(), $this->_allowPayments))
                continue;
            $title = $item->getTitle() ? $item->getTitle() : $item->getId();
            $options[] = array('value' => $item->getId(), 'label' => $title);
        }

        return $options;
    }

    public function getAllowPaymentMethods() {
        return $this->_allowPayments;
    }

    public function getAllowPaymentMethodsWithLabel() {
        return $this->_allowPaymentsWithLabel;
    }

}
