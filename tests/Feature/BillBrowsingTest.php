<?php

use App\Models\Bill;
use App\Models\User;

test('users can view bills index', function () {
    $response = $this->get(route('bills.index'));

    $response->assertStatus(200);
    $response->assertViewIs('bills.index');
    $response->assertViewHas('bills');
});

test('users can search bills', function () {
    $response = $this->get(route('bills.index', ['search' => 'Healthcare']));

    $response->assertStatus(200);
    $response->assertSee('Healthcare Access and Affordability Act');
    $response->assertDontSee('Education Funding and Student Support Act');
});

test('users can filter bills by chamber', function () {
    $response = $this->get(route('bills.index', ['chamber' => 'house']));

    $response->assertStatus(200);
    $response->assertSee('Healthcare Access and Affordability Act');
    $response->assertSee('Education Funding and Student Support Act');
    $response->assertDontSee('Climate Action and Clean Energy Investment Act');
});

test('users can view bill details', function () {
    $response = $this->get(route('bills.show', '118-hr1234'));

    $response->assertStatus(200);
    $response->assertViewIs('bills.show');
    $response->assertViewHas('bill');
    $response->assertSee('Healthcare Access and Affordability Act');
});

test('authenticated users can track bills', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('bills.track', '118-hr1234'));

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    
    expect($user->trackedBills()->where('congress_id', '118-hr1234')->exists())->toBeTrue();
});

test('authenticated users can untrack bills', function () {
    $user = User::factory()->create();
    
    // First track the bill
    $this->actingAs($user)->post(route('bills.track', '118-hr1234'));

    $response = $this->actingAs($user)
        ->delete(route('bills.untrack', '118-hr1234'));

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
    
    expect($user->trackedBills()->where('congress_id', '118-hr1234')->exists())->toBeFalse();
});

test('unauthenticated users cannot track bills', function () {
    $response = $this->post(route('bills.track', '118-hr1234'));

    $response->assertRedirect(route('login'));
});
