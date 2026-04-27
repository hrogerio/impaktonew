<?php
// app/Models/BaseModel.php
abstract class BaseModel {
    protected $pdo;
    protected $table;
    protected $fillable = [];
    protected $primaryKey = 'id';
    
    public function __construct() {
        this->pdo = getDatabase(); // Função global para obter a conexão PDO
    }
    
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function findOrFail($id) {
        $result = $this->find($id);
        if (!$result) {
            throw new Exception("Registro não encontrado");
        }
        return $result;
    }
    
    public function create(array $data) {
        $data = $this->filterFillable($data);
        $fields = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        if ($stmt->execute()) {
            return $this->find($this->pdo->lastInsertId());
        }
        
        throw new Exception("Erro ao criar registro");
    }
    
    public function update($id, array $data) {
        $data = $this->filterFillable($data);
        $fields = [];
        
        foreach (array_keys($data) as $field) {
            $fields[] = "$field = :$field";
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = :id";
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return $this->find($id);
        }
        
        throw new Exception("Erro ao atualizar registro");
    }
    
    protected function filterFillable(array $data) {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    protected function buildWhereClause(array $filters, array &$params) {
        $conditions = [];
        
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                if (in_array($field, ['busca', 'search'])) {
                    $searchFields = $this->getSearchableFields();
                    $searchConditions = [];
                    
                    foreach ($searchFields as $searchField) {
                        $searchConditions[] = "$searchField LIKE :search";
                    }
                    
                    if (!empty($searchConditions)) {
                        $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
                        $params[':search'] = "%$value%";
                    }
                } else {
                    $conditions[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
            }
        }
        
        return empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
    }
    
    protected function getSearchableFields() {
        return [];
    }
}