<?php

/**
 * Plugin 'dh_std' for the 'ameos_formidable' extension.
 *
 * @author    Jerome Schneider <typo3dev@ameos.com>
 */
class tx_mkforms_dh_std_Main extends formidable_maindatahandler {

	function _doTheMagic($bShouldProcess = TRUE) {

		if ($bShouldProcess && $this->_allIsValid()) {
			$this->oForm->_debug(
				array(
					"DATA" => $this->getFormData(),
				),
				"DATAHANDLER STANDARD - EXECUTION"
			);
		}
	}
}

if (defined("TYPO3_MODE")
	&& $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]["XCLASS"]["ext/ameos_formidable/api/base/dh_std/api/class.tx_dhstd.php"]
) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]["XCLASS"]["ext/ameos_formidable/api/base/dh_std/api/class.tx_dhstd.php"]);
}
