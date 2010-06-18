<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008-2010 Francois Suter (Cobweb) <typo3@cobweb.ch>
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
 * Wizard for checking the validity and the results of a SQL query
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_wizards_Check {

	/**
	 * This method renders the wizard itself
	 *
	 * @param	array			$PA: parameters of the field
	 * @param	t3lib_TCEforms	$fObj: calling object (TCEform)
	 * @return	string			HTML for the wizard
	 */
	public function render($PA, t3lib_TCEforms $fObj) {
			/**
			 * @var	t3lib_PageRenderer	$pageRenderer
			 */
		$pageRenderer = $GLOBALS['SOBE']->doc->getPageRenderer();
			// Load the necessary JavaScript
		$pageRenderer->addJsFile(t3lib_extMgm::extRelPath('dataquery') . 'res/js/check_wizard.js');
			// Load some localized labels
		$fObj->additionalJS_pre[] = '
			var TX_DATAQUERY = {
				labels : {
					"debugTab" : "' . $GLOBALS['LANG']->sL('LLL:EXT:dataquery/locallang.xml:wizard.check.debugTab') . '",
					"previewTab" : "' . $GLOBALS['LANG']->sL('LLL:EXT:dataquery/locallang.xml:wizard.check.previewTab') . '"
				}
			};
		';
			// First of all render the button that will show/hide the rest of the wizard
		$wizard = '';
			// Assemble the base HTML for the wizard
		$wizard .= '<div id="tx_dataquery_wizardContainer">';
		$wizard .= '<div id="queryDebug">';
		$wizard .= '<p><input type="button" name="debugButton" value="' . $GLOBALS['LANG']->sL('LLL:EXT:dataquery/locallang.xml:wizard.check.debugQuery') . '" /></p>';
		$wizard .= '</div>';
		$wizard .= '<div id="queryPreview">';
		$wizard .= '<p><input type="button" name="previewButton" value="' . $GLOBALS['LANG']->sL('LLL:EXT:dataquery/locallang.xml:wizard.check.previewQuery') . '" /></p>';
		$wizard .= '</div>';
		$wizard .= '</div>';
		return $wizard;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/wizards/class.tx_dataquery_wizards_check.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/wizards/class.tx_dataquery_wizards_check.php']);
}

?>