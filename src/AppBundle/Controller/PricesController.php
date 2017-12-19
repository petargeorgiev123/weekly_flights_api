<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\View\View;
use AppBundle\Entity\User;

class PricesController extends FOSRestController
{

    protected $origin;
    protected $destination;
    protected $outbound_date;
    protected $inbound_date;
    protected $wizzair;
    protected $weeks;
    protected $date;
    protected $weekDays;

    public function __construct()
    {
        var_dump("entramos");
        $this->wizzair = new Scraper();
        $this->wizzair->cacheOff();
        $this->wizzair->verboseOff();
        $this->wizzair->setAdults(1);
        $this->wizzair->setCookieFileName(tempnam(sys_get_temp_dir(), 'wizzaircookie.'));

        $this->date = time();
    }

    protected function getWeekDays()
    {
        $timestamp = strtotime('next Monday');
        $weekDays = array();
        for ($i = 0; $i < 7; $i++) {
            $weekDays[strftime('%A', $timestamp)] = $i+1;
            $timestamp = strtotime('+1 day', $timestamp);
        }
        return $weekDays;
    }

    protected function setOutboundDate($dotw){
        return  ($dotw == array_search(ucfirst($this->outbound_date), $this->weekDays)) ?
            $this->date : strtotime("next " . ucfirst($this->outbound_date), $this->date);
    }

    protected function setInboundDate($start){
        //Remove one day so we can count one more day for the trip
        return strtotime("+". $this->inbound_date - 1 ." day", $start);
    }

    protected function setPrice($flights, $index){
        $price = "No flight";
        if(!empty($flights[$index])){
            $price = $flights[$index][0]["fares"][0]["discountedPrice"]["amount"]
                . " " . $flights[$index][0]["fares"][0]["discountedPrice"]["currencyCode"];
        }
        return $price;
    }

    /**
     * @Rest\Get("/search/{origin}/{destination}/{outbound_date}/{inbound_date}/{weeks}")
     */
    public function getAction($origin, $destination, $outbound_date, $inbound_date, $weeks)
    {
        $this->origin = $origin;
        $this->destination = $destination;
        $this->outbound_date = $outbound_date;
        $this->inbound_date = $inbound_date;
        $this->weeks = $weeks;

        $this->weekDays = $this->getWeekDays();

        $result = array();
        //$date = time();
        for($i = 0; $i <= $this->weeks; $i++){
            if($i != 0){
                $this->date = strtotime("+ 1 week", $this->date);
            }
            $dotw = $dotw = date('w', $this->date);
            if($dotw == 0){
                $dotw = 7;
            }

            $start = $this->setOutboundDate($dotw);
            $end = $this->setInboundDate($start);

            /*$api_detected = $wizzair->detect_api_version();
            if($api_detected)
                echo "Detected api version: {$wizzair->getApiVersion()}\n";*/
            $this->wizzair->setDepartureDate(date("Y-m-d H:i:s", $start));
            $this->wizzair->setReturnDate(date("Y-m-d H:i:s", $end));
            try {
                $flights = $this->wizzair->getFlights($origin, $destination);
                //return json_encode($flights, JSON_PRETTY_PRINT);
                $outboundPrice = $this->setPrice($flights, "outboundFlights");

                $inboundPrice = $this->setPrice($flights,"returnFlights");

                $total = "Not available";
                if($outboundPrice != "No flight" AND $inboundPrice != "No flight"){
                    $total = $flights["outboundFlights"][0]["fares"][0]["discountedPrice"]["amount"] +
                        $flights["returnFlights"][0]["fares"][0]["discountedPrice"]["amount"] . " " .
                        $flights["outboundFlights"][0]["fares"][0]["discountedPrice"]["currencyCode"];
                }
                $result[$i] = array(
                    "outboundFlight" => date("Y-m-d H:i:s", $start),
                    "outboundPrice" => $outboundPrice,
                    "inboundDate" => date("Y-m-d H:i:s", $end),
                    "inboudPrice" => $inboundPrice,
                    "returnTicket" => $total
                );

            }catch(Exception $e)
            {
                echo "An Error ocurred: ", $e->getMessage(), ". You may want to try changing search parameters.";
                echo "\nConnection Info: ";
                var_export($this->wizzair->getInfo());
                exit(1);
            }
        }


        return $result;
    }
}