<?php

declare(strict_types=1);

namespace Verdient\Hyperf3\Database\Listen\MySQL;

/**
 * 二进制日志
 *
 * @author Verdient。
 */
class BinLog
{
    /**
     * @var string 文件名
     *
     * @author Verdient。
     */
    public ?string $fileName = null;

    /**
     * @var string 位置
     *
     * @author Verdient。
     */
    public ?string $position = null;

    /**
     * 构造函数
     *
     * @param string $cacheFilePath 缓存文件路径
     *
     * @author Verdient。
     */
    public function __construct(protected string $cacheFilePath)
    {
        if (file_exists($cacheFilePath)) {

            $binLog = json_decode(file_get_contents($cacheFilePath), true);

            if (
                is_array($binLog)
                && array_is_list($binLog)
                && count($binLog) === 2
            ) {
                $this->fileName = $binLog[0];
                $this->position = $binLog[1];
            }

            unlink($cacheFilePath);
        }
    }

    /**
     * 保存
     *
     * @author Verdient。
     */
    public function save(): bool
    {
        if ($this->fileName === null) {
            return false;
        }

        if ($this->position === null) {
            return false;
        }

        $dirname = dirname($this->cacheFilePath);

        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }

        return file_put_contents(
            $this->cacheFilePath,
            json_encode([
                $this->fileName,
                $this->position
            ])
        ) !== false;
    }
}
