<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 René Nitzsche (nitzsche@das-medienkombinat.de)
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
 * Execute code within XML.
 *
 */
class tx_mkforms_util_Runnable {
	private $config;
	private $form;
	private $aUserObjParamsStack = array();
	private $aForcedUserObjParamsStack = array();
	private $aCodeBehinds = array();

	private function __construct($config, $form) {
		$this->config = $config;
		$this->form = $form;
	}

	/**
	 * [Describe function...]
	 *
	 * @param	[type]		$mMixed: ...
	 * @return	[type]		...
	 */
	public static function isUserObj($mMixed) {
		return is_array($mMixed) && array_key_exists('userobj', $mMixed);
	}

	private static function hasCodeBehind($mMixed) {
		 return is_array($mMixed) && array_key_exists('exec', $mMixed);
	}

	public static function isRunnable($mMixed) {
		return self::isUserObj($mMixed) || self::hasCodeBehind($mMixed);
	}

	/**
	 * Aufruf eines UserObj im XML. Neben dem XML-Pfad können weitere Parameter übergeben werden, die
	 * dann als Parameter durchgereicht werden
	 *
	 * @param mixed $mMixed
	 * @return unknown
	 */
	public function callRunnable($mMixed) {
		if(!self::isRunnable($mMixed))
			return $mMixed;
		// NOTE: for userobj, only ONE argument may be passed
		$aArgs = func_get_args();

		if(self::isUserObj($mMixed)) {
			$params = array_key_exists(1, $aArgs) && is_array($aArgs[1]) ? $aArgs[1] : array();
			$contextObj = count($aArgs)>1 ? $aArgs[count($aArgs)-1] : false; // Ggf. das Context-Objekt
			return $this->callUserObj($mMixed, $params, $contextObj);
		}

		if(self::hasCodeBehind($mMixed)) {
			// it's a codebehind
			$aArgs[0] = $mMixed; // Wir ersetzen den ersten Parameter
			$mRes = call_user_func_array(array($this, 'callCodeBehind'), $aArgs);
			return $mRes;
		}

		return $mMixed;
	}
	/**
	 * Führt das Runnable für ein bestimmtes Widget aus. Die Methode stammt ursprünglich
	 * aus der Klasse mainrenderlet.
	 * @param mainrenderlet $widget
	 * @param mixed $mMixed
	 * @return mixed
	 */
	public function callRunnableWidget($widget, $mMixed) {
		$aArgs = func_get_args();
		array_shift($aArgs);
		$this->getForm()->pushCurrentRdt($widget);
		// Dieser Aufruf geht ans main_object. Die Methode muss aber da noch raus!
		$mRes = call_user_func_array(array($widget, 'callRunneable'), $aArgs);
		$this->getForm()->pullCurrentRdt();
		return $mRes;
	}

	/**
	 * [Describe function...]
	 *
	 * @param 	array 	$aUserObjParams
	 * @param 	array 	$aParams
	 * @return 	array
	 */
	public function parseParams($aUserObjParams, $aParams = array()){
		while(list($index, $aParam) = each($aUserObjParams)) {
			if (is_array($aParam)) {
				$name = $aParam['name'];
				// Scalar values are set in attribute "value"
				if (isset($aParam['value'])) {
					$value = $aParam['value'];
				} else {
					// Treat deep structures aka arrays:
					unset($aParam['name']);

					if (array_key_exists('__value', $aParam)) {
						unset($aParam['__value']);
					}
					$value = $aParam;
				}
			} elseif($index !== '__value') {
				$name = $index;
				$value = $aParam;
			}

			// Finally set this parameter
			$aParams[$name] = $this->getForm()->getConfigXML()->getLLLabel($value);
		}
		reset($aParams);
		return $aParams;
	}

	/**
	 * [Describe function...]
	 *
	 * @param array $aUserobj: Array mit XML-Config
	 * @param array $aParams: Zusätzliche Parameter für Methodenaufruf
	 * @param object $contextObj Bei context=="relative" wird diese Object als 2. Parameter übergeben (anstatt des Forms).
	 * @return [type]		...
	 */
	private function callUserObj($aUserobj, $aParams = array(), $contextObj = false) {
		if(!is_array($this->getConfig()->get('/userobj/', $aUserobj))) return;
		// Das ContextObj ist der zweite Parameter im Aufruf. Normalerweise das Form, es kann aber auch das betroffene Objekt sein.
		$contextObj = $this->getConfig()->get('/userobj/context/', $aUserobj) == 'relative' ? $contextObj : $this->getForm();

		// Weitere Parameter können per XML übergeben werden
		$aUserObjParams = $this->getConfig()->get('/userobj/params/', $aUserobj);
		if($aUserObjParams !== FALSE && is_array($aUserObjParams)) {
			$aParams = $this->parseParams($aUserObjParams, $aParams);
		}

		if(($mPhp = $this->getConfig()->get('/userobj/php', $aUserobj)) !== FALSE) {
			$sPhp = (is_array($mPhp) && array_key_exists('__value', $mPhp)) ? $mPhp['__value'] : $mPhp;

			$sClassName = uniqid('tempcl'). rand(1,1000);
			$sMethodName = uniqid('tempmet');

			$this->__sEvalTemp = array('code' => $sPhp, 'xml' => $aUserobj);

			// TODO: hier wird im PHP-Code das $this durch das Formular ersetzt. In dem Fall ist das natürlich falsch
			// weil hier Runnable gesetzt wird
			$form = $this->getForm();
			$GLOBALS['mkforms_forminstance'] =& $contextObj;
			$sPhp = str_replace("\$this", "\$GLOBALS['mkforms_forminstance']", $sPhp);

			$sClass =	'class ' . $sClassName . ' {'
				.	'	function ' . $sMethodName . "(\$_this, \$aParams) { \$_this=&\$GLOBALS['mkforms_forminstance'];"
				.	'		' . $sPhp
				.	'	}'
				.	'}' ;

			set_error_handler(array(&$form, '__catchEvalException'));
			eval($sClass);
			$oObj = new $sClassName();

			try {
				$this->pushUserObjParam($aParams);
				$sRes = call_user_func(array(&$oObj, $sMethodName),$this->getForm(), $aParams);
				$this->pullUserObjParam();
			} catch(Exception  $e){
				$verbose = intval(tx_rnbase_configurations::getExtensionCfgValue('rn_base', 'verboseMayday'));

				$ret =  'UNCAUGHT EXCEPTION FOR VIEW: ' . get_class($oCbObj) . "\r\n";

				//@TODO: Logging exceptions
				//t3lib_div::sysLog($ret."\r\n".$e->__toString(), 'mkforms', 4);
				if($verbose)
					$ret .= "\r\n" . $e->__toString();
				else
					$ret .= "\r\n" . $e->getMessage();

				// set msg to return;
				$sRes = $ret;
			}

			unset($this->__sEvalTemp);
			restore_error_handler();
			return $sRes;
		}

		if(($this->getConfig()->get('/userobj/cobj', $aUserobj)) !== FALSE) {
			return $this->getCObj()->cObjGetSingle($aUserobj['userobj']['cobj'],$aUserobj['userobj']['cobj.']);
		}

		if(($sTs = $this->getConfig()->get('/userobj/ts', $aUserobj)) !== FALSE) {
			return $this->callTyposcript($aUserobj, $sTs, $aParams);
		}

		if(($sJs = $this->getConfig()->get('/userobj/js', $aUserobj)) !== FALSE) {
			if(($aParams = $this->getConfig()->get('/userobj/params', $aUserobj)) !== FALSE) {
				if($this->getForm()->isRunneable($aParams)) {
					$aParams = $this->getForm()->callRunneable($aParams);
				}
			}
			$aParams = !is_array($aParams) ? array() : $aParams;
			return $this->getForm()->majixExecJs(trim($sJs),$aParams);

		}
		// Jetzt den normalen Fall abarbeiten

		$extension = $this->getConfig()->get('/userobj/extension/', $aUserobj);
		$method = $this->getConfig()->get('/userobj/method/', $aUserobj);
		$mode = $this->getConfig()->get('/userobj/loadmode', $aUserobj);
		if($mode === 'tx_div' || $mode === 'auto' || $extension != 'this' ) {
			tx_rnbase::load($extension);
		}

		$oExtension = (strcasecmp($extension, 'this') == 0) ? $this->getForm()->getParent() : t3lib_div::makeInstance($extension);

		if(!is_object($oExtension)) return;

		$form = &$this->getForm();
		set_error_handler(array(&$form, '__catchEvalException'));

		if(!method_exists($oExtension, $method)) {
			$sObject = ($extension == 'this') ? '\$this (<b>' . get_class($this->getForm()->getParent()) . '</b>)' : $extension;
			tx_mkforms_util_Div::mayday($this->getConfig()->get('/type/', $aElement) . ' <b>' . $this->getConfig()->get('/name/', $aElement) . '</b> : callback method <b>' . $method . '</b> of the Object <b>' . $sObject . '</b> doesn\'t exist');
		}

		try {
			$newData = $oExtension->{$method}($aParams,  $contextObj);
		} catch(Exception  $e){

			// wir leiten die Exception direkt an rn_base weiter,
			// ohne den Mayday aufzurufen.
			if($this->getForm()->getConfTS('disableMaydayOnUserObjExceptions')) {
				throw $e;
			}

			$ret =  'UNCAUGHT EXCEPTION FOR VIEW: ' . get_class($oCbObj) . "\r\n";

			tx_rnbase::load('tx_rnbase_util_Logger');
			if(tx_rnbase_util_Logger::isWarningEnabled()) {
				tx_rnbase_util_Logger::warn('Method callUserObj() failed.', 'mkforms', array('Exception' => $e->getMessage(), 'XML'=>$aUserobj, 'Params' => $aParams, 'Form-ID' => $this->getForm()->getFormId()));
			}
			$ret .= "\r\n" . $e->getMessage();

			$this->getForm()->mayday($ret);

			// set msg to return;
			$newData = $ret;
		}
		restore_error_handler();
		tx_mkforms_util_Div::debug($newData, 'RESULT OF ' . $extension . '->' . $method . '()',$this->getForm());
		return $newData;
	}

	private function getCObj() {
		$sEnvExecMode = tx_mkforms_util_Div::getEnvExecMode();
		return ($sEnvExecMode === 'BE' || $sEnvExecMode === 'CLI') ? $this->getForm()->getCObj() : $GLOBALS['TSFE']->cObj;
	}
	/**
	 * Typoscript-Code ausführen
	 *
	 * @param array $aUserobj
	 * @param string $sTs
	 * @param array $aParams
	 * @return mixed
	 */
	private function callTyposcript($aUserobj, $sTs, $aParams) {
		$sTs = '
				temp.ameos_formidable >
				temp.ameos_formidable {
					' . $sTs . '
				}';

		$oParser = t3lib_div::makeInstance('t3lib_tsparser');
		$oParser->tt_track = 0;	// Do not log time-performance information
		$oParser->setup = $GLOBALS['TSFE']->tmpl->setup;

		if(array_key_exists('params.', $oParser->setup)) {
			unset($oParser->setup['params.']);
		}
		$oParser->setup['params.'] = tx_mkforms_util_Div::addDots($aParams);

		if(($aUserObjParams = $this->getConfig()->get('/userobj/params', $aUserobj)) !== FALSE) {
			if(is_array($aUserObjParams)) {

				if($this->getForm()->isRunneable($aUserObjParams)) {
					$aUserObjParams = $this->getForm()->callRunneable($aUserObjParams);
					if(!is_array($aUserObjParams)) {
						$aUserObjParams = array();
					}
				}
				$oParser->setup['params.'] = t3lib_div::array_merge_recursive_overrule(
					$oParser->setup['params.'],
					$aUserObjParams
				);
			}
		}

		$oParser->parse($sTs);
		$this->aLastTs = $oParser->setup['temp.']['ameos_formidable.'];

		$sOldCWD = getcwd();		// changing current working directory for use of GIFBUILDER in BE
		chdir(PATH_site);

		$aRes = $this->getCObj()->cObjGet($oParser->setup['temp.']['ameos_formidable.']);

		chdir($sOldCWD);
		return $aRes;
	}
	function pushUserObjParam($aParam) {
		array_push($this->aUserObjParamsStack, $aParam);
	}

	function pullUserObjParam() {
		array_pop($this->aUserObjParamsStack);
	}

	function pushForcedUserObjParam($aParam) {
		//$this->aForcedUserObjParamsStack[$sName] = $aParam;
		array_push($this->aForcedUserObjParamsStack, $aParam);
		return (count($this->aForcedUserObjParamsStack) - 1);
	}

	function pullForcedUserObjParam($iIndex = FALSE) {

		if($iIndex === FALSE) {
			if(!empty($this->aForcedUserObjParamsStack)) {
				array_pop($this->aForcedUserObjParamsStack);
			}
		} else {
			if(array_key_exists($iIndex, $this->aForcedUserObjParamsStack)) {
				unset($this->aForcedUserObjParamsStack[$sName]);
			}
		}
	}

	function getForcedUserObjParams() {
		$aParams = array();
		if(!empty($this->aForcedUserObjParamsStack)) {
			$aParams = $this->aForcedUserObjParamsStack[count($this->aForcedUserObjParamsStack) - 1];
		}

		return $aParams;
	}

	/**
	 * Liefert die Parameter eines aktuellen Aufrufs eines UserObjects im XML.
	 *
	 * @return array
	 */
	public function getUserObjParams() {

		$aParams = array();

		if(!empty($this->aUserObjParamsStack)) {
			$aParams = $this->aUserObjParamsStack[count($this->aUserObjParamsStack) - 1];
		}

		if(!empty($this->aForcedUserObjParamsStack)) {
			$aForcedParams = $this->getForcedUserObjParams();
			$aParams = t3lib_div::array_merge_recursive_overrule($aParams, $aForcedParams);
		}

		return $aParams;
	}

	public function cleanBeforeSession() {
		reset($this->aCodeBehinds["php"]);
		while(list($sKey, ) = each($this->aCodeBehinds["php"])) {
			unset($this->aCodeBehinds["php"][$sKey]["object"]->oForm);
			$this->aCodeBehinds["php"][$sKey]["object"] = serialize($this->aCodeBehinds["php"][$sKey]["object"]);
			unset($this->aCB[$sKey]);
		}
	}

	/**
	 * Die Methode wird noch in unHibernate der Formklasse aufgerufen
	 *
	 */
	public function initCodeBehinds() {

		$aMetas = $this->getConfig()->get("/meta");

		if(tx_mkforms_util_Div::getEnvExecMode() === "EID") {
			$this->aCodeBehinds["js"] = array();
		} else {
			unset($this->aCodeBehinds);
			unset($this->aCB);
			$this->aCodeBehinds = array(
				"js" => array(),
				"php" => array(),
			);
		}

		if($this->_xmlPath !== FALSE) {
			// application is defined in an xml file, and we know it's location
			// checking for default codebehind file, named after formid
				// convention over configuration paradigm !


			// default php CB
			$sDefaultCBClass = preg_replace("/[^a-zA-Z0-9_]/", "", $this->formid) . "_cb";
			$sDefaultCBFile = "class." . $sDefaultCBClass . ".php";
			$sDefaultCBDir = tx_mkforms_util_Div::toServerPath(dirname($this->_xmlPath));
			$sDefaultCBPath = $sDefaultCBDir . $sDefaultCBFile;

			if(file_exists($sDefaultCBPath) AND is_readable($sDefaultCBPath)) {
				$aDefaultCB = array(
					"type" => "php",
					"name" => "cb",
					"path" => $sDefaultCBPath,
					"class" => $sDefaultCBClass,
				);

				$aMetas = array_merge(
					array(
						"codebehind-default-php" => $aDefaultCB
					),
					$aMetas
				);
			}

			// default js CB
			$sDefaultCBFile = "class." . $sDefaultCBClass . ".js";
			$sDefaultCBPath = $sDefaultCBDir . $sDefaultCBFile;

			if(file_exists($sDefaultCBPath) AND is_readable($sDefaultCBPath)) {
				$aDefaultCB = array(
					"type" => "js",
					"name" => "js",
					"path" => $sDefaultCBPath . ":" . $sDefaultCBClass,
					"class" => $sDefaultCBClass,
				);

				$aMetas = array_merge(
					array(
						"codebehind-default-js" => $aDefaultCB
					),
					$aMetas
				);
			}
		}

		if(!is_array($aMetas)) $aMetas[0] = $aMetas;
		reset($aMetas);
		while(list($sKey,) = each($aMetas)) {
			if($sKey{0} === "c" && $sKey{1} === "o" && t3lib_div::isFirstPartOfStr(strtolower($sKey), "codebehind")) {

				$aCB = $this->initCodeBehind($aMetas[$sKey]);

				if($aCB["type"] === "php") {
					if(tx_mkforms_util_Div::getEnvExecMode() === "EID") {
						$this->aCodeBehinds["php"][$aCB["name"]]["object"] = unserialize($this->aCodeBehinds["php"][$aCB["name"]]["object"]);
						$this->aCodeBehinds["php"][$aCB["name"]]["object"]->oForm =& $this->getForm();
					} else {
						$this->aCodeBehinds["php"][$aCB["name"]] = $aCB;
					}
					$this->aCB[$aCB["name"]] =& $this->aCodeBehinds["php"][$aCB["name"]]["object"];
				} elseif($aCB["type"] === "js") {
					$this->aCodeBehinds["js"][$aCB["name"]] = $this->buildJsCbObject($aCB);
					$this->aCB[$aCB["name"]] =& $this->aCodeBehinds["js"][$aCB["name"]];
				}
			}
		}
	}

	private function &buildJsCbObject($aCB) {
//		require_once(t3lib_extMgm::extPath('mkforms', 'api/class.mainjscb.php'));
//		$oJsCb = t3lib_div::makeInstance("formidable_mainjscb");
		// den loader benutzen, damit die klasse beim ajax geladen wird
		$oJsCb = $this->getForm()->getObjectLoader()->makeInstance(
					'formidable_mainjscb',
					t3lib_extMgm::extPath('mkforms', 'api/class.mainjscb.php')
				);
		$oJsCb->init($this,$aCB);
		return $oJsCb;
	}

	private function initCodeBehind($aCB) {

		$sCBRef = $aCB["path"];
		$sName = $aCB["name"];

		// check for this (form object)
		if (strtolower($sCBRef) === 'this') {
			$oCB = &$this->getForm()->getParent();
			return array(
				'type' => 'php',
				'name' => $sName,
				'class' => get_class($oCB),
				'object' => &$oCB,
			);
		}

		if($sCBRef{0} === "E" && $sCBRef{1} === "X" && t3lib_div::isFirstPartOfStr($sCBRef, "EXT:")) {
			$sCBRef = substr($sCBRef, 4);
			$sPrefix = "EXT:";
		} else {
			$sPrefix = "";
		}

		$aParts = explode(":", $sCBRef);

		$sFileRef = $sPrefix . $aParts[0];
		$sFilePath = tx_mkforms_util_Div::toServerPath($sFileRef);

		// determining type of the CB
		$sFileExt = strtolower(array_pop(t3lib_div::revExplode(".", $sFileRef, 2)));
		switch($sFileExt) {
			case "php": {
				if(is_file($sFilePath) && is_readable($sFilePath)) {

					if(count($aParts) < 2) {
						if(!in_array($sFilePath, get_included_files())) {

							// class has not been defined. Let's try to determine automatically the class name

							$aClassesBefore = get_declared_classes();
							ob_start();
							require_once($sFilePath);
							ob_end_clean();		// output buffering for easing use of php class files that execute something outside the class definition ( like BE module's index.php !!)
							$aClassesAfter = get_declared_classes();

							$aNewClasses = array_diff($aClassesAfter, $aClassesBefore);

							if(count($aNewClasses) !== 1) {
								tx_mkforms_util_Div::mayday("<b>CodeBehind: Cannot automatically determine the classname to use in '" . $sFilePath . "'</b><br />Please add ':myClassName' after the file-path to explicitely.");
							} else {
								$sClass = array_shift($aNewClasses);
							}
						} else {
							tx_mkforms_util_Div::mayday("<b>CodeBehind: Cannot automatically determine the classname to use in '" . $sFilePath . "'</b><br />Please add ':myClassName' after the file-path.");
						}
					} else {
						$sClass = $aParts[1];
						ob_start();
						require_once($sFilePath);
						ob_end_clean();		// output buffering for easing use of php class files that execute something outside the class definition ( like BE module's index.php !!)
					}
					if(class_exists($sClass)) {
						$oCB = new $sClass();
						$oCB->oForm =& $this->getForm();
						if(method_exists($oCB, "init")) {
							$oCB->init($this->getForm());	// changed: avoid call-time pass-by-reference
						}

						return array("type" => "php","name" => $sName,"class" => $sClass,"object" => &$oCB);
					} else {
						tx_mkforms_util_Div::mayday("CodeBehind [" . $sCBRef . "]: class <b>" . $sClass . "</b> does not exist.");
					}
				} else {
					tx_mkforms_util_Div::mayday("CodeBehind [" . $sCBRef . "]: file <b>" . $sFileRef . "</b> does not exist.");
				}
				break;
			}
			case "js": {

				if(count($aParts) < 2) {
					tx_mkforms_util_Div::mayday("CodeBehind [" . $sCBRef . "]: you have to provide a class name for javascript codebehind <b>" . $sCBRef . "</b>. Please add ':myClassName' after the file-path.");
				} else {
					$sClass = $aParts[1];
				}

				if(is_file($sFilePath) && is_readable($sFilePath)) {
					if(intval(filesize($sFilePath)) === 0) {
						//$this->mayday("CodeBehind [" . $sCBRef . "]: seems to be empty</b>.");
						tx_mkforms_util_Div::smartMayday_CBJavascript($sFilePath, $sClass, FALSE);
					}
					//debug($sFilePath);
					// inclusion of the JS
					$this->getForm()->aCodeBehindJsIncludes[$sCBRef] =
						'<script type="text/javascript" src="' .
						$this->getForm()->getJSLoader()->getScriptPath(
								tx_mkforms_util_Div::toWebPath($sFilePath)
							) . '"></script>';

					$sScript = "Formidable.CodeBehind." . $sClass . " = new Formidable.Classes." . $sClass . "({formid: '" . $this->formid . "'});";
					$this->getForm()->aCodeBehindJsInits[] = $sScript;

					return array("type" => "js","name" => $sName,"class" => $sClass,);
				}
				break;
			}
			default: {
				tx_mkforms_util_Div::mayday("CodeBehind [" . $sCBRef . "]: allowed file extensions are <b>'.php', '.js' and '.ts'</b>.");
			}
		}
	}

	/**
	 * Aufruf von CodeBehind-Code. Ajax-Calls (PHP) und JavaScript
	 *
	 * @param array $aCB Array mit der Konfiguration des CB aus dem XML
	 * @return String
	 */
	private function &callCodeBehind($aCB) {
		if(!array_key_exists('exec', $aCB)) return; // Nix zu tun

		$aArgs = func_get_args();
		$cbConfig = $aArgs[0];
		$bCbRdt = FALSE;
		// $sCBRef - Der eigentliche Aufruf: cb.doSomething()
		$sCBRef = $aCB['exec'];
		$aExec = $this->getForm()->getTemplateTool()->parseForTemplate($sCBRef);
		$aInlineArgs = $this->getForm()->getTemplateTool()->parseTemplateMethodArgs($aExec[1]['args']);
		// $aExec enthält ein Array mit zwei Einträgen. Der erste ist ein Array mit
		// mit dem CodeBehind-Namen und der zweite ein Array mit der eigentlichen Aufrufmethode
		// array([expr] => btnUserSave_click,  [rec] => '', [args] => '')

		// Es gibt anscheinend den Sonderfall von rdt( als CB -Code...
		if(t3lib_div::isFirstPartOfStr($sCBRef, 'rdt(')) {
			$bCbRdt = TRUE;
			$aCbRdtArgs = $this->getForm()->getTemplateTool()->parseTemplateMethodArgs($aExec[0]['args']);
			if(($oRdt =& $this->getForm()->getWidget($aCbRdtArgs[0])) === FALSE) {
				tx_mkforms_util_Div::mayday('CodeBehind ' . $sCBRef . ': Refers to an undefined renderlet', $this->getForm());
			}
		}

		// Das sind vermutlich nochmal zusätzliche Parameter für den Aufruf...
		if(count($aInlineArgs) > 0) {
			reset($aInlineArgs);
			while(list($sKey, ) = each($aInlineArgs)) {
				if(is_object($aInlineArgs[$sKey])) {
					$aArgs[] =& $aInlineArgs[$sKey];
				} else {
					$aArgs[] = $aInlineArgs[$sKey];
				}
			}
		}

		// $aArgs enthält zwei Einträge. Der erste ist ein Array mit der Config des CB aus dem XML
		// im zweiten Eintrag liegen die Parameter aus dem Request

		$sName = $aExec[0]['expr'];
		$sMethod = $aExec[1]['expr'];

		$tmpArr = $aArgs;
		array_shift($tmpArr);
		$iNbParams = count($tmpArr);
		// back compat with revisions when only one single array-parameter was allowed
		$this->pushUserObjParam(($iNbParams === 1) ? $tmpArr[0] : $tmpArr);
		unset($tmpArr);

		if(array_key_exists($sName, $this->aCodeBehinds['php'])) {
			$sType = 'php';
		} elseif(array_key_exists($sName, $this->aCodeBehinds['js'])) {
			$sType = 'js';
		} else {
			if($bCbRdt !== TRUE) {
				tx_mkforms_util_Div::mayday('CodeBehind ' . $sCBRef . ': ' . $sName . ' is not a declared CodeBehind');
			}
		}

		// Jetzt wird wohl die eigentliche Klasse aufgelöst die aufgerufen wird
		if($bCbRdt === TRUE) {
			// Das ist der Sonderfall mit dem rdt(
			$sType = 'php';
			$oCbObj =& $oRdt;
			$sClass = get_class($oCbObj);
		} else {
			if($sType === 'php') {
				$aCB =& $this->aCodeBehinds[$sType][$sName];
				$oCbObj =& $aCB['object'];
				$sClass = $aCB['class'];
			} elseif($sType === 'js') {
				$aCB =& $this->aCodeBehinds[$sType][$sName]->aConf;
				$oCbObj =& $this->aCodeBehinds[$sType][$sName];
				$sClass = $aCB['class'];
			}
		}

		// forms object has to be the second parameter in php callbacks!!!
		$firstArg = array_shift($aArgs);
		$secondArg = array_shift($aArgs);
		array_unshift($aArgs, $this->getForm());
		array_unshift($aArgs, $secondArg);
		array_unshift($aArgs, $firstArg);

		// parameter aus dem xml übernehmen
		$aUserObjParams = $this->getConfig()->get('/params/', $aArgs[0]);
		if($aUserObjParams !== FALSE && is_array($aUserObjParams)) {
			$aArgs[1] = $this->parseParams($aUserObjParams, $aArgs[1]);
		}

		// Jetzt der Aufruf
		switch($sType) {
			case 'php': {
				array_shift($aArgs);
				if(is_object($oCbObj) && method_exists($oCbObj, $sMethod)) {
					// sollen die Widget validiert werden?
					$errors = array();
					$validate = array_key_exists('validate',$cbConfig) && $cbConfig['validate'] ? $cbConfig['validate'] : '';
					if($validate) {
						// Im ersten Parameter werden die Widgets erwartet
						// Wir validieren ein Set von Widgets
						$errors = $this->getForm()->getValidationTool()->validateWidgets4Ajax($aArgs[0]);
						if(count($errors))
							$this->getForm()->attachErrorsByJS($errors, $validate);
						else
							// wenn keine validationsfehler aufgetreten sind,
							// eventuell vorherige validierungs fehler entfernen
							$this->getForm()->attachErrorsByJS(null, $validate, true);
					}

					if(!count($errors)){
						try {
							$mRes = call_user_func_array(array($oCbObj, $sMethod), $aArgs);
						} catch(Exception  $e){

							$verbose = intval(tx_rnbase_configurations::getExtensionCfgValue('rn_base', 'verboseMayday'));
							$dieOnMayday = intval(tx_rnbase_configurations::getExtensionCfgValue('rn_base', 'dieOnMayday'));

							$ret =  'UNCAUGHT EXCEPTION FOR VIEW: ' . get_class($oCbObj) . "\r\n";

							//@TODO: Logging exceptions
							//t3lib_div::sysLog($ret."\r\n".$e->__toString(), 'mkforms', 4);
							if($verbose)
								$ret .= "\r\n" . $e->__toString();
							else
								$ret .= "\r\n" . $e->getMessage();

							if($dieOnMayday)
								die($ret);
							else
								echo($ret);
						}
					}

				} else {
					if(!is_object($oCbObj)) {
						tx_mkforms_util_Div::mayday('CodeBehind ' . $sCBRef . ': ' . $sClass . ' is not a valid PHP class');
					} else {
						tx_mkforms_util_Div::mayday('CodeBehind ' . $sCBRef . ': <b>' . $sMethod . '()</b> method does not exists on object <b>' . $sClass . '</b>');
					}
				}
				break;
			}
			case 'js': {
				// TODO: Das muss noch getestet werden!!
				if(isset($aArgs[0]['params'])) $aArgs[] = $aArgs[0]['params'];
				$aArgs[0] = $sMethod;
				$mRes = call_user_func_array(array($oCbObj, 'majixExec'), $aArgs);
			}
		}

		$this->pullUserObjParam();
		return $mRes;
	}

	/**
	 * @return tx_mkforms_util_Config $form
	 */
	private function getConfig() {
		return $this->config;
	}
	/**
	 * Liefert das Form
	 *
	 * @return tx_ameosformidable
	 */
	private function getForm() {
		return $this->form;
	}
	/**
	 * @param tx_mkforms_forms_IForm $form
	 */
	public static function createInstance(tx_mkforms_util_Config $config, $form) {
		$runnable = new tx_mkforms_util_Runnable($config, $form);
		$runnable->initCodeBehinds();
		return $runnable;
	}

	/**
	 * Liefert den codeBehind
	 *
	 * @param $sNname
	 * @param $sType
	 */
	public function getCodeBehind($sNname, $sType = 'php') {
		if(array_key_exists($sNname,$this->aCodeBehinds[$sType]))
			return $this->aCodeBehinds[$sType][$sNname];

		//else
		return false;
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/mkforms/util/class.tx_mkforms_util_Runnable.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/mkforms/util/class.tx_mkforms_util_Runnable.php']);
}
?>