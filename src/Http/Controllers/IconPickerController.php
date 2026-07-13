<?php

namespace IconPicker\Http\Controllers;

use IconPicker\Icons\IconManager;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IconPickerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private IconManager $manager,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'set'     => ['nullable', 'string'],
            'variant' => ['nullable', 'string', 'in:o,s,m,c,r,tt'],
            'q'       => ['nullable', 'string', 'max:100'],
            'page'    => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->manager->getIcons(
            setPrefix: $validated['set'] ?? null,
            variant: $validated['variant'] ?? null,
            query: $validated['q'] ?? null,
            page: $validated['page'] ?? 1,
        );

        return response()->json([
            'icons'   => array_map(fn ($icon) => $icon->toArray(), $result['icons']),
            'total'   => $result['total'],
            'hasMore' => $result['hasMore'],
        ]);
    }

    public function sets(): JsonResponse
    {
        return response()->json($this->manager->getSets());
    }
}
