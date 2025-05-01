<?php

namespace App\Totp;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class TwoFactorAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        // Return the response to tell the client that authentication including two-factor
        // authentication is complete now.
        return new Response('{"login": ' . $token->getUser()->getUserIdentifier() . ', "two_factor_complete": true}');
   }
}
