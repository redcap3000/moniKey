<?php

/*
        moniKey
        Feb 2012
        AGPL
        Ronaldo Barbachano

        PHP Library to simplify mongodb interactions into one overloaded static method class.

        Later versions may support other methods to do other things with mongo.

        Most object calls return arrays, and convert mongo id's to normal strings.

        All functions take the name and database as final arguments, but should ideally be defined by calling classes.

*/

// call syntax is messy but can be abstracted later..

define('MONGO_DB','monikey');

class moniKey{

        public static function __callStatic($names,$arguments){
        // to make this work database is usually always the same ? the name of the web app
        // the collections are named various classes get_called_class()

        $names = explode('_',$names);
        $database = MONGO_DB;
        if(count($names) ==  2){
                $collection = $names[1];
        }elseif(count($names) == 3){
                $database = $names[1];
                $collection = $names[2];
        }else{
                $collection = get_called_class();
        }

        if(!$collection){
        // just make a collection already if we are using outside of a class/ do not define it to avoid massive errors
                $collection = 'monikey_collection';
        }

        if(is_array($arguments[0])){
                $data = $arguments[0];
        }elseif(isset($arguments[1])){
                $id = $arguments[0];
                $data = $arguments[1];
        }else{
                $id= $arguments[0];
        }
        // connect
        // make $m a glob to avoid opening a ton of DB connx?
        $m = new Mongo();
        // select a database
        $db = $m->$database;
        $collection = $db->$collection;
        if($data != NULL && $id != NULL){
                $collection->update(array('_id'=> new MongoID($id)), $data);
        }
        elseif(is_array($data)){
                return $collection->insert($data);
         }elseif($id != NULL){
                $cursor = $collection->findOne(array('_id' => new MongoId($id) ) );
                foreach($cursor as $key=>$obj){
                        $r [$key] = $obj;
                }
                $r['_id'] = $id;
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

