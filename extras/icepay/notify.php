<?php

/**
 * @package       ICEPAY Payment Module for VirtueMart 3
 * @author        Ricardo Jacobs <ricardo.jacobs@icepay.com>
 *                Old authors: O. Abbenhuis
 * @copyright     (c) 2015 ICEPAY. All rights reserved.
 * @version       1.0.1, July 2015
 * @license       BSD-2-Clause, see LICENSE.md
 */

chdir('../../');

// LOAD ZENCART DATA
require('./includes/application_top.php');
require($template->get_template_dir('main_template_vars.php', DIR_WS_TEMPLATE, $current_page_base, 'common') . '/main_template_vars.php');
require(DIR_WS_CLASSES . 'payment.php');

// LOAD PAYMENT MODULE
include(DIR_WS_MODULES . 'payment/icepay.php');
$ICE_module = new icepay();

// LOAD LANGUAGE FILE FOR PROCESSING
include(DIR_WS_LANGUAGES . $_SESSION['language'] . "/checkout_process.php");

$notifyCustomer = false;
// STATUS HANDLING
if ($ICE_module->OnPostback()) {
    switch ($_POST['Status']) {
        case 'OPEN':
            if ($ICE_module->status == ICE_PAYMENT_START) {
                $ICE_module->updateStatus($ICE_module->orderID, ICE_PAYMENT_OPEN, 1, $ICE_module->postback->statusCode);
                $notifyCustomer = true;
            };
            break;
        case 'OK':
            if ($ICE_module->status == ICE_PAYMENT_START || $ICE_module->status == ICE_PAYMENT_OPEN) {
                $ICE_module->updateStatus($ICE_module->orderID, ICE_PAYMENT_OK, 1, $ICE_module->postback->statusCode, true);
                $notifyCustomer = true;
            };
            break;
        case 'ERR':
            if ($ICE_module->status == ICE_PAYMENT_START || $ICE_module->status == ICE_PAYMENT_OPEN) {
                $ICE_module->updateStatus($ICE_module->orderID, ICE_PAYMENT_ERR, 0, $ICE_module->postback->statusCode);
            };
            break;
        case 'CBACK':
            if ($ICE_module->status == ICE_PAYMENT_OK) {
                $ICE_module->updateStatus($ICE_module->orderID, ICE_PAYMENT_CBACK, 0, $ICE_module->postback->statusCode);
            };
            break;
        case 'REFUND':
            if ($ICE_module->status == ICE_PAYMENT_OK) {
                $ICE_module->updateStatus($ICE_module->orderID, ICE_PAYMENT_REFUND, 0, $ICE_module->postback->statusCode);
            };
            break;
    };
};


if ($notifyCustomer) {
    // Load order data
    $order_totals = $ICE_module->load_session($ICE_module->orderID);

    include_once(DIR_WS_CLASSES . 'order.php');

    // Override default setting
    $_SESSION['payment'] = 'icepay';
    $GLOBALS['icepay']->title = $ICE_module->postback->paymentMethod;

    // Retrieve order
    $order = new order($ICE_module->orderID);

    // E-mail setup
    for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
        $order->total_weight += ($order->products[$i]['qty'] * $order->products[$i]['weight']);
        $order->total_tax += zen_calculate_tax($total_products_price, $products_tax) * $order->products[$i]['qty'];
        $order->total_cost += $total_products_price;

        $order->products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ($order->products[$i]['model'] != '' ? ' (' . $order->products[$i]['model'] . ') ' : '') . ' = ' .
            $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) .
            ($order->products[$i]['onetime_charges'] != 0 ? "\n" . TEXT_ONETIME_CHARGES_EMAIL . $currencies->display_price($order->products[$i]['onetime_charges'], $order->products[$i]['tax'], 1) : '') .
            $order->products_ordered_attributes . "\n";
        $order->products_ordered_html .=
            '<tr>' . "\n" .
            '<td class="product-details" align="right" valign="top" width="30">' . $order->products[$i]['qty'] . '&nbsp;x</td>' . "\n" .
            '<td class="product-details" valign="top">' . nl2br($order->products[$i]['name']) . ($order->products[$i]['model'] != '' ? ' (' . nl2br($order->products[$i]['model']) . ') ' : '') . "\n" .
            '<nobr><small><em> ' . nl2br($order->products_ordered_attributes) . '</em></small></nobr></td>' . "\n" .
            '<td class="product-details-num" valign="top" align="right">' .
            $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) .
            ($order->products[$i]['onetime_charges'] != 0 ?
                '</td></tr>' . "\n" . '<tr><td class="product-details">' . nl2br(TEXT_ONETIME_CHARGES_EMAIL) . '</td>' . "\n" .
                '<td>' . $currencies->display_price($order->products[$i]['onetime_charges'], $order->products[$i]['tax'], 1) : '') .
            '</td></tr>' . "\n";
    };

    // Send e-mail
    $order->send_order_email($ICE_module->orderID, 2);
};

?>
