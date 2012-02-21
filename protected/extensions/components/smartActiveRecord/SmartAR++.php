<?php
/**
* SmartAR class file
* 
* @author Vitaliy Stepanenko <mail@vitaliy.in>
* @package SmartActiveRecord
* @version $Id: SmartAR++.php 29361 2011-06-14 17:37:04Z stepanenko $
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
	* @var array
	*/
	public $relations		= self::USE_STATIC_MODEL;
	public $scopes			= self::USE_STATIC_MODEL;
	public $rules			= self::USE_STATIC_MODEL;
	public $behaviors		= self::USE_STATIC_MODEL;
	public $attributeLabels	= self::USE_STATIC_MODEL;
	
	private $_setOnlySafeAttributes = self::USE_STATIC_MODEL;
	
	/**
	* Returns the static model (instance of SmartAR) for specified DB table.		
	* @param string $tableName db table name
	* @return SmartAR active record model instance.
	*/
	public static function model($tableName)
	{		        
		/** @todo replace self::* to static::* when we will use only PHP 5.3+ that have a support of LSB */
		if(!isset(self::$_models[$tableName])) {
			self::$_models[$tableName] = self::create($tableName,true);
			self::$_models[$tableName]->initializeDefaultOptions();
		} 
		return self::$_models[$tableName];
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
	
	/**
	* Factory method that creates model.
	* To prevent metadata refreshing use static model instead of this method if it is possible.
	* This method can be useful when you use named scopes, have alot of work with AR DB criteria, 
	* want to create model with some specific params.
	* 
	* This method not provokes creating of static model by default (if $useAsStaticModel argument = false) 
	* 
	* @todo test it
	* 
	* @param string $tableName
	* @return SmartAR
	*/
	public static function create($config, $useAsStaticModel = false)
	{
		if (is_string($config)) {			# Used in SmartAR::model()
			$tableName	= $config;
			$model		= new SmartAR(null);
			$ignoreStaticModel = false;
			
		} else { 							# Can be used to create configured model. 
			if (!isset($config['class'])) {
				$config['class'] = __CLASS__;
			}
			$tableName = $config['tableName'];
			unset($config['tableName']);
			if (isset($config['ignoreStaticModel'])) {
				$ignoreStaticModel = $config['ignoreStaticModel'];
				unset($config['ignoreStaticModel']);
			}
						
			# second argument is passed to CActiveRecord::__construct(null) as scenario to avoid executing of any logics in constructor
			$model = Yii::createComponent($config, null); 
						
		}	
		
		$model->_tableName  = self::$_lastTableName = $tableName;						
		$model->refreshMetaData();
		self::$_lastTableName = null;		
		
	   //	$model->init(false,$ignoreStaticModel);
		return $model;
	}
	
	/**
	* Initialize default options,
	* Call it from static model to clear all options
	* Or call it from non static model instance to avoid overriding model options from static model
	* @return SmartAR
	* 
	*/
	protected function initializeDefaultOptions()
	{
		$this->relations 
		= $this->scopes 
		= $this->rules 
		= $this->behaviors
		= $this->attributeLabels 
		= array();
		$this->_setOnlySafeAttributes = false;
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
		unset($config['tableName']);
		unset($config['class']);
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
		return array_keys($this->getMetaData()->columns);	
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
	/*
	public function setTableName($value) 
	{
		$this->_tableName  = self::$_lastTableName = $value;						
		$this->refreshMetaData();
		self::$_lastTableName = null;		
	}
	*/	
	
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
	
	/**	
	* 
	* @param bool $useAsStaticModel  True by default, coz it will used without params when you use SmartAR as application component, so 
	* when you describe model as application component it always will be a static model for corresponding table.	
	* 
	* @param bool $ignoreStaticModel static model will used for options if TRUE, this param is used only in ::create
	*/
	public function init($useAsStaticModel = true, $ignoreStaticModel = false)
	{
		if ($useAsStaticModel or $ignoreStaticModel) {
			$this->initializeDefaultOptions();
		}	
		
		if ($useAsStaticModel) {
			self::$_models[$this->tableName()] = $this;
		}
		
		$this->attachBehaviors($this->behaviors());
		$this->afterConstruct();
		$this->_initialized = true;
	}
	
	public function getIsInitialized()
	{
		return $this->_initialized;
	}
}