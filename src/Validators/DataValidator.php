<?php

class DataValidator
{
    public static function validateEmail($email)
    {
        if (empty($email)) {
            return false;
        }
        
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePhone($phone)
    {
        if (empty($phone)) {
            return false;
        }
        
        // Очищаем номер от лишних символов
        $cleanedPhone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Проверяем минимальную длину
        return strlen($cleanedPhone) >= 10;
    }
    
    public static function validateBudget($budget)
    {
        if (empty($budget)) {
            return false;
        }
        
        // Преобразуем бюджет в число
        $budgetValue = floatval(preg_replace('/[^0-9.]/', '', $budget));
        
        return $budgetValue > 0;
    }
    
    public static function validateContact(Contact $contact)
    {
        $errors = [];
        
        if (empty($contact->name)) {
            $errors[] = "Имя не может быть пустым";
        }
        
        if (!self::validatePhone($contact->phone)) {
            $errors[] = "Неверный формат телефона: {$contact->phone}";
        }
        
        if (!self::validateEmail($contact->email)) {
            $errors[] = "Неверный формат email: {$contact->email}";
        }
        
        if (!self::validateBudget($contact->budget)) {
            $errors[] = "Неверный формат бюджета: {$contact->budget}";
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
}