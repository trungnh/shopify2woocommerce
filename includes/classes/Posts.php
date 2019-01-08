<?php 
class Posts extends SimpleOrm 
{ 
	static protected $table = DB_PREFIX . 'posts';
	static protected $pk = 'ID';
}