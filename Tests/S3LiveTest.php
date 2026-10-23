<?php

namespace Omnidrive\S3\Tests;

use AsyncAws\SimpleS3\SimpleS3Client;
use Omnidrive\Drive;
use Omnidrive\S3\S3DriveFactory;
use Omnidrive\Testing\DriveContractTests;
use PHPUnit\Framework\TestCase;

/**
 * The contract against a real S3 service: the harness's MinIO
 * (docker compose run --rm omnidrive test), or the one OMNIDRIVE_S3_* name.
 * Skipped when none is named. The bucket is made when it is not there.
 */
final class S3LiveTest extends TestCase
{
    use DriveContractTests;

    /** @return array<string, string> */
    private static function options(): array
    {
        $endpoint = getenv('OMNIDRIVE_S3_ENDPOINT');
        if (false === $endpoint || '' === $endpoint) {
            self::markTestSkipped('No S3 service named (OMNIDRIVE_S3_ENDPOINT).');
        }

        return [
            'bucket' => (string) getenv('OMNIDRIVE_S3_BUCKET'),
            'region' => (string) (getenv('OMNIDRIVE_S3_REGION') ?: 'us-east-1'),
            'endpoint' => $endpoint,
            'path_style' => (string) getenv('OMNIDRIVE_S3_PATH_STYLE'),
            'key' => (string) getenv('OMNIDRIVE_S3_KEY'),
            'secret' => (string) getenv('OMNIDRIVE_S3_SECRET'),
            'prefix' => 'live',
        ];
    }

    /**
     * MinIO answers 501 to an object's ACL, and an AWS bucket made since April 2023 refuses ACLs
     * (Object Ownership "bucket owner enforced"): OMNIDRIVE_S3_ACL=1 for a bucket that keeps them.
     */
    protected function keepsVisibility(): bool
    {
        return \in_array(getenv('OMNIDRIVE_S3_ACL'), ['1', 'true'], true);
    }

    protected function drive(): Drive
    {
        $o = self::options();
        $client = new SimpleS3Client(['region' => $o['region'], 'endpoint' => $o['endpoint'], 'accessKeyId' => $o['key'], 'accessKeySecret' => $o['secret'], 'pathStyleEndpoint' => '' !== $o['path_style'] && '0' !== $o['path_style'] ? 'true' : 'false']);
        if (!$client->bucketExists(['Bucket' => $o['bucket']])->isSuccess()) {
            $client->createBucket(['Bucket' => $o['bucket']])->resolve();
        }

        return (new S3DriveFactory())->create($o, 's3');
    }

    public function testASignedAddressServesTheFileUntilItExpires(): void
    {
        $drive = $this->drive();
        $drive->write('signed/hello.txt', 'signed');

        $url = $drive->temporaryUrl('signed/hello.txt', new \DateTimeImmutable('+1 minute'));
        self::assertSame('signed', @file_get_contents($url));
        self::assertFalse(@file_get_contents($drive->publicUrl('signed/hello.txt')), 'the bucket is private: its plain address serves nothing');

        $drive->delete('signed/hello.txt');
    }
}
