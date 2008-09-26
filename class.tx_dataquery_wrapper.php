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
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   62: class tx_dataquery_wrapper extends tx_basecontroller_providerbase
 *   72:     public function __construct()
 *   81:     public function getData()
 *  183:     protected function loadQuery()
 *  209:     public function getMainTableName()
 *  220:     public function getProvidedDataStructure()
 *  230:     public function providesDataStructure($type)
 *  239:     public function getAcceptedDataStructure()
 *  249:     public function acceptsDataStructure($type)
 *  260:     public function loadData($data)
 *  270:     public function getDataStructure()
 *  280:     public function setDataStructure($structure)
 *  290:     public function setDataFilter($filter)
 *  301:     public function getTablesAndFields($language = '')
 *
 * TOTAL FUNCTIONS: 13
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

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
	protected $sqlParser; // Local instance of the SQL parser class (tx_dataquery_parser)
	protected $structure; // Input standardised data structure

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

		$this->sqlParser->addTypo3Mechanisms($this->providerData);

// Assemble filters, if defined

		if (is_array($this->filter)) $this->sqlParser->addFilter($this->filter);

// Use idList from input SDS, if defined

		if (is_array($this->structure)) $this->sqlParser->addIdList($this->structure['uidListWithTable']);

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

	/**
	 * This method loads the current query's details from the database and starts the parser
	 *
	 * @return	void
	 */
	protected function loadQuery() {
		$this->sqlParser = t3lib_div::makeInstance('tx_dataquery_parser');
		$this->sqlParser->parseQuery($this->providerData['sql_query']);
    }

    /**
	 * This method returns the name of the main table of the query,
	 * which is the table name that appears in the FROM clause, or the alias, if any
	 *
	 * @return	string		main table name
	 */
	public function getMainTableName() {
		return $this->mainTable;
	}

// Data Provider interface methods

	/**
	 * This method returns the type of data structure that the Data Provider can prepare
	 *
	 * @return	string		type of the provided data structure
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
	 * This method returns the type of data structure that the Data Provider can receive as input
	 *
	 * @return	string		type of used data structures
	 */
	public function getAcceptedDataStructure() {
		return tx_basecontroller::$idlistStructureType;
	}

	/**
	 * This method indicates whether the Data Provider can use as input the type of data structure requested or not
	 *
	 * @param	string		$type: type of data structure
	 * @return	boolean		true if it can use the requested type, false otherwise
	 */
	public function acceptsDataStructure($type) {
		return $type == tx_basecontroller::$idlistStructureType;
	}

	/**
	 * This method assembles the data structure and returns it
	 *
	 * @return	array		standardised data structure
	 */
	public function getDataStructure() {
		return $this->getData();
	}

	/**
	 * This method is used to pass a data structure to the Data Provider
	 *
	 * @param	array		$structure: standardised data structure
	 * @return	void
	 */
	public function setDataStructure($structure) {
		if (is_array($structure)) $this->structure = $structure;
	}

	/**
	 * This method loads the query and gets the list of tables and fields,
	 * complete with localized labels
	 *
	 * @param	string		$language: 2-letter iso code for language
	 * @return	array		list of tables and fields
	 */
	public function getTablesAndFields($language = '') {
		$this->loadQuery();
		return $this->sqlParser->getLocalizedLabels($language);
    }
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_wrapper.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_wrapper.php']);
}

?>