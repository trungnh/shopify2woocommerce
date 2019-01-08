<?php
class WoocommerceAttributeTaxonomies extends SimpleOrm
{
	static protected $table = DB_PREFIX . 'woocommerce_attribute_taxonomies';
	static protected $pk = 'attribute_id';
}