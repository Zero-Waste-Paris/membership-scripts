<?php
/*
Copyright (C) 2020-2022  Zero Waste Paris

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class TestController extends AbstractController {

  public function __construct(
    private TotpAuthenticatorInterface $totp,
    private UserRepository $userRepository,
    private LoggerInterface $logger
  ){}

  #[Route("api/test/set-candidate-totp")]
  public function stuff(#[CurrentUser] ?User $user): Response {
    if ($user === null) {
      return new Response("not (fully) authentified");
    }
    if ($user->getTotpSecret()) {
      $this->logger->info("user " . $user->getEmail() . " has already an associated totp secret");
    } else {
      $this->logger->info("setting a totp secret to user " . $user->getEmail());
      $user->setTotpAuthenticationEnabled(true);
      $user->setTotpSecret($this->totp->generateSecret());
      $this->userRepository->saveAndFlush($user);
    }

    return $this->displayQrCode($this->totp->getQRContent($user));
  }

  #[Route("api/test/drop-totp")]
  public function dropTotp(#[CurrentUser] ?User $user): Response {
    if ($user === null) {
      return new Response("not (fully) authentified");
    }
    $user->setTotpAuthenticationEnabled(false);
    $user->setTotpSecret(null);
    $this->userRepository->saveAndFlush($user);

  }

  private function displayQrCode(string $qrCodeContent): Response
  {
    $builder = new Builder(
      writer: new PngWriter(),
      data: $qrCodeContent
    );
    return new Response($builder->build()->getString(), 200, ['Content-Type' => 'image/png']);
  }
}
