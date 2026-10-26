# omnidrive/s3

An S3 bucket as an [omnidrive](https://github.com/glitchr-omnisense/omnidrive) drive - AWS, or any
service that speaks S3: MinIO, Scaleway, OVHcloud, Cloudflare R2, Garage... - through
league/flysystem-async-aws-s3 (AsyncAws).

```sh
composer require omnisense/omnidrive omnidrive/s3
```

```php
use Omnidrive\Registry;
use Omnidrive\S3\S3DriveFactory;

$drives = new Registry([new S3DriveFactory()], [
    'archive' => ['factory' => 's3', 'options' => ['bucket' => 'archive', 'region' => 'eu-west-3', 'key' => getenv('S3_KEY'), 'secret' => getenv('S3_SECRET')]],
    'media' => ['factory' => 's3', 'options' => ['bucket' => 'site', 'prefix' => 'uploads', 'endpoint' => 'http://minio:9000', 'path_style' => true, 'key' => getenv('MINIO_KEY'), 'secret' => getenv('MINIO_SECRET')]],
]);

$drives->get('archive')->temporaryUrl('2026/report.pdf', new DateTimeImmutable('+1 hour'));   // signed, expires
```

| Option | Default | |
|---|---|---|
| `bucket` | required | |
| `region` | `us-east-1` | |
| `endpoint` | AWS's | another service than AWS (`http://minio:9000`, `https://s3.fr-par.scw.cloud`) |
| `path_style` | false | the bucket in the path, not in the host name (MinIO) |
| `key`, `secret`, `session_token` | AsyncAws's own chain | without them: the environment, `~/.aws`, the instance's role |
| `prefix` | none | every path under it |
| `public_url` | the bucket's address | a CDN's, say |
| `visibility` | `private` (Flysystem's) | the ACL a file is written with: `public` is `public-read` |
| `directory_visibility`, `retain_visibility` | | as every drive |

`new S3DriveFactory($httpClient)` uses the application's HTTP client
(symfony/http-client-contracts) rather than AsyncAws's own.

`capabilities()`: not local, public addresses (the bucket's, or `public_url`), temporary addresses
(presigned GET).

## Verified, and not

- Verified (2026-10-23, omnidrive's harness, PHP 8.4): the contract's tests against MinIO (path
  style, a prefix); a presigned address fetched and served, the bucket's plain address refused; in
  plain PHP (`bare --live`); offline with a mock HTTP client: the PUT's address under the prefix,
  its ACL header, its type from the file name, a signature of ten minutes, AWS's virtual-host
  address from a region.
- Not verified: AWS S3 itself, Scaleway, OVHcloud, R2 (MinIO only); objects' ACLs (MinIO answers
  501; an AWS bucket made since April 2023 refuses ACLs unless its Object Ownership allows them -
  `OMNIDRIVE_S3_ACL=1` runs that test against one that does); uploads over 5 GB (AsyncAws's
  multipart upload, never run here).

## Responsibility

Unofficial; affiliated with none of the services it reaches, nor with The PHP League or AsyncAws.
Each S3 service bills its requests and transfers and keeps its own terms; whoever runs this package
complies with them, and answers for the access a public or temporary address gives. MIT licence.
