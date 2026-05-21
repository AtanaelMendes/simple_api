<?php

class UserSession
{
    private static $instance = null;
    private $userData;

    private function __construct()
    {
        // Private constructor to prevent direct instantiation
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new UserSession();
        }
        return self::$instance;
    }

    public function setUserData($data)
    {
        $this->userData = $data;
    }

    public function getUserData()
    {
        return $this->userData;
    }

    public function clearSession()
    {
        $this->userData = null;
    }
}