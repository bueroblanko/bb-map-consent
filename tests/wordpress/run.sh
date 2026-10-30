#!/usr/bin/env bash
# Downloads official WordPress/WP-CLI/SQLite integration into a disposable directory.
set -euo pipefail
repo_dir="$(cd "$(dirname "$0")/../.." && pwd)"
work="$(mktemp -d "${TMPDIR:-/tmp}/bb-legal-integration.XXXXXX")"
server_pid=''
cleanup(){ if [ -n "$server_pid" ]; then kill "$server_pid" 2>/dev/null || true; fi; rm -rf "$work"; }
trap cleanup EXIT
curl --fail --silent --show-error --location https://wordpress.org/latest.tar.gz -o "$work/wordpress.tar.gz"
curl --fail --silent --show-error --location https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip -o "$work/sqlite.zip"
curl --fail --silent --show-error --location https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o "$work/wp.phar"
tar -xzf "$work/wordpress.tar.gz" -C "$work"
unzip -q "$work/sqlite.zip" -d "$work"
mkdir -p "$work/wordpress/wp-content/mu-plugins" "$work/wordpress/wp-content/plugins/bb-basic-probe/includes"
cp -R "$work/sqlite-database-integration" "$work/wordpress/wp-content/plugins/"
cp "$work/sqlite-database-integration/db.copy" "$work/wordpress/wp-content/db.php"
ln -s "$repo_dir" "$work/wordpress/wp-content/plugins/bb-consent"
cp -R "$repo_dir/includes/legal" "$work/wordpress/wp-content/plugins/bb-basic-probe/includes/"
cp "$repo_dir/tests/wordpress/basic-probe.php" "$work/wordpress/wp-content/plugins/bb-basic-probe/probe.php"
cp "$repo_dir/tests/wordpress/mock.php" "$work/wordpress/wp-content/mu-plugins/legal-mock.php"
wpcli(){ php -d error_reporting=22527 "$work/wp.phar" --path="$work/wordpress" "$@"; }
run_case(){ BB_LEGAL_SCENARIO="$1" wpcli eval-file "$repo_dir/tests/wordpress/assert.php"; }
wpcli config create --dbname=isolated_legal_test --dbuser=unused --skip-check
wpcli config set BB_LEGAL_TEST_ENV true --raw
port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0"); echo parse_url("tcp://".stream_socket_get_name($s,false),PHP_URL_PORT); fclose($s);')"
wpcli config set WP_HOME "http://127.0.0.1:$port"
wpcli config set WP_SITEURL "http://127.0.0.1:$port"
wpcli core install --url="http://127.0.0.1:$port" --title='Legal Integration Test' --admin_user=legaltest --admin_password="$(php -r 'echo bin2hex(random_bytes(24));')" --admin_email=test@example.test --skip-email
wpcli plugin activate bb-consent
run_case off
run_case prepare
run_case active
run_case editor
cat > "$work/wordpress/legal-probe.php" <<'PHP'
<?php
require __DIR__.'/wp-load.php';
if(!defined('BB_LEGAL_TEST_ENV')||!BB_LEGAL_TEST_ENV)exit;
wp();http_response_code(200);
echo do_shortcode('[erecht24 type="imprint"][erecht24 type="privacy_policy"][erecht24 type="social_media"]');
PHP
php -d error_reporting=22527 -S "127.0.0.1:$port" -t "$work/wordpress" >"$work/server.log" 2>&1 &
server_pid=$!
for attempt in {1..40}; do if curl --silent --fail "http://127.0.0.1:$port/legal-probe.php" >"$work/probe.html"; then break; fi; sleep 0.2; done
grep -q 'Neutraler Testtext imprint' "$work/probe.html"
run_case ack
wpcli option update bb_legal_test_foreign 1
run_case foreign
wpcli option delete bb_legal_test_foreign
wpcli plugin activate bb-basic-probe
run_case fallback
wpcli option update bb_legal_enabled_basic 1
run_case basic
wpcli eval 'update_option("active_plugins",array_reverse(get_option("active_plugins")));'
run_case basic
wpcli option update bb_legal_enabled_basic 0
run_case fallback
wpcli option update bb_legal_schema_version 2
run_case incompatible
wpcli core version
