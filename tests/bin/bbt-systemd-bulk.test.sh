#!/bin/bash
#
# Test the Active-line extraction used by bbt-systemd-bulk.sh check_units().
#
# Regression coverage for services whose `systemctl status` output contains a
# Drop-In: section, which previously pushed the Active: line below line 3 and
# broke the old `sed -n '3 p'` extraction.

set -u

failures=0

extract_active_line() {
  grep -m1 '^[[:space:]]*Active:'
}

assert_contains() {
  local label="$1"
  local needle="$2"
  local haystack="$3"

  if [[ "$haystack" == *"$needle"* ]]; then
    echo "  PASS: $label"
  else
    echo "  FAIL: $label"
    echo "    expected to contain: $needle"
    echo "    actual:              $haystack"
    failures=$((failures + 1))
  fi
}

# ---------------------------------------------------------------------------
# Case 1: timer unit, no Drop-In section — Active: is on line 3
# ---------------------------------------------------------------------------
no_dropin_output=$(cat <<'EOF'
● DownloadEthocaAlerts.timer - DownloadEthocaAlerts
     Loaded: loaded (/usr/lib/systemd/user/DownloadEthocaAlerts.timer; static)
     Active: active (waiting) since Fri 2025-09-26 14:50:22 EDT; 7 months 20 days ago
   Triggers: ● DownloadEthocaAlerts.service
EOF
)

result=$(echo "$no_dropin_output" | extract_active_line)
assert_contains "no Drop-In: Active line extracted" \
  "Active: active (waiting) since Fri 2025-09-26 14:50:22 EDT" \
  "$result"

# ---------------------------------------------------------------------------
# Case 2: service unit WITH Drop-In section — Active: is below line 3
# (this is the bug being fixed: line 3 is the Drop-In path, not Active)
# ---------------------------------------------------------------------------
with_dropin_output=$(cat <<'EOF'
● DisputeJobWorker.service - DisputeJobWorker
     Loaded: loaded (/usr/lib/systemd/user/DisputeJobWorker.service; static)
     Drop-In: /usr/lib/systemd/user/service.d
              └─50-environment.conf
     Active: active (running) since Mon 2026-04-06 05:14:36 EDT; 1 month 11 days ago
   Main PID: 12345 (php)
EOF
)

result=$(echo "$with_dropin_output" | extract_active_line)
assert_contains "with Drop-In: Active line extracted (not Drop-In)" \
  "Active: active (running) since Mon 2026-04-06 05:14:36 EDT" \
  "$result"
assert_contains "with Drop-In: result must not be the Drop-In line" \
  "Active:" \
  "$result"
if [[ "$result" == *"Drop-In:"* ]]; then
  echo "  FAIL: with Drop-In: result wrongly contains the Drop-In line"
  echo "    actual: $result"
  failures=$((failures + 1))
fi

# ---------------------------------------------------------------------------
# Case 3: failed unit — Active line should still be extracted so the caller
# can colorize it with the existing 'failed\|dead' grep
# ---------------------------------------------------------------------------
failed_output=$(cat <<'EOF'
× SomeBroken.service - SomeBroken
     Loaded: loaded (/usr/lib/systemd/user/SomeBroken.service; static)
     Active: failed (Result: exit-code) since Sun 2026-05-17 10:00:00 EDT; 1 day ago
EOF
)

result=$(echo "$failed_output" | extract_active_line)
assert_contains "failed unit: Active failed line extracted" \
  "Active: failed" \
  "$result"

# ---------------------------------------------------------------------------
# Case 4: empty input (unit not found / not running) — result is empty so the
# caller's existing fallback ("SERVICE NOT RUNNING") still triggers
# ---------------------------------------------------------------------------
result=$(echo "" | extract_active_line)
if [[ -z "$result" ]]; then
  echo "  PASS: empty input produces empty result (preserves fallback path)"
else
  echo "  FAIL: empty input should produce empty result, got: $result"
  failures=$((failures + 1))
fi

# ---------------------------------------------------------------------------
echo ""
if [[ "$failures" -eq 0 ]]; then
  echo "All tests passed."
  exit 0
else
  echo "$failures test(s) failed."
  exit 1
fi