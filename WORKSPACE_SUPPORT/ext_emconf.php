<?php

########################################################################
# Extension Manager/Repository config file for ext "dataquery".
#
# Auto generated 27-09-2010 16:03
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

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
	'version' => '1.0.0',
	'constraints' => array(
		'depends' => array(
			'tesseract' => '1.0.0-0.0.0',
			'datafilter' => '1.0.0-0.0.0',
			'overlays' => '1.0.0-0.0.0',
			'typo3' => '4.3.0-0.0.0',
			'expressions' => '',
		),
		'conflicts' => array(
		),
		'suggests' => array(
			'devlog' => '',
			'cachecleaner' => '',
		),
	),
	'_md5_values_when_last_written' => 'a:29:{s:9:"ChangeLog";s:4:"e6e6";s:10:"README.txt";s:4:"b3a6";s:27:"class.tx_dataquery_ajax.php";s:4:"2907";s:28:"class.tx_dataquery_cache.php";s:4:"1106";s:29:"class.tx_dataquery_parser.php";s:4:"0d8b";s:34:"class.tx_dataquery_queryobject.php";s:4:"662c";s:32:"class.tx_dataquery_sqlparser.php";s:4:"565c";s:30:"class.tx_dataquery_wrapper.php";s:4:"2dcb";s:16:"ext_autoload.php";s:4:"6a06";s:21:"ext_conf_template.txt";s:4:"6c35";s:12:"ext_icon.gif";s:4:"ebf0";s:17:"ext_localconf.php";s:4:"41bb";s:14:"ext_tables.php";s:4:"3162";s:14:"ext_tables.sql";s:4:"c88c";s:13:"locallang.xml";s:4:"5f94";s:37:"locallang_csh_txdatafilterfilters.xml";s:4:"73bc";s:36:"locallang_csh_txdataqueryqueries.xml";s:4:"795c";s:16:"locallang_db.xml";s:4:"6374";s:7:"tca.php";s:4:"d635";s:14:"doc/manual.pdf";s:4:"6ad8";s:14:"doc/manual.sxw";s:4:"040e";s:43:"hooks/class.tx_dataquery_datafilterhook.php";s:4:"5edc";s:34:"res/icons/add_dataquery_wizard.gif";s:4:"909a";s:39:"res/icons/icon_tx_dataquery_queries.gif";s:4:"ebf0";s:22:"res/js/check_wizard.js";s:4:"b347";s:42:"samples/class.tx_dataquery_sample_hook.php";s:4:"6530";s:38:"tests/tx_dataquery_sqlbuilder_Test.php";s:4:"f821";s:37:"tests/tx_dataquery_sqlparser_Test.php";s:4:"1d40";s:44:"wizards/class.tx_dataquery_wizards_check.php";s:4:"9b01";}',
	'suggests' => array(
	),
);

?>