#!/usr/bin/env bash
# Maintainer packaging only. End users extract the resulting archive and run start.sh.
set -euo pipefail
php_prefix=$(realpath "${1:?PocketMine PHP prefix required}")
native=$(realpath "${2:?Pinned native build directory required}")
output=$(realpath -m "${3:?Output directory required}")
unset LD_LIBRARY_PATH PHPRC PHP_INI_SCAN_DIR
command -v patchelf >/dev/null
test "$(uname -m)" = x86_64
mkdir -p "$output"
stage=$(mktemp -d "$output/.runtime-XXXXXXXX")
trap 'rm -rf -- "$stage"' EXIT
prefix="$stage/bin/php7"
mkdir -p "$prefix/bin" "$prefix/lib/php/extensions" "$stage/licenses"
cp "$php_prefix/bin/php" "$prefix/bin/php.real"
patchelf --set-rpath '$ORIGIN/../lib' "$prefix/bin/php.real"
cp -a "$php_prefix"/lib/*.so* "$prefix/lib/"
rtc="$native/ext-rtc/.build/rtc"
cp -a "$rtc"/install/lib/libdatachannel.so* "$prefix/lib/"
while IFS= read -r -d '' library; do
    patchelf --set-rpath '$ORIGIN' "$library"
done < <(find "$prefix/lib" -maxdepth 1 -type f -name '*.so*' -print0)
extension_dir=$(PHPRC="$native/php.ini" LD_LIBRARY_PATH="$rtc/install/lib:$php_prefix/lib" "$php_prefix/bin/php" -r 'echo ini_get("extension_dir");')
# every shared extension the ini may load (opcache, quiche, ...), never a leftover ext-webrtc
find "$extension_dir" -maxdepth 1 -name '*.so' ! -name 'webrtc.so' -exec cp {} "$prefix/lib/php/extensions/" \;
cp "$rtc/extension/modules/rtc.so" "$prefix/lib/php/extensions/"
patchelf --set-rpath '$ORIGIN/../..' "$prefix/lib/php/extensions/rtc.so"
sed -E '/^[[:space:]]*extension_dir[[:space:]]*=/d; /^[[:space:]]*extension[[:space:]]*=.*(webrtc|rtc\.so)/d' "$native/php.ini" > "$prefix/bin/php.ini"
printf '\nextension_dir=${AMBER_PHP_EXTENSION_DIR}\nextension=rtc.so\n' >> "$prefix/bin/php.ini"
cat > "$prefix/bin/php" <<'PHP_WRAPPER'
#!/usr/bin/env bash
set -euo pipefail
prefix=$(cd -- "$(dirname -- "$0")/.." && pwd)
export PHPRC="$prefix/bin/php.ini"
export AMBER_PHP_EXTENSION_DIR="$prefix/lib/php/extensions"
export LD_LIBRARY_PATH="$prefix/lib${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
export OPENSSL_CONF=/etc/ssl/openssl.cnf
exec "$prefix/bin/php.real" "$@"
PHP_WRAPPER
chmod 0755 "$prefix/bin/php"
cp "$native/php-src/LICENSE" "$stage/licenses/PHP-LICENSE"
cp "$native/ext-rtc/LICENSE" "$stage/licenses/ext-rtc-LICENSE"
cp "$native/ext-rtc/third-party/NOTICES.md" "$stage/licenses/ext-rtc-NOTICES.md"
cp "$native/ext-rtc/third-party/licenses/"*.txt "$stage/licenses/"
cp "$rtc/libdatachannel/LICENSE" "$stage/licenses/libdatachannel-LICENSE"
# Retain source dependency licenses, including statically linked ICE/SCTP code.
(cd "$rtc/libdatachannel"; find deps -type f \( -iname 'LICENSE*' -o -iname 'COPYING*' \) -exec cp --parents '{}' "$stage/licenses/" \;)
env -u PHPRC -u LD_LIBRARY_PATH -u PHP_INI_SCAN_DIR "$prefix/bin/php" -r '
foreach(["rtc", "openssl", "pmmpthread", "encoding", "crypto", "libdeflate", "morton", "chunkutils2", "leveldb", "gmp", "igbinary", "sockets", "curl", "yaml", "mbstring", "simplexml", "zip"] as $extension){
    if(!extension_loaded($extension)){fwrite(STDERR, "Missing extension: $extension\n"); exit(1);}
}
if(!PHP_ZTS || extension_loaded("webrtc") || \amber\rtc\RtcRuntime::getApiVersion() !== 1){exit(1);}
// libdatachannel resolves the bundled libssl, which must not predate the OpenSSL 3.5 it was built against
if(OPENSSL_VERSION_NUMBER < 0x30500000){fwrite(STDERR, "Bundled OpenSSL " . OPENSSL_VERSION_TEXT . " predates the 3.5 build of libdatachannel\n"); exit(1);}
'
printf 'Staged Linux x86_64 runtime; compatibility floor depends on build host.\nNot a published distribution. Audit all bundled dependency licenses before release.\n' > "$stage/RUNTIME.txt"
tar -czf "$output/amber-php-linux-x86_64-staging.tar.gz" -C "$stage" .
(cd "$output"; sha256sum amber-php-linux-x86_64-staging.tar.gz > amber-php-linux-x86_64-staging.tar.gz.sha256)
echo "$output/amber-php-linux-x86_64-staging.tar.gz"
