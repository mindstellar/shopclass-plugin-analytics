#!/usr/bin/env bash
# Unit tests: no core, no database, no network.
set -u
cd "$(dirname "$0")/.."
fail=0
for t in tests/*.php; do
    echo "── $t"
    php "$t" | tail -3
    [ "${PIPESTATUS[0]}" -eq 0 ] || fail=1
done
echo "── public/analytics.js"
node --check public/analytics.js || fail=1
if command -v node >/dev/null 2>&1; then
    echo "── tests/analytics.test.js"
    node tests/analytics.test.js | tail -20
    [ "${PIPESTATUS[0]}" -eq 0 ] || fail=1
fi
for f in index.php settings.php src/*.php; do
    php -l "$f" >/dev/null || fail=1
done
exit "$fail"
