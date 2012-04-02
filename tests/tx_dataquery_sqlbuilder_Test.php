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
	protected static $baseConditionForTable;

	/**
	 * @var string Absolute minimal condition applied to all TYPO3 requests, even in workspaces
	 */
	protected static $minimalConditionForTable;

	/**
	 * @var string Condition on user groups found inside the base condition
	 */
	protected static $groupsConditionForTable;

	/**
	 * @var	string	Language-related SQL condition to apply to tt_content table
	 */
	protected static $baseLanguageConditionForTable = '(###TABLE###.sys_language_uid IN (0,-1))';

	/**
	 * @var	string	Versioning-related SQL condition to apply to tt_content table
	 */
	protected static $baseWorkspaceConditionForTable = '(###TABLE###.t3ver_oid = \'0\') ';

	/**
	 * @var	string	Full SQL condition (for tt_content) to apply to all queries. Will be based on the above components.
	 */
	protected static $fullConditionForTable;

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

	/**
	 * @var Tx_Phpunit_Framework
	 */
	protected $testingFramework;

	/** @var tx_dataquery_parser */
	protected $sqlParser;
	/**
	 * Set up the test environment
	 *
	 * @return void
	 */
	public function setUp() {
		$this->testingFramework = new Tx_Phpunit_Framework('tx_dataquery');
		$this->testingFramework->createFakeFrontEnd();

		self::assembleConditions();
		$this->settings = array(
			'ignore_language_handling' => FALSE,
			'ignore_enable_fields' => 0,
			'ignore_time_for_tables' => '*',
			'ignore_disabled_for_tables' => '*',
			'ignore_fegroup_for_tables' => '*',
		);

			// Get a minimal instance of tx_dataquery_wrapper for passing to the parser as a back-reference
			/** @var $dataQueryWrapper tx_dataquery_wrapper */
		$dataQueryWrapper = t3lib_div::makeInstance('tx_dataquery_wrapper');
			/** @var $controller tx_displaycontroller */
		$controller = t3lib_div::makeInstance('tx_displaycontroller');
		$dataQueryWrapper->setController($controller);
		$this->sqlParser = t3lib_div::makeInstance('tx_dataquery_parser', $dataQueryWrapper);
	}

	/**
	 * Clean up the test environment
	 * @return void
	 */
	public function tearDown() {
		$this->testingFramework->cleanUp();
	}

	/**
	 * This method defines the values of various SQL conditions used in the testing
	 *
	 * @return void
	 */
	public static function assembleConditions() {
		$currentVersion = class_exists('t3lib_utility_VersionNumber')
	        ? t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version)
    	    : t3lib_div::int_from_ver(TYPO3_version);
		self::$isMinimumVersion = $currentVersion >= 4005000;
		if (self::$isMinimumVersion) {
			self::$minimalConditionForTable = '###TABLE###.deleted=0 AND ###TABLE###.t3ver_state<=0 AND ###TABLE###.pid<>-1';
			self::$groupsConditionForTable = ' AND (###TABLE###.fe_group=\'\' OR ###TABLE###.fe_group IS NULL OR ###TABLE###.fe_group=\'0\' OR FIND_IN_SET(\'0\',###TABLE###.fe_group) OR FIND_IN_SET(\'-1\',###TABLE###.fe_group))';
			self::$baseConditionForTable = '(###MINIMAL_CONDITION### AND ###TABLE###.hidden=0 AND ###TABLE###.starttime<=###NOW### AND (###TABLE###.endtime=0 OR ###TABLE###.endtime>###NOW###)###GROUP_CONDITION###)';
		} else {
			self::$minimalConditionForTable = '###TABLE###.deleted=0 AND ###TABLE###.t3ver_state<=0';
			self::$groupsConditionForTable = ' AND (###TABLE###.fe_group=\'\' OR ###TABLE###.fe_group IS NULL OR ###TABLE###.fe_group=\'0\' OR (###TABLE###.fe_group LIKE \'%,0,%\' OR  ###TABLE###.fe_group LIKE \'0,%\' OR ###TABLE###.fe_group LIKE \'%,0\' OR ###TABLE###.fe_group=\'0\') OR (###TABLE###.fe_group LIKE \'%,-1,%\' OR  ###TABLE###.fe_group LIKE \'-1,%\' OR ###TABLE###.fe_group LIKE \'%,-1\' OR ###TABLE###.fe_group=\'-1\'))';
			self::$baseConditionForTable = '(###MINIMAL_CONDITION### AND ###TABLE###.hidden=0 AND ###TABLE###.starttime<=###NOW### AND (###TABLE###.endtime=0 OR ###TABLE###.endtime>###NOW###)###GROUP_CONDITION###)';
		}
			// NOTE: markers are used instead of the corresponding conditions, because the setUp() method
			// is not invoked inside the data providers. Thus when using a data provider, it's not possible
			// to refer to the conditions defined via setUp()
		self::$fullConditionForTable = '###BASE_CONDITION### AND ###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###';
	}

	/**
	 * This method takes care of replacing all the markers found in the conditions
	 *
	 * @static
	 * @param string $condition The condition to parse for markers
	 * @param string $table The name of the table to use (the default is tt_content, which is used in most tests)
	 * @return string The parsed condition
	 */
	public static function finalizeCondition($condition, $table = 'tt_content') {
		$parsedCondition = $condition;
			// Replace the base condition marker
		$parsedCondition = str_replace('###BASE_CONDITION###', self::$baseConditionForTable, $parsedCondition);
			// Replace the minimal condition marker (which may have been inside the ###BASE_CONDITION### marker)
		$parsedCondition = str_replace('###MINIMAL_CONDITION###', self::$minimalConditionForTable, $parsedCondition);
			// Replace the group condition marker (which may have been inside the ###BASE_CONDITION### marker)
		$parsedCondition = str_replace('###GROUP_CONDITION###', self::$groupsConditionForTable, $parsedCondition);
			// Replace the language condition marker
		$parsedCondition = str_replace('###LANGUAGE_CONDITION###', self::$baseLanguageConditionForTable, $parsedCondition);
			// Replace the workspace condition marker
		$parsedCondition = str_replace('###WORKSPACE_CONDITION###', self::$baseWorkspaceConditionForTable, $parsedCondition);
			// Replace table marker by table name
		$parsedCondition = str_replace('###TABLE###', $table, $parsedCondition);
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
		$condition = self::finalizeCondition(self::$fullConditionForTable);
		$additionalSelectFields = $this->prepareAdditionalFields('tt_content');
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid, tt_content.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS tt_content WHERE ' . $condition;

		$query = 'SELECT uid,header FROM tt_content';
		$this->sqlParser->parseQuery($query);
		$this->sqlParser->setProviderData($this->settings);
		$this->sqlParser->addTypo3Mechanisms();
		$actualResult = $this->sqlParser->buildQuery();

		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a simple SELECT query with an alias for the table name
	 *
	 * @test
	 */
	public function simpleSelectQueryWithTableAlias() {
			// Replace markers in the condition
		$condition = self::finalizeCondition(self::$fullConditionForTable);
			// Replace table name by its alias
		$condition = str_replace('tt_content', 'c', $condition);
		$additionalSelectFields = $this->prepareAdditionalFields('c');
		$expectedResult = 'SELECT c.uid, c.header, c.pid, c.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS c WHERE ' . $condition;

		$query = 'SELECT uid,header FROM tt_content AS c';
		$this->sqlParser->parseQuery($query);
		$this->sqlParser->setProviderData($this->settings);
		$this->sqlParser->addTypo3Mechanisms();
		$actualResult = $this->sqlParser->buildQuery();

		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with an id list
	 *
	 * @test
	 */
	public function selectQueryWithIdList() {
			// Replace markers in the condition
		$condition = self::finalizeCondition(self::$fullConditionForTable);
		$additionalSelectFields = $this->prepareAdditionalFields('tt_content');
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid, tt_content.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS tt_content WHERE ' . $condition. 'AND (tt_content.uid IN (1,12)) ';

		$query = 'SELECT uid,header FROM tt_content';
		$this->sqlParser->parseQuery($query);
		$this->sqlParser->setProviderData($this->settings);
		$this->sqlParser->addTypo3Mechanisms();
			// Add the id list
			// NOTE: "pages_3" is expected to be ignored, as the "pages" table is not being queried
		$this->sqlParser->addIdList('1,tt_content_12,pages_3');
		$actualResult = $this->sqlParser->buildQuery();

		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with an id list
	 *
	 * @test
	 */
	public function selectQueryWithUidAsAliasAndDistinct() {
			// Language condition does not apply when DISTINCT is used, so assemble a specific condition
		$condition = '###BASE_CONDITION### AND ###WORKSPACE_CONDITION###';
			// Replace markers in the condition
		$condition = self::finalizeCondition($condition);
		$additionalSelectFields = $this->prepareAdditionalFields('tt_content');
		$expectedResult = 'SELECT DISTINCT tt_content.CType AS uid' . $additionalSelectFields . ' FROM tt_content AS tt_content WHERE ' . $condition;

		$query = 'SELECT DISTINCT CType AS uid FROM tt_content';
		$this->sqlParser->parseQuery($query);
		$this->sqlParser->setProviderData($this->settings);
		$this->sqlParser->addTypo3Mechanisms();
		$actualResult = $this->sqlParser->buildQuery();

		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Provides filters for testing query with filters
	 * Some filters are arbitrarily negated, to test the building of negated conditions
	 * Also provides the expected interpretation of the filter
	 *
	 * @return array
	 */
	public function filterProvider() {
		$filters = array(
			'like foo' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'header',
							'conditions' => array(
								0 => array(
									'operator' => 'like',
									'value' => array(
										'foo',
										'bar'
									),
									'negate' => FALSE
								)
							)
						),
					),
				),
				'condition' => '((tt_content.header LIKE \'%foo%\' OR tt_content.header LIKE \'%bar%\'))'
			),
			'interval' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'uid',
							'conditions' => array(
								0 => array(
									'operator' => '>',
									'value' => 10,
									'negate' => FALSE
								),
								1 => array(
									'operator' => '<=',
									'value' => 50,
									'negate' => FALSE
								)
							)
						),
					)
				),
				'condition' => '((tt_content.uid > \'10\') AND (tt_content.uid <= \'50\'))'
			),
			'not in' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'uid',
							'conditions' => array(
								0 => array(
									'operator' => 'in',
									'value' => array(1, 2, 3),
									'negate' => TRUE
								)
							)
						),
					)
				),
				'condition' => '((tt_content.uid NOT IN (\'1\',\'2\',\'3\')))'
			),
			'not orgroup' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'fe_group',
							'conditions' => array(
								0 => array(
									'operator' => 'orgroup',
									'value' => '1,2,3',
									'negate' => TRUE
								)
							)
						),
					)
				),
				'condition' => '((NOT (FIND_IN_SET(\'1\',tt_content.fe_group) OR FIND_IN_SET(\'2\',tt_content.fe_group) OR FIND_IN_SET(\'3\',tt_content.fe_group))))'
			),
			'combined with AND' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'header',
							'conditions' => array(
								0 => array(
									'operator' => 'like',
									'value' => array(
										'foo',
										'bar'
									),
									'negate' => FALSE
								)
							)
						),
						1 => array(
							'table' => 'tt_content',
							'field' => 'uid',
							'conditions' => array(
								0 => array(
									'operator' => '>',
									'value' => 10,
									'negate' => FALSE
								),
								1 => array(
									'operator' => '<=',
									'value' => 50,
									'negate' => FALSE
								)
							)
						)
					),
					'logicalOperator' => 'AND'
				),
				'condition' => '((tt_content.header LIKE \'%foo%\' OR tt_content.header LIKE \'%bar%\')) AND ((tt_content.uid > \'10\') AND (tt_content.uid <= \'50\'))'
			),
			'combined with OR' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'header',
							'conditions' => array(
								0 => array(
									'operator' => 'like',
									'value' => array(
										'foo',
										'bar'
									),
									'negate' => FALSE
								)
							)
						),
						1 => array(
							'table' => 'tt_content',
							'field' => 'uid',
							'conditions' => array(
								0 => array(
									'operator' => '>',
									'value' => 10,
									'negate' => FALSE
								),
								1 => array(
									'operator' => '<=',
									'value' => 50,
									'negate' => FALSE
								)
							)
						)
					),
					'logicalOperator' => 'OR'
				),
				'condition' => '((tt_content.header LIKE \'%foo%\' OR tt_content.header LIKE \'%bar%\')) OR ((tt_content.uid > \'10\') AND (tt_content.uid <= \'50\'))'
			),
			'filter on alias' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'year',
							'conditions' => array(
								0 => array(
									'operator' => '=',
									'value' => 2010,
									'negate' => FALSE
								)
							)
						)
					)
				),
				'condition' => '((FROM_UNIXTIME(tstamp, \'%Y\') = \'2010\'))'
			),
			'special value null' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'image',
							'conditions' => array(
								0 => array(
									'operator' => '=',
									'value' => '\null',
									'negate' => TRUE
								)
							)
						)
					)
				),
				'condition' => '((NOT (tt_content.image IS NULL)))'
			),
			'special value empty' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'header',
							'conditions' => array(
								0 => array(
									'operator' => '=',
									'value' => '\empty',
									'negate' => FALSE
								)
							)
						)
					)
				),
				'condition' => '((tt_content.header = \'\'))'
			),
				// NOTE: a filter with "all" does not get applied (no matter the operator)
			'special value all' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'bodytext',
							'conditions' => array(
								0 => array(
									'operator' => '=',
									'value' => '\all',
									'negate' => FALSE
								),
								1 => array(
									'operator' => 'like',
									'value' => '\all',
									'negate' => FALSE
								),
								2 => array(
									'operator' => 'in',
									'value' => '\all',
									'negate' => FALSE
								),
								3 => array(
									'operator' => 'andgroup',
									'value' => '\all',
									'negate' => FALSE
								)
							)
						)
					)
				),
				'condition' => ''
			),
				// NOTE: void filters do not get applied
			'void filter' => array(
				'filter' => array(
					'filters' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'month',
							'void' => TRUE,
							'conditions' => array(
								0 => array(
									'operator' => '>',
									'value' => 3,
									'negate' => FALSE
								)
							)
						)
					)
				),
				'condition' => ''
			),
			'ordering' => array(
				'filter' => array(
					'filters' => array(),
					'orderby' => array(
						0 => array(
							'table' => 'tt_content',
							'field' => 'crdate',
							'order' => 'desc'
						)
					)
				),
				'condition' => 'ORDER BY tt_content.crdate desc',
				'sqlCondition' => FALSE
			),
				// Filter limits are not applied explicitly
			'limit' => array(
				'filter' => array(
					'filters' => array(),
					'limit' => array(
						'max' => 20,
						'offset' => 2
					),
				),
				'condition' => ''
			),
		);
		return $filters;
	}

	/**
	 * Parse and rebuild a SELECT query with a filter
	 *
	 * @param array $filter Filter configuration
	 * @param string $condition Interpreted condition
	 * @param boolean $isSqlCondition TRUE if the filter applies as a SQL WHERE condition, FALSE otherwise
	 * @test
	 * @dataProvider filterProvider
	 */
	public function selectQueryWithFilter($filter, $condition, $isSqlCondition = TRUE) {
			// Replace markers in the condition
		$generalCondition = self::finalizeCondition(self::$fullConditionForTable);
		$additionalSelectFields = $this->prepareAdditionalFields('tt_content');
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, FROM_UNIXTIME(tstamp, \'%Y\') AS year, tt_content.pid, tt_content.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS tt_content WHERE ' . $generalCondition;
			// Add the filter's condition if not empty
		if (!empty($condition)) {
			if ($isSqlCondition) {
				$expectedResult .= 'AND (' . $condition . ') ';
			} else {
				$expectedResult .= $condition . ' ';
			}
		}

		$query = 'SELECT uid,header, FROM_UNIXTIME(tstamp, \'%Y\') AS year FROM tt_content';
		$this->sqlParser->parseQuery($query);
		$this->sqlParser->setProviderData($this->settings);
		$this->sqlParser->addTypo3Mechanisms();
		$this->sqlParser->addFilter($filter);
		$actualResult = $this->sqlParser->buildQuery();

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
				'condition' => self::$fullConditionForTable
			),
				// Ignore all enable fields (detailed settings should be irrelevant)
			'ignore all' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '1',
					'ignore_time_for_tables' => '',
					'ignore_disabled_for_tables' => 'pages',
					'ignore_fegroup_for_tables' => 'tt_content'
				),
				'condition' => '###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###'
			),
				// Ignore select enable fields, take 1: ignore all fields for all tables
			'ignore selected - all for all tables' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '2',
					'ignore_time_for_tables' => '*',
					'ignore_disabled_for_tables' => '*',
					'ignore_fegroup_for_tables' => '*'
				),
				'condition' => '(###MINIMAL_CONDITION###) AND ###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###'
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
				'condition' => '(###MINIMAL_CONDITION###) AND ###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###'
			),
				// Ignore select enable fields, take 3: ignore time fields for all tables and hidden field for tt_content
			'ignore selected - time and disabled for tt_content' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '2',
					'ignore_time_for_tables' => '*',
					'ignore_disabled_for_tables' => ', tt_content', // Weird but valid value (= tt_content)
					'ignore_fegroup_for_tables' => 'pages' // Irrelevant, table "pages" is not in query
				),
				'condition' => "(###MINIMAL_CONDITION######GROUP_CONDITION###) AND ###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###"
			),
				// Ignore select enable fields, take 4: no tables defined at all, so nothing is ignore after all
			'ignore selected - ignore nothing after all' => array(
				'ignore_setup' => array(
					'ignore_enable_fields' => '2',
					'ignore_time_for_tables' => '',
					'ignore_disabled_for_tables' => '',
					'ignore_fegroup_for_tables' => ''
				),
				'condition' => self::$fullConditionForTable
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
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, tt_content.pid, tt_content.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS tt_content WHERE ' . $condition;

		$query = 'SELECT uid,header FROM tt_content';
		$this->sqlParser->parseQuery($query);
			// Assemble the settings and rebuild the query
		$settings = array_merge($this->settings, $ignoreSetup);
		$this->sqlParser->setProviderData($settings);
		$this->sqlParser->addTypo3Mechanisms();
		$actualResult = $this->sqlParser->buildQuery();

		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with an explicit JOIN and fields forced to another table
	 *
	 * @test
	 */
	public function selectQueryWithJoin() {
			// Replace markers in the condition
		$conditionForTtContent = self::finalizeCondition(self::$fullConditionForTable);
		$conditionForPages = self::finalizeCondition('###BASE_CONDITION### AND ###WORKSPACE_CONDITION###', 'pages');
		$additionalSelectFieldsForTtContent = $this->prepareAdditionalFields('tt_content');
		$additionalSelectFieldsForPages = $this->prepareAdditionalFields('pages', FALSE);
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, pages.title AS tt_content$title, tt_content.pid, pages.uid AS pages$uid, pages.pid AS pages$pid, tt_content.sys_language_uid' . $additionalSelectFieldsForTtContent . $additionalSelectFieldsForPages . ' FROM tt_content AS tt_content INNER JOIN pages AS pages ON pages.uid = tt_content.pid AND ' . $conditionForPages . 'WHERE ' . $conditionForTtContent;

		$query = 'SELECT uid,header,pages.title AS tt_content.title FROM tt_content INNER JOIN pages ON pages.uid = tt_content.pid';
		$this->sqlParser->parseQuery($query);
		$this->sqlParser->setProviderData($this->settings);
		$this->sqlParser->addTypo3Mechanisms();
		$actualResult = $this->sqlParser->buildQuery();
		$this->compareStringLetterPerLetter($expectedResult, $actualResult);

		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * Parse and rebuild a SELECT query with an implicit JOIN and filters applying to both tables,
	 * including one forced to main
	 *
	 * @test
	 */
	public function selectQueryWithJoinAndFilter() {
			// Replace markers in the conditions
		$conditionForTtContent = self::finalizeCondition(self::$fullConditionForTable);
		$conditionForPages = self::finalizeCondition('###BASE_CONDITION### AND ###WORKSPACE_CONDITION###', 'pages');
		$additionalSelectFieldsForTtContent = $this->prepareAdditionalFields('tt_content');
		$additionalSelectFieldsForPages = $this->prepareAdditionalFields('pages', FALSE);
			// Assemble expected result
		$expectedResult = 'SELECT tt_content.header, pages.title AS pages$title, tt_content.uid, tt_content.pid, ';
		$expectedResult .= 'pages.uid AS pages$uid, pages.pid AS pages$pid, tt_content.sys_language_uid';
		$expectedResult .= $additionalSelectFieldsForTtContent . $additionalSelectFieldsForPages;
		$expectedResult .= ' FROM tt_content AS tt_content INNER JOIN pages AS pages ON ' . $conditionForPages;
		$expectedResult .= 'AND (((pages.title LIKE \'%bar%\'))) WHERE (pages.uid = tt_content.pid) AND ';
		$expectedResult .= $conditionForTtContent . 'AND (((tt_content.header LIKE \'%foo%\')) AND ((pages.tstamp > \'' . mktime(0, 0, 0, 1, 1, 2010) . '\'))) ';

			// Define the filter to apply
		$filter = array(
			'filters' => array(
				0 => array(
					'table' => 'tt_content',
					'field' => 'header',
					'conditions' => array(
						0 => array(
							'operator' => 'like',
							'value' => array(
								'foo',
							)
						)
					)
				),
				1 => array(
					'table' => 'pages',
					'field' => 'title',
					'conditions' => array(
						0 => array(
							'operator' => 'like',
							'value' => array(
								'bar',
							)
						)
					)
				),
				2 => array(
					'table' => 'pages',
					'field' => 'tstamp',
					'conditions' => array(
						0 => array(
							'operator' => '>',
							'value' => mktime(0, 0, 0, 1, 1, 2010)
						)
					),
					'main' => TRUE
				)
			),
			'logicalOperator' => 'AND'
		);

		$query = 'SELECT header,pages.title FROM tt_content,pages WHERE pages.uid = tt_content.pid';
		$this->sqlParser->parseQuery($query);
		$this->sqlParser->setProviderData($this->settings);
		$this->sqlParser->addTypo3Mechanisms();
		$this->sqlParser->addFilter($filter);
		$actualResult = $this->sqlParser->buildQuery();
		$this->compareStringLetterPerLetter($expectedResult, $actualResult);

		$this->assertEquals($expectedResult, $actualResult);
	}

	/**
	 * This method prepares the addition to the SELECT string necessary for any
	 * additional fields defined by a given test class
	 *
	 * @param string $table Name of the table to use
	 * @param boolean $isMainTable True if the table is the main one, false otherwise
	 * @return	string	List of additional fields to add to SELECT statement
	 */
	protected function prepareAdditionalFields($table, $isMainTable = TRUE) {
		$additionalSelectFields = '';
		if (count($this->additionalFields) > 0) {
			foreach ($this->additionalFields as $field) {
				$additionalSelectFields .= ', ' . $table . '.' . $field;
					// If table is not the main one, add alias
				if (!$isMainTable) {
					$additionalSelectFields .= ' AS ' . $table . '$' . $field;
				}
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