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
 *   57: class tx_dataquery_parser
 *   77:     public function parseQuery($query)
 *  290:     public function getLocalizedLabels($language = '')
 *  363:     public function addTypo3Mechanisms($parameters)
 *  407:     public function addFilter($filter)
 *  436:     public function addIdList($idList)
 *  476:     public function buildQuery()
 *  519:     public function parseSearch($searchParameters)
 *  567:     public function addWhereClause($clause)
 *  579:     public function getMainTableName()
 *  589:     public function getSubtablesNames()
 *  598:     public function hasMergedResults()
 *
 * TOTAL FUNCTIONS: 11
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once(t3lib_extMgm::extPath('overlays', 'class.tx_overlays.php'));

/**
 * This class is used to parse a SELECT SQL query into a structured array
 *
 * @author	Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_dataquery
 */
class tx_dataquery_parser {
	protected static $tokens = array('SELECT', 'FROM', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'WHERE', 'GROUP BY', 'ORDER BY', 'LIMIT', 'OFFSET', 'MERGED');
	protected static $allowedComparisons = array('eq' => '=', 'ne' => '!=', 'lt' => '<', 'le' => '<=', 'gt' => '>', 'ge' => '>=', 'in' => 'IN', 'like' => 'LIKE');
	protected $structure = array();
	protected $mainTable; // Name (or alias if defined) of the main query table, i.e. the one in the FROM part of the query
	protected $aliases = array(); // The keys to this array are the aliases of the tables used in the query and they point to the true table names
	protected $isMergedResult = false;
	protected $subtables = array();
	protected $queryFields = array();
	protected $doOverlays = array(); // Flag for each table (or its alias) whether to perform overlays or not
	protected static $useEnableFields = 1;
	protected static $useLanguageOverlays = 2;
	protected static $useVersioning = 4;

	/**
	 * This method is used to parse a SELECT SQL query.
	 * It is a simple parser and no way generic. It expects queries to be written a certain way.
	 *
	 * @param	string		the query to be parsed
	 * @return	mixed		array containing the query parts or false if the query was empty or invalid
	 */
	public function parseQuery($query) {
		$query = str_replace(array("\r", "\n", "\f"), ' ', $query);

// Get all parts of the query, using the SQL keywords as tokens
// The returned matches array contains the keywords matched (in position 2) and the string after each keyword (in position 3)

		$regexp = '/('.implode('|', self::$tokens).')/';
		$matches = preg_split($regexp, $query, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
//t3lib_div::debug($regexp);
//t3lib_div::debug($query);
//t3lib_div::debug($matches);

// Fill the structure array, as suited for each keyword

		$i = 0;
		$numMatches = count($matches);
		$this->aliases = array();
		while ($i < $numMatches) {
			$keyword = $matches[$i];
			$i++;
			$value = $matches[$i];
			$i++;
			if (!isset($this->structure[$keyword])) $this->structure[$keyword] = array();
			switch ($keyword) {
				case 'SELECT':

// Explode the select string in its constituent parts and store it as is
// More processing takes place later on

					$selectString = trim($value);
					$selectArray = t3lib_div::trimExplode(',', $selectString, 1);
					foreach ($selectArray as $value) {
						$this->structure[$keyword][] = $value;
					}
					break;
				case 'FROM':
					$fromParts = explode('AS',$value);
					$this->structure[$keyword]['table'] = trim($fromParts[0]);
					if (count($fromParts) > 1) {
						$this->structure[$keyword]['alias'] = trim($fromParts[1]);
						$this->aliases[$this->structure[$keyword]['alias']] = $this->structure[$keyword]['table'];
					}
					else {
						$this->structure[$keyword]['alias'] = $this->structure[$keyword]['table'];
					}
					$this->mainTable = $this->structure[$keyword]['alias'];
					break;
				case 'INNER JOIN':
				case 'LEFT JOIN':
				case 'RIGHT JOIN':
					$joinType = strtolower(substr($keyword, 0, strpos($keyword,'JOIN') - 1));
					$parts = explode('ON', $value);
					$moreParts = explode('AS',$parts[0]);
					$theJoin = array();
					$theJoin['table'] = trim($moreParts[0]);
					$theJoin['type'] = $joinType;
					if (count($moreParts) > 1) {
						$theJoin['alias'] = trim($moreParts[1]);
						$this->aliases[$theJoin['alias']] = $theJoin['table'];
					}
					else {
						$theJoin['alias'] = $theJoin['table'];
					}
					$this->subtables[] = $theJoin['alias'];
					if (count($parts) > 1) {
						$theJoin['on'] = trim($parts[1]);
					}
					else {
						$theJoin['on'] = '';
					}
					if (!isset($this->structure['JOIN'])) $this->structure['JOIN'] = array();
					$this->structure['JOIN'][$theJoin['alias']] = $theJoin;
					break;
				case 'WHERE':
					$this->structure[$keyword][] = trim($value);
					break;
				case 'ORDER BY':
				case 'GROUP BY':
					$orderParts = explode(',', $value);
					foreach ($orderParts as $part) {
						$this->structure[$keyword][] = trim($part);
					}
					break;
				case 'LIMIT':
					$this->structure[$keyword] = trim($value);
					break;
				case 'OFFSET':
					$this->structure[$keyword] = trim($value);
					break;

// Dataquery allows for non-standard keywords to be used in the SQL query for special purposes
// MERGED says that results from different tables are kept as single records.
// Otherwise joined tables are structured as subtables from the main table (the one in the FROM clause)

				case 'MERGED':
					$this->isMergedResult = true;
					break;
			}
		}

// Loop again on all SELECT items

		$numSelects = count($this->structure['SELECT']);
		$tableHasUid = array();
		for ($i = 0; $i < $numSelects; $i++) {
			$selector = $this->structure['SELECT'][$i];
			$alias = '';
			$fieldAlias = '';
			if (stristr($selector, '*')) { // If the string is just * (or possibly table.*), get all the fields for the table
				if ($selector == '*') { // It's only *, get list of fields for the main table
					$table = $this->mainTable;
					$alias = $table;
                }
				else { // It's table.*, get list of fields for the given table
					$selectorParts = t3lib_div::trimExplode('.', $selector, 1);
					$table = (isset($this->aliases[$selectorParts[0]]) ? $this->aliases[$selectorParts[0]] : $selectorParts[0]);
					$alias = $selectorParts[0];
                }
				$fieldInfo = $GLOBALS['TYPO3_DB']->admin_get_fields($table);
				$fields = array_keys($fieldInfo);
            }
			else { // Else, the field is some string, analyse it
				if (stristr($selector, 'AS')) { // If there's an alias, extract it and continue parsing
					$selectorParts = t3lib_div::trimExplode('AS', $selector, 1);
					$selector = $selectorParts[0];
					$fieldAlias = $selectorParts[1];
                }
				if (stristr($selector, '.')) { // If there's a dot, get table name
					$selectorParts = t3lib_div::trimExplode('.', $selector, 1);
					$table = (isset($this->aliases[$selectorParts[0]]) ? $this->aliases[$selectorParts[0]] : $selectorParts[0]);
					$alias = $selectorParts[0];
					$fields = array($selectorParts[1]);
                }
				else { // No dot, the table is the main one
					$fields = array($selector);
					$table = $this->mainTable;
					$alias = $table;
				}
            }

// Assemble list of fields per table
// The name of the field is used both as key and value, but the value will be replaced by the fields' labels in getLocalizedLabels()

			if (!isset($this->queryFields[$alias])) {
				$this->queryFields[$alias] = array('name' => $table, 'table' => (empty($this->aliases[$alias])) ? $table : $this->aliases[$alias], 'fields' => array());
			}
			foreach ($fields as $aField) {
				$this->queryFields[$alias]['fields'][$aField] = $aField;
			}

// Keep track of whether a field called "uid" (either its true name or an alias)
// exists for every table. If not, it will be added later
// (there must be a primary key, if it is not called "uid", an alias called "uid" must be used in the query)

            if (!isset($tableHasUid[$alias])) $tableHasUid[$alias] = false; // Initialise to false
			if (in_array('uid', $fields) || (isset($fieldAlias) && $fieldAlias == 'uid')) $tableHasUid[$alias] |= true;

// Assemble full names for each field
// The full name is:
//	1) the name of the table or its alias
//	2) a dot
//	3) the name of the table
//
// If it's the main table and there's an alias for the field
//
//	4a) AS and the field alias
//
// If it's not the main table, all fields get an alias using either their own name or the given field alias
//
//	4b) AS and $ and the field or its alias
//
// So something like foo.bar AS boink will get transformed into foo.bar AS foo$boink
//
// The $ sign is used in class tx_dataquery_wrapper for building the data structure

			$prefix = (empty($alias) ? $table : $alias);
			$completeFields = array();
			foreach ($fields as $name) {
				$fullField = $prefix.'.'.$name;
				if ($table == $this->mainTable) {
					if (!empty($fieldAlias)) $fullField .= ' AS '.$fieldAlias;
                }
				else {
					$fullField .= ' AS ';
					if (empty($fieldAlias)) {
						$fullField .= $prefix.'$'.$name;
					}
					else {
						$fullField .= $prefix.'$'.$fieldAlias;
                    }
                }
				$completeFields[] = $fullField;
			}
			$this->structure['SELECT'][$i] = implode(', ', $completeFields);
        }
        foreach ($tableHasUid as $alias => $flag) {
        	if (!$flag) {
        		$fullField = $alias.'.uid';
				if ($alias != $this->mainTable) {
	       			$fullField .= ' AS '.$alias.'$uid';
				}
				$this->structure['SELECT'][] = $fullField;
        	}
        }
//t3lib_div::debug($this->structure);
	}

	/**
	 * This method gets the localized labels for all tables and fields in the query in the given language
	 *
	 * @param	string		$language: two-letter ISO code of a language
	 * @return	array		list of all localized labels
	 */
	public function getLocalizedLabels($language = '') {
			// Make sure we have a lang object available
			// Use the global one, if it exists
		if (isset($GLOBALS['lang'])) {
			$lang = $GLOBALS['lang'];
        }
			// If no language object is available, create one
		else {
			require_once(PATH_typo3.'sysext/lang/lang.php');
			$lang = t3lib_div::makeInstance('language');
				// Find out which language to use
			if (empty($language)) {
				$languageCode = '';
					// If in the BE, it's taken from the user's preferences
				if (TYPO3_MODE == 'BE') {
					global $BE_USER;
					$languageCode = $BE_USER->uc['lang'];
                }
					// In the FE, we use the config.language TS property
				else {
					if (isset($GLOBALS['TSFE']->tmpl->setup['config.']['language'])) $languageCode = $GLOBALS['TSFE']->tmpl->setup['config.']['language'];
                }
            }
			else {
				$languageCode = $language;
            }
			$lang->init($languageCode);
		}

			// Now that we have a properly initialised language object,
			// loop on all labels and get any existing localised string
		$hasFullTCA = false;
		foreach ($this->queryFields as $alias => $tableData) {
			$table = $tableData['table'];
				// For the pages table, the t3lib_div::loadTCA() method does not work
				// We have to load the full TCA. Set a flag to signal that it's pointless
				// to call t3lib_div::loadTCA() after that, since the whole TCA is loaded anyway
				// Note: this is necessary only for the FE
			if ($table == 'pages') {
				if (TYPO3_MODE == 'FE') {
					$GLOBALS['TSFE']->includeTCA();
					$hasFullTCA = true;
				}
            }
			else {
				if (!$hasFullTCA) t3lib_div::loadTCA($table);
			}
				// Get the labels for the tables
			if (isset($GLOBALS['TCA'][$table]['ctrl']['title'])) {
				$tableName = $tableName = $lang->sL($GLOBALS['TCA'][$table]['ctrl']['title']);
				$this->queryFields[$alias]['name'] = $tableName;
			}
				// Get the labels for the fields
			foreach ($tableData['fields'] as $key => $value) {
				if (isset($GLOBALS['TCA'][$table]['columns'][$key]['label'])) {
					$fieldName = $lang->sL($GLOBALS['TCA'][$table]['columns'][$key]['label']);
					$this->queryFields[$alias]['fields'][$key] = $fieldName;
                }
            }
            	// By default disable language overlays for all tables
            	// Overlays are activated again on a case by case basis in addTypo3Mechanisms()
			$this->doOverlays[$alias] = false;
        }
		return $this->queryFields;
    }

	/**
	 * This method adds where clause elements related to typical TYPO3 control parameters:
	 *
	 * 	- the enable fields
	 * 	- the language handling
	 * 	- the versioning system
	 *
	 * @param	array		$settings: database record corresponding to the current Data Query
	 * @return	void
	 */
	public function addTypo3Mechanisms($settings) {

// Add the enable fields, first to the main table

		if (!empty($settings['use_enable_fields'])) {
			$enableClause = tx_overlays::getEnableFieldsCondition($this->aliases[$this->mainTable]);
			if ($this->mainTable != $this->aliases[$this->mainTable]) $enableClause = str_replace($this->aliases[$this->mainTable], $this->mainTable, $enableClause);
			$this->addWhereClause($enableClause);

// Add enable fields to JOINed tables

			if (isset($this->structure['JOIN']) && is_array($this->structure['JOIN'])) {
				foreach ($this->structure['JOIN'] as $tableIndex => $joinData) {
					$table = $joinData['table'];
					$enableClause = tx_overlays::getEnableFieldsCondition($table);
					if (!empty($enableClause)) {
						if ($table != $joinData['alias']) $enableClause = str_replace($table, $joinData['alias'], $enableClause);
						if (!empty($this->structure['JOIN'][$tableIndex]['on'])) $this->structure['JOIN'][$tableIndex]['on'] .= ' AND ';
						$this->structure['JOIN'][$tableIndex]['on'] .= '('.$enableClause.')';
					}
				}
			}
		}

// Add the language condition, if necessary

		if (!empty($settings['language_handling'])) {

// Add the DB fields and the SQL conditions necessary for having everything ready to handle overlays
// as per the standard TYPO3 mechanism

			if ($settings['language_handling'] == 'overlay') {
				// Loop on all tables involved
				foreach ($this->queryFields as $alias => $tableData) {
					$table = $tableData['table'];
					$fields = array_keys($tableData['fields']);
						// For each table, make sure that the fields necessary for handling the language overlay are included in the list of selected fields
					try {
						$fieldsForOverlay = tx_overlays::selectOverlayFields($table, implode(',', $fields));
						$fieldsForOverlayArray = t3lib_div::trimExplode(',', $fieldsForOverlay);
							// Strip the "[table name]." prefix
						$numFields = count($fieldsForOverlayArray);
						for ($i = 0; $i < $numFields; $i++) {
							$fieldsForOverlayArray[$i] = str_replace($table.'.', '', $fieldsForOverlayArray[$i]);
						}
							// Extract which fields were added and add them the list of fields to select
						$addedFields = array_diff($fieldsForOverlayArray, $fields);
						if (count($addedFields) > 0) {
							foreach ($addedFields as $aField) {
								$this->structure['SELECT'][] = $alias.'.'.$aField.' AS '.$alias.'$'.$aField;
								$this->queryFields[$table]['fields'][$aField] = $aField;
							}
						}
						$this->doOverlays[$alias] = true;
							// Add the language condition for the given table
						$languageCondition = tx_overlays::getLanguageCondition($table);
						if ($table != $alias) $languageCondition = str_replace($table, $alias, $languageCondition);
						if ($alias == $this->mainTable) {
							$this->addWhereClause($languageCondition);
						}
						else {
							if (!empty($this->structure['JOIN'][$alias]['on'])) $this->structure['JOIN'][$alias]['on'] .= ' AND ';
							$this->structure['JOIN'][$alias]['on'] .= '('.$languageCondition.')';
						}
					}
					catch (Exception $e) {
						$this->doOverlays[$alias] = false;
					}
				}
			}

// "Simple" language handling is just about adding the proper condition on the sys_language_uid field and nothing more
// No overlays will be handled at a later time

			elseif ($settings['language_handling'] == 'simple') {
				foreach ($this->queryFields as $alias => $tableData) {
					$table = $tableData['table'];
					if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
							// Take language that corresponds to current language or [All]
						$languageCondition = $alias.'.'.$GLOBALS['TCA'][$table]['ctrl']['languageField'].' IN ('.$GLOBALS['TSFE']->sys_language_content.', -1)';
						if ($alias == $this->mainTable) {
							$this->addWhereClause($languageCondition);
						}
						else {
							if (!empty($this->structure['JOIN'][$alias]['on'])) $this->structure['JOIN'][$alias]['on'] .= ' AND ';
							$this->structure['JOIN'][$alias]['on'] .= '('.$languageCondition.')';
						}
					}
				}
			}
		}
	}

	/**
	 * This method takes a Data Filter structure and turns it into SQL WHERE clauses
	 *
	 * @param	array		$filter: Data Filter structure
	 * @return	void
	 */
	public function addFilter($filter) {
		$completeFilter = '';
		$logicalOperator = (empty($filter['logicalOperator'])) ? 'AND' : $filter['logicalOperator'];
		if (isset($filter['filters']) && is_array($filter['filters'])) {
			foreach ($filter['filters'] as $filterData) {
				if (!empty($completeFilter)) $completeFilter .= ' '.$logicalOperator.' ';
				$table = (empty($filterData['table'])) ? $this->mainTable: $filterData['table'];
				$field = $filterData['field'];
				$condition = '';
				foreach ($filterData['conditions'] as $conditionData) {
					if (!empty($condition)) {
						$condition .= ' AND ';
					}
					$condition .= $table.'.'.$field.' '.self::$allowedComparisons[$conditionData['operator']].' '.$GLOBALS['TYPO3_DB']->fullQuoteStr($conditionData['value'], $table);
				}
				$completeFilter .= '('.$condition.')';
			}
			$this->addWhereClause($completeFilter);
		}
	}

	/**
	 * This method takes a list of uid's prepended by their table name,
	 * as returned in the "uidListWithTable" property of a idList-type SDS,
	 * and makes it into appropriate SQL IN conditions for every table that matches those used in the query
	 *
	 * @param	array		$idList: Comma-separated list of uid's prepended by their table name
	 * @return	void
	 */
	public function addIdList($idList) {
		if (!empty($idList)) {
			$idArray = t3lib_div::trimExplode(',', $idList);
			$idlistsPerTable = array();
				// First assemble a list of all uid's for each table
			foreach ($idArray as $item) {
					// Code inspired from t3lib_loadDBGroup
					// String is reversed before exploding, to get uid first
				list($uid, $table) = explode('_', strrev($item), 2);
					// Exploded parts are reversed back
				$uid = strrev($uid);
					// If table is not defined, assume it's the main table
				if (empty($table)) {
					$table = $this->mainTable;
				}
				else {
					$table = strrev($table);
				}
				if (!isset($idlistsPerTable[$table])) $idlistsPerTable[$table] = array();
				$idlistsPerTable[$table][] = $uid;
			}
				// Loop on all tables and add test on list of uid's, if table is indeed in query
			foreach ($idlistsPerTable as $table => $uidArray) {
				$condition = $table.'.uid IN ('.implode(',', $uidArray).')';
				if ($table == $this->mainTable) {
					$this->addWhereClause($condition);
				}
				elseif (in_array($table, $this->subtables)) {
					if (!empty($this->structure['JOIN'][$table]['on'])) $this->structure['JOIN'][$table]['on'] .= ' AND ';
					$this->structure['JOIN'][$table]['on'] .= $condition;
				}
			}
		}
	}

	/**
	 * This method builds up the query with all the data stored in the structure
	 *
	 * @return	string		the assembled SQL query
	 */
	public function buildQuery() {
		$query = 'SELECT '.implode(', ',$this->structure['SELECT']).' ';
		$query .= 'FROM '.$this->structure['FROM']['table'];
		if (!empty($this->structure['FROM']['alias'])) $query .= ' AS '.$this->structure['FROM']['alias'];
		$query .= ' ';
		if (isset($this->structure['JOIN'])) {
			foreach ($this->structure['JOIN'] as $theJoin) {
				$query .= strtoupper($theJoin['type']).' JOIN '.$theJoin['table'];
				if (!empty($theJoin['alias'])) $query .= ' AS '.$theJoin['alias'];
				if (!empty($theJoin['on'])) $query .= ' ON '.$theJoin['on'];
				$query .= ' ';
			}
		}
		if (isset($this->structure['WHERE'])) {
			$whereClause = '';
			foreach ($this->structure['WHERE'] as $clause) {
				if (!empty($whereClause)) $whereClause .= ' AND ';
				$whereClause .= $clause;
			}
			$query .= 'WHERE '.$whereClause.' ';
		}
		if (count($this->structure['ORDER BY']) > 0) {
			$query .= 'ORDER BY '.implode(', ',$this->structure['ORDER BY']).' ';
		}
		if (count($this->structure['GROUP BY']) > 0) {
			$query .= 'GROUP BY '.implode(', ',$this->structure['GROUP BY']).' ';
		}
		if (count($this->structure['LIMIT']) > 0) {
			$query .= 'LIMIT '.$this->structure['LIMIT'];
		}
		if (count($this->structure['OFFSET']) > 0) {
			$query .= 'OFFSET '.$this->structure['OFFSET'];
		}
//t3lib_div::debug($query);
		return $query;
	}

	/**
	 * Analyse search structure (multidimensional array) and set where fields accordingly
	 *
	 * @param	array	search fields structure
	 *
	 * @return	void
	public function parseSearch($searchParameters) {
		$whereClause = '';
		if (is_array($searchParameters) && count($searchParameters) > 0) {
			foreach ($searchParameters as $groupID => $groupData) {
				$fieldOperator = '';
				foreach ($groupData['fields'] as $fieldName => $fieldData) {
					$useField = true;
					if (isset($fieldData['ignore'])) { // Check if the field must be ignored
						if ($fieldData['ignore'] == 'empty') {
							if (empty($fieldData['value'])) $useField = false;
						}
						elseif ($fieldData['value'] == $fieldData['ignore']) {
							$useField = false;
						}
					}
					if ($useField) { // If the field must not be ignored, add it to the where clause
						if (!empty($fieldOperator)) $whereClause .= ' '.$fieldOperator.' '; // Concatenate with operator from previous field (if any)
						if (empty($fieldData['comparison'])) { // Default comparison operator is equals
							$comparison = '=';
						}
						else {
							$fieldComparison = strtolower($fieldData['comparison']);
							if (isset($this->allowedComparisons[$fieldComparison])) { // Check that comparison operator is valid, else use equals
								$comparison = $this->allowedComparisons[$fieldComparison];
							}
							else {
								$comparison = '=';
							}
						}
						$whereClause .= ' '.$fieldName.' '.$comparison." '".$fieldData['value']."'"; // Add to where clause
						$fieldOperator = (empty($fieldData['operator'])) ? 'AND' : $fieldData['operator']; // Prepare logical operator for chaining with next field (if any)
					}
				}
			}
			// Group operator
		}
		$this->addWhereClause($whereClause);
	}
	 */

// Setters and getters

	/**
	 * Add a condition for the WHERE clause
	 *
	 * @param	string		SQL WHERE clause (without WHERE)
	 * @return	void
	 */
	public function addWhereClause($clause) {
		if (!empty($clause)) {
			$this->structure['WHERE'][] = $clause;
		}
	}

	/**
	 * This method returns the name (alias) of the main table of the query,
	 * which is the table name that appears in the FROM clause, or the alias, if any
	 *
	 * @return	string		main table name (alias)
	 */
	public function getMainTableName() {
		return $this->mainTable;
	}

	/**
	 * This method returns an array containing the list of all subtables in the query,
	 * i.e. the tables that appear in any of the JOIN statements
	 *
	 * @return	array		names of all the joined tables
	 */
	public function getSubtablesNames() {
		return $this->subtables;
	}

	/**
	 * This method returns the value of the isMergedResult flag
	 *
	 * @return	boolean
	 */
	public function hasMergedResults() {
		return $this->isMergedResult;
	}

	/**
	 * This method indicates whether the language overlay mechanism must/can be handled for a given table
	 *
	 * @param	string		$table: name (alias) of the table to handle
	 * @return	boolean		true if language overlay must and can be performed, false otherwise
	 *
	 * @see tx_dataquery_parser::addTypo3Mechanisms()
	 */
	public function mustHandleLanguageOverlay($table) {
		return (isset($this->doOverlays[$table])) ? $this->doOverlays[$table] : false;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_parser.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_parser.php']);
}

?>