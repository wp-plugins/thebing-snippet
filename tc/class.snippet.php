<?php

require(dirname(__FILE__) . "/Snoopy.class.php");

/**
 * Snippet class
 * 
 * ACHTUNG: Die Snippet-Klasse unterstützt aktuell keine PHP-Sessions! 
 * Das könnte aber noch ergänzt werden
 *
 * @author	plan-i GmbH <info@plan-i.de>
 */
class Thebing_Snippet {

	protected $_sUrl;
	protected $_sCode;
	protected $_sTemplate;
	protected $_iTimeout = 10;
	protected $_sContent;
	protected $_sCharset = 'utf-8';

	protected $_aUserParams = array();

	public function __construct($sUrl, $sCode, $sTemplate, $sCharset='utf-8') {

		$this->_sUrl = $sUrl;
		$this->_sCode = $sCode;
		$this->_sTemplate = $sTemplate;
		$this->_sCharset = strtolower($sCharset);

	}

	public function getContent() {

		$sContent = $this->_sContent;

		if($this->_sCharset != 'utf-8') {
			$sContent = iconv('utf-8', $this->_sCharset.'//TRANSLIT', $sContent);
		}

		return $sContent;

	}

	public function setTimeout($iTimeout) {
		$this->_iTimeout = $iTimeout;
	}

	public function convertInput(&$sInput) {
		if(is_scalar($sInput)) {
			$sInput = iconv($this->_sCharset, 'utf-8//TRANSLIT', $sInput);
		}
	}
	
	protected function _getRequest() {
		
		$_REQUEST['REQUEST_URI'] = $_SERVER['REQUEST_URI'];	
		$aRequest = $_REQUEST;
		
		if($this->_sCharset != 'utf-8') {
			array_walk_recursive($aRequest, array($this, 'convertInput'));
		}

		return $aRequest;
		
	}
	
	/**
	 * The main method of the PlugIn
	 *
	 * @return	string The content that is displayed on the website
	 */
	function execute() {

		$sUrl = $this->_sUrl;
		$sCode = $this->_sCode;
		$sTemplate = $this->_sTemplate;

		if(
			empty($sUrl) ||
			empty($sCode) ||
			empty($sTemplate)
		) {
			$sContent = 'Error: Configuration data is missing!';

			$sContent .= ' (URL: '.$sUrl.', Code: '.$sCode.', Template: '.$sTemplate.')';

		} else {
			
			$oSnoopy = new Snoopy;
			$oSnoopy->read_timeout = $this->_iTimeout;

			$sHostName = $this->getHostUrl($sUrl);
			$sUrl = $sHostName . '/system/extensions/tc_api.php';

			$aVariables = array();

			$aRequest = $this->_getRequest();
			
			// Add all request parameters to snoopy request
			if(!empty($aRequest)) {
				foreach((array)$aRequest as $mKey=>$mValue) {
					$aVariables[$mKey] = $mValue;
				}
			}

			$aVariables['code'] = $sCode;
			$aVariables['template'] = $sTemplate;

			if(!empty($this->_aUserParams))
			{
				$aVariables['frontend_combination_params'] = $this->_aUserParams;
			}

			$aFiles = array();

			if(!empty($_FILES)) {

				// Check temporary directory
				$sTempDir = $oSnoopy->temp_dir;
				if(!is_dir($sTempDir) || !is_writable($sTempDir.'/test')) {
					$sTempDir = dirname(__FILE__).'/tmp';
					if(!is_dir($sTempDir)) {
						mkdir($sTempDir, 0777);
						chmod($sTempDir, 0777);
					}
				}

				// Save all files to temporary directory
				foreach((array)$_FILES as $sKey => $mItems)
				{
					if(!is_array($mItems['name']))
					{
						$sTarget = $sTempDir . '/' . $mItems['name'];
						$bSuccess = move_uploaded_file($mItems['tmp_name'], $sTarget);

						if($bSuccess) {
							$aFiles[$sKey] = $sTarget;
						}
					}
					else
					{
						$this->_prepareFiles($mItems['name'], $mItems['tmp_name'], $aFiles[$sKey], $sTempDir);
					}
				}
			}

			// Website Session ID entfernen
			unset($_COOKIE['PHPSESSID']);
			unset($aVariables['PHPSESSID']);

			// Session ID von der API übermitteln
			if(
				isset($_COOKIE['thebing_snippet_session_id']) &&
				isset($_COOKIE['thebing_snippet_session_name'])
			) {
				$_COOKIE[$_COOKIE['thebing_snippet_session_name']] = $_COOKIE['thebing_snippet_session_id'];
			}

			// Add cookie data to snoopy request
			$oSnoopy->cookies = $_COOKIE;

			$oSnoopy->set_submit_multipart();

			$oSnoopy->submit($sUrl, $aVariables, $aFiles);

			if(
				(
					isset($_REQUEST['task']) &&
					(
						$_REQUEST['task'] == 'get_js' ||
						$_REQUEST['task'] == 'get_image' ||
						$_REQUEST['task'] == 'get_file' ||
						$_REQUEST['task'] == 'get_ajax'
					)
				) ||
				isset($_REQUEST['get_request']) ||
				isset($_REQUEST['get_file'])
				
			) {

				foreach((array)$oSnoopy->headers as $sHeader) {
					if(strpos($sHeader, 'Content-Type') !== false) {
						header($sHeader);
					}
				}
				
				// OB beenden
				while(ob_get_level() > 0) {
					ob_end_clean();
				}

				$sContent = $oSnoopy->results;
				echo $sContent;
				die();

			} else {

				$sContent = $oSnoopy->results;

				if(empty($sContent)) {
					if($oSnoopy->timed_out == 1) {
						$sContent = 'Connection timeout exceeded! Please reload this page.';
					} else {
						$sContent = $oSnoopy->error;
					}
				}

			}

			// Set cookies
			foreach((array)$oSnoopy->cookies as $sKey=>$mValue) {
				$bIsMagicQuotes = get_magic_quotes_gpc();
				if($bIsMagicQuotes) {
					$mValue = stripslashes($mValue);
				}
				if(is_scalar($mValue)) {
					// Auf keinen Fall Cookies überschreiben
					if(
						!isset($_COOKIE[$sKey]) &&
						$sKey != 'PHPSESSID'
					) {
						setcookie($sKey, $mValue, 0, '/');
					}
				}
			}

			if(!empty($aFiles))
			{
				$this->_unlinkFiles($aFiles);
			}
		}

		$this->_sContent = $sContent;

	}

	protected function _prepareFiles($mItems, $mTmpItems, &$aFiles, $sTempDir)
	{
		foreach((array)$mItems as $sKey => $aItems)
		{
			if(!is_array($aItems))
			{
				$sTarget = $sTempDir . '/' . $mItems[$sKey];
				$bSuccess = move_uploaded_file($mTmpItems[$sKey], $sTarget);

				if($bSuccess) {
					$aFiles[$sKey] = $sTarget;
				}
			}
			else
			{
				$this->_prepareFiles($mItems[$sKey], $mTmpItems[$sKey], $aFiles[$sKey], $sTempDir);
			}
		}
	}

	protected function _unlinkFiles(&$aFiles)
	{
		foreach((array)$aFiles as $mKey => $mFile)
		{
			if(is_array($mFile))
			{
				$this->_unlinkFiles($aFiles[$mKey]);
			}
			else if(is_file($mFile))
			{
				unlink($mFile);
			}
		}
	}
	
	public function encodeString(&$sString, $sKey=false) {
		global $aConfig;

		if(
			is_string($sString) &&
			$aConfig['charset'] != 'utf-8'
		) {
			$sString = iconv($aConfig['charset'], 'utf-8', $sString);
		}

	}

	public function decodeString(&$sString, $sKey=false) {
		global $aConfig;

		if(
			is_string($sString) &&
			$aConfig['charset'] != 'utf-8'
		) {
			$sString = iconv('utf-8', $aConfig['charset'], $sString);
		}

	}

	public function getHostUrl($sHost=false) {

		if(!empty($sHost) && strpos($sHost, 'http') === false) {
			$sHost = 'http://'.$sHost;
		}

		return $sHost;

	}

	/**
	 * Manipulate combination settings
	 * 
	 * @param string $sKey
	 * @param mixed $mValue
	 * @return Thebing_Snippet
	 */
	public function setCombinationParameter($sKey, $mValue)
	{
		$this->_aUserParams[$sKey] = $mValue;

		return $this;
	}
}
