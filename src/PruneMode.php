<?php

declare(strict_types=1);

namespace Yard\PostWriter;

enum PruneMode: string
{
	case Delete = 'delete';
	case Trash = 'trash';
	case Draft = 'draft';
}
