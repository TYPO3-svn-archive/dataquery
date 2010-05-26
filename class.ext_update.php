<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Francois Suter <typo3@cobweb.ch>
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
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
 * Class for updating the data query
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class ext_update {

	/**
	 * Main function, returning the HTML content of the module
	 *
	 * @return	string	HTML to display
	 */
	function main() {
		$update = t3lib_div::_GP('submitButton');
			// The update button was clicked
		if (!empty($update)) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid, additional_sql, tx_dataquery_sql', 'tx_datafilter_filters', "additional_sql <> ''");
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$fields = array();
				$fields['tx_dataquery_sql'] = $row['additional_sql'];
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_datafilter_filters', 'uid = ' . $row['uid'], $fields);
			}
		}
		$content = '<h2>Updating additional SQL field</h2>';
			// Check if field exists at all
		$fields = $GLOBALS['TYPO3_DB']->admin_get_fields('tx_datafilter_filters');
			// The old field exists
		if (isset($fields['additional_sql'])) {
				// The new field must exist too, otherwise no update can be performed
			if (isset($fields['tx_dataquery_sql'])) {
					// Get all records with a non-empty additional_sql field
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid, title, additional_sql, tx_dataquery_sql', 'tx_datafilter_filters', "additional_sql <> ''");
					// There are none
				if ($GLOBALS['TYPO3_DB']->sql_num_rows($res) == 0) {
					$content .= '<p>The additional SQL field exists, but is not used.</p>';

					// The additional SQL field is not empty for some records, propose update
				} else {
					$content .= '<p>The following records use the additional SQL field and should be updated. If the same SQL appears in both old and new field, there\'s nothing more to do.</p>';
					$content .= '<table cellpadding="4" cellspacing="0" border="1">';
					$content .= '<thead><tr><th>Record</th><th>Old SQL field</th><th>New SQL field</th></tr></thead>';
					$content .= '<tbody>';
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$content .= '<tr valign="top">';
						$content .= '<td>' . $row['title'] . ' [' . $row['uid'] . ']</td>';
						$content .= '<td>' . $row['additional_sql'] . '</td>';
						$content .= '<td>' . $row['tx_dataquery_sql'] . '</td>';
						$content .= '</tr>';
					}
					$content .= '</tbody>';
					$content .= '</table>';
						// Display update form, if the update button was not already clicked
					if (empty($update)) {
						$content .= '<form name="updateForm" action="" method ="post">';
						$content .= '<p><input type="submit" name="submitButton" value ="Update"></p>';
						$content .= '</form>';
					}
				}
				// The new field does not exist, no update can take place
			} else {
				$content .= '<p>The new additional SQL field does not exist, the update cannot be performed. Make the necessary database updates and come back here again.</p>';
			}
			// The field does not exist, there's nothing to do
		} else {
			$content .= '<p>The old additional SQL field does not exist, there\'s nothing to update.</p>';
		}
		return $content;
	}

	/**
	 * This method checks whether it is necessary to display the UPDATE option at all
	 *
	 * @param	string	$what: What should be updated
	 */
	function access($what = 'all') {
		return TRUE;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.ext_update.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/dataquery/class.ext_update.php']);
}
?>
