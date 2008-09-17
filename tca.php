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
					'example' => array(
						'title' => 'Example Wizard:',
						'type' => 'script',
						'notNewRecords' => 1,
						'icon' => t3lib_extMgm::extRelPath('dataquery').'tx_dataquery_queries_sql_query/wizard_icon.gif',
						'script' => t3lib_extMgm::extRelPath('dataquery').'tx_dataquery_queries_sql_query/index.php',
					),
				),
			)
		),
		'use_enable_fields' => array(		
			'exclude' => 1,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.use_enable_fields',		
			'config' => array(
				'type' => 'check',
				'default' => 1,
				'items' => array(
					array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.use_enable_fields.I.0', ''),
				),
			)
		),
		'language_handling' => array(		
			'exclude' => 0,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.language_handling',		
			'config' => array(
				'type' => 'radio',
				'items' => array(
					array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.language_handling.I.0', ''),
					array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.language_handling.I.1', 'simple'),
					array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.language_handling.I.2', 'overlay'),
				),
			)
		),
		'use_versioning' => array(		
			'exclude' => 1,		
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.use_versioning',		
			'config' => array(
				'type' => 'check',
				'default' => 1,
				'items' => array(
					array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.use_versioning.I.0', ''),
				),
			)
		),
	),
	'types' => array(
		'0' => array('showitem' => 'hidden;;;;1-1-1, title;;;;2-2-2, description;;;;3-3-3, sql_query, use_enable_fields;;;;4-4-4, language_handling, use_versioning')
	),
);
?>