<?php

declare(strict_types=1);

namespace pathfinder;

use pathfinder\command\PathfinderCommand;
use pathfinder\entity\TestEntity;
use pocketmine\plugin\PluginBase;

class Pathfinder extends PluginBase {
    public static Pathfinder $instance;

    protected function onEnable(): void{
        self::$instance = $this;
    }
}