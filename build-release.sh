#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
PLUGIN_PARENT=$(dirname -- "$PLUGIN_DIR")
PLUGIN_NAME=wevoi-redesign
ARCHIVE="$PLUGIN_PARENT/wevoi-redesign-0.1.24.zip"

FILES=(
  "wevoi-redesign.php"
  "readme.txt"
  "assets/css/wevoi.css"
  "assets/css/wevoi-register.css"
  "assets/css/wevoi-map.css"
  "assets/img/global-community-world-map.png"
  "assets/img/hero-collage-v13.png"
  "assets/img/voicehub-preview.png"
  "assets/js/wevoi.js"
  "templates/pages/about.html"
  "templates/pages/ambassadors-partners.html"
  "templates/pages/blog.html"
  "templates/pages/evaluation-criteria.html"
  "templates/pages/forum.html"
  "templates/pages/home.html"
  "templates/pages/how-it-works.html"
  "templates/pages/how-to-prepare.html"
  "templates/pages/juniorvoi.html"
  "templates/pages/login.html"
  "templates/pages/partner-with-wevoi.html"
  "templates/pages/results.html"
  "templates/pages/rounds.html"
  "templates/pages/signup.html"
  "templates/pages/spark-poster.html"
  "templates/pages/speech-guidelines.html"
  "templates/pages/startervoi.html"
  "templates/pages/submissions.html"
  "templates/pages/student-dashboard.html"
  "templates/pages/supervisor-dashboard.html"
  "templates/pages/parent-dashboard.html"
  "templates/pages/bulk-registration.html"
  "templates/pages/student-ambassadors.html"
  "templates/pages/teenvoi.html"
  "templates/pages/video-recording-guidelines.html"
  "templates/pages/voicehub.html"
  "templates/pages/wevoi-reload.html"
  "templates/pages/wevoi-spark.html"
  "templates/pages/wevoi-summit.html"
  "templates/pages/workshops.html"
  "templates/pages/youngstervoi.html"
  "templates/partials/footer.html"
  "templates/partials/header.html"
)

if [[ $# -gt 1 || ( $# -eq 1 && "$1" != "--check" ) ]]; then
  printf 'Usage: %s [--check]\n' "$0" >&2
  exit 2
fi

for file in "${FILES[@]}"; do
  case "$file" in
    /*|../*|*/../*|wevoi-signup/*|wevoi-register/*|wevoi-summit-videos-top-three/*|wevoi-supervisor-dashboard/*|wevoi-student-dashboard/*|wpie-supervisor-profile/*|wpie-parent-profile/*|*.DS_Store|*/.DS_Store)
      printf 'Rejected non-production path in allowlist: %s\n' "$file" >&2
      exit 1
      ;;
  esac

  if [[ ! -f "$PLUGIN_DIR/$file" ]]; then
    printf 'Allowlisted file is missing: %s\n' "$file" >&2
    exit 1
  fi
done

if [[ $# -eq 1 ]]; then
  printf 'Production file allowlist (%s files):\n' "${#FILES[@]}"
  for file in "${FILES[@]}"; do
    printf '  %s/%s\n' "$PLUGIN_NAME" "$file"
  done
  printf 'Allowlist check passed; no archive was created.\n'
  exit 0
fi

if [[ -e "$ARCHIVE" ]]; then
  printf 'Refusing to overwrite existing archive: %s\n' "$ARCHIVE" >&2
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  printf 'The zip command is required to build the release archive.\n' >&2
  exit 1
fi

ZIP_FILES=()
for file in "${FILES[@]}"; do
  ZIP_FILES+=( "$PLUGIN_NAME/$file" )
done

( cd -- "$PLUGIN_PARENT" && zip -q "$ARCHIVE" "${ZIP_FILES[@]}" )
printf 'Created %s\n' "$ARCHIVE"
