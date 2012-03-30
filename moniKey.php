<?php

/*
        moniKey
        March 2012
        AGPL
        Ronaldo Barbachano

        PHP Library to simplify mongodb interactions into one overloaded static method class.

        Later versions may support other methods to do other things with mongo.

        Most object calls return arrays, and convert mongo id's to normal strings.

        All functions take the name and database as final arguments, but should ideally be defined by calling classes.
        
        Does a lot of advanced stuff that I just haven't documented yet like mongo id conversions, and automatic
        'query' optimizations (for example a view can be made to only return a single field, instead of mongodb's
        default 'all fields'). Also properly handles updates/inserts now, and can do unique field checks on inserts.
        
        Check out noClass framework for more info, or read the comments.

*/

// call syntax is messy but can be abstracted later..
interface noSqlCRUD
{
    public function get($id,$database);
    public function put($data,$id,$database);
// reorder and put opt before database ...
    public function view($name,$key,$database,$opt);
    public function update($id,$data,$database);
    public function delete($id,$opt,$database);
}
abstract class moniKey implements noSqlCRUD{
        public static function __callStatic($names,$arguments){
		$names = explode('__',$names);
		$database = self::mongodb;
		if(count($names) ==  2)
			$collection = $names[1];
		elseif(count($names) == 3){
			$database = $names[1];
			$collection = $names[2];
		}else
			$collection = get_called_class();

		!$collection AND $collection = 'monikey_collection';
		// just make a collection already if we are using outside of a 
		//class/ do not define it to avoid massive errors consider making a class const
		$m = new Mongo();
		// select a database
		$db = $m->$database;
		$collection = $db->$collection;
		
		if($names[0] == 'find' ){
			if(count($arguments) > 1 ){
				foreach($collection->find($arguments[0],$arguments[1]) as $result){
					// dont return objects that have mongo id's .. following custom generated convention.. id keys
					if(!is_object($result['_id'])){
						$r [$result['_id']]=$result;
						// remove old id value that is now the key
						unset($r[$result['_id']]['_id'] );
						//  = array_filter($r);
					}else{
						// convert $result['_id']
						$new_id = $result['_id']->{'$id'};
						$r [$new_id] = $result;
						unset($r[$new_id]['_id']);
					}
				}
				// theres probably some array function that does this for me..
				if(isset($arguments[2])){
					// reform the array field to send back a more simple structure
					$id = key($r);
					$r[$id] = $r[$id][key($r[$id])];
				}
				return $r;
			}else{
				$c = $collection->find($arguments[0]);
				foreach( $c as $result){
					if(isset($result['_id']) && is_string($result['_id']) ){
						$r[$result['_id']]=$result;
						unset($r[$result['_id']]['_id'] );
					}else{
						$m_id = $result['_id']->{'$id'};
						unset($result['_id']);
						$r[$m_id] = $result;
					}
				}
				return $r;
			}
			
		}elseif($names[0] == 'findOne' && is_array($arguments[0])){
			if(isset($arguments[0])){
			// allow for filter...
			// $fields-values = array('fieldname'=>'value');
			// $return-fields = array('fieldname')
			// moniKey->findOne__DATABASE($fields-values,$return fields(optional) )
			// PHP loses track of my arguments for some stupid reason this entire logical flow needs work
				if($arguments[1] == 'firstFieldCheck' || isset($arguments[1])){
				// only return the first field
					$arguments[1] = array($arguments[0][0]);
				// a little loosey goosey	
				
					return (is_array($arguments[1]) ? $collection->findOne($arguments[0],$arguments[1]) : false);
				}
				else{
					return $collection->findOne($arguments[0]);
					}
			}	
		}
		
		elseif($names[0] == 'remove' && isset($arguments[0]) ){
			// support OPT's for delete in range ? not for now...
			// if arguments[0] just pass it into the remove function
			// of arguments[1] then process the option array like ("justOne") etc.
			if(isset($arguments[0]['_id']) )
				$collection->remove( array('_id'=> new MongoId($arguments[0]['_id'] ) ) ) ;
			else{
				$cursor = $collection->findOne( $arguments[0]);
				foreach($cursor as $key=>$obj)
					$r [$key] = $obj;
			// get the doc then remove it .. annoying...
			$to_remove  = $collection->findOne($arguments[0]);
			$collection->remove( $to_remove->_id ) ;

			}
		}
		else{
			if(is_array($arguments[0]))
				$data = $arguments[0];
			elseif(isset($arguments[1])){
				$id = $arguments[0];
				$data = $arguments[1];
				}
			else
				$id= $arguments[0];
			if(isset($data) && $data != NULL && $id != NULL){
				if(isset($_POST['_id']) ){
					array_pop($data);
					// really need to determine wether an id is a mongo id or a string
				}
				// do array comparison on existing and $_POST and only update the changed fields ... may need
				// to do some trickery to handle the mongofunction call if i cant oop it.. :(
				
				// get rid of id
				// would be nice to call /store elsewhere to reavoid lookup .. but kinda handy 
				// for multiuser enviornments (incase another user sets the same values...)
				
				// careful here MongoID to take argument[0] in specific cases ? this may break inserts?
				$existing = $collection->findOne(array('_id'=> new MongoID($id)));
				if(empty($existing)){
					// try looking it up as a non MongoID
					$existing = $collection->findOne(array('_id'=> $id ) );
				}

				$id = array_shift($existing);

				// order could throw this operation off... annoying since array_dif doesnt sem to work ?
				if(	!empty($data)){
				// could store data to a 'version' database and track changes here... but probably should write another
				// function/class for versioning and testing
				// could get from arguments[0] but diddnt feel like making a new mongo object..
						if(!$collection->update(array('_id'=> new MongoID($id)	),$data) ){
							echo 'update failed';
						}
						// forward self to new page ? or handle with noClass...
						return true;
						}
					else{
						return false;
						}
				// NO Change for existing record ??
			}
			elseif(isset($data) && is_array($data)){
			
				$collection = $collection->save($data);					
			}elseif($id != NULL){
			// count string and if it does not contain underscores...
//				if(strlen($id) == 31 && !strpos($id,'_') )
//					$id = new MongoID($id);
				$cursor = $collection->findOne(array('_id' =>  (strlen($id) == 24 && !strpos($id,'_') ? new MongoId($id): $id ) ) ) ;
				foreach($cursor as $key=>$obj)
						$r [$key] = $obj;
						
				return $r;
			}else{
			// return a better structured array ?
			// dont display the _id as a mongoId object ...
				foreach( $collection->find() as $obj){
						$the_id = $obj['_id']->{'$id'};
						unset($obj['_id']);
						// converts ID to string and uses it as the key in the returned array
						$r [$the_id]=  $obj ;
				}
				return $r;
			}
		}	

	}
	public function get($id=NULL,$database=NULL){
	// if id null then get all records ?? in collection 
	// by 'database' for mongo we mean 'collection'
		$database == NULL AND $database = get_called_class();
		$call = "get__$database";
		return self::$call($id);

	}
	public function put($data,$id=NULL,$database=NULL){
		
//		echo 'attempting to put';
		if($database == null) $database = get_called_class();
		// check to see if document exists before putting or else replace to ensue.. maybe mongodb command exists to do this already
		!$data['_id'] && $id != NULL AND $data['_id'] = $id;
		if(!$data['_id'] && $id == NULL && isset($this->_k) ){
		// generate doc ... good time to use a _k to look up if its a unique key.. or use a _u to track unique
		// keys per class ??
		 	foreach(array_keys($data) as $key ) 
		 		foreach($this->_k as $type=>$ktype)
			 		if(in_array( $key, $ktype)) 
			 		// plug val into search query ...
			 			$to_check [$key]=$data[$key];
			$call = 'find__' . get_called_class();
			$pre_check = self::$call($to_check);
			if(!empty($pre_check)){
			// handle better.. maybe make a 'reporter' class ???? to do logging/debugging?? would be nice
			// mid week project...
				$this->message = array('html'=>'<h4 class="error">Record exists with similar values</h4>');
				$this->_f[] = 'message';
				foreach($to_check as $name=>$value)
					unset($_POST[$name]);
			}
			else{
				$database == NULL AND $database = get_called_class();
				$call = "__$database";
				return self::$call($data,$id);
			}

		}

		$database == NULL AND $database = get_called_class();
		$call = "__$database";
		// check if key exists ??
		
		if($id == NULL)
			// set id to new mongo id?
			return self::$call($data);
		if($id != NULL)
		{
				
			if(!is_array(self::GET($id))){
				return self::$call($data,$id);
			}
			elseif(isset($_POST['_id'])) {
				echo 'duplicate encountered';
				foreach($_POST as $key=>$value){
					if($value == $id)
						unset($_POST[$key]);
				}
				echo $this();
				// this gets called prematurely ... only execute this if the post method is get?
				// we arent handling this flow very well..
				die( "$id already exists within '". get_called_class() . "', please pick a new value");
			}
		}	

	}
	public function view($name,$key=NULL,$database=NULL,$opt=NULL){
	// mongo opt - $opt = 'all keys' $opt = 'one key'
		$name_cpy = $name;
		if($database == null) $database = get_called_class();
		$call = "find__$database";	
			$find = $call;
		if(!is_array($key) && !is_array($name) ){
			if($key == NULL){
				$search = array("$name"=> array('$exists' => true));
				return self::$call($search );
				}
			}	
		
		if(!is_array($name) && is_string($key) ){
			// never gets called why is it here?
			// if we do this we have to depreciate 'findOne option' calls ... 
			$find_2 = array("$name" => $key);
			if(is_string($opt)){
				$name = $opt;
				// return only one field unless a comma list is encountered or if opt is an array ??
				// set some kind of flag to let it return the data in a better format if we are just returning one field
				// i.e mongo object -> _id => field instead of mongo object -> _id => field_name =>field value
				return self::$call($find_2,array("$name"=>true),'oneField' );
			}
			
			// hopefully callstatic can handle the second array value properly...
			return self::$call($find_2 );
		}
		// need to do this better. ... 
		if(is_array($name_cpy)){
			if($opt == 'findOne' || $opt == 'findFirstField'){
			// use view($criteria,NULL,NULL,'findOne')
				$call = "findOne__$database";
			}
			return self::$call($name_cpy);
		}
		
		if($opt == 'all keys')
			return self::$call($name);
		elseif(is_array($key) && is_array($name)){
			return self::$call($name,$key);
			}
		elseif(is_array($name)){
			return self::$call($name);
		}
		else{
			return self::$call($key,array("$name"=>1));
		}
		// should be simple enough once i figure out the appr. interface...
	}
	public function update($id,$data,$database=NULL){
	// yikes update is always set to 'true' ? so we always create new records ? whats up with that...
		$database == NULL AND $database = get_called_class();
		$call = "__$database";
		echo "\n\n update call : $call : $id\n\n";
		return self::$call($id,$data);
		
	}
	public function delete($id,$opt=null,$database=null){
		if($database ==null) $database = get_called_class();
		$call = "remove__$database";
		$id = array('_id'=>$id);
		self::$call($id );
	}

}
