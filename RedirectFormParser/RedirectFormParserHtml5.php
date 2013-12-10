<?php
namespace Webit\Accounting\PaymentCashbillBundle\RedirectFormParser;

use Symfony\Component\CssSelector\Parser;

class RedirectFormParserHtml5 implements RedirectFormParserInterface {
	/**
	 * 
	 * @param string $html
	 * @return string
	 * 
	 */
	public function getRedirectUrl($html) {
		$document = new \DOMDocument();
		$document->loadHTML($html);

		$xpath = new \DOMXPath($document);
		$url = null;
		foreach ($xpath->query(Parser::cssToXpath('form')) as $node)
		{
			$url = (string)$node->getAttribute('action');
			break;
		}
		 
		$token = null;
		foreach ($xpath->query(Parser::cssToXpath('form input[name="token"]')) as $node)
		{
			$token = (string)$node->getAttribute('value');
			break;
		} 
		
		if(empty($url) || empty($token)) {
			return false;
		}
		
		$url = sprintf('%s?token=%s',$url, $token);
		
		return $url;
	}
}
