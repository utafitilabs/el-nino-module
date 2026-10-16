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

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Uhifadhi\ElNino\Model\PayloadException;
use Uhifadhi\ElNino\Model\ProducerBulletin;
use Uhifadhi\ElNino\Service\BulletinIngestService;
use Uhifadhi\ElNino\Service\ProducerPayloadReader;
use Uhifadhi\ElNino\Service\WatchService;

/**
 * THE DOOR A PRODUCER POSTS A BULLETIN THROUGH.
 *
 * One multipart POST per build, one part per file under its contract path
 * (`status.json`, `series.json`, `map/roads.geojson`, …), a bearer token in
 * the Authorization header, and the contract named in `X-El-Nino-Contract`.
 * The token is the whole of the authentication: it names the area, and the
 * bulletin must name that area's producer. A refused push is a 4xx with a
 * reason the producer's author can read; only a store failure is a 503, the
 * one answer worth retrying.
 *
 * Outside every firewall by the installation's own rule — the README gives
 * the one PUBLIC_ACCESS line — so the limiter, the size cap and the token are
 * what stand here.
 */
final readonly class IngestController
{
    public const string PATH = '/el-nino/ingest';
    public const string CONTRACT_HEADER = 'X-El-Nino-Contract';

    /** @var array<string, string> contract path => the reader's part name; the bare names are taken too */
    public const array PARTS = [
        'status.json' => ProducerPayloadReader::STATUS,
        'series.json' => ProducerPayloadReader::SERIES,
        'map/roads.geojson' => ProducerPayloadReader::ROADS,
        'map/destinations.geojson' => ProducerPayloadReader::DESTINATIONS,
        'map/susceptibility.geojson' => ProducerPayloadReader::SUSCEPTIBILITY,
        'map/bomas.geojson' => ProducerPayloadReader::BOMAS,
        'map/provenance.json' => ProducerPayloadReader::PROVENANCE,
    ];

    public function __construct(
        private WatchService $watches,
        private ProducerPayloadReader $reader,
        private BulletinIngestService $ingest,
        private RateLimiterFactoryInterface $elNinoIngestLimiter,
        private ?LoggerInterface $logger,
        private int $bodyCap,
    ) {
    }

    #[Route(self::PATH, name: 'el_nino_ingest', methods: ['POST'])]
    public function ingest(Request $request): Response
    {
        $ip = $request->getClientIp() ?? 'unknown';
        if (!$this->elNinoIngestLimiter->create($ip)->consume()->isAccepted()) {
            return self::problem(Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited', 'Too many pushes from this address; try again in a minute.');
        }

        $header = $request->headers->get('Authorization') ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return self::problem(Response::HTTP_UNAUTHORIZED, 'no_token', 'Send the producer token as "Authorization: Bearer <token>".');
        }
        $watch = $this->watches->findByToken(substr($header, 7));
        if (null === $watch) {
            return self::problem(Response::HTTP_UNAUTHORIZED, 'unknown_token', 'The token names no area; it may have been withdrawn.');
        }

        $contract = $request->headers->get(self::CONTRACT_HEADER);
        if (null !== $contract && ProducerBulletin::CONTRACT !== $contract) {
            return self::problem(Response::HTTP_BAD_REQUEST, 'wrong_contract', \sprintf('This installation reads "%s".', ProducerBulletin::CONTRACT));
        }

        $parts = [];
        foreach (self::PARTS as $path => $name) {
            foreach ([$path, self::asPhpNamesIt($path), $name, basename($path), self::asPhpNamesIt(basename($path))] as $key) {
                $text = $this->part($request, $key);
                if (null === $text) {
                    continue;
                }
                if (\strlen($text) > $this->bodyCap) {
                    return self::problem(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'part_too_large', \sprintf('"%s" is larger than the %d bytes this installation takes.', $path, $this->bodyCap));
                }
                $parts[$name] = $text;
                break;
            }
        }
        if ([] === $parts) {
            return self::problem(Response::HTTP_BAD_REQUEST, 'no_parts', 'Send the files as multipart parts named by their contract path: status.json, series.json, map/*.geojson, map/provenance.json.');
        }

        try {
            $bulletin = $this->reader->read($parts);
        } catch (PayloadException $e) {
            return self::problem(Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_bulletin', $e->getMessage());
        }

        try {
            $report = $this->ingest->ingest($watch, $bulletin);
        } catch (PayloadException $e) {
            return self::problem(Response::HTTP_FORBIDDEN, 'wrong_area', $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger?->error('el-nino: bulletin ingest failed', ['exception' => $e]);

            return self::problem(Response::HTTP_SERVICE_UNAVAILABLE, 'store_failed', 'The bulletin could not be stored; retry later.');
        }

        return new JsonResponse($report->toArray(), $report->duplicate ? Response::HTTP_OK : Response::HTTP_ACCEPTED);
    }

    /** A part's text: an uploaded file, or a plain field where the producer sent one. */
    /**
     * PHP rewrites a dot or a space in a multipart part's name to an underscore
     * before the request reaches the application ("status.json" arrives as
     * "status_json", "map/roads.geojson" as "map/roads_geojson"), so a part
     * named by its contract path is looked up under that name too.
     *
     * @see https://www.php.net/manual/en/language.variables.external.php — "Dots and spaces in variable names are converted to underscores."
     */
    private static function asPhpNamesIt(string $name): string
    {
        return strtr($name, ['.' => '_', ' ' => '_']);
    }

    private function part(Request $request, string $key): ?string
    {
        $file = $request->files->get($key);
        if ($file instanceof UploadedFile) {
            if (!$file->isValid() || $file->getSize() > $this->bodyCap) {
                return str_repeat(' ', $this->bodyCap + 1);
            }
            $content = file_get_contents($file->getPathname());

            return false === $content ? null : $content;
        }
        if ($request->request->has($key)) {
            return $request->request->getString($key);
        }

        return null;
    }

    private static function problem(int $status, string $error, string $message): JsonResponse
    {
        return new JsonResponse(['error' => $error, 'message' => $message], $status);
    }
}
