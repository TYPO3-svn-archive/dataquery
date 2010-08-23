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

require_once(t3lib_extMgm::extPath('overlays', 'class.tx_overlays.php'));

/**
 * This class is used to parse a SELECT SQL query into a structured array
 * It rebuilds the query aferwards, automatically handling a number of TYPO3 constructs,
 * like enable fields and language overlays
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_parser {
		/**
		 * List of eval types which indicate non-text fields
		 * @var	array	$notTextTypes
		 */
	static protected $notTextTypes = array('date', 'datetime', 'time', 'timesec', 'year', 'num', 'md5', 'int', 'double2');

		/**
		 * Structured type containing the parts of the parsed query
		 * @var	tx_dataquery_queryobject	$queryObject
		 */
	protected $queryObject;

		/**
		 * True names for all the fields. The key is the actual alias used in the query.
		 * @var	array	$fieldTrueNames
		 */
	protected $fieldTrueNames = array();

		/**
		 * List of all fields being queried, arranged per table (aliased)
		 * @var	array	$queryFields
		 */
	protected $queryFields = array();

		/**
		 * Flag for each table whether to perform overlays or not
		 * @var	array
		 */
	protected $doOverlays = array();

		/**
		 * True if order by is processed using SQL, false otherwise (see preprocessOrderByFields())
		 * @var	boolean
		 */
	protected $processOrderBy = TRUE;
	protected $isMergedResult = FALSE;

	/**
	 * This method is used to parse a SELECT SQL query.
	 * It is a simple parser and no way generic. It expects queries to be written a certain way.
	 *
	 * @param	string		$query: the query to be parsed
	 * @return	void
	 */
	public function parseQuery($query) {
			// Put the query through the field parser to filter out commented lines
		$queryLines = tx_tesseract_utilities::parseConfigurationField($query);
			// Put the query into a single string
		$query = implode(' ', $queryLines);
			// Strip backquotes
		$query = str_replace('`', '', $query);
			// Strip trailing semi-colon if any
		if (strrpos($query, ';') == strlen($query) - 1) {
			$query = substr($query, 0, -1);
		}
			// Parse query for subexpressions
		$query = tx_expressions_parser::evaluateString($query, FALSE);

			// Parse the SQL query
			/**
			 * @var	tx_dataquery_sqlparser
			 */
		$sqlParser = t3lib_div::makeInstance('tx_dataquery_sqlparser');
			// NOTE: the following call may throw exceptions,
			// but we let them bubble up
		$this->queryObject = $sqlParser->parseSQL($query);

			// Loop on all query fields to assemble additional information structures
		foreach ($this->queryObject->structure['SELECT'] as $index => $fieldInfo) {
				// Assemble list of fields per table
				// The name of the field is used both as key and value, but the value will be replaced by the fields' labels in getLocalizedLabels()
			if (!isset($this->queryFields[$fieldInfo['tableAlias']])) {
				$this->queryFields[$fieldInfo['tableAlias']] = array('name' => $fieldInfo['table'], 'table' => $fieldInfo['tableAlias'], 'fields' => array());
			}
			$this->queryFields[$fieldInfo['tableAlias']]['fields'][] = array('name' => $fieldInfo['field'], 'function' => $fieldInfo['function']);

				// Assemble full names for each field
				// The full name is:
				//	1) the name of the table or its alias
				//	2) a dot
				//	3) the name of the field
				//
				// => If it's the main table and there's an alias for the field
				//
				//	4a) AS and the field alias
				//
				//	4a-2)	if the alias contains a dot (.) it means it contains a table name (or alias)
				//			and a field name. So we use this information
				//
				// This means something like foo.bar AS hey.you will get transformed into foo.bar AS hey$you
				//
				// In effect this means that you can "reassign" a value from one table (foo) to another (hey)
				//
				// => If it's not the main table, all fields get an alias using either their own name or the given field alias
				//
				//	4b) AS and $ and the field or its alias
				//
				// So something like foo.bar AS boink will get transformed into foo.bar AS foo$boink
				//
				//	4b-2)	like 4a-2) above, but for subtables
				//
				// The $ sign is used in class tx_dataquery_wrapper for building the data structure
				// Initialize values
			$theAlias = '';
			$mappedField = '';
			$mappedTable = '';
			$fullField = $fieldInfo['tableAlias'] . '.' . $fieldInfo['field'];
			if ($fieldInfo['function']) {
				$fullField = $fieldInfo['field'];
			}
			$theField = $fieldInfo['field'];
				// Case 4a
			if ($fieldInfo['tableAlias'] == $this->queryObject->mainTable) {
				if (empty($fieldInfo['fieldAlias'])) {
					$theAlias = $theField;
				} else {
					$fullField .= ' AS ';
					if (strpos($fieldInfo['fieldAlias'], '.') === false) {
						$theAlias = $fieldInfo['fieldAlias'];
						$mappedTable = $fieldInfo['tableAlias'];
						$mappedField = $fieldInfo['fieldAlias'];
					}
						// Case 4a-2
					else {
						list($mappedTable, $mappedField) = explode('.', $fieldInfo['fieldAlias']);
						$theAlias = str_replace('.', '$', $fieldInfo['fieldAlias']);
					}
					$fullField .= $theAlias;
				}
			} else {
				$fullField .= ' AS ';
				if (empty($fieldInfo['fieldAlias'])) {
					$theAlias = $fieldInfo['tableAlias'] . '$' . $fieldInfo['field'];
				}
				else {
						// Case 4b
					if (strpos($fieldInfo['fieldAlias'], '.') === false) {
						$theAlias = $fieldInfo['tableAlias'] . '$' . $fieldInfo['fieldAlias'];
					}
						// Case 4b-2
					else {
						list($mappedTable, $mappedField) = explode('.', $fieldInfo['fieldAlias']);
						$theAlias = str_replace('.', '$', $fieldInfo['fieldAlias']);
					}
				}
				$fullField .= $theAlias;
			}
			if (empty($mappedTable)) {
				$mappedTable = $fieldInfo['tableAlias'];
				$mappedField = $theField;
			}
			$this->fieldTrueNames[$theAlias] = array(
													'table' => $fieldInfo['table'],
													'aliasTable' => $fieldInfo['tableAlias'],
													'field' => $theField,
													'mapping' => array('table' => $mappedTable, 'field' => $mappedField)
												);
			$this->queryObject->structure['SELECT'][$index] = $fullField;
        }

			// Add the uid field to tables that don't have it yet
        foreach ($this->queryObject->hasUidField as $alias => $flag) {
        	if (!$flag) {
					// Get all fields for the given table
				$fieldsInfo = $GLOBALS['TYPO3_DB']->admin_get_fields($this->queryObject->aliases[$alias]);
					// Add the uid field only if it exists
				if (isset($fieldsInfo['uid'])) {
					$fullField = $alias . '.uid';
					$theField = 'uid';
					$fieldAlias = 'uid';
					if ($alias != $this->queryObject->mainTable) {
						$fieldAlias = $alias . '$uid';
						$fullField .= ' AS ' . $fieldAlias;
					}
					$this->fieldTrueNames[$fieldAlias] = array(
															'table' => $this->getTrueTableName($alias),
															'aliasTable' => $alias,
															'field' => $theField,
															'mapping' => array('table' => $alias, 'field' => $theField)
														);
					$this->queryObject->structure['SELECT'][] = $fullField;
					$this->queryFields[$alias]['fields'][] = array('name' => 'uid', 'function' => FALSE);
				}
        	}
        }
//t3lib_div::debug($this->queryObject->aliases, 'Table aliases');
//t3lib_div::debug($this->fieldAliases, 'Field aliases');
//t3lib_div::debug($this->fieldTrueNames, 'Field true names');
//t3lib_div::debug($this->queryFields, 'Query fields');
//t3lib_div::debug($this->queryObject->structure, 'Structure');
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

			// If no language object is available, create one
        } else {
			require_once(PATH_typo3 . 'sysext/lang/lang.php');
			$lang = t3lib_div::makeInstance('language');
			$languageCode = '';
				// Find out which language to use
			if (empty($language)) {
					// If in the BE, it's taken from the user's preferences
				if (TYPO3_MODE == 'BE') {
					$languageCode = $GLOBALS['BE_USER']->uc['lang'];

					// In the FE, we use the config.language TS property
                } else {
					if (isset($GLOBALS['TSFE']->tmpl->setup['config.']['language'])) {
						$languageCode = $GLOBALS['TSFE']->tmpl->setup['config.']['language'];
					}
                }
            } else {
				$languageCode = $language;
            }
			$lang->init($languageCode);
		}

			// Include the full TCA ctrl section
		if (TYPO3_MODE == 'FE') {
			$GLOBALS['TSFE']->includeTCA();
		}
			// Now that we have a properly initialised language object,
			// loop on all labels and get any existing localised string
		$localizedStructure = array();
		foreach ($this->queryFields as $alias => $tableData) {
			$table = $tableData['name'];
				// Initialize structure for table, if not already done
			if (!isset($localizedStructure[$alias])) {
				$localizedStructure[$alias] = array('table' => $table, 'fields' => array());
			}
				// Load the full TCA for the table
			t3lib_div::loadTCA($table);
				// Get the labels for the tables
			if (isset($GLOBALS['TCA'][$table]['ctrl']['title'])) {
				$tableName = $lang->sL($GLOBALS['TCA'][$table]['ctrl']['title']);
				$localizedStructure[$alias]['name'] = $tableName;
			}
			else {
				$localizedStructure[$alias]['name'] = $table;
			}
				// Get the labels for the fields
			foreach ($tableData['fields'] as $fieldData) {
					// Set default values
				$tableAlias = $alias;
				$field = $fieldData['name'];
					// Get the localized label, if it exists, otherwise use field name
					// Skip if it's a function (it will have no TCA definition anyway)
				$fieldName = $field;
				if (!$fieldData['function'] && isset($GLOBALS['TCA'][$table]['columns'][$fieldData['name']]['label'])) {
					$fieldName = $lang->sL($GLOBALS['TCA'][$table]['columns'][$fieldData['name']]['label']);
				}
					// Check if the field has an alias, if yes use it
					// Otherwise use the field name itself as an alias
				$fieldAlias = $field;
				if (isset($this->queryObject->fieldAliases[$alias][$field])) {
					$fieldAlias = $this->queryObject->fieldAliases[$alias][$field];
						// If the alias contains a dot (.), it means it contains the alias of a table name
						// Explode the name on the dot and use the parts as a new table alias and field name
					if (strpos($fieldAlias, '.') !== false) {
						list($tableAlias, $fieldAlias) = t3lib_div::trimExplode('.', $fieldAlias);
							// Initialize structure for table, if not already done
						if (!isset($localizedStructure[$tableAlias])) $localizedStructure[$tableAlias] = array('table' => $tableAlias, 'fields' => array());
					}
				}
					// Store the localized label
				$localizedStructure[$tableAlias]['fields'][$fieldAlias] = $fieldName;
            }
        }
//		t3lib_div::debug($localizedStructure, 'Localized structure');
		return $localizedStructure;
    }

	/**
	 * This method returns an associative array containing information for method enableFields.
	 * enableFields() will skipped each fields from the returned array.
	 *
	 * @param	array		$settings: database record corresponding to the current Data Query
	 * 						(this may contain flags *disabling* the use of enable fields or language overlays)
	 * @param	string		$tableName: the name of the table
	 * @return	array		the array containing the keys to be ignored
	 */
	protected function getIgnoreArray($settings, $tableName) {
		$ignore_array = array();
			// Handle case when some fields should be partially excluded from enableFields()
		if ($settings['ignore_enable_fields'] == '2') {

				// starttime / endtime field
			$tableNames = explode(',', $settings['ignore_starttime_endtime_tablename']);
			array_map('trim', $tableNames);
			if (in_array($tableName, $tableNames) ||
					$settings['ignore_starttime_endtime_tablename'] == '' ||
					$settings['ignore_starttime_endtime_tablename'] == '*') {
				$ignore_array['starttime'] = TRUE;
				$ignore_array['endtime'] = TRUE;
			}

				// disabled field
			$tableNames = explode(',', $settings['ignore_disabled_tablename']);
			array_map('trim', $tableNames);
			if ($settings['ignore_disabled_tablename'] == $tableName ||
					$settings['ignore_disabled_tablename'] == '' ||
					$settings['ignore_disabled_tablename'] == '*') {
				$ignore_array['disabled'] = TRUE;
			}

				// fe_group field
			$tableNames = explode(',', $settings['ignore_fegroup_tablename']);
			array_map('trim', $tableNames);
			if ($settings['ignore_fegroup_tablename'] == $tableName ||
					$settings['ignore_fegroup_tablename'] == '' ||
					$settings['ignore_fegroup_tablename'] == '*') {
				$ignore_array['fe_group'] = TRUE;
			}
		}
		return $ignore_array;
	}

	/**
	 * This method adds where clause elements related to typical TYPO3 control parameters:
	 *
	 * 	- the enable fields
	 * 	- the language handling
	 * 	- the versioning system
	 *
	 * @param	array		$settings: database record corresponding to the current Data Query
	 * 						(this may contain flags *disabling* the use of enable fields or language overlays)
	 * @return	void
	 */
	public function addTypo3Mechanisms($settings) {

			// Add the enable fields, first to the main table
		if ($settings['ignore_enable_fields'] == '0' || $settings['ignore_enable_fields'] == '2') {
			
				// Defines variables for this part
			$table = $this->queryObject->aliases[$this->queryObject->mainTable];
			$showHidden = $table == 'pages' ? $GLOBALS['TSFE']->showHiddenPage : $GLOBALS['TSFE']->showHiddenRecords;
			$ignore_array = $this->getIgnoreArray($settings, $table);

			$enableClause = tx_overlays::getEnableFieldsCondition($this->queryObject->aliases[$this->queryObject->mainTable], $showHidden, $ignore_array);
			if ($this->queryObject->mainTable != $this->queryObject->aliases[$this->queryObject->mainTable]) {
				$enableClause = str_replace($this->queryObject->aliases[$this->queryObject->mainTable], $this->queryObject->mainTable, $enableClause);
			}
			$this->addWhereClause($enableClause);

				// Add enable fields to JOINed tables
			if (isset($this->queryObject->structure['JOIN']) && is_array($this->queryObject->structure['JOIN'])) {
				foreach ($this->queryObject->structure['JOIN'] as $tableIndex => $joinData) {
					
					// Defines variables for this part
					$table = $joinData['table'];
					$showHidden = $table == 'pages' ? $GLOBALS['TSFE']->showHiddenPage : $GLOBALS['TSFE']->showHiddenRecords;
					$ignore_array = $this->getIgnoreArray($settings, $table);

					$enableClause = tx_overlays::getEnableFieldsCondition($table, $showHidden, $ignore_array);
					if (!empty($enableClause)) {
						if ($table != $joinData['alias']) {
							$enableClause = str_replace($table, $joinData['alias'], $enableClause);
						}
						if (!empty($this->queryObject->structure['JOIN'][$tableIndex]['on'])) {
							$this->queryObject->structure['JOIN'][$tableIndex]['on'] .= ' AND ';
						}
						$this->queryObject->structure['JOIN'][$tableIndex]['on'] .= '('.$enableClause.')';
					}
				}
			}
		}

			// Add the language condition, if necessary
		if (empty($settings['ignore_language_handling']) && !$this->queryObject->structure['DISTINCT']) {

				// Add the DB fields and the SQL conditions necessary for having everything ready to handle overlays
				// as per the standard TYPO3 mechanism
				// Loop on all tables involved
			foreach ($this->queryFields as $alias => $tableData) {
				$table = $tableData['table'];

					// First check which handling applies, based on existing TCA structure
					// The table must at least have a language field or point to a foreign table for translation
				if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField']) || isset($GLOBALS['TCA'][$table]['ctrl']['transForeignTable'])) {

						// The table uses translations in the same table (transOrigPointerField) or in a foreign table (transForeignTable)
						// Prepare for overlays
					if (isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField']) || isset($GLOBALS['TCA'][$table]['ctrl']['transForeignTable'])) {
							// Assemble a list of all fields for the table,
							// skipping function calls (which can't be overlayed anyway)
						$fields = array();
						foreach ($tableData['fields'] as $fieldData) {
							if (!$fieldData['function']) {
								$fields[] = $fieldData['name'];
							}
						}
							// For each table, make sure that the fields necessary for handling the language overlay are included in the list of selected fields
						try {
							$fieldsForOverlay = tx_overlays::selectOverlayFields($table, implode(',', $fields));
							$fieldsForOverlayArray = t3lib_div::trimExplode(',', $fieldsForOverlay);
								// Strip the "[table name]." prefix
							$numFields = count($fieldsForOverlayArray);
							for ($i = 0; $i < $numFields; $i++) {
								$fieldsForOverlayArray[$i] = str_replace($table . '.', '', $fieldsForOverlayArray[$i]);
							}
								// Extract which fields were added and add them to the list of fields to select
							$addedFields = array_diff($fieldsForOverlayArray, $fields);
							if (count($addedFields) > 0) {
								foreach ($addedFields as $aField) {
									$newFieldName = $alias . '.' . $aField;
									$newFieldAlias = $alias . '$' . $aField;
									$this->queryObject->structure['SELECT'][] = $newFieldName . ' AS ' . $newFieldAlias;
									$this->queryFields[$table]['fields'][] = array('name' => $aField, 'function' => FALSE);
									$this->fieldTrueNames[$newFieldAlias] = array(
																				'table' => $table,
																				'aliasTable' => $alias,
																				'field' => $aField,
																				'mapping' => array('table' => $alias, 'field' => $aField)
																			);
								}
							}
							$this->doOverlays[$table] = true;
								// Add the language condition for the given table (only for tables containing their own translations)
							if (isset($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])) {
								$languageCondition = '(' . tx_overlays::getLanguageCondition($table) . ')';
								if ($table != $alias) {
									$languageCondition = str_replace($table, $alias, $languageCondition);
								}
								if ($alias == $this->queryObject->mainTable) {
									$this->addWhereClause($languageCondition);
								} else {
									if (!empty($this->queryObject->structure['JOIN'][$alias]['on'])) {
										$this->queryObject->structure['JOIN'][$alias]['on'] .= ' AND ';
									}
									$this->queryObject->structure['JOIN'][$alias]['on'] .= $languageCondition;
								}
							}
						}
						catch (Exception $e) {
							$this->doOverlays[$table] = false;
						}
					}

					// The table simply contains a language flag.
					// This is just about adding the proper condition on the language field and nothing more
					// No overlays will be handled at a later time
				} else {
					if (isset($GLOBALS['TCA'][$table]['ctrl']['languageField'])) {
							// Take language that corresponds to current language or [All]
						$languageCondition = $alias . '.' . $GLOBALS['TCA'][$table]['ctrl']['languageField'] . ' IN (' . $GLOBALS['TSFE']->sys_language_content . ', -1)';
						if ($alias == $this->queryObject->mainTable) {
							$this->addWhereClause($languageCondition);
						} else {
							if (!empty($this->queryObject->structure['JOIN'][$alias]['on'])) {
								$this->queryObject->structure['JOIN'][$alias]['on'] .= ' AND ';
							}
							$this->queryObject->structure['JOIN'][$alias]['on'] .= '(' . $languageCondition . ')';
						}
					}
				}
			}
		}
			// Add workspace condition (always)
			// Make sure to take only records from live workspace
			// NOTE: Other workspaces are not handled, preview will not work
		foreach ($this->queryFields as $alias => $tableData) {
			$table = $tableData['table'];
			if (!empty($GLOBALS['TCA'][$table]['ctrl']['versioningWS'])) {
				$workspaceCondition = $alias . ".t3ver_oid = '0'";
				if ($alias == $this->queryObject->mainTable) {
					$this->addWhereClause($workspaceCondition);
				} else {
					if (!empty($this->queryObject->structure['JOIN'][$alias]['on'])) {
						$this->queryObject->structure['JOIN'][$alias]['on'] .= ' AND ';
					}
					$this->queryObject->structure['JOIN'][$alias]['on'] .= '(' . $workspaceCondition . ')';
				}
			}
		}
//t3lib_div::debug($this->doOverlays);
	}

	/**
	 * This method takes a Data Filter structure and processes its instructions
	 *
	 * @param	array		$filter: Data Filter structure
	 * @return	void
	 */
	public function addFilter($filter) {
			// First handle the "filter" part, which will be turned into part of a SQL WHERE clause
		$completeFilters = array();
		$logicalOperator = (empty($filter['logicalOperator'])) ? 'AND' : $filter['logicalOperator'];
		if (isset($filter['filters']) && is_array($filter['filters'])) {
			foreach ($filter['filters'] as $filterData) {
				$table = (empty($filterData['table'])) ? $this->queryObject->mainTable : $filterData['table'];
				$field = $filterData['field'];
				$fullField = $table . '.' . $field;
					// If the field is an alias, override full field definition
					// to whatever the alias is mapped to
				if (isset($this->queryObject->fieldAliasMappings[$field])) {
					$fullField = $this->queryObject->fieldAliasMappings[$field];
				}
				$condition = '';
					// Define table on which to apply the condition
					// Conditions will normally be applied in the WHERE clause
					// if the table is the main one, otherwise it is applied
					// in the ON clause of the relevant JOIN statement
					// However the application of the condition may be forced to be in the WHERE clause,
					// no matter which table it targets
				$tableForApplication = $table;
				if ($filterData['main']) {
					$tableForApplication = $this->queryObject->mainTable;
				}
				foreach ($filterData['conditions'] as $conditionData) {
					if (!empty($condition)) {
						$condition .= ' AND ';
					}
						// Some operators require a bit more handling
						// "in" values just need to be put within brackets
					if ($conditionData['operator'] == 'in') {
						$condition .= $fullField . ' IN (' . $conditionData['value'] . ')';

						// "andgroup" and "orgroup" requires more handling
						// The associated value is a list of comma-separated values and each of these values must be handled separately
						// Furthermore each value will be tested against a comma-separated list of values too, so the test is not so simple
					} elseif ($conditionData['operator'] == 'andgroup' || $conditionData['operator'] == 'orgroup') {
						$values = explode(',', $conditionData['value']);
						$localCondition = '';
						$localOperator = 'OR';
						if ($conditionData['operator'] == 'andgroup') {
							$localOperator = 'AND';
						}
						foreach ($values as $aValue) {
							if (!empty($localCondition)) {
								$localCondition .= ' ' . $localOperator . ' ';
							}
							$localCondition .= $GLOBALS['TYPO3_DB']->listQuery($fullField, $aValue, $table);
						}
						$condition .= $localCondition;

						// If the operator is "like", "start" or "end", the SQL operator is always LIKE, but different wildcards are used
					} elseif ($conditionData['operator'] == 'like' || $conditionData['operator'] == 'start' || $conditionData['operator'] == 'end') {
						$value = '';
						if ($conditionData['operator'] == 'start') {
							$value = $conditionData['value'] . '%';
						} elseif ($conditionData['operator'] == 'end') {
							$value = '%' . $conditionData['value'];
						} else {
							$value = '%' . $conditionData['value'] . '%';
						}
						$condition .= $fullField . ' LIKE ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($value, $table);

						// Other operators are handled simply
						// We just need to take care of special values: "\empty", "\null" and "\all"
					} else {
						$operator = $conditionData['operator'];
						$quotedValue = '';
							// If the value is special value "\all", all values must be taken,
							// so the condition is simply ignored
						if ($conditionData['value'] != '\all') {
								// Special value "\empty" means evaluation against empty string
							if ($conditionData['value'] == '\empty') {
								$quotedValue = "''";

								// Special value "\null" means evaluation against IS NULL or IS NOT NULL
							} elseif ($conditionData['value'] == '\null') {
								if ($operator == '=') {
									$operator = 'IS';
								} else {
									$operator = 'IS NOT';
								}
								$quotedValue = 'NULL';

								// Normal value
							} else {
								$quotedValue = $GLOBALS['TYPO3_DB']->fullQuoteStr($conditionData['value'], $table);
							}
							$condition .= $fullField . ' ' . $operator . ' ' . $quotedValue;
						}
					}
				}
					// Add the condition only if it wasn't empty
				if (!empty($condition)) {
					if (empty($completeFilters[$tableForApplication])) {
						$completeFilters[$tableForApplication] = '';
					} else {
						$completeFilters[$tableForApplication] .= ' ' . $logicalOperator . ' ';
					}
					$completeFilters[$tableForApplication] .= '(' . $condition . ')';
				}
			}
			foreach ($completeFilters as $table => $whereClause) {
				if ($table == $this->queryObject->mainTable) {
					$this->addWhereClause($whereClause);
				} elseif (in_array($table, $this->queryObject->subtables)) {
					if (!empty($this->queryObject->structure['JOIN'][$table]['on'])) {
						$this->queryObject->structure['JOIN'][$table]['on'] .= ' AND ';
					}
					$this->queryObject->structure['JOIN'][$table]['on'] .= $whereClause;
				}
			}
				// Free some mempory
			unset($completeFilters);
		}
			// Add the eventual raw SQL in the filter
			// Raw SQL is always added to the main where clause
		if (!empty($filter['rawSQL'])) {
			$this->addWhereClause($filter['rawSQL']);
		}
			// Handle the order by clauses
		if (count($filter['orderby']) > 0) {
			foreach ($filter['orderby'] as $orderData) {
				$completeField = ((empty($orderData['table'])) ? $this->queryObject->mainTable : $orderData['table']) . '.' . $orderData['field'];
				$orderbyClause = $completeField . ' ' . $orderData['order'];
				$this->queryObject->structure['ORDER BY'][] = $orderbyClause;
				$this->queryObject->orderFields[] = array('field' => $completeField, 'order' => $orderData['order']);
			}
		}
	}

	/**
	 * This method takes a list of uid's prepended by their table name,
	 * as returned in the "uidListWithTable" property of a idList-type SDS,
	 * and makes it into appropriate SQL IN conditions for every table that matches those used in the query
	 *
	 * @param	string		$idList: Comma-separated list of uid's prepended by their table name
	 * @return	void
	 */
	public function addIdList($idList) {
		if (!empty($idList)) {
			$idArray = t3lib_div::trimExplode(',', $idList);
			$idlistsPerTable = array();
				// First assemble a list of all uid's for each table
			foreach ($idArray as $item) {
				$table = '';
					// Code inspired from t3lib_loadDBGroup
					// String is reversed before exploding, to get uid first
				list($uid, $table) = explode('_', strrev($item), 2);
					// Exploded parts are reversed back
				$uid = strrev($uid);
					// If table is not defined, assume it's the main table
				if (empty($table)) {
					$table = $this->queryObject->mainTable;
				} else {
					$table = strrev($table);
				}
				if (!isset($idlistsPerTable[$table])) {
					$idlistsPerTable[$table] = array();
				}
				$idlistsPerTable[$table][] = $uid;
			}
				// Loop on all tables and add test on list of uid's, if table is indeed in query
			foreach ($idlistsPerTable as $table => $uidArray) {
				$condition = $table . '.uid IN (' . implode(',', $uidArray) . ')';
				if ($table == $this->queryObject->mainTable) {
					$this->addWhereClause($condition);
				} elseif (in_array($table, $this->queryObject->subtables)) {
					if (!empty($this->queryObject->structure['JOIN'][$table]['on'])) {
						$this->queryObject->structure['JOIN'][$table]['on'] .= ' AND ';
					}
					$this->queryObject->structure['JOIN'][$table]['on'] .= $condition;
				}
			}
				// Free some memory
			unset($idlistsPerTable);
		}
	}

	/**
	 * This method builds up the query with all the data stored in the structure
	 *
	 * @return	string		the assembled SQL query
	 */
	public function buildQuery() {
			// First check what to do with ORDER BY fields
		$this->preprocessOrderByFields();
			// Start assembling the query
		$query  = 'SELECT ';
		if ($this->queryObject->structure['DISTINCT']) {
			$query .= 'DISTINCT ';
		}
		$query .= implode(', ', $this->queryObject->structure['SELECT']) . ' ';
		$query .= 'FROM ' . $this->queryObject->structure['FROM']['table'];
		if (!empty($this->queryObject->structure['FROM']['alias'])) {
			$query .= ' AS ' . $this->queryObject->structure['FROM']['alias'];
		}
		$query .= ' ';
		if (isset($this->queryObject->structure['JOIN'])) {
			foreach ($this->queryObject->structure['JOIN'] as $theJoin) {
				$query .= strtoupper($theJoin['type']) . ' JOIN ' . $theJoin['table'];
				if (!empty($theJoin['alias'])) {
					$query .= ' AS ' . $theJoin['alias'];
				}
				if (!empty($theJoin['on'])) {
					$query .= ' ON ' . $theJoin['on'];
				}
				$query .= ' ';
			}
		}
		if (count($this->queryObject->structure['WHERE']) > 0) {
			$whereClause = '';
			foreach ($this->queryObject->structure['WHERE'] as $clause) {
				if (!empty($whereClause)) {
					$whereClause .= ' AND ';
				}
				$whereClause .= $clause;
			}
			$query .= 'WHERE ' . $whereClause . ' ';
		}
		if (count($this->queryObject->structure['GROUP BY']) > 0) {
			$query .= 'GROUP BY ' . implode(', ', $this->queryObject->structure['GROUP BY']) . ' ';
		}
			// Add order by clause if defined and if applicable (see preprocessOrderByFields())
		if ($this->processOrderBy && count($this->queryObject->structure['ORDER BY']) > 0) {
			$query .= 'ORDER BY ' . implode(', ', $this->queryObject->structure['ORDER BY']) . ' ';
		}
		if (isset($this->queryObject->structure['LIMIT'])) {
			$query .= 'LIMIT ' . $this->queryObject->structure['LIMIT'];
			if (isset($this->queryObject->structure['OFFSET'])) {
				$query .= ' OFFSET ' . $this->queryObject->structure['OFFSET'];
			}
		}
//t3lib_div::debug($query);
		return $query;
	}

	/**
	 * This method performs some operations on the fields used for ordering the query, if any
	 * If the language is not the default one, order may not be desirable in SQL
	 * As translations are handled using overlays in TYPO3, it is not possible
	 * to sort the records alphabetically in the SQL statement, because the SQL
	 * statement gets only the records in original language
	 *
	 * @return	boolean		true if order by must be processed by the SQL query, false otherwise
	 */
	protected function preprocessOrderByFields() {
/*
t3lib_div::debug($this->queryObject->orderFields, 'Order fields');
t3lib_div::debug($this->queryObject->fieldAliases, 'Field aliases');
t3lib_div::debug($this->fieldTrueNames, 'Field true names');
t3lib_div::debug($this->queryFields, 'Query fields');
t3lib_div::debug($this->queryObject->structure['SELECT'], 'Select structure');
 *
 */
		if (count($this->queryObject->orderFields) > 0) {
				// If in the FE context and not the default language, start checking for possible use of SQL or not
			if (TYPO3_MODE == 'FE' && $GLOBALS['TSFE']->sys_language_content > 0) {
					// Include the complete ctrl TCA
				$GLOBALS['TSFE']->includeTCA();
					// Initialise sorting mode flag
				$cannotUseSQLForSorting = false;
					// Initialise various arrays
				$newQueryFields = array();
				$newSelectFields = array();
				$newTrueNames = array();
				$countNewFields = 0;
				foreach ($this->queryObject->orderFields as $index => $orderInfo) {
					$alias = '';
					$field = '';
						// Define the table and field names
					$fieldParts = explode('.', $orderInfo['field']);
					if (count($fieldParts) == 1) {
						$alias = $this->queryObject->mainTable;
						$field = $fieldParts[0];
					} else {
						$alias = $fieldParts[0];
						$field = $fieldParts[1];
					}
						// If the field has an alias, change the order fields list to use it
					if (isset($this->queryObject->fieldAliases[$alias][$field])) {
						$this->queryObject->orderFields[$index]['alias'] = $this->queryObject->orderFields[$index]['field'];
						$this->queryObject->orderFields[$index]['field'] = $this->queryObject->fieldAliases[$alias][$field];
					}
						// Get the field's true table and name, if defined, in case an alias is used in the ORDER BY statement
					if (isset($this->fieldTrueNames[$field])) {
						$alias = $this->fieldTrueNames[$field]['aliasTable'];
						$field = $this->fieldTrueNames[$field]['field'];
					}
						// Get the true table name and initialise new field array, if necessary
					$table = $this->getTrueTableName($alias);
					if (!isset($newQueryFields[$alias])) {
						$newQueryFields[$alias] = array('name' => $alias, 'table' => $table, 'fields' => array());
					}

						// Check the type of the field in the TCA
						// If the field is of some text type and that the table uses overlays,
						// ordering cannot happen in SQL.
					if (isset($GLOBALS['TCA'][$table])) {
							// Check if table uses overlays
						$usesOverlay = isset($GLOBALS['TCA'][$table]['ctrl']['languageField']) || isset($GLOBALS['TCA'][$table]['ctrl']['transForeignTable']);
							// Check the field type (load full TCA first)
							// NOTE: if there's no TCA available, we'll assume it's a text field
							// We could query the database and get the SQL datatype, but is it worth it?
						t3lib_div::loadTCA($table);
						$isTextField = TRUE;
						if (isset($GLOBALS['TCA'][$table]['columns'][$field])) {
							$fieldConfig = $GLOBALS['TCA'][$table]['columns'][$field]['config'];
								// It's text, easy :-)
							if ($fieldConfig['type'] == 'text') {
								$isTextField = TRUE;

								// It's input, further check the "eval" property
							} elseif ($fieldConfig['type'] == 'input') {
									// If the field has no eval property, assume it's just text
								if (empty($fieldConfig['eval'])) {
 									$isTextField = TRUE;
								} else {
									$evaluations = explode(',', $fieldConfig['eval']);
										// Check if some eval types are common to both array. If yes, it's not a text field.
									$foundTypes = array_intersect($evaluations, self::$notTextTypes);
									$isTextField = (count($foundTypes) > 0) ? FALSE : TRUE;
								}

								// It's another type, it's definitely not text
							} else {
								$isTextField = FALSE;
							}
						}
						$cannotUseSQLForSorting |= ($usesOverlay && $isTextField);
					}
						// Check if the field is already part of the SELECTed fields (under its true name or an alias)
						// If not, get ready to add it by defining all necessary info in temporary arrays
						// (it will be added only if necessary, i.e. if at least one field needs to be ordered later)
					if (!$this->isAQueryField($alias, $field) && !isset($this->queryObject->fieldAliases[$alias][$field])) {
						$fieldAlias = $alias . '$' . $field;
						$newQueryFields[$alias]['fields'][] = array('name' => $field, 'function' => FALSE);
						$newSelectFields[] = $alias . '.' . $field . ' AS ' . $fieldAlias;
						$newTrueNames[$fieldAlias] = array(
														'table' => $table,
														'aliasTable' => $alias,
														'field' => $field,
														'mapping' => array('table' => $alias, 'field' => $field)
													);
						$countNewFields++;
					}
				}
					// If sorting cannot be left simply to SQL, prepare to return false
					// and add the necessary fields to the SELECT statement
				if ($cannotUseSQLForSorting) {
					if ($countNewFields > 0) {
						$this->queryFields = t3lib_div::array_merge_recursive_overrule($this->queryFields, $newQueryFields);
						$this->queryObject->structure['SELECT'] = array_merge($this->queryObject->structure['SELECT'], $newSelectFields);
						$this->fieldTrueNames = t3lib_div::array_merge_recursive_overrule($this->fieldTrueNames, $newTrueNames);
/*
t3lib_div::debug($newQueryFields, 'New query fields');
t3lib_div::debug($this->queryFields, 'Updated query fields');
t3lib_div::debug($newTrueNames, 'New field true names');
t3lib_div::debug($this->fieldTrueNames, 'Updated field true names');
t3lib_div::debug($newSelectFields, 'New select fields');
t3lib_div::debug($this->queryObject->structure['SELECT'], 'Updated select structure');
 *
 */
							// Free some memory
						unset($newQueryFields);
						unset($newSelectFields);
						unset($newTrueNames);
					}
					$this->processOrderBy = FALSE;
				} else {
					$this->processOrderBy = TRUE;
				}
			} else {
				$this->processOrderBy = TRUE;
			}
		} else {
			$this->processOrderBy = TRUE;
		}
	}

	/**
	 * This is an internal utility method that checks whether a given field
	 * can be found in the fields reference list (i.e. $this->queryFields) for
	 * a given table
	 *
	 * @param	string		$table: name of the table inside which to look up
	 * @param	string		$field: name of the field to search for
	 * @return	boolean		True if the field was found, false otherwise
	 */
	protected function isAQueryField($table, $field) {
		$isAQueryField = FALSE;
		foreach ($this->queryFields[$table]['fields'] as $fieldData) {
			if ($fieldData['name'] == $field) {
				$isAQueryField = TRUE;
				break;
			}
		}
		return $isAQueryField;
	}

// Setters and getters

	/**
	 * Add a condition for the WHERE clause
	 *
	 * @param	string		SQL WHERE clause (without WHERE)
	 * @return	void
	 */
	public function addWhereClause($clause) {
		if (!empty($clause)) {
			$this->queryObject->structure['WHERE'][] = $clause;
		}
	}

	/**
	 * This method returns the structure of the parsed query
	 * There should be little real-life uses for this, but it is used by the
	 * test case to get the parsed structure
	 * 
	 * @return	array	The parsed query
	 */
	public function getQueryStructure() {
		return $this->queryObject->structure;
	}

	/**
	 * This method returns the name (alias) of the main table of the query,
	 * which is the table name that appears in the FROM clause, or the alias, if any
	 *
	 * @return	string		main table name (alias)
	 */
	public function getMainTableName() {
		return $this->queryObject->mainTable;
	}

	/**
	 * This method returns an array containing the list of all subtables in the query,
	 * i.e. the tables that appear in any of the JOIN statements
	 *
	 * @return	array		names of all the joined tables
	 */
	public function getSubtablesNames() {
		return $this->queryObject->subtables;
	}

	/**
	 * This method takes an alias and returns the true table name
	 *
	 * @param	string		$alias: alias of a table
	 * @return	string		True name of the corresponding table
	 */
	public function getTrueTableName($alias) {
		return $this->queryObject->aliases[$alias];
	}

	/**
	 * This method takes the alias and returns it's true name
	 * The alias is the full alias as used in the query (e.g. table$field)
	 *
	 * @param	string		$alias: alias of a field
	 * @return	array		Array with the true name of the corresponding field
	 *						and the true name of the table it belongs and the alias of that table
	 */
	public function getTrueFieldName($alias) {
		$trueNameInformation = $this->fieldTrueNames[$alias];
			// Assemble field key (possibly disambiguated with function name)
		$fieldKey = $trueNameInformation['field'];
//		if (!empty($trueNameInformation['function'])) {
//			$fieldKey .= '_' . $trueNameInformation['function'];
//		}
			// If the field has an explicit alias, we must also pass back that information
		if (isset($this->queryObject->fieldAliases[$trueNameInformation['aliasTable']][$fieldKey])) {
			$alias = $this->queryObject->fieldAliases[$trueNameInformation['aliasTable']][$fieldKey];
				// Check if the alias contains a table name
				// If yes, strip it, as this information is already handled
			$table = '';
			$field = '';
			if (strpos($alias, '.') !== FALSE) {
				list($table, $field) = explode('.', $alias);
				$alias = $field;
			}
			$trueNameInformation['mapping']['alias'] = $alias;
		}
		return $trueNameInformation;
	}

	/**
	 * This method returns the list of fields defined for ordering the data
	 * 
	 * @return	array	Fields for ordering (and sort order)
	 */
	public function getOrderByFields() {
		return $this->queryObject->orderFields;
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
	 * @param	string		$table: true name of the table to handle
	 * @return	boolean		true if language overlay must and can be performed, false otherwise
	 * @see tx_dataquery_parser::addTypo3Mechanisms()
	 */
	public function mustHandleLanguageOverlay($table) {
		return (isset($this->doOverlays[$table])) ? $this->doOverlays[$table] : FALSE;
	}

	/**
	 * This method returns whether the ordering of the records was done in the SQL query
	 * or not
	 * 
	 * @return	boolean	true if SQL was used, false otherwise
	 */
	public function isSqlUsedForOrdering() {
		return $this->processOrderBy;
	}

	/**
	 * This method returns true if any ordering has been defined at all
	 * False otherwise
	 *
	 * @return	boolean	true if there's at least one ordering criterion, false otherwise
	 */
	public function hasOrdering() {
		return count($this->queryObject->orderFields) > 0;
	}

	/**
	 * This method returns the name of the first significant table to be INNER JOINed
	 * A "significant table" is a table that has a least one field SELECTed
	 * If the first significant table is not INNER JOINed or if there are no JOINs
	 * or no INNER JOINs, an empty string is returned
	 *
	 * @return	string	alias of the first significant table, if INNER JOINed, empty string otherwise
	 */
	public function hasInnerJoinOnFirstSubtable() {
		$returnValue = '';
		if (count($this->queryObject->structure['JOIN']) > 0) {
			foreach ($this->queryObject->structure['JOIN'] as $alias => $joinInfo) {
				if (isset($this->queryFields[$alias])) {
					if ($joinInfo['type'] == 'inner') {
						$returnValue = $alias;
					}
					break;
				}
			}
		}
		return $returnValue;
	}

	/**
	 * This method can be used to get the limit that was defined for a given subtable
	 * (i.e. a JOINed table). If no limit exists, 0 is returned
	 *
	 * @param	string		$table: name of the table to find the limit for
	 * @return	integer		Value of the limit, or 0 if not defined
	 */
	public function getSubTableLimit($table) {
		return isset($this->queryObject->structure['JOIN'][$table]['limit']) ? $this->queryObject->structure['JOIN'][$table]['limit'] : 0;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_parser.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_parser.php']);
}

?>
