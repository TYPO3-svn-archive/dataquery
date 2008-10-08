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
		$subtables = $this->sqlParser->getSubtablesNames();
		$numSubtables = count($subtables);
		$allTables = $subtables;
		array_push($allTables, $this->mainTable);
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

		// Execute the query
		$res = $GLOBALS['TYPO3_DB']->sql_query($query);
		// Make a first loop over all the records
		// Apply an offset and limit, if any and if not already done in the query itself
		// If the limit was already applied, set it to 0 so that all records are taken
//		if ($this->sqlParser->isLimitAlreadyApplied()) {
//			$limit = 0;
//			$offset = 0;
//		}
		// If the limit was not already applied, get it from the filter
//		else {
			$limit = (isset($this->filter['limit']['max'])) ? $this->filter['limit']['max'] : 0;
			if ($limit > 0) {
				$offset = $limit * ((isset($this->filter['limit']['offset'])) ? $this->filter['limit']['offset'] : 0);
				if ($offset < 0) $offset = 0;
			}
			else {
				$offset = 0;
			}
//		}
//t3lib_div::debug(array('offset' => $offset, 'limit' => $limit));

		// Initialise array for storing records and uid's per table
		$rows = array($this->mainTable => array(0 => array()));
		$uids = array($this->mainTable => array());
		if ($numSubtables > 0) {
			foreach ($subtables as $table) {
				$rows[$table] = array();
				$uids[$table] = array();
			}
		}
		// Loop on all records to sort them by table. This can be seen as "de-JOINing" the tables.
		// This is necessary for such operations as overlays. When overlays are done, tables will be joined again
		// but within the format of Standardised Data Structure
		$oldUID = 0;
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$currentUID = $row['uid'];
			// If we're not handling the same main record as before, perform some initialisations
			if ($currentUID != $oldUID) {
				if ($numSubtables > 0) {
					foreach ($subtables as $table) {
						$rows[$table][$currentUID] = array();
					}
				}
			}
			$recordsPerTable = array();
			foreach ($row as $fieldName => $fieldValue) {
				$fieldNameParts = t3lib_div::trimExplode('$', $fieldName);
				// The query contains no joined table
				// All fields belong to the main table
				if ($numSubtables == 0) {
					$fieldName = (isset($fieldNameParts[1])) ? $fieldNameParts[1] : $fieldNameParts[0];
					$recordsPerTable[$this->mainTable][$fieldName] = $fieldValue;
				}
				// There are multiple tables
				else {
					// Field belongs to a subtable
					if (in_array($fieldNameParts[0], $subtables)) {
						$subtableName = $fieldNameParts[0];
						if (isset($fieldValue)) {
							$recordsPerTable[$subtableName][$fieldNameParts[1]] = $fieldValue;
							// If the field is the uid field, store it in the list of uid's for the given subtable
							if ($fieldNameParts[1] == 'uid') {
								$uids[$subtableName][] = $fieldValue;
							}
						}
					}
					// Else assume the field belongs to the main table
					else {
						$fieldName = (isset($fieldNameParts[1])) ? $fieldNameParts[1] : $fieldNameParts[0];
						$recordsPerTable[$this->mainTable][$fieldName] = $fieldValue;
					}
				}
			}
			// If we're not handling the same main record as before, store the current information for the main table
			if ($currentUID != $oldUID) {
				$uids[$this->mainTable][] = $currentUID;
				$rows[$this->mainTable][0][] = $recordsPerTable[$this->mainTable];
				$oldUID = $currentUID;
			}
			// Store information for each subtable
			if ($numSubtables > 0) {
				foreach ($subtables as $table) {
					$rows[$table][$currentUID][] = $recordsPerTable[$table];
				}
			}
		}
//t3lib_div::debug($rows);
//t3lib_div::debug($uids);

		// If localisation is active and the current language is not the default one,
		// get the overlays for all tables for which localisation by overlays is needed
		if ($GLOBALS['TSFE']->sys_language_content > 0) {
			$overlays = array();
			foreach ($allTables as $table) {
				if ($this->sqlParser->mustHandleLanguageOverlay($table)) {
					$overlays[$table] = tx_overlays::getOverlayRecords($this->sqlParser->getTrueTableName($table), $uids[$table], $GLOBALS['TSFE']->sys_language_content);
				}
			}
		}
//t3lib_div::debug($overlays['tt_content']);

		// Loop on all records of the main table, applying overlays if needed
		// Apply limit and offset
		$counter = 0;
		$totalCounter = 0;
//		$oldUID = 0;
		$mainRecords = array();
		// Perform overlays only if language is not default and if necessary for table
		$doOverlays = ($GLOBALS['TSFE']->sys_language_content > 0) & $this->sqlParser->mustHandleLanguageOverlay($this->mainTable);
		foreach ($rows[$this->mainTable][0] as $row) {
//			$currentUID = $row['uid'];
			if ($doOverlays) {
				if (isset($overlays[$this->mainTable][$row['uid']][$row['pid']])) {
					$row = tx_overlays::overlaySingleRecord($table, $row, $overlays[$this->mainTable][$row['uid']][$row['pid']]);
				}
					// No overlay exists
				else {
						// Take original record, only if non-translated are not hidden, or if language is [All]
					if ($GLOBALS['TSFE']->sys_language_contentOL == 'hideNonTranslated' && $row[$tableCtrl['languageField']] != -1) {
						continue; // Skip record
					}
				}
			}
			// Get only those records that are after the offset and within the limit
			if ($counter >= $offset && ($limit == 0 || ($limit > 0 && $counter - $offset < $limit))) {
				$counter++;
				$mainRecords[] = $row;
//t3lib_div::debug(array('counter' => $counter, 'offset' => $offset, 'limit' => $limit, 'check' => ($counter - $offset)));
			}
			// If the offset has not been reached yet, just increase the counter
			elseif ($counter < $offset) {
				$counter++;
			}
				// If there was a limit and it is passed, stop looping on the records
			if ($limit > 0 && $counter - $offset >= $limit) {
//				break;
			}
			$totalCounter++;
			// Increment the counter only if the main id has changed
			// This way we can indeed capture "limit" records of the main table of the query
//			if ($currentUID != $oldUID) {
//				$oldUID = $currentUID;
//				$counter++;
//			}
		}
//t3lib_div::debug($mainRecords);

		// Prepare the header parts for all tables
		$headers = array();
		foreach ($allTables as $table) {
			$headers[$table] = array();
			foreach ($tableAndFieldLabels[$table]['fields'] as $key => $label) {
				$headers[$table][$key] = array('label' => $label);
	        }
		}

		// Now loop on the filtered recordset of the main table and join it again to all its subtables
		// Overlays are applied to subtables as needed
		$uidList = array();
		$fullRecords = array();
		foreach ($mainRecords as $aRecord) {
			$uidList[] = $aRecord['uid'];
			$theFullRecord = $aRecord;
			$theFullRecord['sds:subtables'] = array();
			// Check if there are any subtables in the query
			if ($numSubtables > 0) {
				foreach ($subtables as $table) {
					// Check if there are any subrecords for this record
					if (isset($rows[$table][$aRecord['uid']])) {
						$numSubrecords = count($rows[$table][$aRecord['uid']]);
						if ($numSubrecords > 0) {
							$sublimit = $this->sqlParser->getSubTableLimit($table);
							$subcounter = 0;
							// Perform overlays only if language is not default and if necessary for table
							$doOverlays = ($GLOBALS['TSFE']->sys_language_content > 0) & $this->sqlParser->mustHandleLanguageOverlay($table);
							$subRecords = array();
							$subUidList = array();
							// Loop on all subrecords and perform overlays if necessary
							foreach ($rows[$table][$aRecord['uid']] as $subRow) {
								if ($doOverlays) {
									if (isset($overlays[$table][$subRow['uid']][$subRow['pid']])) {
										$subRow = tx_overlays::overlaySingleRecord($table, $subRow, $overlays[$table][$subRow['uid']][$subRow['pid']]);
									}
										// No overlay exists
									else {
										// Take original record, only if non-translated are not hidden, or if language is [All]
										if ($GLOBALS['TSFE']->sys_language_contentOL == 'hideNonTranslated' && $subRow[$GLOBALS['TCA'][$table]['ctrl']['languageField']] != -1) {
											continue; // Skip record
										}
									}
								}
								// No overlays
								else {
									// Make sure there's actually something in the JOINed record
									// (it might be empty in case of LEFT JOIN)
									if (!isset($subRow['uid'])) {
										continue;
									}
								}
								if ($sublimit == 0 || $subcounter < $sublimit) {
									$subRecords[] = $subRow;
									$subUidList[] = $subRow['uid'];
								}
								elseif ($sublimit != 0 || $subcounter >= $sublimit) {
									break;
								}
								$subcounter++;
							}
							// If there are indeed items, add the subtable to the record
							$numItems = count($subUidList);
							if ($numItems > 0) {
								$theFullRecord['sds:subtables'][] = array(
																		'name' => $table,
																		'count' => $numItems,
																		'uidList' => implode(',' , $subUidList),
																		'header' => $headers[$table],
																		'records' => $subRecords
																	);
							}
						}
					}
				}
			}
			$fullRecords[] = $theFullRecord;
		}
		$dataStructure = array(
							'name' => $this->mainTable,
							'count' => count($fullRecords),
							'totalCount' => $totalCounter,
							'uidList' => implode(',', $uidList),
							'header' => $headers[$this->mainTable],
							'records' => $fullRecords
						);

// Hook for post-processing the data structure

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructure'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructure'] as $className) {
				$postProcessor = &t3lib_div::getUserObj($className);
				$dataStructure = $postProcessor->postProcessDataStructure($dataStructure, $this);
			}
		}
//t3lib_div::debug($dataStructure);
		return $dataStructure;
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