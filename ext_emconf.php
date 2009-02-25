<?php

########################################################################
# Extension Manager/Repository config file for ext: "dataquery"
#
# Auto generated 08-05-2008 11:14
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Data Query Engine (Data Provider)',
	'description' => 'Assembles a query on data stored in the TYPO3 local database, automatically enforcing criteria like languages and versioning, and returns a standardised data set. Acts as a Data Provider for the Display Controller.',
	'category' => 'misc',
	'author' => 'Francois Suter (Cobweb)',
	'author_email' => 'typo3@cobweb.ch',
	'shy' => '',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'module' => 'tx_dataquery_queries_sql_query',
	'state' => 'alpha',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => '',
	'version' => '0.6.7',
	'constraints' => array(
		'depends' => array(
			'basecontroller' => '0.2.0-0.0.0',
			'overlays' => '0.2.0-0.0.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
			'devlog' => '',
		),
	),
	'_md5_values_when_last_written' => 'a:17:{s:9:"ChangeLog";s:4:"b93a";s:10:"README.txt";s:4:"e9a1";s:29:"class.tx_dataquery_parser.php";s:4:"e818";s:30:"class.tx_dataquery_wrapper.php";s:4:"0371";s:21:"ext_conf_template.txt";s:4:"f2bd";s:12:"ext_icon.gif";s:4:"ebf0";s:17:"ext_localconf.php";s:4:"62d8";s:14:"ext_tables.php";s:4:"83a3";s:14:"ext_tables.sql";s:4:"71c8";s:29:"icon_tx_dataquery_queries.gif";s:4:"ebf0";s:16:"locallang_db.xml";s:4:"ae5a";s:7:"tca.php";s:4:"e6f5";s:40:"tx_dataquery_queries_sql_query/clear.gif";s:4:"cc11";s:39:"tx_dataquery_queries_sql_query/conf.php";s:4:"bc5e";s:40:"tx_dataquery_queries_sql_query/index.php";s:4:"be83";s:44:"tx_dataquery_queries_sql_query/locallang.xml";s:4:"8ce6";s:46:"tx_dataquery_queries_sql_query/wizard_icon.gif";s:4:"1bdc";}',
	'suggests' => array(
	),
);

?>