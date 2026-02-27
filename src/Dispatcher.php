<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Database\Listen\MySQL;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ProcessInterface;
use MySQLReplication\BinLog\BinLogException;
use MySQLReplication\Config\ConfigBuilder;
use MySQLReplication\Definitions\ConstEventType;
use MySQLReplication\MySQLReplicationFactory;
use Override;
use Ramsey\Uuid\Uuid;
use Swoole\Process;
use Throwable;
use Verdient\Hyperf3\Database\Listen\AbstractDatabaseEventDispatcher;
use Verdient\Hyperf3\Database\Listen\MySQL\BinLog;
use Verdient\Hyperf3\Database\Listen\MySQL\BinLogSubscriber;
use Verdient\Hyperf3\Database\Listen\MySQL\EventSubscriber;
use Verdient\Hyperf3\Database\Listen\ObservationException;

/**
 * MySQL数据库事件调度器
 *
 * @author Verdient。
 */
class Dispatcher extends AbstractDatabaseEventDispatcher
{
    /**
     * @author Verdient。
     */
    #[Override]
    public function handle(): void
    {
        $filename = md5($this->connectionName . '-bin-log.state');

        $binLog = new BinLog(BASE_PATH . '/runtime/app/state/' . $filename);

        foreach ([SIGINT, SIGTERM] as $signal) {
            $handler = pcntl_signal_get_handler($signal);

            Process::signal($signal, function () use ($binLog, $signal, $handler) {
                if (is_callable($handler)) {
                    Process::signal($signal, $handler);
                } else {
                    pcntl_signal($signal, $handler);
                }
                $binLog->save();
                $this->logger()->info('MySQL Event Dispatcher stoped at ' . $binLog->fileName . ':' . $binLog->position . '.');
                Process::kill(posix_getpid(), $signal);
            });
        }

        /** @var ConfigInterface */
        $config = $this->container->get(ConfigInterface::class);

        $config = $config->get('databases.' . $this->connectionName);

        $startFileName = $binLog->fileName;
        $startPosition = $binLog->position;

        while (true) {

            try {
                $factory = new MySQLReplicationFactory(
                    (new ConfigBuilder())
                        ->withUser($config['username'])
                        ->withHost($config['host'])
                        ->withPassword($config['password'])
                        ->withPort($config['port'])
                        ->withHeartbeatPeriod(5)
                        ->withSlaveUuid(Uuid::uuid4()->toString())
                        ->withBinLogFileName($binLog->fileName ?: '')
                        ->withBinLogPosition($binLog->position ?: '')
                        ->withEventsIgnore([ConstEventType::HEARTBEAT_LOG_EVENT->value])
                        ->withDatabasesOnly([$config['database']])
                        ->withTablesOnly($this->tables)
                        ->build()
                );

                $factory->registerSubscriber(new EventSubscriber($this->modelClassResolver));

                $factory->registerSubscriber(new BinLogSubscriber($binLog));

                $this->logger()->info('MySQL Event Dispatcher started' . ($binLog->fileName !== null && $binLog->position !== null ? (' at ' . $binLog->fileName . ':' . $binLog->position) : '') . '.');

                $factory->run();
            } catch (\Throwable $e) {
                $this->logger()->info('MySQL Event Dispatcher stoped at ' . $binLog->fileName . ':' . $binLog->position . '.');

                $this->logger()->error($e);

                if (
                    $startFileName === $binLog->fileName &&
                    $startPosition === $binLog->position &&
                    $e instanceof BinLogException
                ) {
                    $binLog->fileName = null;
                    $binLog->position = null;
                    continue;
                }

                if ($e instanceof ObservationException) {
                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * 创建默认的记录器的组名集合
     *
     * @return array<int|string,string>
     * @author Verdient。
     */
    protected function groupsForCreateDefaultLogger(): array
    {
        return [static::class => ProcessInterface::class];
    }
}
