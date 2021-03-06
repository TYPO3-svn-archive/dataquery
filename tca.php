<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$GLOBALS['TCA']['tx_dataquery_queries'] = array(
	'ctrl' => $GLOBALS['TCA']['tx_dataquery_queries']['ctrl'],
	'interface' => array(
		'showRecordFieldList' => 'hidden,title,description,sql_query,t3_mechanisms'
	),
	'feInterface' => $GLOBALS['TCA']['tx_dataquery_queries']['feInterface'],
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
					'check' => array(
						'type' => 'userFunc',
						'userFunc' => 'EXT:dataquery/wizards/class.tx_dataquery_wizards_check.php:tx_dataquery_wizards_Check->render'
					)
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
				'type' => 'radio',
				'default' => 0,
				'items' => array(
					array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.ignore_enable_fields.I.0', '0'), # don't ignore
					array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.ignore_enable_fields.I.1', '1'), # ignore everything
					array('LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.ignore_enable_fields.I.2', '2'), # ignore partially
				),
			)
		),
		'ignore_time_for_tables' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.ignore_time_for_tables',
			'config' => array(
				'type' => 'input',
				'size' => 255,
				'default' => '*',
			)
		),
		'ignore_disabled_for_tables' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.ignore_disabled_for_tables',
			'config' => array(
				'type' => 'input',
				'size' => 255,
				'default' => '*',
			)
		),
		'ignore_fegroup_for_tables' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.ignore_fegroup_for_tables',
			'config' => array(
				'type' => 'input',
				'size' => 255,
				'default' => '*',
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
		'skip_overlays_for_tables' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.skip_overlays_for_tables',
			'config' => array(
				'type' => 'input',
				'size' => 255,
			)
		),
		'get_versions_directly' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.get_versions_directly',
			'config' => array(
				'type' => 'input',
				'size' => 255,
			)
		),
	),
	'types' => array(
		'0' => array('showitem' => 'hidden;;;;1-1-1, title;;1;;2-2-2, sql_query;;;;3-3-3,
									--div--;LLL:EXT:dataquery/locallang_db.xml:tx_dataquery_queries.tab.advanced, cache_duration;;;;1-1-1, ignore_enable_fields;;2;;2-2-2 , ignore_language_handling;;3;;3-3-3, get_versions_directly')
	),
	'palettes' => array(
		'1' => array('showitem' => 'description'),
		'2' => array('showitem' => 'ignore_time_for_tables, --linebreak--, ignore_disabled_for_tables, --linebreak--, ignore_fegroup_for_tables'),
		'3' => array('showitem' => 'skip_overlays_for_tables')
	)
);
?>