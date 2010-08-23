<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007-2010 Francois Suter (Cobweb) <typo3@cobweb.ch>
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

require_once(t3lib_extMgm::extPath('tesseract', 'services/class.tx_tesseract_providerbase.php'));
require_once(t3lib_extMgm::extPath('tesseract', 'lib/class.tx_tesseract_utilities.php'));

/**
 * Wrapper for data query
 * This class is used to get the results of a specific data query
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_wrapper extends tx_tesseract_providerbase {
	public $extKey = 'dataquery';
	protected $configuration; // Extension configuration
	protected $mainTable; // Store the name of the main table of the query
	/**
	 * Local instance of the SQL parser class
	 *
	 * @var tx_dataquery_parser	$sqlParser
	 */
	protected $sqlParser;
	static public $sortingFields = array(); // List of fields used for sorting recordset
	static public $sortingLevel = 0;

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
		$dataStructure = array();
		$returnStructure = array();

			// If the cache duration is not set to 0, try to find a cached query
			// Avoid that if global no_cache flag is set
		$hasStructure = FALSE;
		if (!empty($this->providerData['cache_duration']) && empty($GLOBALS['TSFE']->no_cache)) {
			try {
				$dataStructure = $this->getCachedStructure();
//t3lib_div::debug($dataStructure);
				$hasStructure = TRUE;
			}
				// No structure was found, set flag that there's no structure yet
			catch (Exception $e) {
				$hasStructure = FALSE;
			}
		}

			// If there's no structure yet, assemble it
		if (!$hasStructure) {
			$this->loadQuery();

				// Add the SQL conditions for the selected TYPO3 mechanisms
			$this->sqlParser->addTypo3Mechanisms($this->providerData);

				// Assemble filters, if defined
			if (is_array($this->filter) && count($this->filter) > 0) {
				$this->sqlParser->addFilter($this->filter);
			}

				// Use idList from input SDS, if defined
			if (is_array($this->structure) && !empty($this->structure['count'])) {
				$this->sqlParser->addIdList($this->structure['uidListWithTable']);
			}

				// Build the complete query
				// TODO: the exception handling should be part of a global error handling mechanism
			try {
				$query = $this->sqlParser->buildQuery();
				if ($this->configuration['debug'] || TYPO3_DLOG) {
					t3lib_div::devLog($query, $this->extKey, -1);
				}
			}
			catch (Exception $e) {
				if ($this->configuration['debug'] || TYPO3_DLOG) {
					t3lib_div::devLog($e->getMessage(), $this->extKey, 3);
				}
			}

				// Execute the query
			$res = $GLOBALS['TYPO3_DB']->sql_query($query);

				// Prepare the full data structure
			$dataStructure = $this->prepareFullStructure($res);
		}

			// Prepare the limit and offset parameters
		$limit = (isset($this->filter['limit']['max'])) ? $this->filter['limit']['max'] : 0;
		$offset = 0;
		if ($limit > 0) {
				// If there's a direct pointer, it takes precedence over the offset
			if (isset($this->filter['limit']['pointer']) && $this->filter['limit']['pointer'] > 0) {
				$offset = $this->filter['limit']['pointer'];
			} else {
				$offset = $limit * ((isset($this->filter['limit']['offset'])) ? $this->filter['limit']['offset'] : 0);
				if ($offset < 0) {
					$offset = 0;
				}
			}
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
		unset($dataStructure);

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
		$tableAndFieldLabels = $this->sqlParser->getLocalizedLabels();
		$uidList = array();
		$fullRecords = array();

			// Get true table names for all tables
		$allTablesTrueNames = array();
		foreach ($allTables as $alias) {
			$allTablesTrueNames[$alias] = $this->sqlParser->getTrueTableName($alias);
		}
//t3lib_div::debug($allTablesTrueNames, 'True table names');

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

			// Act only if there are records
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) > 0) {
				// Initialise array for storing records
			$rows = array($this->mainTable => array(0 => array()));
			if ($numSubtables > 0) {
				foreach ($subtables as $table) {
					$rows[$table] = array();
				}
			}

				// Loop on all records to assemble the raw recordset
			$rawRecordset = array();
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$rawRecordset[] = $row;

			}
//t3lib_div::debug($rawRecordset, 'Raw result');

				// Analyze the first row of the raw recordset to get which column belongs to which table
				// and which aliases are used, if any
			$testRow = $rawRecordset[0];
			$columnsMappings = array();
			$reverseColumnsMappings = array();
			foreach ($testRow as $columnName => $value) {
				$info = $this->sqlParser->getTrueFieldName($columnName);
//t3lib_div::debug($info, 'Field info');
				$columnsMappings[$columnName] = $info;
				$reverseColumnsMappings[$info['aliasTable']][$info['field']] = $columnName;
			}
//t3lib_div::debug($columnsMappings, 'Columns mappings');
//t3lib_div::debug($reverseColumnsMappings, 'Reversed columns mappings');

				// Get overlays for each table, if language is not default
				// Set a general flag about having been through this process or not
			$hasBeenThroughOverlayProcess = FALSE;
			$finalRecordset = array();
			if ($GLOBALS['TSFE']->sys_language_content == 0) {
				$finalRecordset = $rawRecordset;
					// If no sorting is defined at all, perform fixed order sorting, if defined
					// Note this will work only if the secondary provider refers to a single table
				if (!$this->sqlParser->hasOrdering() && !empty($this->structure['count'])) {
						// Add fixed order to recordset
					$uidList = t3lib_div::trimExplode(',', $this->structure['uidList']);
					$fixedOrder = array_flip($uidList);
					foreach ($finalRecordset as $index => $record) {
						$finalRecordset[$index]['tx_dataquery:fixed_order'] = $fixedOrder[$record['uid']];
					}
					unset($fixedOrder);
//t3lib_div::debug($finalRecordset, 'Recordset with fixed order');
						// Sort recordset according to fixed order
					usort($finalRecordset, array('tx_dataquery_wrapper', 'sortUsingFixedOrder'));
				}
//t3lib_div::debug($finalRecordset, 'Recordset after sorting (no overlays)');
			} else {
					// First collect all the uid's for each table
				$allUIDs = array();
				foreach ($rawRecordset as $row) {
					foreach ($row as $fieldName => $fieldValue) {
						$fieldNameParts = t3lib_div::trimExplode('$', $fieldName);
						$table = '';
						$field = '';
						if (count($fieldNameParts) == 1) {
							$table = $allTablesTrueNames[$this->mainTable];
							$field = $fieldNameParts[0];
						} else {
							$table = $allTablesTrueNames[$fieldNameParts[0]];
							$field = $fieldNameParts[1];
						}
						if ($field == 'uid' && isset($fieldValue)) {
							if (!isset($allUIDs[$table])) {
								$allUIDs[$table] = array();
							}
							$allUIDs[$table][] = $fieldValue;
						}
					}
				}
					// Get overlays for all tables
				$overlays = array();
				$doOverlays = array();
				foreach ($allUIDs as $table => $uidList) {
						// Make sure the uid's are unique
					$allUIDs[$table] = array_unique($uidList);
					$doOverlays[$table] = $this->sqlParser->mustHandleLanguageOverlay($table);
						// Get overlays only if needed/possible
					if ($doOverlays[$table] && count($allUIDs[$table]) > 0) {
						$overlays[$table] = tx_overlays::getOverlayRecords($table, $allUIDs[$table], $GLOBALS['TSFE']->sys_language_content);
							// Set global overlay process flag to true
						$hasBeenThroughOverlayProcess |= TRUE;
					}
				}
//t3lib_div::debug($allUIDs, 'Unique IDs per table');
//t3lib_div::debug($doOverlays, 'Do overlays?');
//t3lib_div::debug($overlays, 'Overlays');

					// Loop on all recordset rows to overlay them
				foreach ($rawRecordset as $row) {
					$subParts = array();
						// Split record into parts related to a single table
					foreach ($columnsMappings as $columnName => $columnInfo) {
						if (!isset($subParts[$columnInfo['aliasTable']])) {
							$subParts[$columnInfo['aliasTable']] = array();
						}
						$subParts[$columnInfo['aliasTable']][$columnInfo['field']] = $row[$columnName];
					}
//t3lib_div::debug($subParts, 'Raw subparts');
						// Overlay each part
					foreach ($subParts as $alias => $subRow) {
						$table = $allTablesTrueNames[$alias];
						$tableCtrl = $GLOBALS['TCA'][$table]['ctrl'];
						$result = $subRow;
						if ($doOverlays[$table] && $subRow[$tableCtrl['languageField']] != $GLOBALS['TSFE']->sys_language_content) {
								// Overlay with record from foreign table
							if (isset($tableCtrl['transForeignTable']) && isset($overlays[$table][$subRow['uid']])) {
								$result = tx_overlays::overlaySingleRecord($table, $subRow, $overlays[$table][$subRow['uid']]);

								// Overlay with record from same table
							} elseif (isset($overlays[$table][$subRow['uid']][$subRow['pid']])) {
								$result = tx_overlays::overlaySingleRecord($table, $subRow, $overlays[$table][$subRow['uid']][$subRow['pid']]);

								// No overlay exists
							} else {
									// Take original record, only if non-translated are not hidden, or if language is [All]
								if ($GLOBALS['TSFE']->sys_language_contentOL == 'hideNonTranslated' && $subRow[$tableCtrl['languageField']] != -1) {
									unset($result); // Skip record
								} else {
									$result = $subRow;
								}
							}
						}
							// Include result only if it was not unset during overlaying
						if (isset($result)) {
							$subParts[$alias] = $result;
						} else {
							unset($subParts[$alias]);
						}
					}
//t3lib_div::debug($subParts, 'Subparts');
						// Reassemble the full record
					$overlaidRecord = array();
					foreach ($subParts as $alias => $subRow) {
						if (isset($subRow)) {
							foreach ($subRow as $field => $value) {
								$overlaidRecord[$reverseColumnsMappings[$alias][$field]] = $value;
							}
						}
						else {
							if ($alias == $this->mainTable) {
								unset($overlaidRecord);
								break;
							}
						}
					}
//t3lib_div::debug($overlaidRecord, 'Overlaid record');
					if (isset($overlaidRecord['uid'])) {
						$finalRecordset[] = $overlaidRecord;
					}
				}
					// Clean up (potentially large) arrays that are not used anymore
				unset($rawRecordset);
				unset($overlays);
				unset($subParts);
//t3lib_div::debug($finalRecordset, 'Overlaid recordset');

					// If the dataquery was provided with a structure,
					// use the list of uid's to define a fixed order of records
				if (isset($this->structure['uidList'])) {
					$uidList = t3lib_div::trimExplode(',', $this->structure['uidList']);
					$fixedOrder = array_flip($uidList);
					foreach ($finalRecordset as $index => $record) {
						$finalRecordset[$index]['tx_dataquery:fixed_order'] = $fixedOrder[$record['uid']];
					}
					unset($fixedOrder);
				}
//t3lib_div::debug($finalRecordset, 'Final recordset before sorting');

					// Perform sorting if not handled by SQL
				if (!$this->sqlParser->isSqlUsedForOrdering()) {
					self::$sortingFields = $this->sqlParser->getOrderByFields();
						// The names of the fields as stored in the sorting fields configuration
						// match the names used in the SQL query, but not the aliases
						// So they will not match the column names in the full recordset
						// Use the reverse mapping information (if available) to get the aliases
						// (if defined, otherwise stick to field name)
					foreach (self::$sortingFields as $index => $orderInfo) {
						$alias = $this->mainTable;
						$field = '';
							// Field may have a special alias which is also not the colum name
							// found in the recordset, but should be used to find that name
						$fieldName = (isset($orderInfo['alias'])) ? $orderInfo['alias'] : $orderInfo['field'];
						$fieldParts = explode('.', $fieldName);
						if (count($fieldParts) == 1) {
							$field = $fieldParts[0];
						} else {
							$alias = $fieldParts[0];
							$field = $fieldParts[1];
						}
						if (isset($reverseColumnsMappings[$alias][$field])) {
							self::$sortingFields[$index]['field'] = $reverseColumnsMappings[$alias][$field];
						} else {
							self::$sortingFields[$index]['field'] = $field;
						}
					}
//t3lib_div::debug(self::$sortingFields, 'Sorting fields');
					self::$sortingLevel = 0;
					usort($finalRecordset, array('tx_dataquery_wrapper', 'sortRecordset'));
//t3lib_div::debug($finalRecordset, 'Sorted, overlaid recordset');

					// If no sorting is defined at all, perform fixed order sorting, if defined
				} elseif (!$this->sqlParser->hasOrdering() && isset($this->structure['uidList'])) {
					usort($finalRecordset, array('tx_dataquery_wrapper', 'sortUsingFixedOrder'));
				}
			} // End of translation handling

				// Loop on all records to sort them by table. This can be seen as "de-JOINing" the tables.
				// This is necessary for such operations as overlays. When overlays are done, tables will be joined again
				// but within the format of Standardised Data Structure
			$oldUID = 0;
			foreach ($finalRecordset as $row) {
				$currentUID = $row['uid'];
					// If we're not handling the same main record as before, perform some initialisations
				if ($currentUID !== $oldUID) {
					if ($numSubtables > 0) {
						foreach ($subtables as $table) {
							$rows[$table][$currentUID] = array();
						}
					}
				}
				$recordsPerTable = array();
				foreach ($row as $fieldName => $fieldValue) {
						// The query contains no joined table
						// All fields belong to the main table
					if ($numSubtables == 0) {
						$recordsPerTable[$this->mainTable][$columnsMappings[$fieldName]['mapping']['field']] = $fieldValue;

						// There are multiple tables
					} else {
							// Get the field's true name
						$finalFieldName = $columnsMappings[$fieldName]['mapping']['field'];
							// However, if the field had an explicit alias, use that alias
						if (isset($columnsMappings[$fieldName]['mapping']['alias'])) {
							$finalFieldName = $columnsMappings[$fieldName]['mapping']['alias'];
						}
							// Field belongs to a subtable
						if (in_array($columnsMappings[$fieldName]['mapping']['table'], $subtables)) {
							$subtableName = $columnsMappings[$fieldName]['mapping']['table'];
							if (isset($fieldValue)) {
								$recordsPerTable[$subtableName][$finalFieldName] = $fieldValue;
							}

							// Else assume the field belongs to the main table
						} else {
							$recordsPerTable[$this->mainTable][$finalFieldName] = $fieldValue;
						}
					}
				}
					// If we're not handling the same main record as before, store the current information for the main table
				if ($currentUID !== $oldUID) {
					$rows[$this->mainTable][0][] = $recordsPerTable[$this->mainTable];
					$oldUID = $currentUID;
				}
					// Store information for each subtable
				if ($numSubtables > 0) {
					foreach ($subtables as $table) {
						if (isset($recordsPerTable[$table]) && count($recordsPerTable[$table]) > 0) {
							$rows[$table][$currentUID][] = $recordsPerTable[$table];
						}
					}
				}
			}
				// Clean up a potentially large array that is not used anymore
			unset($finalRecordset);
//t3lib_div::debug($rows, 'De-JOINed tables');

				// Now loop on all the records of the main table and join them to their subtables
			$hasInnerJoin = $this->sqlParser->hasInnerJoinOnFirstSubtable();
			$uidList = array();
			foreach ($rows[$this->mainTable][0] as $aRecord) {
				$uidList[] = $aRecord['uid'];
				$theFullRecord = $aRecord;
				$theFullRecord['__substructure'] = array();
					// Check if there are any subtables in the query
				$recordsPerSubtable = array();
				if ($numSubtables > 0) {
					foreach ($subtables as $table) {
							// Check if there are any subrecords for this record
						if (isset($rows[$table][$aRecord['uid']])) {
							$numSubrecords = count($rows[$table][$aRecord['uid']]);
							if ($numSubrecords > 0) {
								$sublimit = $this->sqlParser->getSubTableLimit($table);
								$subcounter = 0;
									// Perform overlays only if language is not default and if necessary for table
								$subRecords = array();
								$subUidList = array();
									// Loop on all subrecords and apply limit, if any
								foreach ($rows[$table][$aRecord['uid']] as $subRow) {
									if (!isset($subRow['uid'])) continue;
										// Add the subrecord to the subtable only if it hasn't been included yet
										// Multiple identical subrecords may happen when joining several tables together
										// Take into account any limit that may have been placed on the number of subrecords in the query
										// (using the non-SQL standard keyword MAX)
									if (!in_array($subRow['uid'], $subUidList)) {
										if ($sublimit == 0 || $subcounter < $sublimit) {
											$subRecords[] = $subRow;
											$subUidList[] = $subRow['uid'];
										} elseif ($sublimit != 0 || $subcounter >= $sublimit) {
											break;
										}
										$subcounter++;
									}
								}
									// If there are indeed items, add the subtable to the record
								$numItems = count($subUidList);
								$recordsPerSubtable[$table] = $numItems;
								if ($numItems > 0) {
									$theFullRecord['__substructure'][$table] = array(
																			'name' => $table,
																			'trueName' => $allTablesTrueNames[$table],
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
					// If the query used INNER JOINs and went through the overlay process,
					// preform additional checks
				if ($numSubtables > 0 && !empty($hasInnerJoin) && $hasBeenThroughOverlayProcess) {
						// If there are no subrecords after the overlay process, but the query
						// used an INNER JOIN, the record must be removed, so that the end result
						// will look like what it would have been in the default language
					if (!empty($recordsPerSubtable[$hasInnerJoin])) {
						$fullRecords[] = $theFullRecord;
					}

					// Otherwise just take the record as is
				} else {
					$fullRecords[] = $theFullRecord;
				}
			}
				// Clean up a potentially large array that is not used anymore
			unset($rows);
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

			// Assemble the full structure
		$numRecords = count($fullRecords);
		$dataStructure = array(
							'name' => $this->mainTable,
							'trueName' => $allTablesTrueNames[$this->mainTable],
							'count' => $numRecords,
							'totalCount' => $numRecords,
							'uidList' => implode(',', $uidList),
							'header' => $headers[$this->mainTable],
							'records' => $fullRecords
						);
			// Clean up a potentially large array that is not used anymore
		unset($fullRecords);
//t3lib_div::debug($dataStructure, 'Finished data structure');

			// Hook for post-processing the data structure before it is stored into cache
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructureBeforeCache'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$this->extKey]['postProcessDataStructureBeforeCache'] as $className) {
				$postProcessor = &t3lib_div::getUserObj($className);
				$dataStructure = $postProcessor->postProcessDataStructureBeforeCache($dataStructure, $this);
			}
		}

			// Store the structure in the cache table
		$this->writeStructureToCache($dataStructure);

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
		$where = "query_id = '" . $this->providerData['uid'] . "' AND page_id = '" . $GLOBALS['TSFE']->id . "'";
		$where .= " AND cache_hash = '" . $this->calculateCacheHash(array()) . "'";
		$where .= " AND expires > '" . time() . "'";
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('structure_cache', 'tx_dataquery_cache', $where);
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
			throw new Exception('No cached structure');
		} else {
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			return unserialize($row['structure_cache']);
		}
	}

	/**
	 * This method write the standard data structure to cache,
	 * provided some conditions are met
	 * 
	 * @param	array	$structure: a standard data structure
	 * @return	void
	 */
	protected function writeStructureToCache($structure) {
			// Write only if cache is active
		if (!empty($this->providerData['cache_duration'])) {
			$cacheHash = $this->calculateCacheHash(array());
			$serializedStructure = serialize($structure);
				// Write only if serialized data is not too large
			if (empty($this->configuration['cacheLimit']) || strlen($serializedStructure) <= $this->configuration['cacheLimit']) {
				$fields = array(
								'query_id' => $this->providerData['uid'],
								'page_id' => $GLOBALS['TSFE']->id,
								'cache_hash' => $cacheHash,
								'structure_cache' => $serializedStructure,
								'expires' => time() + $this->providerData['cache_duration']
							);
				$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_dataquery_cache', $fields);
			}
				// If data is too large for caching, make sure no other cache is left over
			else {
				$where = "query_id = '" . $this->providerData['uid'] . "'";
				$where .= " AND page_id = '" . $GLOBALS['TSFE']->id . "'";
				$where .= " AND cache_hash = '" . $cacheHash . "'";
				$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_dataquery_cache', $where);
			}
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
		$cacheParameters = $filterForCache;
		if (is_array($parameters) && count($parameters) > 0) {
			$cacheParameters = array_merge($cacheParameters, $parameters);
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
		return tx_tesseract_utilities::calculateFilterCacheHash($cacheParameters);
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

	/**
	 * This static method is called when performing a special sorting of the recordset
	 * 
	 * @param	mixed	$a: first element to sort
	 * @param	mixed	$b: second element to sort
	 *
	 * @return	integer	-1 if first argument is smaller than second argument, 1 if first is greater than second and 0 if both are equal
	 *
	 * @see	tx_dataquery_wrapper::prepareFullStructure()
	 */
	static public function sortRecordset($a, $b) {
			// Get the sorting information from static variables
			// The level is a pointer to the current field being used for sorting
		$level = self::$sortingLevel;
		$field = self::$sortingFields[$level]['field'];
		$order = (empty(self::$sortingFields[$level]['order'])) ? 'ASC' : strtoupper(self::$sortingFields[$level]['order']);
		$result = strcmp($a[$field], $b[$field]);
		if ($result == 0) {
				// If results are equal on the current level, check if there's a next level of sorting
				// for differentiating the records
				// If yes, call sorting method recursively
			if (isset(self::$sortingFields[$level + 1])) {
				self::$sortingLevel++;
				$result = self::sortRecordset($a, $b);
				self::$sortingLevel--;
			}
		}
		else {
			if ($order == 'DESC') {
				$result = -$result;
			}
		}
		return $result;
	}

	/**
	 * This static method is called when sorting records using a special fixed order value
	 * 
	 * @param	mixed	$a: first element to sort
	 * @param	mixed	$b: second element to sort
	 *
	 * @return	integer	-1 if first argument is smaller than second argument, 1 if first is greater than second and 0 if both are equal
	 *
	 * @see	tx_dataquery_wrapper::prepareFullStructure()
	 */
	static public function sortUsingFixedOrder($a, $b) {
		$result = 1;
		if ($a['tx_dataquery:fixed_order'] == $b['tx_dataquery:fixed_order']) {
			$result = 0;
		} elseif ($a['tx_dataquery:fixed_order'] < $b['tx_dataquery:fixed_order']) {
			$result = -1;
		}
		return $result;
	}

// Data Provider interface methods

	/**
	 * This method returns the type of data structure that the Data Provider can prepare
	 *
	 * @return	string		type of the provided data structure
	 */
	public function getProvidedDataStructure() {
		return tx_tesseract::RECORDSET_STRUCTURE_TYPE;
	}

	/**
	 * This method indicates whether the Data Provider can create the type of data structure requested or not
	 *
	 * @param	string		$type: type of data structure
	 * @return	boolean		true if it can handle the requested type, false otherwise
	 */
	public function providesDataStructure($type) {
		return $type == tx_tesseract::RECORDSET_STRUCTURE_TYPE;
	}

	/**
	 * This method returns the type of data structure that the Data Provider can receive as input
	 *
	 * @return	string		type of used data structures
	 */
	public function getAcceptedDataStructure() {
		return tx_tesseract::IDLIST_STRUCTURE_TYPE;
	}

	/**
	 * This method indicates whether the Data Provider can use as input the type of data structure requested or not
	 *
	 * @param	string		$type: type of data structure
	 * @return	boolean		true if it can use the requested type, false otherwise
	 */
	public function acceptsDataStructure($type) {
		return $type == tx_tesseract::IDLIST_STRUCTURE_TYPE;
	}

	/**
	 * This method assembles the data structure and returns it
	 *
	 * @return	array		standardised data structure
	 */
	public function getDataStructure() {
		if ($this->hasEmptyOutputStructure) {
			return $this->outputStructure;
		} else {
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
		if (is_array($structure)) {
			$this->structure = $structure;
		}
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