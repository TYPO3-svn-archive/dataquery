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
* $Id: class.tx_dataquery_wrapper.php 3939 2008-06-04 10:27:36Z fsuter $
***************************************************************/

require_once(t3lib_extMgm::extPath('dataquery','class.tx_dataquery_parser.php'));

/**
 * Wrapper for data query
 * This class is used to get the results of a specific data query
 *
 * @author	Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_dataquery
 */
class tx_dataquery_wrapper {
	var $extKey = 'dataquery';
	var $configuration; // Extension configuration
	var $mainTable; // Store the name of the main table of the query

	public function __construct() {
		$this->configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
	}

	/**
	 * This method is used to get the data for the given query and return it in a standardised format
	 *
	 * @param	integer		uid of a data query
	 * @param	arrray		key-value pairs of search parameters
	 *
	 * @return	mixed		array containing the data or false if it failed
	 */
	public function getData($uid, $searchParameters = '') {
		global $TCA;

		if (empty($uid)) {
			return false;
		}
		else {
			$tableName = 'tx_dataquery_queries';
			$tableTCA = $TCA['tx_dataquery_queries'];
			$whereClause = "uid = '".$uid."' AND ".$tableTCA['ctrl']['delete']." = '0'";
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('sql_query,t3_mechanisms',$tableName,$whereClause);
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
				return false;
			}
			else {

// Get query and parse it

				$dataQuery = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$sqlParser = t3lib_div::makeInstance('tx_dataquery_parser');
				$sqlParser->parseQuery($dataQuery['sql_query']);
				$this->mainTable = $sqlParser->getMainTableName();

// Add the SQL conditions for the selected TYPO3 mechanisms

				if (!empty($dataQuery['t3_mechanisms'])) $sqlParser->addTypo3Mechanisms($dataQuery['t3_mechanisms']);

// Assemble search query elements

				$sqlParser->parseSearch($searchParameters);

// Build the complete query

				$query = $sqlParser->buildQuery();
				if ($this->configuration['debug']) t3lib_div::devLog($query, $this->extKey);

				$res2 = $GLOBALS['TYPO3_DB']->sql_query($query);
				$records = array('name' => $this->mainTable, 'records' => array());
				if (!$sqlParser->hasMergedResults()) {
					$subtables = $sqlParser->getSubtablesNames();
					$oldUID = 0;
					$mainRecords = array();
					$subRecords = array();
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res2)) {
						$currentUID = $row['mainid'];
						if ($currentUID != $oldUID) {
							$mainRecords[$currentUID] = array();
							$subRecords[$currentUID] = array();
							$subRecordsCounter = 0;
							$oldUID = $currentUID;
						}
						foreach ($row as $fieldName => $fieldValue) {
							$fieldNameParts = t3lib_div::trimExplode('$', $fieldName);
							if (in_array($fieldNameParts[0], $subtables)) {
								$subtableName = $fieldNameParts[0];
								if (!isset($subRecords[$currentUID][$subtableName])) {
									$subRecords[$currentUID][$subtableName] = array();
								}
								$subRecords[$currentUID][$subtableName][$subRecordsCounter][$fieldNameParts[1]] = $fieldValue;
							}
							else {
								$fieldName = (isset($fieldNameParts[1])) ? $fieldNameParts[1] : $fieldNameParts[0];
								$mainRecords[$currentUID][$fieldName] = $fieldValue;
							}
						}
						$subRecordsCounter++;
					}
					foreach ($mainRecords as $uid => $theRecord) {
						if (isset($subRecords[$uid])) {
							$theRecord['subtables'] = array();
							foreach ($subRecords[$uid] as $name => $data) {
								$theRecord['subtables'][] = array('name' => $name, 'records' => $data);
							}
						}
						$records['records'][] = $theRecord;
					}
				}
				else {
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res2)) {
						$records['records'][] = $row;
					}
				}
//t3lib_div::debug($records);
				return $records;
			}
		}
	}

	/**
	 * This method returns the name of the main table of the query,
	 * which is the table name that appears in the FROM clause, or the alias, if any
	 *
	 * @return	string	main table name
	 */
	public function getMainTableName() {
		return $this->mainTable;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_wrapper.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_wrapper.php']);
}

?>