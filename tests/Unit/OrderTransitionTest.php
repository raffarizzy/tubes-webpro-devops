<?php

use App\Models\Order;

function orderWithStatus(string $status): Order
{
    return (new Order)->forceFill(['status' => $status]);
}

test('pesanan pending boleh menjadi paid atau cancelled', function () {
    $order = orderWithStatus('pending');

    expect($order->canTransitionTo('paid'))->toBeTrue()
        ->and($order->canTransitionTo('cancelled'))->toBeTrue();
});

test('alur normal paid ke processing ke shipped ke finished', function () {
    expect(orderWithStatus('paid')->canTransitionTo('processing'))->toBeTrue()
        ->and(orderWithStatus('processing')->canTransitionTo('shipped'))->toBeTrue()
        ->and(orderWithStatus('shipped')->canTransitionTo('finished'))->toBeTrue();
});

test('pesanan yang sudah dikirim tidak bisa dibatalkan', function () {
    expect(orderWithStatus('shipped')->canTransitionTo('cancelled'))->toBeFalse();
});

test('status akhir tidak bisa berpindah ke status mana pun', function () {
    foreach (['finished', 'cancelled'] as $final) {
        foreach (array_keys(Order::TRANSITIONS) as $target) {
            expect(orderWithStatus($final)->canTransitionTo($target))->toBeFalse();
        }
    }
});

test('tidak boleh melompati tahap', function () {
    expect(orderWithStatus('pending')->canTransitionTo('shipped'))->toBeFalse()
        ->and(orderWithStatus('paid')->canTransitionTo('finished'))->toBeFalse();
});

test('status tidak dikenal ditolak', function () {
    expect(orderWithStatus('refund')->canTransitionTo('paid'))->toBeFalse()
        ->and(orderWithStatus('paid')->canTransitionTo('refund'))->toBeFalse();
});
