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
 * Testcase for the Data Query query builder in the Draft workspace
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_sqlbuilder_Workspace_Test extends tx_dataquery_sqlbuilder_Test {
	/**
	 * @var	integer	ID of the current workspace
	 */
	protected $saveWorkspaceValue;

	/**
	 * Set up the workspace preview environment
	 */
	public function setUp() {
		parent::setUp();

			// Add version state to the SELECT fields
		$this->additionalFields[] = 't3ver_state';

			// Activate versioning preview
		$GLOBALS['TSFE']->sys_page->versioningPreview = TRUE;
			// Save current workspace (should the LIVE one really) and switch to Draft
		$this->saveWorkspaceValue = $GLOBALS['BE_USER']->workspace;
		$GLOBALS['BE_USER']->workspace = -1;

			// The base condition is different in the case of workspaces, because
			// versioning preview deactivates most of the enable fields check
		self::$baseConditionForTTContent = 'WHERE tt_content.deleted=0 ';
			// Reset language condition which might have been altered by language unit test
		self::$baseLanguageConditionForTTContent = 'AND (tt_content.sys_language_uid IN (0,-1)) ';
			// Add workspace condition, assuming Draft workspace (= -1)
		self::$baseWorkspaceConditionForTTContent = 'AND (tt_content.t3ver_state <= 0 AND tt_content.t3ver_oid = 0) OR (tt_content.t3ver_state = 1 AND tt_content.t3ver_wsid = -1) OR (tt_content.t3ver_state = 3 AND tt_content.t3ver_wsid = -1) ';
		self::$fullConditionForTTContent = self::$baseConditionForTTContent . self::$baseLanguageConditionForTTContent . self::$baseWorkspaceConditionForTTContent;
	}

	/**
	 * Reset environment
	 */
	public function tearDown() {
		parent::tearDown();
		$GLOBALS['TSFE']->sys_page->versioningPreview = FALSE;
		$GLOBALS['BE_USER']->workspace = $this->saveWorkspaceValue;
	}
}
?>