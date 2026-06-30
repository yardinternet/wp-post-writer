<?php

declare(strict_types=1);

namespace Yard\PostWriter;

enum UpsertAction: string
{
    case Created = 'created';
    case Updated = 'updated';
}
