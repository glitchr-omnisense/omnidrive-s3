<?php

namespace Omnidrive\S3\Tests;

use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/** Its usage policy, as data: the provider publishes no quota: the limits are unknown, the figures it does publish in the notes. */
final class PolicyTest extends TestCase
{
    public function testThePackageDeclaresItsPolicy(): void
    {
        $policy = UsagePolicy::fromFile(__DIR__.'/../policy.json');

        self::assertSame('s3', $policy->provider, 'named after the factory');
        self::assertNull($policy->rateLimits, 'unknown, not none');
        self::assertNotNull($policy->provenance->terms);
        self::assertTrue($policy->requirements->key);
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertMatchesRegularExpression('~"omnisense": \{\s*"policy": "policy\.json"~', (string) file_get_contents(__DIR__.'/../composer.json'), 'composer.json names it');
    }
}
