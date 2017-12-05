<?php
error_reporting(1);
//https://www.sitepoint.com/php-53-namespaces-basics/
//http://zetcode.com/db/mongodbphp/
//http://coreymaynard.com/blog/creating-a-restful-api-with-php/
//https://programmerblog.net/php-mongodb-tutorial/

require('../scripts/image_helper.php');

abstract class API
{
    /**
     * Property: method
     * The HTTP method this request was made in, either GET, POST, PUT or DELETE
     */
    protected $method = '';
    /**
     * Property: endpoint
     * The Model requested in the URI. eg: /files
     */
    protected $endpoint = '';
    /**
     * Property: verb
     * An optional additional descriptor about the endpoint, used for things that can
     * not be handled by the basic methods. eg: /files/process
     */
    protected $verb = '';
    /**
     * Property: args
     * Any additional URI components after the endpoint and verb have been removed, in our
     * case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1>
     * or /<endpoint>/<arg0>
     */
    protected $args = array();
    /**
     * Property: file
     * Stores the input of the PUT request
     */
    protected $file = null;

    /**
     * Constructor: __construct
     * Allow for CORS, assemble and pre-process the data
     */
    public function __construct()
    {
        header("Access-Control-Allow-Orgin: *");
        header("Access-Control-Allow-Methods: *");
        header("Content-Type: application/json");
        
        $this->logger = new thelog();
        $this->logger->clear_log();

        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->request_uri = $_SERVER['REQUEST_URI'];
        
        $this->logger->do_log($this->method);
        
        $this->args = explode('/', rtrim($this->request_uri, '/'));
        
        $this->logger->do_log($this->args);

        while ($this->args[0] != 'index.php') {
            array_shift($this->args);
        }
        array_shift($this->args);
        
        $this->endpoint = array_shift($this->args);


        if (strpos($this->endpoint, '?')) {
            list($this->endpoint,$urlargs) = explode('?', $this->endpoint);
        }
        
        $this->logger->do_log($this->args, "args array:");
        $this->logger->do_log($this->endpoint, "endpoint:");
        

        switch ($this->method) {
            case 'POST':
                $this->request = $this->_cleanInputs($_POST);
                break;
            case 'DELETE':
            case 'GET':
                $this->request = $this->_cleanInputs($_GET);
                break;
            case 'PUT':
                $this->request = $this->_cleanInputs($_GET);
                $this->file = file_get_contents("php://input");
                break;
            default:
                $this->_response('Invalid Method', 405);
                break;
        }
        
        if ($urlargs) {
            $urlargs = explode('&', $urlargs);
            for ($i=0; $i<sizeof($urlargs); $i++) {
                list($k,$v) = explode('=', $urlargs[$i]);
                $this->request[$k] = $v;
            }
        }
    }
    
    public function processAPI()
    {
        $this->logger->do_log($this->endpoint);
        if (method_exists($this, $this->endpoint)) {
            return $this->_response($this->{$this->endpoint}($this->args));
        }
        return $this->_response("No Endpoint: $this->endpoint", 404);
    }

    private function _response($data, $status = 200)
    {
        header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
        return json_encode($data);
    }

    private function _cleanInputs($data)
    {
        $clean_input = array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->_cleanInputs($v);
            }
        } else {
            $clean_input = trim(strip_tags($data));
        }
        return $clean_input;
    }

    private function _requestStatus($code)
    {
        $status = array(
            200 => 'OK',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        );
        return ($status[$code])?$status[$code]:$status[500];
    }
}

class MyAPI extends API
{
    public function __construct()
    {
        parent::__construct();
        
        $this->mdb = 'waldogame';
        $this->mh = new mongoHelper($this->mdb);
        $this->mh->setDbcoll('users');
    }


    public function make_game_board(){
        $args = $this->request;
        return $args;

    }

    public function distance(){
        $result = json_decode(json_encode($this->mh->query()),true);
        $args = $this->request;
        $result = $result[0]['bbox'];
        $ret_res= $this->is_in_box($args['x'],$args['y'],$result);
        $test=['found' => $ret_res[0], 'directions' => $ret_res[1]];
        $this->logger->do_log($test);
        return ['found' => $ret_res[0], 'directions' => $ret_res[1]];

    }

    private function is_in_box($x,$y,$box){
        $directions='Go: ';
        $found = false;
        $x_c = ($box[0]+$box[2])/2;
        $y_c = ($box[1]+$box[3])/2;
        $x /=0.8195669607;
        $y /=0.96144578313;
        $x_d =$x-$x_c;
        $y_d =$y-$y_c;
        if($x >=$box[0] && $x <= $box[2])
            if($y >=$box[1] && $y <= $box[3]){
                $found = true;
                $directions = 'You found him !';
            }
        if(!$found){
            if($x_d < 0&& $x_d < -10){
                $directions .="Right";
        }
            elseif($x_d >0 && $x_d > 10){
                $directions .="Left";
        }
            if($y_d < 0 &&$y_d < -10){
                $directions .=" Down";
        }
            elseif($y_d >0 && $y_d> 10){
                $directions .=" Up";
        }}
        
        $result = [0 => $found, 1 => $directions];
        return $result;
            

    }
        

    /////////////////////////////////////////////////////
    private function flatten_array($array){
        foreach($array as $key => $value){
            //If $value is an array.
            if(is_array($value)){
                //We need to loop through it.
                $this->flatten_array($value);
            } else{
                //It is not an array, so print it out.
                $this->temparray[$key] = $value;
            }
        }
    }

    private function addPrimaryKey($data, $coll, $key)
    {
        $max_id = $this->mh->get_max_id($this->mdb, $coll, $key);
        if ($this->has_string_keys($data)) {
            if (!array_key_exists($data, $key)) {
                $data[$key] = $max_id;
            }
        } else {
            foreach ($data as $row) {
                if (!array_key_exists($data, $key)) {
                    $data[$key] = $max_id;
                    $max_id++;
                }
            }
        }
        return $data;
    }
     
     
    private function isAssoc(array $arr)
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    
    private function has_string_keys(array $array)
    {
        return count(array_filter(array_keys($array), 'is_string')) > 0;
    }
}

$api = new MyAPI();
echo $api->processAPI();

exit;

/*
endpoint:
distance
distance
Array
 1022 X 798
 596 X 250
(
    [x] => 596
    [y] => 250
)

Array
(
    [0] => stdClass Object
        (
            [_id] => MongoDB\BSON\ObjectId Object
                (
                    [oid] => 5a1f96839dc0a36cfa5c6ca2
                )
                    1247 X 830
                    668 X 288
            [x] => 668
            [y] => 288
            [game_id] => 1512019585
            [image_path] => /var/www/html/whereswaldo2/game_images
            [img_type] => png
            [bbox] => Array
                (
                    [0] => 668
                    [1] => 288
                    [2] => 680
                    [3] => 316
                )

        )

)



*/