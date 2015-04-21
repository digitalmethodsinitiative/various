<?php

date_default_timezone_set("Europe/Berlin");
error_reporting(E_ALL);
ini_set('memory_limit', '2G');
$dir = 'Tracktree/contextual/';

$toanalyse = "category"; // local, category, or EuropeanUnion (see examples below)

/*
  $datasets = array(
  60 => 'Austria', # has the wrong categories
  61 => 'Ireland', # has only hungarian domains
  62 => 'Latvia',
  63 => 'France',
  64 => 'Hungary',
  65 => 'Romania',
  66 => 'Spain',
  69 => 'Sweden');
 */

$datasets_to_analyse = array(62, 63, 64, 65, 66, 69);
countryOrCategory($datasets_to_analyse, "category");

/*
 * Gets data per country or category
 * 
 * e.g. to sum all data for a country, excluding sites of EU
 * countryOrCategory(array(65), "local");
 * e.g. to group data for a country per category, excluding sites of EU
 * countryOrCategory(array(65), "category");
 * e.g. to sum all data for all countries, excluding sites of EU
 * countryOrCategory($datasets_to_analyse, "category");
 * e.g. to get info for EU originating from a particular country
 * countryOrCategory(array(65), "EuropeanUnion");
 * 
 */

function countryOrCategory($datasets_to_analyse, $toanalyse) {
    global $dir;
    $results = array();
    foreach ($datasets_to_analyse as $d) {

        if (!in_array($d, $datasets_to_analyse))
            continue;

        $json = json_decode(file_get_contents("$dir/$d/ObjectDump.json"));

        foreach ($json->target as $target) {

            # decide what to group by
            if ($toanalyse == "local")
                $groupby = $json->subject->ISO_name;
            elseif ($toanalyse == "category")
                $groupby = $target->infos->category;
            elseif ($toanalyse == "EuropeanUnion")
                $groupby = $json->tester_source_place->ISO_name;

            # skip certain sections in certain analyses
            if ($toanalyse != "EuropeanUnion" && $target->infos->category == "EuropeanUnion")
                continue;
            elseif ($toanalyse == "EuropeanUnion" && $target->infos->category !== "EuropeanUnion")
                continue;

            # collect info for original urls
            $results[$groupby]['original_url']['country_of_host'][] = $target->network_awe->trace_info->target_geoip;
            $results[$groupby]['original_url']['paths'][] = implode(" -> ", $target->network_awe->country_chain);
            $results[$groupby]['original_url']['domain'][] = $target->infos->fqdn;
            $cib = $target->network_awe->country_chain;
            foreach ($cib as $p)
                $results[$groupby]['original_url']['countries_in_between_all_hops'][] = $p;
            $cibu = array_unique($cib);
            foreach ($cibu as $p)
                $results[$groupby]['original_url']['countries_in_between_unique_per_site'][] = $p;

            # collect info for included urls
            foreach ($target->included as $included) {
                # init
                $company = $country = $fqdn = "";
                $path = array();
                if (isset($included->company))
                    $company = $included->company;
                if (isset($included->network_awe->geoip))
                    $country = $included->network_awe->geoip;
                if (isset($included->fqdn))
                    $fqdn = $included->fqdn;
                if (isset($included->network_awe->country_chain))
                    $path = $included->network_awe->country_chain;

                # add company to trackers
                if (!empty($company)) {
                    $type = "included_tracker";
                    $results[$groupby][$type]['company'][] = $company;
                } else
                    $type = "included_other";
                # collect rest
                $results[$groupby][$type]['country_of_host'][] = $country;
                $results[$groupby][$type]['domain'][] = $fqdn;
                $results[$groupby][$type]['paths'][] = implode(" -> ", $path);
                foreach ($path as $p)
                    $results[$groupby][$type]['countries_in_between_all_hops'][] = $p;
                $path = array_unique($path);
                foreach ($path as $p)
                    $results[$groupby][$type]['countries_in_between_unique_per_site'][] = $p;
            }
        }
    }
    # count and display values
    print "<table border='1' cellspacing='1'>";
    print "<tr><th>";
    if ($toanalyse == "local")
        print "country";
    elseif ($toanalyse == "category")
        print "category";
    elseif ($toanalyse == "EuropeanUnion")
        print "Country connected from";
    print "</th><th>type of url</th><th>type of field</th><th>value</th><th>count</th></tr>";
    ksort($results);
    foreach ($results as $groupby => $rows) {
        ksort($rows);
        foreach ($rows as $type => $types) {
            ksort($types);
            foreach ($types as $k => $v) {
                if (is_array($v))
                    $results[$groupby][$type][$k] = array_count_values($v);
                else
                    $results[$groupby][$type][$k] = array();
                ksort($results[$groupby][$type][$k]);
                foreach ($results[$groupby][$type][$k] as $nk => $nv)
                    print "<tr><td>$groupby</td><td>$type</td><td>$k</td><td>$nk</td><td>$nv</td></tr>";
            }
        }
    }
    print "</table>";
}

?>