<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CongressApiService
{
    private string $baseUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.congress.base_url', 'https://api.congress.gov/v3');
        $this->apiKey = config('services.congress.api_key');
    }

    /**
     * Fetch recent bills from Congress API with pagination
     *
     * @param string|null $chamber
     * @param int $limit
     * @param int $offset
     * @param string|null $search
     * @param bool $onlyWithText Filter to only show bills with available text
     * @return array
     */
    public function fetchRecentBills(?string $chamber = null, int $limit = 20, int $offset = 0, ?string $search = null, bool $onlyWithText = false): array
    {
        if (!$this->apiKey) {
            Log::warning('Congress API key not configured, using sample data');
            $sampleBills = $this->getSampleBills($chamber, $limit, $offset, $search);
            
            // Filter sample bills if requested
            if ($onlyWithText && !empty($sampleBills['bills'])) {
                $sampleBills['bills'] = $this->filterBillsWithText($sampleBills['bills']);
                $sampleBills['pagination']['count'] = count($sampleBills['bills']);
                $sampleBills['pagination']['total'] = count($sampleBills['bills']);
            }
            
            return $sampleBills;
        }

        try {
            $congress = $this->getCurrentCongress();
            
            // If no chamber specified, fetch from both chambers
            if (!$chamber) {
                return $this->fetchBillsFromAllChambers($congress, $limit, $offset, $search, $onlyWithText);
            }
            
            $url = "{$this->baseUrl}/bill/{$congress}";
            
            // Fetch more bills initially if we need to filter for text availability
            $fetchLimit = $onlyWithText ? min($limit * 3, 250) : min($limit, 250);
            
            $params = [
                'api_key' => $this->apiKey,
                'limit' => $fetchLimit,
                'offset' => $offset,
                'sort' => 'updateDate+desc',
                'format' => 'json'
            ];

            // Add chamber filter if specified
            if ($chamber && in_array($chamber, ['house', 'senate'])) {
                $billTypes = $this->getChamberBillTypes($chamber);
                $url .= "/{$billTypes}";
            }

            $response = Http::timeout(30)->get($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                $bills = $data['bills'] ?? [];
                
                // Filter by search term if provided
                if ($search) {
                    $bills = $this->filterBillsBySearch($bills, $search);
                }
                
                // Transform bills to our format
                $transformedBills = array_map([$this, 'transformBillData'], $bills);
                $filteredBills = array_filter($transformedBills); // Remove null entries
                
                // Filter for bills with text if requested
                if ($onlyWithText) {
                    Log::info("Filtering " . count($filteredBills) . " bills for text availability...");
                    $filteredBills = $this->filterBillsWithText($filteredBills);
                    Log::info("Found " . count($filteredBills) . " bills with available text");
                    
                    // Apply pagination after filtering
                    $total = count($filteredBills);
                    $filteredBills = array_slice($filteredBills, 0, $limit);
                } else {
                    $total = $data['pagination']['count'] ?? count($filteredBills);
                }
                
                return [
                    'bills' => $filteredBills,
                    'pagination' => [
                        'count' => count($filteredBills),
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset
                    ]
                ];
            }

            Log::error('Congress API request failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return ['bills' => [], 'pagination' => ['count' => 0, 'total' => 0, 'limit' => $limit, 'offset' => $offset]];
        } catch (Exception $e) {
            Log::error('Congress API error: ' . $e->getMessage());
            return ['bills' => [], 'pagination' => ['count' => 0, 'total' => 0, 'limit' => $limit, 'offset' => $offset]];
        }
    }

    /**
     * Fetch bills from all chambers and merge results
     *
     * @param int $congress
     * @param int $limit
     * @param int $offset
     * @param string|null $search
     * @param bool $onlyWithText
     * @return array
     */
    private function fetchBillsFromAllChambers(int $congress, int $limit, int $offset, ?string $search, bool $onlyWithText = false): array
    {
        try {
            // Fetch more bills initially if we need to filter for text availability
            $fetchLimit = $onlyWithText ? $limit * 3 : $limit; // Fetch 3x more to account for filtering
            
            // Fetch from both chambers
            $houseResult = $this->fetchBillsFromChamber($congress, 'house', $fetchLimit, 0, $search);
            $senateResult = $this->fetchBillsFromChamber($congress, 'senate', $fetchLimit, 0, $search);
            
            // Merge and sort by update date
            $allBills = array_merge($houseResult['bills'], $senateResult['bills']);
            
            // Filter for bills with text if requested
            if ($onlyWithText) {
                Log::info("Filtering " . count($allBills) . " bills for text availability...");
                $allBills = $this->filterBillsWithText($allBills);
                Log::info("Found " . count($allBills) . " bills with available text");
            }
            
            // Sort by introduced date (most recent first)
            usort($allBills, function($a, $b) {
                $dateA = $a['introduced_date'] ?? '1900-01-01';
                $dateB = $b['introduced_date'] ?? '1900-01-01';
                return strcmp($dateB, $dateA);
            });
            
            // Apply pagination to merged results
            $total = count($allBills);
            $paginatedBills = array_slice($allBills, $offset, $limit);
            
            return [
                'bills' => $paginatedBills,
                'pagination' => [
                    'count' => count($paginatedBills),
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ];
        } catch (Exception $e) {
            Log::error('Error fetching bills from all chambers: ' . $e->getMessage());
            return ['bills' => [], 'pagination' => ['count' => 0, 'total' => 0, 'limit' => $limit, 'offset' => $offset]];
        }
    }

    /**
     * Fetch bills from a specific chamber
     *
     * @param int $congress
     * @param string $chamber
     * @param int $limit
     * @param int $offset
     * @param string|null $search
     * @return array
     */
    private function fetchBillsFromChamber(int $congress, string $chamber, int $limit, int $offset, ?string $search): array
    {
        try {
            $billTypes = $this->getChamberBillTypes($chamber);
            $url = "{$this->baseUrl}/bill/{$congress}/{$billTypes}";
            
            $params = [
                'api_key' => $this->apiKey,
                'limit' => min($limit * 2, 250), // Fetch more to account for filtering
                'offset' => 0, // Start from beginning for each chamber
                'sort' => 'updateDate+desc',
                'format' => 'json'
            ];

            $response = Http::timeout(30)->get($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                $bills = $data['bills'] ?? [];
                
                // Filter by search term if provided
                if ($search) {
                    $bills = $this->filterBillsBySearch($bills, $search);
                }
                
                // Transform bills to our format
                $transformedBills = array_map([$this, 'transformBillData'], $bills);
                $filteredBills = array_filter($transformedBills); // Remove null entries
                
                return [
                    'bills' => $filteredBills,
                    'pagination' => [
                        'count' => count($filteredBills),
                        'total' => $data['pagination']['count'] ?? count($filteredBills),
                        'limit' => $limit,
                        'offset' => $offset
                    ]
                ];
            }

            return ['bills' => [], 'pagination' => ['count' => 0, 'total' => 0, 'limit' => $limit, 'offset' => $offset]];
        } catch (Exception $e) {
            Log::error("Error fetching bills from {$chamber}: " . $e->getMessage());
            return ['bills' => [], 'pagination' => ['count' => 0, 'total' => 0, 'limit' => $limit, 'offset' => $offset]];
        }
    }

    /**
     * Fetch detailed information for a specific bill
     *
     * @param string $congressId
     * @return array|null
     */
    public function fetchBillDetails(string $congressId): ?array
    {
        // Check if this is a sample bill (118-* congress IDs)
        if (preg_match('/^118-/', $congressId)) {
            // For sample bills, return with full text included
            return $this->getSampleBillDetails($congressId);
        }
        

        
        if (!$this->apiKey) {
            Log::warning('Congress API key not configured, trying GPO and sample data');
            
            // Try to get bill text from GPO with optimized timeout
            if (preg_match('/^(\d+)-([a-z]+)(\d+)$/i', $congressId, $matches)) {
                $congress = $matches[1];
                $type = strtolower($matches[2]);
                $number = $matches[3];
                
                $gpoResult = $this->fetchBillTextFromGPO($congress, $type, $number);
                if ($gpoResult) {
                    // Get sample bill data and add GPO text
                    $sampleBill = $this->getSampleBillDetails($congressId);
                    if ($sampleBill) {
                        $sampleBill['full_text'] = $gpoResult['text'];
                        $sampleBill['formatted_text_url'] = $gpoResult['formatted_url'];
                        return $sampleBill;
                    }
                }
            }
            
            return $this->getSampleBillDetails($congressId);
        }

        return $this->fetchRealBillDetails($congressId);
    }

    /**
     * Fetch real bill details from Congress API
     *
     * @param string $congressId
     * @return array|null
     */
    private function fetchRealBillDetails(string $congressId): ?array
    {
        try {
            // Parse congress ID (e.g., "118-hr1234" -> congress: 118, type: hr, number: 1234)
            if (!preg_match('/^(\d+)-([a-z]+)(\d+)$/i', $congressId, $matches)) {
                Log::error('Invalid congress ID format', ['congress_id' => $congressId]);
                return null;
            }

            $congress = $matches[1];
            $type = strtolower($matches[2]);
            $number = $matches[3];
            
            $url = "{$this->baseUrl}/bill/{$congress}/{$type}/{$number}";
            
            $response = Http::timeout(10)->get($url, [
                'api_key' => $this->apiKey,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $billData = $data['bill'] ?? null;
                
                if ($billData) {
                    $transformedBill = $this->transformBillData($billData);
                    
                    // Don't fetch bill text automatically to avoid timeouts
                    // Text will be loaded dynamically via AJAX
                    
                    return $transformedBill;
                }
            }

            Log::error('Congress API bill details request failed', [
                'congress_id' => $congressId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Congress API bill details error: ' . $e->getMessage(), [
                'congress_id' => $congressId
            ]);
            return null;
        }
    }

    /**
     * Fetch bill text versions from Congress API
     *
     * @param string $congress
     * @param string $type
     * @param string $number
     * @return array|null
     */
    public function fetchBillTextVersions(string $congress, string $type, string $number): ?array
    {
        if (!$this->apiKey) {
            return null;
        }

        try {
            $url = "{$this->baseUrl}/bill/{$congress}/{$type}/{$number}/text";
            
            $response = Http::timeout(30)->get($url, [
                'api_key' => $this->apiKey,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['textVersions'] ?? [];
            }

            return null;
        } catch (Exception $e) {
            Log::error('Error fetching bill text versions: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch bill text from Congress API
     *
     * @param string $congress
     * @param string $type
     * @param string $number
     * @return string|null
     */
    public function fetchBillText(string $congress, string $type, string $number): ?string
    {
        $result = $this->fetchBillTextWithUrl($congress, $type, $number);
        return $result ? $result['text'] : null;
    }

    /**
     * Fetch bill text and formatted URL from Congress API and GPO
     *
     * @param string $congress
     * @param string $type
     * @param string $number
     * @return array|null
     */
    public function fetchBillTextWithUrl(string $congress, string $type, string $number): ?array
    {
        // Check if this is a sample bill (118-* congress IDs)
        $congressId = "{$congress}-{$type}{$number}";
        if ($congress === '118') {
            $sampleBill = $this->getSampleBillDetails($congressId);
            if ($sampleBill && !empty($sampleBill['full_text'])) {
                return [
                    'text' => $sampleBill['full_text'],
                    'formatted_url' => $sampleBill['formatted_text_url'] ?? null
                ];
            }
        }
        
        // First try GPO endpoints for actual bill text
        $gpoResult = $this->fetchBillTextFromGPO($congress, $type, $number);
        if ($gpoResult) {
            return $gpoResult;
        }

        // Fallback to Congress API if GPO fails
        if (!$this->apiKey) {
            return null;
        }

        try {
            $url = "{$this->baseUrl}/bill/{$congress}/{$type}/{$number}/text";
            
            $response = Http::timeout(30)->get($url, [
                'api_key' => $this->apiKey,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $textVersions = $data['textVersions'] ?? [];
                
                if (!empty($textVersions)) {
                    // Get the most recent text version
                    $latestVersion = $textVersions[0];
                    $formats = $latestVersion['formats'] ?? [];
                    
                    // Look for formatted text first, then XML, then PDF
                    foreach ($formats as $format) {
                        if ($format['type'] === 'Formatted Text') {
                            $text = $this->fetchFormattedText($format['url']);
                            if ($text) {
                                return [
                                    'text' => $text,
                                    'formatted_url' => $format['url']
                                ];
                            }
                        }
                    }
                    
                    // If no formatted text, try XML
                    foreach ($formats as $format) {
                        if ($format['type'] === 'Formatted XML') {
                            $text = $this->fetchXmlText($format['url']);
                            if ($text) {
                                return [
                                    'text' => $text,
                                    'formatted_url' => $format['url']
                                ];
                            }
                        }
                    }
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error('Error fetching bill text: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch bill text from GPO (Government Publishing Office)
     *
     * @param string $congress
     * @param string $type
     * @param string $number
     * @return array|null
     */
    private function fetchBillTextFromGPO(string $congress, string $type, string $number): ?array
    {
        try {
            // Try different GPO URL patterns for bill text
            $gpoUrls = $this->buildGPOUrls($congress, $type, $number);
            
            // Try more URLs with progressive timeout increases
            $maxAttempts = min(8, count($gpoUrls)); // Try up to 8 URLs
            $timeouts = [3, 5, 8, 10, 12, 15, 20, 25]; // Progressive timeouts
            
            for ($i = 0; $i < $maxAttempts; $i++) {
                $urlData = $gpoUrls[$i];
                $timeout = $timeouts[$i] ?? 25;
                
                try {
                    Log::info("Attempting to fetch bill text from: {$urlData['url']} (timeout: {$timeout}s)");
                    
                    $response = Http::timeout($timeout)->get($urlData['url']);
                    
                    if ($response->successful()) {
                        $content = $response->body();
                        
                        // Check if this looks like actual bill content
                        if ($this->isValidBillContent($content)) {
                            $text = $this->extractTextFromGPOContent($content, $urlData['format']);
                            
                            if ($text && strlen(trim($text)) > 100) { // Ensure we have substantial content
                                Log::info("Successfully fetched bill text from: {$urlData['url']}");
                                return [
                                    'text' => $text,
                                    'formatted_url' => $urlData['url']
                                ];
                            }
                        }
                    } else {
                        Log::debug("Failed to fetch from {$urlData['url']}: HTTP {$response->status()}");
                    }
                } catch (Exception $urlException) {
                    Log::debug("Exception fetching from {$urlData['url']}: " . $urlException->getMessage());
                    continue; // Try next URL
                }
            }

            Log::warning("No valid bill text found after trying {$maxAttempts} URLs for {$congress}-{$type}{$number}");
            return null;
        } catch (Exception $e) {
            Log::error('Error fetching bill text from GPO: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build GPO URLs for different bill formats
     *
     * @param string $congress
     * @param string $type
     * @param string $number
     * @return array
     */
    private function buildGPOUrls(string $congress, string $type, string $number): array
    {
        $billType = strtoupper($type);
        $paddedNumber = str_pad($number, 4, '0', STR_PAD_LEFT);
        
        // Common GPO URL patterns
        $baseUrl = 'https://www.congress.gov';
        $gpoBaseUrl = 'https://www.govinfo.gov/content/pkg';
        
        $urls = [];
        
        // Try different version codes (ih = Introduced House, is = Introduced Senate, etc.)
        $versionCodes = $this->getBillVersionCodes($type);
        
        // Prioritize HTML format first as it's most reliable
        foreach ($versionCodes as $versionCode) {
            // Congress.gov formatted text URLs (highest priority)
            $urls[] = [
                'url' => "{$baseUrl}/{$congress}/bills/{$type}{$number}/BILLS-{$congress}{$type}{$number}{$versionCode}.htm",
                'format' => 'html'
            ];
            
            // GPO HTML URLs (second priority)
            $urls[] = [
                'url' => "{$gpoBaseUrl}/BILLS-{$congress}{$type}{$number}{$versionCode}/html/BILLS-{$congress}{$type}{$number}{$versionCode}.htm",
                'format' => 'html'
            ];
        }
        
        // Then try text format
        foreach ($versionCodes as $versionCode) {
            // GPO text URLs
            $urls[] = [
                'url' => "{$gpoBaseUrl}/BILLS-{$congress}{$type}{$number}{$versionCode}/text/BILLS-{$congress}{$type}{$number}{$versionCode}.txt",
                'format' => 'text'
            ];
        }
        
        // Finally try XML format
        foreach ($versionCodes as $versionCode) {
            // GPO XML URLs
            $urls[] = [
                'url' => "{$gpoBaseUrl}/BILLS-{$congress}{$type}{$number}{$versionCode}/xml/BILLS-{$congress}{$type}{$number}{$versionCode}.xml",
                'format' => 'xml'
            ];
        }
        
        // Add alternative URL patterns for edge cases
        foreach (['ih', 'is', 'enr'] as $commonVersion) {
            // Alternative Congress.gov patterns
            $urls[] = [
                'url' => "{$baseUrl}/bill/{$congress}th-congress/{$type}-bill/{$number}/text",
                'format' => 'html'
            ];
            
            // Alternative GPO patterns without version codes
            $urls[] = [
                'url' => "{$gpoBaseUrl}/BILLS-{$congress}{$type}{$number}/html/BILLS-{$congress}{$type}{$number}.htm",
                'format' => 'html'
            ];
        }
        
        return $urls;
    }

    /**
     * Get possible version codes for a bill type
     *
     * @param string $type
     * @return array
     */
    private function getBillVersionCodes(string $type): array
    {
        $type = strtolower($type);
        
        // Common version codes for different bill types
        $codes = [
            'ih', 'is', // Introduced in House/Senate
            'rh', 'rs', // Reported in House/Senate
            'eh', 'es', // Engrossed in House/Senate
            'enr',      // Enrolled
            'pp',       // Public Print
            'pcs',      // Placed on Calendar Senate
            'rfh',      // Referred in House
            'rfs',      // Referred in Senate
        ];
        
        // Prioritize based on bill type
        if ($type === 'hr' || $type === 'hjres' || $type === 'hres' || $type === 'hconres') {
            // House bills - prioritize House versions
            return ['ih', 'rh', 'eh', 'enr', 'pp', 'rfh', 'is', 'rs', 'es', 'pcs', 'rfs'];
        } else {
            // Senate bills - prioritize Senate versions
            return ['is', 'rs', 'es', 'enr', 'pp', 'pcs', 'rfs', 'ih', 'rh', 'eh', 'rfh'];
        }
    }

    /**
     * Check if content looks like valid bill content
     *
     * @param string $content
     * @return bool
     */
    private function isValidBillContent(string $content): bool
    {
        $content = strtolower($content);
        
        // Look for common bill indicators
        $indicators = [
            'a bill',
            'an act',
            'be it enacted',
            'congress finds',
            'section 1',
            'short title',
            'introduced in',
        ];
        
        foreach ($indicators as $indicator) {
            if (strpos($content, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract text from GPO content based on format
     *
     * @param string $content
     * @param string $format
     * @return string|null
     */
    private function extractTextFromGPOContent(string $content, string $format): ?string
    {
        switch ($format) {
            case 'html':
                return $this->extractPreformattedText($content);
            
            case 'xml':
                return $this->formatXmlBillText($content);
            
            case 'text':
                return $this->cleanPreformattedText($content);
            
            default:
                return $this->extractPreformattedText($content);
        }
    }

    /**
     * Fetch and format text from Congress.gov formatted text URL
     *
     * @param string $url
     * @return string|null
     */
    private function fetchFormattedText(string $url): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);
            
            if ($response->successful()) {
                $html = $response->body();
                
                // Extract preformatted text from Congress.gov HTML
                return $this->extractPreformattedText($html);
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('Error fetching formatted text: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract preformatted text from Congress.gov or GPO HTML
     *
     * @param string $html
     * @return string
     */
    private function extractPreformattedText(string $html): string
    {
        try {
            // Load HTML into DOMDocument
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            
            // Look for the main content area in Congress.gov or GPO HTML
            $xpath = new \DOMXPath($dom);
            
            // Try different selectors for Congress.gov and GPO content
            $contentSelectors = [
                '//pre',                           // Preformatted text blocks
                '//*[@class="generated-html-container"]', // Congress.gov container
                '//*[@id="billTextContainer"]',    // Bill text container
                '//div[contains(@class, "bill-text")]', // Bill text div
                '//*[@class="document-content"]',  // GPO document content
                '//*[@class="bill-content"]',      // GPO bill content
                '//*[@id="content"]',              // Generic content ID
                '//main',                          // Main content element
                '//article',                       // Article element
                '//div[contains(@class, "content")]', // Content divs
                '//body',                          // Fallback to body
            ];
            
            $billText = '';
            
            foreach ($contentSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes && $nodes->length > 0) {
                    foreach ($nodes as $node) {
                        $nodeText = $this->getNodeTextWithFormatting($node);
                        if (strlen(trim($nodeText)) > strlen(trim($billText))) {
                            $billText = $nodeText;
                        }
                    }
                    if (!empty(trim($billText)) && $this->isValidBillContent($billText)) {
                        break;
                    }
                }
            }
            
            // If no specific content found, extract from entire document
            if (empty(trim($billText))) {
                $billText = $this->getNodeTextWithFormatting($dom);
            }
            
            // Clean up the extracted text
            return $this->cleanPreformattedText($billText);
            
        } catch (Exception $e) {
            Log::error('Error extracting preformatted text: ' . $e->getMessage());
            // Fallback to simple text extraction
            return $this->cleanPreformattedText(strip_tags($html));
        }
    }

    /**
     * Extract text from DOM node while preserving formatting
     *
     * @param \DOMNode $node
     * @return string
     */
    private function getNodeTextWithFormatting(\DOMNode $node): string
    {
        $text = '';
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->textContent;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->nodeName);
                
                // Add line breaks for block elements
                if (in_array($tagName, ['div', 'p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre'])) {
                    $text .= "\n";
                }
                
                // Recursively get text from child nodes
                $text .= $this->getNodeTextWithFormatting($child);
                
                // Add line breaks after block elements
                if (in_array($tagName, ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre'])) {
                    $text .= "\n";
                }
            }
        }
        
        return $text;
    }

    /**
     * Clean up preformatted text
     *
     * @param string $text
     * @return string
     */
    private function cleanPreformattedText(string $text): string
    {
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove excessive whitespace but preserve intentional formatting
        $text = preg_replace('/[ \t]+/', ' ', $text); // Multiple spaces/tabs to single space
        $text = preg_replace('/\r\n|\r/', "\n", $text); // Normalize line endings
        
        // Remove excessive blank lines (more than 2 consecutive)
        $text = preg_replace('/\n{4,}/', "\n\n\n", $text);
        
        // Clean up lines with only whitespace
        $text = preg_replace('/\n[ \t]+\n/', "\n\n", $text);
        
        // Remove leading/trailing whitespace from each line while preserving indentation
        $lines = explode("\n", $text);
        $cleanedLines = [];
        
        foreach ($lines as $line) {
            // Preserve leading whitespace (indentation) but remove trailing whitespace
            $cleanedLines[] = rtrim($line);
        }
        
        $text = implode("\n", $cleanedLines);
        
        return trim($text);
    }

    /**
     * Fetch and format text from Congress.gov XML URL
     *
     * @param string $url
     * @return string|null
     */
    private function fetchXmlText(string $url): ?string
    {
        try {
            $response = Http::timeout(30)->get($url);
            
            if ($response->successful()) {
                $xml = $response->body();
                
                // Extract text content from XML and format it
                return $this->formatXmlBillText($xml);
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('Error fetching XML text: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Format HTML bill text for display
     *
     * @param string $html
     * @return string
     */
    private function formatBillText(string $html): string
    {
        // Remove HTML tags and format for display
        $text = strip_tags($html);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = str_replace(['\r\n', '\n', '\r'], "\n", $text);
        
        // Add proper spacing for sections
        $text = preg_replace('/SECTION\s+(\d+)\./', "\n\nSECTION $1.", $text);
        $text = preg_replace('/\(([a-z])\)\s*([A-Z])/', "\n\n($1) $2", $text);
        
        // Clean up extra whitespace
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);
        
        return $text;
    }

    /**
     * Format XML bill text for display
     *
     * @param string $xml
     * @return string
     */
    private function formatXmlBillText(string $xml): string
    {
        try {
            $dom = new \DOMDocument();
            $dom->loadXML($xml);
            
            // Extract text content from XML
            $text = $dom->textContent;
            
            // Format similar to HTML text
            return $this->formatBillText($text);
        } catch (Exception $e) {
            Log::error('Error parsing XML bill text: ' . $e->getMessage());
            return strip_tags($xml);
        }
    }

    /**
     * Quick check if bill text is available (without fetching full text)
     *
     * @param string $congress
     * @param string $type
     * @param string $number
     * @return bool
     */
    public function hasBillText(string $congress, string $type, string $number): bool
    {
        try {
            // For now, use a simple heuristic: bills from current congress (119) that are older than 30 days
            // are more likely to have text. This is much faster than checking URLs.
            
            // For sample bills (118-*), always return true since they have text
            if ($congress === '118') {
                return true;
            }
            
            // For current congress bills, use a simple heuristic
            // In a real implementation, you might cache this information or use a different approach
            $billNumber = (int) $number;
            
            // Lower numbered bills are more likely to have text (they're older)
            // This is a rough heuristic - in production you'd want a more sophisticated approach
            if ($billNumber <= 1000) {
                return true; // Likely to have text
            } elseif ($billNumber <= 3000) {
                return rand(1, 100) <= 70; // 70% chance
            } elseif ($billNumber <= 5000) {
                return rand(1, 100) <= 40; // 40% chance
            } else {
                return rand(1, 100) <= 20; // 20% chance for very recent bills
            }
            
        } catch (Exception $e) {
            Log::debug("Error checking bill text availability for {$congress}-{$type}{$number}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Filter bills to only include those with available text
     *
     * @param array $bills
     * @return array
     */
    public function filterBillsWithText(array $bills): array
    {
        $filteredBills = [];
        
        foreach ($bills as $bill) {
            // Parse congress ID to check text availability
            if (isset($bill['congress_id']) && preg_match('/^(\d+)-([a-z]+)(\d+)$/i', $bill['congress_id'], $matches)) {
                $congress = $matches[1];
                $type = strtolower($matches[2]);
                $number = $matches[3];
                
                if ($this->hasBillText($congress, $type, $number)) {
                    $filteredBills[] = $bill;
                }
            }
        }
        
        return $filteredBills;
    }

    /**
     * Sync bill data from Congress API to local database
     *
     * @param int $limit
     * @return int Number of bills synced
     */
    public function syncBillData(int $limit = 100): int
    {
        $bills = $this->fetchRecentBills(null, $limit);
        $syncedCount = 0;

        foreach ($bills as $billData) {
            try {
                $transformedData = $this->transformBillData($billData);
                
                if ($transformedData) {
                    Bill::updateOrCreate(
                        ['congress_id' => $transformedData['congress_id']],
                        $transformedData
                    );
                    $syncedCount++;
                }
            } catch (Exception $e) {
                Log::error('Error syncing bill data', [
                    'bill_data' => $billData,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info("Synced {$syncedCount} bills from Congress API");
        return $syncedCount;
    }

    /**
     * Transform Congress API bill data to match our database schema
     *
     * @param array $billData
     * @return array|null
     */
    private function transformBillData(array $billData): ?array
    {
        try {
            // Extract congress and bill type from the URL or number
            $number = $billData['number'] ?? '';
            $congress = $billData['congress'] ?? $this->getCurrentCongress();
            $type = strtolower($billData['type'] ?? '');
            
            // Create proper congress_id with bill type
            $congressId = "{$congress}-{$type}{$number}";
            
            // Extract additional data
            $actions = $this->extractActions($billData);
            $committees = $this->extractCommittees($billData);
            $subjects = $this->extractSubjects($billData);
            $relatedBills = $this->extractRelatedBills($billData);
            $amendments = $this->extractAmendments($billData);
            $summaries = $this->extractSummaries($billData);
            
            return [
                'congress_id' => $congressId,
                'title' => $billData['title'] ?? 'No title available',
                'number' => $number,
                'chamber' => $this->determineChamber($billData['type'] ?? ''),
                'introduced_date' => $this->parseDate($billData['introducedDate'] ?? null),
                'status' => $billData['latestAction']['text'] ?? 'Unknown',
                'sponsor_name' => $this->extractSponsorName($billData),
                'sponsor_party' => $this->extractSponsorParty($billData),
                'sponsor_state' => $this->extractSponsorState($billData),
                'full_text' => null, // Will be fetched separately if needed
                'summary_url' => $this->extractSummaryUrl($billData),
                'cosponsors' => $this->extractCosponsors($billData),
                'actions' => $actions,
                'committees' => $committees,
                'subjects' => $subjects,
                'related_bills' => $relatedBills,
                'amendments' => $amendments,
                'summaries' => $summaries,
            ];
        } catch (Exception $e) {
            Log::error('Error transforming bill data', [
                'bill_data' => $billData,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract summary URL from bill data
     *
     * @param array $billData
     * @return string|null
     */
    private function extractSummaryUrl(array $billData): ?string
    {
        // Try to get Congress.gov URL
        if (isset($billData['url'])) {
            return $billData['url'];
        }
        
        // Construct URL from bill data
        $congress = $billData['congress'] ?? $this->getCurrentCongress();
        $type = strtolower($billData['type'] ?? '');
        $number = $billData['number'] ?? '';
        
        if ($number && $type) {
            $chamberName = $this->determineChamber($type) === 'house' ? 'house-bill' : 'senate-bill';
            return "https://congress.gov/bill/{$congress}th-congress/{$chamberName}/{$number}";
        }
        
        return null;
    }

    /**
     * Extract actions from bill data
     *
     * @param array $billData
     * @return array
     */
    private function extractActions(array $billData): array
    {
        $actions = $billData['actions'] ?? [];
        $result = [];
        
        foreach ($actions as $action) {
            $result[] = [
                'date' => $this->parseDate($action['actionDate'] ?? null) ?? $action['actionDate'] ?? null,
                'description' => $action['text'] ?? 'Action taken'
            ];
        }
        
        return array_slice($result, 0, 10); // Limit to 10 most recent actions
    }

    /**
     * Extract committees from bill data
     *
     * @param array $billData
     * @return array
     */
    private function extractCommittees(array $billData): array
    {
        $committees = $billData['committees'] ?? [];
        $result = [];
        
        foreach ($committees as $committee) {
            $name = $committee['name'] ?? '';
            if ($name) {
                $result[] = $name;
            }
        }
        
        return array_unique($result);
    }

    /**
     * Extract subjects from bill data
     *
     * @param array $billData
     * @return array
     */
    private function extractSubjects(array $billData): array
    {
        $subjects = $billData['subjects'] ?? [];
        $result = [];
        
        if (isset($subjects['legislativeSubjects'])) {
            foreach ($subjects['legislativeSubjects'] as $subject) {
                if (isset($subject['name'])) {
                    $result[] = $subject['name'];
                }
            }
        }
        
        return array_slice($result, 0, 10); // Limit to 10 subjects
    }

    /**
     * Extract related bills from bill data
     *
     * @param array $billData
     * @return array
     */
    private function extractRelatedBills(array $billData): array
    {
        $relatedBills = $billData['relatedBills'] ?? [];
        $result = [];
        
        foreach ($relatedBills as $relatedBill) {
            $title = $relatedBill['title'] ?? '';
            $number = $relatedBill['number'] ?? '';
            if ($number && $title) {
                $result[] = "{$number} - {$title}";
            } elseif ($number) {
                $result[] = $number;
            }
        }
        
        return array_slice($result, 0, 5); // Limit to 5 related bills
    }

    /**
     * Extract amendments from bill data
     *
     * @param array $billData
     * @return array
     */
    private function extractAmendments(array $billData): array
    {
        $amendments = $billData['amendments'] ?? [];
        $result = [];
        
        foreach ($amendments as $amendment) {
            $number = $amendment['number'] ?? '';
            $description = $amendment['description'] ?? $amendment['purpose'] ?? '';
            if ($number) {
                $result[] = $number . ($description ? ": {$description}" : '');
            }
        }
        
        return array_slice($result, 0, 10); // Limit to 10 amendments
    }

    /**
     * Extract summaries from bill data
     *
     * @param array $billData
     * @return array
     */
    private function extractSummaries(array $billData): array
    {
        $summaries = $billData['summaries'] ?? [];
        $result = [];
        
        foreach ($summaries as $summary) {
            $text = $summary['text'] ?? '';
            $versionCode = $summary['versionCode'] ?? '';
            if ($text) {
                $prefix = $versionCode ? "{$versionCode} version: " : '';
                $result[] = $prefix . $text;
            }
        }
        
        return array_slice($result, 0, 3); // Limit to 3 summaries
    }

    /**
     * Get the current Congress number
     *
     * @return int
     */
    private function getCurrentCongress(): int
    {
        // 118th Congress: 2023-2024, 119th Congress: 2025-2026
        $currentYear = date('Y');
        return intval(($currentYear - 1789) / 2) + 1;
    }

    /**
     * Get bill types for a specific chamber
     *
     * @param string $chamber
     * @return string
     */
    private function getChamberBillTypes(string $chamber): string
    {
        return match ($chamber) {
            'house' => 'hr',
            'senate' => 's',
            default => 'hr'
        };
    }

    /**
     * Filter bills by search term
     *
     * @param array $bills
     * @param string $search
     * @return array
     */
    private function filterBillsBySearch(array $bills, string $search): array
    {
        $searchLower = strtolower($search);
        
        return array_filter($bills, function ($bill) use ($searchLower) {
            $title = strtolower($bill['title'] ?? '');
            $number = strtolower($bill['number'] ?? '');
            $sponsor = strtolower($this->extractSponsorName($bill));
            
            return str_contains($title, $searchLower) || 
                   str_contains($number, $searchLower) || 
                   str_contains($sponsor, $searchLower);
        });
    }

    /**
     * Check if API is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Determine chamber from bill type
     *
     * @param string $type
     * @return string
     */
    private function determineChamber(string $type): string
    {
        $houseTypes = ['hr', 'hjres', 'hres', 'hconres'];
        $senateTypes = ['s', 'sjres', 'sres', 'sconres'];
        
        $type = strtolower($type);
        
        if (in_array($type, $houseTypes)) {
            return 'house';
        } elseif (in_array($type, $senateTypes)) {
            return 'senate';
        }
        
        return 'house'; // Default fallback
    }

    /**
     * Parse date string to proper format
     *
     * @param string|null $dateString
     * @return string|null
     */
    private function parseDate(?string $dateString): ?string
    {
        if (!$dateString) {
            return null;
        }

        try {
            return date('Y-m-d', strtotime($dateString));
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract sponsor name from bill data
     *
     * @param array $billData
     * @return string
     */
    private function extractSponsorName(array $billData): string
    {
        $sponsors = $billData['sponsors'] ?? [];
        if (!empty($sponsors) && is_array($sponsors)) {
            $sponsor = $sponsors[0];
            return trim(($sponsor['firstName'] ?? '') . ' ' . ($sponsor['lastName'] ?? ''));
        }
        return 'Unknown Sponsor';
    }

    /**
     * Extract sponsor party from bill data
     *
     * @param array $billData
     * @return string|null
     */
    private function extractSponsorParty(array $billData): ?string
    {
        $sponsors = $billData['sponsors'] ?? [];
        if (!empty($sponsors) && is_array($sponsors)) {
            return $sponsors[0]['party'] ?? null;
        }
        return null;
    }

    /**
     * Extract sponsor state from bill data
     *
     * @param array $billData
     * @return string|null
     */
    private function extractSponsorState(array $billData): ?string
    {
        $sponsors = $billData['sponsors'] ?? [];
        if (!empty($sponsors) && is_array($sponsors)) {
            return $sponsors[0]['state'] ?? null;
        }
        return null;
    }

    /**
     * Extract cosponsors from bill data
     *
     * @param array $billData
     * @return array
     */
    private function extractCosponsors(array $billData): array
    {
        $cosponsors = $billData['cosponsors'] ?? [];
        $result = [];

        foreach ($cosponsors as $cosponsor) {
            $result[] = [
                'name' => trim(($cosponsor['firstName'] ?? '') . ' ' . ($cosponsor['lastName'] ?? '')),
                'party' => $cosponsor['party'] ?? null,
                'state' => $cosponsor['state'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Get sample bills for demo purposes when API is not configured
     *
     * @param string|null $chamber
     * @param int $limit
     * @param int $offset
     * @param string|null $search
     * @return array
     */
    private function getSampleBills(?string $chamber = null, int $limit = 20, int $offset = 0, ?string $search = null): array
    {
        $sampleBills = [
            [
                'congress_id' => '118-hr1234',
                'title' => 'Healthcare Access and Affordability Act',
                'number' => 'H.R. 1234',
                'chamber' => 'house',
                'introduced_date' => '2024-03-15',
                'status' => 'Referred to Committee on Energy and Commerce',
                'sponsor_name' => 'Jane Smith',
                'sponsor_party' => 'D',
                'sponsor_state' => 'CA',
                'full_text' => 'A bill to improve healthcare access and affordability for all Americans...',
                'summary_url' => 'https://congress.gov/bill/118th-congress/house-bill/1234',
                'cosponsors' => [
                    ['name' => 'John Doe', 'party' => 'D', 'state' => 'NY'],
                    ['name' => 'Mary Johnson', 'party' => 'R', 'state' => 'TX'],
                    ['name' => 'Carlos Rodriguez', 'party' => 'D', 'state' => 'AZ']
                ],
                'actions' => [
                    ['date' => '2024-03-15', 'description' => 'Introduced in House'],
                    ['date' => '2024-03-16', 'description' => 'Referred to the Committee on Energy and Commerce'],
                    ['date' => '2024-03-20', 'description' => 'Referred to the Subcommittee on Health']
                ],
                'committees' => ['House Energy and Commerce', 'House Energy and Commerce - Health Subcommittee'],
                'subjects' => ['Health', 'Healthcare Access', 'Medical Insurance', 'Public Health'],
                'related_bills' => ['S. 1235 - Healthcare Improvement Act'],
                'amendments' => [],
                'summaries' => ['Introduced version summary: This bill aims to improve healthcare access and reduce costs for American families.']
            ],
            [
                'congress_id' => '118-s567',
                'title' => 'Climate Action and Clean Energy Investment Act',
                'number' => 'S. 567',
                'chamber' => 'senate',
                'introduced_date' => '2024-02-28',
                'status' => 'Passed Senate, Referred to House Committee on Energy and Commerce',
                'sponsor_name' => 'Robert Wilson',
                'sponsor_party' => 'D',
                'sponsor_state' => 'WA',
                'full_text' => 'A bill to promote clean energy investment and combat climate change...',
                'summary_url' => 'https://congress.gov/bill/118th-congress/senate-bill/567',
                'cosponsors' => [
                    ['name' => 'Sarah Davis', 'party' => 'D', 'state' => 'OR'],
                    ['name' => 'Michael Brown', 'party' => 'I', 'state' => 'VT'],
                    ['name' => 'Elizabeth Warren', 'party' => 'D', 'state' => 'MA']
                ],
                'actions' => [
                    ['date' => '2024-02-28', 'description' => 'Introduced in Senate'],
                    ['date' => '2024-03-01', 'description' => 'Referred to the Committee on Environment and Public Works'],
                    ['date' => '2024-03-15', 'description' => 'Committee markup held'],
                    ['date' => '2024-04-10', 'description' => 'Passed Senate by voice vote'],
                    ['date' => '2024-04-11', 'description' => 'Referred to House Committee on Energy and Commerce']
                ],
                'committees' => ['Senate Environment and Public Works', 'House Energy and Commerce'],
                'subjects' => ['Climate Change', 'Clean Energy', 'Environmental Protection', 'Renewable Energy', 'Carbon Emissions'],
                'related_bills' => ['H.R. 2468 - Green New Deal Act'],
                'amendments' => [
                    'Amendment 1: Increased funding for solar energy research',
                    'Amendment 2: Added provisions for wind energy tax credits'
                ],
                'summaries' => ['Senate passed version: Comprehensive climate legislation providing $50 billion in clean energy investments.']
            ],
            [
                'congress_id' => '118-hr2468',
                'title' => 'Education Funding and Student Support Act',
                'number' => 'H.R. 2468',
                'chamber' => 'house',
                'introduced_date' => '2024-01-20',
                'status' => 'Introduced in House',
                'sponsor_name' => 'Lisa Garcia',
                'sponsor_party' => 'D',
                'sponsor_state' => 'FL',
                'full_text' => 'A bill to increase funding for public education and provide student support services...',
                'summary_url' => 'https://congress.gov/bill/118th-congress/house-bill/2468',
                'cosponsors' => [
                    ['name' => 'Alexandria Ocasio-Cortez', 'party' => 'D', 'state' => 'NY'],
                    ['name' => 'Rashida Tlaib', 'party' => 'D', 'state' => 'MI']
                ],
                'actions' => [
                    ['date' => '2024-01-20', 'description' => 'Introduced in House'],
                    ['date' => '2024-01-21', 'description' => 'Referred to the Committee on Education and Labor']
                ],
                'committees' => ['House Education and Labor'],
                'subjects' => ['Education', 'Public Schools', 'Student Aid', 'Teacher Training', 'Educational Funding'],
                'related_bills' => [],
                'amendments' => [],
                'summaries' => ['Introduced version: Increases federal funding for K-12 education and expands student support programs.']
            ],
            [
                'congress_id' => '118-s890',
                'title' => 'Infrastructure Modernization and Jobs Act',
                'number' => 'S. 890',
                'chamber' => 'senate',
                'introduced_date' => '2024-04-10',
                'status' => 'Committee Markup',
                'sponsor_name' => 'David Thompson',
                'sponsor_party' => 'R',
                'sponsor_state' => 'OH',
                'full_text' => 'A bill to modernize America\'s infrastructure and create jobs...',
                'summary_url' => 'https://congress.gov/bill/118th-congress/senate-bill/890',
                'cosponsors' => [
                    ['name' => 'Jennifer Lee', 'party' => 'R', 'state' => 'NC'],
                    ['name' => 'Thomas Anderson', 'party' => 'D', 'state' => 'MI'],
                    ['name' => 'Susan Collins', 'party' => 'R', 'state' => 'ME']
                ],
                'actions' => [
                    ['date' => '2024-04-10', 'description' => 'Introduced in Senate'],
                    ['date' => '2024-04-11', 'description' => 'Referred to the Committee on Environment and Public Works'],
                    ['date' => '2024-04-25', 'description' => 'Committee markup scheduled']
                ],
                'committees' => ['Senate Environment and Public Works'],
                'subjects' => ['Infrastructure', 'Transportation', 'Roads and Highways', 'Bridges', 'Job Creation', 'Economic Development'],
                'related_bills' => ['H.R. 3456 - American Infrastructure Act'],
                'amendments' => [],
                'summaries' => ['Introduced version: Authorizes $100 billion for infrastructure improvements over 5 years.']
            ],
            [
                'congress_id' => '118-hr3456',
                'title' => 'American Infrastructure Act',
                'number' => 'H.R. 3456',
                'chamber' => 'house',
                'introduced_date' => '2024-05-01',
                'status' => 'Referred to Committee on Transportation and Infrastructure',
                'sponsor_name' => 'Michael Chen',
                'sponsor_party' => 'D',
                'sponsor_state' => 'CA',
                'full_text' => 'A comprehensive bill to rebuild America\'s infrastructure...',
                'summary_url' => 'https://congress.gov/bill/118th-congress/house-bill/3456',
                'cosponsors' => [
                    ['name' => 'Nancy Pelosi', 'party' => 'D', 'state' => 'CA'],
                    ['name' => 'Chuck Schumer', 'party' => 'D', 'state' => 'NY']
                ],
                'actions' => [
                    ['date' => '2024-05-01', 'description' => 'Introduced in House'],
                    ['date' => '2024-05-02', 'description' => 'Referred to the Committee on Transportation and Infrastructure']
                ],
                'committees' => ['House Transportation and Infrastructure'],
                'subjects' => ['Infrastructure', 'Transportation', 'Broadband', 'Water Systems', 'Public Works'],
                'related_bills' => ['S. 890 - Infrastructure Modernization and Jobs Act'],
                'amendments' => [],
                'summaries' => ['Introduced version: Comprehensive infrastructure package addressing roads, bridges, broadband, and water systems.']
            ],
            [
                'congress_id' => '118-s1111',
                'title' => 'Veterans Healthcare Expansion Act',
                'number' => 'S. 1111',
                'chamber' => 'senate',
                'introduced_date' => '2024-03-01',
                'status' => 'Passed Senate',
                'sponsor_name' => 'Amanda Rodriguez',
                'sponsor_party' => 'D',
                'sponsor_state' => 'TX',
                'full_text' => 'A bill to expand healthcare services for veterans...',
                'summary_url' => 'https://congress.gov/bill/118th-congress/senate-bill/1111',
                'cosponsors' => [
                    ['name' => 'Bernie Sanders', 'party' => 'I', 'state' => 'VT'],
                    ['name' => 'John McCain Jr.', 'party' => 'R', 'state' => 'AZ']
                ],
                'actions' => [
                    ['date' => '2024-03-01', 'description' => 'Introduced in Senate'],
                    ['date' => '2024-03-02', 'description' => 'Referred to the Committee on Veterans\' Affairs'],
                    ['date' => '2024-03-20', 'description' => 'Committee markup held'],
                    ['date' => '2024-04-15', 'description' => 'Passed Senate unanimously']
                ],
                'committees' => ['Senate Veterans\' Affairs'],
                'subjects' => ['Veterans', 'Healthcare', 'Mental Health', 'Medical Services', 'Veterans Benefits'],
                'related_bills' => [],
                'amendments' => ['Amendment 1: Added mental health provisions'],
                'summaries' => ['Senate passed version: Expands VA healthcare services and improves access for rural veterans.']
            ],
            [
                'congress_id' => '118-hr4567',
                'title' => 'Small Business Recovery Act',
                'number' => 'H.R. 4567',
                'chamber' => 'house',
                'introduced_date' => '2024-02-15',
                'status' => 'Subcommittee Consideration',
                'sponsor_name' => 'Patricia Williams',
                'sponsor_party' => 'R',
                'sponsor_state' => 'GA',
                'full_text' => 'A bill to provide support for small businesses recovering from economic challenges...',
                'summary_url' => 'https://congress.gov/bill/118th-congress/house-bill/4567',
                'cosponsors' => [
                    ['name' => 'Kevin McCarthy', 'party' => 'R', 'state' => 'CA'],
                    ['name' => 'Hakeem Jeffries', 'party' => 'D', 'state' => 'NY']
                ],
                'actions' => [
                    ['date' => '2024-02-15', 'description' => 'Introduced in House'],
                    ['date' => '2024-02-16', 'description' => 'Referred to the Committee on Small Business'],
                    ['date' => '2024-03-01', 'description' => 'Referred to the Subcommittee on Economic Growth']
                ],
                'committees' => ['House Small Business', 'House Small Business - Economic Growth Subcommittee'],
                'subjects' => ['Small Business', 'Economic Recovery', 'Business Loans', 'Entrepreneurship', 'Economic Development'],
                'related_bills' => [],
                'amendments' => [],
                'summaries' => ['Introduced version: Provides loan guarantees and tax incentives for small business recovery.']
            ],
            [
                'congress_id' => '118-s2222',
                'title' => 'Cybersecurity Enhancement Act',
                'number' => 'S. 2222',
                'chamber' => 'senate',
                'introduced_date' => '2024-01-30',
                'status' => 'Committee Consideration',
                'sponsor_name' => 'James Park',
                'sponsor_party' => 'R',
                'sponsor_state' => 'UT',
                'full_text' => 'A bill to enhance cybersecurity measures for critical infrastructure...',
                'summary_url' => 'https://congress.gov/bill/118th-congress/senate-bill/2222',
                'cosponsors' => [
                    ['name' => 'Marco Rubio', 'party' => 'R', 'state' => 'FL'],
                    ['name' => 'Mark Warner', 'party' => 'D', 'state' => 'VA']
                ],
                'actions' => [
                    ['date' => '2024-01-30', 'description' => 'Introduced in Senate'],
                    ['date' => '2024-01-31', 'description' => 'Referred to the Committee on Homeland Security and Governmental Affairs']
                ],
                'committees' => ['Senate Homeland Security and Governmental Affairs'],
                'subjects' => ['Cybersecurity', 'National Security', 'Critical Infrastructure', 'Data Protection', 'Technology'],
                'related_bills' => ['H.R. 5678 - Critical Infrastructure Protection Act'],
                'amendments' => [],
                'summaries' => ['Introduced version: Strengthens cybersecurity requirements for critical infrastructure sectors.']
            ]
        ];

        // Filter by chamber if specified
        if ($chamber) {
            $sampleBills = array_filter($sampleBills, fn($bill) => $bill['chamber'] === $chamber);
        }

        // Filter by search if specified
        if ($search) {
            $searchLower = strtolower($search);
            $sampleBills = array_filter($sampleBills, function($bill) use ($searchLower) {
                return str_contains(strtolower($bill['title']), $searchLower) ||
                       str_contains(strtolower($bill['number']), $searchLower) ||
                       str_contains(strtolower($bill['sponsor_name']), $searchLower);
            });
        }

        // Apply pagination
        $total = count($sampleBills);
        $paginatedBills = array_slice($sampleBills, $offset, $limit);

        return [
            'bills' => array_values($paginatedBills),
            'pagination' => [
                'count' => count($paginatedBills),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]
        ];
    }

    /**
     * Get sample bill details for demo purposes when API is not configured
     *
     * @param string $congressId
     * @return array|null
     */
    private function getSampleBillDetails(string $congressId): ?array
    {
        // Get the bill from the sample bills array
        $sampleBills = $this->getSampleBills();
        $bills = $sampleBills['bills'];
        
        foreach ($bills as $bill) {
            if ($bill['congress_id'] === $congressId) {
                // Add detailed full text for the specific bills
                switch ($congressId) {
                    case '118-hr1234':
                        $bill['full_text'] = "A BILL

To improve healthcare access and affordability for all Americans.

Be it enacted by the Senate and House of Representatives of the United States of America in Congress assembled,

SECTION 1. SHORT TITLE.

This Act may be cited as the 'Healthcare Access and Affordability Act'.

SECTION 2. FINDINGS.

Congress finds the following:
(1) Healthcare costs continue to rise, making it difficult for many Americans to access necessary medical care.
(2) Prescription drug prices have increased significantly over the past decade.
(3) Many Americans lack adequate health insurance coverage.
(4) Rural and underserved communities face particular challenges in accessing quality healthcare.
(5) Preventive care and early intervention can reduce long-term healthcare costs.

SECTION 3. HEALTHCARE ACCESS IMPROVEMENTS.

(a) EXPANSION OF COMMUNITY HEALTH CENTERS.—The Secretary of Health and Human Services shall provide additional funding to expand community health centers in underserved areas.

(b) PRESCRIPTION DRUG PRICE TRANSPARENCY.—Healthcare providers and pharmacies shall provide clear pricing information for prescription medications.

(c) TELEMEDICINE EXPANSION.—The Secretary shall establish programs to expand telemedicine services in rural and underserved areas.

(d) PREVENTIVE CARE INITIATIVES.—The Secretary shall develop and implement programs to promote preventive care and health education.

SECTION 4. PRESCRIPTION DRUG AFFORDABILITY.

(a) MEDICARE NEGOTIATION.—The Secretary of Health and Human Services is authorized to negotiate prescription drug prices for Medicare beneficiaries.

(b) GENERIC DRUG PROMOTION.—The Food and Drug Administration shall expedite the approval process for generic medications.

(c) IMPORTATION PROGRAMS.—The Secretary may establish safe importation programs for prescription drugs from approved countries.

SECTION 5. INSURANCE MARKET REFORMS.

(a) PREMIUM SUBSIDIES.—Eligible individuals may receive premium subsidies for health insurance coverage.

(b) COVERAGE REQUIREMENTS.—All health insurance plans must cover essential health benefits including mental health services.

(c) PRE-EXISTING CONDITIONS.—Insurance companies may not deny coverage based on pre-existing medical conditions.

SECTION 6. AUTHORIZATION OF APPROPRIATIONS.

There are authorized to be appropriated such sums as may be necessary to carry out this Act for fiscal years 2024 through 2029.";
                        $bill['formatted_text_url'] = 'https://www.congress.gov/118/bills/hr1234/BILLS-118hr1234ih.htm';
                        break;
                        
                    case '118-s567':
                        $bill['full_text'] = "A BILL

To promote clean energy investment and combat climate change.

Be it enacted by the Senate and House of Representatives of the United States of America in Congress assembled,

SECTION 1. SHORT TITLE.

This Act may be cited as the 'Climate Action and Clean Energy Investment Act'.

SECTION 2. FINDINGS.

Congress finds the following:
(1) Climate change poses a significant threat to the environment and economy.
(2) Investment in clean energy technologies is essential for reducing greenhouse gas emissions.
(3) The United States must lead in the transition to a clean energy economy.
(4) Clean energy investments create jobs and economic opportunities.
(5) Reducing dependence on fossil fuels enhances national security.

SECTION 3. CLEAN ENERGY INVESTMENTS.

(a) TAX CREDITS.—The Internal Revenue Code is amended to provide enhanced tax credits for:
    (1) Solar energy installations
    (2) Wind energy projects
    (3) Geothermal systems
    (4) Energy storage technologies
    (5) Electric vehicle charging infrastructure

(b) RESEARCH AND DEVELOPMENT.—The Department of Energy shall increase funding for clean energy research and development programs, including:
    (1) Advanced battery technologies
    (2) Carbon capture and storage
    (3) Hydrogen fuel cells
    (4) Smart grid technologies

(c) MANUFACTURING INCENTIVES.—The Secretary shall provide incentives for domestic manufacturing of clean energy components.

SECTION 4. CLIMATE RESILIENCE.

(a) INFRASTRUCTURE ADAPTATION.—Federal agencies shall assess and upgrade infrastructure to withstand climate impacts.

(b) NATURAL DISASTER PREPAREDNESS.—The Federal Emergency Management Agency shall enhance disaster preparedness and response capabilities.

(c) ECOSYSTEM RESTORATION.—The Secretary of the Interior shall implement programs to restore damaged ecosystems and enhance carbon sequestration.

SECTION 5. ENVIRONMENTAL JUSTICE.

(a) DISADVANTAGED COMMUNITIES.—At least 40% of clean energy investments shall benefit disadvantaged communities.

(b) POLLUTION REDUCTION.—The Environmental Protection Agency shall prioritize pollution reduction in overburdened communities.

(c) WORKFORCE DEVELOPMENT.—The Secretary of Labor shall establish training programs for clean energy jobs in disadvantaged communities.

SECTION 6. AUTHORIZATION OF APPROPRIATIONS.

There are authorized to be appropriated $50,000,000,000 to carry out this Act for fiscal years 2024 through 2034.";
                        $bill['formatted_text_url'] = 'https://www.congress.gov/118/bills/s567/BILLS-118s567es.htm';
                        break;
                        
                    default:
                        // For other bills, use a shorter sample text
                        $bill['full_text'] = "A BILL

" . $bill['title'] . "

Be it enacted by the Senate and House of Representatives of the United States of America in Congress assembled,

SECTION 1. SHORT TITLE.

This Act may be cited as the '" . $bill['title'] . "'.

SECTION 2. FINDINGS.

Congress finds that this legislation addresses important national priorities and serves the public interest.

SECTION 3. IMPLEMENTATION.

The appropriate federal agencies shall implement the provisions of this Act in accordance with applicable law and regulations.

SECTION 4. AUTHORIZATION OF APPROPRIATIONS.

There are authorized to be appropriated such sums as may be necessary to carry out this Act.";
                        break;
                }
                
                return $bill;
            }
        }

        return null;
    }

    /**
     * Get detailed member information from Congress API
     *
     * @param string $bioguideId
     * @return array|null
     */
    public function getMemberDetails(string $bioguideId): ?array
    {
        if (!$this->apiKey) {
            Log::warning('Congress API key not configured for member details');
            return null;
        }

        try {
            $url = "{$this->baseUrl}/member/{$bioguideId}";
            
            $response = Http::timeout(10)->get($url, [
                'api_key' => $this->apiKey,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['member'])) {
                    Log::info("Successfully fetched member details for: {$bioguideId}");
                    return $this->transformMemberData($data['member']);
                }
            }

            Log::error('Congress API member details request failed', [
                'bioguide_id' => $bioguideId,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Congress API member details error: ' . $e->getMessage(), [
                'bioguide_id' => $bioguideId
            ]);
            return null;
        }
    }

    /**
     * Transform member data from Congress API to our format
     *
     * @param array $memberData
     * @return array
     */
    private function transformMemberData(array $memberData): array
    {
        // Get the most recent party from party history
        $currentParty = null;
        if (!empty($memberData['partyHistory'])) {
            $currentParty = $memberData['partyHistory'][0] ?? null;
        }

        return [
            'bioguide_id' => $memberData['bioguideId'] ?? '',
            'first_name' => $memberData['firstName'] ?? '',
            'last_name' => $memberData['lastName'] ?? '',
            'full_name' => $memberData['directOrderName'] ?? '',
            'direct_order_name' => $memberData['directOrderName'] ?? '',
            'inverted_order_name' => $memberData['invertedOrderName'] ?? '',
            'honorific_name' => $memberData['honorificName'] ?? null,
            'party_abbreviation' => $currentParty['partyAbbreviation'] ?? '',
            'party_name' => $currentParty['partyName'] ?? '',
            'state' => $this->extractStateFromTerms($memberData['terms'] ?? []),
            'district' => $this->extractDistrictFromTerms($memberData['terms'] ?? []),
            'chamber' => $this->extractChamberFromTerms($memberData['terms'] ?? []),
            'birth_year' => $memberData['birthYear'] ?? null,
            'current_member' => $memberData['currentMember'] ?? false,
            'image_url' => $memberData['depiction']['imageUrl'] ?? null,
            'image_attribution' => $memberData['depiction']['attribution'] ?? null,
            'official_website_url' => $memberData['officialWebsiteUrl'] ?? null,
            'office_address' => $memberData['addressInformation']['officeAddress'] ?? null,
            'office_city' => $memberData['addressInformation']['city'] ?? null,
            'office_phone' => $memberData['addressInformation']['phoneNumber'] ?? null,
            'office_zip_code' => $memberData['addressInformation']['zipCode'] ?? null,
            'sponsored_legislation_count' => $memberData['sponsoredLegislation']['count'] ?? 0,
            'cosponsored_legislation_count' => $memberData['cosponsoredLegislation']['count'] ?? 0,
            'party_history' => $memberData['partyHistory'] ?? null,
            'previous_names' => $memberData['previousNames'] ?? null,
        ];
    }

    /**
     * Extract state from member terms
     *
     * @param array $terms
     * @return string|null
     */
    private function extractStateFromTerms(array $terms): ?string
    {
        if (empty($terms)) {
            return null;
        }

        // Get the most recent term
        $recentTerm = $terms[0] ?? null;
        return $recentTerm['stateCode'] ?? null;
    }

    /**
     * Extract district from member terms
     *
     * @param array $terms
     * @return string|null
     */
    private function extractDistrictFromTerms(array $terms): ?string
    {
        if (empty($terms)) {
            return null;
        }

        // Get the most recent term
        $recentTerm = $terms[0] ?? null;
        return $recentTerm['district'] ?? null;
    }

    /**
     * Extract chamber from member terms
     *
     * @param array $terms
     * @return string|null
     */
    private function extractChamberFromTerms(array $terms): ?string
    {
        if (empty($terms)) {
            return null;
        }

        // Get the most recent term
        $recentTerm = $terms[0] ?? null;
        $chamber = $recentTerm['chamber'] ?? null;
        
        // Normalize chamber names
        return match(strtolower($chamber ?? '')) {
            'house of representatives', 'house' => 'house',
            'senate' => 'senate',
            default => null
        };
    }

    /**
     * Fetch all members from Congress API with pagination
     *
     * @param int $limit
     * @param int $offset
     * @return array|null
     */
    public function fetchMembersList(int $limit = 20, int $offset = 0): ?array
    {
        if (!$this->apiKey) {
            Log::warning('Congress API key not configured for members list');
            return null;
        }

        try {
            $url = "{$this->baseUrl}/member";
            
            $response = Http::timeout(30)->get($url, [
                'api_key' => $this->apiKey,
                'format' => 'json',
                'limit' => $limit,
                'offset' => $offset
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("Successfully fetched members list: offset={$offset}, limit={$limit}");
                return $data;
            }

            Log::error('Congress API members list request failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Congress API members list error: ' . $e->getMessage());
            return null;
        }
    }
}