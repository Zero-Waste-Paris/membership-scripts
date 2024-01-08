<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use App\Services\MatomoService;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ApiLoginController extends AbstractController
{
	#[Route('/login', name: 'api_login')]
	public function index(#[CurrentUser] ?User $user, CsrfTokenManagerInterface $csrf, MatomoService $tracker): JsonResponse
	{
		if (null === $user) {
			$tracker->doTrackEvent('authent-missing-credentials');
			return $this->json(['message' => 'missing credentials'], Response::HTTP_UNAUTHORIZED);
		}
		$identifier = $user->getUserIdentifier();
		$response = $this->json([
			'login' => $identifier,
		]);
		$tracker->doTrackEvent('authent-successful', hash("md5", $identifier));

		$cookie = new Cookie('XSRF-TOKEN', $csrf->getToken('id'), strtotime('tomorrow'), null, null, null, false);
		$response->headers->setCookie($cookie);

		return $response;
	}
}
