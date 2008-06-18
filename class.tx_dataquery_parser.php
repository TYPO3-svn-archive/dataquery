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
* $Id: class.tx_dataquery_parser.php 3937 2008-06-04 08:36:39Z fsuter $
***************************************************************/


/**
 * This class is used to parse a SELECT SQL query into a structured array
 *
 * @author	Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package	TYPO3
 * @subpackage	tx_dataquery
 */
class tx_dataquery_parser {
	protected  $tokens = array('SELECT', 'FROM', 'INNER JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'WHERE', 'GROUP BY', 'ORDER BY', 'LIMIT', 'OFFSET', 'MERGED');
	protected $allowedComparisons = array('eq' => '=','ne' => '!=','lt' => '<','le' => '<=','gt' => '>','ge' => '>=','like' => 'LIKE');
	protected $structure = array();
	protected $mainTable;
	protected $isMergedResult = false;
	protected $subtables = array();
	static $useDeletedFlag = 1;
	static $useEnableFields = 2;
	static $useLanguageOverlays = 4;
	static $useVersioning = 8;

	/**
	 * This method is used to parse a SELECT SQL query.
	 * It is a simple parser and no way generic. It expects queries to be written a certain way.
	 *
	 * @param	string		the query to be parsed
	 *
	 * @return	mixed		array containing the query parts or false if the query was empty or invalid
	 */
	public function parseQuery($query) {
		$query = str_replace(array("\r","\n","\f"),' ',$query);

// Get all parts of the query, using the SQL keywords as tokens
// The returned matches array contains the keywords matched (in position 2) and the string after each keyword (in position 3)

		$regexp = '/('.implode('|', $this->tokens).')/';
		$matches = preg_split($regexp, $query, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
//t3lib_div::debug($regexp);
//t3lib_div::debug($query);
//t3lib_div::debug($matches);

// Fill the structure array, as suited for each keyword

		$i = 0;
		$numMatches = count($matches);
		while ($i < $numMatches) {
//		foreach ($matches[1] as $index => $keyword) {
			$keyword = $matches[$i];
			$i++;
			$value = $matches[$i];
			$i++;
			if (!isset($this->structure[$keyword])) $this->structure[$keyword] = array();
			switch ($keyword) {
				case 'SELECT':

// Explode the select string in its constituent parts
// For each part that is not "*", does not already contain an alias (AS) and contains a dot (.)
// automatically create an alias by replacing the dot with an underscore

					$selectString = trim($value);
					$selectArray = t3lib_div::trimExplode(',', $selectString, 1);
					foreach ($selectArray as $index => $value) {
						if ($value != '*' && strpos($value, 'AS') === false && strpos($value, '.')) {
							$valueParts = t3lib_div::trimExplode('.', $value, 1);
							$selectArray[$index] = $value.' AS '.implode('$', $valueParts);
						}
					}
					$this->structure[$keyword][] = implode(',', $selectArray);
					break;
				case 'FROM':
					$fromParts = explode('AS',$value);
					$this->structure[$keyword]['table'] = trim($fromParts[0]);
					if (count($fromParts) > 1) {
						$this->structure[$keyword]['alias'] = trim($fromParts[1]);
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
					$parts = explode('ON',$value);
					$moreParts = explode('AS',$parts[0]);
					$theJoin = array();
					$theJoin['table'] = trim($moreParts[0]);
					$theJoin['type'] = $joinType;
					if (count($moreParts) > 1) {
						$theJoin['alias'] = trim($moreParts[1]);
					}
					else {
						$theJoin['alias'] = $theJoin['table'];
					}
					$this->subtables[] = $theJoin['alias'];
					if (count($parts) > 1) $theJoin['on'] = trim($parts[1]);
					if (!isset($this->structure['JOIN'])) $this->structure['JOIN'] = array();
					$this->structure['JOIN'][] = $theJoin;
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
//t3lib_div::debug($this->structure);
	}

	/**
	 * This method add where clause elements related to typical TYPO3 control parameters:
	 *
	 *	- the deleted flag
	 *	- the enable fields
	 *	- the language overlays
	 *	- the versioning system
	 *
	 * @param	integer		selected mechanisms
	 *
	 * @return	void
	 */
	public function addTypo3Mechanisms($parameters) {

// Mechanisms to use are selected using checkboxes, which means they are stored in a bit-wise fashion
// To get actual values we need to AND the total parameters values with individual flag values

		$hasDeletedFlag = $parameters & self::$useDeletedFlag;
		$hasEnableFlag = $parameters & self::$useEnableFields;
		$hasLanguageFlag = $parameters & self::$useLanguageOverlays;
		$hasVersioningFlag = $parameters & self::$useVersioning;

// Add the deleted clause

		if ($hasDeletedFlag) {
			$tableTCA = $GLOBALS['TCA'][$this->structure['FROM']['table']]['ctrl'];
			if (!empty($tableTCA['delete'])) {
				$deleteClause = $this->mainTable.'.'.$tableTCA['delete']." = '0'";
				$this->addWhereClause($deleteClause);
			}
// TODO: add deleted clause to JOINed tables
// The code below is functional, but it is not always desirable to set all TYPO3 mechanisms for JOINed tables. This needs more thinking...
/*
			$numJoins = 0;
			if (isset($this->structure['JOIN'])) $numJoins = count($this->structure['JOIN']);
			if ($numJoins > 0) {
				for ($i = 0; $i < $numJoins; $i++) {
					$tableTCA = $GLOBALS['TCA'][$this->structure['JOIN'][$i]['table']]['ctrl'];
					if (!empty($tableTCA['delete'])) {
						$tableName = (empty($this->structure['JOIN'][$i]['alias'])) ? $this->structure['JOIN'][$i]['table'] : $this->structure['JOIN'][$i]['alias'];
						$deleteClause = $tableName.'.'.$tableTCA['delete']." = '0'";
						if (!empty($this->structure['JOIN'][$i]['on'])) $this->structure['JOIN'][$i]['on'] .= ' AND ';
						$this->structure['JOIN'][$i]['on'] .= '('.$deleteClause.')';
					}
				}
			}
*/
		}
	}

	/**
	 * This method builds up the query with all the data stored in the structure
	 *
	 * @return	string	the assembled SQL query
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
	 */
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

// Setters and getters

	/**
	 * Add a condition for the WHERE clause
	 *
	 * @param	string	SQL WHERE clause (without WHERE)
	 *
	 * @return	void
	 */
	public function addWhereClause($clause) {
		if (!empty($clause)) {
			$this->structure['WHERE'][] = $clause;
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

	/**
	 * This method returns an array containing the list of all subtables in the query,
	 * i.e. the tables that appear in any of the JOIN statements
	 *
	 * @return	array	names of all the joined tables
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
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_parser.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.tx_dataquery_parser.php']);
}

?>