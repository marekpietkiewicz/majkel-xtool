#!/bin/sh
# Saves meta.json and the products.json pages of the xTool store in <folder>, the snapshot that
# seed-extra-products.php reads (it must be less than an hour old), and prints its Unix fetch time.
# Usage: sh scripts/fetch-snapshot.sh <folder>
set -eu
dir=${1:?Usage: fetch-snapshot.sh <folder>}
mkdir -p "$dir"
cd "$dir"
fetched=$(date +%s)
curl -fsS --compressed --proto '=https' -o meta.json https://www.xtool.eu/meta.json
page=1
while :; do
	sleep 2
	curl -fsS --compressed --proto '=https' -o "products-$page.json" "https://www.xtool.eu/products.json?limit=250&page=$page"
	# An empty page ({"products":[]}) ends the catalogue.
	if [ "$(wc -c < "products-$page.json")" -lt 100 ]; then
		rm "products-$page.json"
		break
	fi
	page=$((page + 1))
done
echo "$fetched"
