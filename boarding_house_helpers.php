<?php
require_once 'config.php';
require_once 'admin_helpers.php';

function getFilteredBoardingHouses($status) {
    $db = Database::getInstance()->getConnection();
    $validStatuses = ['Accredited', 'Pending', 'Conditional'];

    if (!in_array($status, $validStatuses, true)) {
        return [];
    }

    $sql = "SELECT id, house_name, address, owner_name, accreditation_status 
            FROM boarding_houses 
            WHERE accreditation_status = :status";
            
    $stmt = $db->prepare($sql);
    $stmt->execute(['status' => $status]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>