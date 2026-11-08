#!/usr/bin/env bash
# Builds the pinned ext-rtc for the PocketMine PHP on PATH (CI and maintainers) and enables it through PHPRC.
# Requires: git cmake ninja make c++ pkg-config readelf curl, plus the PHP build prerequisites for the headers.
set -euo pipefail
work=$(realpath -m "${1:-.native-rtc}")
ext_rtc_repository=https://github.com/Amber-PM/ext-rtc.git
ext_rtc_commit=e57c53411d4145fd8ea1504ccb1e2af239a04c29
mkdir -p "$work"
php_bin=$(command -v php)
php_library_path=${LD_LIBRARY_PATH:-}
unset LD_LIBRARY_PATH
php(){ LD_LIBRARY_PATH="$php_library_path" "$php_bin" "$@"; }
php_version=$(php -r 'echo PHP_VERSION;')
php_prefix=$(dirname "$(dirname "$(readlink -f "$php_bin")")")
test -x "$php_prefix/bin/phpize"
test -x "$php_prefix/bin/php-config"
php -r 'if(!PHP_ZTS){fwrite(STDERR, "Amber requires PHP ZTS\n"); exit(1);}'
php -r 'if(extension_loaded("webrtc")){fwrite(STDERR, "Remove ext-webrtc from this PHP first, it must not be loaded with ext-rtc\n"); exit(1);}'

# the PocketMine binaries ship no headers: configure the same PHP version for them
mkdir -p "$work/php-src"
curl -fsSL "https://www.php.net/distributions/php-$php_version.tar.xz" | tar -xJ --strip-components=1 -C "$work/php-src"
(
	cd "$work/php-src"
	./configure --enable-zts --disable-all --disable-cgi --without-pear --prefix="$work/php-dev"
)
mkdir -p "$work/php-dev/bin" "$work/php-dev/include" "$work/php-dev/lib/php"
# a wrapper rather than a symlink: the PocketMine binary needs its own library path
printf '#!/bin/sh\nLD_LIBRARY_PATH="%s" exec "%s" "$@"\n' "$php_library_path" "$php_bin" > "$work/php-dev/bin/php"
chmod +x "$work/php-dev/bin/php"
ln -sfn "$work/php-src" "$work/php-dev/include/php"
ln -sfn "$php_prefix/lib/php/build" "$work/php-dev/lib/php/build"
old_prefix=$("$php_prefix/bin/php-config" --prefix)
sed "s|$old_prefix|$work/php-dev|g" "$php_prefix/bin/php-config" > "$work/php-dev/bin/php-config"
sed "s|$old_prefix|$work/php-dev|g" "$php_prefix/bin/phpize" > "$work/php-dev/bin/phpize"
chmod +x "$work/php-dev/bin/php-config" "$work/php-dev/bin/phpize"

if [[ ! -d "$work/ext-rtc/.git" ]]; then git clone "$ext_rtc_repository" "$work/ext-rtc"; fi
git -C "$work/ext-rtc" checkout --detach "$ext_rtc_commit"
test "$(git -C "$work/ext-rtc" rev-parse HEAD)" = "$ext_rtc_commit"
# ext-rtc builds its pinned OpenSSL and libdatachannel and verifies the result itself
bash "$work/ext-rtc/scripts/build-openssl.sh"
PKG_CONFIG_PATH="$work/ext-rtc/.build/openssl/lib/pkgconfig" bash "$work/ext-rtc/scripts/build-linux.sh" \
	--php "$work/php-dev/bin/php" --php-config "$work/php-dev/bin/php-config" --phpize "$work/php-dev/bin/phpize" \
	--build-dir "$work/ext-rtc/.build/rtc"
module="$work/ext-rtc/.build/rtc/extension/modules/rtc.so"

php_ini=$(php -r 'echo php_ini_loaded_file();')
test -f "$php_ini"
sed -E '/^[[:space:]]*extension[[:space:]]*=.*(webrtc|rtc\.so)/d' "$php_ini" > "$work/php.ini"
printf '\nextension_dir=%s\nextension=%s\n' "$(php -r 'echo ini_get("extension_dir");')" "$module" >> "$work/php.ini"
export PHPRC="$work/php.ini"
php_library_path="$work/ext-rtc/.build/rtc/install/lib:$php_library_path"
export LD_LIBRARY_PATH="$php_library_path"
php --ri rtc
php -r 'if(!PHP_ZTS || extension_loaded("webrtc") || \amber\rtc\RtcRuntime::getApiVersion() !== 1){exit(1);}'
if [[ -n "${GITHUB_ENV:-}" ]]; then
	printf 'PHPRC=%s\nLD_LIBRARY_PATH=%s\n' "$PHPRC" "$LD_LIBRARY_PATH" >> "$GITHUB_ENV"
fi
echo "Runtime configured: PHPRC=$PHPRC LD_LIBRARY_PATH=$LD_LIBRARY_PATH"
