#!/usr/bin/env bash
# Baseline harness for PRC Schema SEO cache performance (PRC-575).
#
# Measures CLI cold/warm schema+meta timings and full-page HTTP TTFB for
# singular, term, home/publications, and one post-type archive.
#
# Usage:
#   bash plugins/prc-schema-seo/bin/baseline-seo-cache.sh --label=pre --iterations=5
#   bash plugins/prc-schema-seo/bin/baseline-seo-cache.sh --label=post --iterations=5
#
# Requires: VIP CLI, curl, jq (optional), running prc-platform dev-env.

set -euo pipefail

LABEL="run"
ITERATIONS=5
SLUG="${VIP_DEV_ENV_SLUG:-prc-platform}"
SITE_PATH="${PRC_SCHEMA_SEO_BASELINE_SITE_PATH:-/pewresearch-org}"
BASE_URL="${PRC_SCHEMA_SEO_BASELINE_BASE_URL:-https://prc-platform.vipdev.lndo.site${SITE_PATH}}"
OUT_ROOT="${PRC_SCHEMA_SEO_BASELINE_OUT:-plugins/prc-schema-seo/artifacts/baselines}"

POST_ID="${PRC_SCHEMA_SEO_BASELINE_POST_ID:-}"
TERM_ID="${PRC_SCHEMA_SEO_BASELINE_TERM_ID:-}"
TAXONOMY="${PRC_SCHEMA_SEO_BASELINE_TAXONOMY:-category}"
POST_TYPE="${PRC_SCHEMA_SEO_BASELINE_POST_TYPE:-}"

while [[ $# -gt 0 ]]; do
	case "$1" in
		--label=*) LABEL="${1#*=}"; shift ;;
		--iterations=*) ITERATIONS="${1#*=}"; shift ;;
		--post=*) POST_ID="${1#*=}"; shift ;;
		--term=*) TERM_ID="${1#*=}"; shift ;;
		--taxonomy=*) TAXONOMY="${1#*=}"; shift ;;
		--post-type=*) POST_TYPE="${1#*=}"; shift ;;
		--base-url=*) BASE_URL="${1#*=}"; shift ;;
		--out=*) OUT_ROOT="${1#*=}"; shift ;;
		*) echo "Unknown arg: $1" >&2; exit 1 ;;
	esac
done

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_DIR="${OUT_ROOT}/${LABEL}-${TIMESTAMP}"
mkdir -p "${OUT_DIR}"

vip_wp() {
	vip dev-env exec --slug="${SLUG}" --quiet -- wp "$@"
}

echo "==> Resolving fixtures via VIP CLI (slug=${SLUG})"

if [[ -z "${POST_ID}" ]]; then
	POST_ID="$(vip_wp post list --post_type=post --post_status=publish --posts_per_page=1 --field=ID 2>/dev/null | tr -d '[:space:]' || true)"
fi
if [[ -z "${TERM_ID}" ]]; then
	TERM_ID="$(vip_wp term list "${TAXONOMY}" --hide_empty=1 --number=1 --field=term_id 2>/dev/null | tr -d '[:space:]' || true)"
fi
if [[ -z "${POST_TYPE}" ]]; then
	# Prefer a non-post archive if present; fall back to post.
	POST_TYPE="fact-sheet"
	if ! vip_wp post-type get "${POST_TYPE}" --field=name >/dev/null 2>&1; then
		POST_TYPE="post"
	fi
fi

POST_URL=""
TERM_URL=""
HOME_URL=""
PTA_URL=""

if [[ -n "${POST_ID}" ]]; then
	POST_URL="$(vip_wp post url "${POST_ID}" 2>/dev/null | tr -d '[:space:]' || true)"
fi
if [[ -n "${TERM_ID}" ]]; then
	TERM_URL="$(vip_wp term url "${TAXONOMY}" "${TERM_ID}" 2>/dev/null | tr -d '[:space:]' || true)"
fi
HOME_URL="$(vip_wp eval 'echo home_url("/publications");' 2>/dev/null | tr -d '[:space:]' || true)"
if [[ -z "${HOME_URL}" ]]; then
	HOME_URL="${BASE_URL}/publications"
fi
PTA_URL="$(vip_wp eval "echo get_post_type_archive_link('${POST_TYPE}');" 2>/dev/null | tr -d '[:space:]' || true)"

cat > "${OUT_DIR}/fixtures.json" <<EOF
{
  "label": "${LABEL}",
  "timestamp": "${TIMESTAMP}",
  "base_url": "${BASE_URL}",
  "post_id": "${POST_ID}",
  "post_url": "${POST_URL}",
  "term_id": "${TERM_ID}",
  "taxonomy": "${TAXONOMY}",
  "term_url": "${TERM_URL}",
  "home_url": "${HOME_URL}",
  "post_type": "${POST_TYPE}",
  "pta_url": "${PTA_URL}",
  "iterations": ${ITERATIONS}
}
EOF

echo "==> Fixtures written to ${OUT_DIR}/fixtures.json"

if [[ -n "${POST_ID}" ]]; then
	echo "==> CLI benchmark post=${POST_ID}"
	vip_wp prc-seo benchmark --post="${POST_ID}" --iterations="${ITERATIONS}" > "${OUT_DIR}/cli-post.json" || true
fi
if [[ -n "${TERM_ID}" ]]; then
	echo "==> CLI benchmark term=${TERM_ID} taxonomy=${TAXONOMY}"
	vip_wp prc-seo benchmark --term="${TERM_ID}" --taxonomy="${TAXONOMY}" --iterations="${ITERATIONS}" > "${OUT_DIR}/cli-term.json" || true
fi

http_timing() {
	local name="$1"
	local url="$2"
	local outfile="${OUT_DIR}/http-${name}.csv"
	if [[ -z "${url}" || "${url}" == "false" ]]; then
		echo "skip,${name},empty-url" > "${outfile}"
		return
	fi
	echo "pass,ttfb_s,total_s,http_code,size_bytes" > "${outfile}"
	# Cold: single request (best-effort; object cache may still be warm).
	local cold
	cold="$(curl -k -o /dev/null -s -w '%{time_starttransfer},%{time_total},%{http_code},%{size_download}' "${url}" || echo '0,0,000,0')"
	echo "cold,${cold}" >> "${outfile}"
	local i
	for (( i=1; i<=ITERATIONS; i++ )); do
		local warm
		warm="$(curl -k -o /dev/null -s -w '%{time_starttransfer},%{time_total},%{http_code},%{size_download}' "${url}" || echo '0,0,000,0')"
		echo "warm${i},${warm}" >> "${outfile}"
	done

	# Fingerprint SEO payloads (JSON-LD + a few meta tags).
	local body="${OUT_DIR}/body-${name}.html"
	curl -k -sL "${url}" -o "${body}" || true
	if [[ -f "${body}" ]]; then
		{
			echo "json_ld_bytes=$(grep -o 'application/ld+json' "${body}" 2>/dev/null | wc -l | tr -d ' ')"
			echo "og_title=$(grep -o 'property=\"og:title\" content=\"[^\"]*\"' "${body}" 2>/dev/null | head -1)"
			echo "canonical=$(grep -o 'rel=\"canonical\" href=\"[^\"]*\"' "${body}" 2>/dev/null | head -1)"
			echo "sha256=$(sha256sum "${body}" | awk '{print $1}')"
		} > "${OUT_DIR}/fingerprint-${name}.txt"
	fi
}

echo "==> HTTP timings"
http_timing "singular" "${POST_URL}"
http_timing "term" "${TERM_URL}"
http_timing "home" "${HOME_URL}"
http_timing "pta" "${PTA_URL}"

SUMMARY="${OUT_DIR}/SUMMARY.md"
{
	echo "# Schema SEO baseline (${LABEL})"
	echo
	echo "- Timestamp: \`${TIMESTAMP}\`"
	echo "- Post: \`${POST_ID}\` ${POST_URL}"
	echo "- Term: \`${TAXONOMY}:${TERM_ID}\` ${TERM_URL}"
	echo "- Home: ${HOME_URL}"
	echo "- PTA (\`${POST_TYPE}\`): ${PTA_URL}"
	echo
	echo "## QM checklist (manual)"
	echo
	echo "For each fixture URL in the browser with Query Monitor enabled, record:"
	echo "1. Object cache gets/sets/hits for groups \`prc_schema_seo_*\`"
	echo "2. Database query count"
	echo "3. Page generation time"
	echo
	echo "Attach numbers to Linear PRC-575 alongside this artifact directory."
	echo
	echo "## Artifacts"
	echo
	echo "See \`${OUT_DIR}\` for CLI JSON, HTTP CSV, and fingerprints."
} > "${SUMMARY}"

echo "==> Done. Summary: ${SUMMARY}"
cat "${SUMMARY}"
