<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSpecificationDefinition;
use Illuminate\Http\Request;

class SpecDefinitionController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductSpecificationDefinition::query();
        if ($request->filled('applies_to')) {
            $query->where('applies_to', $request->applies_to);
        }
        return response()->json($query->orderBy('sort_order')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:150',
            'unit'       => 'nullable|string|max:30',
            'value_type' => 'required|in:text,number,boolean',
            'applies_to' => 'required|in:phone,accessory,all',
            'sort_order' => 'integer|min:0',
            'is_active'  => 'boolean',
        ]);

        // Auto-generate a unique code from name
        $data['code'] = \Illuminate\Support\Str::slug($data['name'], '_') . '_' . uniqid();

        $def = ProductSpecificationDefinition::create($data);
        return response()->json($def, 201);
    }

    public function show(ProductSpecificationDefinition $specDefinition)
    {
        return response()->json($specDefinition);
    }

    public function update(Request $request, ProductSpecificationDefinition $specDefinition)
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:150',
            'unit'       => 'nullable|string|max:30',
            'value_type' => 'sometimes|in:text,number,boolean',
            'applies_to' => 'sometimes|in:phone,accessory,all',
            'sort_order' => 'integer|min:0',
            'is_active'  => 'boolean',
        ]);
        $specDefinition->update($data);
        return response()->json($specDefinition);
    }

    public function destroy(ProductSpecificationDefinition $specDefinition)
    {
        $specDefinition->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
