<?php 
class Postmeta extends SimpleOrm 
{ 
	static protected $table = DB_PREFIX . 'postmeta';
	static protected $pk = 'meta_id';
}