<?php
require_once 'vendor/autoload.php';
require_once 'vendor/openaustralia/scraperwiki/scraperwiki.php';

use PGuardiario\PGBrowser;
date_default_timezone_set('Australia/Sydney');

# Default to 'thisweek', use MORPH_PERIOD to change to 'thismonth' or 'lastmonth' for data recovery
switch(getenv('MORPH_PERIOD')) {
    case 'thismonth' :
        $period = 'thismonth';
        $xml_url = 'http://myhorizon.solorient.com.au/Horizon/urlRequest.aw?actionType=run_query_action&query_string=FIND+Applications+WHERE+MONTH(Applications.Lodged)%3DCURRENT_MONTH+AND+YEAR(Applications.Lodged)%3DCURRENT_YEAR+ORDER+BY+Applications.AppYear+DESC%2CApplications.AppNumber+DESC&query_name=SubmittedThisMonth&take=50&skip=0&start=0&pageSize=500';
        break;
    case 'lastmonth' :
        $period = 'lastmonth';
        $xml_url = 'http://myhorizon.solorient.com.au/Horizon/urlRequest.aw?actionType=run_query_action&query_string=FIND+Applications+WHERE+MONTH(Applications.Lodged-1)%3DSystemSettings.SearchMonthPrevious+AND+YEAR(Applications.Lodged)%3DSystemSettings.SearchYear+AND+Applications.CanDisclose%3D%27Yes%27+ORDER+BY+Applications.AppYear+DESC%2CApplications.AppNumber+DESC&query_name=SubmittedLastMonth&take=50&skip=0&start=0&pageSize=500';
        break;
    default         :
        if ( 0+getenv('MORPH_PERIOD') >= 1998) {
          $period = getenv('MORPH_PERIOD');
          $xml_url = 'http://myhorizon.solorient.com.au/Horizon/urlRequest.aw?actionType=run_query_action&query_string=FIND+Applications+WHERE+Applications.AppYear%3D1998+AND+Applications.CanDisclose%3D%27Yes%27+ORDER+BY+Applications.Lodged+DESC%2CApplications.AppYear+DESC%2CApplications.AppNumber+DESC&query_name=Applications_List_Search&take=50&skip=0&start=0&pageSize=500';
          $xml_url = str_replace('1998', $period, $xml_url);
        } else {
          $period = 'thisweek';
          $xml_url = 'http://myhorizon.solorient.com.au/Horizon/urlRequest.aw?actionType=run_query_action&query_string=FIND+Applications+WHERE+WEEK(Applications.Lodged)%3DCURRENT_WEEK-1+AND+YEAR(Applications.Lodged)%3DCURRENT_YEAR+AND+Applications.CanDisclose%3D%27Yes%27+ORDER+BY+Applications.AppYear+DESC%2CApplications.AppNumber+DESC&query_name=SubmittedThisWeek&take=50&skip=0&start=0&pageSize=500';
        }
        break;
}
print "Getting data for `" .$period. "`, changable via MORPH_PERIOD environment\n";

$cookie_url  = 'http://myhorizon.solorient.com.au/Horizon/logonGuest.aw?domain=horizondap_lpsc';
$info_url    = 'http://myhorizon.solorient.com.au/Horizon/logonGuest.aw?domain=horizondap_lpsc';
$comment_url = 'mailto:lpsc@lpsc.nsw.gov.au';

# setup all the cookies then request for the xml page
$browser = new PGBrowser();
$page    = $browser->get($cookie_url);
$page    = $browser->get($xml_url);

$xml = simplexml_load_string($page->html);
foreach ( $xml->run_query_action_return->run_query_action_success->dataset->row as $row ) {
    $record = [
        'council_reference' => (string) $row->AccountNumber['org_value'],
        'address' => (string) $row->Property['org_value'] . ' NSW',
        'description' => (string) $row->Description['org_value'],
        'info_url' => $info_url,
        'comment_url' => $comment_url,
        'date_scraped' => date('Y-m-d'),
        'date_received' => date('Y-m-d', strtotime(str_replace('/', '-', $row->Lodged['org_value']))),
    ];

    if ( count(array_filter($record)) < 7 ) {
        print_r ("Skipping record because empty data found\n");
        print_r ($record);
        continue;
    }

    # Check if record exist, if not, INSERT, else do nothing
    $existingRecords = scraperwiki::select("* from data where `council_reference`='" . $record['council_reference'] . "'");
    if (count($existingRecords) == 0) {
        print ("Saving record " . $record['council_reference'] . " - " . $record['address']. "\n");
//         print_r ($record);
        scraperwiki::save(['council_reference'], $record);
    } else {
        print ("Skipping already saved record " . $record['council_reference'] . "\n");
    }
}
