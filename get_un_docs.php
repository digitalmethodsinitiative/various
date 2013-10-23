<?php

/*
 * This script will retrieve all documents for an ODS search in un.org
 * e.g. http://search.un.org/search?q=%22global+warming%22&ie=utf8&oe=utf8&output=xml_no_dtd&site=ods_un_org&filter=p&proxystylesheet=UN_ODS_test&client=UN_ODS_test&getfields=DocumentSymbol.Title.Size.PublicationDate&ProxyReload=1&as_q=&as_epq=&as_oq=&as_eq=&lr=&num=10&metaTitle=&ie=utf8&oe=utf8&output=xml_no_dtd&site=ods_un_org&filter=0&q=&proxystylesheet=UN_ODS_test&client=UN_ODS_test&getfields=DocumentSymbol.Title.Size.PublicationDate&ProxyReload=1
 * just specify the search and the directory where to store the pdfs and run the script from the command line: php get_un_docs.php
 *
 * @author: Erik Borra <erik@digitalmethods.net>
 */

ERROR_REPORTING(E_ALL);

$search = urlencode('"climate change"');
$dir = "unpdfs"; // directory where to store pdfs

$start = 0; // where to start in rss
$rss_url = "http://search.un.org/search?proxystylesheet=UN_ODS_test2&sort=date:D:S:d1&num=10&q=$search&btnG=Search+the+ODS&ie=utf8&oe=UTF-8&ie=UTF-8&ie=UTF-8&ie=UTF-8&ie=UTF-8&ie=UTF-8&output=xml_no_dtd&client=UN_ODS_test&getfields=DocumentSymbol.Title.Size.PublicationDate&ProxyReload=1&ulang=en&entqr=3&entqrm=0&entsp=a&ud=1&filter=0&site=ods_un_org&ip=157.150.185.24&access=p&start=$start";

$cookiejar = "cookies.txt";

print "Getting rss url\n$rss_url\n";
$content = fetch_through_curl($rss_url);
$data = simplexml_load_string($content);
while(count($data->channel->item)!=0) {
	foreach($data->channel->item as $item) {
		print "\nDoing item $i\n";
	
		print "Getting frame source\n";
		print $item->link."\n";
		$content = fetch_through_curl($item->link,"",$cookiejar);
			
		// clean content
		$content = preg_replace("/[\n\r]/", " ", $content);
		$content = preg_replace("/\s+/", " ", $content);
	
		// extract main frame source
		if(!preg_match_all('/title="Language versions"> <frame src="(.*?)" name="mainFrame"/',$content,$out)) {
			print "ERROR: no main frame source found for ".$item->link."\n";
			continue;
		}
		$url = $out[1][0];
		
		// clean url
		$url = str_replace("&amp;","&",$url);
		
		// look for tmp file
		print "Looking for tmp file\n$url\n";
		$content = fetch_through_curl($url,$item->link,$cookiejar);
	
		if(!preg_match("/URL=(.+\.html)/",$content,$match)) {
			 print "ERROR: no tmp file found for ".$item->link."\n";
			 continue;
		}
		$tmp_url = $match[1];
		$tmp_url = "http://daccess-ods.un.org".$tmp_url;
		
		print "Looking for pdf\n$tmp_url\n";
		$content = fetch_through_curl($tmp_url,$url,$cookiejar);
	
		if(!preg_match("/URL=(.+)\"/",$content,$match)) {
			print "ERROR: no pdf found for ".$item->link."\n";
			continue;
		}
		$pdf_url = $match[1];
	
		
		// log in
		// $login_url = "http://daccess-dds-ny.un.org/prod/ods_mother.nsf?Login&Username=freeods2&Password=1234";
		// print "Logging in\n$login_url\n";
		// fetch_through_curl($login_url,$tmp_url,$cookiejar);
		
		// save pdf	
		print "Retrieving pdf\n$pdf_url\n";
		$content = fetch_through_curl($pdf_url,$tmp_url,$cookiejar);
		file_put_contents($dir."/".get_filename($item,$pdf_url), $content);
		
		print "Sleeping\n";
		sleep(rand(1,3));
	}
	$start += 10;
	$rss_url = "http://search.un.org/search?proxystylesheet=UN_ODS_test2&sort=date:D:S:d1&num=10&q=$search&btnG=Search+the+ODS&ie=utf8&oe=UTF-8&ie=UTF-8&ie=UTF-8&ie=UTF-8&ie=UTF-8&ie=UTF-8&output=xml_no_dtd&client=UN_ODS_test&getfields=DocumentSymbol.Title.Size.PublicationDate&ProxyReload=1&ulang=en&entqr=3&entqrm=0&entsp=a&ud=1&filter=0&site=ods_un_org&ip=157.150.185.24&access=p&start=$start";
	print "\n\nGetting rss url\n$rss_url\n";

	$content = fetch_through_curl($rss_url);
	$data = simplexml_load_string($content);
}
die("No more rss items found for start = $start\n");

function get_filename($item,$pdfurl) {
	preg_match("/([^\/]+?\.pdf)/",$pdfurl,$match);
	$pdfname = $match[1];
	return $pdfname;
	
	$date = date( 'YmdHMS',strtotime($item->pubDate));
	$filename = $pdfname." - ".$date." - ".$item->title.".pdf";
	$filename = preg_replace("/\//", " ", $filename);
	
	return $filename;
}

function fetch_through_curl($url,$referer="",$cookiejar="") {
	$sh = curl_init($url);
	curl_setopt($sh,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9) Gecko/2008052906 Firefox/3.0"); // mask as firefox 3
	curl_setopt($sh,CURLOPT_SSL_VERIFYPEER,false);  //disable ssl certificate validation
	curl_setopt($sh,CURLOPT_SSL_VERIFYHOST,false);
	curl_setopt($sh,CURLOPT_FAILONERROR,1);		
	curl_setopt($sh,CURLOPT_FOLLOWLOCATION,1);	// allow redirects	
	curl_setopt($sh,CURLOPT_RETURNTRANSFER,1);	// return into a variable
	if(!empty($cookiejar)) {
		curl_setopt($sh, CURLOPT_COOKIEJAR, $cookiejar);
		curl_setopt($sh, CURLOPT_COOKIEFILE, $cookiejar);	
	}
	if(!empty($referer))
		curl_setopt($sh,CURLOPT_REFERER, $referer);
	$file = curl_exec($sh);
	curl_close($sh);
	return $file;
}

?>