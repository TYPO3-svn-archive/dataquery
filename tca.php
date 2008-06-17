<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_dataquery_queries'] = array (
	'ctrl' => $TCA['tx_dataquery_queries']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'hidden,title,description,sql_query,t3_mechanisms'
	),
	'feInterface' => $TCA['tx_dataquery_queries']['feInterface'],
	'columns' => array (
		't3ver_label' => array (		
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.versionLabel',
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'max'  => '30',
			)
		),
		'hidden' => array (		
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'title' => Array (		
			'exclude' => 0,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.title',		
			'config' => Array (
				'type' => 'input',	
				'size' => '30',	
				'eval' => 'required,trim',
			)
		),
		'description' => Array (		
			'exclude' => 0,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.description',		
			'config' => Array (
				'type' => 'text',
				'cols' => '30',	
				'rows' => '4',
			)
		),
		'sql_query' => Array (		
			'exclude' => 0,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.sql_query',		
			'config' => Array (
				'type' => 'text',
				'cols' => '30',	
				'rows' => '8',	
				'wizards' => Array(
					'_PADDING' => 2,
					'example' => Array(
						'title' => 'Example Wizard:',
						'type' => 'script',
						'notNewRecords' => 1,
						'icon' => t3lib_extMgm::extRelPath('dataquery').'tx_dataquery_queries_sql_query/wizard_icon.gif',
						'script' => t3lib_extMgm::extRelPath('dataquery').'tx_dataquery_queries_sql_query/index.php',
					),
				),
			)
		),
		't3_mechanisms' => Array (		
			'exclude' => 0,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.t3_mechanisms',		
			'config' => Array (
				'type' => 'check',
				'cols' => 1,
				'default' => 3,
				'items' => Array (
					Array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.t3_mechanisms.I.0', ''),
					Array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.t3_mechanisms.I.1', ''),
					Array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.t3_mechanisms.I.2', ''),
					Array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.t3_mechanisms.I.3', ''),
				),
			)
		),
	),
	'types' => array (
		'0' => array('showitem' => 'hidden;;1;;1-1-1, title;;;;2-2-2, description;;;;3-3-3, sql_query, t3_mechanisms')
	),
	'palettes' => array (
		'1' => array('showitem' => '')
	)
);
?>