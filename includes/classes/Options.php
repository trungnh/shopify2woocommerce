<?php 
class Options extends SimpleOrm 
{ 
	static protected $table = DB_PREFIX . 'options';
	static protected $pk = 'option_id';
}