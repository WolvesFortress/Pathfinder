<?php

declare(strict_types=1);

namespace matze\pathfinder\node;

use pocketmine\math\Vector3;
use pocketmine\world\World;

class Node extends Vector3 {
	private ?Node $parentNode = null;
	private float $g = PHP_INT_MAX; // cost from start to this node (for A*)
	private float $h = 0; // heuristic cost from this node to goal (for A*)

//    private ?Node $parentNode = null;

    private int $hash;

    public function __construct(float|int $x, float|int $y, float|int $z){
        $this->hash = World::blockHash((int)floor($x), (int)floor($y), (int)floor($z));
        parent::__construct($x, $y, $z);
    }

    public function getF(): float {
        return $this->h + $this->g;
    }

    public function getG(): float{
        return $this->g;
    }

    public function getH(): float{
        return $this->h;
    }

    public function setG(float $g): void{
        $this->g = $g;
    }

    public function setH(float $h): void{
        $this->h = $h;
    }

    public function getParentNode(): ?Node{
        return $this->parentNode;
    }

    public function setParentNode(?Node $parentNode): void{
        $this->parentNode = $parentNode;
    }

    public function getHash(): int{
        return $this->hash;
    }

    public static function fromVector3(Vector3 $vector3): Node {
        return new Node($vector3->getFloorX() + 0.5, $vector3->getFloorY(), $vector3->getFloorZ() + 0.5);
    }


	/**
	 * Create a Node from a hash
	 */
	public static function fromHash(int $hash) : self{
		World::getBlockXYZ($hash, $x, $y, $z);
		return self::fromVector3(new Vector3($x, $y, $z));
	}
}