<?php 
class TermTaxonomy extends SimpleOrm 
{ 
	static protected $table = DB_PREFIX . 'term_taxonomy';
	static protected $pk = 'term_taxonomy_id';
}