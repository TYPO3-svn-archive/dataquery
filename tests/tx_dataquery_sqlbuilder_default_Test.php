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

/**
 * Testcase for the Data Query query builder
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_sqlbuilder_Default_Test extends tx_dataquery_sqlbuilder_Test {

	/**
	 * Provides various setups for all ignore flags
	 * Also provides the corresponding expected WHERE clauses
	 *
	 * @return array
	 */
	public static function ignoreSetupProvider() {
		self::assembleConditions();
		$fullCondition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], self::$fullConditionForTTContent);
		$setup = array(
			'ignore nothing' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '0',
					'ignore_time_for_tables' => '',
					'ignore_disabled_for_tables' => 'pages',
					'ignore_fegroup_for_tables' => 'tt_content' // Tests that this is *not* ignore, because global ignore flag is 0
				),
				'condition' => $fullCondition
			),
				// Ignore all enable fields (detailed settings should be irrelevant)
			'ignore all' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '1',
					'ignore_time_for_tables' => '',
					'ignore_disabled_for_tables' => 'pages',
					'ignore_fegroup_for_tables' => 'tt_content'
				),
				'condition' => 'WHERE ' . self::$baseLanguageConditionForTTContent . self::$baseWorkspaceConditionForTTContent
			),
				// Ignore select enable fields, take 1: ignore all fields for all tables
			'ignore selected - all for all tables' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '2',
					'ignore_time_for_tables' => '*',
					'ignore_disabled_for_tables' => '*',
					'ignore_fegroup_for_tables' => '*'
				),
				'condition' => 'WHERE (tt_content.deleted=0 AND tt_content.t3ver_state<=0 AND tt_content.pid!=-1) AND ' . self::$baseLanguageConditionForTTContent . self::$baseWorkspaceConditionForTTContent
			),
				// Ignore select enable fields, take 2: ignore all fields for all tables
				// NOTE: should be the same as previous one since the only table in the query is tt_content
			'ignore selected - all for tt_content' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '2',
					'ignore_time_for_tables' => 'tt_content',
					'ignore_disabled_for_tables' => 'tt_content',
					'ignore_fegroup_for_tables' => 'tt_content'
				),
				'condition' => 'WHERE (tt_content.deleted=0 AND tt_content.t3ver_state<=0 AND tt_content.pid!=-1) AND ' . self::$baseLanguageConditionForTTContent . self::$baseWorkspaceConditionForTTContent
			),
				// Ignore select enable fields, take 3: ignore time fields for all tables and hidden field for tt_content
			'ignore selected - time and disabled for tt_content' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '2',
					'ignore_time_for_tables' => '*',
					'ignore_disabled_for_tables' => ', tt_content', // Weird but valid value (= tt_content)
					'ignore_fegroup_for_tables' => 'pages' // Irrelevant, table "pages" is not in query
				),
				'condition' => "WHERE (tt_content.deleted=0 AND tt_content.t3ver_state<=0 AND tt_content.pid!=-1 AND (tt_content.fe_group='' OR tt_content.fe_group IS NULL OR tt_content.fe_group='0' OR FIND_IN_SET('0',tt_content.fe_group) OR FIND_IN_SET('-1',tt_content.fe_group))) AND " . self::$baseLanguageConditionForTTContent . self::$baseWorkspaceConditionForTTContent
			),
				// Ignore select enable fields, take 4: no tables defined at all, so nothing is ignore after all
			'ignore selected - ignore nothing after all' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '2',
					'ignore_time_for_tables' => '',
					'ignore_disabled_for_tables' => '',
					'ignore_fegroup_for_tables' => ''
				),
				'condition' => $fullCondition
			),
		);
		return $setup;
	}

	/**
	 * Parse and rebuild a simple SELECT query and test value of ignore_enable_fields set to 0,
	 * i.e. enable fields are not ignored at all
	 *
	 * @test
	 * @dataProvider ignoreSetupProvider
	 */
	public function addTypo3MechanismsWithIgnoreEnableFields($ignoreSetup, $condition) {
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid, tt_content.sys_language_uid FROM tt_content AS tt_content ' . $condition;
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header FROM tt_content';
		$parser->parseQuery($query);
			// Assemble the settings and rebuild the query
		$settings = array_merge($this->settings, $ignoreSetup);
		$parser->setProviderData($settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();

		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with a filter
	 *
	 * @test
	 */
	public function selectQueryWithFilter() {
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, FROM_UNIXTIME(tstamp, \'%Y\') AS year, tt_content.pid FROM tt_content AS tt_content WHERE (((tt_content.uid > \'10\') AND (tt_content.uid <= \'50\')) AND ((tt_content.header LIKE \'%foo%\' OR tt_content.header LIKE \'%bar%\')) AND ((tt_content.image IS NOT NULL)) AND ((tt_content.header = \'\')) AND ((FROM_UNIXTIME(tstamp, \'%Y\') = \'2010\'))) ORDER BY tt_content.crdate desc ';
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
							'value' => array(
								'foo',
								'bar'
							)
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
			// Check if the "structure" part is correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with an explicit JOIN and fields forced to another table
	 *
	 * @test
	 */
	public function selectQueryWithJoin() {
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, pages.title AS tt_content$title, tt_content.pid, pages.uid AS pages$uid, pages.pid AS pages$pid FROM tt_content AS tt_content INNER JOIN pages AS pages ON pages.uid = tt_content.pid ';
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header,pages.title AS tt_content.title FROM tt_content INNER JOIN pages ON pages.uid = tt_content.pid';
		$parser->parseQuery($query);
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part is correct
		$this->assertEquals($expectedResult, $actualResult);
	}
}
?>