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

require_once(t3lib_extMgm::extPath('dataquery', 'class.tx_dataquery_sqlparser.php'));

/**
 * Testcase for the Data Query SQL parser
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_sqlparser_Test extends tx_phpunit_testcase {

	/**
	 * Test a simple SELECT query
	 *
	 * @test
	 */
	public function simpleSelectQuery() {
		/**
		 * @var tx_dataquery_sqlparser	$parser
		 */
		$parser = t3lib_div::makeInstance('tx_dataquery_sqlparser');
		$query = 'SELECT * FROM tt_content';
		$expectedResult = array(
			'DISTINCT' => FALSE,
			'SELECT' => array(),
			'FROM' => array('table' => 'tt_content', 'alias' => 'tt_content'),
			'JOIN' => array(),
			'WHERE' => array(),
			'ORDER BY' => array(),
			'GROUP BY' => array()
		);
			// The wildcard will get expanded to include all fields for the given table
			// So get them and add them to the expected results
		$fieldInfo = $GLOBALS['TYPO3_DB']->admin_get_fields($table);
		$fields = array_keys($fieldInfo);
			// Add all fields to the query structure
		foreach ($fields as $aField) {
			$expectedResult['SELECT'][] = array(
				'table' => 'tt_content',
				'tableAlias' => 'tt_content',
				'field' => $aField,
				'fieldAlias' => '',
				'function' => FALSE
			);
		}
		$actualResult = $parser->parseSQL($query);
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult->structure);
	}

	/**
	 * Test a SELECT query with function calls
	 *
	 * @test
	 */
	public function selectQueryWithDistinct() {
		/**
		 * @var tx_dataquery_sqlparser	$parser
		 */
		$parser = t3lib_div::makeInstance('tx_dataquery_sqlparser');
		$query = 'SELECT DISTINCT CType AS uid FROM tt_content';
		$expectedResult = array(
			'DISTINCT' => TRUE,
			'SELECT' => array(
				0 => array(
					'table' => 'tt_content',
					'tableAlias' => 'tt_content',
					'field' => 'CType',
					'fieldAlias' => 'uid',
					'function' => FALSE
				)
			),
			'FROM' => array('table' => 'tt_content', 'alias' => 'tt_content'),
			'JOIN' => array(),
			'WHERE' => array(),
			'ORDER BY' => array(),
			'GROUP BY' => array()
		);
		$actualResult = $parser->parseSQL($query);
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult->structure);
	}

	/**
	 * Test a SELECT query with function calls
	 *
	 * @test
	 */
	public function selectQueryWithFunctionCalls() {
		/**
		 * @var tx_dataquery_sqlparser	$parser
		 */
		$parser = t3lib_div::makeInstance('tx_dataquery_sqlparser');
		$query = 'SELECT uid, FROM_UNIXTIME(tstamp, \'%Y\') AS year, CONCAT(uid, \' in \', pid) FROM tt_content AS content';
		$expectedResult = array(
			'DISTINCT' => FALSE,
			'SELECT' => array(
				0 => array(
					'table' => 'tt_content',
					'tableAlias' => 'content',
					'field' => 'uid',
					'fieldAlias' => '',
					'function' => FALSE
				),
				1 => array(
					'table' => 'tt_content',
					'tableAlias' => 'content',
					'field' => 'FROM_UNIXTIME(tstamp, \'%Y\')',
					'fieldAlias' => 'year',
					'function' => TRUE
				),
				2 => array(
					'table' => 'tt_content',
					'tableAlias' => 'content',
					'field' => 'CONCAT(uid, \' in \', pid)',
					'fieldAlias' => 'function_2',
					'function' => TRUE
				)
			),
			'FROM' => array('table' => 'tt_content', 'alias' => 'content'),
			'JOIN' => array(),
			'WHERE' => array(),
			'ORDER BY' => array(),
			'GROUP BY' => array()
		);
		$actualResult = $parser->parseSQL($query);
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult->structure);
	}

	/**
	 * Test a SELECT query with an implicit join
	 *
	 * @test
	 */
	public function selectQueryWithImplicitJoin() {
		/**
		 * @var tx_dataquery_sqlparser	$parser
		 */
		$parser = t3lib_div::makeInstance('tx_dataquery_sqlparser');
		$query = 'SELECT t.uid, p.uid FROM tt_content AS t, pages AS p WHERE p.uid = t.pid';
		$expectedResult = array(
			'DISTINCT' => FALSE,
			'SELECT' => array(
				0 => array(
					'table' => 'tt_content',
					'tableAlias' => 't',
					'field' => 'uid',
					'fieldAlias' => '',
					'function' => FALSE
				),
				1 => array(
					'table' => 'pages',
					'tableAlias' => 'p',
					'field' => 'uid',
					'fieldAlias' => '',
					'function' => FALSE
				)
			),
			'FROM' => array('table' => 'tt_content', 'alias' => 't'),
			'JOIN' => array(
				'p' => array(
					'type' => 'inner',
					'table' => 'pages',
					'alias' => 'p',
					'on' => ''
				)
			),
			'WHERE' => array(
				0 => 'p.uid = t.pid'
			),
			'ORDER BY' => array(),
			'GROUP BY' => array()
		);
		$actualResult = $parser->parseSQL($query);
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult->structure);
	}

	/**
	 * Test a SELECT query with an explicit join
	 *
	 * @test
	 */
	public function selectQueryWithExplicitJoin() {
		/**
		 * @var tx_dataquery_sqlparser	$parser
		 */
		$parser = t3lib_div::makeInstance('tx_dataquery_sqlparser');
		$query = 'SELECT t.uid, p.uid FROM pages AS p LEFT JOIN tt_content AS t ON t.pid = p.uid';
		$expectedResult = array(
			'DISTINCT' => FALSE,
			'SELECT' => array(
				0 => array(
					'table' => 'tt_content',
					'tableAlias' => 't',
					'field' => 'uid',
					'fieldAlias' => '',
					'function' => FALSE
				),
				1 => array(
					'table' => 'pages',
					'tableAlias' => 'p',
					'field' => 'uid',
					'fieldAlias' => '',
					'function' => FALSE
				)
			),
			'FROM' => array('table' => 'pages', 'alias' => 'p'),
			'JOIN' => array(
				't' => array(
					'type' => 'left',
					'table' => 'tt_content',
					'alias' => 't',
					'on' => 't.pid = p.uid'
				)
			),
			'WHERE' => array(),
			'ORDER BY' => array(),
			'GROUP BY' => array()
		);
		$actualResult = $parser->parseSQL($query);
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult->structure);
	}

	/**
	 * Test a SELECT query with LIMIT defined as LIMIT x,y
	 *
	 * @test
	 */
	public function selectQueryWithImplicitLimitAndOffset() {
		/**
		 * @var tx_dataquery_sqlparser	$parser
		 */
		$parser = t3lib_div::makeInstance('tx_dataquery_sqlparser');
		$query = 'SELECT uid, header FROM tt_content LIMIT 10, 20';
		$expectedResult = array(
			'DISTINCT' => FALSE,
			'SELECT' => array(
				0 => array(
					'table' => 'tt_content',
					'tableAlias' => 'tt_content',
					'field' => 'uid',
					'fieldAlias' => '',
					'function' => FALSE
				),
				1 => array(
					'table' => 'tt_content',
					'tableAlias' => 'tt_content',
					'field' => 'header',
					'fieldAlias' => '',
					'function' => FALSE
				)
			),
			'FROM' => array('table' => 'tt_content', 'alias' => 'tt_content'),
			'JOIN' => array(),
			'WHERE' => array(),
			'ORDER BY' => array(),
			'GROUP BY' => array(),
			'LIMIT' => 20,
			'OFFSET' => 10
		);
		$actualResult = $parser->parseSQL($query);
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult->structure);
	}

	/**
	 * Test a SELECT query with LIMIT defined as LIMIT y OFFSET x
	 *
	 * @test
	 */
	public function selectQueryWithExplicitLimitAndOffset() {
		/**
		 * @var tx_dataquery_sqlparser	$parser
		 */
		$parser = t3lib_div::makeInstance('tx_dataquery_sqlparser');
		$query = 'SELECT uid, header FROM tt_content LIMIT 20 OFFSET 10';
		$expectedResult = array(
			'DISTINCT' => FALSE,
			'SELECT' => array(
				0 => array(
					'table' => 'tt_content',
					'tableAlias' => 'tt_content',
					'field' => 'uid',
					'fieldAlias' => '',
					'function' => FALSE
				),
				1 => array(
					'table' => 'tt_content',
					'tableAlias' => 'tt_content',
					'field' => 'header',
					'fieldAlias' => '',
					'function' => FALSE
				)
			),
			'FROM' => array('table' => 'tt_content', 'alias' => 'tt_content'),
			'JOIN' => array(),
			'WHERE' => array(),
			'ORDER BY' => array(),
			'GROUP BY' => array(),
			'LIMIT' => 20,
			'OFFSET' => 10
		);
		$actualResult = $parser->parseSQL($query);
			// Check if the "structure" part if correct
		$this->assertEquals($expectedResult, $actualResult->structure);
	}
}
?>