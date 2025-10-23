<?php

class Contact
{
    public $name;
    public $phone;
    public $email;
    public $budget;
    
    public function __construct($name, $phone, $email, $budget)
    {
        $this->name = trim($name);
        $this->phone = trim($phone);
        $this->email = trim($email);
        $this->budget = trim($budget);
    }
    
    public function isValid()
    {
        return !empty($this->name) && 
               !empty($this->phone) && 
               !empty($this->email) && 
               !empty($this->budget);
    }
}