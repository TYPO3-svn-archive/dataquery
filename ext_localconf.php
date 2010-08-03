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

	// Register the dataquery cache table to be deleted when all caches are cleared
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearAllCache_additionalTables']['tx_dataquery_cache'] = 'tx_dataquery_cache';

	// Register a hook to clear the cache for a given page
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearPageCacheEval']['tx_dataquery'] = 'EXT:dataquery/class.tx_dataquery_cache.php:&tx_dataquery_cache->clearCache';

	// Register a hook with datafilter to handle the extra field added by dataquery
$TYPO3_CONF_VARS['EXTCONF']['datafilter']['postprocessReturnValue']['tx_dataquery'] = 'EXT:dataquery/hooks/class.tx_dataquery_datafilterhook.php:&tx_dataquery_datafilterhook';

	// Register wizard validation method with generic BE ajax calls handler
$TYPO3_CONF_VARS['BE']['AJAX']['dataquery::validate'] = 'typo3conf/ext/dataquery/class.tx_dataquery_ajax.php:tx_dataquery_Ajax->validate';
?>