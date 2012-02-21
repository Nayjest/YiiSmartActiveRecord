<?php
/**
* SmartAR class file
* 
* @author Vitaliy Stepanenko <mail@vitaliy.in>
* @package SmartActiveRecord
* @version $Id: SmartAR.php 29314 2011-06-10 15:38:17Z stepanenko $
* @license BSD
* 
* @todo check populateRecords
* 
*/

/**
* This class gives possibility to use power of Yii ActiveRecord without describing new model classes.
* It can be useful when you work with dynamically generated tables in database, 
* or when you don't want to create a lot of code to do some simple operations using convenient ActiveRecord interface.
* 
* <p><b>=== Usage ===</b>:</p>
* <code>
* <?php
* 	Yii::import('ext.components.SmartActiveRecord.SmartAR');
*	$model = SmartAR::model('tbl_MyTable')->findByAttributes('id'=>2);
* 	$model->somefield = someValue;
* 	$model->save();
* 	
* ?>
* </code> 
* 
* <p><b>=== Some logics of creating models ===</b>:</p>
* SAR::create(config)
*     $instance->new SAR, AR::__construct(null)
* 	  SAR::$_lastTableName	== config.tableName
*     $instance->AR::refreshMetaData
* 	  	AR::model(get_class($this)==SAR)
* 			AR::$_models['SAR'] = new SAR, AR::__construct(null)			
* 		AR::$_models['SAR']->metadata = new metadata(AR::$_models['SAR']->tableName() ====> SAR::$_lastTableName) //SQL query?
*       $instance->metadata = AR::$_models['SAR']->metadata ====> new metadata(SAR::$_lastTableName)
* --> configured instance is not pushed fo SAR::$_models[tableName], SAR::$_models[tableName] is null, SAR::$_models[tableName] is not used.
* 
* 
*/
class SmartAR extends CActiveRecord implements IApplicationComponent
{
	
	const USE_STATIC_MODEL = 0;
	
	private $_initialized = false;
	
	/**
	* Static models
	* 
	* @var array
	*/
	protected static $_models;	
	
	/**
	* Table name stores here intermediately after SmartAR::model() and before first call of SmartAR::tableName
	* 
	* @var string
	*/
	protected static $_lastTableName;
	
	/**
	* Table name
	* 
	* @var string
	*/
	protected $_tableName;
	
	/**
	* list of related object declarations
	* 
	* @var array
	*/
	public $relations = array();
	public $scopes = array();
	public $rules = array();
	public $behaviors = array();
	public $attributeLabels = array();
	private $_setOnlySafeAttributes = false;
	
	/**
	* Returns the static model (instance of SmartAR) for specified DB table.		
	* @param string $tableName db table name
	* @return SmartAR active record model instance.
	*/
	public static function model($tableName)
	{		        
		/** @todo replace self::* to static::* when we will use only PHP 5.3+ that have a support of LSB */
		if(!isset(self::$_models[$tableName])) {
			self::create($tableName,true);
		}
		return self::$_models[$tableName];
	}
	
	/**
	* Factory method that creates model.
	* To prevent metadata refreshing use static model instead of this method if it is possible.
	* This method can be useful when you use named scopes, have alot of work with AR DB criteria, etc.
	* 
	* @todo test it
	* 
	* @param string $tableName
	* @return SmartAR
	*/
	public static function create($config, $useAsStaticModel=false)
	{
		if (is_string($config)) {
			$model = new SmartAR(null);
			$model->setTableName($config);	
		} else {
			$model = Yii::createComponent($config,null);
		}	
		
		if (!$useAsStaticModel) {
			$model->useStaticModelOptions();
		} else {
			self::$_models[$config] = $model;//@todo!!!
		}					
		
		$model->init();
		return $model;
	}
	
	/**
	* Deny instantiating not via SmartAR::create
	* 
	* @param mixed $scenario
	* @return SmartAR
	*/
	public function __construct($scenario = 'insert') {
		if ($scenario!==null) {
			throw new CException('SmartAR class don\'t allow instantiating objects,  you must use SmartAR::create("<tableName|config>") fabric method instead of it.');
		}
	}
	
	public function setTableName($tableName)
	{
	   $this->_tableName  = self::$_lastTableName = $tableName;
	   $this->refreshMetaData();
	   self::$_lastTableName = null;
	}
	
		
	
	/**
	* Options from static model will used instead of instance options
	* @return SmartAR
	* 
	*/
	protected function useStaticModelOptions()
	{
		$this->relations 
		= $this->scopes 
		= $this->rules 
		= $this->behaviors
		= $this->attributeLabels 
		= $this->_setOnlySafeAttributes = self::USE_STATIC_MODEL;
		return $this;		
	}
	
	
	/**
	* Configure model instance options.
	* It's most convenient way of configuring static models (simple model instace we can configure when creating)
	* 
	* @param array $config
	* @return SmartAR
	*/
	public function configure($config)
	{		
		if (isset($config['class'])) {
			unset($config['class']);
		}
			
		foreach ($config as $field=>$value) {
			$this->$field = $value;
		}
		
		return $this;
	}
	  
	/**
	* Mark all table columns as safe attributes by default.
	* @return array list of safe attributes (equal to table columns).
	* @todo do something with it, coz it's security fail
	*/
	public function getSafeAttributeNames()
	{
		if ($this->setOnlySafeAttributes) {
			return parent::getSafeAttributeNames();
		} else {
			return array_keys($this->getMetaData()->columns);	
		}
	}
	
	public function setAttributes($values, $safeOnly=null)
	{
		 if ($safeOnly === null) {
			 $safeOnly = $this->setOnlySafeAttributes;
		 }
		 return parent::setAttributes($values, $safeOnly);
	}
	
	public function setSetOnlySafeAttributes($value) 
	{
		$this->_setOnlySafeAttributes = $value;
	}
	
 	public function getSetOnlySafeAttributes() 
 	{
		if ($this->_setOnlySafeAttributes === self::USE_STATIC_MODEL) {
			return self::model($this->tableName())->setOnlySafeAttributes;
		}
		return $this->_setOnlySafeAttributes;
	}
	
	public function init()
	{								
		$this->attachBehaviors($this->behaviors());
		$this->afterConstruct();
		$this->_initialized = true;
	}
	
	public function getIsInitialized()
	{
		return $this->_initialized;
	}
	
	/**
	* @return name of DB table that is associated with this instance of SmartAR.
	*/
	public function tableName()
	{			
		if ($this->_tableName) 		return $this->_tableName;	   	
		if (self::$_lastTableName) 	return self::$_lastTableName;
		else throw new CException('Table name not specified.');
	}	
	
	public function relations()
	{
		if ($this->relations === self::USE_STATIC_MODEL) {
			return self::model($this->tableName())->relations;
		}
		return $this->relations;
	}
	
	public function scopes()
	{
		if ($this->scopes === self::USE_STATIC_MODEL) {
			return self::model($this->tableName())->scopes;
		}
		return $this->scopes;
	}
	
	public function rules()
	{
		if ($this->rules === self::USE_STATIC_MODEL) {
			return self::model($this->tableName())->rules;
		}
		return $this->rules;
	}
	
	public function behaviors()
	{
		if ($this->behaviors === self::USE_STATIC_MODEL) {
			return self::model($this->tableName())->behaviors;
		}		
		return $this->behaviors;
	}
	
	public function attributeLabels()
	{
		if ($this->attributeLabels === self::USE_STATIC_MODEL) {
			return self::model($this->tableName())->attributeLabels;
		}	
		return $this->attributeLabels;
	}   
}