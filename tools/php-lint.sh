#!/usr/bin/env bash
# PHP syntax check across the application.
set -euo pipefail
fail=0
while IFS= read -r file; do
  if ! out=$(php -l "$file" 2>&1); then
    echo "SYNTAX ERROR: $file"
    echo "$out"
    fail=1
  fi
done < <(find src public templates tests -name '*.php' -type f)
if [ "$fail" -eq 0 ]; then
  echo "PHP syntax OK"
fi
exit "$fail"
