#!/bin/bash

# This simple script downloads a set of wayback URLs (starting with web.archive.org)
# You need to have wget installed.
# Modify the variables (list, column, ...) below
# Run the script as follows: sh download_ia.sh
# An index.html file will be created with links to the local (saved) html pages

list="list.csv" # tab separated file with wayback URLs
column=1 # the column with the URL to download (first column = 1)
wait_between_downloads=0 # how long to wait between downloads, in seconds

if [ ! -d $results ]; then
    mkdir $results
fi
i=0
cat $list | cut -f $column | while read url; do
    if [[ $url != http* ]]; then
        continue
    fi
    echo "retrieving $url" 
    wget -p -k -e robots=off $url
    if [ $wait_between_downloads -ne "0" ]; then
        sleep $wait_between_downloads
    fi
    ((i++))
done
if [ -d "web.archive.org/web" ]; then
    dirs=$(ls web.archive.org/web/ | grep -v _ | grep [0-9])
    if [ -f "index.html" ]; then
        rm index.html
    fi
    touch "index.html"
    for dir in $dirs; do
        html=$(find web.archive.org/web/$dir/* -type f | grep html | sed 's/^\(.*\)$/<a href="\1">\1<\/a><br>/g')
        echo $html >> index.html
    done
fi
