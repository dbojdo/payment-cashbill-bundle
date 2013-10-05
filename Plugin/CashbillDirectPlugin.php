<?php

namespace Webit\Accounting\PaymentCashbillBundle\Plugin;

use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\Exception\TimeoutException;
use JMS\Payment\CoreBundle\Plugin\ErrorBuilder;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\BlockedException;
use JMS\Payment\CoreBundle\Entity\ExtendedData;
use Webit\Accounting\PaymentCashbillBundle\Form\SignCalculator;
use Webit\Accounting\PaymentCashbillBundle\Form\SignCalculatorInterface;
use Symfony\Component\Routing\RouterInterface;
use Webit\Accounting\PaymentCashbillBundle\Client\TokenInterface;
use Buzz\Browser;
use Symfony\Component\CssSelector\CssSelector;

/*
 * Copyright 2012 ETSGlobal <e4-devteam@etsglobal.org>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Dotpay payment plugin
 *
 * @author ETSGlobal <e4-devteam@etsglobal.org>
 */
class CashbillDirectPlugin extends AbstractPlugin
{
    const STATUS_OK = 'ok';
    const STATUS_ERR = 'err';

    public static $statuses = array(
        self::STATUS_OK    => 'Success',
        self::STATUS_ERR       => 'Error'
    );

    /**
     * @var Router
     */
    protected $router;

    /**
     * 
     * @var TokenInterface
     */
    protected $token;
    
    /**
     * @var SignCalculatorInterface
     */
    protected $signCalculator;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var bool
     */
    protected $testMode;

    /**
     * 
     * @var Browser
     */
    protected $buzz;
   
    /**
     * 
     * @param Router $router
     * @param SignCalculatorInterface $signCalculator
     * @param string $url
     * @param bool $testMode
     */
    public function __construct(RouterInterface $router, TokenInterface $token, SignCalculatorInterface $signCalculator, $url, $testMode)
    {
        $this->router = $router;
        $this->token = $token;
        $this->signCalculator = $signCalculator;
        $this->url = $url;
        $this->testMode = $testMode;
    }

    public function setBuzz(Browser $buzz) {
        $this->buzz = $buzz;
    }
    
    /**
     * This method executes a deposit transaction without prior approval
     * (aka "sale", or "authorization with capture" transaction).
     *
     * A typical use case for this method is an electronic check payments
     * where authorization is not supported. It can also be used to deposit
     * money in only one transaction, and thus saving processing fees for
     * another transaction.
     *
     * @param FinancialTransactionInterface $transaction The transaction
     * @param boolean                       $retry       Retry
     */
    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        if ($transaction->getState() === FinancialTransactionInterface::STATE_NEW || (FinancialTransactionInterface::STATE_PENDING && $retry == false)) {
            throw $this->createCashbillRedirectActionException($transaction);
        }
        
        $this->approve($transaction, $retry);
        $this->deposit($transaction, $retry);
    }

    /**
     * @param FinancialTransactionInterface $transaction
     *
     * @return ActionRequiredException
     */
    public function createCashbillRedirectActionException(FinancialTransactionInterface $transaction)
    {
        $actionRequest = new ActionRequiredException('Redirecting to Cashbill.');
        $actionRequest->setFinancialTransaction($transaction);

        $instruction = $transaction->getPayment()->getPaymentInstruction();
        $extendedData = $transaction->getExtendedData();
        $userdata = $extendedData->has('userdata') ? $extendedData->get('userdata') : null;
        $userdata = $instruction->getId();
        $extendedData->set('userdata',$userdata);
        
        $postData = array(
        	'id' => $this->token->getId(),
            'service' => $this->token->getPosId(),
            'amount' => $transaction->getRequestedAmount()
        );
        
        $keys = array('desc','op','lang','userdata','forname','surname','email','tel','street','street_n1','street_n2','city','postcode','country');
        foreach($keys as $key) {
            $postData[$key] = $extendedData->has($key) ? $extendedData->get($key) : '';
        }
        $sign = $this->signCalculator->calculateSign($postData);
        $postData['sign'] = $sign;
        
        // FIXME: check why SSL invalid
        $this->buzz->getClient()->setOption(CURLOPT_SSL_VERIFYPEER,false);
        $msg = $this->buzz->submit($this->url, $postData);
        
        $content = $msg->getContent();
        $url = $this->parseContent($content);

        $actionRequest->setAction(new VisitUrl($url));

        return $actionRequest;
    }

    private function parseContent($content) {
        $matches = array();
        preg_match('/\<form action\=\"([^\"]*)?\"/',$content, $matches);
        $url = count($matches) == 2 ? $matches[1] : null; 
        
        $matches = array();
        
        preg_match('/name\=\"([^\"]*)?\"/',$content, $matches);
        $tokenName = count($matches) == 2 ? $matches[1] : null;
        
        preg_match('/value\=\"([^\"]*)?\"/',$content, $matches);
        $token = count($matches) == 2 ? $matches[1] : null;
        
        $url .= '?'.$tokenName.'='.$token;
       
        return $url;
    }
    
    /**
     * This method executes an approve transaction.
     *
     * By an approval, funds are reserved but no actual money is transferred. A
     * subsequent deposit transaction must be performed to actually transfer the
     * money.
     *
     * A typical use case, would be Credit Card payments where funds are first
     * authorized.
     *
     * @param FinancialTransactionInterface $transaction The transaction
     * @param boolean                       $retry       Retry
     */
    public function approve(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();
        $this->checkExtendedDataBeforeApproveAndDeposit($data);
        
        if($data->get('status') != self::STATUS_OK) {
            $ex = new FinancialException('Payment status error: '.$data->get('status'));
            $ex->setFinancialTransaction($transaction);
            $transaction->setResponseCode('Error');
            
            throw $ex;
        }
        
        $transaction->setReferenceNumber($data->get('orderid'));
        $transaction->setProcessedAmount($data->get('amount'));
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
    }

    /**
     * This method executes a deposit transaction (aka capture transaction).
     *
     * This method requires that the Payment has already been approved in
     * a prior transaction.
     *
     * A typical use case are Credit Card payments.
     *
     * @param FinancialTransactionInterface $transaction The transaction
     * @param boolean                       $retry       Retry
     *
     * @return mixed
     */
    public function deposit(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();

        $this->checkExtendedDataBeforeApproveAndDeposit($data);

        switch ($data->get('status')) {
            case self::STATUS_OK:
                break;
            case self::STATUS_ERR:
                $ex = new FinancialException('PaymentAction rejected.');
                $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
                $transaction->setReasonCode(PluginInterface::REASON_CODE_BLOCKED);
                $ex->setFinancialTransaction($transaction);

                throw $ex;
        }

        $transaction->setReferenceNumber($data->get('orderid'));
        $transaction->setProcessedAmount($data->get('amount'));
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
    }

    /**
     * Check that the extended data contains the needed values
     * before approving and depositing the transation
     *
     * @param ExtendedData $data
     *
     * @throws BlockedException
     */
    protected function checkExtendedDataBeforeApproveAndDeposit(ExtendedData $data) {

        if (!$data->has('status') || !$data->has('orderid') || !$data->has('amount')) {
            // if these data are missing, we should wait the response from DotPay
            // and the transaction should stay in pending state
            throw new BlockedException("Awaiting extended data from Cashbill");
        }
    }

    /**
     * @param PaymentInstructionInterface $paymentInstruction
     *
     * @throws \JMS\Payment\CoreBundle\Plugin\Exception\InvalidPaymentInstructionException
     */
    public function checkPaymentInstruction(PaymentInstructionInterface $paymentInstruction)
    {
        $errorBuilder = new ErrorBuilder();
        $data = $paymentInstruction->getExtendedData();

        // TODO Check requirements here
        if ($errorBuilder->hasErrors()) {
            throw $errorBuilder->getException();
        }
    }

    /**
     * @param string $paymentSystemName
     *
     * @return boolean
     */
    public function processes($paymentSystemName)
    {
        return 'cashbill_direct' === $paymentSystemName;
    }
}