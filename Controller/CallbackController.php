<?php

namespace Webit\Accounting\PaymentCashbillBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use JMS\Payment\CoreBundle\Entity\PaymentInstruction;
use Webit\Accounting\PaymentCashbillBundle\Event\CashbillConfirmationReceivedEvent;
use Webit\Accounting\PaymentCashbillBundle\Event\Events as CashbillEvents;
use Webit\Accounting\PaymentCashbillBundle\Form\SignCalculatorInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

/**
 * Callback controller
 *
 * @author dbojdo
 */
class CallbackController extends Controller
{
    /**
     * 
     * @param int $id
     * @return PaymentInstruction
     */
    private function getPaymentInstruction($id) {
        $pi = $this->get('doctrine.orm.entity_manager')->getRepository('JMS\Payment\CoreBundle\Entity\PaymentInstruction')->find($id);
        
        return $pi;
    }
    
    /**
     * 
     * @return SignCalculatorInterface
     */
    private function getSignCalculator() {
        return $this->get('webit_accounting_payment_cashbill.sign_calculator');
    }

    /**
     *
     * @param Request $request
     * @return Response
     */
    public function checkAction(Request $request)
    {
        $logger = $this->get('logger');
        
        $piId = (int) $request->get('userdata');
        $pi = $this->getPaymentInstruction($piId);
        if(!$pi) {
            $logger->err(sprintf('[Cashbill - URLC] %s (%d)', 'Payment instruction not found', $piId));

            return new Response('FAIL', 500);
        }
        
        $this->get('event_dispatcher')->dispatch(
            CashbillEvents::PAYMENT_CASHBILL_CONFIRMATION_RECEIVED,
            new CashbillConfirmationReceivedEvent($pi, $request)
        );

        $reqSign = $request->get('sign');
        $data = array(
        	'id' => $request->get('id'),
            'service' => $request->get('service'),
            'orderid' => $request->get('orderid'),
            'amount' => $request->get('amount'),
            'userdata' => $request->get('userdata'),
            'status' => $request->get('status')
        );
        $calcSign = $this->getSignCalculator()->calculateSign($data);
       
        if ($reqSign !== $calcSign) {
            $logger->err('[Cashbill - URLC] pin verification failed');

            return new Response('FAIL', 500);
        }

        if (null === $transaction = $pi->getPendingTransaction()) {
            $logger->err('[Cashbill - URLC] no pending transaction found for the payment instruction');

            return new Response('FAIL', 500);
        }
        
        $transaction->setReferenceNumber($request->get('orderid'));
        $amount = (float) $request->get('amount');
    
        $request = $request;
        $transaction->getExtendedData()->set('status', $request->get('status'));
        $transaction->getExtendedData()->set('orderid', $request->get('orderid'));
        $transaction->getExtendedData()->set('amount', $amount);

        try {
            $this->get('payment.plugin_controller')->approveAndDeposit($transaction->getPayment()->getId(), $amount);
        } catch (\Exception $e) {
            $logger->err(sprintf('[Cashbill - URLC] %s', $e->getMessage()));

            return new Response('FAIL', 500);
        }

        $this->get('doctrine.orm.entity_manager')->flush();

        $logger->info(sprintf('[Cashbill - URLC] Payment instruction %s successfully updated', $pi->getId()));

        return new Response('OK');
    }
    
    public function returnAction(Request $request) {
        $data = $request->query->all();
        $data['payment_instruction_id'] = $request->get('userdata');
        
        $route = $this->container->getParameter('webit_accounting_payment_cashbill.return_route');
        $url = $this->generateUrl($route,$data,Router::ABSOLUTE_PATH);
        
        return $this->redirect($url);
    }
}
