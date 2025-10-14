<?php

namespace App\Services;

use App\Models\ExecutiveOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;

class ExecutiveOrderScraperService
{
    private string $baseUrl = 'https://www.whitehouse.gov';
    private string $listUrl = 'https://www.whitehouse.gov/presidential-actions/executive-orders/';
    private int $requestDelay = 1000; // 1 second delay between requests
    
    /**
     * Scrape executive orders from the White House website
     */
    public function scrapeExecutiveOrders(int $maxPages = 10): array
    {
        $stats = [
            'total_found' => 0,
            'new_orders' => 0,
            'updated_orders' => 0,
            'errors' => 0,
            'pages_scraped' => 0,
            'duplicates_skipped' => 0
        ];
        
        // Track processed URLs to avoid duplicates within the same session
        $processedUrls = [];
        
        Log::info('Starting executive orders scraping', ['max_pages' => $maxPages]);
        
        try {
            // Scrape the listing pages
            for ($page = 1; $page <= $maxPages; $page++) {
                $pageUrl = $page === 1 ? $this->listUrl : $this->listUrl . "page/{$page}/";
                
                echo "📄 Scraping page {$page}: {$pageUrl}" . PHP_EOL;
                
                $orderLinks = $this->scrapeListingPage($pageUrl);
                
                if (empty($orderLinks)) {
                    echo "   No more orders found on page {$page}, stopping." . PHP_EOL;
                    break;
                }
                
                $stats['pages_scraped']++;
                
                // Remove duplicates within this page and across pages
                $uniqueOrders = [];
                foreach ($orderLinks as $orderData) {
                    $url = $orderData['url'];
                    if (!in_array($url, $processedUrls)) {
                        $uniqueOrders[] = $orderData;
                        $processedUrls[] = $url;
                    } else {
                        $stats['duplicates_skipped']++;
                    }
                }
                
                $stats['total_found'] += count($uniqueOrders);
                
                echo "   Found " . count($orderLinks) . " entries (" . count($uniqueOrders) . " unique) on page {$page}" . PHP_EOL;
                
                // Process each unique executive order
                foreach ($uniqueOrders as $orderData) {
                    try {
                        $result = $this->processExecutiveOrder($orderData);
                        
                        if ($result['created']) {
                            $stats['new_orders']++;
                            echo "   ✅ Created: {$orderData['title']}" . PHP_EOL;
                        } elseif ($result['updated']) {
                            $stats['updated_orders']++;
                            echo "   🔄 Updated: {$orderData['title']}" . PHP_EOL;
                        } else {
                            echo "   ⏭️  Skipped: {$orderData['title']}" . PHP_EOL;
                        }
                        
                        // Delay between requests to be respectful
                        usleep($this->requestDelay * 1000);
                        
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        Log::error('Error processing executive order', [
                            'order' => $orderData,
                            'error' => $e->getMessage()
                        ]);
                        echo "   ❌ Error processing: {$orderData['title']} - {$e->getMessage()}" . PHP_EOL;
                    }
                }
                
                // Delay between pages
                sleep(2);
            }
            
        } catch (\Exception $e) {
            Log::error('Error in executive orders scraping', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $stats['errors']++;
        }
        
        Log::info('Executive orders scraping completed', $stats);
        
        return $stats;
    }
    
    /**
     * Scrape a listing page to get executive order links
     */
    private function scrapeListingPage(string $url): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; GovTrack Executive Orders Scraper)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($url);
            
            if (!$response->successful()) {
                Log::warning('Failed to fetch listing page', [
                    'url' => $url,
                    'status' => $response->status()
                ]);
                return [];
            }
            
            return $this->parseListingPage($response->body());
            
        } catch (\Exception $e) {
            Log::error('Error scraping listing page', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Parse the HTML of a listing page to extract executive order data
     */
    private function parseListingPage(string $html): array
    {
        $orders = [];
        $seenUrls = []; // Track URLs within this page to avoid duplicates
        
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Try multiple selectors to find executive order entries
        $selectors = [
            '//article[contains(@class, "post")]',
            '//div[contains(@class, "post")]',
            '//div[contains(@class, "entry")]',
            '//li[contains(@class, "post")]',
            '//div[contains(@class, "item")]'
        ];
        
        foreach ($selectors as $selector) {
            $entries = $xpath->query($selector);
            
            if ($entries->length > 0) {
                foreach ($entries as $entry) {
                    try {
                        // Try multiple ways to find the title and link
                        $titleElement = null;
                        $titleSelectors = [
                            './/h2/a',
                            './/h3/a', 
                            './/h4/a',
                            './/a[contains(@class, "title")]',
                            './/a[contains(@class, "post-title")]',
                            './/a[1]' // First link in the entry
                        ];
                        
                        foreach ($titleSelectors as $titleSelector) {
                            $titleElement = $xpath->query($titleSelector, $entry)->item(0);
                            if ($titleElement) {
                                break;
                            }
                        }
                        
                        if (!$titleElement) {
                            continue;
                        }
                        
                        $title = trim($titleElement->textContent);
                        $relativeUrl = $titleElement->getAttribute('href');
                        
                        if (empty($title) || empty($relativeUrl)) {
                            continue;
                        }
                        
                        $fullUrl = $this->resolveUrl($relativeUrl);
                        
                        // Skip if we've already seen this URL on this page
                        if (in_array($fullUrl, $seenUrls)) {
                            continue;
                        }
                        
                        // Make sure it's an executive order
                        if (!$this->isExecutiveOrder($title, $relativeUrl)) {
                            continue;
                        }
                        
                        $seenUrls[] = $fullUrl;
                        
                        // Extract date if available
                        $dateElement = $xpath->query('.//time | .//span[contains(@class, "date")] | .//div[contains(@class, "date")]', $entry)->item(0);
                        $dateString = $dateElement ? trim($dateElement->textContent) : null;
                        
                        // Extract summary if available
                        $summaryElement = $xpath->query('.//p | .//div[contains(@class, "excerpt")]', $entry)->item(0);
                        $summary = $summaryElement ? trim($summaryElement->textContent) : null;
                        
                        $orders[] = [
                            'title' => $title,
                            'url' => $fullUrl,
                            'date_string' => $dateString,
                            'summary' => $summary
                        ];
                        
                    } catch (\Exception $e) {
                        Log::warning('Error parsing listing entry', [
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }
                
                // If we found entries with this selector, don't try others
                if (count($orders) > 0) {
                    break;
                }
            }
        }
        
        return $orders;
    }
    
    /**
     * Check if this is an executive order based on title and URL
     */
    private function isExecutiveOrder(string $title, string $url): bool
    {
        $title = strtolower($title);
        $url = strtolower($url);
        
        // Check for executive order indicators
        return str_contains($title, 'executive order') || 
               str_contains($url, 'executive-order') ||
               str_contains($url, '/presidential-actions/');
    }
    
    /**
     * Process a single executive order
     */
    private function processExecutiveOrder(array $orderData): array
    {
        $slug = Str::slug($orderData['title']);
        
        // Check if we already have this order
        $existingOrder = ExecutiveOrder::where('slug', $slug)
            ->orWhere('url', $orderData['url'])
            ->first();
        
        $created = false;
        $updated = false;
        
        if ($existingOrder) {
            // Update if needed
            if (!$existingOrder->is_fully_scraped || 
                $existingOrder->last_scraped_at < now()->subDays(7)) {
                
                $this->scrapeFullContent($existingOrder, $orderData);
                $updated = true;
            }
        } else {
            // Create new order
            $executiveOrder = new ExecutiveOrder([
                'title' => $orderData['title'],
                'slug' => $slug,
                'url' => $orderData['url'],
                'signed_date' => $this->parseDate($orderData['date_string']),
                'summary' => $orderData['summary'],
                'status' => 'active'
            ]);
            
            $executiveOrder->save();
            $this->scrapeFullContent($executiveOrder, $orderData);
            $created = true;
        }
        
        return [
            'created' => $created,
            'updated' => $updated
        ];
    }
    
    /**
     * Scrape the full content of an executive order
     */
    private function scrapeFullContent(ExecutiveOrder $order, array $orderData): void
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; GovTrack Executive Orders Scraper)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($order->url);
            
            if (!$response->successful()) {
                $order->update([
                    'scraping_errors' => ['Failed to fetch content: HTTP ' . $response->status()],
                    'last_scraped_at' => now()
                ]);
                return;
            }
            
            $contentData = $this->parseExecutiveOrderPage($response->body());
            
            // Extract order number from title or content
            $orderNumber = $this->extractOrderNumber($order->title, $contentData['content']);
            
            $order->update([
                'order_number' => $orderNumber,
                'content' => $contentData['content'],
                'signed_date' => $contentData['signed_date'] ?: $order->signed_date,
                'topics' => $contentData['topics'],
                'is_fully_scraped' => true,
                'last_scraped_at' => now(),
                'scraping_errors' => null
            ]);
            
        } catch (\Exception $e) {
            $order->update([
                'scraping_errors' => ['Error scraping content: ' . $e->getMessage()],
                'last_scraped_at' => now()
            ]);
            
            Log::error('Error scraping executive order content', [
                'order_id' => $order->id,
                'url' => $order->url,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Parse an executive order page to extract content
     */
    private function parseExecutiveOrderPage(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        $content = '';
        $signedDate = null;
        $topics = [];
        
        // Try to find the main content area
        $contentSelectors = [
            '//div[contains(@class, "entry-content")]',
            '//div[contains(@class, "post-content")]',
            '//div[contains(@class, "content")]',
            '//main//div[contains(@class, "text")]',
            '//article//div'
        ];
        
        foreach ($contentSelectors as $selector) {
            $contentNodes = $xpath->query($selector);
            if ($contentNodes->length > 0) {
                $contentNode = $contentNodes->item(0);
                $content = $this->extractTextContent($contentNode);
                if (strlen($content) > 500) { // Only use if substantial content
                    break;
                }
            }
        }
        
        // Try to extract signed date
        $dateSelectors = [
            '//time[@datetime]',
            '//span[contains(@class, "date")]',
            '//div[contains(@class, "date")]'
        ];
        
        foreach ($dateSelectors as $selector) {
            $dateNodes = $xpath->query($selector);
            if ($dateNodes->length > 0) {
                $dateNode = $dateNodes->item(0);
                $dateString = $dateNode->getAttribute('datetime') ?: $dateNode->textContent;
                $signedDate = $this->parseDate($dateString);
                if ($signedDate) {
                    break;
                }
            }
        }
        
        // Extract topics/tags if available
        $topicNodes = $xpath->query('//div[contains(@class, "tags")]//a | //div[contains(@class, "categories")]//a');
        foreach ($topicNodes as $topicNode) {
            $topic = trim($topicNode->textContent);
            if ($topic) {
                $topics[] = $topic;
            }
        }
        
        return [
            'content' => $content,
            'signed_date' => $signedDate,
            'topics' => array_unique($topics)
        ];
    }
    
    /**
     * Extract clean text content from a DOM node
     */
    private function extractTextContent($node): string
    {
        if (!$node) {
            return '';
        }
        
        // Remove script and style elements
        $xpath = new DOMXPath($node->ownerDocument);
        $scripts = $xpath->query('.//script | .//style', $node);
        foreach ($scripts as $script) {
            $script->parentNode->removeChild($script);
        }
        
        // Get text content and clean it up
        $content = $node->textContent;
        
        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Extract executive order number from title or content
     */
    private function extractOrderNumber(string $title, string $content): ?string
    {
        // Try to extract from title first
        if (preg_match('/Executive Order (\d+)/i', $title, $matches)) {
            return $matches[1];
        }
        
        // Try to extract from content
        if (preg_match('/Executive Order (\d+)/i', $content, $matches)) {
            return $matches[1];
        }
        
        // Try other patterns
        if (preg_match('/E\.O\. (\d+)/i', $title . ' ' . $content, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Parse date string into Carbon instance
     */
    private function parseDate(?string $dateString): ?Carbon
    {
        if (!$dateString) {
            return null;
        }
        
        try {
            // Try various date formats
            $formats = [
                'Y-m-d',
                'Y-m-d\TH:i:s',
                'Y-m-d\TH:i:s\Z',
                'M j, Y',
                'F j, Y',
                'j F Y',
                'm/d/Y',
                'd/m/Y'
            ];
            
            foreach ($formats as $format) {
                try {
                    return Carbon::createFromFormat($format, trim($dateString));
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // Try Carbon's flexible parsing
            return Carbon::parse($dateString);
            
        } catch (\Exception $e) {
            Log::warning('Could not parse date', [
                'date_string' => $dateString,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Resolve relative URL to absolute URL
     */
    private function resolveUrl(string $url): string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }
        
        if (str_starts_with($url, '/')) {
            return $this->baseUrl . $url;
        }
        
        return $this->baseUrl . '/' . $url;
    }
    
    /**
     * Get scraping statistics
     */
    public function getScrapingStats(): array
    {
        return [
            'total_orders' => ExecutiveOrder::count(),
            'fully_scraped' => ExecutiveOrder::where('is_fully_scraped', true)->count(),
            'needs_scraping' => ExecutiveOrder::where('is_fully_scraped', false)->count(),
            'recent_orders' => ExecutiveOrder::where('signed_date', '>=', now()->subDays(30))->count(),
            'current_year' => ExecutiveOrder::whereYear('signed_date', now()->year)->count(),
            'last_scraped' => ExecutiveOrder::max('last_scraped_at'),
        ];
    }
}