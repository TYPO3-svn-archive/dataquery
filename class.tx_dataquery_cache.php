<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Francois Suter (Cobweb) <typo3@cobweb.ch>
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
* $Id: class.tx_dataquery_parser.php 13276 2008-10-22 19:30:46Z francois $
***************************************************************/
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   60: class tx_dataquery_parser
 *   78:     public function parseQuery($query)
 *  291:     public function getLocalizedLabels($language = '')
 *  367:     public function addTypo3Mechanisms($settings)
 *  466:     public function addFilter($filter)
 *  495:     public function addIdList($idList)
 *  535:     public function buildQuery()
 *  578:     public function parseSearch($searchParameters)
 *  626:     public function addWhereClause($clause)
 *  638:     public function getMainTableName()
 *  648:     public function getSubtablesNames()
 *  657:     public function hasMergedResults()
 *  668:     public function mustHandleLanguageOverlay($table)
 *
 * TOTAL FUNCTIONS: 12
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

/**
 * Cache management class for extension "dataquery"
 *
 * @author	Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_dataquery
 */
class tx_dataquery_cache {

	/**
	 * This method is used to clear the dataquery for selected pages only
	 *
	 * @param	array	$parameters: parameters passed by TCEmain, including the pages to clear the cache for
	 * @param	object	$pObj: reference to the calling TCEmain object
	 * @return	void
	 */
	public function clearCache($parameters, $pObj) {
		// Clear the dataquery cache for all the pages passed to this method
		if (isset($parameters['pageIdArray']) && count($parameters['pageIdArray']) > 0) {
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_dataquery_cache', 'page_id IN ('.implode(',', $parameters['pageIdArray']).')');
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_cache.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_cache.php']);
}

?>