<?php

declare(strict_types=1);

namespace Symfony\Bundle\FrameworkBundle\Test {
    abstract class KernelTestCase extends \PHPUnit\Framework\TestCase
    {
    }

    abstract class WebTestCase extends KernelTestCase
    {
    }
}

namespace Spatie\Snapshots {
    trait MatchesSnapshots
    {
    }
}
