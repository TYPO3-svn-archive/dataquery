<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

// Active save and new button

t3lib_extMgm::addUserTSConfig('
	options.saveDocNew.tx_dataquery_queries=1
');

// Register as Data Provider service
// Note that the subtype corresponds to the name of the database table

t3lib_extMgm::addService($_EXTKEY,  'dataprovider' /* sv type */,  'tx_dataquery_dataprovider' /* sv key */,
		array(

			'title' => 'Data Query',
			'description' => 'Data Provider for Data Query',

			'subtype' => 'tx_dataquery_queries',

			'available' => TRUE,
			'priority' => 50,
			'quality' => 50,

			'os' => '',
			'exec' => '',

			'classFile' => t3lib_extMgm::extPath($_EXTKEY, 'class.tx_dataquery_wrapper.php'),
			'className' => 'tx_dataquery_wrapper',
		)
	);
?>