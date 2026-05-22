<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function categories()
    {
        $categories = MenuCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order']);

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function items(Request $request)
    {
        $query = MenuItem::query()
            ->with('category:id,name,sort_order')
            ->where('is_active', true)
            ->orderBy('title');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where('title', 'like', "%{$q}%");
        }

        return response()->json([
            'data' => $query->get([
                'id',
                'category_id',
                'title',
                'description',
                'composition',
                'weight',
                'calories',
                'proteins',
                'fats',
                'carbs',
                'price',
                'image_url',
            ]),
        ]);
    }
}
