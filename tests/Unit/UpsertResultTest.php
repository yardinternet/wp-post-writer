<?php

declare(strict_types=1);

use Yard\PostWriter\UpsertAction;
use Yard\PostWriter\UpsertResult;

it('carries id and action', function () {
    $result = new UpsertResult(42, UpsertAction::Created);

    expect($result->id)->toBe(42)
        ->and($result->action)->toBe(UpsertAction::Created)
        ->and($result->action->value)->toBe('created');
});
