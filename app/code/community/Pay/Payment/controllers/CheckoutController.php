<?php

class Pay_Payment_CheckoutController extends Mage_Core_Controller_Front_Action {

    public function redirectAction() {
        $helper = Mage::helper('pay_payment');
        $session = Mage::getSingleton('checkout/session');
        /* @var $session Mage_Checkout_Model_Session */
        if ($session->getLastRealOrderId()) {
            $orderId = $session->getLastRealOrderId();
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            /* @var $order Mage_Sales_Model_Order */
            $payment = $order->getPayment();

            $store = Mage::app()->getStore();


            if ($order->getId()) {
                $optionId = $session->getOptionId();
                $optionSubId = $session->getOptionSubId();

                $serviceId = Mage::getStoreConfig('pay_payment/general/serviceid', Mage::app()->getStore());

                $apiToken = Mage::getStoreConfig('pay_payment/general/apitoken', Mage::app()->getStore());

                $amount = $order->getGrandTotal();

                $description = $order->getIncrementId();

                $sendOrderData = Mage::getStoreConfig('pay_payment/general/send_order_data', Mage::app()->getStore());

                $api = Mage::helper('pay_payment/api_start');
                /* @var $api Pay_Payment_Helper_Api_Start */
                $api->setExtra2($order->getCustomerEmail());

                if ($sendOrderData == 1) {

                    $itemsTotal = 0;

                    $items = $order->getItemsCollection();
                    foreach ($items as $item) {
                        /* @var $item Mage_Sales_Model_Order_Item */
                        $productId = $item->getId();
                        $description = $item->getName();
                        $price = $item->getPriceInclTax();
                        $taxAmount = $item->getTaxAmount();
                        $quantity = $item->getQtyOrdered();

                        $taxClass= $helper->calculateTaxClass($price,$taxAmount);

                        $price = round($price * 100);

                        $itemsTotal += $price * $quantity;

                        if ($price != 0) {
                            $api->addProduct($productId, $description, $price, $quantity, $taxClass);
                        }
                    }

                    $discountAmount = $order->getDiscountAmount();

                    if ($discountAmount < 0) {
                        $itemsTotal += round($discountAmount * 100);
                        $api->addProduct(0, 'Korting (' . $order->getDiscountDescription() . ')', round($discountAmount * 100), 1, 'N');
                    }

                    $shipping = $order->getShippingInclTax();
                    $shippingTax = $order->getShippingTaxAmount();
                    $shippingTaxClass = $helper->calculateTaxClass($shipping, $shippingTax);
                    $shipping = round($shipping * 100);
                    if ($shipping != 0) {
                        $itemsTotal += round($shipping * 100);
                        $api->addProduct('0', 'Verzendkosten', $shipping, 1, $shippingTaxClass);
                    }
                    $extraFee = $order->getPaymentCharge();
                    if ($extraFee != 0) {
                        $itemsTotal += round($extraFee * 100);
                        $api->addProduct('0', Mage::getStoreConfig('pay_payment/general/text_payment_charge', Mage::app()->getStore()), round($extraFee * 100), 1, 'H');
                    }

                    $arrEnduser = array();
                    $shippingAddress = $order->getShippingAddress();

                    $arrEnduser['gender'] = substr($order->getCustomerGender(), 0, 1);

                    $arrEnduser['dob'] = $order->getCustomerDob();
                    $arrEnduser['emailAddress'] = $order->getCustomerEmail();
                    $billingAddress = $order->getBillingAddress();

                    
                    
                    if (!empty($shippingAddress)) {
                        $arrEnduser['initials'] = substr($shippingAddress->getFirstname(), 0, 1);
                        $arrEnduser['lastName'] = substr($shippingAddress->getLastname(), 0, 30);

                        $streetName = "";
                        $streetNumber = "";
                        $matches = array();
                        $addressFull = $shippingAddress->getStreetFull();
                        $addressFull = str_replace("\n", ' ', $addressFull);
                        $addressFull = str_replace("\r", ' ', $addressFull);
    

                        list($address, $housenumber) = $helper->splitAddress($addressFull);

                        $arrEnduser['address']['streetName'] = $address;
                        $arrEnduser['address']['streetNumber'] = $housenumber;
                        $arrEnduser['address']['zipCode'] = $shippingAddress->getPostcode();
                        $arrEnduser['address']['city'] = $shippingAddress->getCity();
                        $arrEnduser['address']['countryCode'] = $shippingAddress->getCountry();
                    } elseif (!empty($billingAddress)) {
                        $arrEnduser['initials'] = substr($billingAddress->getFirstname(), 0, 1);
                        $arrEnduser['lastName'] = substr($billingAddress->getLastname(), 0, 30);
                    }

                    if (!empty($billingAddress)) {
                        $streetName = "";
                        $streetNumber = "";
                        $matches = array();

                        $addressFull = $billingAddress->getStreetFull();
                        $addressFull = str_replace("\n", ' ', $addressFull);
                        $addressFull = str_replace("\r", ' ', $addressFull);



                        list($address, $housenumber) = $helper->splitAddress($addressFull);

                        $arrEnduser['invoiceAddress']['streetName'] = $address;
                        $arrEnduser['invoiceAddress']['streetNumber'] = $housenumber;
                        $arrEnduser['invoiceAddress']['zipCode'] = $billingAddress->getPostcode();
                        $arrEnduser['invoiceAddress']['city'] = $billingAddress->getCity();
                        $arrEnduser['invoiceAddress']['countryCode'] = $billingAddress->getCountry();

                        $arrEnduser['invoiceAddress']['initials'] = substr($billingAddress->getFirstname(), 0, 1);
                        $arrEnduser['invoiceAddress']['lastName'] = substr($billingAddress->getLastname(), 0, 30);
                    }
                    $api->setEnduser($arrEnduser);
                }

                $api->setServiceId($serviceId);
                $api->setApiToken($apiToken);

                $api->setAmount(round($amount * 100));
                $api->setCurrency($order->getOrderCurrencyCode());

                $api->setPaymentOptionId($optionId);
                $api->setFinishUrl(Mage::getUrl('pay_payment/order/return'));

                $api->setExchangeUrl(Mage::getUrl('pay_payment/order/exchange'));
                $api->setOrderId($order->getIncrementId());
                if (!empty($optionSubId)) {
                    $api->setPaymentOptionSubId($optionSubId);
                }
                try {

                    $resultData = $api->doRequest();
                } catch (Exception $e) {
                    // Reset previous errors
                    Mage::getSingleton('checkout/session')->getMessages(true);

                    // cart restoren
                    $restoreCart = Mage::getStoreConfig('pay_payment/general/restore_cart', Mage::app()->getStore());
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
                            } catch (Exception $e) {
                                Mage::getSingleton('checkout/session')->addException($e, Mage::helper('checkout')->__('Cannot add the item to shopping cart.')
                                );
                            }
                        }
                        $cart->save();
                    }

                    // Add error to cart
                    Mage::getSingleton('checkout/session')->addError(Mage::helper('pay_payment')->__('Er is een storing bij de door u gekozen betaalmethode of bank. Kiest u alstublieft een andere betaalmethode of probeer het later nogmaals'));
                    Mage::getSingleton('checkout/session')->addError($e->getMessage());
                    // Mage::getSingleton('checkout/session')->addError(print_r($api->getPostData(),1));
                    // Redirect via header
                    Mage::app()->getResponse()->setRedirect(Mage::getUrl('checkout/cart'));
                    return;
                    // Fallback redirect via javascript
                    //echo 'window.location = \'' . Mage::getUrl('checkout/cart') . '\'';
                }

                $transaction = Mage::getModel('pay_payment/transaction');

                $transactionId = $resultData['transaction']['transactionId'];

                $transaction->setData(
                        array(
                            'transaction_id' => $transactionId,
                            'service_id' => $serviceId,
                            'option_id' => $optionId,
                            'option_sub_id' => $optionSubId,
                            'amount' => round($amount * 100),
                            'order_id' => $order->getId(),
                            'status' => Pay_Payment_Model_Transaction::STATE_PENDING,
                            'created' => time(),
                            'last_update' => time(),
                ));

                $transaction->save();

                //redirecten
                $url = $resultData['transaction']['paymentURL'];

                $statusPending = Mage::getStoreConfig('payment/' . $payment->getMethod() . '/order_status', Mage::app()->getStore());

                $order->setState(
                        Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, $statusPending, 'Transactie gestart, transactieId: ' . $transactionId." \nBetaalUrl: ".$url
                );
                

                $order->save();

                $sendMail = Mage::getStoreConfig('payment/' . $payment->getMethod() . '/send_mail', Mage::app()->getStore());
                if ($sendMail == 'start') {
                    $order->sendNewOrderEmail();
                }

                Mage::app()->getResponse()->setRedirect($url);
            }
        }
    }

}
