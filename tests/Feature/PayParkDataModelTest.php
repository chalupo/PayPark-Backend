<?php

namespace Tests\Feature;

use App\Models\Access;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayParkDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_vehicles_accesses_and_payments_are_related(): void
    {
        $user = User::factory()->create();
        $vehicle = $user->vehicles()->create($this->vehicleAttributes());
        $secondVehicle = $user->vehicles()->create($this->vehicleAttributes('XYZ-987'));
        $access = $vehicle->accesses()->create(['entry_at' => now()]);
        $closedAccess = $vehicle->accesses()->create([
            'entry_at' => now()->subHours(2),
            'exit_at' => now(),
            'status' => 'closed',
        ]);
        $payment = $access->payment()->create([
            'amount' => '45.50',
            'paid_at' => now(),
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('accesses', ['id' => $access->id, 'vehicle_id' => $vehicle->id]);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'access_id' => $access->id]);
        $this->assertCount(2, $user->fresh()->vehicles);
        $this->assertTrue($user->vehicles->contains($secondVehicle));
        $this->assertTrue($vehicle->fresh()->user->is($user));
        $this->assertCount(2, $vehicle->fresh()->accesses);
        $this->assertTrue($access->fresh()->vehicle->is($vehicle));
        $this->assertTrue($access->fresh()->payment->is($payment));
        $this->assertTrue($payment->fresh()->access->is($access));
        $this->assertSame('active', $access->fresh()->status);
        $this->assertNull($access->fresh()->exit_at);
        $this->assertSame('closed', $closedAccess->fresh()->status);
        $this->assertInstanceOf(\DateTimeInterface::class, $access->fresh()->entry_at);
        $this->assertInstanceOf(\DateTimeInterface::class, $closedAccess->fresh()->exit_at);
        $this->assertInstanceOf(\DateTimeInterface::class, $payment->fresh()->paid_at);
        $this->assertSame('45.50', $payment->fresh()->amount);
    }

    public function test_vehicle_plates_are_unique(): void
    {
        $user = User::factory()->create();
        $user->vehicles()->create($this->vehicleAttributes());

        $this->expectException(QueryException::class);
        User::factory()->create()->vehicles()->create($this->vehicleAttributes());
    }

    public function test_an_access_can_have_only_one_payment(): void
    {
        $access = $this->createAccess();
        $attributes = ['amount' => '20.00', 'paid_at' => now(), 'status' => 'paid'];
        $access->payment()->create($attributes);

        $this->expectException(QueryException::class);
        $access->payment()->create($attributes);
    }

    public function test_foreign_keys_reject_orphan_records(): void
    {
        $this->expectException(QueryException::class);
        Vehicle::create(['user_id' => 9999, ...$this->vehicleAttributes()]);
    }

    public function test_access_status_rejects_unknown_values(): void
    {
        $access = $this->createAccess();

        $this->expectException(QueryException::class);
        $access->update(['status' => 'unknown']);
    }

    private function createAccess(): Access
    {
        $vehicle = User::factory()->create()->vehicles()->create($this->vehicleAttributes());

        return $vehicle->accesses()->create(['entry_at' => now()]);
    }

    private function vehicleAttributes(string $plate = 'ABC-123'): array
    {
        return ['plate' => $plate, 'brand' => 'Nissan', 'model' => 'Versa', 'color' => 'Azul'];
    }
}
