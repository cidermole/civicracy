<?php

/**
 * This is the model class for table "tbl_user".
 *
 * The followings are the available columns in table 'tbl_user':
 * @property integer $id
 * @property string $username
 * @property string $password
 * @property string $email
 * @property string $realname
 * @property string $slogan
 * @property integer $active
 * @property string $activationcode
 *
 * The followings are the available model relations:
 *
 * @property Category[] $tblCategories
 *
 * The following scenarios are supported:
 * - insert: used by admin to create a user
 * - update: used by admin to change user's real name, e-mail or send her a new password
 * - anonReg: register a new user anonymously (without being logged in) via registration code
 * - activate: activate user via activation code (from e-mail) after registration
 * - settings: user changing her profile
 */
class User extends CActiveRecord
{
	public $registrationCode;
	public $repeat_password;
	public $old_password;
	public $initialPassword; // stores the password hash
	public $reset_password; // flag for admin if he wants to reset the password

	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return User the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return 'tbl_user';
	}

	/**
	 * Validation rules. For supported scenarios, see class documentation comment above.
	 *
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('username, email, realname', 'required'),
			array('password, repeat_password, old_password, registrationCode', 'default'),
			array('registrationCode', 'required', 'on'=>'anonReg'),
			array('registrationCode', 'validRegistrationCode', 'on'=>'anonReg'),
			array('password', 'required', 'on'=>'activate'),
			array('slogan', 'default'),
			array('reset_password', 'default', 'value'=>false),
			array('password', 'compare', 'compareAttribute'=>'repeat_password', 'on'=>array('settings', 'activate')),
			array('username, password, email, realname', 'length', 'max'=>128),
			array('username, email, realname', 'length', 'max'=>128),
			array('username, realname, email', 'isUniqueAttribute'),
			array('old_password', 'validOldPassword', 'on'=>'settings'),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('username, realname, slogan', 'safe', 'on'=>'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
			'votes' => array(self::HAS_MANY, 'Vote', 'voter_id'),
			'supporters' => array(self::HAS_MANY, 'Vote', 'candidate_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
			'username' => Yii::t('app', 'models.username'),
			'password' => Yii::t('app', 'models.password'),
			'old_password' => Yii::t('app', 'models.old_password'),
			'repeat_password' => Yii::t('app', 'models.repeat_password'),
			'reset_password' => Yii::t('app', 'models.reset_password'),
			'email' => Yii::t('app', 'models.email'),
			'realname' => Yii::t('app', 'models.realname'),
			'slogan' => Yii::t('app', 'models.slogan'),
			'registrationCode' => Yii::t('app', 'models.registrationCode'),
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		// Warning: Please modify the following code to remove attributes that
		// should not be searched.

		$criteria=new CDbCriteria;

		$criteria->compare('username',$this->username,true);
		$criteria->compare('realname',$this->realname,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
		));
	}

	/**
	 * @return CArrayDataProvider providing our weights per category
	 */
	public function getVoteCount()
	{
		$categories = Category::model()->findAll();
		$voteCount = array();

		$users = array();
		$condition = $this->activeUserConditions();
		$userObjects = User::model()->findAll($condition['condition'], $condition['params']);
		foreach($userObjects as $u)
			$users[] = $u->id;

		foreach($categories as $c) {
			$voteCount[] = $this->getVoteCountInCategoryInternal($c, $users);
		}

		return new CArrayDataProvider($voteCount, array(
			'id' => 'vote_count',
			'keyField' => 'categoryName',
		));
	}

	/**
	 * @return VoteCount our weight in a specific category queried
	 */
	public function getVoteCountInCategory($categoryId) {
		$users = array();
		$userObjects = $this->getVotersInCategory($categoryId);
		foreach($userObjects as $u)
			$users[] = $u->id;

		return $this->getVoteCountInCategoryInternal(Category::model()->findByPk($categoryId), $users);
	}

	/**
	 * @param $users array user id array
	 * @return VoteCount our weightproperty_exists(Yii::app()->user, 'isAdmin') &&  in a specific category queried
	 */
	public function getVoteCountInCategoryInternal($category, $users) {
		$votes = Vote::model()->findAllByAttributes(array('category_id' => $category->id));
		$graph = new VoteGraph($users, $votes);
		$weights = $graph->getWeights();

		$entry = new VoteCount;
		$entry->categoryName = $category->name;
		$entry->voteCount = $weights[$this->id];

		return $entry;
	}

	/**
	 * @param $categoryID
	 * @param $addCondition optional condition string to be appended, strictly clean SQL, no OR allowed!, ' AND ' will be prepended
	 * @param $addParams optional condition parameters for the condition string
	 * @return array of users eligible for voting in a specified category.
	 */
	public function getVotersInCategory($categoryID, $addCondition = '', $addParams = array()) {
		$condition = $this->voterEligibilityConditions($categoryID, $addCondition, $addParams);
		return User::model()->findAll($condition['condition'], $condition['params']);
	}

	/**
	 * @param $categoryID
	 * @param $addCondition optional condition string to be appended, strictly clean SQL, no OR allowed!, ' AND ' will be prepended
	 * @param $addParams optional condition parameters for the condition string
	 * @return number of users eligible for voting in a specified category.
	 */
	public function getVoterCountInCategory($categoryID, $addCondition = '', $addParams = array()) {
		$condition = $this->voterEligibilityConditions($categoryID, $addCondition, $addParams);
		return User::model()->count($condition['condition'], $condition['params']);
	}

	/**
	 * Return conditions for User model indicating active users eligible for voting in the specified category.
	 *
	 * @param $categoryID
	 * @param $addCondition optional condition string to be appended, strictly clean SQL, no OR allowed!, ' AND ' will be prepended
	 * @param $addParams optional condition parameters for the condition string
	 * @return Yii conditions for finding users eligible for voting in a specified category.
	 */
	public function voterEligibilityConditions($categoryID, $addCondition = '', $addParams = array()) {
		// later: add condition to check if user is really allowed to vote in the category
		return $this->activeUserConditions($addCondition, $addParams);
	}

	/**
	 * Return conditions for User model indicating active users.
	 *
	 * @param $addCondition optional condition string to be appended, strictly clean SQL, no OR allowed!, ' AND ' will be prepended
	 * @param $addParams optional condition parameters for the condition string
	 *
	 * @return Yii conditions for finding users eligible for voting in general.
	 */
	public function activeUserConditions($addCondition = '', $addParams = array()) {
		// TODO: adminity check
		$cond = ($addCondition != '') ? ' AND ' . $addCondition : '';
		return array('condition' => 'username != :username AND active = :active' . $cond, 'params' => CMap::mergeArray(array('username' => 'admin', 'active' => 1), $addParams));
	}

	/**
	 * @param $categoryID ID of category
	 * @return $ranking array Array of all users in Board or false if there are no votes existent
	 */
	public function getVoteCountInCategoryTotal($categoryID) {
		$userObjects = $this->getVotersInCategory($categoryID);
		$category = Category::model()->findByPk($categoryID);
		$boardsize = $category->boardsize;
		$users = array();
		foreach($userObjects as $u)
			$users[] = $u->id;
		$votes = Vote::model()->findAllByAttributes(array('category_id' => $category->id));
		$graph = new VoteGraph($users, $votes);
		if(count($votes))
		{
			// Getting Boardmembers for percentage-defined boardsize
			$weights = $graph->getWeights();
			$ranking=array();
			$realname=array();
			$email=array();
			$weighttable=array();
			$numberofusers = count($userObjects);
			
			// getting board members
			foreach($weights as $id => $weight)
			{
				$vote=Vote::model()->find('voter_id = :voter_id', array('voter_id'=>$id));
				$boardflag=($vote !== null && $vote->candidate_id == $id);
				// decimal boardsize denotes board members defined by minimum representation percentage
				if($boardsize < 1)
				{
					$minimumweight = $numberofusers * $boardsize;
					$boardflag = $boardflag && ($weight > $minimumweight);
				}
				if($boardflag)
				{
					$ranking[$id]['id'] = $id;
					$ranking[$id]['realname'] = User::model()->findByPk($id)->realname;
					$ranking[$id]['email'] = User::model()->findByPk($id)->email;
					$ranking[$id]['weight'] = $weight;
					$ranking[$id]['slogan'] = User::model()->findByPk($id)->slogan;
					$ranking[$id]['percentUsers'] = round($weight / $numberofusers, 3)*100;
					$ranking[$id]['percentBoard'] = 0;
					$weighttable[$id]=$weight;
				}
			}
			array_multisort($weighttable, SORT_DESC, $ranking);
			// numerical notion of board size: remove the remaining users
			if($boardsize >= 1)
				array_splice($ranking, $boardsize);
			
			$allWeights = 0;
			foreach($ranking as $id)
			{
				$allWeights += $id['weight'];
			}
			
			for($n=0; $n<count($ranking); $n++)
				$ranking[$n]['percentBoard'] = round($ranking[$n]['weight'] / $allWeights, 3)*100;
		}else 
			$ranking=false;
		return $ranking;
	}

	/**
	 * Checks if a passed password is valid for this user.
	 */
	public function validatePassword($password)
	{
		if(empty($password))
			return false;

		// prevent DoS attacks
		if(strlen($password) > 72)
			throw new CException('Passwords must be 72 characters or less');

		$hasher = $this->getPasswordHasher();
		return $hasher->CheckPassword($password, $this->initialPassword);
	}

	/**
	 * Convert the stored user password to a hash that may be safely stored.
	 */
	private function createPasswordHash()
	{
		// prevent DoS attacks
		if(strlen($this->password) > 72)
			throw new CException('Passwords must be 72 characters or less');

		$hasher = $this->getPasswordHasher();
		$hash = $hasher->HashPassword($this->password);
		if(strlen($hash) < 20)
			throw new CException("Internal error in password hashing");
		$this->password = $hash;
	}

	/**
	 * Executed after validation, but before saving. Generates a fresh hash for new passwords.
	 */
	protected function beforeSave()
	{
		// if the user left the pw field empty, he didn't change his password - keep the old one
		if(empty($this->password)) {
			$this->password = $this->initialPassword;
		} else {
			/*
			 * if initialPassword ("old" password from DB) is empty, we are either...
			 *
			 * - registering a new user (user/create via registration code), or
			 * - activating a new user (user/activate via activation code).
			 *
			 * if we are the admin resetting a password, scenario will be 'update'.
			 *
			 * we can't check for a valid password in any of these cases.
			 */
			if(!empty($this->initialPassword) && $this->scenario != 'update' && !$this->validatePassword($this->old_password))
				throw new CException("Internal error in User::beforeSave()"); // we should've validated before
			$this->createPasswordHash();
		}

		return parent::beforeSave();
	}

	/**
	 * Instantiate phpass with our settings
	 */
	private function getPasswordHasher()
	{
		return new PasswordHash(8, false);
	}

	protected function afterFind()
	{
		// don't show the password hash to the user
		$this->initialPassword = $this->password;
		$this->sanitizePassword();

		return parent::afterFind();
	}

	public function sanitizePassword()
	{
		$this->password = '';
		$this->repeat_password = '';
		$this->old_password = '';
	}

	/**
	 * Creates a random password.
	 */
	public function createRandomPassword()
	{
		$this->password = $this->urlFriendlyGibberish(12);
		return $this->password;
	}

	public function createActivationCode()
	{
		$this->activationcode = $this->urlFriendlyGibberish();
		return $this->activationcode;
	}

	private function urlFriendlyGibberish($len = 20)
	{
		$alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$salt = '';

		for($i = 0; $i < $len; $i++)
			$salt .= substr($alphabet, rand(0, strlen($alphabet) - 1), 1);

		return $salt;
	}

	/**
	 * Override the default delete() method to provide deletion via active flag.
	 */
	public function delete()
	{
		$this->active = 0;
		return $this->save();
	}

	/**
	 * Check if a specified attribute is unique (not present in the database yet), and add a model error otherwise.
	 * Used in rules() method.
	 *
	 * @param string $attribute the name of the attribute to be validated
	 * @param array $params options specified in the validation rule
	 */
	public function isUniqueAttribute($attribute, $params)
	{
		$other = $this->find($attribute . '=:val', array(':val' => $this->getAttribute($attribute)));

		// if there is another user with that attribute, and that user is not us, that's a problem
		if($other !== null && $other->id !== $this->id)
			$this->addError($attribute, Yii::t('app', 'models.duplicate', array('{attribute}' => $this->getAttributeLabel($attribute), '{value}' => $this->getAttribute($attribute))));
	}

	/**
	 * When a new password is specified, check if the old one is valid and add a model error otherwise.
	 * Used in rules() method.
	 *
	 * @param string $attribute the name of the attribute to be validated
	 * @param array $params options specified in the validation rule
	 */
	public function validOldPassword($attribute, $params)
	{
		if(!empty($this->password))
			if(!$this->validatePassword($this->old_password))
				$this->addError($attribute, Yii::t('app', 'models.old_password.invalid'));
	}

	/**
	 * Check if specified registrationCode attribute is valid.
	 */
	public function validRegistrationCode($attribute, $params)
	{
		// check registration code from category
		$category = Category::model()->find('active=:active', array('active' => 1));

		if($this->registrationCode !== $category->registrationcode || $category->registrationcode == '')
			$this->addError('registrationCode', Yii::t('app', 'models.registrationCode.invalid'));
	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer category ID
	 */
	public function loadVoteByCategoryId($categoryId)
	{
		$model=Category::model()->with('votes')->find('voter_id=:voter_id AND category_id=:category_id', array(
			':voter_id' => $this->id,
			':category_id' => $categoryId,
		));
		if($model === null || $model->votes === null || count($model->votes) != 1)
			return null;
		return $model->votes[0];
	}
}
