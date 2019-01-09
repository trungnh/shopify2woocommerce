<?php
class Woocommerce_Attribute_Taxonomies extends SimpleOrm
{
	static protected $table = DB_PREFIX . 'woocommerce_attribute_taxonomies';
	static protected $pk = 'attribute_id';
}