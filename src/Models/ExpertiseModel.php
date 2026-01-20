<?php
namespace Models;

use Core\Database;
use PDO;
use PDOException;

class ExpertiseModel {
    private PDO $db;

    public function __construct() {
        $this->db = Database::pdo();
    }

    public function getAllExpertise() {
        $query = "SELECT * FROM expertise";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExpertiseById($id) {
        $query = "SELECT * FROM expertise WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => (int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createExpertise($data) {
        $query = "INSERT INTO expertise (name, description, created_at) VALUES (:name, :description, NOW())";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description']
        ]);
    }

    public function updateExpertise($id, $data) {
        $query = "UPDATE expertise SET name = :name, description = :description, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':id' => (int)$id,
            ':name' => (string)$data['name'],
            ':description' => (string)$data['description']
        ]);
    }

    public function deleteExpertise($id) {
        $query = "DELETE FROM expertise WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => (int)$id]);
    }

    public function createBulkExpertise($expertiseArray) {
        $this->db->beginTransaction();
        try {
            $query = "INSERT INTO expertise (name, description, created_at) VALUES (:name, :description, NOW())";
            $stmt = $this->db->prepare($query);
            
            $successCount = 0;
            foreach ($expertiseArray as $expertise) {
                $result = $stmt->execute([
                    ':name' => (string)($expertise['name'] ?? ''),
                    ':description' => (string)($expertise['description'] ?? '')
                ]);
                if ($result) $successCount++;
            }
            
            $this->db->commit();
            return [
                'total' => count($expertiseArray),
                'successful' => $successCount
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}