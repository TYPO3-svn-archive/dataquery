<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008-2010 Francois Suter (Cobweb) <typo3@cobweb.ch>
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

require_once(t3lib_extMgm::extPath('dataquery', 'class.tx_dataquery_queryobject.php'));

/**
 * SQL parser class for extension "dataquery"
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_sqlparser {
		/**
		 * @var	array	List of all the main keywords accepted in the query
		 */
	static protected $tokens = array('INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'WHERE', 'GROUP BY', 'ORDER BY', 'LIMIT', 'OFFSET', 'MERGED');

		/**
		 * @var	tx_dataquery_queryobject	Structured type containing the parts of the parsed query
		 */
	protected $queryObject;

		/**
		 *
		 * @var	integer	Number of SQL function calls inside SELECT statement
		 */
	protected $numFunctions = 0;

	/**
	 * This function parses a SQL query and extract structured information about it
	 *
	 * @param	string	$query: the SQL to parse
	 * @return	array	An associative array with information about the query
	 */
	public function parseSQL($query) {
		$this->queryObject = t3lib_div::makeInstance('tx_dataquery_queryobject');

			// First find the start of the SELECT statement
		$selectPosition = stripos($query, 'SELECT');
		if ($selectPosition === FALSE) {
			throw new tx_tesseract_exception('Missing SELECT keyword', 1272556228);
		}
			// Next find the position of the last FROM keyword
			// There may be more than one FROM keyword when some functions are used
			// (example: EXTRACT(YEAR FROM tstamp))
			// NOTE: sub-selects are not supported, but these could be a source
			// of additional FROMs
		$matches = array();
		$queryParts = preg_split('/\bFROM\b/', $query);
			// If the query was not split, FROM keyword is missing
		if (count($queryParts) == 1) {
			throw new tx_tesseract_exception('Missing FROM keyword', 1272556601);
		}
		$afterLastFrom = array_pop($queryParts);

			// Everything before the last FROM is the SELECT part
			// This is parsed last as we need information about any table aliases used in the query first
		$selectPart = implode(' FROM ', $queryParts);
		$selectedFields = trim(substr($selectPart, $selectPosition + 6));

			// Get all parts of the query after SELECT ... FROM, using the SQL keywords as tokens
			// The returned matches array contains the keywords matched (in position 2) and the string after each keyword (in position 3)
		$regexp = '/(' . implode('|', self::$tokens) . ')/';
		$matches = preg_split($regexp, $afterLastFrom, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
//t3lib_div::debug($regexp);
//t3lib_div::debug($query);
//t3lib_div::debug($matches, 'Matches');

			// The first position is the string that followed the main FROM keyword
			// Parse that information. It's important to do this first,
			// as we need to know the query' main table for later
		$fromPart = array_shift($matches);
		$this->parseFromStatement($fromPart);

			// Fill the structure array, as suited for each keyword
		$i = 0;
		$numMatches = count($matches);
		while ($i < $numMatches) {
			$keyword = $matches[$i];
			$i++;
			$value = $matches[$i];
			$i++;
			switch ($keyword) {
				case 'INNER JOIN':
				case 'LEFT JOIN':
				case 'RIGHT JOIN':
						// Extract the JOIN type (INNER, LEFT or RIGHT)
					$joinType = strtolower(substr($keyword, 0, strpos($keyword,'JOIN') - 1));
					$theJoin = array();
					$theJoin['type'] = $joinType;
						// Separate the table from the join condition
					$parts = explode('ON', $value);
						// Separate an alias from the table name
					$moreParts = t3lib_div::trimExplode('AS', $parts[0]);
					$theJoin['table'] = trim($moreParts[0]);
					if (count($moreParts) > 1) {
						$theJoin['alias'] = trim($moreParts[1]);
					}
					else {
						$theJoin['alias'] = $theJoin['table'];
					}
					$this->queryObject->subtables[] = $theJoin['alias'];
					$this->queryObject->aliases[$theJoin['alias']] = $theJoin['table'];
						// Handle the "ON" part which may contain the non-SQL keyword "MAX"
						// This keyword is not used in the SQL query, but is an indication to the wrapper that
						// we want only a single record from this join
					if (count($parts) > 1) {
						$moreParts = t3lib_div::trimExplode('MAX', $parts[1]);
						$theJoin['on'] = trim($moreParts[0]);
						if (count($moreParts) > 1) {
							$theJoin['limit'] = $moreParts[1];
						}
					}
					else {
						$theJoin['on'] = '';
					}
					if (!isset($this->queryObject->structure['JOIN'])) $this->queryObject->structure['JOIN'] = array();
					$this->queryObject->structure['JOIN'][$theJoin['alias']] = $theJoin;
					break;
				case 'WHERE':
					$this->queryObject->structure[$keyword][] = trim($value);
					break;
				case 'ORDER BY':
				case 'GROUP BY':
					$orderParts = explode(',', $value);
					foreach ($orderParts as $part) {
						$thePart = trim($part);
						$this->queryObject->structure[$keyword][] = $thePart;
							// In case of ORDER BY, perform additional operation to get field name and sort order separately
						if ($keyword == 'ORDER BY') {
							$finerParts = preg_split('/\s/', $thePart, -1, PREG_SPLIT_NO_EMPTY);
							$orderField = $finerParts[0];
							$orderSort = (isset($finerParts[1])) ? $finerParts[1] : 'ASC';
							$this->queryObject->orderFields[] = array('field' => $orderField, 'order' => $orderSort);
						}

					}
					break;
				case 'LIMIT':
					if (strpos($value, ',') !== FALSE) {
						$limitParts = t3lib_div::trimExplode(',', $value, TRUE);
						$this->queryObject->structure['OFFSET'] = intval($limitParts[0]);
						$this->queryObject->structure[$keyword] = intval($limitParts[1]);
					} else {
						$this->queryObject->structure[$keyword] = intval($value);
					}
					break;
				case 'OFFSET':
					$this->queryObject->structure[$keyword] = intval($value);
					break;

// Dataquery allows for non-standard keywords to be used in the SQL query for special purposes
// MERGED says that results from different tables are kept as single records.
// Otherwise joined tables are structured as subtables from the main table (the one in the FROM clause)
/* NOTE: this is not used anymore
 * TODO: implement usage again or drop altogether
				case 'MERGED':
					$this->isMergedResult = true;
					break;
 *
 */
			}
		}
			// Free some memory
		unset($matches);

			// Parse the SELECT part
		$this->parseSelectStatement($selectedFields);

			// Return the object containing the parsed query
		return $this->queryObject;
	}

	/**
	 * This method parses the SELECT part of the statement and isolates each field in the selection
	 *
	 * @param	string	$select: the beginning of the SQL statement, between SELECT and FROM (both excluded)
	 * @return	void
	 */
	public function parseSelectStatement($select) {

			// Parse the SELECT part
			// First, check if the select string starts with "DISTINCT"
			// If yes, remove that and set the distinct flag to true
		$distinctPosition = strpos($select, 'DISTINCT');
		if ($distinctPosition !== FALSE) {
			$this->queryObject->structure['DISTINCT'] = TRUE;
			$croppedString = substr($select, $distinctPosition + 8);
			$select = trim($croppedString);
		}
			// Next, parse the rest of the string character by character
		$stringLenth = strlen($select);
		$openBrackets = 0;
		$lastBracketPosition = 0;
		$currentField = '';
		$currentPosition = 0;
		$hasFunctionCall = FALSE;
		$hasWildcard = FALSE;
		for ($i = 0; $i < $stringLenth; $i++) {
				// Get the current character
			$character = $select[$i];
				// Count the position inside the current field
				// This is reset for each new field found
			$currentPosition++;
			switch ($character) {
					// An open bracket is the sign of a function call
					// Functions may be nested, so we count the number of open brackets
				case '(':
					$currentField .= $character;
					$openBrackets++;
					$hasFunctionCall = TRUE;
					break;

					// Decrease the open bracket count
				case ')':
					$currentField .= $character;
					$openBrackets--;
						// Store position of closing bracket (minus one), as we need the position
						// of the last one later for further processing
					$lastBracketPosition = $currentPosition - 1;
					break;

					// If the wildcard character appears outside of function calls,
					// take it into consideration. Otherwise not (it might be COUNT(*) for example)
				case '*':
					$currentField .= $character;
					if (!$hasFunctionCall) {
						$hasWildcard = TRUE;
					}
					break;

					// A comma indicates that we have reached the end of a field,
					// unless there are open brackets, in which case the comma is
					// a separator of function arguments
				case ',':
						// We are at the end of a field: add it to the list of fields
						// and reset some values
					if ($openBrackets == 0) {
						$this->parseSelectField(trim($currentField), $lastBracketPosition, $hasFunctionCall, $hasWildcard);
						$currentField = '';
						$hasFunctionCall = FALSE;
						$hasWildcard = FALSE;
						$currentPosition = 0;
						$lastBracketPosition = 0;

						// We're inside a function, keep the comma and keep the current character
					} else {
						$currentField .= $character;
					}
					break;

					// Nothing special, just add the current character to the current field's name
				default:
					$currentField .= $character;
					break;
			}
		}
			// Upon exit from the loop, save the last field found,
			// except if there's still an open bracket, in which case we have a syntax error
		if ($openBrackets > 0) {
			throw new tx_tesseract_exception('Bad SQL syntax, opening and closing brackets are not balanced', 1272954424);
		} else {
			$this->parseSelectField(trim($currentField), $lastBracketPosition, $hasFunctionCall, $hasWildcard);
		}

	}

	/**
	 * This method parses one field from the SELECT part of the SQL query and
	 * analyzes its content. In particular it will expand the "*" wildcard to include
	 * all fields. It also keeps tracks of field aliases.
	 *
	 * @param	string		$fieldString: the string to parse
	 * @param	integer		$lastBracketPosition: the position of the last closing bracket in the string, if any
	 * @param	boolean		$hasFunctionCall: true if a SQL function call was detected in the string
	 * @param	boolean		$hasWildcard: true if the wildcard character (*) was detected in the string
	 * @return	void
	 */
	protected function parseSelectField($fieldString, $lastBracketPosition = 0, $hasFunctionCall = FALSE, $hasWildcard = FALSE) {
			// Exit early if field string is empty
		if (empty($fieldString)) {
			return;
		}
		$table = '';
		$alias = '';

			// If the string is just * (or possibly table.*), get all the fields for the table
		if ($hasWildcard) {
				// It's only *, set table as main table
			if ($fieldString === '*') {
				$table = $this->queryObject->mainTable;
				$alias = $table;

				// It's table.*, extract table name
			} else {
				$fieldParts = t3lib_div::trimExplode('.', $fieldString, 1);
				$table = (isset($this->queryObject->aliases[$fieldParts[0]]) ? $this->queryObject->aliases[$fieldParts[0]] : $fieldParts[0]);
				$alias = $fieldParts[0];
			}
			if (!isset($this->queryObject->hasUidField[$alias])) {
				$this->queryObject->hasUidField[$alias] = FALSE;
			}
				// Get all fields for the given table
			$fieldInfo = $GLOBALS['TYPO3_DB']->admin_get_fields($table);
			$fields = array_keys($fieldInfo);
				// Add all fields to the query structure
			foreach ($fields as $aField) {
				if ($aField == 'uid') {
					$this->queryObject->hasUidField[$alias] = TRUE;
				}
				$this->queryObject->structure['SELECT'][] = array(
					'table' => $table,
					'tableAlias' => $alias,
					'field' => $aField,
					'fieldAlias' => '',
					'function' => FALSE
				);
			}

			// Else, the field is some string, analyse it
		} else {

				// If there's an alias, extract it and continue parsing
				// An alias is indicated by a "AS" keyword after the last closing bracket if any
				// (brackets indicate a function call and there might be "AS" keywords inside them)
			$field = '';
			$fieldAlias = '';
			$asPosition = strpos($fieldString, ' AS ', $lastBracketPosition);
			if ($asPosition !== FALSE) {
				$fieldAlias = trim(substr($fieldString, $asPosition + 4));
				$fieldString = trim(substr($fieldString, 0, $asPosition));
			}
			if ($hasFunctionCall) {
				$this->numFunctions++;
				$alias = $this->queryObject->mainTable;
				$table = (isset($this->queryObject->aliases[$alias]) ? $this->queryObject->aliases[$alias] : $alias);
				$field = $fieldString;
					// Function calls need aliases
					// If none was given, define one
				if (empty($fieldAlias)) {
					$fieldAlias = 'function_' . $this->numFunctions;
				}

				// There's no function call
			} else {

					// If there's a dot, get table name
				if (stristr($fieldString, '.')) {
					$fieldParts = t3lib_div::trimExplode('.', $fieldString, 1);
					$table = (isset($this->queryObject->aliases[$fieldParts[0]]) ? $this->queryObject->aliases[$fieldParts[0]] : $fieldParts[0]);
					$alias = $fieldParts[0];
					$field = $fieldParts[1];

					// No dot, the table is the main one
				} else {
					$alias = $this->queryObject->mainTable;
					$table = (isset($this->queryObject->aliases[$alias]) ? $this->queryObject->aliases[$alias] : $alias);
					$field = $fieldString;
				}
			}
				// Set the appropriate flag if the field is uid
				// Initialize first, if not yet done
			if (!isset($this->queryObject->hasUidField[$alias])) {
				$this->queryObject->hasUidField[$alias] = FALSE;
			}
			if ((empty($fieldAlias) && $field == 'uid') || (!empty($fieldAlias) && $fieldAlias == 'uid')) {
				$this->queryObject->hasUidField[$alias] = TRUE;
			}
				// Add field's information to query structure
			$this->queryObject->structure['SELECT'][] = array(
				'table' => $table,
				'tableAlias' => $alias,
				'field' => $field,
				'fieldAlias' => $fieldAlias,
				'function' => $hasFunctionCall
			);

				// If there's an alias for the field, store it in a separate array, for later use
			if (!empty($fieldAlias)) {
				if (!isset($this->queryObject->fieldAliases[$alias])) {
					$this->queryObject->fieldAliases[$alias] = array();
				}
				$this->queryObject->fieldAliases[$alias][$field] = $fieldAlias;
					// Keep track of which field the alias is related to
					// (this is used by the parser to map alias used in filters)
					// If the alias is related to a function, we store the function syntax as is,
					// otherwise we map the alias to the syntax table.field
				if ($hasFunctionCall) {
					$this->queryObject->fieldAliasMappings[$fieldAlias] = $field;
				} else {
					$this->queryObject->fieldAliasMappings[$fieldAlias] = $table . '.' . $field;
				}
			}
		}
	}

	/**
	 * This method parses the FROM statement of the query,
	 * which may be comprised of a comma-separated list of tables
	 *
	 * @param	string	$from: the FROM statement
	 * @return	void
	 */
	public function parseFromStatement($from) {
		$fromTables = t3lib_div::trimExplode(',', $from, TRUE);
		$numTables = count($fromTables);
		for ($i = 0; $i < $numTables; $i++) {
			$tableName = $fromTables[$i];
			$tableAlias = $tableName;
			if (strpos($fromTables[$i], ' AS ') !== FALSE) {
				$tableParts = t3lib_div::trimExplode(' AS ', $fromTables[$i], TRUE);
				$tableName = $tableParts[0];
				$tableAlias = $tableParts[1];
			}
				// Consider the first table to be the main table of the query,
				// i.e. the table to which all others are JOINed
			if ($i == 0) {
				$this->queryObject->structure['FROM']['table'] = $tableName;
				$this->queryObject->structure['FROM']['alias'] = $tableAlias;
				$this->queryObject->mainTable = $tableAlias;

				// Each further table in the FROM statement is registered
				// as being INNER JOINed
			} else {
				$this->queryObject->structure['JOIN'][$tableAlias] = array(
					'type' => 'inner',
					'table' => $tableName,
					'alias' => $tableAlias,
					'on' => ''
				);
				$this->queryObject->subtables[] = $tableAlias;
			}
			$this->queryObject->aliases[$tableAlias] = $tableName;
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_sqlparser.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_sqlparser.php']);
}

?>