<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi El Niño Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\ElNino\Controller;

use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;
use Uhifadhi\ElNino\Service\WatchService;
use Uhifadhi\ElNino\Shell\ElNinoConfigurationSections;

/**
 * THE WRITES OF THE PRODUCER SECTION: the words, the token issued, the token
 * withdrawn. Each is its own address and its own form, and each lands back on
 * the section it came from with a flash saying what happened.
 */
#[Route(defaults: [RegistryBundle::MODULE_ROUTE_DEFAULT => ElNinoModuleProvider::SLUG])]
final readonly class ProducerController
{
    /** The token id every form on the Producer section carries. */
    public const string CSRF_TOKEN_ID = 'el_nino_producer';

    /** Where a freshly issued token waits for the one page that may print it. */
    public const string ISSUED_TOKEN_SESSION_KEY = 'el_nino.issued_token';

    public function __construct(
        private UrlGeneratorInterface $router,
        private WatchService $watches,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/areas/{uuid}/modules/elnino/configure/producer', name: 'el_nino_producer_save', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('el-nino.configure', subject: 'area')]
    public function save(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): RedirectResponse {
        $this->guard($request);
        $slug = trim($request->request->getString('producer_slug'));
        if ('' !== $slug && 1 !== preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $slug)) {
            return $this->back($request, $area, 'error', 'A producer slug is lowercase letters, digits and hyphens, forty at most. Nothing was saved.');
        }
        $url = trim($request->request->getString('analysis_url'));
        if ('' !== $url && !str_starts_with($url, 'https://') && !str_starts_with($url, 'http://')) {
            return $this->back($request, $area, 'error', 'The analysis link needs to be a web address. Nothing was saved.');
        }
        $this->watches->saveWords($area, $slug, $url, $request->request->getString('area_record'));

        return $this->back($request, $area, 'success', 'Saved. This area reads its bulletins from the producer named here.');
    }

    #[Route('/areas/{uuid}/modules/elnino/configure/producer/token', name: 'el_nino_producer_token', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('el-nino.configure', subject: 'area')]
    public function issue(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): RedirectResponse {
        $this->guard($request);
        $token = $this->watches->issueToken($area);
        if ($request->hasSession()) {
            $request->getSession()->set(self::ISSUED_TOKEN_SESSION_KEY, $token);
        }

        return $this->back($request, $area, 'success', 'A token was issued. It is printed once, below; copy it into the producer now.');
    }

    #[Route('/areas/{uuid}/modules/elnino/configure/producer/token/withdraw', name: 'el_nino_producer_token_withdraw', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('el-nino.configure', subject: 'area')]
    public function withdraw(
        Request $request,
        #[MapEntity(mapping: ['uuid' => 'uuid'])] AreaOfInterest $area,
    ): RedirectResponse {
        $this->guard($request);
        $this->watches->withdrawToken($area);

        return $this->back($request, $area, 'success', 'The token was withdrawn. The producer is refused until a new one is issued.');
    }

    private function guard(Request $request): void
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token')))) {
            throw new AccessDeniedException('Invalid CSRF token for the El Niño producer section.');
        }
    }

    private function back(Request $request, AreaOfInterest $area, string $type, string $message): RedirectResponse
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }

        return new RedirectResponse($this->router->generate('shell_module_configure', [
            'uuid' => $area->getUuidString(),
            'slug' => ElNinoModuleProvider::SLUG,
            'section' => ElNinoConfigurationSections::PRODUCER,
        ]));
    }
}
