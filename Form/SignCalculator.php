<?php
namespace Webit\Accounting\PaymentCashbillBundle\Form;

use Webit\Accounting\PaymentCashbillBundle\Client\TokenInterface;
use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;

class SignCalculator implements SignCalculatorInterface {
    /**
     * 
     * @var TokenInteface
     */
    private $token;
    
    /**
     * 
     * @param TokenInteface $token
     */
    public function __construct(TokenInterface $token) {
        $this->token = $token;
    }

    /**
     * 
     * @param ExtendedDataInterface $data
     * @return string
     */
    public function calculateSign(array $postData) {
        $sign = md5(implode('',array_values($postData)).$this->token->getPrivateKey());
        
        return $sign;
    }
}