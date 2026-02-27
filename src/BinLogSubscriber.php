<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Database\Listen\MySQL;

use MySQLReplication\Event\DTO\EventDTO;
use MySQLReplication\Event\EventSubscribers;
use Override;

/**
 * 二进制文件订阅者
 *
 * @author Verdient。
 */
class BinLogSubscriber extends EventSubscribers
{

    /**
     * @param BinLog $binLog 二进制文件
     *
     * @author Verdient。
     */
    public function __construct(protected BinLog $binLog) {}

    /**
     * @author Verdient。
     */
    #[Override]
    protected function allEvents(EventDTO $event): void
    {
        $binLogCurrent = $event->getEventInfo()->binLogCurrent;

        $this->binLog->fileName = $binLogCurrent->getBinFileName();

        $this->binLog->position = $binLogCurrent->getBinLogPosition();

        parent::allEvents($event);
    }
}
