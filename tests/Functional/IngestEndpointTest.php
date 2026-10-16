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

namespace Uhifadhi\ElNino\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\ElNino\Controller\IngestController;
use Uhifadhi\ElNino\Devkit\SyntheticBulletin;
use Uhifadhi\ElNino\Entity\Bulletin;
use Uhifadhi\ElNino\Service\ProducerPayloadReader;
use Uhifadhi\ElNino\Service\WatchService;
use Uhifadhi\ElNino\Tests\Fixtures\ProducerFiles;

/**
 * The door as the producer meets it: one multipart POST per build, the files
 * as parts named by their contract path, a bearer token, and plain answers.
 */
final class IngestEndpointTest extends WebTestCase
{
    use EveryAreaRunsTheWatch;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private AreaOfInterest $area;
    private string $token;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;
        self::freshSchema($this->em);
        $this->area = new AreaOfInterest()->setSource('test fixture')->setName('Seed reserve')->setGeom(
            '{"type":"MultiPolygon","coordinates":[[[[12.2,-5.8],[12.5,-5.8],[12.5,-5.5],[12.2,-5.5],[12.2,-5.8]]]]}',
        );
        $this->em->persist($this->area);
        $this->em->flush();
        $watches = static::getContainer()->get('test_public.'.WatchService::class);
        \assert($watches instanceof WatchService);
        $watches->saveWords($this->area, SyntheticBulletin::SLUG, null, null);
        $this->token = $watches->issueToken($this->area);
        $this->everyAreaRunsTheWatch($this->em);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }

    public function testAPushWithTheTokenStoresTheBulletinAndAnswersAccepted(): void
    {
        $this->push($this->files(), $this->token);

        self::assertResponseStatusCodeSame(202);
        $answer = $this->answer();
        self::assertSame('stored', $answer['status']);
        self::assertSame(5, $answer['weeks']);
        self::assertSame('imported', $answer['exposure']);
        self::assertSame(1, $this->em->getRepository(Bulletin::class)->count([]));
    }

    public function testTheSameBuildPushedAgainAnswersDuplicateWithOk(): void
    {
        $this->push($this->files(), $this->token);
        $this->push($this->files(), $this->token);

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->answer()['duplicate']);
        self::assertSame(1, $this->em->getRepository(Bulletin::class)->count([]));
    }

    public function testThePartsMayBeNamedByTheirBareNamesToo(): void
    {
        $parts = ProducerFiles::parts(SyntheticBulletin::over(self::box(), new \DateTimeImmutable('2026-10-12 05:07', new \DateTimeZone('UTC')), 5));
        $this->client->request('POST', IngestController::PATH, $parts, [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        self::assertResponseStatusCodeSame(202);
    }

    public function testNoTokenIsUnauthorized(): void
    {
        $this->push($this->files(), null);
        self::assertResponseStatusCodeSame(401);
        self::assertSame('no_token', $this->answer()['error']);
    }

    public function testAWithdrawnTokenIsUnauthorized(): void
    {
        $watches = static::getContainer()->get('test_public.'.WatchService::class);
        \assert($watches instanceof WatchService);
        $watches->withdrawToken($this->area);

        $this->push($this->files(), $this->token);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('unknown_token', $this->answer()['error']);
    }

    public function testABulletinAboutAnotherAreaIsForbiddenByName(): void
    {
        $other = SyntheticBulletin::over(self::box(), new \DateTimeImmutable('2026-10-12 05:07', new \DateTimeZone('UTC')), 2, true, 'elsewhere');
        $this->push($this->filesOf($other), $this->token);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('wrong_area', $this->answer()['error']);
        self::assertStringContainsString('"elsewhere"', self::message($this->answer()));
    }

    public function testAnotherContractIsRefusedBeforeAnythingIsRead(): void
    {
        $this->push($this->files(), $this->token, ['HTTP_X_EL_NINO_CONTRACT' => 'el-nino-producer/9']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('wrong_contract', $this->answer()['error']);
    }

    public function testAFileThatIsNotTheContractIsUnprocessableWithTheFault(): void
    {
        $files = $this->files();
        unset($files['series.json']);
        $this->push($files, $this->token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_bulletin', $this->answer()['error']);
        self::assertStringContainsString('series: is missing', self::message($this->answer()));
    }

    public function testAPushWithNoPartsSaysWhatToSend(): void
    {
        $this->client->request('POST', IngestController::PATH, [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('no_parts', $this->answer()['error']);
    }

    /**
     * @param array<string, UploadedFile> $files
     * @param array<string, string>       $headers
     */
    private function push(array $files, ?string $token, array $headers = []): void
    {
        $server = $headers;
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->client->request('POST', IngestController::PATH, [], $files, $server);
    }

    /** @return array<string, UploadedFile> */
    private function files(): array
    {
        return $this->filesOf(SyntheticBulletin::over(self::box(), new \DateTimeImmutable('2026-10-12 05:07', new \DateTimeZone('UTC')), 5));
    }

    /** @return array<string, UploadedFile> */
    private function filesOf(\Uhifadhi\ElNino\Model\ProducerBulletin $bulletin): array
    {
        $byName = array_flip(IngestController::PARTS);
        $files = [];
        foreach (ProducerFiles::parts($bulletin) as $name => $text) {
            $path = $byName[$name] ?? $name;
            $tmp = tempnam(sys_get_temp_dir(), 'el-nino-');
            \assert(false !== $tmp);
            file_put_contents($tmp, $text);
            $files[$path] = new UploadedFile($tmp, basename($path), 'application/json', null, true);
        }
        \assert(isset($files['status.json']) && isset($files[ProducerPayloadReader::SERIES.'.json']));

        return $files;
    }

    /** @return array<string, mixed> */
    private function answer(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /** @param array<string, mixed> $answer */
    private static function message(array $answer): string
    {
        $message = $answer['message'] ?? null;

        return \is_string($message) ? $message : '';
    }

    /** @return array{west: float, south: float, east: float, north: float} */
    private static function box(): array
    {
        return ['west' => 12.2, 'south' => -5.8, 'east' => 12.5, 'north' => -5.5];
    }
}
