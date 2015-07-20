<?php
/**
 * Plugin Name: Thebing Snippet
 * Plugin URI: http://www.thebing.com
 * Description: The plugin links the forms for thebing management school & agency software
 * Version: 1.1
 * Author: Thebing Services GmbH
 * Author URI: http://www.thebing.com
 * License: GPLv2
 */

require_once(__DIR__.'/tc/class.snippet.php');

// add hook for start output buffering
add_action('init', function() {
	ob_start();
});

// add hook for flush output buffering
add_action('wp_footer', function() {
	ob_end_flush();
});

// add hook for thebingsnippet tag
add_shortcode('thebingsnippet', 'Thebing_WP_Snippet::getContent');


/**
 * Class Thebing_WP_Snippet
 * @author Thebing Services GmbH
 */
class Thebing_WP_Snippet {

	/**
	 * Verfügbare Typen: tsFeedback, tsPlacementTest, tsRegistrationForm, default
	 * Verfügbare Attribute: type, server, combinationkey, templatekey, key, language, currencyid, currencyiso
	 *
	 * @param array $attributes
	 *     type = tsFeedback -> $attributes('type', 'server', 'key', 'language')
	 * 	   type = tsPlacementTest -> $attributes('type', 'server', 'key', ['language'], ['currencyid'], ['currencyiso'])
	 * 	   type = tsRegistrationForm -> $attributes('type', 'server', ['key'], ['language'])
	 * 	   type = default -> $attributes('type', 'server', 'combinationkey', 'templatekey')
	 *
	 * @return string
	 */
	static public function getContent($attributes) {

		$tagAttributes = shortcode_atts(array(
			'type' => 'default',
			'server' => '',
			'combinationkey' => '',
			'templatekey' => '',
			'key' => '',
			'language' => '',
			'currencyid' => '',
			'currencyiso' => ''
		), $attributes);

		$sContent = '';
		switch($tagAttributes['type']) {
			case 'tsFeedback':
				$sContent = self::getTsFeedback($tagAttributes['server'], $tagAttributes['key'], $tagAttributes['language']);
				break;
			case 'tsPlacementTest':
				$sContent = self::getPlacementTest($tagAttributes['server'], $tagAttributes['key'], $tagAttributes['language'], $tagAttributes['currencyid'], $tagAttributes['currencyiso']);
				break;
			case 'tsRegistrationForm':
				$sContent = self::getRegistrationForm($tagAttributes['server'], $tagAttributes['key'], $tagAttributes['language']);
				break;
			case 'default':
				$sContent = self::getDefault($tagAttributes['server'], $tagAttributes['combinationkey'], $tagAttributes['templatekey']);
				break;
		}

		return $sContent;
	}

	/**
	 * @param string $sServer
	 * @param string $sKey
	 * @param string $sLanguage
	 * @return string
	 */
	private static function getTsFeedback($sServer, $sKey, $sLanguage) {

		$aSubmitVars['r'] = isset($_GET['r']) ? $_GET['r'] : '';
		$aSubmitVars['KEY'] = $sKey;
		$aSubmitVars['save'] = $_POST['save'];
		$aSubmitVars['sLanguage'] = $sLanguage;
		$aSubmitVars['pid'] = $_SESSION['__pid'];
		$aSubmitVars['pp'] = $_SESSION['__ppa'];

		if($_REQUEST['task'] == 'detail' and $_REQUEST['action'] == 'save') {
			foreach($_REQUEST as $key => $value) {
				$aSubmitVars[$key] = $value;
			}
		}

		$oSnoopy = new Snoopy();
		$oSnoopy->submit($sServer . '/system/extensions/kolumbus_feedback.php', $aSubmitVars);
		$sResults = $oSnoopy->results;

		return $sResults;
	}

	/**
	 * @param string $sServer
	 * @param string $sKey
	 * @param string $sLanguage define the language of the site, the default Language of the school is used if it is not defined
	 * @param string $iCurrencyId define the Currency of the Site by ID , otherwise the first currency or $_VARS['sCurrency'] is used
	 * @param string $sCurrencyIso define the Currency of the Site by ISO name, otherwise the first currency or $_VARS['idCurrency'] is used
	 * @return string
	 */
	private static function getPlacementTest($sServer, $sKey, $sLanguage = '', $iCurrencyId = '', $sCurrencyIso = '') {

		$aSubmitVars['r'] = $_REQUEST['r'];
		$aSubmitVars['KEY'] = $sKey;
		$aSubmitVars['save'] = $_POST['save'];
		$aSubmitVars['isPeriod'] = $_POST['idPeriod'];

		if($sLanguage !== '') {
			$aSubmitVars['page_language'] = $sLanguage;
		}
		if($iCurrencyId !== '') {
			$aSubmitVars['idCurrency'] = $iCurrencyId;
		}
		if($sCurrencyIso !== '') {
			$aSubmitVars['sCurrency'] = $sCurrencyIso;
		}

		$oSnoopy = new Snoopy();
		$oSnoopy->submit($sServer . '/system/extensions/kolumbus_placementtest.php', $aSubmitVars);
		$sResults = $oSnoopy->results;

		return $sResults;
	}

	/**
	 * @param string $sServer
	 * @param string $sKey
	 * @param string $sLanguage
	 * @return string
	 */
	private static function getRegistrationForm($sServer, $sKey = '', $sLanguage = '') {

		$aSubmitVars = array();

		if(!empty($_REQUEST)) {
			foreach((array)$_REQUEST as $mKey=>$mValue) {
				$aSubmitVars[$mKey] = $mValue;
			}
		}
		if($sKey !== '') {
			$aSubmitVars['form_key'] = $sKey;
		}
		if($sLanguage !== '') {
			$aSubmitVars['page_language'] = $sLanguage;
		}

		$oSnoopy = new Snoopy();
		$aFiles = array();

		if(!empty($_FILES)) {

			$sTempDir = sys_get_temp_dir();
			if(!is_writeable($sTempDir)) {
				die('Fatal error while uploading file');
			}

			foreach((array)$_FILES as $sKey => $mItems) {
				if(!is_array($mItems['name'])) {
					$sTarget = $sTempDir . '/' . $mItems['name'];
					if(move_uploaded_file($mItems['tmp_name'], $sTarget)) {
						$aFiles[$sKey] = $sTarget;
					}
				} else {
					self::prepareFiles($mItems['name'], $mItems['tmp_name'], $aFiles[$sKey], $sTempDir);
				}
			}

		}

		$oSnoopy->cookies = $_COOKIE;
		unset($aSubmitVars['PHPSESSID']);

		$oSnoopy->set_submit_multipart();
		$oSnoopy->submit($sServer . '/system/extensions/thebing_registration_form.php?'.$_SERVER['QUERY_STRING'], $aSubmitVars, $aFiles);
		$sResults = $oSnoopy->results;

		if(
			isset($_REQUEST['task']) &&
			(
				$_REQUEST['task'] == 'get_js' ||
				$_REQUEST['task'] == 'get_image' ||
				$_REQUEST['task'] == 'get_file' ||
				$_REQUEST['task'] == 'get_ajax'
			)
		) {
			foreach((array)$oSnoopy->headers as $sHeader) {
				if(strpos($sHeader, 'Content-Type') !== false) {
					header($sHeader);
				}
			}
			ob_clean();
			echo $oSnoopy->results;
			die();
		}

		// Make internal server error of registration form recognizably
		if($oSnoopy->status == 500) {
			$sResults = 'Fatal error of registration form!';
		}

		// If content is already sent, no cookies can be set afterwards.
		// That's deadly for the function of the registration form, so that's a fatal error.
		// Usually this is an user error!
		if(headers_sent()) {
			$sResults = 'Wrong order of content output. Check whether you\'ve no output before including of registration form!';
		}

		foreach((array)$oSnoopy->cookies as $sKey=>$mValue) {
			$bIsMagicQuotes = get_magic_quotes_gpc();
			if($bIsMagicQuotes) {
				$mValue = stripslashes($mValue);
			}
			if(is_scalar($mValue)) {
				setcookie($sKey, $mValue);
			}
		}

		if(!empty($aFiles)) {
			self::unlinkFiles($aFiles);
		}

		return $sResults;
	}

	/**
	 * @param string $sServer
	 * @param string $sCombinationKey
	 * @param string $sTemplateKey
	 * @return string
	 */
	private static function getDefault($sServer, $sCombinationKey, $sTemplateKey) {

		$oSnippet = new Thebing_Snippet($sServer, $sCombinationKey, $sTemplateKey);
		$oSnippet->execute();
		$sContent = $oSnippet->getContent();

		return $sContent;
	}

	/**
	 * @param array $aFiles
	 */
	private static function unlinkFiles(&$aFiles) {

		foreach((array)$aFiles as $mKey => $mFile) {
			if(is_array($mFile)) {
				self::unlinkFiles($aFiles[$mKey]);
			} else if(is_file($mFile)) {
				unlink($mFile);
			}
		}

	}

	/**
	 * @param $mItems
	 * @param $mTmpItems
	 * @param $aFiles
	 * @param $sTempDir
	 */
	private static function prepareFiles($mItems, $mTmpItems, &$aFiles, $sTempDir) {

		foreach((array)$mItems as $sKey => $aItems) {
			if(!is_array($aItems)) {
				$sTarget = $sTempDir . '/' . $mItems[$sKey];
				if(move_uploaded_file($mTmpItems[$sKey], $sTarget)) {
					$aFiles[$sKey] = $sTarget;
				}
			} else {
				self::prepareFiles($mItems[$sKey], $mTmpItems[$sKey], $aFiles[$sKey], $sTempDir);
			}
		}

	}

}