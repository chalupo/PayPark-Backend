<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackendIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_parking_and_payment_flow(): void
    {
        $user = User::factory()->create();
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $vehicleId = $this->postJson('/api/vehicles', [
            'user_id' => $user->id, 'plate' => 'FLOW-001',
            'brand' => 'Nissan', 'model' => 'Versa', 'color' => 'Azul',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/availability')->assertOk()->assertExactJson([
            'capacity' => 50, 'occupied' => 0, 'available' => 50,
        ]);

        $accessId = $this->postJson('/api/accesses/entry', ['vehicle_id' => $vehicleId])
            ->assertCreated()->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.exit_at', null)->json('data.id');

        $this->getJson('/api/availability')->assertOk()->assertExactJson([
            'capacity' => 50, 'occupied' => 1, 'available' => 49,
        ]);
        $this->postJson('/api/accesses/entry', ['vehicle_id' => $vehicleId])->assertStatus(409);
        $this->assertDatabaseCount('accesses', 1);

        $this->travel(1)->hours();
        $this->postJson('/api/accesses/'.$vehicleId.'/exit')->assertOk()
            ->assertJsonPath('data.id', $accessId)->assertJsonPath('data.status', 'closed');
        $this->assertDatabaseHas('accesses', ['id' => $accessId, 'status' => 'closed']);

        $this->getJson('/api/availability')->assertOk()->assertExactJson([
            'capacity' => 50, 'occupied' => 0, 'available' => 50,
        ]);
        $this->postJson('/api/accesses/'.$vehicleId.'/exit')->assertStatus(409);

        $paymentId = $this->postJson('/api/payments', ['access_id' => $accessId, 'amount' => '25.00'])
            ->assertCreated()->assertJsonPath('data.access_id', $accessId)
            ->assertJsonPath('data.status', 'paid')->json('data.id');

        $this->postJson('/api/payments', ['access_id' => $accessId, 'amount' => '25.00'])
            ->assertUnprocessable()->assertJsonValidationErrors('access_id');
        $this->assertDatabaseCount('payments', 1);

        $this->getJson('/api/payments/'.$paymentId)->assertOk()
            ->assertJsonPath('data.id', $paymentId)->assertJsonPath('data.access_id', $accessId)
            ->assertJsonPath('data.amount', '25.00');
        $this->getJson('/api/accesses')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $accessId)->assertJsonPath('data.0.status', 'closed');
        $this->getJson('/api/payments')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $paymentId)->assertJsonPath('data.0.access_id', $accessId);

        // Registrar el pago no vuelve a ocupar un lugar ni modifica el acceso cerrado.
        $this->getJson('/api/availability')->assertOk()->assertExactJson([
            'capacity' => 50, 'occupied' => 0, 'available' => 50,
        ]);
        $this->getJson('/api/accesses/'.$accessId)->assertOk()->assertJsonPath('data.status', 'closed');
    }
}
