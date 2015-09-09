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

require('includes/application_top.php');
require($template->get_template_dir('main_template_vars.php', DIR_WS_TEMPLATE, $current_page_base, 'common') . '/main_template_vars.php');
require(DIR_WS_CLASSES . 'payment.php');

include('includes/modules/payment/icepay.php');

$ICE_module = new icepay();

if ($_GET['Reference']) {
    if ($_GET['Status'] == 'OPEN' || $_GET['Status'] == 'OK') {
        zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
    } else {
        $ICE_module->load_session($_GET['Reference']);
        $ICE_module->getOrderData($_GET['Reference']);
        if ($ICE_module->status == ICE_PAYMENT_START) $ICE_module->updateStatus($_GET['Reference'], ICE_PAYMENT_ERR, 0, $_GET['StatusCode']);
        zen_redirect(zen_href_link(FILENAME_CHECKOUT_CONFIRMATION, 'ErrCode=' . $_GET['ErrCode'] . '&StatusCode=' . $_GET['StatusCode'], 'SSL'));
    };
} else {
    die("No Reference found");
}

?>
