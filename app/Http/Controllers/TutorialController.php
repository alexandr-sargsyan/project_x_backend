<?php

namespace App\Http\Controllers;

use App\Models\Tutorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorialController extends Controller
{
    /**
     * Получить список всех tutorials для селектора
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tutorial::query();

        // Поиск по label или tutorial_url
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('label', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('tutorial_url', 'ILIKE', "%{$searchTerm}%");
            });
        }

        $tutorials = $query->select('id', 'label', 'tutorial_url')
            ->orderBy('label')
            ->get();

        return response()->json([
            'data' => $tutorials,
        ]);
    }
}
