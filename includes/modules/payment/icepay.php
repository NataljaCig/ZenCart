<?php

/**
 * @package       ICEPAY Payment Module for VirtueMart 3
 * @author        Ricardo Jacobs <ricardo.jacobs@icepay.com>
 *                Old authors: O. Abbenhuis
 * @copyright     (c) 2015 ICEPAY. All rights reserved.
 * @version       1.0.1, July 2015
 * @license       BSD-2-Clause, see LICENSE.md
 */

define('TABLE_ICEPAY_SESSION', DB_PREFIX . 'icepay_session');

class icepay
{
    var $code, $title, $description, $enabled, $payment, $disclaimer;
    var $icepay_url;
    var $fingerPrint = "";
    var $orderID, $status;
    var $version = "2.2.7";

    function icepay()
    {
        global $order;
        global $db;

        $this->code = 'icepay';
        //$this->version = '';

        $statusSet = $this->checkStatusCodes();


        $this->title = '<b>ICEPAY</b>';
        if (!$statusSet) $this->title .= " <span class=\"alert\">(not properly configured - status codes not set)</span>";

        $this->description = '<a border="0" href="http://www.icepay.com" target="_blank"><img src="../images/icepay/icepay-logo.png" border="0""></a><BR><BR>'
            . '<BR>'
            . '<b>URL completed:</b><BR><nobr>'
            . HTTP_SERVER . DIR_WS_CATALOG . "extras/icepay/result.php</nobr><BR><BR>"
            . '<b>URL error:</b><BR><nobr>'
            . HTTP_SERVER . DIR_WS_CATALOG . "extras/icepay/result.php</nobr><BR><BR>"
            . '<b>URL notify:</b><BR><nobr>'
            . HTTP_SERVER . DIR_WS_CATALOG . "extras/icepay/notify.php</nobr><BR><BR>"
            . '<b>Module Version:</b><BR>'
            . $this->version . "<BR><BR>"
            . '<b>Module ID:</b><BR>'
            . $this->generateFingerPrint() . "<BR><BR>";


        $this->sort_order = MODULE_PAYMENT_ICEPAY_SORT_ORDER;
        $this->enabled = ((ICE_ENABLED == 'True') ? true : false);
        $this->merchantID = ICE_MERCHANTID;
        $this->secretCode = ICE_ENCCODE;


        if ((int)ICE_PAYMENT_START > 0) {
            $this->order_status = ICE_PAYMENT_START;
            $payment = 'icepay';
        } else {
            if ($payment == 'icepay') {
                $payment = '';
            }
        }

        if (is_object($order)) $this->update_status();

        $this->email_footer = "Used with purchases via ICEPAY";
        $this->icepay_url = 'https://pay.icepay.eu/basic/';

    }

    function checkStatusCodes()
    {
        if (
            (int)ICE_PAYMENT_START > 0
            && (int)ICE_PAYMENT_OPEN > 0
            && (int)ICE_PAYMENT_OK > 0
            && (int)ICE_PAYMENT_ERR > 0
            && (int)ICE_PAYMENT_REFUND > 0
            && (int)ICE_PAYMENT_CBACK > 0
        ) {
            return true;
        };

        return false;
    }

    function updateStatus($orderID, $status, $notify = 0, $statusInfo = '', $removeStock = false)
    {
        global $db;
        // Update order status
        $db->Execute(sprintf("UPDATE %s SET orders_status = '%s' WHERE orders_id  = '%s' ",
            TABLE_ORDERS,
            $status,
            $orderID
        ));
        // Insert order history
        $db->Execute(sprintf("INSERT INTO %s SET orders_id = '%s', orders_status_id = '%s', customer_notified = '%s', date_added = now(), comments = '%s' ",
            TABLE_ORDERS_STATUS_HISTORY,
            $orderID,
            $status,
            $notify,
            $statusInfo
        ));

        if ($removeStock == true) {
            $this->removeStock($orderID);
        };
    }

    function update_status()
    {
        global $db;
        global $order;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_ICEPAY_ZONE > 0)) {
            $check_flag = false;
            $check = $db->Execute(sprintf("SELECT zone_id FROM %s WHERE geo_zone_id = '%s' AND zone_country_id = '%s' ORDER BY zone_id ",
                TABLE_ZONES_TO_GEO_ZONES,
                MODULE_PAYMENT_ICEPAY_ZONE,
                $order->billing['country']['id']
            ));
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    function javascript_validation()
    {
        return false;
    }

    function selection()
    {
        $title = "";
        if (ICE_CHECKOUT_TITLE == "Text") {
            $title = ICEPAY_CHECKOUT_TEXT;
        } else {
            $title = ICEPAY_CHECKOUT_IMAGES;
        }
        return array('id' => $this->code,
            'module' => $title);
    }

    function pre_confirmation_check()
    {
        return false;
    }

    function confirmation()
    {
        return array('title' => "Used for purchase of ICEPAY");
    }

    function process_button()
    {

        if ($_GET['ErrCode']) {
            echo("<div class='messageStackWarning'><strong>ERROR!</strong><BR>" . $_GET['ErrCode'] . "<BR></div>");
        };
        if ($_GET['StatusCode']) {
            echo("<div class='messageStackWarning'><strong>ERROR!</strong><BR>" . $_GET['StatusCode'] . "<BR></div>");
        };

        return '';
    }


    function before_process()
    {

    }

    function getCurrency()
    {
        if (ICE_CURRENCY == 'DETECT') {
            $my_currency = $_SESSION['currency'];
        } else {
            $my_currency = ICE_CURRENCY;
        }
        $ice_currencies = array('CAD', 'EUR', 'GBP', 'JPY', 'USD', 'AUD', 'CHF', 'CZK', 'DKK', 'HKD', 'HUF', 'NOK', 'NZD', 'PLN', 'SEK', 'SGD', 'THB');
        if (!in_array($my_currency, $ice_currencies)) {
            $my_currency = 'EUR';
        }
        return $my_currency;
    }

    function getLanguage()
    {
        if (ICE_LANGUAGE == 'DETECT') {
            $my_language = $_SESSION['languages_code'];
        } else {
            $my_language = ICE_LANGUAGE;
        }
        $ice_languages = array('NL', 'EN', 'DE', 'ES', 'FR', 'IT');
        if (!in_array($my_language, $ice_languages)) {
            $my_language = 'EN';
        }
        return $my_language;
    }

    function after_order_create($orderID)
    {
        global $HTTP_POST_VARS, $order, $currencies, $db, $order_totals;

        // assigning ICEPAY variables
        $this->merchantID = ICE_MERCHANTID;
        $this->secretCode = ICE_ENCCODE;
        $this->amount = number_format($order->info['total'] * 100 * $order->info['currency_value'], 0, '', '');
        $this->reference = $orderID;
        $this->orderID = '';
        $this->currency = $this->getCurrency();
        $this->language = $this->getLanguage();

        if (ICE_COUNTRY == 'DETECT') {
            // Get currenty country out of session
            $countrycheck_query = $db->Execute(sprintf("SELECT countries_iso_code_2 FROM %s WHERE countries_id = '%s' LIMIT 1 ",
                TABLE_COUNTRIES,
                $_SESSION['customer_country_id']
            ));
            if ($countrycheck_query->fields['countries_iso_code_2']) {
                $this->country = strtoupper($countrycheck_query->fields['countries_iso_code_2']);
            } else {
                $this->country = ICE_COUNTRY;
            }
        } else {
            $this->country = ICE_COUNTRY;
        };

        // save the session permanently for e-mail
        $sql = sprintf("INSERT INTO %s (ice_user, ice_ref, ice_status, ice_session, ice_amount, saved_session, saved_globals, saved_totals, expiry) " .
            "VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
            TABLE_ICEPAY_SESSION,
            $_SESSION['customer_id'],
            $orderID,
            ICE_PAYMENT_START,
            session_id(),
            zen_db_input($this->amount),
            base64_encode(serialize($_SESSION)),
            base64_encode(serialize($GLOBALS)),
            base64_encode(serialize($order_totals)),
            (time() + (60 * 60 * 24 * 61))
        );
        $db->Execute($sql);

        $order->create_add_products($orderID);

        $this->removeCustomerNotified($orderID);
        $this->restock($orderID);

        $url = '?ic_merchantid=' . $this->merchantID;
        $url .= '&ic_reference=' . $orderID;
        $url .= '&ic_amount=' . $this->amount;
        $url .= '&ic_country=' . $this->country;
        $url .= '&ic_currency=' . $this->currency;
        $url .= '&ic_language=' . $this->language;
        $url .= '&ic_fp=' . $this->generateFingerPrint();
        $url .= '&chk=' . $this->generateChecksumForBasicMode();

        $_SESSION['cart']->reset(true);
        unset($_SESSION['sendto']);
        unset($_SESSION['billto']);
        unset($_SESSION['shipping']);
        unset($_SESSION['payment']);
        unset($_SESSION['comments']);
        unset($_SESSION['cot_gv']);

        zen_redirect($this->icepay_url . $url);
        die($this->icepay_url . $url);
    }

    function removeCustomerNotified($orderID)
    {
        global $db, $order;
        $db->Execute(sprintf("UPDATE %s SET customer_notified = 0 WHERE orders_id = '%s' and orders_status_id = '%s' LIMIT 1 ",
            TABLE_ORDERS_STATUS_HISTORY,
            $orderID,
            ICE_PAYMENT_START
        ));
    }

    function restock($orderID)
    {
        global $db, $order;
        $order = $db->Execute(sprintf("SELECT products_id, products_quantity FROM %s WHERE orders_id = '%s' ",
            TABLE_ORDERS_PRODUCTS,
            (int)$orderID
        ));
        while (!$order->EOF) {
            $db->Execute(sprintf("UPDATE %s SET products_quantity = products_quantity + %s, products_ordered = products_ordered - %s WHERE products_id = '%s' ",
                TABLE_PRODUCTS,
                $order->fields['products_quantity'],
                $order->fields['products_quantity'],
                (int)$order->fields['products_id']
            ));
            $order->MoveNext();
        }

    }

    function removeStock($orderID)
    {
        global $db;
        $order = $db->Execute(sprintf("SELECT products_id, products_quantity FROM %s WHERE orders_id = '%s' ",
            TABLE_ORDERS_PRODUCTS,
            (int)$orderID
        ));
        while (!$order->EOF) {
            $db->Execute(sprintf("UPDATE %s SET products_quantity = products_quantity - %s, products_ordered = products_ordered + %s WHERE products_id = '%s' ",
                TABLE_PRODUCTS,
                $order->fields['products_quantity'],
                $order->fields['products_quantity'],
                (int)$order->fields['products_id']
            ));
            $order->MoveNext();
        }

    }

    function load_session($orderID)
    {
        global $db, $GLOBALS, $_SESSION;
        $ICEPAY_Session = $db->Execute(sprintf("SELECT * FROM %s WHERE ice_ref = '%s' ",
            TABLE_ICEPAY_SESSION,
            (int)$orderID
        ));
        $unserializeorder = base64_decode($ICEPAY_Session->fields['saved_session']);
        $unserializeGlobals = base64_decode($ICEPAY_Session->fields['saved_globals']);
        $unserializeTotals = base64_decode($ICEPAY_Session->fields['saved_totals']);
        $_SESSION = unserialize($unserializeorder);
        $GLOBALS = unserialize($unserializeGlobals);
        $order_total = unserialize($unserializeTotals);
        return $order_total;
    }

    function checkStatus()
    {
        return false;
    }

    function after_process()
    {
        return false;
    }

    function get_error()
    {
        return false;
    }

    function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute(sprintf("SELECT configuration_value FROM %s WHERE configuration_key = 'ICE_ENABLED' ",
                TABLE_CONFIGURATION
            ));
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    function logRequest($info_string)
    {
        $this->doLogging($info_string);
    }

    function GetCoreClasses()
    {
        return array
        (
            './icepay.php',
            '../../../extras/icepay/notify.php',
            '../../../extras/icepay/result.php'
        );
    }

    function generateFingerPrint()
    {
        if ($this->fingerPrint != "") return $this->fingerPrint;

        $content = "";

        foreach ($this->GetCoreClasses() as $item)
            if (false === ($content .= file_get_contents(dirname(__FILE__) . '/' . $item)))
                die("Could not generate fingerprint");

        $this->fingerPrint = sha1($content);

        return $this->fingerPrint;
    }

    function printFP()
    {
        echo($this->generateFingerPrint());
    }

    function getFP()
    {
        return $this->generateFingerPrint();
    }

    function allowedCountries()
    {
        $countries = array('DETECT', 'NL', 'AT', 'AU', 'BE', 'CA', 'CH', 'CZ', 'DE', 'ES', 'IT', 'LU', 'PL', 'PT', 'SK', 'GB', 'US', 'FR');
        $countries_string = implode(",", $countries);
        return $countries_string;
    }

    function allowedLanguages()
    {
        $languages = array('DETECT', 'EN', 'DE', 'NL');
        $languages_string = implode(",", $languages);
        return $languages_string;
    }

    function allowedCurrencies()
    {
        $currencies = array('DETECT', 'EUR', 'GBP', 'USD', 'AUD', 'CAD', 'CHF', 'CZK', 'PLN', 'SKK', 'MXN', 'CLP', 'LVL');
        $currencies_string = implode(",", $currencies);
        return $currencies_string;
    }

    function doLogging($line)
    {
        if (ICE_LOGGING == 'False') return false;

        $filename = sprintf("%s/#%s.log", 'extras/icepay/icepay_log', date("Ymd", time()));
        $fp = @fopen($filename, "a");
        $line = sprintf("%s - %s\r\n", date("H:i", time()), $line);
        @fwrite($fp, $line);
        @fclose($fp);

        return true;
    }

    function getOrderData($orderID)
    {
        global $db;

        $o_query = $db->Execute(sprintf("SELECT orders_status FROM %s WHERE orders_id = '%s' LIMIT 1 ",
            TABLE_ORDERS,
            (int)$orderID
        ));
        $this->orderID = $orderID;
        $this->status = $o_query->fields['orders_status'];
    }

    function GetData()
    {
        $o = NULL;

        $o->status = $_GET['Status'];
        $o->statusCode = $_GET['StatusCode'];
        $o->merchant = $_GET['Merchant'];
        $o->orderID = $_GET['OrderID'];
        $o->paymentID = $_GET['PaymentID'];
        $o->reference = $_GET['Reference'];
        $o->transactionID = $_GET['TransactionID'];
        $o->checksum = $_GET['Checksum'];

        return $o;
    }

    function OnPostback()
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') return false;

        $this->postback = NULL;
        $this->postback->status = $_POST['Status'];
        $this->postback->statusCode = $_POST['StatusCode'];
        $this->postback->merchant = $_POST['Merchant'];
        $this->postback->orderID = $_POST['OrderID'];
        $this->postback->paymentID = $_POST['PaymentID'];
        $this->postback->reference = $_POST['Reference'];
        $this->postback->transactionID = $_POST['TransactionID'];
        $this->postback->consumerName = $_POST['ConsumerName'];
        $this->postback->consumerAccountNumber = $_POST['ConsumerAccountNumber'];
        $this->postback->consumerAddress = $_POST['ConsumerAddress'];
        $this->postback->consumerHouseNumber = $_POST['ConsumerHouseNumber'];
        $this->postback->consumerCity = $_POST['ConsumerCity'];
        $this->postback->consumerCountry = $_POST['ConsumerCountry'];
        $this->postback->consumerEmail = $_POST['ConsumerEmail'];
        $this->postback->consumerPhoneNumber = $_POST['ConsumerPhoneNumber'];
        $this->postback->consumerIPAddress = $_POST['ConsumerIPAddress'];
        $this->postback->amount = $_POST['Amount'];
        $this->postback->currency = $_POST['Currency'];
        $this->postback->duration = $_POST['Duration'];
        $this->postback->paymentMethod = $_POST['PaymentMethod'];
        $this->postback->checksum = $_POST['Checksum'];

        $this->doLogging(sprintf("Postback: %s", serialize($_POST)));

        if (!is_numeric($this->postback->merchant)) {
            $this->clearPostback();
            return false;
        }
        if (!is_numeric($this->postback->amount)) {
            $this->clearPostback();
            return false;
        }
        if (!is_numeric($this->postback->duration)) {
            $this->clearPostback();
            return false;
        }

        if ($this->merchantID != $this->postback->merchant) {
            $this->clearPostback();
            $this->doLogging($this->postback->paymentID . ": Invalid merchant ID");
            return false;
        }

        if ($this->generateChecksumForPostback() != $this->postback->checksum) {
            $this->clearPostback();
            $this->doLogging($this->postback->paymentID . ":Checksum does not match");
            return false;
        }

        $this->getOrderData($this->postback->reference);

        return true;
    }

    //
    // Edited from API
    function generateChecksumForPostback()
    {
        return sha1
        (
            ICE_ENCCODE . "|" .
            ICE_MERCHANTID . "|" .
            $this->postback->status . "|" .
            $this->postback->statusCode . "|" .
            $this->postback->orderID . "|" .
            $this->postback->paymentID . "|" .
            $this->postback->reference . "|" .
            $this->postback->transactionID . "|" .
            $this->postback->amount . "|" .
            $this->postback->currency . "|" .
            $this->postback->duration . "|" .
            $this->postback->consumerIPAddress
        );
    }

    function clearPostback()
    {
        $this->postback = NULL;

        $this->postback->status = "";
        $this->postback->statusCode = "";
        $this->postback->merchant = "";
        $this->postback->orderID = "";
        $this->postback->paymentID = "";
        $this->postback->reference = "";
        $this->postback->transactionID = "";
        $this->postback->consumerName = "";
        $this->postback->consumerAccountNumber = "";
        $this->postback->consumerAddress = "";
        $this->postback->consumerHouseNumber = "";
        $this->postback->consumerCity = "";
        $this->postback->consumerCountry = "";
        $this->postback->consumerEmail = "";
        $this->postback->consumerPhoneNumber = "";
        $this->postback->consumerIPAddress = "";
        $this->postback->amount = "";
        $this->postback->currency = "";
        $this->postback->duration = "";
        $this->postback->paymentMethod = "";
        $this->postback->checksum = "";

        return;
    }

    function generateChecksumForBasicMode()
    {
        return sha1
        (
            $this->merchantID . "|" .
            $this->secretCode . "|" .
            $this->amount . "|" .
            $this->orderID . "|" .
            $this->reference . "|" .
            $this->currency . "|" .
            $this->country
        );
    }


    function generateChecksumForPage()
    {
        $data = $this->GetData();

        return sha1
        (
            $this->secretCode . "|" .
            $this->merchantID . "|" .
            $data->status . "|" .
            $data->statusCode . "|" .
            $data->orderID . "|" .
            $data->paymentID . "|" .
            $data->reference . "|" .
            $data->transactionID
        );
    }

    function _installstatus($status_name)
    {

        global $db;
        $new_order_id = $db->Execute("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS . " ORDER BY orders_status_id DESC LIMIT 1");
        $new_order_id = $new_order_id->fields['orders_status_id'] + 1;


        $languages = $db->Execute("select languages_id
                                 from " . TABLE_LANGUAGES . " 
                                 order by sort_order");

        while (!$languages->EOF) {
            $check = $db->Execute("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_name = '" . $status_name . "' AND language_id = '" . $languages->fields['languages_id'] . "' LIMIT 1");
            if (!$check->fields['orders_status_id']) {
                $db->Execute("INSERT INTO " . TABLE_ORDERS_STATUS . " (orders_status_name, orders_status_id, language_id) value ('" . $status_name . "','" . $new_order_id . "'," . $languages->fields['languages_id'] . ")");
            };
            $languages->MoveNext();
        };
    }

    function install()
    {
        global $db;
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Activate ICEPAY', 'ICE_ENABLED', 'False', 'Activate ICEPAY payment method', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now());");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Display ICEPAY in checkout as: ', 'ICE_CHECKOUT_TITLE', 'Text', '\(Text\)', '6','0','zen_cfg_select_option(array(\'Text\', \'Payment method images\'), ', now());");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant ID', 'ICE_MERCHANTID', 'xxxx', '', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Secret Code', 'ICE_ENCCODE', 'xxxx', '', '6', '0', now())");

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Status on preparing payment', 'ICE_PAYMENT_START', '0', 'Set the status of orders made where payment has been received with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Status on payment SUCCESS', 'ICE_PAYMENT_OK', '0', 'Set the status of orders made where payment has been received with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Status on payment ERROR', 'ICE_PAYMENT_ERR', '0', 'Set the status of orders which generated errors to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Status on payment OPEN', 'ICE_PAYMENT_OPEN', '0', 'Set the status of orders made and awaiting payment with this payment module to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Status on payment REFUND', 'ICE_PAYMENT_REFUND', '0', 'Set the status of orders which have been refunded.', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Status on payment CHARGEBACK', 'ICE_PAYMENT_CBACK', '0', 'Set the status of orders which have been charged back.', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");


        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Currency', 'ICE_CURRENCY', 'DETECT', 'Type of currency', '6', '2', 'zen_cfg_select_option(array(" . $this->allowedCurrencies() . "), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Country', 'ICE_COUNTRY', 'DETECT', 'Country acceptation. \(00 is Global\)', '6', '2', 'zen_cfg_select_option(array(\'00\'," . $this->allowedCountries() . "), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Language', 'ICE_LANGUAGE', 'DETECT', 'Language setting', '6', '2', 'zen_cfg_select_option(array(" . $this->allowedLanguages() . "), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Logging', 'ICE_LOGGING', 'False', 'Make sure the icepay_log directory is writable', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now());");

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_ICEPAY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_ICEPAY_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");

        // Install status
        $this->_installstatus("Preparing [ICEPAY]");
        $this->_installstatus("Success [ICEPAY]");
        $this->_installstatus("Error [ICEPAY]");
        $this->_installstatus("Open [ICEPAY]");
        $this->_installstatus("Refund [ICEPAY]");
        $this->_installstatus("Chargeback [ICEPAY]");

        // Create session table
        $sql = "DROP TABLE IF EXISTS `" . TABLE_ICEPAY_SESSION . "`;";
        $db->Execute($sql);

        $sql = "CREATE TABLE `" . TABLE_ICEPAY_SESSION . "` ( " .
            "`ice_id` int(11) NOT NULL auto_increment, " .
            "`ice_user` int(11) NOT NULL, " .
            "`ice_ref` text NOT NULL, " .
            "`ice_status` text NOT NULL, " .
            "`ice_session` text NOT NULL, " .
            "`ice_amount` text NOT NULL, " .
            "`order_id` int(11) NOT NULL, " .
            "`saved_session` text NOT NULL, " .
            "`saved_globals` text NOT NULL, " .
            "`saved_totals` text NOT NULL, " .
            "`expiry` int(17) NOT NULL default '0', " .
            "PRIMARY KEY  (`ice_id`) " .
            ")";
        $db->Execute($sql);

        // alter table for return url
        $sql = "ALTER TABLE " . TABLE_WHOS_ONLINE . " " .
            "DROP INDEX `idx_last_page_url_zen`," .
            "ADD INDEX `idx_last_page_url_zen` (`last_page_url`(254))";
        $db->Execute($sql);
        $sql = "ALTER TABLE " . TABLE_WHOS_ONLINE . " " .
            "CHANGE `last_page_url` `last_page_url` TEXT NOT NULL";
        $db->Execute($sql);

    }

    function remove()
    {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");

        // Remove session table
        $db->Execute("DROP TABLE IF EXISTS `" . TABLE_ICEPAY_SESSION . "`; ");
    }

    function keys()
    {
        return array(
            'ICE_ENABLED',
            'ICE_CHECKOUT_TITLE',
            'ICE_MERCHANTID',
            'ICE_ENCCODE',

            'ICE_PAYMENT_START',
            'ICE_PAYMENT_OPEN',
            'ICE_PAYMENT_OK',
            'ICE_PAYMENT_ERR',
            'ICE_PAYMENT_REFUND',
            'ICE_PAYMENT_CBACK',

            'ICE_CURRENCY',
            'ICE_COUNTRY',
            'ICE_LANGUAGE',
            'ICE_LOGGING',

            'MODULE_PAYMENT_ICEPAY_SORT_ORDER',
            'MODULE_PAYMENT_ICEPAY_ZONE'
        );
    }
}

?>
