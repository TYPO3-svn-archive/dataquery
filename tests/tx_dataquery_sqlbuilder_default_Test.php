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
	 * Parse and rebuild a simple SELECT query and test value of ignore_enable_fields set to 0,
	 * i.e. enable fields are not ignored at all
	 *
	 * @test
	 */
	public function addTypo3MechanismsWithIgnoreEnableFieldSetToZero() {
		if (!$this->isMinimumVersion) {
			return;
		}
		$condition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], self::$baseConditionForTTContent);
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid, tt_content.sys_language_uid FROM tt_content AS tt_content ' . $condition;
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header FROM tt_content';
			// Replace time marker by time used for starttime and endtime enable fields
		$parser->parseQuery($query);
		$settings = $this->settings;
		$settings['ignore_enable_fields'] = '0';
		$settings['ignore_time_for_tables'] = '';
		$settings['ignore_disabled_for_tables'] = 'pages';
		$settings['ignore_fegroup_for_tables'] = 'tt_content';

		$parser->setProviderData($settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part is correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a simple SELECT query and test value of ignore_enable_fields set to 1,
	 * i.e. all enable fields are ignored
	 *
	 * @test
	 */
	public function addTypo3MechanismsWithIgnoreEnableFieldSetToOne() {
		if (!$this->isMinimumVersion) {
			return;
		}
		$condition = "WHERE (tt_content.sys_language_uid IN (0,-1)) AND tt_content.t3ver_oid = '0' ";
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid, tt_content.sys_language_uid FROM tt_content AS tt_content ' . $condition;
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header FROM tt_content';
			// Replace time marker by time used for starttime and endtime enable fields
		$parser->parseQuery($query);
		$settings = $this->settings;
		$settings['ignore_enable_fields'] = '1';
		$settings['ignore_time_for_tables'] = '';
		$settings['ignore_disabled_for_tables'] = 'pages';
		$settings['ignore_fegroup_for_tables'] = 'tt_content';

		$parser->setProviderData($settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part is correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a simple SELECT query and test value of ignore_enable_fields set to 2,
	 * i.e. some enable fields are ignored for some tables
	 *
	 * @test
	 */
	public function addTypo3MechanismsWithIgnoreEnableFieldSetToTwo() {
		if (!$this->isMinimumVersion) {
			return;
		}
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid, tt_content.sys_language_uid FROM tt_content AS tt_content ';

			//////////////////////
			// First assertion
			//////////////////////
		$settings = $this->settings;
		$settings['ignore_enable_fields'] = '2';
		$settings['ignore_time_for_tables'] = '*';
		$settings['ignore_disabled_for_tables'] = '*';
		$settings['ignore_fegroup_for_tables'] = '*';

			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header FROM tt_content';
		$parser->parseQuery($query);
		$parser->setProviderData($settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();

			// Check if the "structure" part is correct
		$condition = "WHERE tt_content.deleted=0 AND tt_content.t3ver_state<=0 AND (tt_content.sys_language_uid IN (0,-1)) AND tt_content.t3ver_oid = '0' ";
		$this->assertEquals($expectedResult . $condition, $actualResult);

			//////////////////////
			// Second assertion
			//////////////////////
		$settings = $this->settings;
		$settings['ignore_enable_fields'] = '2';
		$settings['ignore_time_for_tables'] = 'tt_content';
		$settings['ignore_disabled_for_tables'] = 'tt_content';
		$settings['ignore_fegroup_for_tables'] = 'tt_content';

		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$parser->parseQuery($query);
		$parser->setProviderData($settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();

			// Check if the "structure" part is correct
		$this->assertEquals($expectedResult . $condition, $actualResult);

			//////////////////////
			// Third assertion
			//////////////////////
		$settings = $this->settings;
		$settings['ignore_enable_fields'] = '2';
		$settings['ignore_time_for_tables'] = '*';
		$settings['ignore_disabled_for_tables'] = ' , tt_content '; # weird value
		$settings['ignore_fegroup_for_tables'] = 'pages';

		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$parser->parseQuery($query);
		$parser->setProviderData($settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();

			// Check if the "structure" part is correct
		$condition = "WHERE tt_content.deleted=0 AND tt_content.t3ver_state<=0 AND (tt_content.fe_group='' OR tt_content.fe_group IS NULL OR tt_content.fe_group='0' OR FIND_IN_SET('0',tt_content.fe_group) OR FIND_IN_SET('-1',tt_content.fe_group)) AND (tt_content.sys_language_uid IN (0,-1)) AND tt_content.t3ver_oid = '0' ";
		$this->assertEquals($expectedResult . $condition, $actualResult);

			//////////////////////
			// Fourth assertion
			//////////////////////
		$settings = $this->settings;
		$settings['ignore_enable_fields'] = '2';
		$settings['ignore_time_for_tables'] = '';
		$settings['ignore_disabled_for_tables'] = '';
		$settings['ignore_fegroup_for_tables'] = '';

		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$parser->parseQuery($query);
		$parser->setProviderData($settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();

		$condition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], self::$baseConditionForTTContent);
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid AS tt_content$pid, tt_content.sys_language_uid AS tt_content$sys_language_uid FROM tt_content AS tt_content ';

			// Check if the "structure" part is correct
		$this->assertEquals($expectedResult . $condition, $actualResult);
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