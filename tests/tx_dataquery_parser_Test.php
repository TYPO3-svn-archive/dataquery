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
 * Testcase for the Data Query query parser
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_parser_Test extends tx_phpunit_testcase {
	/**
	 * Provides fields to test for them being text or not
	 *
	 * @return array
	 */
	public function tablesAndFieldsProvider() {
		$fields = array(
				// Text (single line) field
			'tt_content.header' => array(
				'table' => 'tt_content',
				'field' => 'header',
				'result' => TRUE
			),
				// Text (multi-line) field
			'tt_content.bodytext' => array(
				'table' => 'tt_content',
				'field' => 'bodytext',
				'result' => TRUE
			),
				// No TCA, will default to be considered a text field
			'tt_content.crdate' => array(
				'table' => 'tt_content',
				'field' => 'bodytext',
				'result' => TRUE
			),
				// Date and time, not a text field
			'tt_content.starttime' => array(
				'table' => 'tt_content',
				'field' => 'starttime',
				'result' => FALSE
			),
				// Integer, not a date field
			'tt_content.CType' => array(
				'table' => 'tt_content',
				'field' => 'CType',
				'result' => FALSE
			),
		);
		return $fields;
	}

	/**
	 * Test the text detection routine
	 *
	 * @param string $table Name of the table
	 * @param string $field Name of the field
	 * @param boolean $result The expected result
	 * @test
	 * @dataProvider tablesAndFieldsProvider
	 */
	public function detectTextField($table, $field, $result) {
		/** @var tx_dataquery_parser $parser */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$this->assertEquals(
			$parser->isATextField(
				$table,
				$field
			),
			$result
		);
	}
}
?>