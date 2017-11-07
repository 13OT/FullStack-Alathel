
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
        
        while($this->args[0] == '' || $this->args[0] == 'api.php' || $this->args[0] == 'useradmin'){
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
        $this->temparray = [];
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
                $size= $this->request['size'];
            }else{
                $size=100;
            }
            $data = file_get_contents("https://randomuser.me/api/?results={$size}&nat=us&exc=id");
            if($data){
                $data = json_decode($data,true);
                foreach($data['results'] as $user){
                    $this->temparray = [];
                    $this->flatten_array($user);
                    $this->logger->do_log("flatten");
                    $this->logger->do_log($this->temparray);
                    $max_id = $this->mh->get_max_id($this->mdb,$this->mh->collection ,'_id');
                    $this->logger->do_log("MAX ID");
                    $this->logger->do_log($max_id);
                    $this->temparray['_id'] = $max_id;
                    $results[] = $this->mh->insert([$this->temparray]);
                }   
            }
            
        }
        return $results;
    }
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
        /**
     *  @name: add_user
     *  @description: adds a new user(s) to the collection
     *  @type: POST
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
                foreach($this->request as $k => $v)
                    $arr_ins[] = json_decode($v, true);
                $result = $this->mh->insert($arr_ins);
                return $result;
        }
                /**
     *  @name: update_user
     *  @description: update existing user(s) in the collection
     *  @type: POST
     *
     *  The posted data will be an json array of 1 - N arrays of key value pairs.
     *  E.g.  
     *  Example:
     *  { 
     *     TBD in class
     *  }
     */

        protected function update_user()
        {
            $this->mh->setDbcoll('users');

            

	        $request= [];
	        $users = [];
	        $result_del=[];
	        $result_ins=[];
            foreach($this->request as $k => $v){
    	        $request[$k][0] = json_decode($v[0], true);
                $request[$k][1] = json_decode($v[1], true);
                $users[] = json_decode(json_encode($this->mh->query($request[$k][0])), true);
                $users[$k] =$users[$k][0];
                $to_del[]= $request[$k][0];
}
            $result_del = $this->mh->delete($to_del);
            foreach ($request as $key => $val){
                foreach($val[1] as $k => $v)
	    		    $users[$key][strtolower($k)] = $v;
}
            $result_ins=$this->mh->insert($users);
            $this->logger->do_log($result_ins);
            $this->logger->do_log($result_del);
            if($result_ins[0] && $result_del)
                $result = array(" update" => "Success");
            else
                $result = array(" update" => "Failed");
            

            return $result;
        }
            /**
     *  @name: delete_user
     *  @description: deletes a user(s) from the collection
     *  @type: POST
     *  The posted data will be an json array of 1 - N arrays of key value pairs.
     *  E.g.  
     *  Example:
     *  { 
     *     TBD in class
     *  }
     */
    protected function delete_user()
    {

	    $arr_del=[];
        $this->mh->setDbcoll('users');
        foreach($this->request as $k => $v)
            $arr_del[] = json_decode($v, true);
        $result = $this->mh->delete($arr_del);
        return $result;
    }

    /**
     *
     *  @name: find_user:
     *  @description: finds a user(s) in the collection
     *  @type: POST
     *  specify attributes in request
     *  E.g.  
     *  Example:
     *  { 
     *     TBD in class
     *  }
     */
    protected function find_user()
    {
        $this->mh->setDbcoll('users');
        $this->request = array_filter($this->request);
        if(empty($this->request))
            return $this->mh->query();
        foreach($this->request as $k => $v)
        	$users[] = json_decode(json_encode($this->mh->query(json_decode($v,true))), true);
        return $users;
    }
    /*
     *  @name: random_user:
     *  @description: retreives a random user(s) from the collection 
     *  @type: GET
     *  specify count in request
     *  E.g.  
     *  Example:
     *  { 
     *     TBD in class
     *  }
     */

    protected function random_user()
    {
        $this->mh->setDbcoll('users');        
        $count = $this->request['count'];
        $result=[];
        $users = $this->mh->query();        
        for ($x = 0; $x < $count; $x++) {
            $i= rand ( 0 , count($users)-1 );
            $result [$x] = $users[$i];
        }

        return $result;
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
