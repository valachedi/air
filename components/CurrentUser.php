<?php

class CurrentUser
{
    private static $INSTANCE = null;
    private $user;


    public static function getInstance()
    {
        return is_null(self::$INSTANCE)
                ? self::$INSTANCE = new static()
                : self::$INSTANCE;
    }


    private function __construct()
    {
        $headers = getallheaders();

        if(isset($headers['Token'])) {
            if($user = DB::queryFirstRow('SELECT * FROM user WHERE token = %s', $headers['Token'])) {
                $this->user = $user;
            }
        }
    }


    public function isAuthorized()
    {
        return isset($this->user);
    }


    public function isAdmin()
    {
        return $this->isAuthorized() && $this->user['is_admin'] == 1;
    }


    public function getId()
    {
        return $this->isAuthorized() ? $this->user['id'] : null;
    }


    public function getToken()
    {
        return $this->user['token'];
    }


    public function getPhotoPath()
    {
        return __DIR__."/../data/avatars/{$this->getId()}.jpg";
    }


    public function login($login, $password)
    {
        $user = DB::queryFirstRow('SELECT is_active,is_admin,token FROM user WHERE login = %s AND password = %s', $login, md5($password));

        if($user) {
            DB::update('user', [
                'token' => $this->generateToken()
            ], 'login = %s', $login);

            $user = DB::queryFirstRow('SELECT is_active,is_admin,token FROM user WHERE login = %s', $login);
        }

        return $user;
    }


    public function canSpend($moneyWantToSpend)
    {
        return $this->user['money'] >= $moneyWantToSpend;
    }


    public function spendMoney($moneyToSpend)
    {
        if($this->canSpend($moneyToSpend)) {
            UserRequest::decreaseUserMoney($this->getId(), $moneyToSpend);
        }

        return $this;
    }


    private function generateToken()
    {
        return md5(microtime(true));
    }
}
