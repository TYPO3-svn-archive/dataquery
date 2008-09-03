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
* $Id$
***************************************************************/

require_once(t3lib_extMgm::extPath('dataquery', 'class.tx_dataquery_parser.php'));
require_once(t3lib_extMgm::extPath('basecontroller', 'services/class.tx_basecontroller_providerbase.php'));

/**
 * Wrapper for data query
 * This class is used to get the results of a specific data query
 *
 * @author	Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_dataquery
 */
class tx_dataquery_wrapper extends tx_basecontroller_providerbase {
	public $extKey = 'dataquery';
	protected $configuration; // Extension configuration
	protected $mainTable; // Store the name of the main table of the query
	protected $table; // Name of the table where the details about the data query are stored
	protected $uid; // Primary key of the record to fetch for the details
	protected $sqlParser; // Local instance of the SQL parser class (tx_dataquery_parser)
	protected $filter; // Data Filter structure

	public function __construct() {
		$this->configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
	}

	/**
	 * This method is used to get the data for the given query and return it in a standardised format
	 *
	 * @return	mixed		array containing the data structure or false if it failed
	 */
	public function getData() {
		$this->loadQuery();
		$this->mainTable = $this->sqlParser->getMainTableName();
		$tableAndFieldLabels = $this->sqlParser->getLocalizedLabels($language);

// Add the SQL conditions for the selected TYPO3 mechanisms

		if (!empty($dataQuery['t3_mechanisms'])) $this->sqlParser->addTypo3Mechanisms($dataQuery['t3_mechanisms']);

// Assemble filters

		$this->sqlParser->addFilters($this->filter);

// Build the complete query

		$query = $this->sqlParser->buildQuery();
		if ($this->configuration['debug'] || TYPO3_DLOG) t3lib_div::devLog($query, $this->extKey);

		$res2 = $GLOBALS['TYPO3_DB']->sql_query($query);
		$records = array('name' => $this->mainTable, 'records' => array());
		$records['header'] = array();
		foreach ($tableAndFieldLabels[$this->mainTable]['fields'] as $key => $label) {
			$records['header'][$key] = array('label' => $label);
        }
		if (!$this->sqlParser->hasMergedResults()) {
			$subtables = $this->sqlParser->getSubtablesNames();
			$oldUID = 0;
			$mainRecords = array();
			$mainUIDs = array();
			$subRecords = array();
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res2)) {
				$currentUID = $row['uid'];
				if ($currentUID != $oldUID) {
					$mainRecords[$currentUID] = array();
					$mainUIDs[] = $currentUID;
					$subRecords[$currentUID] = array();
					$subUIDs[$currentUID] = array();
					$subRecordsCounter = 0;
					$oldUID = $currentUID;
				}
				foreach ($row as $fieldName => $fieldValue) {
					$fieldNameParts = t3lib_div::trimExplode('$', $fieldName);
					if (in_array($fieldNameParts[0], $subtables)) {
						$subtableName = $fieldNameParts[0];
						if (!isset($subRecords[$currentUID][$subtableName])) {
							$subRecords[$currentUID][$subtableName] = array();
							$subUIDs[$currentUID][$subtableName] = array();
						}
						if (isset($fieldValue)) {
							$subRecords[$currentUID][$subtableName][$subRecordsCounter][$fieldNameParts[1]] = $fieldValue;
							if ($fieldNameParts[1] == 'uid') {
								$subUIDs[$currentUID][$subtableName][] = $fieldValue;
							}
						}
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
					foreach ($subRecords[$uid] as $name => $data) {
						if (count($data) > 0) {
							if (!isset($theRecord['sds:subtables'])) {
								$theRecord['sds:subtables'] = array();
							}
							$subtableHeader = array();
							foreach ($tableAndFieldLabels[$name]['fields'] as $key => $label) {
								$subtableHeader[$key] = array('label' => $label);
							}
							$theRecord['sds:subtables'][] = array('name' => $name, 'count' => count($data), 'uidList' => implode(',', $subUIDs[$uid][$name]), 'header' => $subtableHeader, 'records' => $data);
						}
					}
				}
				$records['records'][] = $theRecord;
			}
		}
		else {
			$mainUIDs = array();
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res2)) {
				$records['records'][] = $row;
				$mainUIDs[] = $row['uid'];
			}
		}
		$records['uidList'] = implode(',', $mainUIDs);
		$records['count'] = count($records['records']);
//t3lib_div::debug($records);
		return $records;
	}

	protected function loadQuery() {
		$tableTCA = $GLOBALS['TCA'][$this->table];
		$whereClause = "uid = '".$this->uid."'";
		if (isset($GLOBALS['TSFE'])) {
			$whereClause .= $GLOBALS['TSFE']->sys_page->enableFields($this->table, $GLOBALS['TSFE']->showHiddenRecords);
		}
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('sql_query, t3_mechanisms', $this->table, $whereClause);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
			throw new Exception('No query found');
		}
		else {

// Get query and parse it

			$dataQuery = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$this->sqlParser = t3lib_div::makeInstance('tx_dataquery_parser');
			$this->sqlParser->parseQuery($dataQuery['sql_query']);
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

// Data Provider interface methods

	/**
	 * This method returns the type of data structure that the Data Provider can prepare
	 *
	 * @return	string	type of the provided data structure
	 */
	public function getProvidedDataStructure() {
		return tx_basecontroller::$recordsetStructureType;
	}

	/**
	 * This method indicates whether the Data Provider can create the type of data structure requested or not
	 *
	 * @param	string		$type: type of data structure
	 * @return	boolean		true if it can handle the requested type, false otherwise
	 */
	public function providesDataStructure($type) {
		return $type == tx_basecontroller::$recordsetStructureType;
	}

	/**
	 * This method is used to load the details about the Data Provider passing it whatever data it needs
	 * This will generally be a table name and a primary key value
	 *
	 * @param	array	$data: Data for the Data Provider
	 * @return	void
	 */
	public function loadProviderData($data) {
		$this->table = $data['table'];
		$this->uid = $data['uid'];
	}

	/**
	 * This method assembles the data structure and returns it
	 *
	 * @return	array	standardised data structure
	 */
	public function getDataStructure() {
		return $this->getData();
	}

	/**
     * This method loads the query and gets the list of tables and fields,
     * complete with localized labels
     *
     * @param	string	$language: 2-letter iso code for language
     *
     * @return	array	list of tables and fields
     */
	public function getTablesAndFields($language = '') {
		$this->loadQuery();
		return $this->sqlParser->getLocalizedLabels($language);
    }

	/**
	 * This method is used to pass a Data Filter structure to the Data Consumer
	 *
	 * @param	DataFilter	$filter: Data Filter structure
	 * @return	void
	 */
	public function setDataFilter($filter) {
		if (is_array($filter)) $this->filter = $filter;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_wrapper.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_wrapper.php']);
}

?>