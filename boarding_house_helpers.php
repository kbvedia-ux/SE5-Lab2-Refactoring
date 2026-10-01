<?php
require_once 'config.php';
require_once 'admin_helpers.php';

/**
 * Retrieves boarding houses filtered by accreditation status.
 *
 * @param string $status Category filter (e.g., 'Accredited', 'Pending', 'Conditional')
 * @return array
 */
function getFilteredBoardingHouses($status) {
    $db = Database::getInstance()->getConnection();
    
    // Address Reviewer Comment 1: Sanitize input first
    $cleanStatus = sanitizeInput($status);
    $validStatuses = ['Accredited', 'Pending', 'Conditional'];

    if (!in_array($cleanStatus, $validStatuses, true)) {
        return [];
    }

    // Address Reviewer Comment 2: Wrap query execution in a try-catch block
    try {
        $sql = "SELECT id, house_name, address, owner_name, accreditation_status 
                FROM boarding_houses 
                WHERE accreditation_status = :status";
                
        $stmt = $db->prepare($sql);
        $stmt->execute(['status' => $cleanStatus]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getFilteredBoardingHouses: " . $e->getMessage());
        return [];
    }
}
?>