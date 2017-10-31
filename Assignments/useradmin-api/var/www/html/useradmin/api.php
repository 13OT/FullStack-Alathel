<?php
error_reporting(1);
//https://www.sitepoint.com/php-53-namespaces-basics/
//http://zetcode.com/db/mongodbphp/
//https://programmerblog.net/php-mongodb-tutorial/
require('mongo_helper.php');
/**
* Api based on: http://coreymaynard.com/blog/creating-a-restful-api-with-php/
*/
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
    protected $args = Array();
    /**
     * Property: file
     * Stores the input of the PUT request
     */
     protected $file = Null;
    /**
     * Constructor: __construct
     * Allow for CORS, assemble and pre-process the data
     */
    public function __construct() {
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
        
        while($this->args[0] == '' || $this->args[0] == 'api.php'){
        	array_shift($this->args);
        }
        
        $this->endpoint = array_shift($this->args);
        if(strpos($this->endpoint,'?')){
        	list($this->endpoint,$urlargs) = explode('?',$this->endpoint);
        }
        $this->logger->do_log("args array");
        $this->logger->do_log($this->args);
	$this->logger->do_log("ENDPOINT");
        $this->logger->do_log($this->endpoint);
        
        switch($this->method) {
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
        
        if($urlargs){
        	$urlargs = explode('&',$urlargs);
        	for($i=0;$i<sizeof($urlargs);$i++){
        		list($k,$v) = explode('=',$urlargs[$i]);
        		$this->request[$k] = $v;
        	}	
        }
    }
    
    public function processAPI() {
        $this->logger->do_log($this->endpoint);
        if (method_exists($this, $this->endpoint)) {
            return $this->_response($this->{$this->endpoint}($this->args));
        }
        return $this->_response("No Endpoint: $this->endpoint", 404);
    }
    private function _response($data, $status = 200) {
        header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
        return json_encode($data);
    }
    private function _cleanInputs($data) {
        $clean_input = Array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->_cleanInputs($v);
            }
        } else {
            $clean_input = trim(strip_tags($data));
        }
        return $clean_input;
    }
    private function _requestStatus($code) {
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
    public function __construct() {
        parent::__construct();
		
		$this->mdb = 'fake-business';
        $this->mh = new mongoHelper($this->mdb);
        $this->primary_key = 'uid';
    }
    /**
     * Inserts random users into the db
     */
    protected function gen_random_user() {
        $this->mh->setDbcoll('users');
        $this->mh->delete();
        $results = [];
        if ($this->method == "GET"){
            if($this->request['size']){
                $size=$this->request['size'];
            }else{
                $size=100;
            }
            $data = file_get_contents("https://randomuser.me/api/?results={$size}&nat=us&exc=id");
            if($data){
                $data = json_decode($data,true);
                foreach($data['results'] as $user){
                    $max_id = $this->mh->get_max_id($this->mdb,$this->mh->collection ,'_id');
                    $this->logger->do_log($max_id,"Max ID:");
                    $user['_id'] = $max_id;
                    $results[] = $this->mh->insert([$user]);
                }   
            }
            
        }
        return $results;
    }
        /**
     *  @name: add_user
     *  @description: adds a new user(s) to the collection
     *  @type: PUT
     *
     *  The posted data will be an json array of 1 - N arrays of key value pairs.
     *  E.g.  
     *  Example:
     *  { 
     *     TBD in class
     *  }
     */
    protected function add_user(){
        
                $this -> mh-> setDbcoll('users');
        
                if(is_array($this->request)){
                        if(array_key_exists('data',$this->request)){
                                foreach($this->request['data'] as $k => $v)
                                        $arr_ins[] = json_decode($v, true);
                        }
        
                        else{
                                foreach($this->request as $k => $v)
                                        $arr_ins[] = json_decode($v, true);
        }}
                //$users = $this->mh->query(); pull users to see if keys match
                //$users = json_decode(json_encode($users), True);
                //$users = $users[0];
                //foreach($users[0] as $k => $v)
                //    $all_val[] = $k;
              //          $i=0;
              // foreach ($arr_ins as $key1 => $val1) {
              //    foreach($val1 as $key2 => $val2){
              //      if(in_array($key2, $all_val) && (is_string($val2) || is_long($val2)))
              //        $arr_to_ins[$i][$key2] = $val2;
              //    }
              //    $i++;
              //}
                $result = $this->mh->insert($arr_ins);
                return $result;
        }
        protected function update_user()
        {

            $this->mh->setDbcoll('users');

       	    $this->logger->do_log("REQUEST UPDATE");
	    $this->logger->do_log($this->request);
	    $request= [];
	    $users = [];
	    $result_del=[];
	    $result_ins=[];
            foreach($this->request as $k => $v){
    	        $request[$k][0] = json_decode($v[0], true);
                $request[$k][1] = json_decode($v[1], true);
}
            $this->logger->do_log("USER update requ after loop");
	    $this->logger->do_log($request);
            foreach($request as $k => $v)
                $users[] = $this->mh->query($v[0]);
	    $this->logger->do_log("PIECE OF SHIT QUERY");
            $this->logger->do_log($users);
            foreach($users as $k => $v)
                $users[$k] = json_decode(json_encode($v), true);
            $this->logger->do_log("USERs FOR update user");
            $this->logger->do_log($users);
            $this->logger->do_log("DELETES");
            foreach($request as $k => $v){
		$this->logger->do_log($v[0]);
                $result_del[] = $this->mh->delete([$v[0]]);
}
            foreach ($request as $key => $val){
                foreach($val[1] as $k => $v)
	    		$users[$key][0][$k] = $v;
}
            $this->logger->do_log(" NEW USERs update");
            $this->logger->do_log($users);
            foreach($users as $k => $v)
                $result_ins[] = $this->mh->insert($v);
            $results = array(" Insert" => $result_ins, "Delete" => $result_del);
            $this->logger->do_log("RESULT UP/DELETE");
            $this->logger->do_log($results);
            
            return $results;
            
            
        }
            /**
     *  @name: delete_user
     *  @description: deletes a user(s) from the collection
     *  @type: DELETE
     *  E.g.  
     *  Example:
     *  { 
     *     TBD in class
     *  }
     */
    protected function delete_user()
    {

        $this->mh->setDbcoll('users');
        foreach($this->request as $k => $v)
            $arr_del[] = json_decode($v, true);
        $result = $this->mh->delete($arr_del);
        $this->logger->do_log("RESULT OF dELETE  ");
        $this->logger->do_log($result);
        return $result;
    }

    /**
     *
     *  @name: find_user:
     *  @description: finds a user(s) in the collection
     *  @type: GET
     *  E.g.  
     *  Example:
     *  { 
     *     TBD in class
     *  }
     */
    protected function find_user()
    {
        $this->mh->setDbcoll('users');
        $this->logger->do_log("FIND  Request  ");
        $this->logger->do_log($this->request);        
        $users = $this->mh->query($this->request);
        $this->logger->do_log("RESULT OF find  ");
        
        $this->logger->do_log($result);
        
        return $users;
    }
    /*
     *  @name: random_user:
     *  @description: retreives a random user(s) from the randomuser api
     *  @type: GET
     *  This will also filter the data so that only the values we need are in the collection, and all top level keys with no nesting.
     *  E.g.  
     *  Example:
     *  { 
     *     TBD in class
     *  }
     */

    protected function random_user()
    {
        $this->mh->setDbcoll('users');
        $users = $this->mh->query();
        $i= rand ( 0 , count($users)-1 );
        $this->logger->do_log("I and its random ");
        $this->logger->do_log($i);        
        $this->logger->do_log($users[$i]);
        
        return $users[$i];
    }
    
     
     protected function max(){
        $this->mh->get_max_id();
     }
     private function addPrimaryKey($data,$coll,$key){
        $max_id = $this->mh->get_max_id($this->mdb,$coll,$key);
        if($this->has_string_keys($data)){
            if(!array_key_exists($data,$key)){
                $data[$key] = $max_id;
            }
        }else{
            foreach($data as $row){
                if(!array_key_exists($data,$key)){
                    $data[$key] = $max_id;
                    $max_id++;
                }
            }
        }
        return $data;
     }
     
     
    private function isAssoc(array $arr){
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    
    private function has_string_keys(array $array) {
  		return count(array_filter(array_keys($array), 'is_string')) > 0;
	}
     
 }
$api = new MyAPI(); 
echo $api->processAPI();
exit;
