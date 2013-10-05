<?php
namespace Webit\Accounting\PaymentCashbillBundle\Form;

use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;

interface SignCalculatorInterface {
    /**
     *
     * @param array $data
     * @return string
     */
    public function calculateSign(array $data);
}
