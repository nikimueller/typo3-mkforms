<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Ren'e Nitzsche (nitzsche@das-medienkombinat.de)
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

tx_rnbase::load('tx_mkforms_forms_Factory');

class tx_mkforms_tests_Lister_testcase extends tx_phpunit_testcase {

	public function test_HtmlAttribites() {
//		$form = tx_mkforms_forms_Factory::createForm('test');
//		$form->init($this, 'EXT:mkforms/tests/fixtures/lister1.xml');
//		$html = $form->render();

		
//		t3lib_div::debug($form->getWidget('thelist')->getChilds(),'tx_mkforms_tests_Lister_testcase.php : '); // TODO: remove me
		self::assertTrue("hello" !== "world", "Hello is not equal to world !");
	}
	
}

?>