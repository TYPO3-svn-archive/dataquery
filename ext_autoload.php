<?php
/* 
 * Register necessary class names with autoloader
 *
 * $Id$
 */
$extensionPath = t3lib_extMgm::extPath('dataquery');
return array(
	'tx_dataquery_wizards_check' => $extensionPath . 'wizards/class.tx_dataquery_wizards_check.php',
	'tx_dataquery_ajax' => $extensionPath . 'wizards/class.tx_dataquery_ajax.php',
	'tx_dataquery_queryobject' => $extensionPath . 'class.tx_dataquery_queryobject.php',
	'tx_dataquery_sqlparser' => $extensionPath . 'class.tx_dataquery_sqlparser.php',
	'tx_dataquery_parser' => $extensionPath . 'class.tx_dataquery_parser.php',
);
?>
