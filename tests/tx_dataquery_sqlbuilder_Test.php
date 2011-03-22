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
	 * @var	string	Language-related SQL condition to apply to tt_content table
	 */
	protected static $baseLanguageConditionForTTContent = '(tt_content.sys_language_uid IN (0,-1)) ';

	/**
	 * @var	string	Versioning-related SQL condition to apply to tt_content table
	 */
	protected static $baseWorkspaceConditionForTTContent = 'AND (tt_content.t3ver_oid = \'0\') ';

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
			self::$baseConditionForTTContent = 'WHERE (tt_content.deleted=0 AND tt_content.t3ver_state<=0 AND tt_content.pid!=-1 AND tt_content.hidden=0 AND tt_content.starttime<=###NOW### AND (tt_content.endtime=0 OR tt_content.endtime>###NOW###) AND (tt_content.fe_group=\'\' OR tt_content.fe_group IS NULL OR tt_content.fe_group=\'0\' OR FIND_IN_SET(\'0\',tt_content.fe_group) OR FIND_IN_SET(\'-1\',tt_content.fe_group))) ';
		}
		else {
			self::$baseConditionForTTContent = 'WHERE (tt_content.deleted=0 AND tt_content.t3ver_state<=0 AND tt_content.hidden=0 AND tt_content.starttime<=###NOW### AND (tt_content.endtime=0 OR tt_content.endtime>###NOW###) AND (tt_content.fe_group=\'\' OR tt_content.fe_group IS NULL OR tt_content.fe_group=\'0\' OR (tt_content.fe_group LIKE \'%,0,%\' OR  tt_content.fe_group LIKE \'0,%\' OR tt_content.fe_group LIKE \'%,0\' OR tt_content.fe_group=\'0\') OR (tt_content.fe_group LIKE \'%,-1,%\' OR  tt_content.fe_group LIKE \'-1,%\' OR tt_content.fe_group LIKE \'%,-1\' OR tt_content.fe_group=\'-1\'))) ';
		}
		self::$fullConditionForTTContent = self::$baseConditionForTTContent . 'AND ' . self::$baseLanguageConditionForTTContent . self::$baseWorkspaceConditionForTTContent;
	}

	/**
	 * Parse and rebuild a simple SELECT query
	 *
	 * @test
	 */
	public function simpleSelectQuery() {
			// Replace time marker by time used for starttime and endtime enable fields
		$condition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], self::$fullConditionForTTContent);
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
			// Replace time marker by time used for starttime and endtime enable fields
		$condition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], self::$fullConditionForTTContent);
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
			// Replace time marker by time used for starttime and endtime enable fields
		$condition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], self::$fullConditionForTTContent);
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
	public function selectQueryWithUidAsAlias() {
			// Language condition does not apply when DISTINCT is used
		$condition = self::$baseConditionForTTContent . self::$baseWorkspaceConditionForTTContent;
			// Replace time marker by time used for starttime and endtime enable fields
		$condition = str_replace('###NOW###', $GLOBALS['SIM_ACCESS_TIME'], $condition);
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