<?php
namespace Webit\Accounting\PaymentCashbillBundle\Client;

class Token implements TokenInterface {
    /**
     * 
     * @var string
     */
    private $id;
    
    /**
     * 
     * @var string
     */
    private $posId;
    
    /**
     * 
     * @var string
     */
    private $privateKey;
    
    /**
     * 
     * @param string $id
     * @param string $posId
     * @param string $privateKey
     */
    public function __construct($id, $posId, $privateKey) {
        $this->id = $id;
        $this->posId = $posId;
        $this->privateKey = $privateKey;
    }
    
    /**
     * 
     * @return string
     */
    public function getId() {
        return $this->id;
    }
    
    /**
     *
     * @return string
     */
    public function getPosId() {
        return $this->posId;
    }
    
    /**
     *
     * @return string
     */
    public function getPrivateKey() {
        return $this->privateKey;
    }
}