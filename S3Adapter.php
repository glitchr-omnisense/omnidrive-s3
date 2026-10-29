<?php

namespace Omnidrive\S3;

use AsyncAws\Core\Exception\Http\ClientException;
use AsyncAws\Core\Exception\Exception as AsyncAwsException;
use AsyncAws\S3\S3Client;
use League\Flysystem\AsyncAwsS3\AsyncAwsS3Adapter;
use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\AsyncAwsS3\VisibilityConverter;

/**
 * Flysystem's AsyncAws adapter, with a file's existence asked by HeadObject: 200 is there, 404
 * is not, any other answer (403: wrong keys, no permission) a failure. The adapter's
 * objectExists() waiter read a 403 as "not there", so a refused request looked like a missing
 * file. AWS answers 404 for a missing object only to a caller with s3:ListBucket, 403 to one
 * without: grant it.
 */
final class S3Adapter extends AsyncAwsS3Adapter
{
    private readonly PathPrefixer $keys;

    public function __construct(
        private readonly S3Client $s3,
        private readonly string $s3Bucket,
        string $prefix = '',
        ?VisibilityConverter $visibility = null,
    ) {
        parent::__construct($s3, $s3Bucket, $prefix, $visibility);
        $this->keys = new PathPrefixer($prefix);
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->s3->headObject(['Bucket' => $this->s3Bucket, 'Key' => $this->keys->prefixPath($path)])->resolve();

            return true;
        } catch (ClientException $e) {
            if (404 === $e->getResponse()->getStatusCode()) {
                return false;
            }
            throw UnableToCheckFileExistence::forLocation($path, $e);
        } catch (AsyncAwsException $e) {
            throw UnableToCheckFileExistence::forLocation($path, $e);
        }
    }
}
