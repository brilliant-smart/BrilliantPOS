<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function stats(Request $request)
    {
        // Get date range from request (default to last 30 days)
        $days = $request->input('days', 30);
        $startDate = now()->subDays($days);
        $previousStartDate = now()->subDays($days * 2);
        $previousEndDate = $startDate->copy();

        // Current period stats
        $totalProducts = Product::count();
        $activeProducts = Product::where('is_active', true)->count();
        $inactiveProducts = Product::where('is_active', false)->count();
        $featuredProducts = Product::where('is_featured', true)->count();
        $recentlyAdded = Product::where('created_at', '>=', $startDate)->count();

        // Previous period stats for comparison
        $previousProducts = Product::where('created_at', '>=', $previousStartDate)
            ->where('created_at', '<', $previousEndDate)
            ->count();

        // Calculate trends
        $productTrend = $previousProducts > 0
            ? round((($recentlyAdded - $previousProducts) / $previousProducts) * 100, 1)
            : ($recentlyAdded > 0 ? 100 : 0);

        // User stats (exclude anonymized/deleted users)
        $totalUsers = User::where('name', '!=', 'Deleted User')->count();
        $activeUsers = User::where('name', '!=', 'Deleted User')->where('is_active', true)->count();
        $inactiveUsers = User::where('name', '!=', 'Deleted User')->where('is_active', false)->count();

        // User role breakdown
        $owners = User::where('name', '!=', 'Deleted User')->where('role', 'owner')->count();
        $managers = User::where('name', '!=', 'Deleted User')->where('role', 'manager')->count();
        $cashiers = User::where('name', '!=', 'Deleted User')->where('role', 'cashier')->count();

        // Users by role (for charts)
        $usersByRole = [
            ['name' => 'Owner', 'value' => $owners, 'role' => 'owner'],
            ['name' => 'Manager', 'value' => $managers, 'role' => 'manager'],
            ['name' => 'Cashier', 'value' => $cashiers, 'role' => 'cashier'],
        ];

        // Recent users (last 30 days)
        $recentUsers = User::where('name', '!=', 'Deleted User')->where('created_at', '>=', now()->subDays(30))->count();
        $previousUsers = User::where('name', '!=', 'Deleted User')->where('created_at', '>=', now()->subDays(60))
            ->where('created_at', '<', now()->subDays(30))
            ->count();

        $userTrend = $previousUsers > 0
            ? round((($recentUsers - $previousUsers) / $previousUsers) * 100, 1)
            : ($recentUsers > 0 ? 100 : 0);

        // Recent products (last 10)
        $recentProducts = Product::orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'is_active' => $product->is_active,
                    'is_featured' => $product->is_featured,
                    'created_at' => $product->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            // User stats
            'totalUsers'    => $totalUsers,
            'activeUsers'   => $activeUsers,
            'inactiveUsers' => $inactiveUsers,
            'owners'        => $owners,
            'managers'      => $managers,
            'cashiers'      => $cashiers,
            'recentUsers'   => $recentUsers,
            'userTrend'     => $userTrend,

            // User breakdowns for charts
            'usersByRole' => $usersByRole,

            // Product stats
            'totalProducts' => $totalProducts,
            'activeProducts' => $activeProducts,
            'inactiveProducts' => $inactiveProducts,
            'featuredProducts' => $featuredProducts,
            'recentlyAdded' => $recentlyAdded,
            'productTrend' => $productTrend,

            // Recent activity
            'recentProducts' => $recentProducts,

            // Period info
            'period' => [
                'days' => $days,
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => now()->format('Y-m-d'),
            ],
        ]);
    }
}