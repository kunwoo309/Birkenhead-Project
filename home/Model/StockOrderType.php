<?php

App::uses('AppModel', 'Model');

/**
 * StockOrderType model for ONZSA.
 *
 * @package       onzsa.Model
 */
class StockOrderType extends AppModel {

/**
 * The name of the database connection to use bind this model class.
 *
 * @var string
 */
    public $useDbConfig = "default";

/**
 * The name of the database table.
 *
 * @var string
 */
    public $useTable = "stock_order_types";

/**
 * Primary key of the database table.
 *
 * @var string
 */
    public $primaryKey = "id";

/**
 * Name of the Model.
 *
 * @var string
 */
    public $name = "StockOrderType";

/**
 * belongsTo property
 *
 * @var array
 */
    public $belongsTo = array();

/**
 * Validation rules.
 *
 * @var array
 */
    public $validate = array(
    );

}
