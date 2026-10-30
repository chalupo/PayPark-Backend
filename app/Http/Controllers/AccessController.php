<?php

namespace App\Http\Controllers;

use App\Models\Access;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccessController extends Controller
{
    private const CAPACITY = 50;

    public function index(): JsonResponse
    {
        return response()->json(['data' => Access::orderBy('id')->get()]);
    }

    public function show(Access $access): JsonResponse
    {
        return response()->json(['data' => $access]);
    }

    public function entry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
        ]);

        return DB::transaction(function () use ($data): JsonResponse {
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);

            if ($vehicle->accesses()->where('status', 'active')->exists()) {
                return response()->json(['message' => 'El vehículo ya tiene una entrada activa.'], 409);
            }

            if (Access::where('status', 'active')->count() >= self::CAPACITY) {
                return response()->json(['message' => 'El estacionamiento está lleno.'], 409);
            }

            $access = $vehicle->accesses()->create([
                'entry_at' => now(),
                'exit_at' => null,
                'status' => 'active',
            ]);

            return response()->json(['data' => $access], 201);
        });
    }

    public function exit(Vehicle $vehicle): JsonResponse
    {
        return DB::transaction(function () use ($vehicle): JsonResponse {
            $access = $vehicle->accesses()->where('status', 'active')->first();

            if ($access === null) {
                return response()->json(['message' => 'El vehículo no tiene una entrada activa.'], 409);
            }

            $access->update([
                'exit_at' => now(),
                'status' => 'closed',
            ]);

            return response()->json(['data' => $access->refresh()]);
        });
    }

    public function availability(): JsonResponse
    {
        $occupied = Access::where('status', 'active')->count();

        return response()->json([
            'capacity' => self::CAPACITY,
            'occupied' => $occupied,
            'available' => self::CAPACITY - $occupied,
        ]);
    }
}
