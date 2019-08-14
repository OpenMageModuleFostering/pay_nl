<?php

class Pay_Payment_OrderController extends Mage_Core_Controller_Front_Action {

    public function returnAction() {

        try {
            $params = $this->getRequest()->getParams();

            $transactionId = $params['orderId'];

            $orderHelper = Mage::helper('pay_payment/order');
            /** @var $orderHelper Pay_Payment_Helper_Order */
            $orderHelper instanceof Pay_Payment_Helper_Order;
            
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
            
            $restoreCart = Mage::getStoreConfig('pay_payment/general/restore_cart', Mage::app()->getStore()) && 
                    (
                        // Wanneer persistent shopping cart aan staat, wordt bij NIET ingelogde gebruikers de winkelwagen, dubbel gerestored.
                        // Bij wel ingelogde gebruikers lijkt persistent shopping cart niet te werken.
                        // Vandaar dat ik hier controleer of de gebruiker is ingelogd, of persistent shopping cart uit staat
                        Mage::getSingleton('customer/session')->isLoggedIn() || 
                        Mage::getStoreConfig('persistent/options/enabled', Mage::app()->getStore()) == 0
                    );      
            if ($restoreCart) {
                $items = $order->getItemsCollection();
                foreach ($items as $item) {
                    try {
                        $cart = Mage::getSingleton('checkout/cart');

                        $cart->addOrderItem($item);
                    } catch (Mage_Core_Exception $e) {
                        if (Mage::getSingleton('checkout/session')->getUseNotice(true)) {
                            Mage::getSingleton('checkout/session')->addNotice($e->getMessage());
                        } else {
                            Mage::getSingleton('checkout/session')->addError($e->getMessage());
                        }
                        $this->_redirect($pageCanceled);
                    } catch (Exception $e) {
                        Mage::getSingleton('checkout/session')->addException($e, Mage::helper('checkout')->__('Cannot add the item to shopping cart.')
                        );
                        $this->_redirect($pageCanceled);
                    }
                }
                $cart->save();
            }

            $this->_redirect($pageCanceled);
        }
    }

    public function exchangeAction() {
        $params = $this->getRequest()->getParams();      

        $transactionId = $params['order_id'];
            
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
                $resultMsg = 'ERROR: ';
            }
            $resultMsg .= $e->getMessage();
        } catch (Exception $e) {
            $resultMsg = 'ERROR: ' . $e->getMessage();
        }

        echo "TRUE|" . $resultMsg;
        die();
    }

}
