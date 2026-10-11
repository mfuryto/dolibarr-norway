<?php
// SPDX-License-Identifier: GPL-3.0-or-later
if (PHP_SAPI !== 'cli' || getenv('NORWAY_SYNTHETIC_TEST') !== '1') { exit(1); }
require '/var/www/html/custom/norway/class/actions_norway.class.php';
$hook = new ActionsNorway(null);
$group = array('groupName' => 'BankAccount', 'stats' => array('bank_account',
    'chequereceipt', 'widthdraw_direct_debit', 'widthdraw_credit_transfer'));
$object = new stdClass(); $action = '';
$hook->addOpenElementsDashboardGroup(array('dashboardgroup' => array('bank_account' => $group)), $object, $action, null);
if ($hook->results !== array('bank_account' => array('groupName' => 'BankAccount',
    'stats' => array('bank_account', 'widthdraw_direct_debit', 'widthdraw_credit_transfer')))) {
    throw new RuntimeException('Cheque group not removed or normal bank functions altered');
}
$hook->addOpenElementsDashboardGroup(array('dashboardgroup' => array()), $object, $action, null);
if ($hook->results !== array()) { throw new RuntimeException('Missing bank group caused unrelated dashboard changes'); }
print "PASS cheque dashboard row removed while bank reconciliation and transfers remain\n";
