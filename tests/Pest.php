<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| Feature tests boot the framework; Unit tests stay framework-free unless they opt in.
*/
pest()->extend(TestCase::class)->in('Feature');
