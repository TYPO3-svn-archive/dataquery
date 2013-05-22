<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "dataquery".
 *
 * Auto generated 10-12-2012 17:37
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = array(
	'title' => 'SQL-based Data Provider - Tesseract project',
	'description' => 'Assembles a query on data stored in the TYPO3 local database, automatically enforcing criteria like language, publication date, etc. More info on http://www.typo3-tesseract.com/',
	'category' => 'misc',
	'author' => 'Francois Suter (Cobweb)',
	'author_email' => 'typo3@cobweb.ch',
	'shy' => '',
	'dependencies' => 'tesseract,datafilter,overlays,expressions',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => '',
	'version' => '1.8.2',
	'constraints' => array(
		'depends' => array(
			'tesseract' => '1.5.0-0.0.0',
			'datafilter' => '1.6.0-0.0.0',
			'overlays' => '2.0.0-0.0.0',
			'typo3' => '4.5.0-6.1.99',
			'expressions' => '',
		),
		'conflicts' => array(
		),
		'suggests' => array(
			'devlog' => '',
			'cachecleaner' => '',
		),
	),
	'_md5_values_when_last_written' => 'a:36:{s:9:"ChangeLog";s:4:"5681";s:27:"class.tx_dataquery_ajax.php";s:4:"2d32";s:28:"class.tx_dataquery_cache.php";s:4:"1106";s:29:"class.tx_dataquery_parser.php";s:4:"a33b";s:34:"class.tx_dataquery_queryobject.php";s:4:"b13b";s:32:"class.tx_dataquery_sqlparser.php";s:4:"f447";s:33:"class.tx_dataquery_sqlutility.php";s:4:"9e86";s:30:"class.tx_dataquery_wrapper.php";s:4:"fc7e";s:16:"ext_autoload.php";s:4:"6a06";s:21:"ext_conf_template.txt";s:4:"bd30";s:12:"ext_icon.gif";s:4:"ebf0";s:17:"ext_localconf.php";s:4:"c03f";s:14:"ext_tables.php";s:4:"28e0";s:14:"ext_tables.sql";s:4:"5115";s:13:"locallang.xml";s:4:"8d70";s:37:"locallang_csh_txdatafilterfilters.xml";s:4:"73bc";s:36:"locallang_csh_txdataqueryqueries.xml";s:4:"d2d2";s:16:"locallang_db.xml";s:4:"fe86";s:10:"README.txt";s:4:"b3a6";s:7:"tca.php";s:4:"cd0f";s:14:"doc/manual.pdf";s:4:"f3da";s:14:"doc/manual.sxw";s:4:"7548";s:14:"doc/manual.txt";s:4:"f4d7";s:43:"hooks/class.tx_dataquery_datafilterhook.php";s:4:"43e3";s:34:"res/icons/add_dataquery_wizard.gif";s:4:"909a";s:39:"res/icons/icon_tx_dataquery_queries.gif";s:4:"ebf0";s:22:"res/js/check_wizard.js";s:4:"b347";s:42:"samples/class.tx_dataquery_sample_hook.php";s:4:"6530";s:34:"tests/tx_dataquery_parser_Test.php";s:4:"bdb8";s:46:"tests/tx_dataquery_sqlbuilder_default_Test.php";s:4:"8bf8";s:47:"tests/tx_dataquery_sqlbuilder_language_Test.php";s:4:"5b9c";s:38:"tests/tx_dataquery_sqlbuilder_Test.php";s:4:"2255";s:48:"tests/tx_dataquery_sqlbuilder_workspace_Test.php";s:4:"7b25";s:37:"tests/tx_dataquery_sqlparser_Test.php";s:4:"1d40";s:35:"tests/tx_dataquery_wrapper_Test.php";s:4:"c609";s:44:"wizards/class.tx_dataquery_wizards_check.php";s:4:"7a93";}',
	'suggests' => array(
	),
);

?>