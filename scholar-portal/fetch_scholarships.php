<?php
// Connect to the database
require_once 'includes/db.php';
$db = new Database();
$conn = $db->getConnection();

// RSS feed URLs
$rssFeeds = [
    'Commonwealth Scholarships' => 'https://cscuk.fcdo.gov.uk/scholarships/feed/',
    'Chevening Scholarships' => 'https://www.chevening.org/feed/',
    'Scholars4Dev' => 'scholars4dev.com/feed/',
    'HKU Legal Scholarship Blog' => 'http://researchblog.law.hku.hk/feeds/posts/default?alt=rss',
    'US News Scholarships' => 'https://www.usnews.com/topics/subjects/scholarships/feed/',
    'Scholarships Ads Blog' => 'scholarshipsads.com/blog/feed/'
];

// Function to fetch and parse RSS feed
function fetchScholarshipsFromRSS($url, $source) {
    $rss = simplexml_load_file($url);

    $scholarships = [];

    foreach ($rss->channel->item as $item) {
        $title = (string)$item->title;
        $description = (string)$item->description;
        $link = (string)$item->link;

        // Extract deadline and other details from the description
        $deadline = extractDeadline($description);
        $country = extractCountry($description);
        $field_of_study = extractFieldOfStudy($description);

        $scholarships[] = [
            'title' => $title,
            'description' => $description,
            'country' => $country,
            'field_of_study' => $field_of_study,
            'deadline' => $deadline,
            'url' => $link,
            'source' => $source
        ];
    }

    return $scholarships;
}

// Function to extract deadline from description
function extractDeadline($description) {
    // This is a simple regex to find a date in the format YYYY-MM-DD
    // You may need to adjust this based on the actual format used in the RSS feed
    preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $description, $matches);
    return $matches[0] ?? null;
}

// Function to extract country from description
function extractCountry($description) {
    // This is a simple regex to find country names
    // You may need to adjust this based on the actual format used in the RSS feed
    preg_match('/\b(United Kingdom|UK|Commonwealth)\b/', $description, $matches);
    return $matches[0] ?? 'Unknown';
}

// Function to extract field of study from description
function extractFieldOfStudy($description) {
    // This is a simple regex to find field of study
    // You may need to adjust this based on the actual format used in the RSS feed
    preg_match('/\b(Engineering|Computer Science|Business|Medicine|Arts|Social Sciences)\b/', $description, $matches);
    return $matches[0] ?? 'Unknown';
}

// Fetch and save scholarships
foreach ($rssFeeds as $source => $url) {
    $scholarships = fetchScholarshipsFromRSS($url, $source);

    foreach ($scholarships as $scholarship) {
        $query = "INSERT INTO scholarships (title, description, country, field_of_study, deadline, url, source) VALUES ($1, $2, $3, $4, $5, $6, $7)";
        $result = pg_query_params($conn, $query, [
            $scholarship['title'],
            $scholarship['description'],
            $scholarship['country'],
            $scholarship['field_of_study'],
            $scholarship['deadline'],
            $scholarship['url'],
            $scholarship['source']
        ]);

        if (!$result) {
            error_log("Error inserting scholarship: " . pg_last_error($conn));
        }
    }
}

echo "Scholarships fetched and saved successfully.";