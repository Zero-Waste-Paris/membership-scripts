<?php
namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(priority: 33 /*run before RouterListener*/)]
class StaticRouteListener {
	public function __construct(
		private ContainerBagInterface $bag,
		private LoggerInterface $logger,
	) {}

	public function __invoke(RequestEvent $event): void {
		$request = $event->getRequest();
		if ($this->isRequestToBeHandledByTheFront($request)) {
			$this->logger->info("detected a route to be handled by the front. We serve the UI.");
			$request->attributes->set('_controller', function(): Response {
				$content = file_get_contents($this->bag->get('kernel.project_dir')  . "/public/index.html");
				return new Response($content);
			});
		}
	}

	private function isRequestToBeHandledByTheFront(Request $request): bool {
		return !str_starts_with($request->getPathInfo(), "/api")
			&& $request->getPathInfo() !== "/login"
			&& $request->getPathInfo() !== "/logout";
	}

}
