<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_dataquery_queries'] = array(
	'ctrl' => $TCA['tx_dataquery_queries']['ctrl'],
	'interface' => array(
		'showRecordFieldList' => 'hidden,title,description,sql_query,t3_mechanisms'
	),
	'feInterface' => $TCA['tx_dataquery_queries']['feInterface'],
	'columns' => array(
		't3ver_label' => array(		
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.versionLabel',
			'config' => array(
				'type' => 'input',
				'size' => '30',
				'max'  => '30',
			)
		),
		'hidden' => array(		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array(
				'type'    => 'check',
				'default' => '0'
			)
		),
		'title' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.title',		
			'config' => array(
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'required,trim',
			)
		),
		'description' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.description',		
			'config' => array(
				'type' => 'text',
				'cols' => '30',	
				'rows' => '4',
			)
		),
		'sql_query' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.sql_query',		
			'config' => array(
				'type' => 'text',
				'cols' => '30',	
				'rows' => '8',
				'wizards' => array(
					'_PADDING' => 2,
				)
			)
		),
		'cache_duration' => array(		
			'exclude' => 1,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.cache_duration',		
			'config' => array(
				'type' => 'input',	
				'size' => 20,
				'default' => 86400,
				'eval' => 'int',
			)
		),
		'ignore_enable_fields' => array(		
			'exclude' => 1,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.ignore_enable_fields',		
			'config' => array(
				'type' => 'check',
				'default' => 0,
				'items' => array(
					array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.ignore_enable_fields.I.0', ''),
				),
			)
		),
		'ignore_language_handling' => array(		
			'exclude' => 1,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.ignore_language_handling',		
			'config' => array(
				'type' => 'check',
				'default' => 0,
				'items' => array(
					array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.ignore_language_handling.I.0', ''),
				),
			)
		),
	),
	'types' => array(
		'0' => array('showitem' => 'hidden;;;;1-1-1, title;;1;;2-2-2, sql_query;;;;3-3-3,--div--;LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.tab.advanced, cache_duration;;;;1-1-1, ignore_enable_fields;;;;2-2-2, ignore_language_handling')
	),
	'palettes' => array(
		'1' => array('showitem' => 'description'),
	)
);

	// Add the check query wizard only if TYPO3 is version 4.3 or more
	// (because the wizard relies on ExtJS being loaded)
	// NOTE: the check doesn't use t3lib_div::compat_version() because this method
	// also takes $TYPO3_CONF_VARS['SYS']['compat_version'] into account, which has nothing to do here
if (t3lib_div::int_from_ver(TYPO3_version) >= t3lib_div::int_from_ver('4.3.0')) {
	$TCA['tx_dataquery_queries']['columns']['sql_query']['config']['wizards']['check'] = array(
		'type' => 'userFunc',
		'userFunc' => 'EXT:dataquery/wizards/class.tx_dataquery_wizards_check.php:tx_dataquery_wizards_Check->render'
	);
}
?>