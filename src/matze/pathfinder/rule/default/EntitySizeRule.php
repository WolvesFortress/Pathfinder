<?php

declare(strict_types=1);

namespace matze\pathfinder\rule\default;

use matze\pathfinder\rule\Rule;
use matze\pathfinder\setting\Settings;
use matze\pathfinder\world\FictionalWorld;
use pocketmine\block\Block;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\math\Vector3;

/**
 * Useful for every normal walking entity
 */
class EntitySizeRule extends Rule{
	protected int $halfWidth;
	protected int $height;

	public function __construct(
		EntitySizeInfo $size,
		int $priority = self::PRIORITY_HIGHEST
	){
		parent::__construct($priority);
		$this->halfWidth = (int) round($size->getWidth() / 2, PHP_ROUND_HALF_DOWN);
		$this->height = (int) ceil(max($size->getHeight() - 1, 1));
	}

	public function couldWalkTo(Vector3 $currentNode, Vector3 $targetNode, FictionalWorld $world, Settings $settings, int &$cost) : bool{
		if($this->couldStandAt($targetNode, $world, $cost)){
			error_log("EntitySizeRule: Valid position at " . $targetNode->__toString());
			return true;
		}

		// Try moving down
		for($yy = 0; $yy <= $settings->getMaxTravelDistanceDown(); $yy++){
			$down = $targetNode->down($yy);
			if(!$this->isAreaClear($down, $world)){
				error_log("EntitySizeRule: Area not clear at " . $down->__toString());
				break;
			}
			if($this->couldStandAt($down, $world, $cost)){
				$targetNode->y -= $yy;
				error_log("EntitySizeRule: Adjusted position to " . $targetNode->__toString());
				return true;
			}
		}

		// Try moving up
		for($yy = 1; $yy <= $settings->getMaxTravelDistanceUp(); $yy++){
			if(!$this->isAreaClear($currentNode->up($yy), $world)){
				error_log("EntitySizeRule: Area not clear at " . $currentNode->up($yy)->__toString());
				break;
			}
			if($this->couldStandAt($targetNode->up($yy), $world, $cost)){
				$targetNode->y += $yy;
				error_log("EntitySizeRule: Adjusted position to " . $targetNode->__toString());
				return true;
			}
		}

		error_log("EntitySizeRule: No valid position found for " . $targetNode->__toString());
		return false;
	}

	public function couldStandAt(Vector3 $node, FictionalWorld $world, int &$cost) : bool{
		return $this->hasBlockBelow($node, $world) && $this->isAreaClear($node, $world);
	}

	protected function hasBlockBelow(Vector3 $center, FictionalWorld $world) : bool{
		for($xx = -$this->halfWidth; $xx <= $this->halfWidth; $xx++){
			for($zz = -$this->halfWidth; $zz <= $this->halfWidth; $zz++){
				if($this->isSolid($world->getBlock($center->add($xx, -1, $zz)))){
					return true;
				}
			}
		}
		return false;
	}

	protected function isAreaClear(Vector3 $center, FictionalWorld $world) : bool{
		for($xx = -$this->halfWidth; $xx <= $this->halfWidth; $xx++){
			for($zz = -$this->halfWidth; $zz <= $this->halfWidth; $zz++){
				for($yy = 0; $yy <= $this->height; $yy++){
					if(!$this->isPassable($world->getBlock($center->add($xx, $yy, $zz)))){
						return false;
					}
				}
			}
		}
		return true;
	}

	protected function isSolid(Block $block) : bool{
		return $block->isFullCube();
	}

	protected function isPassable(Block $block) : bool{
		// Allow more blocks to be passable for small entities
		return $block->canBeReplaced() || $block->isTransparent();
	}
}