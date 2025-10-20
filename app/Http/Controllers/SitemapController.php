<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\ExecutiveOrder;
use App\Models\Member;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Generate the main sitemap index
     */
    public function index(): Response
    {
        $sitemaps = [
            [
                'loc' => route('sitemap.static'),
                'lastmod' => now()->toISOString(),
            ],
            [
                'loc' => route('sitemap.bills'),
                'lastmod' => Bill::latest('updated_at')->first()?->updated_at?->toISOString() ?? now()->toISOString(),
            ],
            [
                'loc' => route('sitemap.executive-orders'),
                'lastmod' => ExecutiveOrder::latest('updated_at')->first()?->updated_at?->toISOString() ?? now()->toISOString(),
            ],
            [
                'loc' => route('sitemap.members'),
                'lastmod' => Member::latest('updated_at')->first()?->updated_at?->toISOString() ?? now()->toISOString(),
            ],
        ];

        $xml = view('sitemaps.index', compact('sitemaps'))->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Generate static pages sitemap
     */
    public function static(): Response
    {
        $urls = [
            [
                'loc' => route('bills.index'),
                'lastmod' => now()->toISOString(),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ],
            [
                'loc' => route('executive-orders.index'),
                'lastmod' => now()->toISOString(),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ],
            [
                'loc' => route('members.index'),
                'lastmod' => now()->toISOString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
            [
                'loc' => route('chatbot.index'),
                'lastmod' => now()->toISOString(),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ],
        ];

        $xml = view('sitemaps.urlset', compact('urls'))->render();

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Generate bills sitemap
     */
    public function bills(): Response
    {
        // Cache for 1 hour since bills don't change that frequently
        $xml = Cache::remember('sitemap.bills', 3600, function () {
            $bills = Bill::select('congress_id', 'updated_at')
                        ->where('congress', 119) // Current congress
                        ->orderBy('updated_at', 'desc')
                        ->get();

            $urls = $bills->map(function ($bill) {
                return [
                    'loc' => route('bills.show', $bill->congress_id),
                    'lastmod' => $bill->updated_at->toISOString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            })->toArray();

            return view('sitemaps.urlset', compact('urls'))->render();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Generate executive orders sitemap
     */
    public function executiveOrders(): Response
    {
        // Cache for 1 hour
        $xml = Cache::remember('sitemap.executive-orders', 3600, function () {
            $orders = ExecutiveOrder::select('id', 'updated_at')
                                  ->orderBy('updated_at', 'desc')
                                  ->get();

            $urls = $orders->map(function ($order) {
                return [
                    'loc' => route('executive-orders.show', $order->id),
                    'lastmod' => $order->updated_at->toISOString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                ];
            })->toArray();

            return view('sitemaps.urlset', compact('urls'))->render();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Generate members sitemap
     */
    public function members(): Response
    {
        // Cache for 4 hours since members change less frequently
        $xml = Cache::remember('sitemap.members', 14400, function () {
            $members = Member::select('bioguide_id', 'updated_at')
                           ->orderBy('updated_at', 'desc')
                           ->get();

            $urls = $members->map(function ($member) {
                return [
                    'loc' => route('members.show', $member->bioguide_id),
                    'lastmod' => $member->updated_at->toISOString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ];
            })->toArray();

            return view('sitemaps.urlset', compact('urls'))->render();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=14400',
        ]);
    }
}