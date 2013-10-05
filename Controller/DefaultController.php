<?php

namespace Webit\Accounting\PaymentCashbillBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('WebitAccountingPaymentCashbillBundle:Default:index.html.twig', array('name' => $name));
    }
}
