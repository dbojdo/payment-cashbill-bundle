<?php

namespace Webit\Accounting\PaymentCashbillBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use JMS\Payment\CoreBundle\Entity\PaymentInstruction;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;

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
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function checkAction()
    {
        $piId = (int)$this->getRequest()->get('userdata');
        $pi = $this->getPaymentInstruction($piId);
        
//         $this->get('event_dispatcher')->dispatch(
//             CashbillEvents::PAYMENT_CASHBILL_CONFIRMATION_RECEIVED,
//             new CashbillConfirmationReceivedEvent($instruction, $request->request)
//         );

        $token = $this->get('webit_accounting_payment_cashbill.token');
        $logger = $this->get('logger');

        $reqSign = $this->getRequest()->get('sign');
        $data = array(
        	'id' => $this->getRequest()->get('id'),
            'service' => $this->getRequest()->get('service'),
            'orderid' => $this->getRequest()->get('orderid'),
            'amount' => $this->getRequest()->get('amount'),
            'userdata' => $this->getRequest()->get('userdata'),
            'status' => $this->getRequest()->get('status')
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
        
        $transaction->setReferenceNumber($this->getRequest()->get('orderid'));
        $amount = (float)$this->getRequest()->get('amount');
    
        $request = $this->getRequest();
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
    
    public function returnAction() {
        $route = $this->container->getParameter('webit_accounting_payment_cashbill.return_route');
        
        $data = $this->getRequest()->query->all();
        $data['payment_instruction_id'] = $this->getRequest()->get('userdata');
        
        $route = $this->container->getParameter('webit_accounting_payment_cashbill.return_route');
        $url = $this->generateUrl($route,$data,Router::ABSOLUTE_PATH);
        die(var_dump($url));
        return $this->redirect($url);
    }
}
