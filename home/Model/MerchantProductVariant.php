<?php

App::uses('AppModel', 'Model');

/**
 * MerchantProductVariant model for ONZSA.
 *
 * @package       onzsa.Model
 */
class MerchantProductVariant extends AppModel {

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
    public $useTable = "merchant_product_variants";

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
    public $name = "MerchantProductVariant";

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
        'name' => array(
            'notEmpty' => array(
                'rule' => 'notEmpty'
            ),
            'isUnique' => array(
                'rule' => 'isUnique'
            ),
        ),
    );

}
