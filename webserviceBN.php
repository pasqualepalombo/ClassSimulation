<?php
/**
 * Created by PhpStorm.
 * User: Gabriel
 * Date: 3/29/2018
 * Time: 12:24 PM
 */
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_reporting(E_WARNING);*/
class WebServiceBN {

    /**
     * Method used to call the BNS to get its parameters
     *
     * @return BNS parameters
     */
    function get_parameters() {
        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => ''
            )
        );

        $context = stream_context_create($opts);

        $json_parameters = file_get_contents("http://twiki.di.uniroma1.it/BNservice/v0", false, $context);
        $parameters = json_decode($json_parameters);
	
        return $parameters;
    }

    /**
     * Sends the data about the sessions to the Bayesian Network Service to model the students
     *
     * @param    stdClass $sessiondata       the data about the sessions
     * @param    string $url                 the url to the Bayesian Network Service API
     * @return   string                      the models of the students represented as a json string
     */
    function post_session_data($sessiondata)
    {
		/* si mostra cosi sessiondata:
        {
            "parameters": {
                "strategy": "maxEntropy",
                "termination": "corrected30",
                "mapping": "weightedSum",
                "domain": [
                    1,
                    0.94999999999999996,
                    0.84999999999999998,
                    0.75,
                    0.65000000000000002,
                    0.55000000000000004,
                    0
                ]
            },
            "peer-assessments": []
        } */

        $jsonsessiondata = json_encode($sessiondata, JSON_NUMERIC_CHECK );

        $opts = array(
            'http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/json',
                'content' => $jsonsessiondata
            )
        );

        $context = stream_context_create($opts);
        // End POST parameter

        $url = "https://twiki.di.uniroma1.it/BNservice/v0/BN";
	
        $json_student_models = file_get_contents($url, false, $context);

        
        $studentmodels = json_decode($json_student_models);
        
        return $json_student_models;
    }
}

?>

