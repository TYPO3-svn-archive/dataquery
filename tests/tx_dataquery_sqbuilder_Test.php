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
	protected static $baseConditionForTTContent = 'WHERE tt_content.deleted=0 AND tt_content.t3ver_state<=0 AND tt_content.hidden=0 AND tt_content.starttime<=###NOW### AND (tt_content.endtime=0 OR tt_content.endtime>###NOW###) AND (tt_content.fe_group=\'\' OR tt_content.fe_group IS NULL OR tt_content.fe_group=\'0\' OR (tt_content.fe_group LIKE \'%,0,%\' OR  tt_content.fe_group LIKE \'0,%\' OR tt_content.fe_group LIKE \'%,0\' OR tt_content.fe_group=\'0\') OR (tt_content.fe_group LIKE \'%,-1,%\' OR  tt_content.fe_group LIKE \'-1,%\' OR tt_content.fe_group LIKE \'%,-1\' OR tt_content.fe_group=\'-1\')) AND (tt_content.sys_language_uid IN (0,-1)) AND tt_content.t3ver_oid = \'0\' ';

	/**
	 * Parse and rebuild a simple SELECT query
	 *
	 * @test
	 */
	public function simpleSelectQuery() {
		$condition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], self::$baseConditionForTTContent);
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid AS tt_content$pid, tt_content.sys_language_uid AS tt_content$sys_language_uid FROM tt_content AS tt_content ' . $condition;
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header FROM tt_content';
			// Replace time marker by time used for starttime and endtime enable fields
		$parser->parseQuery($query);
		$settings = array(
			'ignore_enable_fields' => FALSE,
			'ignore_language_handling' => FALSE
		);
		$parser->addTypo3Mechanisms($settings);
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with an id list
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
		$parser->parseQuery($query);
		$parser->addIdList('1,tt_content_12');
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with an id list
	 *
	 * @test
	 */
	public function selectQueryWithUidAsAlias() {
		$expectedResult = 'SELECT DISTINCT tt_content.CType AS uid FROM tt_content AS tt_content ';
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT DISTINCT CType AS uid FROM tt_content';
		$parser->parseQuery($query);
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with a filter
	 *
	 * @test
	 */
	public function selectQueryWithFilter() {
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, FROM_UNIXTIME(tstamp, \'%Y\') AS year FROM tt_content AS tt_content WHERE (tt_content.uid > \'10\' AND tt_content.uid <= \'50\') AND (tt_content.header LIKE \'%foo%\') AND (tt_content.image IS NOT NULL) AND (tt_content.header = \'\') AND (FROM_UNIXTIME(tstamp, \'%Y\') = \'2010\') ORDER BY tt_content.crdate desc ';
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header, FROM_UNIXTIME(tstamp, \'%Y\') AS year FROM tt_content';
		$parser->parseQuery($query);
			// Define filter with many different conditions
		$filter = array(
			'filters' => array(
				0 => array(
					'table' => 'tt_content',
					'field' => 'uid',
					'conditions' => array(
						0 => array(
							'operator' => '>',
							'value' => 10
						),
						1 => array(
							'operator' => '<=',
							'value' => 50
						)
					)
				),
				1 => array(
					'table' => 'tt_content',
					'field' => 'header',
					'conditions' => array(
						0 => array(
							'operator' => 'like',
							'value' => 'foo'
						)
					)
				),
					// Test filters using special value \null, \empty and \all
				2 => array(
					'table' => 'tt_content',
					'field' => 'image',
					'conditions' => array(
						0 => array(
							'operator' => '!=',
							'value' => '\null'
						)
					)
				),
				3 => array(
					'table' => 'tt_content',
					'field' => 'header',
					'conditions' => array(
						0 => array(
							'operator' => '=',
							'value' => '\empty'
						)
					)
				),
				4 => array(
					'table' => 'tt_content',
					'field' => 'bodytext',
					'conditions' => array(
						0 => array(
							'operator' => '=',
							'value' => '\all'
						)
					)
				),
					// Test filter on a field using an alias
				5 => array(
					'table' => 'tt_content',
					'field' => 'year',
					'conditions' => array(
						0 => array(
							'operator' => '=',
							'value' => 2010
						)
					)
				)
			),
			'logicalOperator' => 'AND',
			'limit' => array(
				'max' => 20,
				'offset' => 2
			),
			'orderby' => array(
				0 => array(
					'table' => 'tt_content',
					'field' => 'crdate',
					'order' => 'desc'
				)
			)
		);
		$parser->addFilter($filter);
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with an explicit JOIN and fields forced to another table
	 *
	 * @test
	 */
	public function selectQueryWithJoin() {
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, pages.title AS tt_content$title, pages.uid AS pages$uid FROM tt_content AS tt_content INNER JOIN pages AS pages ON pages.uid = tt_content.pid ';
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header,pages.title AS tt_content.title FROM tt_content INNER JOIN pages ON pages.uid = tt_content.pid';
		$parser->parseQuery($query);
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult);
	}
}
?>