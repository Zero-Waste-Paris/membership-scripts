<?php

namespace App\Totp;

use App\Controller\ApiLoginController;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
	public function __construct(private CsrfTokenManagerInterface $csrf) {}

	public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
	{
		if ($token instanceof TwoFactorTokenInterface) {
			return new Response('{"login": "success", "two_factor_complete": false}');
	    }

		// Otherwise return the default response for successful login. could do this by decorating
		// the original authentication success handler and calling it here.
        return new JsonResponse(['login' => $token->getUser()->getUserIdentifier()]);
		return (new ApiLoginController())->successfullLoginResponse($token->User(), $this->csrf);
	}
}

