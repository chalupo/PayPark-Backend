<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_vehicle_can_be_registered(): void
    {
        $data = $this->vehicleData();

        $this->postJson('/api/vehicles', $data)
            ->assertCreated()
            ->assertJsonPath('data.plate', $data['plate'])
            ->assertJsonPath('data.user_id', $data['user_id']);

        $this->assertDatabaseHas('vehicles', $data);
    }

    public function test_vehicles_can_be_listed(): void
    {
        $vehicle = Vehicle::create($this->vehicleData());

        $this->getJson('/api/vehicles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $vehicle->id);
    }

    public function test_a_vehicle_can_be_retrieved_by_id(): void
    {
        $vehicle = Vehicle::create($this->vehicleData());

        $this->getJson('/api/vehicles/'.$vehicle->id)
            ->assertOk()
            ->assertJsonPath('data.id', $vehicle->id)
            ->assertJsonPath('data.plate', $vehicle->plate);
    }

    public function test_a_vehicle_can_be_updated_without_changing_its_plate(): void
    {
        $data = $this->vehicleData();
        $vehicle = Vehicle::create($data);
        $data['color'] = 'Rojo';

        $this->putJson('/api/vehicles/'.$vehicle->id, $data)
            ->assertOk()
            ->assertJsonPath('data.color', 'Rojo');

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, ...$data]);
    }

    public function test_a_vehicle_can_be_updated_using_patch(): void
    {
        $data = $this->vehicleData();
        $vehicle = Vehicle::create($data);
        $data['plate'] = 'XYZ-987';

        $this->patchJson('/api/vehicles/'.$vehicle->id, $data)
            ->assertOk()
            ->assertJsonPath('data.plate', 'XYZ-987');

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, ...$data]);
    }

    public function test_a_vehicle_can_be_deleted(): void
    {
        $vehicle = Vehicle::create($this->vehicleData());

        $this->deleteJson('/api/vehicles/'.$vehicle->id)
            ->assertOk()
            ->assertJsonPath('message', 'Vehículo eliminado correctamente.');

        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
    }

    public function test_a_duplicate_plate_cannot_be_registered(): void
    {
        $data = $this->vehicleData();
        Vehicle::create($data);

        $this->postJson('/api/vehicles', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_required_fields_cannot_be_omitted(): void
    {
        $this->postJson('/api/vehicles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'plate', 'brand', 'model', 'color']);

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_user_id_must_exist(): void
    {
        $data = $this->vehicleData();
        $data['user_id'] = 9999;

        $this->postJson('/api/vehicles', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->assertDatabaseCount('vehicles', 0);
    }

    public function test_updates_cannot_use_another_vehicles_plate(): void
    {
        $data = $this->vehicleData();
        Vehicle::create($data);
        $other = Vehicle::create([...$data, 'plate' => 'XYZ-987']);

        $this->putJson('/api/vehicles/'.$other->id, $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plate');

        $this->assertSame('XYZ-987', $other->fresh()->plate);
    }

    public function test_updates_require_valid_complete_data(): void
    {
        $vehicle = Vehicle::create($this->vehicleData());

        $this->putJson('/api/vehicles/'.$vehicle->id, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'plate', 'brand', 'model', 'color']);

        $this->patchJson('/api/vehicles/'.$vehicle->id, [
            ...$vehicle->only(['plate', 'brand', 'model', 'color']),
            'user_id' => 9999,
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_missing_vehicles_return_json_not_found(): void
    {
        $this->get('/api/vehicles/9999')->assertNotFound()->assertJsonStructure(['message']);
        $this->putJson('/api/vehicles/9999', [])->assertNotFound();
        $this->deleteJson('/api/vehicles/9999')->assertNotFound();
    }

    public function test_vehicles_with_related_records_cannot_be_deleted(): void
    {
        $vehicle = Vehicle::create($this->vehicleData());
        $vehicle->accesses()->create(['entry_at' => now()]);

        $this->deleteJson('/api/vehicles/'.$vehicle->id)
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id]);
    }

    private function vehicleData(): array
    {
        return [
            'user_id' => User::factory()->create()->id,
            'plate' => 'ABC-123',
            'brand' => 'Nissan',
            'model' => 'Versa',
            'color' => 'Azul',
        ];
    }
}
