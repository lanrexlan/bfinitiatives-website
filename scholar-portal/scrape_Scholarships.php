<?php
require 'vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;

// Function to scrape scholarships from a specific website
function scrapeScholarships($url) {
    $client = new Client();
    $response = $client->get($url);
    $html = $response->getBody()->getContents();

    $crawler = new Crawler($html);

    $scholarships = [];

    // This is a placeholder. You'll need to customize based on the website's structure
    $crawler->filter('.scholarship-item')->each(function (Crawler $node) use (&$scholarships) {
        $title = $node->filter('h2')->text();
        $description = $node->filter('.description')->text();
        $country = $node->filter('.country')->text();
        $fieldOfStudy = $node->filter('.field-of-study')->text();
        $deadline = $node->filter('.deadline')->text();
        $url = $node->filter('a')->attr('href');

        $scholarships[] = [
            'title' => trim($title),
            'description' => trim($description),
            'country' => trim($country),
            'field_of_study' => trim($fieldOfStudy),
            'deadline' => trim($deadline),
            'url' => trim($url),
            'source' => 'Example Scholarship Website'
        ];
    });

    return $scholarships;
}

// Function to save scholarships to the database
function saveScholarshipsToDatabase($scholarships) {
    require_once 'includes/db.php';
    
    $db = new Database();
    $conn = $db->getConnection();

    foreach ($scholarships as $scholarship) {
        $query = "INSERT INTO scholarships (title, description, country, field_of_study, deadline, url, source) VALUES ($1, $2, $3, $4, $5, $6, $7)";
        $result = pg_query_params($conn, $query, [
            $scholarship['title'],
            $scholarship['description'],
            $scholarship['country'],
            $scholarship['field_of_study'],
            date('Y-m-d', strtotime($scholarship['deadline'])),
            $scholarship['url'],
            $scholarship['source']
        ]);

        if (!$result) {
            error_log("Error inserting scholarship: " . pg_last_error($conn));
        }
    }
}

// Main execution
$urlsToScrape = [
    'https://example-scholarship-website.com/scholarships',
    // Add more URLs as needed
];

foreach ($urlsToScrape as $url) {
    $scholarships = scrapeScholarships($url);
    saveScholarshipsToDatabase($scholarships);
}

echo "Scraping and database update completed.";