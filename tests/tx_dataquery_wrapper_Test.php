<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Francois Suter <typo3@cobweb.ch>
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
 * Testcase for the Data Query wrapper (Data Provider)
 *
 * @author		Francois Suter <typo3@cobweb.ch>
 * @package		TYPO3
 * @subpackage	tx_dataquery
 *
 * $Id$
 */
class tx_dataquery_wrapper_Test extends tx_phpunit_testcase {
	/**
	 * Provides array(s) to sort
	 *
	 * @return array
	 */
	public function stuffToSortProvider() {
		$stuffToSort = array(
			'standard case' => array(
				'records' => array(
					0 => array(
						'name' => 'Arthur Dent',
						'age' => '22'
					),
					1 => array(
						'name' => 'Slartibartfast',
						'age' => '12'
					),
					2 => array(
						'name' => 'Ford Prefect',
						'age' => '12'
					),
					3 => array(
						'name' => 'Zaphod Beeblebrox',
						'age' => '1'
					),
					4 => array(
						'name' => 'Prostetnic Vogon Jeltz',
						'age' => '2'
					),
				),
				'result' => array(
					0 => array(
						'name' => 'Zaphod Beeblebrox',
						'age' => '1'
					),
					1 => array(
						'name' => 'Prostetnic Vogon Jeltz',
						'age' => '2'
					),
					2 => array(
						'name' => 'Ford Prefect',
						'age' => '12'
					),
					3 => array(
						'name' => 'Slartibartfast',
						'age' => '12'
					),
					4 => array(
						'name' => 'Arthur Dent',
						'age' => '22'
					),
				)
			)
		);
		return $stuffToSort;
	}

	/**
	 * @param array $records Unsorted records
	 * @param array $result Sorted records
	 * @test
	 * @dataProvider stuffToSortProvider
	 */
	public function testSortingMethod($records, $result) {
		tx_dataquery_wrapper::$sortingFields[0]['field'] = 'age';
		tx_dataquery_wrapper::$sortingFields[1]['field'] = 'name';
		usort($records, array('tx_dataquery_wrapper', 'sortRecordset'));
		$this->assertEquals($result, $records);
	}
}
?>