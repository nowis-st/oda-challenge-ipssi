<?php
/**
 * Created by PhpStorm.
 * User: administrateur
 * Date: 22/08/16
 * Time: 16:08
 */

namespace App\Http\Controllers;

use App\Http\Managers\PayzenManager;
use App\Models\Offer;
use Exception;
use JWTAuth;


class PaymentController extends Controller
{
    private $payzen;

    public function __construct()
    {
        $this->payzen = new PayzenManager();
    }

    public function index($id_offer){
        $token = JWTAuth::getToken();
        dd($token);

        return redirect()->route('payment',['id' => $id_offer, '/?token=' => $token ]);
    }

    public function generateForm($id,$mode = null){

        $user = JWTAuth::parseToken()->authenticate();
        //Test if offer exist if not set to null
        $offerData = Offer::find($id);
        $chosenOffer = !is_null($offerData) ? $offerData->toArray() : null;

        //Setting up form option to merge with default parameters (set in thne PayzenManager & config files)
        // Test if payement is annual or monthly and change payment method
        if(is_null($mode) && !is_null($chosenOffer)){
            $mutiplier = 1;
            $paymentSettings["vads_payment_config"]= "SINGLE";
        }
        else{
            if($mode === "annual"){
                $mutiplier = 1.1;
                $paymentSettings["vads_payment_config"] = "MULTI:first=".intval($chosenOffer["price"] * 100 * $mutiplier / 12).";count=12;period=30";
            }
            else{
                Throw new Exception("Méthode de paiement non valide");
            }
        }

        //Multiply by 100 to get cents (minimum quanta of money), see PayzenDoc for more details
        $paymentSettings['vads_amount'] = intval($chosenOffer["price"] * 100 * $mutiplier);
        $paymentSettings["vads_url_success"] = 'http://localhost:8000/validate/'.$user->id.'/'.$offerData->id;
        $paymentSettings["vads_url_error"] = 'http://localhost:9000/#/';
        $paymentSettings["vads_url_return"] = 'http://localhost:8000/validate/'.$user->id;
        $paymentSettings["vads_return_mode"] = 'GET';

        // Retieving all data to complete the form in order to make the payment
        $formData = $this->payzen->getFormParams($paymentSettings);
        $signature = $formData["signature"];
        $fields = $formData['fields'];
        return view('form', ["fields" => $fields,"signature" => $signature]);
    }
}