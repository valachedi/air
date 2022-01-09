<?php
require 'vendor/meekrodb/db.class.php';

require 'components/Flight/SearchReseat.php';
require 'components/CurrentUser.php';
require "ModelRequest/AbstractRequest.php";
require "ModelRequest/AirplaneRequest.php";
require "ModelRequest/AirportRequest.php";
require 'ModelRequest/ComfortCategoryRequest.php';
require 'ModelRequest/CountryRequest.php';
require 'ModelRequest/CountryCityRequest.php';
require 'ModelRequest/FlightRequest.php';
require 'ModelRequest/TicketRequest.php';
require 'ModelRequest/UserRequest.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

DB::$user = 'air';
DB::$password = 'aircraft';
DB::$dbName = 'air';
DB::$encoding = 'utf8mb4';

list($uri) = explode('?', $_SERVER['REQUEST_URI']);

@list($model,$action) = explode('/', substr($uri,1));
$jsonResponse = true;

switch($model) {
    case 'airplane':

        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, TRUE);
            $responseData = (new AirplaneRequest($input))->save();
        } else {
            $responseData = AirplaneRequest::findAll();
        }
        break;

    case 'airport':
        $responseData = AirportRequest::findAll();
        break;

    case 'comfort-category':
        $responseData = ComfortCategoryRequest::findAll();
        break;

    case 'country':
        $responseData = CountryRequest::findAll();
        break;

    case 'country-city':
        $responseData = CountryCityRequest::findAll();
        break;

    case 'flight':
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, TRUE);
            $responseData = (new FlightRequest($input))->save();
        } else {
            $responseData = (new FlightRequest($_GET))->find();
        }
        break;

    case 'ticket':
        switch($action) {
            case 'buy':
                $request = new TicketRequest($_GET);
                $responseData = $request->buy();
                break;
            case 'remove':
                $inputJSON = file_get_contents('php://input');
                $input = json_decode($inputJSON, TRUE);
                $responseData = (new TicketRequest($input))->delete();
                break;
            default:
                $request = new TicketRequest($_GET);
                $responseData = $request->findAll();
        }

        break;

    case 'user':
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $input = file_get_contents('php://input');
            $inputJson = json_decode($input, TRUE);

            if($action == 'login') {
                $responseData = (new UserRequest($inputJson))->login();
            } elseif($action == 'register') {
                $responseData = (new UserRequest($_POST))->register();
            } elseif($action == 'update-own') {
                $responseData = (new UserRequest($_POST))->updateOwn();
            } elseif($action == 'password-restore') {
                $responseData = (new UserRequest($inputJson))->restorePassword();
            } else {
                $responseData = (new UserRequest($inputJson))->save();
            }
        } else {
            if($action == 'own') {
                $responseData = (new UserRequest($_GET))->getOwn();
            } elseif($action == 'photo') {
                (new UserRequest($_GET))->echoPhotoContent();
                $jsonResponse = false;
            }else {
                $responseData = (new UserRequest($_GET))->findAll();
            }
        }

        break;

    case 'dev':
        $responseData = ['is_authorized' => CurrentUser::getInstance()->isAuthorized()];
        break;

    default:
        $responseData = ['error' => 'unknown request'];
}

if($jsonResponse) {
    echo json_encode($responseData);
}
