<?php

class Pay_Payment_OrderController extends Mage_Core_Controller_Front_Action {

    public function returnAction() {

        try {
            $params = $this->getRequest()->getParams();

            $transactionId = $params['orderId'];

            $orderHelper = Mage::helper('pay_payment/order');
            /** @var $orderHelper Pay_Payment_Helper_Order */
            
            $status = $orderHelper->getTransactionStatus($transactionId);
            $order = $orderHelper->getOrderByTransactionId($transactionId);
            //$orderHelper->processByTransactionId($transactionId);
        } catch (Pay_Payment_Exception $e) {
            if ($e->getCode() != 0) {
                throw new Exception($e);
            }
        }
        
        $pageSuccess = Mage::getStoreConfig('pay_payment/general/page_success', Mage::app()->getStore());
        $pagePending = Mage::getStoreConfig('pay_payment/general/page_pending', Mage::app()->getStore());
        $pageCanceled = Mage::getStoreConfig('pay_payment/general/page_canceled', Mage::app()->getStore());

        
        if ($status == Pay_Payment_Model_Transaction::STATE_CANCELED) {
            Mage::getSingleton('checkout/session')->addNotice('Betaling geannuleerd');
        }
        if ($status == Pay_Payment_Model_Transaction::STATE_SUCCESS) {
            $this->_redirect($pageSuccess);
        } elseif ($status == Pay_Payment_Model_Transaction::STATE_PENDING) {
            $this->_redirect($pagePending);
        } else {

            $restoreCart = Mage::getStoreConfig('pay_payment/general/restore_cart', Mage::app()->getStore());
            if ($restoreCart) {
                $quoteModel = Mage::getModel('sales/quote');
                $quoteId = $order->getQuoteId();

                /**
                 * @var $quote Mage_Sales_Model_Quote
                 */
                $quote = $quoteModel->load($quoteId);

                $quote->setIsActive(true)->save();
            }

            $this->_redirect($pageCanceled);
        }
    }

    public function exchangeAction() {


        $error = false;
        $params = $this->getRequest()->getParams();

        $transactionId = $params['order_id'];
        if(empty($transactionId)){
            $get = $this->getRequest()->setParamSources(array('_GET'))->getParams();
            $post = $this->getRequest()->setParamSources(array('_POST'))->getParams();

            if(!empty($get['order_id'])){
                $transactionId = $get['order_id'];
                Mage::log('Error in exchange, but fixed by getting only the _GET var ',null,'exchange.log');
            } elseif(!empty($post['order_id'])) {
                $transactionId = $post['order_id'];
                Mage::log('Error in exchange, but fixed by getting only the _POST var ',null,'exchange.log');
            } else {
                Mage::log('Error in exchange, cannot find orderId in _GET or _POST ',null,'exchange.log');
            }

            $get=$this->getRequest()->setParamSources(array('_GET'))->getParams();
            $post=$this->getRequest()->setParamSources(array('_POST'))->getParams();
            Mage::log('_GET was: '.json_encode($get),null,'exchange.log');
            Mage::log('_POST was: '.json_encode($post),null,'exchange.log');
        }
            
        $helper = Mage::helper('pay_payment');
        /** @var $helper Pay_Payment_Helper_Data */
        try {
            $orderHelper = Mage::helper('pay_payment/order');
            /** @var $orderHelper Pay_Payment_Helper_Order */
            
            if ($params['action'] == 'pending') {
                throw Mage::exception('Pay_Payment', 'Ignoring pending', 0);
            }
           
            $status = $orderHelper->processByTransactionId($transactionId);        

            $resultMsg = 'Status updated to ' . $status;
        } catch (Pay_Payment_Exception $e) {
            if ($e->getCode() == 0) {
                $resultMsg = 'NOTICE: ';
            } else {
                $error = true;
                $resultMsg = 'ERROR: ';
            }
            $resultMsg .= $e->getMessage();
        } catch (Exception $e) {
            $error = true;
            $resultMsg = 'ERROR: ' . $e->getMessage();
        }

        if($error){
            echo "FALSE|" . $resultMsg;
        } else {
            echo "TRUE|" . $resultMsg;
        }
        
        die();
    }

}
