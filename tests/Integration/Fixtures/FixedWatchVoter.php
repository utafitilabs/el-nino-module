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

namespace Uhifadhi\ElNino\Tests\Integration\Fixtures;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Uhifadhi\Bundle\AreaBundle\Access\AreaConcerns;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Contracts\Access\Grant;
use Uhifadhi\Contracts\Access\Verb;
use Uhifadhi\ElNino\Access\ElNinoConcerns;

/**
 * THE VOTER THE SUITE PLAYS: three people, told apart by their address, each
 * holding a fixed set of pairs. A real installation's GrantVoter reads a
 * position; this one reads an email, so a test states who somebody is by
 * signing them in and nothing else.
 *
 * @extends Voter<string, mixed>
 */
final class FixedWatchVoter extends Voter
{
    /** Reads the watch, and nothing else. */
    public const string READER_EMAIL = 'reader@example.test';

    /** Names the producer and issues tokens as well. */
    public const string CONFIGURER_EMAIL = 'configurer@example.test';

    /** Holds nothing of this module's. */
    public const string STRANGER_EMAIL = 'stranger@example.test';

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, self::all(), true);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->supportsAttribute($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || null !== $user->getPosition()) {
            return false;
        }

        return \in_array($attribute, self::tiers()[(string) $user->getEmail()] ?? [], true);
    }

    /** @return array<string, list<string>> */
    private static function tiers(): array
    {
        $reads = [
            self::pair(AreaConcerns::AREAS, Verb::Read),
            self::pair(ElNinoConcerns::WATCH, Verb::Read),
        ];

        return [
            self::READER_EMAIL => $reads,
            self::CONFIGURER_EMAIL => [...$reads, self::pair(ElNinoConcerns::WATCH, Verb::Configure)],
            self::STRANGER_EMAIL => [self::pair(AreaConcerns::AREAS, Verb::Read)],
        ];
    }

    /** @return list<string> */
    private static function all(): array
    {
        $all = [];
        foreach (self::tiers() as $pairs) {
            foreach ($pairs as $pair) {
                $all[$pair] = true;
            }
        }

        return array_keys($all);
    }

    private static function pair(string $concern, Verb $verb): string
    {
        return (string) Grant::of($concern, $verb);
    }
}
