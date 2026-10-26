#!/usr/bin/env bash
set -euo pipefail
work=$(realpath -m "${1:-.native-webrtc}")
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

mkdir -p "$work/php-src"
curl -fsSL "https://www.php.net/distributions/php-$php_version.tar.xz" | tar -xJ --strip-components=1 -C "$work/php-src"
(
	cd "$work/php-src"
	./configure --enable-zts --disable-all --disable-cgi --without-pear --prefix="$work/php-dev"
)
mkdir -p "$work/php-dev/bin" "$work/php-dev/include" "$work/php-dev/lib/php"
ln -s "$php_bin" "$work/php-dev/bin/php"
ln -s "$work/php-src" "$work/php-dev/include/php"
ln -s "$php_prefix/lib/php/build" "$work/php-dev/lib/php/build"
old_prefix=$("$php_prefix/bin/php-config" --prefix)
sed "s|$old_prefix|$work/php-dev|g" "$php_prefix/bin/php-config" > "$work/php-dev/bin/php-config"
sed "s|$old_prefix|$work/php-dev|g" "$php_prefix/bin/phpize" > "$work/php-dev/bin/phpize"
chmod +x "$work/php-dev/bin/php-config" "$work/php-dev/bin/phpize"

git clone https://github.com/paullouisageneau/libdatachannel.git "$work/libdatachannel"
git -C "$work/libdatachannel" checkout --detach 443f6934d9007eb7076ab7825ba330f355fcbead
git -C "$work/libdatachannel" submodule update --init --recursive
cmake -S "$work/libdatachannel" -B "$work/datachannel-build" \
	-DCMAKE_BUILD_TYPE=Release -DCMAKE_INSTALL_PREFIX="$work/install" \
	-DCMAKE_INSTALL_LIBDIR=lib -DNO_MEDIA=ON -DNO_WEBSOCKET=ON -DNO_EXAMPLES=ON -DNO_TESTS=ON
cmake --build "$work/datachannel-build" --parallel 2
cmake --install "$work/datachannel-build"

git clone https://github.com/axolotl-pm/ext-webrtc.git "$work/ext-webrtc"
git -C "$work/ext-webrtc" checkout --detach 6ef8c2342e2638a05c46fd62e7e1bcefd3ce6ed8
(
	cd "$work/ext-webrtc"
	"$work/php-dev/bin/phpize"
	./configure --enable-webrtc --with-php-config="$work/php-dev/bin/php-config" --with-libdatachannel="$work/install"
	make -j2
)
php_ini=$(php -r 'echo php_ini_loaded_file();')
test -f "$php_ini"
sed '/^[[:space:]]*extension[[:space:]]*=.*webrtc/d' "$php_ini" > "$work/php.ini"
printf '\nextension_dir=%s\nextension=%s\n' "$(php -r 'echo ini_get("extension_dir");')" "$work/ext-webrtc/modules/webrtc.so" >> "$work/php.ini"
export PHPRC="$work/php.ini"
php_library_path="$work/install/lib:$php_library_path"
export LD_LIBRARY_PATH="$php_library_path"
php --ri webrtc
php -r 'if(!PHP_ZTS || phpversion("webrtc") !== "0.3.0" || constant("pmmp\\webrtc\\WEBRTC_VERSION") !== "0.24.5"){exit(1);}'
if [[ -n "${GITHUB_ENV:-}" ]]; then
	printf 'PHPRC=%s\nLD_LIBRARY_PATH=%s\n' "$PHPRC" "$LD_LIBRARY_PATH" >> "$GITHUB_ENV"
fi
echo "Runtime configured: PHPRC=$PHPRC LD_LIBRARY_PATH=$LD_LIBRARY_PATH"
