<?php
// hipp.php - Hipocrate Patient Analyzer Interface

// API Configuration
$API_BASE_URL = "http://10.200.8.16:44660";
$API_LOGIN_URL = $API_BASE_URL . "/api/login";
$API_SEARCH_URL = $API_BASE_URL . "/api/patients/search";
$API_PATIENT_URL = $API_BASE_URL . "/api/patients";
$API_ANALYSES_URL = $API_BASE_URL . "/api/analyses";
$API_REPORTS_URL = $API_BASE_URL . "/api/reports";
$API_CHECKOUTS_URL = $API_BASE_URL . "/api/checkouts";

// Initialize variables
$search_term = "";
$patient_data = null;
$analyses_data = null;
$reports_data = [];
$error_message = "";
$success_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_term'])) {
    $search_term = trim($_POST['search_term']);
    
    if (empty($search_term)) {
        $error_message = "Please enter a patient name or CNP.";
    } else {
        // Perform patient search
        $search_result = searchPatient($search_term);
        
        if ($search_result['status'] === 'success') {
            $patient_data = $search_result['data'];
            
            // If we have a patient ID, get their analyses
            if (isset($patient_data['id'])) {
                $analyses_result = getPatientAnalyses($patient_data['id']);
                if ($analyses_result['status'] === 'success') {
                    $analyses_data = $analyses_result;
                    
                    // Get reports for each analysis
                    if (isset($analyses_data['analyses']) && is_array($analyses_data['analyses'])) {
                        foreach ($analyses_data['analyses'] as $analysis) {
                            if (isset($analysis['report_id'])) {
                                $report_result = getAnalysisReport($analysis['report_id']);
                                if ($report_result['status'] === 'success') {
                                    $reports_data[] = $report_result;
                                }
                            }
                        }
                    }
                } else {
                    $error_message = $analyses_result['message'] ?? "Failed to retrieve patient analyses.";
                }
            }
        } else {
            $error_message = $search_result['message'] ?? "Patient not found.";
        }
    }
}

// Function to search for a patient by name or CNP
function searchPatient($search_term) {
    global $API_SEARCH_URL;
    
    $url = $API_SEARCH_URL . "?q=" . urlencode($search_term);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false) {
        return ['status' => 'error', 'message' => 'API connection failed.'];
    }
    
    $data = json_decode($response, true);
    
    if ($http_code === 200) {
        return $data;
    } else {
        return ['status' => 'error', 'message' => $data['message'] ?? 'Search failed.'];
    }
}

// Function to get patient analyses
function getPatientAnalyses($patient_id) {
    global $API_ANALYSES_URL;
    
    $url = $API_ANALYSES_URL . "?id=" . urlencode($patient_id);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false) {
        return ['status' => 'error', 'message' => 'API connection failed.'];
    }
    
    $data = json_decode($response, true);
    
    if ($http_code === 200) {
        return $data;
    } else {
        return ['status' => 'error', 'message' => $data['message'] ?? 'Failed to retrieve analyses.'];
    }
}

// Function to get analysis report
function getAnalysisReport($report_id) {
    global $API_REPORTS_URL;
    
    $url = $API_REPORTS_URL . "?id=" . urlencode($report_id);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response === false) {
        return ['status' => 'error', 'message' => 'API connection failed.'];
    }
    
    $data = json_decode($response, true);
    
    if ($http_code === 200) {
        return $data;
    } else {
        return ['status' => 'error', 'message' => $data['message'] ?? 'Failed to retrieve report.'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hipocrate Patient Analyzer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .search-form {
            margin-bottom: 30px;
            text-align: center;
        }
        .search-input {
            padding: 12px;
            width: 300px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .search-button {
            padding: 12px 24px;
            background-color: #007cba;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            margin-left: 10px;
        }
        .search-button:hover {
            background-color: #005a87;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .patient-info {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        .patient-info h2 {
            margin-top: 0;
            color: #333;
        }
        .reports-section {
            margin-top: 30px;
        }
        .report {
            background-color: #f0f8ff;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #007cba;
        }
        .report h3 {
            margin-top: 0;
            color: #333;
        }
        .report-item {
            margin-bottom: 10px;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hipocrate Patient Analyzer</h1>
        
        <div class="search-form">
            <form method="POST">
                <input 
                    type="text" 
                    name="search_term" 
                    class="search-input" 
                    placeholder="Enter patient name or CNP" 
                    value="<?php echo htmlspecialchars($search_term); ?>"
                    required
                >
                <button type="submit" class="search-button">Search Patient</button>
            </form>
        </div>
        
        <?php if ($error_message): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="message success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($patient_data): ?>
            <div class="patient-info">
                <h2>Patient Information</h2>
                <?php foreach ($patient_data as $key => $value): ?>
                    <div class="report-item">
                        <span class="label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                        <?php if (is_array($value)): ?>
                            <?php echo implode(', ', $value); ?>
                        <?php else: ?>
                            <?php echo htmlspecialchars($value); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($analyses_data && isset($analyses_data['analyses']) && !empty($analyses_data['analyses'])): ?>
            <div class="reports-section">
                <h2>Medical Imaging Reports</h2>
                <?php foreach ($reports_data as $report): ?>
                    <div class="report">
                        <h3>Report Details</h3>
                        <?php if (isset($report['patient_name'])): ?>
                            <div class="report-item">
                                <span class="label">Patient Name:</span>
                                <?php echo htmlspecialchars($report['patient_name']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($report['age'])): ?>
                            <div class="report-item">
                                <span class="label">Age:</span>
                                <?php echo htmlspecialchars($report['age']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($report['gender'])): ?>
                            <div class="report-item">
                                <span class="label">Gender:</span>
                                <?php echo htmlspecialchars($report['gender']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($report['examination'])): ?>
                            <div class="report-item">
                                <span class="label">Examination:</span>
                                <?php echo htmlspecialchars($report['examination']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($report['sample_datetime'])): ?>
                            <div class="report-item">
                                <span class="label">Sample Date:</span>
                                <?php echo htmlspecialchars($report['sample_datetime']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($report['examiner'])): ?>
                            <div class="report-item">
                                <span class="label">Examiner:</span>
                                <?php echo htmlspecialchars($report['examiner']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($report['reports']) && is_array($report['reports'])): ?>
                            <h4>Report Details:</h4>
                            <?php foreach ($report['reports'] as $report_detail): ?>
                                <div class="report-item">
                                    <?php foreach ($report_detail as $key => $value): ?>
                                        <div>
                                            <span class="label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                                            <?php echo htmlspecialchars($value); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($patient_data): ?>
            <div class="message info">
                No medical imaging reports found for this patient.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
