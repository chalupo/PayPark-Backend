<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Payment::orderBy('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $data = $request->validate([
                'access_id' => ['required', 'integer', 'exists:accesses,id', 'unique:payments,access_id'],
                'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:99999999.99'],
            ]);

            $payment = Payment::create([
                ...$data,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            return response()->json(['data' => $payment], 201);
        });
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json(['data' => $payment]);
    }
}
