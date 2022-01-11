<?php

class UserRequest extends AbstractRequest
{
    const
        AVATAR_MAX_SIZE = 10000000,
        IS_ACTIVE = 1;

    protected static $tableName = 'user';

    private static $registerFields = [
        'name',
        'login',
        'email',
        'password'
    ];

    private static $updateFields = [
        'name',
        'email',
        'password'
    ];

    public static function findAll()
    {
        if(!CurrentUser::getInstance()->isAdmin()) {
            return ['error' => 'access error'];
        }

        return parent::findAll();
    }

    public function getOwn()
    {
        return DB::queryFirstRow('SELECT name,login,email,money FROM user WHERE token = %s', CurrentUser::getInstance()->getToken());
    }

    public function echoPhotoContent()
    {
        $filePath = CurrentUser::getInstance()->getPhotoPath();

        if(is_readable($filePath)) {
            echo base64_encode(file_get_contents($filePath));
        }
    }

    public function register()
    {
        $validFields = self::$registerFields;

        $params = array_filter($this->params, function($fieldName) use($validFields) {
            return in_array($fieldName, $validFields);
        }, ARRAY_FILTER_USE_KEY);

        if(DB::queryFirstRow('SELECT * FROM user WHERE login = %s', $params['login'])) {
            return ['error' => 'Пользователь с таким логином уже существует'];
        }

        $params['password'] = md5($this->params['password']);
        DB::insert('user', $params);
        $userId = mysqli_insert_id(DB::get());

        $this->savePhoto();

        return DB::queryFirstRow('SELECT name,login,is_active FROM user WHERE id = %i', $userId);
    }

    public function save()
    {
        if(!CurrentUser::getInstance()->isAdmin()) {
            return ['error' => 'access error'];
        }

        return parent::save();
    }


    public function updateOwn()
    {
        $token = CurrentUser::getInstance()->getToken();
        $validFields = static::$updateFields;

        $params = array_filter($this->params, function($fieldName) use($validFields) {
            return in_array($fieldName, $validFields);
        }, ARRAY_FILTER_USE_KEY);

        DB::update('user', $params, 'token = %s', $token);

        $this->savePhoto();

        return DB::queryFirstRow('SELECT name,login,is_active FROM user WHERE token = %s', $token);
    }


    public function login()
    {
        $user = CurrentUser::getInstance()->login($this->params['login'], $this->params['password']);

        if($user) {
            if($user['is_active'] != static::IS_ACTIVE) {
                $result = ['error' => 'Пользователь заблокирован'];
            } else {
                $result = $user;
            }
        } else {
            $result = ['error' => 'Пользователь с такими логином или паролем не найден'];
        }

        return $result;
    }


    public function restorePassword()
    {
        $user = DB::queryFirstRow('SELECT * FROM user WHERE email = %s', $this->params['email']);

        if($user) {
            $newPassword = substr(md5(microtime(true)), 0, 7);
            $passwordHash = md5($newPassword);
            DB::update('user', ['password' => $passwordHash], 'email = %s', $user['email']);
            $user['real_password'] = $newPassword;
            $result = $user;
        } else {
            $result = ['error' => 'Пользователь с таким email не найден'];
        }

        return $result;
    }


    public static function decreaseUserMoney($userId, $moneyAmountToDecrease)
    {
        DB::startTransaction();
        $user = static::getById($userId);
        static::setUserIdMoney($userId, $user['money'] - $moneyAmountToDecrease);
        DB::commit();

        return static::getById($userId);
    }


    public static function increaseUserMoney($userId, $moneyAmountToDecrease)
    {
        DB::startTransaction();
        $user = static::getById($userId);
        static::setUserIdMoney($userId, $user['money'] + $moneyAmountToDecrease);
        DB::commit();

        return static::getById($userId);
    }


    private static function setUserIdMoney($userId, $money)
    {
        DB::update('user', ['money' => $money], 'id = %i', $userId);
    }


    public static function getById($userId)
    {
        return (new UserRequest(['id' => $userId]))->find()[0];
    }


    private function savePhoto()
    {
        if(isset($_FILES['photo'])) {
            $sourceFile = $_FILES['photo']['tmp_name'];

            if(filesize($sourceFile) < self::AVATAR_MAX_SIZE) {
                copy($sourceFile, CurrentUser::getInstance()->getPhotoPath());
            }
        }
    }
}
