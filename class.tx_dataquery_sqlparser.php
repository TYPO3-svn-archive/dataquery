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

	protected $structure = array(); // Contains all components of the parsed query
	protected $mainTable; // Name (or alias if defined) of the main query table, i.e. the one in the FROM part of the query
	protected $aliases = array(); // The keys to this array are the aliases of the tables used in the query and they point to the true table names
	protected $orderFields = array(); // Array with all information of the fields used to order data
	protected $subtables = array(); // List of all subtables, i.e. tables in the JOIN statements

	/**
	 * This function parses a SQL query and extract structured information about it
	 *
	 * @param	string	$query: the SQL to parse
	 * @return	array	An associative array with information about the query
	 */
	public function parseSQL($query) {
		$this->aliases = array();
		$this->structure['DISTINCT'] = FALSE;

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
		$selectPart = implode(' FROM ', $queryParts);
		$selectedFields = trim(substr($selectPart, $selectPosition + 6));
		$this->parseSelectStatement($selectedFields);

			// Get all parts of the query, using the SQL keywords as tokens
			// The returned matches array contains the keywords matched (in position 2) and the string after each keyword (in position 3)
		$regexp = '/(' . implode('|', self::$tokens) . ')/';
		$matches = preg_split($regexp, $afterLastFrom, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
//t3lib_div::debug($regexp);
//t3lib_div::debug($query);
//t3lib_div::debug($matches, 'Matches');
			// The first position is the string that followed the main FROM keyword
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
			if (!isset($this->structure[$keyword])) $this->structure[$keyword] = array();
			switch ($keyword) {
				case 'SELECT':
					break;
				case 'FROM':
					break;
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
					$this->subtables[] = $theJoin['alias'];
					$this->aliases[$theJoin['alias']] = $theJoin['table'];
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
						$thePart = trim($part);
						$this->structure[$keyword][] = $thePart;
							// In case of ORDER BY, perform additional operation to get field name and sort order separately
						if ($keyword == 'ORDER BY') {
							$finerParts = preg_split('/\s/', $thePart, -1, PREG_SPLIT_NO_EMPTY);
							$orderField = $finerParts[0];
							$orderSort = (isset($finerParts[1])) ? $finerParts[1] : 'ASC';
							$this->orderFields[] = array('field' => $orderField, 'order' => $orderSort);
						}

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
			// Assemble the results of parsing in a single array and return it
		$result = array(
			'mainTable' => $this->mainTable,
			'subtables' => $this->subtables,
			'structure' => $this->structure,
			'aliases' => $this->aliases,
			'orderFields' => $this->orderFields
		);
		return $result;
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
			$this->structure['DISTINCT'] = TRUE;
			$croppedString = substr($select, $distinctPosition, 8);
			$select = trim($croppedString);
		}
			// Next, parse the rest of the string character by character
		$stringLenth = strlen($select);
		$openBrackets = 0;
		$currentField = '';
		for ($i = 0; $i < $stringLenth; $i++) {
			$character = $select[$i];
			switch ($character) {
					// An open bracket is the sign of a function call
					// Functions may be nested, so we count the number of open brackets
				case '(':
					$currentField .= $character;
					$openBrackets++;
					break;

					// Decrease the open bracket count
				case ')':
					$currentField .= $character;
					$openBrackets--;
					break;

					// A comma indicates that we have reached the end of a field,
					// unless there are open brackets, in which case the comma is
					// a separator of function arguments
				case ',':
						// We are at the end of a field: add it to the list of fields
						// and reset the current field
					if ($openBrackets == 0) {
						$this->structure['SELECT'][] = trim($currentField);
						$currentField = '';

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
			$this->structure['SELECT'][] = trim($currentField);
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
		$this->structure['FROM'] = array();
		$this->structure['JOIN'] = array();
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
				$this->structure['FROM']['table'] = $tableName;
				$this->structure['FROM']['alias'] = $tableAlias;
				$this->mainTable = $tableAlias;

				// Each further table in the FROM statement is registered
				// as being INNER JOINed
			} else {
				$this->structure['JOIN'][$tableAlias] = array(
					'table' => $tableName,
					'alias' => $tableAlias,
					'type' => 'inner',
					'on' => ''
				);
				$this->subtables[] = $tableAlias;
			}
			$this->aliases[$tableAlias] = $tableName;
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_sqlparser.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_sqlparser.php']);
}

?>