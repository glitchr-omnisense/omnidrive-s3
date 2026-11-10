# Changelog

## [Unreleased]

### Added

- Its usage policy, on omnisense/contracts: `policy.json` (named in `composer.json`,
  `extra.omnisense.policy`), verified 2026-11-10; the README's Usage policy section says which
  writes a client's retry could replay (none doubles a file). `Tests/PolicyTest.php`.

### Fixed

- A request S3 refuses (403: wrong keys, no permission) is the drive's failure: `exists()` throws
  and `read()` is a `DriveException` naming it, where `exists()` answered false and `read()` threw
  `FileNotFoundException`. The existence is asked by HeadObject (a new `S3Adapter`, Flysystem's
  AsyncAws adapter but for `fileExists()`): 200 there, 404 not, anything else a failure. A caller
  without `s3:ListBucket` gets 403 for a missing object too: grant it.
