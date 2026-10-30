<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Vehicle::orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $vehicle = Vehicle::create($this->validatedData($request));

        return response()->json(['data' => $vehicle], 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        return response()->json(['data' => $vehicle]);
    }

    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->update($this->validatedData($request, $vehicle));

        return response()->json(['data' => $vehicle->refresh()]);
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        if ($vehicle->accesses()->exists()) {
            return response()->json(['message' => 'No se puede eliminar un vehículo con accesos asociados.'], 409);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Vehículo eliminado correctamente.']);
    }

    private function validatedData(Request $request, ?Vehicle $vehicle = null): array
    {
        $uniquePlate = Rule::unique('vehicles', 'plate');

        if ($vehicle !== null) {
            $uniquePlate->ignore($vehicle);
        }

        return $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'plate' => ['required', 'string', 'max:255', $uniquePlate],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:255'],
        ]);
    }
}
