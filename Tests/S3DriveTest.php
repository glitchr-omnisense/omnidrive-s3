<?php

namespace Omnidrive\S3\Tests;

use Omnidrive\Exception\InvalidConfigException;
use Omnidrive\S3\S3DriveFactory;
use Omnidrive\Visibility;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** The options as AsyncAws is given them, offline: what is sent, what is signed. */
final class S3DriveTest extends TestCase
{
    private const OPTIONS = ['bucket' => 'site', 'endpoint' => 'http://minio:9000', 'path_style' => '1', 'key' => 'test-key', 'secret' => 'test-secret', 'prefix' => '/uploads/'];

    public function testAWriteIsAPutUnderThePrefixWithTheAclOfItsVisibility(): void
    {
        /** @var list<array{string, string, array<string, list<string>>}> $requests */
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests) {
            /** @var array<string, list<string>> $headers */
            $headers = $options['normalized_headers'] ?? [];
            $requests[] = [$method, $url, array_map(static fn (array $lines) => array_map('strtolower', $lines), $headers)];

            return new MockResponse('', ['http_code' => 200]);
        });
        $drive = (new S3DriveFactory($http))->create(self::OPTIONS, 'uploads');

        $drive->write('avatars/1.png', 'png', ['visibility' => Visibility::Public]);
        $drive->write('private/2.txt', 'txt');

        [$method, $url, $headers] = $requests[0];
        self::assertSame('PUT', $method);
        self::assertSame('http://minio:9000/site/uploads/avatars/1.png', $url, 'path style, the prefix');
        self::assertSame(['x-amz-acl: public-read'], $headers['x-amz-acl'] ?? []);
        self::assertSame(['content-type: image/png'], $headers['content-type'] ?? [], 'its type from its name');
        self::assertSame(['x-amz-acl: private'], $requests[1][2]['x-amz-acl'] ?? [], 'no visibility given: private, Flysystem\'s default');
    }

    public function testTheAddressesAreTheBucketsAndASignedOneExpires(): void
    {
        $drive = (new S3DriveFactory(new MockHttpClient()))->create(self::OPTIONS);
        $capabilities = $drive->capabilities();

        self::assertSame(['local' => false, 'publicUrls' => true, 'temporaryUrls' => true], $capabilities->toArray());
        self::assertNull($drive->localPath('a.png'));
        self::assertSame('http://minio:9000/site/uploads/a.png', $drive->publicUrl('a.png'));

        $url = $drive->temporaryUrl('a.png', new \DateTimeImmutable('+10 minutes'));
        self::assertStringStartsWith('http://minio:9000/site/uploads/a.png?', $url);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame('600', $query['X-Amz-Expires'] ?? null);
        $signature = $query['X-Amz-Signature'] ?? null;
        self::assertIsString($signature);
        self::assertMatchesRegularExpression('~^[0-9a-f]{64}$~', $signature);

        $cdn = (new S3DriveFactory(new MockHttpClient()))->create(['public_url' => 'https://cdn.example.org'] + self::OPTIONS);
        self::assertSame('https://cdn.example.org/a.png', $cdn->publicUrl('a.png'), 'public_url over the bucket\'s own');
    }

    public function testAnAwsBucketIsAddressedByItsRegion(): void
    {
        $drive = (new S3DriveFactory(new MockHttpClient()))->create(['bucket' => 'site', 'region' => 'eu-west-3', 'key' => 'k', 'secret' => 's']);

        self::assertSame('https://site.s3.eu-west-3.amazonaws.com/a.png', $drive->publicUrl('a.png'));
    }

    public function testTheBucketIsRequired(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The "s3" drive needs: bucket.');
        (new S3DriveFactory())->create(['region' => 'eu-west-3']);
    }
}
