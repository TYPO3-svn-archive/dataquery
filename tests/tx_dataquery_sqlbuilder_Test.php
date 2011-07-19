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
abstract class tx_dataquery_sqlbuilder_Test extends tx_phpunit_testcase {

	/**
	 * @var	string	Base SQL condition to apply to tt_content table
	 */
	protected static $baseConditionForTTContent;

	/**
	 * @var string Absolute minimal condition applied to all TYPO3 requests, even in workspaces
	 */
	protected static $minimalConditionForTTContent;

	/**
	 * @var string Condition on user groups found inside the base condition
	 */
	protected static $groupsConditionForTTContent;

	/**
	 * @var	string	Language-related SQL condition to apply to tt_content table
	 */
	protected static $baseLanguageConditionForTTContent = '(tt_content.sys_language_uid IN (0,-1))';

	/**
	 * @var	string	Versioning-related SQL condition to apply to tt_content table
	 */
	protected static $baseWorkspaceConditionForTTContent = '(tt_content.t3ver_oid = \'0\') ';

	/**
	 * @var	string	Full SQL condition (for tt_content) to apply to all queries. Will be based on the above components.
	 */
	protected static $fullConditionForTTContent;

	/**
	 * @var boolean the minimum version. Currently the 4.5.0
	 */
	protected static $isMinimumVersion;

	/**
	 * @var	array	some default data configuration from the record
	 */
	protected $settings;

	/**
	 * @var	array	fields that must be added to the SELECT clause in some conditions
	 */
	protected $additionalFields = array();

	public function setUp() {
		self::assembleConditions();
		$this->settings = array(
			'ignore_language_handling' => FALSE,
			'ignore_enable_fields' => 0,
			'ignore_time_for_tables' => '*',
			'ignore_disabled_for_tables' => '*',
			'ignore_fegroup_for_tables' => '*',
		);
	}

	/**
	 * This method defines the values of various SQL conditions used in the testing
	 *
	 * @return void
	 */
	public static function assembleConditions() {
		self::$isMinimumVersion = t3lib_div::int_from_ver(TYPO3_version) >= t3lib_div::int_from_ver('4.5.0');
		if (self::$isMinimumVersion) {
			self::$minimalConditionForTTContent = 'tt_content.deleted=0 AND tt_content.t3ver_state<=0 AND tt_content.pid!=-1';
			self::$groupsConditionForTTContent = ' AND (tt_content.fe_group=\'\' OR tt_content.fe_group IS NULL OR tt_content.fe_group=\'0\' OR FIND_IN_SET(\'0\',tt_content.fe_group) OR FIND_IN_SET(\'-1\',tt_content.fe_group))';
			self::$baseConditionForTTContent = '(###MINIMAL_CONDITION### AND tt_content.hidden=0 AND tt_content.starttime<=###NOW### AND (tt_content.endtime=0 OR tt_content.endtime>###NOW###)###GROUP_CONDITION###)';
		}
		else {
			self::$minimalConditionForTTContent = 'tt_content.deleted=0 AND tt_content.t3ver_state<=0';
			self::$groupsConditionForTTContent = ' AND (tt_content.fe_group=\'\' OR tt_content.fe_group IS NULL OR tt_content.fe_group=\'0\' OR (tt_content.fe_group LIKE \'%,0,%\' OR  tt_content.fe_group LIKE \'0,%\' OR tt_content.fe_group LIKE \'%,0\' OR tt_content.fe_group=\'0\') OR (tt_content.fe_group LIKE \'%,-1,%\' OR  tt_content.fe_group LIKE \'-1,%\' OR tt_content.fe_group LIKE \'%,-1\' OR tt_content.fe_group=\'-1\'))';
			self::$baseConditionForTTContent = '(###MINIMAL_CONDITION### AND tt_content.hidden=0 AND tt_content.starttime<=###NOW### AND (tt_content.endtime=0 OR tt_content.endtime>###NOW###)###GROUP_CONDITION###)';
		}
			// NOTE: markers are used instead of the corresponding conditions, because the setUp() method
			// is not invoked inside the data providers. Thus when using a data provider, it's not possible
			// to refer to the conditions defined via setUp()
		self::$fullConditionForTTContent = 'WHERE ###BASE_CONDITION### AND ###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###';
	}

	/**
	 * This method takes care of replacing all the markers found in the conditions
	 *
	 * @static
	 * @param string $condition The condition to parse for markers
	 * @return string The parsed condition
	 */
	public static function finalizeCondition($condition) {
		$parsedCondition = $condition;
			// Replace the base condition marker
		$parsedCondition = str_replace('###BASE_CONDITION###', self::$baseConditionForTTContent, $parsedCondition);
			// Replace the minimal condition marker (which may have been inside the ###BASE_CONDITION### marker)
		$parsedCondition = str_replace('###MINIMAL_CONDITION###', self::$minimalConditionForTTContent, $parsedCondition);
			// Replace the group condition marker (which may have been inside the ###BASE_CONDITION### marker)
		$parsedCondition = str_replace('###GROUP_CONDITION###', self::$groupsConditionForTTContent, $parsedCondition);
			// Replace the language condition marker
		$parsedCondition = str_replace('###LANGUAGE_CONDITION###', self::$baseLanguageConditionForTTContent, $parsedCondition);
			// Replace the workspace condition marker
		$parsedCondition = str_replace('###WORKSPACE_CONDITION###', self::$baseWorkspaceConditionForTTContent, $parsedCondition);
			// Replace time marker by time used for starttime and endtime enable fields
			// This is done last because it is "contained" in other markers
		$parsedCondition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], $parsedCondition);
		return $parsedCondition;
	}

	/**
	 * Parse and rebuild a simple SELECT query
	 *
	 * @test
	 */
	public function simpleSelectQuery() {
			// Replace markers in the condition
		$condition = self::finalizeCondition(self::$fullConditionForTTContent);
		$additionalSelectFields = $this->prepareAdditionalFields('tt_content');
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid, tt_content.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS tt_content ' . $condition;
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header FROM tt_content';
		$parser->parseQuery($query);
		$parser->setProviderData($this->settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part is correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a simple SELECT query with an alias for the table name
	 *
	 * @test
	 */
	public function simpleSelectQueryWithTableAlias() {
			// Replace markers in the condition
		$condition = self::finalizeCondition(self::$fullConditionForTTContent);
			// Replace table name by its alias
		$condition = str_replace('tt_content', 'c', $condition);
		$additionalSelectFields = $this->prepareAdditionalFields('c');
		$expectedResult = 'SELECT c.uid, c.header, c.pid, c.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS c ' . $condition;
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header FROM tt_content AS c';
		$parser->parseQuery($query);
		$parser->setProviderData($this->settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part is correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with an id list
	 *
	 * @test
	 */
	public function selectQueryWithIdList() {
			// Replace markers in the condition
		$condition = self::finalizeCondition(self::$fullConditionForTTContent);
		$additionalSelectFields = $this->prepareAdditionalFields('tt_content');
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid, tt_content.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS tt_content ' . $condition. 'AND (tt_content.uid IN (1,12)) ';
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header FROM tt_content';
		$parser->parseQuery($query);
		$parser->setProviderData($this->settings);
		$parser->addTypo3Mechanisms();
			// Add the id list
			// NOTE: "pages_3" is expected to be ignored, as the "pages" table is not being queried
		$parser->addIdList('1,tt_content_12,pages_3');
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part is correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with an id list
	 *
	 * @test
	 */
	public function selectQueryWithUidAsAliasAndDistinct() {
			// Language condition does not apply when DISTINCT is used, so assemble a specific condition
		$condition = 'WHERE ###BASE_CONDITION### AND ###WORKSPACE_CONDITION###';
			// Replace markers in the condition
		$condition = self::finalizeCondition($condition);
		$additionalSelectFields = $this->prepareAdditionalFields('tt_content');
		$expectedResult = 'SELECT DISTINCT tt_content.CType AS uid' . $additionalSelectFields . ' FROM tt_content AS tt_content ' . $condition;
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT DISTINCT CType AS uid FROM tt_content';
		$parser->parseQuery($query);
		$parser->setProviderData($this->settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();
			// Check if the "structure" part is correct
		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with a filter
	 *
	 * @test
	 */
	public function selectQueryWithFilter() {
			// Replace markers in the condition
		$condition = self::finalizeCondition(self::$fullConditionForTTContent);
		$additionalSelectFields = $this->prepareAdditionalFields('tt_content');
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, FROM_UNIXTIME(tstamp, \'%Y\') AS year, tt_content.pid, tt_content.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS tt_content ' . $condition . 'AND (((tt_content.uid > \'10\') AND (tt_content.uid <= \'50\')) AND ((tt_content.header LIKE \'%foo%\' OR tt_content.header LIKE \'%bar%\')) AND ((tt_content.image IS NOT NULL)) AND ((tt_content.header = \'\')) AND ((FROM_UNIXTIME(tstamp, \'%Y\') = \'2010\'))) ORDER BY tt_content.crdate desc ';
			/**
			 * @var tx_dataquery_parser	$parser
			 */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header, FROM_UNIXTIME(tstamp, \'%Y\') AS year FROM tt_content';
		$parser->parseQuery($query);
		$parser->setProviderData($this->settings);
		$parser->addTypo3Mechanisms();
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
				),
					// This filter must not be applied (void filter)
				6 => array(
					'table' => 'tt_content',
					'field' => 'month',
					'void' => TRUE,
					'conditions' => array(
						0 => array(
							'operator' => '>',
							'value' => 3
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
	 * Provides various setups for all ignore flags
	 * Also provides the corresponding expected WHERE clauses
	 * NOTE: we use markers for some conditions, because the values defined in setUp() are not available
	 * to the data providers
	 *
	 * @return array
	 */
	public static function ignoreSetupProvider() {
		self::assembleConditions();
		$setup = array(
			'ignore nothing' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '0',
					'ignore_time_for_tables' => '',
					'ignore_disabled_for_tables' => 'pages',
					'ignore_fegroup_for_tables' => 'tt_content' // Tests that this is *not* ignore, because global ignore flag is 0
				),
				'condition' => self::$fullConditionForTTContent
			),
				// Ignore all enable fields (detailed settings should be irrelevant)
			'ignore all' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '1',
					'ignore_time_for_tables' => '',
					'ignore_disabled_for_tables' => 'pages',
					'ignore_fegroup_for_tables' => 'tt_content'
				),
				'condition' => 'WHERE ###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###'
			),
				// Ignore select enable fields, take 1: ignore all fields for all tables
			'ignore selected - all for all tables' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '2',
					'ignore_time_for_tables' => '*',
					'ignore_disabled_for_tables' => '*',
					'ignore_fegroup_for_tables' => '*'
				),
				'condition' => 'WHERE (###MINIMAL_CONDITION###) AND ###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###'
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
				'condition' => 'WHERE (###MINIMAL_CONDITION###) AND ###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###'
			),
				// Ignore select enable fields, take 3: ignore time fields for all tables and hidden field for tt_content
			'ignore selected - time and disabled for tt_content' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '2',
					'ignore_time_for_tables' => '*',
					'ignore_disabled_for_tables' => ', tt_content', // Weird but valid value (= tt_content)
					'ignore_fegroup_for_tables' => 'pages' // Irrelevant, table "pages" is not in query
				),
				'condition' => "WHERE (###MINIMAL_CONDITION######GROUP_CONDITION###) AND ###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###"
			),
				// Ignore select enable fields, take 4: no tables defined at all, so nothing is ignore after all
			'ignore selected - ignore nothing after all' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '2',
					'ignore_time_for_tables' => '',
					'ignore_disabled_for_tables' => '',
					'ignore_fegroup_for_tables' => ''
				),
				'condition' => self::$fullConditionForTTContent
			),
		);
		return $setup;
	}

	/**
	 * Parse and rebuild a simple SELECT query and test value of ignore_enable_fields set to 0,
	 * i.e. enable fields are not ignored at all
	 *
	 * @param array $ignoreSetup Array with mechanisms to ignore
	 * @param string $condition Expected condition
	 * @test
	 * @dataProvider ignoreSetupProvider
	 */
	public function addTypo3MechanismsWithIgnoreEnableFields($ignoreSetup, $condition) {
			// Replace markers in the condition
		$condition = self::finalizeCondition($condition);
			// Add extra fields, as needed
		$additionalSelectFields = $this->prepareAdditionalFields('tt_content');
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid, tt_content.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS tt_content ' . $condition;
			/** @var $parser tx_dataquery_parser */
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
	 * This method prepares the addition to the SELECT string necessary for any
	 * additional fields defined by a given test class
	 *
	 * @param	string	$table: name of the table to use
	 * @return	string	List of additional fields to add to SELECT statement
	 */
	protected function prepareAdditionalFields($table) {
		$additionalSelectFields = '';
		if (count($this->additionalFields) > 0) {
			foreach ($this->additionalFields as $field) {
				$additionalSelectFields .= ', ' . $table . '.' . $field;
			}
		}
		return $additionalSelectFields;
	}

	/**
	 * Utility method to compare two strings one letter after the other
	 * This helps when trying to find whitespace differences which may make a test fail,
	 * but are not visible in the BE module
	 *
	 * @param string $a: first string to compare
	 * @param string $b: second string to compare
	 * @return void
	 */
	protected function compareStringLetterPerLetter($a, $b) {
		$length = max(array(strlen($a), strlen($b)));
		$comparison = array();
		for ($i = 0; $i < $length; $i++) {
			$comparison[] = ((isset($a[$i])) ? $a[$i] : '*') . ' - ' . ((isset($b[$i])) ? $b[$i] : '*');
		}
		t3lib_div::devlog('String comparison', 'dataquery', 0, $comparison);
	}
}
?>