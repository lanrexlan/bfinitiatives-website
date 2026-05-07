<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    exit('Access denied');
}

require_once 'includes/config.php';
require_once 'includes/db.php';

// Get export format from URL parameter
$format = $_GET['format'] ?? 'csv';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get all applications data
    $query = "
        SELECT 
            id,
            application_id,
            program_type,
            first_name,
            last_name,
            email,
            phone,
            status,
            created_at,
            updated_at,
            transcript_file,
            recommendation_file,
            financial_statement_file,
            undergraduate_institution,
            degree_class,
            gpa,
            graduation_year,
            scholarship_statement,
            field_of_study,
            cv_file,
            research_experience,
            financial_statement,
            mentorship_statement,
            mentorship_areas,
            achievements,
            hear_about,
            additional_comments
        FROM scholarship_applications
        ORDER BY created_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($applications)) {
        throw new Exception('No applications found to export');
    }
    
    // Export as CSV (works without any external libraries)
    if ($format === 'xlsx') {
        // Export as CSV but with .xlsx extension for Excel compatibility
        exportAsExcelCompatibleCsv($applications);
    } else {
        exportToCsv($applications);
    }
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    http_response_code(500);
    echo "Error exporting data: " . $e->getMessage();
}

function exportToCsv($applications) {
    $filename = 'scholarship_applications_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV Headers
    $headers = [
        'ID',
        'Application ID',
        'Program Type',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'Status',
        'Application Date',
        'Last Updated',
        'Undergraduate Institution',
        'Degree Class',
        'GPA',
        'Graduation Year',
        'Field of Study',
        'How They Heard About Us',
        'Transcript File',
        'Recommendation File',
        'Financial Statement File',
        'CV File',
        'Scholarship Statement',
        'Research Experience',
        'Financial Statement',
        'Mentorship Statement',
        'Mentorship Areas',
        'Achievements',
        'Additional Comments'
    ];
    
    fputcsv($output, $headers);
    
    // Add data rows
    foreach ($applications as $app) {
        $row = [
            $app['id'],
            $app['application_id'],
            $app['program_type'],
            $app['first_name'],
            $app['last_name'],
            $app['email'],
            $app['phone'],
            ucwords(str_replace('_', ' ', $app['status'])),
            date('Y-m-d H:i:s', strtotime($app['created_at'])),
            date('Y-m-d H:i:s', strtotime($app['updated_at'])),
            $app['undergraduate_institution'] ?? '',
            $app['degree_class'] ?? '',
            $app['gpa'] ?? '',
            $app['graduation_year'] ?? '',
            $app['field_of_study'] ?? '',
            $app['hear_about'] ?? '',
            $app['transcript_file'] ?? '',
            $app['recommendation_file'] ?? '',
            $app['financial_statement_file'] ?? '',
            $app['cv_file'] ?? '',
            cleanTextForCsv($app['scholarship_statement'] ?? ''),
            cleanTextForCsv($app['research_experience'] ?? ''),
            cleanTextForCsv($app['financial_statement'] ?? ''),
            cleanTextForCsv($app['mentorship_statement'] ?? ''),
            formatArrayForCsv($app['mentorship_areas'] ?? null),
            cleanTextForCsv($app['achievements'] ?? ''),
            cleanTextForCsv($app['additional_comments'] ?? '')
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

function exportAsExcelCompatibleCsv($applications) {
    $filename = 'scholarship_applications_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // Set headers to make Excel recognize it as a spreadsheet
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    // Create an HTML table that Excel can read as a spreadsheet
    echo '<?xml version="1.0"?>';
    echo '<ss:Workbook xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    echo '<ss:Worksheet ss:Name="Scholarship Applications">';
    echo '<ss:Table>';
    
    // Headers
    echo '<ss:Row>';
    $headers = [
        'ID', 'Application ID', 'Program Type', 'First Name', 'Last Name', 'Email', 'Phone',
        'Status', 'Application Date', 'Last Updated', 'Undergraduate Institution', 'Degree Class',
        'GPA', 'Graduation Year', 'Field of Study', 'How They Heard About Us', 'Transcript File',
        'Recommendation File', 'Financial Statement File', 'CV File', 'Scholarship Statement',
        'Research Experience', 'Financial Statement', 'Mentorship Statement', 'Mentorship Areas',
        'Achievements', 'Additional Comments'
    ];
    
    foreach ($headers as $header) {
        echo '<ss:Cell><ss:Data ss:Type="String">' . htmlspecialchars($header) . '</ss:Data></ss:Cell>';
    }
    echo '</ss:Row>';
    
    // Data rows
    foreach ($applications as $app) {
        echo '<ss:Row>';
        $row = [
            $app['id'],
            $app['application_id'],
            $app['program_type'],
            $app['first_name'],
            $app['last_name'],
            $app['email'],
            $app['phone'],
            ucwords(str_replace('_', ' ', $app['status'])),
            date('Y-m-d H:i:s', strtotime($app['created_at'])),
            date('Y-m-d H:i:s', strtotime($app['updated_at'])),
            $app['undergraduate_institution'] ?? '',
            $app['degree_class'] ?? '',
            $app['gpa'] ?? '',
            $app['graduation_year'] ?? '',
            $app['field_of_study'] ?? '',
            $app['hear_about'] ?? '',
            $app['transcript_file'] ?? '',
            $app['recommendation_file'] ?? '',
            $app['financial_statement_file'] ?? '',
            $app['cv_file'] ?? '',
            cleanTextForCsv($app['scholarship_statement'] ?? ''),
            cleanTextForCsv($app['research_experience'] ?? ''),
            cleanTextForCsv($app['financial_statement'] ?? ''),
            cleanTextForCsv($app['mentorship_statement'] ?? ''),
            formatArrayForCsv($app['mentorship_areas'] ?? null),
            cleanTextForCsv($app['achievements'] ?? ''),
            cleanTextForCsv($app['additional_comments'] ?? '')
        ];
        
        foreach ($row as $cell) {
            $type = is_numeric($cell) ? 'Number' : 'String';
            echo '<ss:Cell><ss:Data ss:Type="' . $type . '">' . htmlspecialchars($cell) . '</ss:Data></ss:Cell>';
        }
        echo '</ss:Row>';
    }
    
    echo '</ss:Table>';
    echo '</ss:Worksheet>';
    echo '</ss:Workbook>';
    exit();
}

function cleanTextForCsv($text) {
    if (empty($text)) return '';
    
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    $text = str_replace(["\r", "\n"], ' ', $text);
    return trim($text);
}

function formatArrayForCsv($array) {
    if (empty($array) || !is_array($array)) return '';
    
    if (is_string($array)) {
        $array = str_replace(['{', '}'], '', $array);
        $array = explode(',', $array);
        $array = array_map('trim', $array);
    }
    
    return implode('; ', $array);
}
?>