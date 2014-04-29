#!/usr/bin/php5
<?php

/*
 * Get UvA Mediastudies employee details (please include the full source code of this file in your Wordpress theme functions.php or elsewhere)
 *
 * Call: get_mediastudies_staff()
 *
 * Return object: a nested array with the following structure 
 *
 *    [b.rieder] => Array
 *       (
 *           [gender] => dhr.
 *           [titlesBefore] => dr.
 *           [titlesAfter] => 
 *           [photo] => 
 *           [description] => 
 *           [e-mail] => B.Rieder@uva.nl
 *           [phone] => 0205252980
 *           [fullName] => B. (Bernhard) Rieder
 *           [firstName] => Bernhard
 *           [initials] => B.
 *           [lastName] => Rieder
 *           [searchurl] => http://www.uva.nl/disciplines/mediastudies/medewerkers/medewerkers-mediastudies/medewerkers-mediastudies/folder/r/i/b.rieder/b.rieder.html
 *       )
 *
 *    [b.rieder] is the unique identifier
 *
 * Example: $resultset = get_mediastudies_staff(); 
 *          if ( isset($resultset['a.woudstra']) ) {
 *              echo $resultset['a.woudstra']['fullName'] . "\n";
 *          }
 *
 * NOTE:
 *
 * If the functions fails null will be returned. The database should then _not_ be updated.
 * When this happens a notify e-mail will be sent automatically to the maintainers of the function
 *
 * Please make your system configuration and Wordpress configuration are set up to allow e-mailing (the wp_mail() functions works) 
 *
 */

function get_mediastudies_staff() {

    try {

       // create curl resource
       $ch = curl_init();

       // set url
       curl_setopt($ch, CURLOPT_URL, "http://www.uva.nl/disciplines/mediastudies/medewerkers/medewerkers-mediastudies.html?page=1&pageSize=200");

       // set user agent
       curl_setopt($ch, CURLOPT_USERAGENT, "Mediastudies Wordpress staff synchronizer");

       //return the transfer as a string
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

       // $output contains the output string
       $output = curl_exec($ch);

       // close curl resource to free up system resources
       curl_close($ch);

       // a new dom object
       $dom = new domDocument;

       // supress warnings
       libxml_use_internal_errors(true);

       /*** load the html into the object ***/
       if (@$dom->loadHTML($output) == false) {
           throw new Exception("Cannot decipher html");
       }

       $xpath = new DOMXPath($dom);
       $employees = $xpath->query("//*[contains(@class,'employeeitem')]");

       if ($employees->length == 0) {
           throw new Exception("Cannot iterate employeeitems");
       }

       foreach ($employees as $employee) {
           /*
           $text = $employee->ownerDocument->saveXML($employee);
           $subdom = new domDocument;
           $subdom->loadHTML($text);
            */

           // .//* , $context

           //$xp = new DOMXPath($subdom);

           $gender = DOMNodeListAsString($xpath->query(".//*[contains(@class,'gender')]", $employee));
           $titlesBefore = DOMNodeListAsString($xpath->query(".//*[contains(@class,'titlesBefore')]", $employee));
           $titlesAfter = DOMNodeListAsString($xpath->query(".//*[contains(@class,'titlesAfter')]", $employee));
           $photo = DOMNodeListAsString($xpath->query(".//img/@src", $employee));
           if (strlen($photo)) {
                $photo = 'http://www.uva.nl' . $photo;
            }
           $searchurl = 'http://www.uva.nl' . preg_replace("|\?.*$|", '', DOMNodeListAsString($xpath->query(".//a/@href", $employee)));
           $contactList = $xpath->query(".//*[contains(@class,'contact-info')]", $employee);
           $description = false;
           foreach ($contactList as $contactNode) {
               if ($description === false) {
                   $description = trim($contactNode->nodeValue);
                   continue;
               }
               $contact = trim($contactNode->nodeValue);
               // split e-mail and phone
               $exp = explode(" | ", $contact);
               $email = $exp[0];
               if (isset($exp[1])) {
                   $phone = substr($exp[1], 3);    // cut: "T: "
               } else {
                   $phone = '';
               }
           }
           $names = DOMNodeListAsString($xpath->query(".//h4", $employee));
           $names = str_replace(array($gender, $titlesBefore, $titlesAfter, "\r", "\n", "\t"),
                                array('', '', '', '', '', ''), $names);
           $fullName = trim(preg_replace("/ +/", " ", $names));
           if (preg_match("|\((.*)\)|U", $fullName, $matches)) {
               $firstName = $matches[1];
           } else {
               $firstName = '';
           }
           if (preg_match("|^(.*\.) (.*?)$|", $fullName, $matches)) {
               $initials = $matches[1]; 
               $lastName = str_replace("($firstName) ", '', $matches[2]);
           }

           if (preg_match("/^(.*) (.*?)$/", $lastName, $matches)) {
               $lastNameInserts = $matches[1];
               $lastNameCapitals = $matches[2];
           } else {
               $lastNameInserts = '';
               $lastNameCapitals = $lastName;
            }

           // data sanity check
           
           if (!strlen($searchurl)) {
                throw new Exception("Search URL is missing");
           }

           if (preg_match("|^.*/(.*?)/.*$|", $searchurl, $matches)) {
                $id = $matches[1];
           } else {
                throw new Exception("Employee identifier lookup failed");
           }

           if (!strlen($fullName) || !strlen($email)) {
                throw new Exception("Full name or e-mail address lookup failed for user with identifier $id");
           }

           $object = array(
                       "gender" => $gender,
                       "titlesBefore" => $titlesBefore,
                       "titlesAfter" => $titlesAfter,
                       "photo" => $photo,
                       "description" => $description,
                       "e-mail" => $email,
                       "phone" => $phone,
                       "fullName" => $fullName,
                       "firstName" => $firstName,
                       "initials" => $initials,
                       "lastName" => $lastName,
                       "lastNameInserts" => $lastNameInserts,
                       "lastNameCapitals" => $lastNameCapitals, 
                       "searchurl" => $searchurl,
                    );
           $resultset[$id] = $object;
       }

       libxml_use_internal_errors(false);

       return $resultset;

    } catch (Exception $e) {

       // Something went bad

       if (function_exists('wp_mail')) {

           wp_mail( 'emile@digitalmethods.net', 'Exception occured in Mediastudies UvA employees synchronization', $e, 'Cc: Erik Borra <erik@erikborra.net>' ); 

       } else {

           mail( 'emile@digitalmethods.net', 'Exception occured in Mediastudies UvA employees synchronization', $e, 'Cc: Erik Borra <erik@erikborra.net>' ); 

       }

       return null;

    }

}

// Helper function to iterate over a DOMNodeList and concatenate values as a string (trimming each value)

function DOMNodeListAsString($nodeList, $separator = ' ') {
    $first = true; $str = '';
    foreach ($nodeList as $node) {
        if (!$first) { $str .= $separator; }
        $first = false;
        $str .= trim($node->nodeValue);
    }
    return $str;
}
