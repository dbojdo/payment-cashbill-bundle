<?php
namespace Webit\Accounting\PaymentCashbillBundle\RedirectFormParser;

interface RedirectFormParserInterface {
	/**
	 * 
	 * @param string $html
	 * @return string
	 * 
	 */
	public function getRedirectUrl($html);
}
