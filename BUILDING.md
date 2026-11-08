# Building AmberPM

This guide covers **production releases and the stable `main` branch**.

## Recommended: use a release

Building from source is optional. For a production server, download:

1. **AmberPM server:** the `PocketMine-MP.phar` asset from [AmberPM Releases](https://github.com/Amber-PM/Amber/releases/latest).
2. **PHP runtime:** a matching Windows or Linux package from [Amber-PM/PHP-Binaries Releases](https://github.com/Amber-PM/PHP-Binaries/releases). PHP 8.4 is the stable runtime; PHP 8.5 releases are marked pre-release.
3. Extract the PHP runtime into your server directory, preserving its `bin/` structure, and place `PocketMine-MP.phar` in the server directory.

The PHAR does **not** include the PHP interpreter or its extensions. The runtime must be 64-bit, thread-safe (ZTS), and include the PocketMine-MP extensions. Use a validated runtime package for your operating system.

To launch, use the startup scripts from the AmberPM source checkout (`start.cmd` on Windows or `start.sh` on Linux) or configure your server host to execute `PocketMine-MP.phar` with the extracted PHP binary and its `php.ini`.

## Build from stable source

Requirements: Git, Composer, and PHP **8.1 or newer**, 64-bit, with the extensions listed in `composer.json`.

```sh
git clone --branch main https://github.com/Amber-PM/Amber.git
cd Amber
composer run make-server
```

The Composer script installs the locked production dependencies and creates `PocketMine-MP.phar` in the repository root. BedrockProtocol and BedrockData are downloaded through Composer; no sibling clones are required.

On Windows, `compile.bat` is also available. It requires a compatible PHP runtime.

To rebuild after updates to `main`, run:

```sh
git pull --ff-only
composer run make-server
```

The build script runs `composer install` using the locked dependency versions.

## Running and updating

Keep your worlds, configuration files, and plugins backed up before replacing a PHAR or PHP runtime. Shut down the server cleanly before upgrading and confirm plugin compatibility with the selected PHP version.

For a release installation, download the new stable PHAR from [AmberPM Releases](https://github.com/Amber-PM/Amber/releases) and a compatible runtime from [Amber-PM/PHP-Binaries Releases](https://github.com/Amber-PM/PHP-Binaries/releases), as needed. Avoid development builds on production servers.
