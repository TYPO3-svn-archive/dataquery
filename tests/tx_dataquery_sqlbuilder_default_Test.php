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
	 * Parse and rebuild a SELECT query with an explicit JOIN and fields forced to another table
	 *
	 * @test
	 */
	public function selectQueryWithJoin() {
			// Replace markers in the condition
		$condition = self::finalizeCondition(self::$fullConditionForTable);
		$conditionForPages = str_replace('tt_content.', 'pages.', $condition);
		$additionalSelectFields = $this->prepareAdditionalFields('tt_content');
		$expectedResult = 'SELECT tt_content.uid, tt_content.header, pages.title AS tt_content$title, tt_content.pid, pages.uid AS pages$uid, pages.pid AS pages$pid, tt_content.sys_language_uid' . $additionalSelectFields . ' FROM tt_content AS tt_content INNER JOIN pages AS pages ON pages.uid = tt_content.pid AND ' . $conditionForPages . ' WHERE ' . $condition;

			/** @var $parser tx_dataquery_parser */
		$parser = t3lib_div::makeInstance('tx_dataquery_parser');
		$query = 'SELECT uid,header,pages.title AS tt_content.title FROM tt_content INNER JOIN pages ON pages.uid = tt_content.pid';
		$parser->parseQuery($query);
		$parser->setProviderData($this->settings);
		$parser->addTypo3Mechanisms();
		$actualResult = $parser->buildQuery();

		$this->assertEquals($expectedResult, $actualResult);
	}
}
?>