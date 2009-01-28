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
require_once(t3lib_extMgm::extPath('basecontroller', 'lib/class.tx_basecontroller_utilities.php'));

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

	public function __construct() {
		$this->initialise();
	}

	/**
	 * This method performs various initializations that are shared between the constructor
	 * and the reset() method inherited from the service interface
	 *
	 * NOTE: this method is NOT called init() to avoid conflicts with the init() method of the service interface
	 *
	 * @return	void
	 */
	public function initialise() {
		$this->configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		$this->mainTable = '';
		$this->sqlParser = t3lib_div::makeInstance('tx_dataquery_parser');
	}

	/**
	 * This method is used to get the data for the given query and return it in a standardised format
	 *
	 * @return	mixed		array containing the data structure or false if it failed
	 */
	public function getData() {

		// If the cache duration is not set to 0, try to find a cached query
		if (!empty($this->providerData['cache_duration'])) {
			try {
				$dataStructure = $this->getCachedStructure();
//t3lib_div::debug($dataStructure);
				$hasStructure = true;
			}
			// No structure was found, set flag that there's no structure yet
			catch (Exception $e) {
				$hasStructure = false;
			}
		}
		// No cache, no structure
		else {
			$hasStructure = false;
		}

		// If there's no structure yet, assemble it
		if (!$hasStructure) {
			$this->loadQuery();

			// Add the SQL conditions for the selected TYPO3 mechanisms
			$this->sqlParser->addTypo3Mechanisms($this->providerData);

			// Assemble filters, if defined
			if (is_array($this->filter) && count($this->filter) > 0) $this->sqlParser->addFilter($this->filter);

			// Use idList from input SDS, if defined
			if (is_array($this->structure) && isset($this->structure['uidListWithTable'])) $this->sqlParser->addIdList($this->structure['uidListWithTable']);

			// Build the complete query
			$query = $this->sqlParser->buildQuery();
			if ($this->configuration['debug'] || TYPO3_DLOG) t3lib_div::devLog($query, $this->extKey);

			// Execute the query
			$res = $GLOBALS['TYPO3_DB']->sql_query($query);

			// Prepare the full data structure
			$dataStructure = $this->prepareFullStructure($res);
		}

		// Prepare the limit and offset parameters
		$limit = (isset($this->filter['limit']['max'])) ? $this->filter['limit']['max'] : 0;
		if ($limit > 0) {
			// If there's a direct pointer, it takes precedence over the offset
			if (isset($this->filter['limit']['pointer']) && $this->filter['limit']['pointer'] > 0) {
				$offset = $this->filter['limit']['pointer'];
			}
			else {
				$offset = $limit * ((isset($this->filter['limit']['offset'])) ? $this->filter['limit']['offset'] : 0);
				if ($offset < 0) $offset = 0;
			}
		}
		else {
			$offset = 0;
		}

		// Take the structure and apply limit and offset, if defined
		if ($limit > 0 || $offset > 0) {
			// Reset offset if beyond total number of records
			if ($offset > $dataStructure['totalCount']) {
				$offset = 0;
			}
			// Initialise final structure with data that won't change
			$returnStructure = array(
									'name' => $dataStructure['name'],
									'trueName' => $dataStructure['trueName'],
									'totalCount' => $dataStructure['totalCount'],
									'header' => $dataStructure['header'],
									'records' => array()
									 );
			$counter = 0;
			$uidList = array();
			foreach ($dataStructure['records'] as $record) {
				// Get only those records that are after the offset and within the limit
				if ($counter >= $offset && ($limit == 0 || ($limit > 0 && $counter - $offset < $limit))) {
					$counter++;
					$returnStructure['records'][] = $record;
					$uidList[] = $record['uid'];
				}
				// If the offset has not been reached yet, just increase the counter
				elseif ($counter < $offset) {
					$counter++;
				}
				else {
					break;
				}
			}
			$returnStructure['count'] = count($returnStructure['records']);
			$returnStructure['uidList'] = implode(',', $uidList);
		}
		// If there's no limit take the structure as is
		else {
			$returnStructure = $dataStructure;
		}

// Hook for post-processing the data structure

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructure'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructure'] as $className) {
				$postProcessor = &t3lib_div::getUserObj($className);
				$returnStructure = $postProcessor->postProcessDataStructure($returnStructure, $this);
			}
		}
//t3lib_div::debug($returnStructure);
		return $returnStructure;
	}

	/**
	 * This method prepares a full data structure with overlays if needed but without limits and offset
	 * This is the structure that will be cached (at the end of method) to be called again from the cache when appropriate
	 *
	 * @param	pointer		$res: database resource from the executed query
	 * @return	array		The full data structure
	 */
	protected function prepareFullStructure($res) {
		// Initialise some variables
		$this->mainTable = $this->sqlParser->getMainTableName();
		$subtables = $this->sqlParser->getSubtablesNames();
		$numSubtables = count($subtables);
		$allTables = $subtables;
		array_push($allTables, $this->mainTable);
		$tableAndFieldLabels = $this->sqlParser->getLocalizedLabels($language);
		
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

		// Prepare the header parts for all tables
		$headers = array();
		foreach ($allTables as $table) {
			if (isset($tableAndFieldLabels[$table]['fields'])) {
				$headers[$table] = array();
				foreach ($tableAndFieldLabels[$table]['fields'] as $key => $label) {
					$headers[$table][$key] = array('label' => $label);
		        }
			}
		}

		// Loop on all records of the main table, applying overlays if needed
		$mainRecords = array();
		// Perform overlays only if language is not default and if necessary for table
		$doOverlays = ($GLOBALS['TSFE']->sys_language_content > 0) & $this->sqlParser->mustHandleLanguageOverlay($this->mainTable);
		$hasForeignOverlays = isset($GLOBALS['TCA'][$this->sqlParser->getTrueTableName($this->mainTable)]['ctrl']['transForeignTable']);
		foreach ($rows[$this->mainTable][0] as $row) {
				// Overlay if necessary and if record is not already in current language
			if ($doOverlays && $row[$tableCtrl['languageField']] != $GLOBALS['TSFE']->sys_language_content) {
				if ($hasForeignOverlays && isset($overlays[$this->mainTable][$row['uid']])) {
					$row = tx_overlays::overlaySingleRecord($table, $row, $overlays[$this->mainTable][$row['uid']]);
				}
				elseif (isset($overlays[$this->mainTable][$row['uid']][$row['pid']])) {
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
			$mainRecords[] = $row;
		}

		// Now loop on all the overlaid records of the main table and join them to their subtables
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
					$trueTableName = $this->sqlParser->getTrueTableName($table);
					// Check if there are any subrecords for this record
					if (isset($rows[$table][$aRecord['uid']])) {
						$numSubrecords = count($rows[$table][$aRecord['uid']]);
						if ($numSubrecords > 0) {
							$sublimit = $this->sqlParser->getSubTableLimit($table);
							$subcounter = 0;
							// Perform overlays only if language is not default and if necessary for table
							$doOverlays = ($GLOBALS['TSFE']->sys_language_content > 0) & $this->sqlParser->mustHandleLanguageOverlay($table);
							$hasForeignOverlays = isset($GLOBALS['TCA'][$trueTableName]['ctrl']['transForeignTable']);
							$subRecords = array();
							$subUidList = array();
							// Loop on all subrecords and perform overlays if necessary
							foreach ($rows[$table][$aRecord['uid']] as $subRow) {
								// Overlay if necessary and if record is not already in current language
								if ($doOverlays && $subRow[$GLOBALS['TCA'][$trueTableName]['ctrl']['languageField']] != $GLOBALS['TSFE']->sys_language_content) {
									if ($hasForeignOverlays && isset($overlays[$table][$subRow['uid']])) {
										$subRow = tx_overlays::overlaySingleRecord($table, $row, $overlays[$table][$subRow['uid']]);
									}
									elseif (isset($overlays[$table][$subRow['uid']][$subRow['pid']])) {
										$subRow = tx_overlays::overlaySingleRecord($table, $subRow, $overlays[$table][$subRow['uid']][$subRow['pid']]);
									}
										// No overlay exists
									else {
										// Take original record, only if non-translated are not hidden, or if language is [All]
										if ($GLOBALS['TSFE']->sys_language_contentOL == 'hideNonTranslated' && $subRow[$GLOBALS['TCA'][$trueTableName]['ctrl']['languageField']] != -1) {
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
								// Add the subrecord to the subtable only if it hasn't been included yet
								// Multiple identical subrecords may happen when joining several tables together
								// Take into account any limit that may have been placed on the number of subrecords in the query
								// (using the non-SQL standard keyword MAX)
								if (!in_array($subRow['uid'], $subUidList)) {
									if ($sublimit == 0 || $subcounter < $sublimit) {
										$subRecords[] = $subRow;
										$subUidList[] = $subRow['uid'];
									}
									elseif ($sublimit != 0 || $subcounter >= $sublimit) {
										break;
									}
									$subcounter++;
								}
							}
							// If there are indeed items, add the subtable to the record
							$numItems = count($subUidList);
							if ($numItems > 0) {
								$theFullRecord['sds:subtables'][] = array(
																		'name' => $table,
																		'trueName' => $this->sqlParser->getTrueTableName($table),
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

		// Assemble the full structure
		$numRecords = count($fullRecords);
		$dataStructure = array(
							'name' => $this->mainTable,
							'trueName' => $this->sqlParser->getTrueTableName($this->mainTable),
							'count' => $numRecords,
							'totalCount' => $numRecords,
							'uidList' => implode(',', $uidList),
							'header' => $headers[$this->mainTable],
							'records' => $fullRecords
						);

		// Hook for post-processing the data structure before it is stored into cache
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructureBeforeCache'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructureBeforeCache'] as $className) {
				$postProcessor = &t3lib_div::getUserObj($className);
				$dataStructure = $postProcessor->postProcessDataStructureBeforeCache($dataStructure, $this);
			}
		}

		// Store the structure in the cache table
		// The structure is not cached if the cache duration is set to 0
		if (!empty($this->providerData['cache_duration'])) {
			$fields = array(
							'query_id' => $this->providerData['uid'],
							'page_id' => $GLOBALS['TSFE']->id,
							'cache_hash' => $this->calculateCacheHash(array()),
							'structure_cache' => serialize($dataStructure),
							'expires' => time() + $this->providerData['cache_duration']
						);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_dataquery_cache', $fields);
		}

		// Finally return the assembled structure
		return $dataStructure;
	}

	/**
	 * This method is used to retrieve a data structure stored in cache provided it fits all parameters
	 * If no appropriate cache is found, it throws an exception
	 *
	 * @return	array	A standard data structure
	 */
	protected function getCachedStructure() {
		// Assemble condition for finding correct cache
		// This means matching the dataquery's primary key, the current language, the filter's hash (without the limit)
		// and that it has not expired
		$where = "query_id = '".$this->providerData['uid']."' AND page_id = '".$GLOBALS['TSFE']->id."'";
		$where .= " AND cache_hash = '".$this->calculateCacheHash(array())."'";
		$where .= " AND expires > '".time()."'";
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('structure_cache', 'tx_dataquery_cache', $where);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
			throw new Exception('No cached structure');
		}
		else {
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			return unserialize($row['structure_cache']);
		}
	}

	/**
	 * This method assembles a hash parameter depending on a variety of parameters, including
	 * the current FE language and the groups of the current FE user, if any
	 *
	 * @param	array	$parameters: additional parameters to add to the hash calculation
	 * @return	string	A md5 hash
	 */
	protected function calculateCacheHash(array $parameters) {
		// The base of the hash parameters is the current filter
		// To this we add the uidList (if it exists)
		// This makes it possible to vary the cache as a function of the idList provided by a secondary provider
		$filterForCache = $this->filter;
		if (is_array($this->structure) && isset($this->structure['uidListWithTable'])) {
			$filterForCache['uidListWithTable'] = $this->structure['uidListWithTable'];
		}
		// If some parameters were given, add them to the base cache parameters
		if (is_array($parameters) && count($parameters) > 0) {
			$cacheParameters = array_merge($filterForCache, $parameters);
		}
		else {
			$cacheParameters = $filterForCache;
		}
		// Finally we add other parameters of unicity:
		//	- the current FE language
		//	- the groups of the currently logged in FE user (if any)
		$cacheParameters['sys_language_uid'] = $GLOBALS['TSFE']->sys_language_content;
		if (is_array($this->fe_user->user) && count($this->fe_user->groupData['uid']) > 0) {
			$cacheParameters['fe_groups'] = $this->fe_user->groupData['uid'];
		}
		// Calculate the hash using the method provided by the base controller,
		// which filters out the "limit" part of the filter
		return tx_basecontroller_utilities::calculateFilterCacheHash($cacheParameters);
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
		if ($this->hasEmptyOutputStructure) {
			return $this->outputStructure;
		}
		else {
			return $this->getData();
		}
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

// t3lib_svbase methods

	/**
	 * This method resets values for a number of properties
	 * This is necessary because services are managed as singletons
	 * 
	 * NOTE: If you make your own implementation of reset in your DataProvider class, don't forget to call parent::reset()
	 * 
	 * @return	void
	 */
	public function reset() {
		parent::reset();
		$this->initialise();
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_wrapper.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_wrapper.php']);
}

?>