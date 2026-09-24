<?php

namespace App\Http\Controllers;

use App\Models\House;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class HouseController extends Controller
{
    /**
     * Display a listing of houses with their house master and student count.
     */
    public function index()
    {
        $houses = House::with('houseMaster')->withCount('students')->orderBy('name')->paginate(20);

        return view('houses.index', compact('houses'));
    }

    /**
     * Show the form for creating a new house.
     */
    public function create()
    {
        $teachers = Teacher::orderBy('name')->get();

        return view('houses.create', compact('teachers'));
    }

    /**
     * Store a newly created house in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:houses,name',
            'house_master_id' => 'nullable|exists:teachers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('houses.create')
                ->withErrors($validator)
                ->withInput();
        }

        House::create($request->only(['name', 'house_master_id']));

        return redirect()->route('houses.index')
            ->with('success', 'House created successfully.');
    }

    /**
     * Show the form for editing the specified house.
     */
    public function edit(House $house)
    {
        $teachers = Teacher::orderBy('name')->get();

        return view('houses.edit', compact('house', 'teachers'));
    }

    /**
     * Update the specified house in storage.
     */
    public function update(Request $request, House $house)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('houses', 'name')->ignore($house->id)],
            'house_master_id' => 'nullable|exists:teachers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->route('houses.edit', $house)
                ->withErrors($validator)
                ->withInput();
        }

        $house->update($request->only(['name', 'house_master_id']));

        return redirect()->route('houses.index')
            ->with('success', 'House updated successfully.');
    }

    /**
     * Remove the specified house from storage.
     */
    public function destroy(House $house)
    {
        if ($house->students()->count() > 0) {
            return redirect()->route('houses.index')
                ->with('error', 'Cannot delete a house with students assigned to it.');
        }

        $house->delete();

        return redirect()->route('houses.index')
            ->with('success', 'House deleted successfully.');
    }
}
