<?php
// hipp.php - Hipocrate Patient Analyzer Interface

// Include common functions
include 'common.php';

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
$checkout_data = null;
$error_message = "";
$success_message = "";

// Check if a specific checkout is requested
$checkout_id = isset($_GET['checkout']) ? trim($_GET['checkout']) : "";

// Check if search term is provided in query string
$query_search_term = isset($_GET['search']) ? trim($_GET['search']) : "";

// Check if this is an API request (no submit button)
$is_api_request = !isset($_POST['submit']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $search_term = trim($_POST['search']);
    
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
        
        // If this is an API request, return JSON response
        if ($is_api_request) {
            header('Content-Type: application/json');
            
            if ($error_message) {
                echo json_encode(['status' => 'error', 'message' => $error_message]);
            } else {
                $response = [
                    'status' => 'success',
                    'patient' => $patient_data,
                    'analyses' => $analyses_data,
                    'reports' => $reports_data
                ];
                echo json_encode($response);
            }
            exit;
        }
    }
}
// Handle search from query string
elseif (!empty($query_search_term)) {
    $search_term = $query_search_term;
    
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
    
    // If this is an API request, return JSON response
    if ($is_api_request && !empty($query_search_term)) {
        header('Content-Type: application/json');
        
        if ($error_message) {
            echo json_encode(['status' => 'error', 'message' => $error_message]);
        } else {
            $response = [
                'status' => 'success',
                'patient' => $patient_data,
                'analyses' => $analyses_data,
                'reports' => $reports_data
            ];
            echo json_encode($response);
        }
        exit;
    }
}

// If a checkout ID is provided, fetch that specific checkout
if (!empty($checkout_id)) {
    $checkout_result = getCheckout($checkout_id);
    if ($checkout_result['status'] === 'success') {
        $checkout_data = $checkout_result;
        
        // If this is an API request, return JSON response
        if ($is_api_request) {
            header('Content-Type: application/json');
            echo json_encode($checkout_result);
            exit;
        }
    } else {
        $error_message = $checkout_result['message'] ?? "Failed to retrieve checkout information.";
        
        // If this is an API request, return JSON error response
        if ($is_api_request) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $error_message]);
            exit;
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

// Function to get checkout information
function getCheckout($checkout_id) {
    global $API_CHECKOUTS_URL;
    
    $url = $API_CHECKOUTS_URL . "?id=" . urlencode($checkout_id);
    
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
        return ['status' => 'error', 'message' => $data['message'] ?? 'Failed to retrieve checkout.'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hipocrate Patient Analyzer</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 Hipocrate Patient Analyzer</h1>
            <p>Search patient information and medical imaging reports</p>
        </div>

        <div class="content">
            <?php if ($checkout_data): ?>
                <!-- Display specific checkout information -->
                <div class="result-card">
                    <div class="result-header">
                        <h2 style="color: #111827; font-size: 20px;">Checkout Information</h2>
                        <a href="hipp.php" class="btn btn-secondary" style="margin-left: 10px;">← Back to Search</a>
                    </div>
                    
                    <div class="summary-box">
                        <?php if (isset($checkout_data['patient_name'])): ?>
                            <div class="report-item">
                                <span class="label">Patient Name:</span>
                                <?php echo htmlspecialchars($checkout_data['patient_name']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($checkout_data['patient_id'])): ?>
                            <div class="report-item">
                                <span class="label">Patient ID:</span>
                                <?php echo htmlspecialchars($checkout_data['patient_id']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($checkout_data['patient_code'])): ?>
                            <div class="report-item">
                                <span class="label">Patient Code:</span>
                                <?php echo htmlspecialchars($checkout_data['patient_code']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($checkout_data['admission_diagnostic'])): ?>
                            <div class="report-item">
                                <span class="label">Admission Diagnostic:</span>
                                <?php echo htmlspecialchars($checkout_data['admission_diagnostic']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($checkout_data['epicrisis'])): ?>
                            <div class="report-item">
                                <span class="label">Epicrisis:</span>
                                <div style="margin-top: 5px; padding: 10px; background-color: #f0f8ff; border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($checkout_data['epicrisis'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($checkout_data['diagnostic'])): ?>
                            <div class="report-item">
                                <span class="label">Diagnostic:</span>
                                <?php echo htmlspecialchars($checkout_data['diagnostic']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($checkout_data['surgery'])): ?>
                            <div class="report-item">
                                <span class="label">Surgery:</span>
                                <?php echo htmlspecialchars($checkout_data['surgery']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($checkout_data['recommendations'])): ?>
                            <div class="report-item">
                                <span class="label">Recommendations:</span>
                                <div style="margin-top: 5px; padding: 10px; background-color: #f0f8ff; border-radius: 4px;">
                                    <?php echo nl2br(htmlspecialchars($checkout_data['recommendations'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Display search form -->
                <form method="POST" action="">
                    <input type="hidden" name="submit" value="1">
                    <?php if ($error_message): ?>
                        <div class="error">
                            <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success_message): ?>
                        <div class="success">
                            <strong>✅ Success:</strong> <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="search">Patient Search:</label>
                        <input 
                            type="text" 
                            id="search"
                            name="search" 
                            required
                            placeholder="Enter patient name or CNP" 
                            value="<?php echo htmlspecialchars($search_term); ?>"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        🔍 Search Patient
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="clearForm()">
                        🔄 New Search
                    </button>
                </form>
                
                <?php if ($patient_data): ?>
                    <div class="result-card">
                        <div class="result-header">
                            <h2 style="color: #111827; font-size: 20px;">Patient Information</h2>
                        </div>
                        
                        <div class="summary-box">
                            <?php foreach ($patient_data as $key => $value): ?>
                                <div class="report-item">
                                    <span class="label"><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</span>
                                    <?php if (is_array($value)): ?>
                                        <?php if ($key === 'checkout_ids'): ?>
                                            <?php foreach ($value as $id): ?>
                                                <a href="?checkout=<?php echo urlencode($id); ?>" class="btn btn-secondary" style="display: inline-block; margin: 2px; padding: 4px 8px; font-size: 12px;">
                                                    <?php echo htmlspecialchars($id); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <?php echo implode(', ', $value); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($value); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($patient_data['checkout_ids']) && is_array($patient_data['checkout_ids']) && !empty($patient_data['checkout_ids'])): ?>
                    <div class="result-card">
                        <div class="result-header">
                            <h2 style="color: #111827; font-size: 20px;">Patient Checkouts</h2>
                        </div>
                        
                        <div class="summary-box">
                            <?php foreach ($patient_data['checkout_ids'] as $id): ?>
                                <div class="report-item">
                                    <a href="?checkout=<?php echo urlencode($id); ?>" class="btn btn-secondary" style="display: inline-block; margin: 5px 0;">
                                        View Checkout #<?php echo htmlspecialchars($id); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($analyses_data && isset($analyses_data['analyses']) && !empty($analyses_data['analyses'])): ?>
                    <div class="result-card">
                        <div class="result-header">
                            <h2 style="color: #111827; font-size: 20px;">Medical Imaging Reports</h2>
                        </div>
                        
                        <?php foreach ($reports_data as $report): ?>
                            <div class="summary-box">
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
                    <div class="result-card">
                        <div class="summary-box">
                            <p>No medical imaging reports found for this patient.</p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function clearForm() {
            document.getElementById('search').value = '';
            // Reload page to clear results
            window.location.href = window.location.pathname;
        }
    </script>
</body>
</html>
