<?php
/**
 * 	@package tx_mklib
 *  @subpackage tx_mklib_tests
 *  @author Hannes Bochmann
 *
 *  Copyright notice
 *
 *  (c) 2010 Hannes Bochmann <hannes.bochmann@das-medienkombinat.de>
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
 */

/**
 * benötigte Klassen einbinden
 */
require_once(t3lib_extMgm::extPath('rn_base') . 'class.tx_rnbase.php');
tx_rnbase::load('tx_mkforms_forms_Factory');
/**
 * Statische Hilfsmethoden für Tests
 *
 * @package tx_mklib
 * @subpackage tx_mklib_tests
 */
class tx_mkforms_tests_Util {

	/**
	 * Liefert ein Form Objekt
	 * Enter description here ...
	 */
	public static function getForm() {
		$oForm = tx_mkforms_forms_Factory::createForm('generic');
		$oForm->setTestMode();

		$oParameters = tx_rnbase::makeInstance('tx_rnbase_parameters');
		$oConfigurations = tx_rnbase::makeInstance('tx_rnbase_configurations');
		$aConfigArray = array(
			'generic.' => array(
				'xml' => 'EXT:mkforms/tests/xml/renderlets.xml',
				'addfields.' => array(
						'widget-addfield' => 'addfield feld',
						'widget-remove' => 'unset',
					),
				'fieldSeparator' => '-',
				'addPostVars' => 1,
				'formconfig.' => array('loadJsFramework' => 0), // formconfig für config check setzen.
			)
		);
		$oConfigurations->init(
			$aConfigArray,
			$oConfigurations->getCObj(1),
			'mkforms', 'mkforms'
		);
		$oConfigurations->setParameters($oParameters);
		
		$oForm->init(
			$this,
			$oConfigurations->get('generic.xml'),
			0,
			$oConfigurations,
			'generic.formconfig.'
		);
		
		return $oForm;
	}
	
	/**
	 * Setzt die werte aus dem array für die korrespondierenden widgets.
	 * bei boxen wird rekursiv durchgegangen.
	 * 
	 * @param array $aData	|	Die Daten wie sie in processForm ankommen 
	 * @param $oForm
	 * @return void
	 */
	public static function setWidgetValues($aData, $oForm) {
		foreach ($aData as $sName => $mValue){
			if(is_array($mValue)) self::setWidgetValues($mValue,$oForm);
			else $oForm->getWidget($sName)->setValue($mValue);
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/mklib/tests/class.tx_mklib_tests_Util.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/mklib/tests/class.tx_mklib_tests_Util.php']);
}