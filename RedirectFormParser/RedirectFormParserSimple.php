<?php
namespace Webit\Accounting\PaymentCashbillBundle\RedirectFormParser;

class RedirectFormParserSimple implements RedirectFormParserInterface {
	/**
	 * 
	 * @param string $html
	 * @return string
	 * 
	 */
	public function getRedirectUrl($html) {
		$matches = array();
		preg_match('/\<form action\=\"([^\"]*)?\"/',$html, $matches);
		$url = count($matches) == 2 ? $matches[1] : null;
		
		$matches = array();
		
		preg_match('/name\=\"([^\"]*)?\"/',$html, $matches);
		$tokenName = count($matches) == 2 ? $matches[1] : null;
		
		preg_match('/value\=\"([^\"]*)?\"/',$html, $matches);
		$token = count($matches) == 2 ? $matches[1] : null;
		
		if(empty($url) || empty($token) || empty($tokenName)) {
			return false;
		}
		
		$url .= '?'.$tokenName.'='.$token;
		
		return $url;
	}
}
