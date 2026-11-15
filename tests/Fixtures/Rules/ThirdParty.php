<?php

declare(strict_types=1);

namespace Doctrine\ORM {
    interface EntityManagerInterface
    {
        public function flush(): void;
    }

    final class EntityManager implements EntityManagerInterface
    {
        public function flush(): void
        {
        }
    }
}

namespace Psr\Log {
    interface LoggerInterface
    {
        public function info(string $message): void;
    }
}
