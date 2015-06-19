#!/bin/bash

# This simple script downloads a set of URLs or html pages
# You need to have curl installed. On Ubuntu do: sudo apt-get install curl; on Mac OSX follow e.g. http://www.vettyofficer.com/2013/06/how-to-install-curl-in-mac-os-x.html
# Modify the variables (list, column, results, ...) belwo
# Run the script as follows: sh download.sh

list="list.csv" # tab separated file
column=1 # the column with the URL to download (first column = 1)
results="results" # where results should be stored
url_in_filename=0 # whether the URL should be reflected in the filename
html_extension=0 # whether .html should be added to the file name
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
    if [ $url_in_filename -eq 1 ]; then
        filename="$(echo $url | cut -d / -f 1- |  tr -C "[:alnum:]" _)"
    else
        filename="$(echo $url | rev | cut -d / -f 1 | rev)"
    fi
    if [ -z "$filename" ]; then
        filename=$i
    fi
    if [ $html_extension -eq 1 ]; then
        filename="$filename.html"
    fi
    curl -s -o "$results/$filename" $url
    if [ $wait_between_downloads -ne "0" ]; then
        sleep $wait_between_downloads
    fi
    ((i++))
done
