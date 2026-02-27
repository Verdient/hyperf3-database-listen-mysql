<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Database\Listen\MySQL;

use MySQLReplication\Event\DTO\DeleteRowsDTO;
use MySQLReplication\Event\DTO\UpdateRowsDTO;
use MySQLReplication\Event\DTO\WriteRowsDTO;
use MySQLReplication\Event\EventSubscribers;
use Override;
use Verdient\Hyperf3\Database\Listen\Event;
use Verdient\Hyperf3\Database\Listen\EventDispatcher;
use Verdient\Hyperf3\Database\Listen\EventModels;
use Verdient\Hyperf3\Database\Listen\ModelClassResolverInterface;
use Verdient\Hyperf3\Database\Model\ModelInterface;
use Verdient\Hyperf3\Database\Model\Utils;

/**
 * 事件订阅者
 *
 * @author Verdient。
 */
class EventSubscriber extends EventSubscribers
{
    /**
     * @var array<string,class-string<ModelInterface>
     *
     * @author Verdient。
     */
    protected array $classes;

    /**
     * 调度器
     *
     * @author Verdient。
     */
    protected EventDispatcher $dispatcher;

    /**
     * @param ModelClassResolverInterface $modelClassResolver 模型类处理器
     *
     * @author Verdient。
     */
    public function __construct(
        protected ModelClassResolverInterface $modelClassResolver
    ) {
        $this->dispatcher = new EventDispatcher();
    }

    /**
     * @author Verdient。
     */
    #[Override]
    public function onDelete(DeleteRowsDTO $event): void
    {
        if ($modelClass = $this->modelClassResolver->resolve($event->tableMap->table)) {
            $eventModels = new EventModels(Event::DELETE, $modelClass);

            foreach ($event->values as $value) {
                $eventModels->add($modelClass::createWithOriginals(Utils::deserialize($modelClass, $value)));
            }

            $this->dispatcher->dispatch($eventModels);
        }

        parent::onDelete($event);
    }

    /**
     * @author Verdient。
     */
    #[Override]
    public function onWrite(WriteRowsDTO $event): void
    {
        if ($modelClass = $this->modelClassResolver->resolve($event->tableMap->table)) {

            $eventModels = new EventModels(Event::INSERT, $modelClass);

            foreach ($event->values as $value) {
                $eventModels->add($modelClass::createWithOriginals(Utils::deserialize($modelClass, $value)));
            }

            $this->dispatcher->dispatch($eventModels);
        }

        parent::onWrite($event);
    }

    /**
     * @author Verdient。
     */
    #[Override]
    public function onUpdate(UpdateRowsDTO $event): void
    {
        if ($modelClass = $this->modelClassResolver->resolve($event->tableMap->table)) {

            $eventModels = new EventModels(Event::UPDATE, $modelClass);

            foreach ($event->values as $value) {
                $model = Utils::createModelWithOriginals($modelClass, $value['before']);

                foreach (Utils::deserialize($modelClass, $value['after']) as $key => $value) {
                    $model->setAttribute($key, $value);
                }

                $eventModels->add($model);
            }

            $this->dispatcher->dispatch($eventModels);
        }

        parent::onUpdate($event);
    }
}
