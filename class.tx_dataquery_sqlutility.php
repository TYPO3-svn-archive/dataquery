<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Francois Suter (Cobweb) <typo3@cobweb.ch>
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
 * Class containing some utility SQL methods
 *
 * @author		Francois Suter (Cobweb) <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
final class tx_dataquery_SqlUtility {
	static public function conditionToSql($field, $table, $conditionData) {
		$condition = '';
			// If the value is special value "\all", all values must be taken,
			// so the condition is simply ignored
		if ($conditionData['value'] != '\all') {
				// Some operators require a bit more handling
				// "in" values just need to be put within brackets
			if ($conditionData['operator'] == 'in') {
					// If the condition value is an array, use it as is
					// Otherwise assume a comma-separated list of values and explode it
				$conditionParts = $conditionData['value'];
				if (!is_array($conditionParts)) {
					$conditionParts = t3lib_div::trimExplode(',', $conditionData['value'], TRUE);
				}
				$escapedParts = array();
				foreach ($conditionParts as $value) {
					$escapedParts[] = $GLOBALS['TYPO3_DB']->fullQuoteStr($value, $table);
				}
				$condition = $field . (($conditionData['negate']) ? ' NOT' : '') . ' IN (' . implode(',', $escapedParts) . ')';

				// "andgroup" and "orgroup" require more handling
				// The associated value is a list of comma-separated values and each of these values must be handled separately
				// Furthermore each value will be tested against a comma-separated list of values too, so the test is not so simple
			} elseif ($conditionData['operator'] == 'andgroup' || $conditionData['operator'] == 'orgroup') {
					// If the condition value is an array, use it as is
					// Otherwise assume a comma-separated list of values and explode it
				$values = $conditionData['value'];
				if (!is_array($values)) {
					$values = t3lib_div::trimExplode(',', $conditionData['value'], TRUE);
				}
				$condition = '';
				$localOperator = 'OR';
				if ($conditionData['operator'] == 'andgroup') {
					$localOperator = 'AND';
				}
				foreach ($values as $aValue) {
					if (!empty($condition)) {
						$condition .= ' ' . $localOperator . ' ';
					}
					$condition .= $GLOBALS['TYPO3_DB']->listQuery($field, $aValue, $table);
				}
				if ($conditionData['negate']) {
					$condition = 'NOT (' . $condition . ')';
				}

				// If the operator is "like", "start" or "end", the SQL operator is always LIKE, but different wildcards are used
			} elseif ($conditionData['operator'] == 'like' || $conditionData['operator'] == 'start' || $conditionData['operator'] == 'end') {
					// Make sure values are an array
				$values = $conditionData['value'];
				if (!is_array($values)) {
					$values = array($conditionData['value']);
				}
					// Loop on each value and assemble condition
				$condition = '';
				foreach ($values as $aValue) {
					$aValue = $GLOBALS['TYPO3_DB']->escapeStrForLike($aValue, $table);
					if (!empty($condition)) {
						$condition .= ' OR ';
					}
					if ($conditionData['operator'] == 'start') {
						$value = $aValue . '%';
					} elseif ($conditionData['operator'] == 'end') {
						$value = '%' . $aValue;
					} else {
						$value = '%' . $aValue . '%';
					}
					$condition .= $field . ' LIKE ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($value, $table);
				}
				if ($conditionData['negate']) {
					$condition = 'NOT (' . $condition . ')';
				}

				// Other operators are handled simply
				// We just need to take care of special values: "\empty" and "\null"
			} else {
				$operator = $conditionData['operator'];
					// Make sure values are an array
				$values = $conditionData['value'];
				if (!is_array($values)) {
					$values = array($conditionData['value']);
				}
					// Loop on each value and assemble condition
				$condition = '';
				foreach ($values as $aValue) {
					if (!empty($condition)) {
						$condition .= ' OR ';
					}
						// Special value "\empty" means evaluation against empty string
					if ($conditionData['value'] == '\empty') {
						$quotedValue = "''";

						// Special value "\null" means evaluation against IS NULL or IS NOT NULL
					} elseif ($conditionData['value'] == '\null') {
						if ($operator == '=') {
							$operator = 'IS';
						}
						$quotedValue = 'NULL';

						// Normal value
					} else {
						$quotedValue = $GLOBALS['TYPO3_DB']->fullQuoteStr($aValue, $table);
					}
					$condition .= $field . ' ' . $operator . ' ' . $quotedValue;
				}
				if ($conditionData['negate']) {
					$condition = 'NOT (' . $condition . ')';
				}
			}
		}
		return $condition;
	}
}
?>