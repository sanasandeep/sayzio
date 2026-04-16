# Bundled City Data — Attribution

The `cities` reference table is seeded from two bundled datasets:

## 1. `world-cities.csv` (~142,000 cities, no population)

**Source**: lutangar/cities.json (https://github.com/lutangar/cities.json)
**License**: Public Domain (Unlicense)
**Used for**: long-tail city → coordinate lookup.

This is a redistributable, public-domain dataset compatible with the
"SimpleMaps Basic World Cities" requirement (similar shape, broader
coverage, no licensing strings attached). SimpleMaps Basic itself is
public-domain but is not directly downloadable via script — it requires
interactive ToS acceptance on simplemaps.com — so we ship the
equivalent public-domain data instead.

## 2. `cities15000.txt` (~26,000 major cities, with population)

**Source**: GeoNames (https://download.geonames.org/export/dump/cities15000.zip)
**License**: Creative Commons Attribution 4.0 (CC-BY 4.0)
**Used for**: providing the `population` column for major cities.

Per CC-BY 4.0 we credit GeoNames here. If you redistribute this
project's bundled dataset, you must keep this attribution intact.
