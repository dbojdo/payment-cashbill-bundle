<?php
namespace Webit\Accounting\PaymentCashbillBundle\Event;

use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;

class CashbillConfirmationReceivedEvent extends Event {
	
	/**
	 * 
	 * @var PaymentInstructionInterface
	 */
	private $paymentInstruction;
	
	/**
	 * 
	 * @var Request
	 */
	private $request;

	public function __construct(PaymentInstructionInterface $paymentInstruction, Request $request) {
		$this->paymentInstruction = $paymentInstruction;
		$this->request = $request;
	}
	
	/**
	 * 
	 * @return \JMS\Payment\CoreBundle\Model\PaymentInstructionInterface
	 */
	public function getPaymentInstruction() {
		return $this->paymentInstruction;
	}
	
	/**
	 * 
	 * @return \Symfony\Component\HttpFoundation\Request
	 */
	public function getRequest() {
		return $this->request;
	}
}
