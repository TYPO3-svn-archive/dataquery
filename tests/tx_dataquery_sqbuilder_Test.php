<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Francois Suter <typo3@cobweb.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(t3lib_extMgm::extPath('dataquery', 'class.tx_dataquery_parser.php'));

/**
 * Testcase for the Data Query query builder
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_sqlbuilder_Test extends tx_phpunit_testcase {
	protected $baseConditionForContent = 'WHERE tt_content.deleted=0 AND tt_content.t3ver_state<=0 AND tt_content.hidden=0 AND tt_content.starttime<=###NOW### AND (tt_content.endtime=0 OR tt_content.endtime>###NOW###) AND (tt_content.fe_group=\'\' OR tt_content.fe_group IS NULL OR tt_content.fe_group=\'0\' OR (tt_content.fe_group LIKE \'%,0,%\' OR tt_content.fe_group LIKE \'0,%\' OR tt_content.fe_group LIKE \'%,0\' OR tt_content.fe_group=\'0\') OR (tt_content.fe_group LIKE \'%,-1,%\' OR tt_content.fe_group LIKE \'-1,%\' OR tt_content.fe_group LIKE \'%,-1\' OR tt_content.fe_group=\'-1\')) AND (tt_content.sys_language_uid IN (0,-1)) AND tt_content.t3ver_oid = \'0\' ';

	/**
	 * Parse and rebuild a simple SELECT query
	 *
	 * @test
	 */
	public function simpleSelectQuery() {
		$expectedResult = 'SELECT tt_content.uid, tt_content.header FROM tt_content AS tt_content ';
		/**
		 * @var tx_dataquery_parser	$parser
		 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header FROM tt_content';
			// Replace time marker by time used for starttime and endtime enable fields
//		$condition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], self::$baseConditionForContent);
		$parser->parseQuery($query);
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a simple SELECT query
	 *
	 * @test
	 */
	public function selectQueryWithIdList() {
		$expectedResult = 'SELECT tt_content.uid, tt_content.header FROM tt_content AS tt_content WHERE tt_content.uid IN (1,12) ';
		/**
		 * @var tx_dataquery_parser	$parser
		 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header FROM tt_content';
			// Replace time marker by time used for starttime and endtime enable fields
//		$condition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], self::$baseConditionForContent);
		$parser->parseQuery($query);
		$parser->addIdList('1,tt_content_12');
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult);
	}
}
?>