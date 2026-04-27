<?php
class ValidationService {
    public static function sanitizeString($input, $maxLength = 255) {
        if (!is_string($input)) return '';
        $input = trim($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return substr($input, 0, $maxLength);
    }
    
    public static function validateId($id) {
        return filter_var($id, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
    }
    
    public static function validatePagination($page, $limit) {
        $page = filter_var($page, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'default' => 1]
        ]);
        $limit = filter_var($limit, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100, 'default' => 10]
        ]);
        
        return ['page' => $page, 'limit' => $limit];
    }
    
    public static function validateFilters(array $filters) {
        $allowedFilters = ['situacao', 'regiao', 'tipo', 'cidade', 'busca'];
        $validFilters = [];
        
        foreach ($allowedFilters as $filter) {
            if (isset($filters[$filter]) && !empty($filters[$filter])) {
                $validFilters[$filter] = self::sanitizeString($filters[$filter]);
            }
        }
        
        return $validFilters;
    }
}

