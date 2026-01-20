<?php
namespace Controllers;

use Core\Response;
use Models\ExpertiseModel;
use Exception;

class ExpertiseController {
    private $expertiseModel;

    public function __construct() {
        $this->expertiseModel = new ExpertiseModel();
    }

    public function getAllExpertise() {
        try {
            $expertise = $this->expertiseModel->getAllExpertise();
            return Response::json([
                'success' => true,
                'message' => 'Expertise list retrieved successfully',
                'data' => $expertise
            ]);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'message' => 'Failed to retrieve expertise list'
            ], 500);
        }
    }

    public function getExpertiseById($params) {
        try {
            $id = $params['id'] ?? null;
            $expertise = $this->expertiseModel->getExpertiseById($id);
            if (!$expertise) {
                return Response::json([
                    'success' => false,
                    'message' => 'Expertise not found'
                ], 404);
            }
            return Response::json([
                'success' => true,
                'message' => 'Expertise retrieved successfully',
                'data' => $expertise
            ]);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'message' => 'Failed to retrieve expertise'
            ], 500);
        }
    }

    public function createExpertise() {
        try {
            $data = Response::input();
            
            if (!isset($data['name']) || !isset($data['description'])) {
                return Response::json([
                    'success' => false,
                    'message' => 'Name and description are required'
                ], 400);
            }

            $result = $this->expertiseModel->createExpertise($data);
            if ($result) {
                return Response::json([
                    'success' => true,
                    'message' => 'Expertise created successfully'
                ], 201);
            }
            return Response::json([
                'success' => false,
                'message' => 'Failed to create expertise'
            ], 500);
        } catch (Exception $e) {
            error_log("Error creating expertise: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'Failed to create expertise: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateExpertise($params) {
        try {
            $id = $params['id'] ?? null;
            $data = Response::input();
            
            if (!isset($data['name']) || !isset($data['description'])) {
                return Response::json([
                    'success' => false,
                    'message' => 'Name and description are required'
                ], 400);
            }

            $expertise = $this->expertiseModel->getExpertiseById($id);
            if (!$expertise) {
                return Response::json([
                    'success' => false,
                    'message' => 'Expertise not found'
                ], 404);
            }

            $result = $this->expertiseModel->updateExpertise($id, $data);
            if ($result) {
                return Response::json([
                    'success' => true,
                    'message' => 'Expertise updated successfully'
                ]);
            }
            return Response::json([
                'success' => false,
                'message' => 'Failed to update expertise'
            ], 500);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'message' => 'Failed to update expertise'
            ], 500);
        }
    }

    public function deleteExpertise($params) {
        try {
            $id = $params['id'] ?? null;
            $expertise = $this->expertiseModel->getExpertiseById($id);
            if (!$expertise) {
                return Response::json([
                    'success' => false,
                    'message' => 'Expertise not found'
                ], 404);
            }

            $result = $this->expertiseModel->deleteExpertise($id);
            if ($result) {
                return Response::json([
                    'success' => true,
                    'message' => 'Expertise deleted successfully'
                ]);
            }
            return Response::json([
                'success' => false,
                'message' => 'Failed to delete expertise'
            ], 500);
        } catch (Exception $e) {
            return Response::json([
                'success' => false,
                'message' => 'Failed to delete expertise'
            ], 500);
        }
    }

    public function createBulkExpertise() {
        try {
            $data = Response::input();
            
            if (!isset($data['expertise']) || !is_array($data['expertise']) || empty($data['expertise'])) {
                return Response::json([
                    'success' => false,
                    'message' => 'Expertise array is required and cannot be empty'
                ], 400);
            }

            // Validate each expertise entry
            foreach ($data['expertise'] as $expertise) {
                if (!isset($expertise['name']) || empty(trim($expertise['name']))) {
                    return Response::json([
                        'success' => false,
                        'message' => 'Each expertise must have a name'
                    ], 400);
                }
            }

            $result = $this->expertiseModel->createBulkExpertise($data['expertise']);
            
            return Response::json([
                'success' => true,
                'message' => 'Bulk expertise creation completed',
                'data' => [
                    'total_entries' => $result['total'],
                    'successful_entries' => $result['successful'],
                    'failed_entries' => $result['total'] - $result['successful']
                ]
            ], 201);
            
        } catch (Exception $e) {
            error_log("Error in bulk expertise creation: " . $e->getMessage());
            return Response::json([
                'success' => false,
                'message' => 'Failed to create bulk expertise entries: ' . $e->getMessage()
            ], 500);
        }
    }
}