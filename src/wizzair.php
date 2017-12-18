<?php
/**
 * Project: WizzairScraper
 *
 * @author Amado Martinez <amado@projectivemotion.com>
 */
if(!isset($argv))
    die("Run from command line.");
// copied this from doctrine's bin/doctrine.php
$autoload_files = array( __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php');
//var_dump($autoload_files);die;
foreach($autoload_files as $autoload_file)
{
    if(!file_exists($autoload_file)) continue;
    require_once $autoload_file;
}
// end autoloader finder
if($argc < 5)
    die("Usage:\n\t$argv[0] origin destination outbound-date inbound-date\n" .
        "Example:\n\t$argv[0] BFS VNO 2016-11-21 2016-11-25\n\n");
$origin = $argv[1];
$destination = $argv[2];
$departure_date = $argv[3];
$return_date = $argv[4];
echo "Using Parameters: $origin - $destination / $departure_date - $return_date\n\n";
$wizzair    =   new \projectivemotion\WizzairScraper\Scraper();
$wizzair->cacheOff();
$wizzair->verboseOff();
$wizzair->setAdults(1);
$wizzair->setCookieFileName(tempnam(sys_get_temp_dir(), 'wizzaircookie.'));
$api_detected = $wizzair->detect_api_version();
if($api_detected)
    echo "Detected api version: {$wizzair->getApiVersion()}\n";
$wizzair->setDepartureDate($departure_date);
$wizzair->setReturnDate($return_date);
try {
    $flights = $wizzair->getFlights($origin, $destination);
    echo json_encode($flights, JSON_PRETTY_PRINT);
}catch(Exception $e)
{
    echo "An Error ocurred: ", $e->getMessage(), ". You may want to try changing search parameters.";
    echo "\nConnection Info: ";
    var_export($wizzair->getInfo());
    exit(1);
}