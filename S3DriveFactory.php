<?php

namespace Omnidrive\S3;

use AsyncAws\SimpleS3\SimpleS3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\AsyncAwsS3\PortableVisibilityConverter;
use Omnidrive\Config;
use Omnidrive\Drive;
use Omnidrive\Flysystem\FlysystemDriveFactory;
use Omnidrive\Visibility;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * An S3 bucket - AWS, or any service that speaks S3: MinIO, Scaleway, OVHcloud,
 * Cloudflare R2, Garage... - through league/flysystem-async-aws-s3:
 *
 *   (new S3DriveFactory())->create(['bucket' => 'uploads', 'region' => 'eu-west-3', 'key' => '...', 'secret' => '...']);
 *   (new S3DriveFactory())->create(['bucket' => 'uploads', 'endpoint' => 'http://minio:9000', 'path_style' => true, 'key' => '...', 'secret' => '...']);
 *
 * Options: bucket (required), region (us-east-1), endpoint (another service
 * than AWS), path_style (the bucket in the path, not in the host: MinIO), key and
 * secret (else AsyncAws's own chain: environment, ~/.aws, the instance's role),
 * session_token, prefix (every path under it), public_url (else the bucket's own
 * address), visibility (the ACL a file is written with: "public" is public-read,
 * "private" - Flysystem's default), directory_visibility.
 *
 * The HTTP client is the application's when one is given, else AsyncAws's own.
 */
final class S3DriveFactory extends FlysystemDriveFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnidrive.factory_name' => 's3',
            'omnidrive.factory_title' => 'An S3 bucket',
            'omnidrive.required_options' => ['bucket'],
            'region' => 'us-east-1',
            'path_style' => false,
            'prefix' => '',
        ]);
    }

    protected function build(Config $c, string $name): Drive
    {
        $client = new SimpleS3Client(array_filter([
            'region' => $c->string('region'),
            'endpoint' => $c->string('endpoint'),
            'accessKeyId' => $c->string('key'),
            'accessKeySecret' => $c->string('secret'),
            'sessionToken' => $c->string('session_token'),
            'pathStyleEndpoint' => $c->bool('path_style') ? 'true' : null,
        ], static fn (?string $value) => null !== $value), null, $this->http);

        $adapter = new AsyncAwsS3Adapter(
            $client,
            (string) $c->string('bucket'),
            trim((string) $c->string('prefix'), '/'),
            new PortableVisibilityConverter(Visibility::of($c->string('directory_visibility') ?? 'public')->value),
        );

        return $this->drive($name, $adapter, $c);
    }
}
