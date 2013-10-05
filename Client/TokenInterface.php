<?php
namespace Webit\Accounting\PaymentCashbillBundle\Client;

interface TokenInterface {
    public function getId();
    
    public function getPosId();
    
    public function getPrivateKey();
}
