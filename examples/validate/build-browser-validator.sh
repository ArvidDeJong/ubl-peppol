#!/bin/bash
# Compile the official validation rules for the browser validator on the docs site
# (docs/validator.md). Run it again for every new OpenPEPPOL release.
#
# usage: build-browser-validator.sh <rules-dir> <saxonjs-dir> <release>
#
#   <rules-dir>    holds CEN-EN16931-UBL.xslt and PEPPOL-EN16931-UBL.xslt of the release,
#                  for example from https://github.com/phax/phive-rules
#   <saxonjs-dir>  the unpacked browser release of SaxonJS 2, holding SaxonJS2.rt.js and LICENSE.txt:
#                  https://downloads.saxonica.com/SaxonJS/2/SaxonJS-2.7.zip
#   <release>      the name of the release, shown on the page, for example 2026.5
#
# Needs Node; the compiler is fetched with npx.
set -euo pipefail

if [ $# -ne 3 ]; then
    sed -n '4,13p' "$0"
    exit 1
fi

rules="$1"
saxon="$2"
release="$3"
out="$(cd "$(dirname "$0")/../.." && pwd)/docs/assets/validator"

mkdir -p "$out"

for ruleset in CEN-EN16931-UBL PEPPOL-EN16931-UBL; do
    echo "Compiling $ruleset"
    npx --yes xslt3 -xsl:"$rules/$ruleset.xslt" -export:"$out/$ruleset.sef.json" -nogo -relocate:on
done

cp "$saxon/SaxonJS2.rt.js" "$out/SaxonJS2.rt.js"
cp "$saxon/LICENSE.txt" "$out/SaxonJS-LICENSE.txt"
printf '{"release": "%s"}\n' "$release" > "$out/release.json"

echo "Written to $out"
