<?php

declare(strict_types=1);

namespace matze\pathfinder;

use matze\pathfinder\node\Node;
use matze\pathfinder\result\PathResult;
use matze\pathfinder\rule\Rule;
use matze\pathfinder\setting\Settings;
use matze\pathfinder\world\FictionalWorld;
use pocketmine\math\Vector3;

class BasePathfinder {
    private const SIDES = [
        [0, 1],
        [1, 0],
        [0, -1],
        [-1, 0],
    ];

    /** @var Node[]  */
    private array $openList = [];

    /**
     * @param Rule[] $rules
     */
    public function __construct(
        private FictionalWorld $world,
        private Settings $settings,
        private float $timeout,
        private array $rules,
    ){}

    public function isNicePositionToWalk(Vector3 $current, Vector3 $target, int &$cost): bool {
        foreach($this->rules as $rule) {
            if(!$rule->couldWalkTo($current, $target, $this->world, $this->settings, $cost)) {
                return false;
            }
        }
        return true;
    }

    public function isNicePositionToStand(Vector3 $target, int &$cost): bool {
        foreach($this->rules as $rule) {
            if(!$rule->couldStandAt($target, $this->world, $cost)) {
                return false;
            }
        }
        return true;
    }

    public function findPath(Vector3 $startVector, Vector3 $targetVector): ?PathResult {
        if($startVector->floor()->equals($targetVector->floor())) {
            return null;
        }
		// Calculate direction vector once
		$directionVector = $targetVector->subtractVector($startVector);
		// Store raw distance for scaling purposes
		$totalDistance = $directionVector->length();
		$directionVector = $directionVector->normalize();

        $rules = [];
        foreach($this->rules as $rule) {
            $rules[$rule->getPriority()."_".$rule::class] = $rule;
        }
        krsort($rules, SORT_NUMERIC);
        $this->rules = array_values($rules);

        $this->openList = [];
        $closedList = [];

        $startNode = Node::fromVector3($startVector);
        $startNode->setG(0.0);

        $targetNode = Node::fromVector3($targetVector);

        $this->openList[$startNode->getHash()] = $startNode;

        $bestNode = null;

        $result = new PathResult();

        $startTime = microtime(true);
        while((microtime(true) - $startTime) < $this->timeout) {
            $key = $this->getLowestFCost();
            if($key === null){
                break;
            }
            /** @var Node $currentNode */
            $currentNode = $this->openList[$key];

            unset($this->openList[$currentNode->getHash()]);
            $closedList[$currentNode->getHash()] = $currentNode;

            if($currentNode->getHash() === $targetNode->getHash()) {
                $targetNode->setParentNode($currentNode);
                $result->addNode($targetNode);
                break;
            }

            foreach(self::SIDES as $SIDE) {
                $x = (int)floor($currentNode->x + $SIDE[0]);
                $y = $currentNode->y;
                $z = (int)floor($currentNode->z + $SIDE[1]);
                $node = new Node($x + 0.5, $y, $z + 0.5);
                $cost = 1;
                if(!$this->isNicePositionToWalk($currentNode, $node, $cost)) {
                    continue;
                }
                if(isset($closedList[$node->getHash()])) {
                    continue;
                }
                if(!isset($this->openList[$node->getHash()]) || ($currentNode->getG() + $cost) < $node->getG()) {
                    $node->setG(($currentNode->getG() + $cost));
//					$node->setH($this->calculateSimpleHeuristic($node, $targetVector));
					$node->setH($this->calculateDirectionalHeuristic($node, $targetVector, $totalDistance, $directionVector));
					$node->setParentNode($currentNode);
                    $this->openList[$node->getHash()] = $node;
                    if($bestNode === null || $bestNode->getH() > $node->getH()) {
                        $bestNode = $node;
                    }
                }
            }
        }
        $node = $targetNode->getParentNode();
        if($node === null) {
            $node = $bestNode;
            if($node === null) {
                return null;
            }
        }
        $result->addNode($node);

        $start = null;
        $clear = false;
        while(true) {
            $last = clone $node;
            $node = $node->getParentNode();
            if(!$node instanceof Node) {
                $result->addNode($last);
                break;
            }
            if($start === null) {
                $start = $last;
            }
            if($start !== null && $this->isClearBetweenPoints($start, $node)) {
                $clear = true;
                continue;
            }
            if($clear) {
                $result->addNode($last);
                $clear = false;
                $start = null;
                $node = $last;
            } else {
                $result->addNode($node);
                $start = null;
            }
        }
        $result->nodes = array_reverse($result->nodes);
        return $result;
    }

    protected function tryTravelDown(Node $current, Node $target, int &$cost): bool {
        return $this->isNicePositionToWalk($current, $target->down($this->settings->getMaxTravelDistanceDown()), $cost);
    }

    protected function tryTravelUp(Node $current, Node $target, int &$cost): bool {
        return $this->isNicePositionToWalk($current, $target->up($this->settings->getMaxTravelDistanceDown()), $cost);
    }

    protected function getLowestFCost(): ?int {
        $openList = [];
        foreach($this->openList as $hash => $node) {
            $openList[$hash] = $node->getF();
        }
        asort($openList);
        return array_key_first($openList);
    }

	protected function calculateSimpleHeuristic(Node $node, Vector3 $targetVector) : float{
		// Manhattan distance is often better for grid-based movement
		$dx = abs($node->x - $targetVector->x);
		$dy = abs($node->y - $targetVector->y);
		$dz = abs($node->z - $targetVector->z);

		// Combine Manhattan distance with a small Euclidean component
		// This will encourage more direct paths without risking pathfinding errors
		$manhattan = $dx + $dy + $dz;
//		$euclidean = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

		return $manhattan/* * 0.8 + $euclidean * 0.2*/ ;
	}

	protected function calculateDirectionalHeuristic(Node $node, Vector3 $targetVector, float $maxDistance, Vector3 $directionVector) : float{
		// Basic distance to target
		$distanceToTarget = $this->calculateSimpleHeuristic($node, $targetVector);

		// Calculate how aligned this node is with the desired direction
		$nodeToTarget = $targetVector->subtractVector($node);
		// Calculate the distance to the end point to have less penalty the closer you are, otherwise it might give high penalty for nodes close to the goal since the angle is different
		$nodeDistanceToTarget = $nodeToTarget->length();
		$nodeToTargetNorm = $nodeToTarget->normalize();

		// Dot product measures alignment (1 = perfect alignment, 0 = perpendicular, -1 = opposite)
		$alignment = $nodeToTargetNorm->dot($directionVector);

		// Reward nodes that are well-aligned with the target direction
		// This basically says "prefer nodes that are along the direct path"
		$alignmentWeight = abs(($maxDistance - $nodeDistanceToTarget) ** 2); // Adjust as needed

		return $distanceToTarget ** 2 * 0.8 - ($alignment * $alignmentWeight);
	}

    protected function isClearBetweenPoints(Vector3 $vec1, Vector3 $vec2): bool {
        if(!$this->settings->isPathSmoothing() || $vec1->getFloorY() !== $vec2->getFloorY()) {
            return false;
        }
        $distance = $vec1->distance($vec2);
        $rayPos = clone $vec1;
        $direction = $vec2->subtractVector($vec1)->normalize();
        if($distance < $direction->length()) {
            return true;
        }
        while($distance > $vec1->distance($rayPos)) {
            $cost = 0;
            if(!$this->isNicePositionToStand($rayPos, $cost)) {
                return false;
            }
            $rayPos = $rayPos->addVector($direction);
        }
        return true;
    }
}