<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Fabien Udriot <fabien.udriot@ecodev.ch>
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
*
* $Id$
***************************************************************/

/**
 * This class answers to AJAX calls from the 'dataquery' extension
 *
 * @author		Fabien Udriot <fabien.udriot@ecodev.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 */
class tx_dataquery_Ajax {

	/**
	 * This method returns the parsed query through dataquery parser
	 * or error messages from exceptions should any have been thrown
	 * during query parsin
	 *
	 * @param	array		$params: empty array (yes, that's weird but true)
	 * @param	TYPO3AJAX	$ajaxObj: back-reference to the calling object
	 * @return	void
	 */
	public function validate($params, TYPO3AJAX $ajaxObj) {
		$severity = t3lib_FlashMessage::OK;
		$message = '';
		$title = '';

			// Try parsing and building the query
		try {
				// Get the query to parse from the GET/POST parameters
			$query = t3lib_div::_GP('query');
				// Create an instance of the parser, parse and build
			$parser = t3lib_div::makeInstance('tx_dataquery_parser');
			$parser->parseQuery($query);
			$parsedQuery = $parser->buildQuery();
				// The query building completed, issue success message
			$title = $GLOBALS['LANG']->sL('LLL:EXT:dataquery/locallang.xml:query.success');
			$message = $parsedQuery;
		}
		catch(Exception $e) {
				// The query parsing failed, issue error message
			$severity = t3lib_FlashMessage::ERROR;
			$title = $GLOBALS['LANG']->sL('LLL:EXT:dataquery/locallang.xml:query.failure');
			$message = $e->getMessage();
		}
			// Render result as flash message
		$flashMessage = t3lib_div::makeInstance(
			't3lib_FlashMessage',
			$message,
			$title,
			$severity
		);
		$ajaxObj->addContent('dataquery', $flashMessage->render());
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_ajax.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_ajax.php']);
}
?>