<?php 
class Terms extends SimpleOrm 
{ 
	static protected $table = DB_PREFIX . 'terms';
	static protected $pk = 'term_id';
}