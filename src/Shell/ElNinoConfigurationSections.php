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

namespace Uhifadhi\ElNino\Shell;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Contracts\Shell\ConfigurationSection;
use Uhifadhi\Contracts\Shell\ConfigurationSectionsInterface;
use Uhifadhi\ElNino\Controller\ProducerController;
use Uhifadhi\ElNino\Module\ElNinoModuleProvider;
use Uhifadhi\ElNino\Service\WatchService;

/**
 * THE MODULE'S ONE CONFIGURE PAGE, as sections the shell draws: the producer
 * that feeds this area, the token it posts with, and the words the area adds
 * to the page in its own voice.
 */
final class ElNinoConfigurationSections implements ConfigurationSectionsInterface
{
    /** The shell's own configure route, which is where a section body is drawn. */
    private const string CONFIGURE_ROUTE = 'shell_module_configure';

    public const string PRODUCER = 'producer';

    /**
     * The token a freshly answered issue left in the session, read once per
     * request and kept here, because the shell asks for the sections more than
     * once while it draws one page and the token is printed exactly once.
     *
     * @var array<int, string|null>
     */
    private array $issuedByRequest = [];

    public function __construct(
        private readonly RequestStack $requests,
        private readonly AreaOfInterestRepository $areas,
        private readonly WatchService $watches,
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function slug(): string
    {
        return ElNinoModuleProvider::SLUG;
    }

    public function heading(): string
    {
        $area = $this->currentArea();

        return null === $area ? '' : $area->getName().' — El Niño watch';
    }

    public function summary(): string
    {
        return 'Which producer sends this area its weekly bulletin, the token it posts with, and the words this area adds to the page in its own voice.';
    }

    public function sections(): array
    {
        return [
            ConfigurationSection::page(
                self::PRODUCER,
                'Producer',
                '@UhifadhiElNino/configure/_producer.html.twig',
                $this->producerVariables(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function producerVariables(): array
    {
        $request = $this->requests->getCurrentRequest();
        $area = $this->currentArea();
        if (null === $request || null === $area || self::CONFIGURE_ROUTE !== $request->attributes->get('_route')) {
            return [];
        }

        $key = spl_object_id($request);
        if (!\array_key_exists($key, $this->issuedByRequest)) {
            $issued = null;
            if ($request->hasSession()) {
                $session = $request->getSession();
                $issued = $session->get(ProducerController::ISSUED_TOKEN_SESSION_KEY);
                $session->remove(ProducerController::ISSUED_TOKEN_SESSION_KEY);
            }
            $this->issuedByRequest = [$key => \is_string($issued) ? $issued : null];
        }

        return [
            'area' => $area,
            'watch' => $this->watches->forArea($area),
            'issuedToken' => $this->issuedByRequest[$key],
            'csrfToken' => $this->csrfTokenManager?->getToken(ProducerController::CSRF_TOKEN_ID)->getValue() ?? '',
        ];
    }

    private function currentArea(): ?AreaOfInterest
    {
        $uuid = $this->requests->getCurrentRequest()?->attributes->get('uuid');
        if (!\is_string($uuid) || !Uuid::isValid($uuid)) {
            return null;
        }

        return $this->areas->findOneByUuid($uuid);
    }
}
