<?php
/**
 * Huey - An API for Louisiana Statutory Laws
 *
 * This is the scraper which retrieves the laws
 * from the Legislature's database.
 * 
 * Uses PHP Simple HTML DOM Parser:
 * http://simplehtmldom.sourceforge.net/
 *
 * @author Judson Mitchell <judsonmitchell@gmail.com>
 * @copyright 2012 Judson Mitchell, Three Pipe Problem, LLC
 * @url https://github.com/judsonmitchell/huey
 * @license MIT
 */

//require_once('../db.php');
require_once('simple_html_dom.php');

$time_start = microtime(true);

//Function to deal with anomalies in sortcode; remove any data
//which is unncessary or which destroys the sort
function clean_sortcodes($val)
{
    switch ($val) {
        case  substr_count($val,'RS') > 1: //revised statutes; duplicate rs line
            $sortcode = substr($val,10);    
            return $sortcode;
            break;
        case  substr_count($val,'CE') > 1: //code of evidence; duplicate "CE"
            $sortcode = substr($val,3);    
            return $sortcode;
            break;
        case  strstr($val,'CC  000200'): //civil code; mysterious "000200" 
            $sortcode = str_replace('CC  000200','CC',$val);    
            return $sortcode;
            break;
        case  substr_count($val,'CHC') > 1: //children's code; duplicate "CHC"
            $sortcode = substr($val,4);    
            return $sortcode;
            break;
        case  substr_count($val,'CCP') > 1: //code of civil proc.; duplicate "CCP"
            $sortcode = substr($val,4);    
            return $sortcode;
            break;
        case  substr_count($val,'CCRP') > 1: //code of criminal proc; duplicate "CCRP"
            $sortcode = substr($val,5);    
            return $sortcode;
            break;
        case  substr_count($val,'CONST') > 1: //constitution; duplicate "CONST"
            $sortcode = substr($val,13);    
            return $sortcode;
            break;
        case  substr_count($val,'LAC') > 1: //admin code;duplicate "LAC"
            $sortcode = substr($val,11);    
            return $sortcode;
            break;
        case  substr_count($val,'CA') > 1: //constit. amends; duplicate "CA" 
            $sortcode = substr($val,3);    
            return $sortcode;
            break;
        case  substr_count($val,'ERC') > 1: //ERC?; duplicate "ERC" 
            $sortcode = substr($val,4);    
            return $sortcode;
            break;
        case  substr_count($val,'CJP') > 1: //duplicate "CJP" 
            $sortcode = substr($val,4);    
            return $sortcode;
            break;
        default:
            return $val;
            break;
    }
}

// Abbreviations for the components of the laws.
$acronyms = array(
	'rs' => 'Revised Statutes',
	'lc' => 'Louisiana Constitution',
	'ca' => 'Constitution Ancillaries',
	'chc' => 'Children\'s Code',
	'cc' => 'Civil Code',
	'ccp' => 'Code of Civil Procedure',
	'ccrp' => 'Code of Criminal Procedure',
	'ce' => 'Code of Evidence',
	'hrule' => 'House Rules',
	'srule' => 'Senate Rules',
	'jrule' => 'Joint Rules'
);
$titles = array(
	'rs' => array(
		1=>'General Provisions',
		2=>'Aeronautics',
		3=>'Agriculture and Forestry',
		4=>'Amusements and Sports',
		6=>'Banks and Banking',
		8=>'Cemeteries',
		9=>'Civil Code-Ancillaries',
		10=>'Commercial Laws',
		11=>'Consolidated Public Retirement',
		12=>'Corporations and Associations',
		13=>'Courts and Judicial Procedure',
		14=>'Criminal Law',
		15=>'Criminal Procedure',
		16=>'District Attorneys',
		17=>'Education',
		18=>'Louisiana Election Code',
		19=>'Expropriation',
		20=>'Homesteads and Exemptions',
		21=>'Hotels and Lodging Houses',
		22=>'Insurance',
		23=>'Labor and Worker\'s Compensation',
		24=>'Legislature and Laws',
		25=>'Libraries, Museums, and Other Scientific',
		26=>'Liquors-Alcoholic Beverages',
		27=>'Louisiana Gaming Control',
		28=>'Mental Health',
		29=>'Military, Naval, and Veteran\'s Affairs',
		30=>'Minerals, Oil, and Gas and Environmental Quality',
		31=>'Mineral Code',
		32=>'Motor Vehicles and Traffic Regulation',
		33=>'Municipalities and Parishes',
		34=>'Navigation and Shipping',
		35=>'Notaries Public and Commissioners',
		36=>'Organization of the Exective Branch',
		37=>'Professions and Occupations',
		38=>'Public Contracts, Works and Improvements',
		39=>'Public Finance',
		40=>'Public Health and Safety',
		41=>'Public Lands',
		42=>'Public Officers and Employees',
		43=>'Public Printing and Advertisements',
		44=>'Public Records and Recorders',
		45=>'Public Utilities and Carriers',
		46=>'Public Welfare and Assistance',
		47=>'Revenue and Taxation',
		48=>'Roads, Bridges and Ferries',
		49=>'State Administration',
		50=>'Surveys and Surveyors',
		51=>'Trade and Commerce',
		52=>'United States',
		53=>'War Emergency',
		54=>'Warehouses',
		55=>'Weights and Measures',
		56=>'Wildlife and Fisheries'
	//Constitution
	'const' => array (
		1=>'Declaration of Rights',
		2=>'Distribution of Powers',
		3=>'Legislative Branch',
		4=>'Executive Branch',
		5=>'Judicial Branch',
		6=>'Local Government',
		7=>'Revenue & Finance',
		8=>'Education',
		9=>'Natural Resources',
		10=>'Public Officials & Employees',
		11=>'Elections',
		12=>'General Provisions',
		13=>'Constitution Revision',
		14=>'Transitional Provisions'
	)
);

echo "Scraping...this could take a while...\r";
$counter = 0; //number of laws successfully scraped
$errors = 0; //db errors
$docs = 0; //number of urls touched

//Define the ranges of document ids we are requesting; State does not
//appear to have any logic to assigning these ids, but as far as I can
//tell the lowest id is around 67940 and the highest around 750000 
$min = 67940;
$max = 750000;

for ($min; $min <= $max; $min++) {

    $law = file_get_html('http://legis.la.gov/lss/newWin.asp?doc=' . $min);
echo 'http://legis.la.gov/lss/newWin.asp?doc=' . $min."\n";
    if (is_object($law)) {

        $docs++; //url has been hit

        if (!$law->find('html',0)) //Server returns 'file not found'
        {
            $law->clear(); 
            unset($law);
        }
        else
        {
        	
        	//Create a new object to store XML content.
        	$xml = new stdClass();
        	
            //Parse meta tags
            $meta = array();
            foreach($law->find('meta') as $item) {
                $meta[$item->name] = $item->content; 
            }

            //In the revised statutes, the meta tags contain the law title; in 
            //the others, there is no such tag.  So parse the <title> tag
            $title = array();
            foreach ($law->find('title') as $item) {
                $xml->catch_line = $item->innertext;
            }

            //Get the entire body of the law; will use later when applying diff
            //to see if there has been a change
            foreach ($law->find('body') as $b) {
                $body = $b->innertext;
            }
			
			//Get the text of the law.
			$xml->text = '';
			foreach ($law->find('p.00003') as $paragraph)
			{
				$xml->text .= $paragraph->plaintext."\r";
##Set aside the last section, assuming it has the right keywords, as $xml->history.
			}
			
            //generate an alternative description if meta does not have it
            //Having to find the align attribute is a special bit of fun; 99%
            //of the time, the first paragraph has the description; but sometimes,
            //if it's the start of the chapter, you get chapter name instead; these
            //are, however, aligned center, so just find the first paragraph that is
            //aligned justify
            $first_para = $law->find('p[align="justify"]',0);
            if ($first_para)
            {
                $alt_description = explode('&nbsp;',$first_para->innertext);
            }
            
            //Save the section identifier.
            $xml->section_number = $law->find('title',0)->plaintext;
            
            //Save the title number.
            $tmp = explode(':', $xml->section_number);
            $tmp = explode(' ', $tmp[0]);
            $xml->structure->unit = $tmp[1];

            if (isset($meta['description']))
            {
                $xml->catch_line = $meta['description'];
            }
            elseif (isset($alt_description[1]))
            {
                $xml->catch_line = $alt_description[1];
            }
            else
            {
                $xml->catch_line = ''; //all else fails
            }

            //Deal with inconsistent case of sortcode meta tag;
            //sometimes it's capitalized, sometimes not
            if (isset($meta['sortcode']))
            {
                $xml->order_by = clean_sortcodes($meta['sortcode']);    
            }
            else
            {
                $xml->order_by = clean_sortcodes($meta['Sortcode']);    
            }
print_r($xml);
die();
            $error = $q->errorInfo();
            if ($error[1])
            {
                print_r($error);$errors++;
            }
            else
            {
                $counter++;
            }

            $law->clear(); 
            unset($law);
        }
    }
}

//Find execution time
$time_end = microtime(true);
$execution_time = ($time_end - $time_start)/60;

echo "\nScraping complete in " . round($execution_time,2) .
" minutes.  $docs urls scanned, $counter statutes added, $errors errors"; 
