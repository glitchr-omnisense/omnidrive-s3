# omnidrive/s3

An S3 bucket as an [omnisense/omnidrive](https://github.com/glitchr-omnisense/omnidrive) drive - AWS,
or any service that speaks S3: MinIO, Scaleway, OVHcloud, Cloudflare R2, Garage... - through
league/flysystem-async-aws-s3 (`AsyncAwsS3Adapter` over AsyncAws's `SimpleS3Client`).

| | Address |
|---|---|
| AWS | `https://s3.<region>.amazonaws.com/<bucket>/...` (AsyncAws's, from `region`) |
| another service | `endpoint` (`http://minio:9000`, `https://s3.fr-par.scw.cloud`), the bucket in the host name or, with `path_style`, in the path |

## Installation

```sh
composer require omnisense/omnidrive omnidrive/s3
```

Requires PHP 8.2, `omnisense/omnidrive` (1.x), `league/flysystem-async-aws-s3` (^3.0),
`async-aws/simple-s3` (^2.1) and `symfony/http-client-contracts`; AsyncAws calls S3 through
`symfony/http-client`.

## Credentials

An access key and its secret (`key`, `secret`, and `session_token` for temporary credentials) with
the permissions the drive uses on the bucket (get, put, delete, list objects; put object ACL for a
visibility). Without `key` and `secret`, AsyncAws's own chain is used: the environment
(`AWS_ACCESS_KEY_ID`...), `~/.aws`, the instance's or the container's role. MinIO's are its root
account or an access key made in its console; the harness's MinIO uses `omnidrive` /
`omnidrive-harness`.

## Options

`new S3DriveFactory(?Symfony\Contracts\HttpClient\HttpClientInterface $http = null)` (the
application's HTTP client, else AsyncAws's own); factory name `s3`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `bucket` | string | - | yes | |
| `region` | string | `us-east-1` | no | |
| `endpoint` | string | AWS's | no | another S3 service |
| `path_style` | bool | `false` | no | the bucket in the path, not in the host (MinIO) |
| `key`, `secret` | string | AsyncAws's chain | no | |
| `session_token` | string | none | no | |
| `prefix` | string | `''` | no | every path under it (slashes trimmed) |
| `public_url` | string or list | none | no | the prefix files are served from, or several (sharded) |
| `visibility` | `public`/`private` | `private` (Flysystem's S3 default) | no | a write's visibility when it does not say |
| `directory_visibility` | `public`/`private` | `public` | no | the same for directories |
| `retain_visibility` | bool | `true` | no | whether a copy keeps its source's visibility |

## Capabilities

| Capability | |
|---|---|
| `local` | no |
| `publicUrls` | yes: the object's address on the service, or `public_url` |
| `temporaryUrls` | yes: a presigned GET |
| visibility per file | the object's ACL (`public-read` or `private`), where the bucket keeps ACLs |
| checksum | any algorithm `hash()` knows (the file read and hashed); `etag`: the object's ETag |

## Behaviour

A write is a `PUT` of the object under `prefix`, with the ACL of its visibility and a
`Content-Type` from the file name (or the `ContentType` / `mimetype` option); large files go
through AsyncAws's multipart upload (not run here). A listing is `ListObjectsV2`, paginated by AsyncAws; directories are
the common prefixes, `createDirectory()` writes the marker object `<path>/`. `publicUrl()`
without `public_url` is the service's address of the object, including the prefix; with
`public_url` it is that prefix followed by the drive's path - **without** the S3 `prefix` (point
the CDN at `<bucket>/<prefix>`). The address is not a permission: a private object's is refused
by the service. `temporaryUrl()` presigns a GET valid until `$expiresAt` (`X-Amz-Expires`), served
to whoever holds it. Nothing is sent when the drive is built.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use AsyncAws\SimpleS3\SimpleS3Client;
use Omnidrive\Registry;
use Omnidrive\S3\S3DriveFactory;

$minio = ['endpoint' => 'http://minio:9000', 'path_style' => true, 'key' => 'omnidrive', 'secret' => 'omnidrive-harness'];
$drives = new Registry([new S3DriveFactory()], [
    'media' => ['factory' => 's3', 'options' => ['bucket' => 'site', 'prefix' => 'uploads'] + $minio],
    'cdn' => ['factory' => 's3', 'options' => ['bucket' => 'site', 'prefix' => 'uploads', 'public_url' => 'https://cdn.example.org'] + $minio],
]);
try {   // the harness's MinIO starts empty
    (new SimpleS3Client(['endpoint' => 'http://minio:9000', 'pathStyleEndpoint' => 'true', 'accessKeyId' => 'omnidrive', 'accessKeySecret' => 'omnidrive-harness']))->createBucket(['Bucket' => 'site'])->resolve();
} catch (Throwable) {
}
$media = $drives->get('media');

$media->write('2026/photo.jpg', 'JPEG...');
echo json_encode($media->capabilities()->toArray()), "\n";
echo $media->publicUrl('2026/photo.jpg'), "\n";
echo $drives->get('cdn')->publicUrl('2026/photo.jpg'), "\n";
$url = $media->temporaryUrl('2026/photo.jpg', new DateTimeImmutable('+10 minutes'));
parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
echo strtok($url, '?'), ' ', implode(',', array_keys($query)), ' expires ', $query['X-Amz-Expires'], "\n";
echo $media->mimeType('2026/photo.jpg'), ' ', $media->visibility('2026/photo.jpg')->value, ' ', $media->checksum('2026/photo.jpg', 'etag'), "\n";
echo implode(' ', array_map(static fn ($e) => $e->path, [...$media->list('', deep: true)])), "\n";
$media->deleteDirectory('2026');
```

```
{"local":false,"publicUrls":true,"temporaryUrls":true}
http://minio:9000/site/uploads/2026/photo.jpg
https://cdn.example.org/2026/photo.jpg
http://minio:9000/site/uploads/2026/photo.jpg X-Amz-Algorithm,X-Amz-Date,X-Amz-Expires,X-Amz-Credential,x-amz-content-sha256,X-Amz-SignedHeaders,X-Amz-Signature expires 600
image/jpeg private 16d37bc3b1ee7d3b6ec0455727574ddb
2026/photo.jpg
```

(run on 2026-10-25 in omnidrive's harness against its MinIO: the CDN address without the
`uploads` prefix, the presigned one valid 600 seconds, the object written `private`)

## Errors

The service's refusals are `DriveException`s whose message carries AsyncAws's (the status, S3's
error code); a missing object read is a `FileNotFoundException`.

A file's existence is asked with HeadObject: 200 is there, 404 is not, any other answer a
failure - a request refused with 403 (wrong keys, no permission) makes `exists()` throw and
`read()` a `DriveException` naming the 403, never a `FileNotFoundException`. AWS answers 404 for
a missing object only to a caller allowed `s3:ListBucket` on the bucket, 403 to one without: give
the drive's keys that permission, or every missing file is a failure.

A missing `bucket` is an `InvalidConfigException`.

## Verified, and not

Verified (2026-10-23 and 2026-10-25, omnidrive's harness, PHP 8.4): the contract's tests against
MinIO (path style, a prefix); a presigned address fetched and served, the bucket's plain address
refused; in plain PHP (`bare --live`); offline with a mock HTTP client: the PUT's address under the
prefix, its ACL header, its type from the file name, a signature of ten minutes, AWS's
virtual-host address from a region. Not verified: AWS S3 itself, Scaleway, OVHcloud, R2 (MinIO
only); objects' ACLs (MinIO answers 501; an AWS bucket made since April 2023 refuses ACLs unless
its Object Ownership allows them - `OMNIDRIVE_S3_ACL=1` runs that test against one that does);
uploads over 5 GB.

## Responsibility

Unofficial; affiliated with none of the services it reaches, nor with The PHP League or AsyncAws.
Each S3 service bills its requests and transfers and keeps its own terms; whoever runs this
package complies with them, and answers for the access a public or temporary address gives (see
the README's Responsibility section).
