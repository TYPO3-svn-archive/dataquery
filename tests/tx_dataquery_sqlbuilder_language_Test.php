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
 * Testcase for the Data Query query builder with a non-default language
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_sqlbuilder_Language_Test extends tx_dataquery_sqlbuilder_Test {

	/**
	 * Set up a different language
	 */
	public function setUp() {
		parent::setUp();

			// Set a different language than default
		$GLOBALS['TSFE']->sys_language_content = 2;

			// Adapt language condition accordingly
		self::$baseLanguageConditionForTable = "(###TABLE###.sys_language_uid IN (0,-1) OR (###TABLE###.sys_language_uid = '2' AND ###TABLE###.l18n_parent = '0'))";
			// NOTE: markers are used instead of the corresponding conditions, because the setUp() method
			// is not invoked inside the data providers. Thus when using a data provider, it's not possible
			// to refer to the conditions defined via setUp()
		self::$fullConditionForTable = '###BASE_CONDITION### AND ###LANGUAGE_CONDITION### AND ###WORKSPACE_CONDITION###';
	}

	/**
	 * Reset environment
	 */
	public function tearDown() {
		parent::tearDown();
		$GLOBALS['TSFE']->sys_language_content = 0;
	}
}
?>