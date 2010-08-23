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
);
?>
