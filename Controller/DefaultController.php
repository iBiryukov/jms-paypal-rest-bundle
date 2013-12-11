<?php

namespace Wanawork\JMS\PaypalRestBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('WanaworkJMSPaypalRestBundle:Default:index.html.twig', array('name' => $name));
    }
}
